import React from "react";
import { useForm } from "@inertiajs/react";

export default function CreateGroupModal({ show, onClose }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: "",
        description: "",
    });

    const handleSubmit = (e) => {
        e.preventDefault();

        post("/broadcast/groups", {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    const handleClose = () => {
        reset();
        onClose();
    };

    if (!show) return null;

    return (
        <div
            className="modal fade show d-block"
            style={{ backgroundColor: "rgba(0,0,0,0.5)" }}
            tabIndex="-1"
        >
            <div className="modal-dialog">
                <div className="modal-content">
                    <div className="modal-header bg-info text-white">
                        <h5 className="modal-title">
                            <i className="bi bi-people-fill me-2"></i>
                            Buat Grup Baru
                        </h5>
                        <button
                            type="button"
                            className="btn-close btn-close-white"
                            onClick={handleClose}
                        ></button>
                    </div>
                    <form onSubmit={handleSubmit}>
                        <div className="modal-body">
                            <div className="mb-3">
                                <label htmlFor="name" className="form-label">
                                    Nama Grup{" "}
                                    <span className="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="name"
                                    className={`form-control ${
                                        errors.name ? "is-invalid" : ""
                                    }`}
                                    value={data.name}
                                    onChange={(e) =>
                                        setData("name", e.target.value)
                                    }
                                    placeholder="Contoh: Pelanggan FTTH"
                                    autoFocus
                                />
                                {errors.name && (
                                    <div className="invalid-feedback">
                                        {errors.name}
                                    </div>
                                )}
                                <small className="text-muted">
                                    Nama grup untuk mengelompokkan penerima
                                    email
                                </small>
                            </div>

                            <div className="mb-3">
                                <label
                                    htmlFor="description"
                                    className="form-label"
                                >
                                    Deskripsi{" "}
                                    <small className="text-muted">
                                        (Opsional)
                                    </small>
                                </label>
                                <textarea
                                    id="description"
                                    className={`form-control ${
                                        errors.description ? "is-invalid" : ""
                                    }`}
                                    value={data.description}
                                    onChange={(e) =>
                                        setData("description", e.target.value)
                                    }
                                    rows="3"
                                    placeholder="Contoh: Grup untuk pelanggan layanan internet fiber optik"
                                ></textarea>
                                {errors.description && (
                                    <div className="invalid-feedback">
                                        {errors.description}
                                    </div>
                                )}
                                <small className="text-muted">
                                    Deskripsi singkat tentang grup ini
                                </small>
                            </div>

                            <div className="alert alert-info mb-0">
                                <i className="bi bi-info-circle me-2"></i>
                                <small>
                                    Setelah grup dibuat, Anda dapat menambahkan
                                    penerima ke grup ini melalui tombol{" "}
                                    <strong>"Tambah Manual"</strong> atau saat
                                    mengimport file Excel.
                                </small>
                            </div>
                        </div>
                        <div className="modal-footer">
                            <button
                                type="button"
                                className="btn btn-secondary"
                                onClick={handleClose}
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                className="btn btn-info text-white"
                                disabled={processing}
                            >
                                {processing ? (
                                    <>
                                        <span className="spinner-border spinner-border-sm me-2"></span>
                                        Membuat...
                                    </>
                                ) : (
                                    <>
                                        <i className="bi bi-plus-circle me-2"></i>
                                        Buat Grup
                                    </>
                                )}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
