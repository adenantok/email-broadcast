import React, { useEffect } from "react";
import { useForm } from "@inertiajs/react";

export default function EditGroupModal({ group, show, onClose }) {
    const { data, setData, put, processing, errors, reset } = useForm({
        name: group?.name || "",
        description: group?.description || "",
    });

    useEffect(() => {
        if (group) {
            setData({
                name: group.name || "",
                description: group.description || "",
            });
        }
    }, [group]);

    const handleSubmit = (e) => {
        e.preventDefault();

        put(`/broadcast/groups/${group.id}`, {
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
                    <div className="modal-header bg-warning">
                        <h5 className="modal-title">
                            <i className="bi bi-pencil-square me-2"></i>
                            Edit Grup
                        </h5>
                        <button
                            type="button"
                            className="btn-close"
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
                                />
                                {errors.name && (
                                    <div className="invalid-feedback">
                                        {errors.name}
                                    </div>
                                )}
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
                                className="btn btn-warning"
                                disabled={processing}
                            >
                                {processing ? (
                                    <>
                                        <span className="spinner-border spinner-border-sm me-2"></span>
                                        Menyimpan...
                                    </>
                                ) : (
                                    <>
                                        <i className="bi bi-save me-2"></i>
                                        Simpan Perubahan
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
