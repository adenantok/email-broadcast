import React, { useState, useEffect, useRef } from "react";
import { router, usePage, useForm } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import EditRecipientModal from "./EditRecipientModal";

export default function BroadcastIndex() {
    const { recipients, templates, filters, flash, component } =
        usePage().props;
    const [search, setSearch] = useState(filters.search || "");
    const [selectedTemplate, setSelectedTemplate] = useState("");
    const [subjectPreview, setSubjectPreview] = useState("-");
    const [showProgressModal, setShowProgressModal] = useState(false);
    const [progress, setProgress] = useState({
        current: 0,
        total: 0,
        percentage: 0,
        logs: [],
        isComplete: false,
    });
    const eventSourceRef = useRef(null);
    const logContainerRef = useRef(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        file: null,
    });

    // Check if we're on the broadcast page
    const isOnBroadcastPage = window.location.pathname === "/broadcast";

    const [showEditModal, setShowEditModal] = useState(false);
    const [editingRecipient, setEditingRecipient] = useState(null);

    // Auto-search (debounced) - ONLY on broadcast page
    useEffect(() => {
        // ✅ Jangan jalankan auto-search jika bukan di halaman broadcast
        if (!isOnBroadcastPage) {
            return;
        }

        // ✅ Jangan update URL jika search kosong DAN belum pernah search
        const currentSearch = new URLSearchParams(window.location.search).get(
            "search"
        );
        if (!search && !currentSearch) {
            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                "/broadcast",
                { search },
                { preserveState: true, replace: true }
            );
        }, 400);
        return () => clearTimeout(timeout);
    }, [search, isOnBroadcastPage]);

    // Auto scroll logs
    useEffect(() => {
        if (logContainerRef.current) {
            logContainerRef.current.scrollTop =
                logContainerRef.current.scrollHeight;
        }
    }, [progress.logs]);

    // Cleanup EventSource on unmount
    useEffect(() => {
        return () => {
            if (eventSourceRef.current) {
                eventSourceRef.current.close();
            }
            // Reset modal state when leaving page
            setShowProgressModal(false);
        };
    }, []);

    // Handle template selection
    const handleTemplateChange = (e) => {
        const templateId = e.target.value;
        setSelectedTemplate(templateId);

        const selectedOption = e.target.selectedOptions[0];
        const subject = selectedOption?.getAttribute("data-subject") || "-";
        setSubjectPreview(subject);

        // Simpan template_id ke session via POST
        if (templateId) {
            fetch("/broadcast/set-template", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]'
                    ).content,
                },
                body: JSON.stringify({ template_id: templateId }),
            }).catch((err) => console.error("Error setting template:", err));
        }
    };

    // Tambahkan function untuk handle edit
    const handleEdit = (recipient) => {
        setEditingRecipient(recipient);
        setShowEditModal(true);
    };

    const handleDelete = (id, email) => {
        if (confirm(`Hapus penerima ${email}?`)) {
            router.delete(`/broadcast/recipients/${id}`, {
                preserveScroll: true,
            });
        }
    };

    // Handle preview
    const handlePreview = () => {
        if (selectedTemplate) {
            window.open(`/broadcast/preview/${selectedTemplate}`, "_blank");
        }
    };

    // Handle file upload
    const handleFileSubmit = (e) => {
        e.preventDefault();

        if (!data.file) {
            alert("Pilih file terlebih dahulu!");
            return;
        }

        post("/broadcast/import", {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                // Reset file input manually
                const fileInput = document.querySelector('input[type="file"]');
                if (fileInput) fileInput.value = "";
            },
        });
    };

    // Add log helper
    const addLog = (message, className = "") => {
        setProgress((prev) => ({
            ...prev,
            logs: [...prev.logs, { message, className }],
        }));
    };

    // Handle send broadcast with SSE
    const handleSendBroadcast = () => {
        if (!selectedTemplate) {
            alert("Pilih template email terlebih dahulu!");
            return;
        }

        if (!confirm("Kirim broadcast ke semua penerima aktif?")) {
            return;
        }

        // Reset dan show modal
        setShowProgressModal(true);
        setProgress({
            current: 0,
            total: 0,
            percentage: 0,
            logs: [],
            isComplete: false,
        });

        // Close existing EventSource if any
        if (eventSourceRef.current) {
            eventSourceRef.current.close();
        }

        // Create new EventSource
        const eventSource = new EventSource("/broadcast/send-stream");
        eventSourceRef.current = eventSource;

        eventSource.addEventListener("message", (e) => {
            const data = JSON.parse(e.data);

            if (data.type === "init") {
                setProgress((prev) => ({
                    ...prev,
                    total: data.total,
                }));
                addLog(`📊 Total penerima: ${data.total}`, "text-info");
                if (data.template) {
                    addLog(`📧 Template: ${data.template}`, "text-info");
                }
            } else if (data.type === "progress") {
                const percentage = Math.round(
                    (data.current / data.total) * 100
                );
                setProgress((prev) => ({
                    ...prev,
                    current: data.current,
                    total: data.total,
                    percentage: percentage,
                }));

                const icon = data.status === "success" ? "✅" : "⚠️";
                const colorClass =
                    data.status === "success" ? "text-success" : "text-danger";
                addLog(`${icon} ${data.email} - ${data.message}`, colorClass);
            } else if (data.type === "complete") {
                setProgress((prev) => ({
                    ...prev,
                    isComplete: true,
                    percentage: 100,
                }));
                addLog(
                    `\n🎉 Selesai! ${data.success} berhasil, ${data.failed} gagal dari ${data.total} email`,
                    "text-primary fw-bold"
                );
                eventSource.close();
            } else if (data.type === "error") {
                addLog(`❌ Error: ${data.message}`, "text-danger fw-bold");
                setProgress((prev) => ({
                    ...prev,
                    isComplete: true,
                }));
                eventSource.close();
            }
        });

        eventSource.addEventListener("error", (err) => {
            console.error("SSE Error:", err);
            addLog("❌ Koneksi terputus atau error", "text-danger fw-bold");
            setProgress((prev) => ({
                ...prev,
                isComplete: true,
            }));
            eventSource.close();
        });
    };

    // Close modal and reload
    const handleCloseModal = () => {
        if (eventSourceRef.current) {
            eventSourceRef.current.close();
        }
        setShowProgressModal(false);
        router.reload({ only: ["recipients"] });
    };

    return (
        <AppLayout>
            <div className="container mt-4">
                <h2 className="mb-4">📢 Broadcast Email Manager</h2>

                {/* Flash Messages */}
                {flash?.success && (
                    <div
                        className="alert alert-success alert-dismissible fade show"
                        role="alert"
                    >
                        {flash.success}
                        <button
                            type="button"
                            className="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Close"
                        ></button>
                    </div>
                )}

                {flash?.error && (
                    <div
                        className="alert alert-danger alert-dismissible fade show"
                        role="alert"
                    >
                        {flash.error}
                        <button
                            type="button"
                            className="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Close"
                        ></button>
                    </div>
                )}

                {/* Progress Modal - Only show on broadcast page */}
                {showProgressModal && isOnBroadcastPage && (
                    <div
                        className="modal fade show d-block"
                        style={{ backgroundColor: "rgba(0,0,0,0.5)" }}
                        tabIndex="-1"
                        role="dialog"
                    >
                        <div className="modal-dialog modal-lg modal-dialog-scrollable">
                            <div className="modal-content">
                                <div className="modal-header bg-primary text-white">
                                    <h5 className="modal-title">
                                        📧 Mengirim Broadcast Email...
                                    </h5>
                                </div>
                                <div className="modal-body">
                                    {/* Progress Bar */}
                                    <div className="mb-3">
                                        <div
                                            className="progress"
                                            style={{ height: "25px" }}
                                        >
                                            <div
                                                className={`progress-bar progress-bar-striped ${
                                                    !progress.isComplete
                                                        ? "progress-bar-animated"
                                                        : ""
                                                } bg-success`}
                                                role="progressbar"
                                                style={{
                                                    width: `${progress.percentage}%`,
                                                }}
                                            >
                                                <span>
                                                    {progress.percentage}%
                                                </span>
                                            </div>
                                        </div>
                                        <p className="text-center mt-2 mb-0">
                                            <span>{progress.current}</span> /{" "}
                                            <span>{progress.total}</span> email
                                        </p>
                                    </div>

                                    {/* Log Container */}
                                    <div
                                        ref={logContainerRef}
                                        className="border rounded p-3 bg-light"
                                        style={{
                                            height: "400px",
                                            overflowY: "auto",
                                            fontFamily: "monospace",
                                            fontSize: "14px",
                                        }}
                                    >
                                        {progress.logs.map((log, idx) => (
                                            <div
                                                key={idx}
                                                className={log.className}
                                            >
                                                {log.message}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                                <div className="modal-footer">
                                    <button
                                        type="button"
                                        className="btn btn-secondary"
                                        onClick={handleCloseModal}
                                        disabled={!progress.isComplete}
                                    >
                                        Tutup
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Pilih Template Email */}
                <div className="card mb-4 border-warning">
                    <div className="card-header bg-warning text-dark">
                        <i className="bi bi-envelope-paper"></i> Pilih Template
                        Email
                    </div>
                    <div className="card-body">
                        <div className="row align-items-end">
                            <div className="col-md-8">
                                <label
                                    htmlFor="templateSelect"
                                    className="form-label fw-bold"
                                >
                                    Template Email:
                                </label>
                                <select
                                    className="form-select form-select-lg"
                                    id="templateSelect"
                                    value={selectedTemplate}
                                    onChange={handleTemplateChange}
                                >
                                    <option value="">
                                        -- Pilih Template Email --
                                    </option>
                                    {templates.map((template) => (
                                        <option
                                            key={template.id}
                                            value={template.id}
                                            data-subject={template.subject}
                                        >
                                            {template.name}
                                        </option>
                                    ))}
                                </select>
                                <small className="text-muted">
                                    Subject:{" "}
                                    <span className="fw-bold">
                                        {subjectPreview}
                                    </span>
                                </small>
                            </div>
                            <div className="col-md-4">
                                <button
                                    type="button"
                                    className="btn btn-outline-primary w-100"
                                    onClick={handlePreview}
                                    disabled={!selectedTemplate}
                                >
                                    <i className="bi bi-eye"></i> Preview Email
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Form Upload Excel */}
                <div className="card mb-4">
                    <div className="card-header bg-primary text-white">
                        📂 Import Daftar Email
                    </div>
                    <div className="card-body">
                        <div className="mb-3">
                            <label htmlFor="file" className="form-label">
                                Pilih file Excel (.xlsx)
                            </label>
                            <input
                                type="file"
                                id="file"
                                className="form-control"
                                accept=".xlsx,.xls"
                                onChange={(e) =>
                                    setData("file", e.target.files[0])
                                }
                            />
                            {errors.file && (
                                <div className="text-danger small mt-1">
                                    {errors.file}
                                </div>
                            )}
                            <small className="text-muted">
                                Kolom urutan: Nama Perusahaan | PIC | Email
                            </small>
                        </div>
                        <button
                            type="button"
                            className="btn btn-success"
                            onClick={handleFileSubmit}
                            disabled={processing || !data.file}
                        >
                            <i className="bi bi-upload"></i>{" "}
                            {processing ? "Uploading..." : "Upload & Import"}
                        </button>
                    </div>
                </div>

                {/* Daftar Penerima */}
                <div className="card mb-4 shadow-sm">
                    <div className="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                        <span>📋 Daftar Penerima</span>
                        <span className="badge bg-light text-dark">
                            Total: {recipients.total}
                        </span>
                    </div>
                    <div className="card-body">
                        {/* Input Pencarian */}
                        <div className="mb-3">
                            <input
                                type="text"
                                className="form-control"
                                placeholder="Cari nama, PIC, atau email..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>

                        {/* Tabel */}
                        <div className="table-responsive">
                            <table className="table table-striped table-hover align-middle mb-0">
                                <thead className="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Nama Perusahaan</th>
                                        <th>PIC</th>
                                        <th>Email</th>
                                        <th className="text-center">
                                            Subscribed
                                        </th>
                                        <th>Status</th>
                                        <th>Terakhir Dikirim</th>
                                        <th>Waktu Unsubscribe</th>
                                        <th className="text-center">
                                            Jumlah Kirim
                                        </th>
                                        <th className="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recipients.data.length > 0 ? (
                                        recipients.data.map((r, index) => (
                                            <tr key={r.id}>
                                                <td>
                                                    {recipients.from + index}
                                                </td>
                                                <td>
                                                    {r.nama_perusahaan || "-"}
                                                </td>
                                                <td>{r.pic || "-"}</td>
                                                <td>
                                                    <small>{r.email}</small>
                                                </td>
                                                <td className="text-center">
                                                    {r.is_subscribed ? (
                                                        <span className="badge bg-success">
                                                            Ya
                                                        </span>
                                                    ) : (
                                                        <span className="badge bg-danger">
                                                            Tidak
                                                        </span>
                                                    )}
                                                </td>
                                                <td>
                                                    <small>
                                                        {r.status || "-"}
                                                    </small>
                                                </td>
                                                <td>
                                                    <small>
                                                        {r.last_sent_at || "-"}
                                                    </small>
                                                </td>
                                                <td>
                                                    <small>
                                                        {r.unsubscribed_at ||
                                                            "-"}
                                                    </small>
                                                </td>
                                                <td className="text-center">
                                                    <span className="badge bg-info">
                                                        {r.sent_count || 0}
                                                    </span>
                                                </td>
                                                <td className="text-center">
                                                    <button
                                                        className="btn btn-sm btn-warning me-1"
                                                        onClick={() =>
                                                            handleEdit(r)
                                                        }
                                                        title="Edit"
                                                    >
                                                        <i className="bi bi-pencil-square"></i>
                                                    </button>
                                                    <button
                                                        className="btn btn-sm btn-danger"
                                                        onClick={() =>
                                                            handleDelete(
                                                                r.id,
                                                                r.email
                                                            )
                                                        }
                                                        title="Hapus"
                                                    >
                                                        <i className="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan="8"
                                                className="text-center text-muted py-4"
                                            >
                                                <i
                                                    className="bi bi-inbox"
                                                    style={{ fontSize: "2rem" }}
                                                ></i>
                                                <p className="mb-0 mt-2">
                                                    Belum ada data penerima.
                                                </p>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {recipients.links && recipients.links.length > 3 && (
                            <div className="d-flex justify-content-between align-items-center mt-3">
                                <div className="text-muted">
                                    Menampilkan {recipients.from} -{" "}
                                    {recipients.to} dari {recipients.total} data
                                </div>
                                <nav>
                                    <ul className="pagination mb-0">
                                        {recipients.links.map((link, index) => (
                                            <li
                                                key={index}
                                                className={`page-item ${
                                                    link.active ? "active" : ""
                                                } ${
                                                    !link.url ? "disabled" : ""
                                                }`}
                                            >
                                                <button
                                                    className="page-link"
                                                    onClick={() => {
                                                        if (link.url) {
                                                            router.get(
                                                                link.url,
                                                                {},
                                                                {
                                                                    preserveState: true,
                                                                    preserveScroll: true,
                                                                }
                                                            );
                                                        }
                                                    }}
                                                    disabled={!link.url}
                                                    dangerouslySetInnerHTML={{
                                                        __html: link.label,
                                                    }}
                                                />
                                            </li>
                                        ))}
                                    </ul>
                                </nav>
                            </div>
                        )}
                    </div>
                </div>

                {/* Tombol Kirim Broadcast */}
                <div className="text-center mb-4">
                    <button
                        type="button"
                        className="btn btn-lg btn-danger px-5"
                        onClick={handleSendBroadcast}
                        disabled={!selectedTemplate}
                    >
                        <i className="bi bi-send-fill"></i> Kirim Broadcast
                        Sekarang
                    </button>
                    <p className="text-muted mt-2">
                        <small>* Pilih template email terlebih dahulu</small>
                    </p>
                </div>
            </div>
            {/* Edit Modal */}
            <EditRecipientModal
                recipient={editingRecipient}
                show={showEditModal}
                onClose={() => {
                    setShowEditModal(false);
                    setEditingRecipient(null);
                }}
            />
        </AppLayout>
    );
}
