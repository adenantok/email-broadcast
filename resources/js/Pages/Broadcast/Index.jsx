import React, { useState, useEffect, useRef } from "react";
import { router, usePage, useForm } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import EditRecipientModal from "./EditRecipientModal";
import AddRecipientModal from "./AddRecipientModal";
import CreateGroupModal from "./CreateGroupModal";
import EditGroupModal from "./EditGroupModal";

export default function BroadcastIndex() {
    const { recipients, templates, groups, filters, flash, component } =
        usePage().props;
    const [search, setSearch] = useState(filters.search || "");
    const [selectedTemplate, setSelectedTemplate] = useState("");
    const [selectedGroup, setSelectedGroup] = useState(filters.group || "");
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
    const [showAddModal, setShowAddModal] = useState(false);
    const [showCreateGroupModal, setShowCreateGroupModal] = useState(false);
    const [showEditGroupModal, setShowEditGroupModal] = useState(false);
    const [editingGroup, setEditingGroup] = useState(null);

    // Auto-search (debounced) - ONLY on broadcast page
    useEffect(() => {
        if (!isOnBroadcastPage) {
            return;
        }

        const currentSearch = new URLSearchParams(window.location.search).get(
            "search"
        );
        if (!search && !currentSearch) {
            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                "/broadcast",
                { search, group: selectedGroup },
                { preserveState: true, replace: true, preserveScroll: true }
            );
        }, 400);
        return () => clearTimeout(timeout);
    }, [search, isOnBroadcastPage]);

    // Track previous group to prevent unnecessary reloads
    const prevGroupRef = useRef(selectedGroup);

    // Handle group change - only when actually changed
    useEffect(() => {
        if (!isOnBroadcastPage) {
            return;
        }

        // Skip if group hasn't actually changed
        if (prevGroupRef.current === selectedGroup) {
            return;
        }

        prevGroupRef.current = selectedGroup;

        router.get(
            "/broadcast",
            { search, group: selectedGroup },
            { preserveState: true, replace: true, preserveScroll: true }
        );
    }, [selectedGroup, isOnBroadcastPage]);

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

    const handleDeleteGroup = (id, name) => {
        if (
            confirm(
                `Hapus grup "${name}"?\n\nPeringatan: Penerima di grup ini tidak akan dihapus, hanya grup-nya saja.`
            )
        ) {
            router.delete(`/broadcast/groups/${id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    // Reset selected group if the deleted group was selected
                    if (selectedGroup == id) {
                        setSelectedGroup("");
                    }
                },
            });
        }
    };

    const handleEditGroup = (group) => {
        setEditingGroup(group);
        setShowEditGroupModal(true);
    };

    const handlePreview = () => {
        if (selectedTemplate) {
            window.open(`/broadcast/preview/${selectedTemplate}`, "_blank");
        }
    };

    const handleFileSubmit = (e) => {
        e.preventDefault();

        if (!data.file) {
            alert("Pilih file terlebih dahulu!");
            return;
        }

        // Sertakan group_id yang sedang dipilih
        const formData = new FormData();
        formData.append("file", data.file);
        if (selectedGroup) {
            formData.append("group_id", selectedGroup);
        }

        router.post("/broadcast/import", formData, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                const fileInput = document.querySelector('input[type="file"]');
                if (fileInput) fileInput.value = "";
            },
        });
    };

    const addLog = (message, className = "") => {
        setProgress((prev) => ({
            ...prev,
            logs: [...prev.logs, { message, className }],
        }));
    };

    // Cari function handleSendBroadcast di file BroadcastIndex.jsx Anda
    // REPLACE function handleSendBroadcast yang lama dengan yang ini:

    const handleSendBroadcast = () => {
        if (!selectedTemplate) {
            alert("Pilih template email terlebih dahulu!");
            return;
        }

        const confirmMessage = selectedGroup
            ? `Kirim broadcast ke grup "${
                  groups.find((g) => g.id == selectedGroup)?.name
              }"?`
            : "Kirim broadcast ke semua penerima aktif?";

        if (!confirm(confirmMessage)) {
            return;
        }

        setShowProgressModal(true);
        setProgress({
            current: 0,
            total: 0,
            percentage: 0,
            logs: [],
            isComplete: false,
        });

        if (eventSourceRef.current) {
            eventSourceRef.current.close();
        }

        // ✅ Pass parameter group jika ada
        const eventSource = new EventSource(
            `/broadcast/send-stream${
                selectedGroup ? `?group=${selectedGroup}` : ""
            }`
        );
        eventSourceRef.current = eventSource;

        // ✅ Flag untuk ignore error setelah complete
        let isCompleted = false;

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
                if (data.group) {
                    addLog(`👥 Grup: ${data.group}`, "text-info");
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
                // ✅ PENTING: Set flag SEBELUM update UI
                isCompleted = true;

                setProgress((prev) => ({
                    ...prev,
                    isComplete: true,
                    percentage: 100,
                }));
                addLog(
                    `\n🎉 Selesai! ${data.success} berhasil, ${data.failed} gagal dari ${data.total} email`,
                    "text-primary fw-bold"
                );

                // ✅ Delay sebentar sebelum close
                setTimeout(() => {
                    eventSource.close();
                }, 200);
            } else if (data.type === "error") {
                isCompleted = true;
                addLog(`❌ Error: ${data.message}`, "text-danger fw-bold");
                setProgress((prev) => ({
                    ...prev,
                    isComplete: true,
                }));
                eventSource.close();
            }
        });

        eventSource.addEventListener("error", (err) => {
            // ✅ PENTING: Ignore error jika sudah complete
            if (isCompleted) {
                console.log("✅ Connection closed normally after completion");
                return;
            }

            console.error("SSE Error:", err);
            addLog("❌ Koneksi terputus atau error", "text-danger fw-bold");
            setProgress((prev) => ({
                ...prev,
                isComplete: true,
            }));
            eventSource.close();
        });
    };

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

                {/* Progress Modal */}
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

                {/* Group Management Card */}
                <div className="card mb-4 border-info">
                    <div className="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <span>
                            <i className="bi bi-people-fill"></i> Manajemen Grup
                            Penerima
                        </span>
                        <button
                            type="button"
                            className="btn btn-sm btn-light"
                            onClick={() => setShowCreateGroupModal(true)}
                        >
                            <i className="bi bi-plus-circle"></i> Buat Grup Baru
                        </button>
                    </div>
                    <div className="card-body">
                        <div className="row">
                            <div className="col-md-12">
                                <label
                                    htmlFor="groupSelect"
                                    className="form-label fw-bold"
                                >
                                    Pilih Grup:
                                </label>
                                <select
                                    className="form-select form-select-lg"
                                    id="groupSelect"
                                    value={selectedGroup}
                                    onChange={(e) =>
                                        setSelectedGroup(e.target.value)
                                    }
                                >
                                    <option value="">
                                        -- Semua Penerima --
                                    </option>
                                    {groups?.map((group) => (
                                        <option key={group.id} value={group.id}>
                                            {group.name} (
                                            {group.recipients_count} penerima)
                                        </option>
                                    ))}
                                </select>
                                <small className="text-muted">
                                    Filter penerima berdasarkan grup yang
                                    dipilih
                                </small>
                            </div>
                        </div>

                        {selectedGroup && (
                            <div className="alert alert-info mt-3 mb-0 d-flex justify-content-between align-items-center">
                                <div>
                                    <i className="bi bi-info-circle me-2"></i>
                                    Menampilkan penerima dari grup:{" "}
                                    <strong>
                                        {
                                            groups?.find(
                                                (g) => g.id == selectedGroup
                                            )?.name
                                        }
                                    </strong>
                                </div>
                                <div>
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-warning me-1"
                                        onClick={() =>
                                            handleEditGroup(
                                                groups?.find(
                                                    (g) => g.id == selectedGroup
                                                )
                                            )
                                        }
                                        title="Edit Grup"
                                    >
                                        <i className="bi bi-pencil-square"></i>{" "}
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-danger"
                                        onClick={() => {
                                            const group = groups?.find(
                                                (g) => g.id == selectedGroup
                                            );
                                            handleDeleteGroup(
                                                group.id,
                                                group.name
                                            );
                                        }}
                                        title="Hapus Grup"
                                    >
                                        <i className="bi bi-trash"></i> Hapus
                                    </button>
                                </div>
                            </div>
                        )}
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
                        <div className="d-flex align-items-center gap-2">
                            <span className="badge bg-light text-dark">
                                Total: {recipients.total}
                            </span>
                            <button
                                type="button"
                                className="btn btn-sm btn-success"
                                onClick={() => setShowAddModal(true)}
                            >
                                <i className="bi bi-person-plus-fill"></i>{" "}
                                Tambah Manual
                            </button>
                        </div>
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
                                        <th>Grup</th>
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
                                                <td>
                                                    {r.group_name ? (
                                                        <span className="badge bg-info">
                                                            {r.group_name}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted">
                                                            -
                                                        </span>
                                                    )}
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
                                                colSpan="11"
                                                className="text-center text-muted py-4"
                                            >
                                                <i
                                                    className="bi bi-inbox"
                                                    style={{ fontSize: "2rem" }}
                                                ></i>
                                                <p className="mb-0 mt-2">
                                                    {selectedGroup
                                                        ? "Belum ada penerima di grup ini."
                                                        : "Belum ada data penerima."}
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
                        {selectedGroup && (
                            <span>
                                {" "}
                                ke{" "}
                                {
                                    groups?.find((g) => g.id == selectedGroup)
                                        ?.name
                                }
                            </span>
                        )}
                    </button>
                    <p className="text-muted mt-2">
                        <small>
                            * Pilih template email terlebih dahulu
                            {selectedGroup && (
                                <span>
                                    {" "}
                                    | Mengirim ke grup:{" "}
                                    {
                                        groups?.find(
                                            (g) => g.id == selectedGroup
                                        )?.name
                                    }
                                </span>
                            )}
                        </small>
                    </p>
                </div>
            </div>

            {/* Modals */}
            <EditRecipientModal
                recipient={editingRecipient}
                show={showEditModal}
                onClose={() => {
                    setShowEditModal(false);
                    setEditingRecipient(null);
                }}
                groups={groups}
            />

            <AddRecipientModal
                show={showAddModal}
                onClose={() => setShowAddModal(false)}
                groups={groups}
            />

            <CreateGroupModal
                show={showCreateGroupModal}
                onClose={() => setShowCreateGroupModal(false)}
            />

            <EditGroupModal
                group={editingGroup}
                show={showEditGroupModal}
                onClose={() => {
                    setShowEditGroupModal(false);
                    setEditingGroup(null);
                }}
            />
        </AppLayout>
    );
}
