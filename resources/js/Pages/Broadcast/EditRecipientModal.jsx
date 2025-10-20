import React, { useState, useEffect } from "react";
import { useForm } from "@inertiajs/react";

export default function EditRecipientModal({ recipient, show, onClose }) {
    const { data, setData, put, processing, errors, reset } = useForm({
        nama_perusahaan: recipient?.nama_perusahaan || "",
        pic: recipient?.pic || "",
        email: recipient?.email || "",
    });

    useEffect(() => {
        if (recipient) {
            setData({
                nama_perusahaan: recipient.nama_perusahaan || "",
                pic: recipient.pic || "",
                email: recipient.email || "",
            });
        }
    }, [recipient]);

    const handleSubmit = (e) => {
        e.preventDefault();

        put(`/broadcast/recipients/${recipient.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
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
                    <div className="modal-header">
                        <h5 className="modal-title">
                            <i className="bi bi-pencil-square me-2"></i>
                            Edit Penerima
                        </h5>
                        <button
                            type="button"
                            className="btn-close"
                            onClick={onClose}
                        ></button>
                    </div>
                    <div className="modal-body">
                        <div className="mb-3">
                            <label
                                htmlFor="nama_perusahaan"
                                className="form-label"
                            >
                                Nama Perusahaan{" "}
                                <span className="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                id="nama_perusahaan"
                                className={`form-control ${
                                    errors.nama_perusahaan ? "is-invalid" : ""
                                }`}
                                value={data.nama_perusahaan}
                                onChange={(e) =>
                                    setData("nama_perusahaan", e.target.value)
                                }
                            />
                            {errors.nama_perusahaan && (
                                <div className="invalid-feedback">
                                    {errors.nama_perusahaan}
                                </div>
                            )}
                        </div>

                        <div className="mb-3">
                            <label htmlFor="pic" className="form-label">
                                PIC
                            </label>
                            <input
                                type="text"
                                id="pic"
                                className={`form-control ${
                                    errors.pic ? "is-invalid" : ""
                                }`}
                                value={data.pic}
                                onChange={(e) => setData("pic", e.target.value)}
                            />
                            {errors.pic && (
                                <div className="invalid-feedback">
                                    {errors.pic}
                                </div>
                            )}
                        </div>

                        <div className="mb-3">
                            <label htmlFor="email" className="form-label">
                                Email <span className="text-danger">*</span>
                            </label>
                            <input
                                type="email"
                                id="email"
                                className={`form-control ${
                                    errors.email ? "is-invalid" : ""
                                }`}
                                value={data.email}
                                onChange={(e) =>
                                    setData("email", e.target.value)
                                }
                            />
                            {errors.email && (
                                <div className="invalid-feedback">
                                    {errors.email}
                                </div>
                            )}
                            <small className="text-muted">
                                * Jika email diubah, status akan berubah menjadi
                                "Updated"
                            </small>
                        </div>
                    </div>
                    <div className="modal-footer">
                        <button
                            type="button"
                            className="btn btn-secondary"
                            onClick={onClose}
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            className="btn btn-primary"
                            onClick={handleSubmit}
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
                </div>
            </div>
        </div>
    );
}
