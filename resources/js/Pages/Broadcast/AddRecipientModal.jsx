import React from "react";
import { useForm } from "@inertiajs/react";

export default function AddRecipientModal({ show, onClose, groups }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        nama_perusahaan: "",
        pic: "",
        email: "",
        group_id: "",
    });

    const handleSubmit = (e) => {
        e.preventDefault();

        post("/broadcast/recipients", {
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
                    <div className="modal-header bg-success text-white">
                        <h5 className="modal-title">
                            <i className="bi bi-person-plus-fill me-2"></i>
                            Tambah Penerima Baru
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
                                        errors.nama_perusahaan
                                            ? "is-invalid"
                                            : ""
                                    }`}
                                    value={data.nama_perusahaan}
                                    onChange={(e) =>
                                        setData(
                                            "nama_perusahaan",
                                            e.target.value
                                        )
                                    }
                                    placeholder="Contoh: PT. Example Indonesia"
                                />
                                {errors.nama_perusahaan && (
                                    <div className="invalid-feedback">
                                        {errors.nama_perusahaan}
                                    </div>
                                )}
                            </div>

                            <div className="mb-3">
                                <label htmlFor="pic" className="form-label">
                                    PIC (Person In Charge)
                                </label>
                                <input
                                    type="text"
                                    id="pic"
                                    className={`form-control ${
                                        errors.pic ? "is-invalid" : ""
                                    }`}
                                    value={data.pic}
                                    onChange={(e) =>
                                        setData("pic", e.target.value)
                                    }
                                    placeholder="Contoh: Budi Santoso"
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
                                    placeholder="Contoh: email@example.com"
                                />
                                {errors.email && (
                                    <div className="invalid-feedback">
                                        {errors.email}
                                    </div>
                                )}
                            </div>

                            <div className="mb-3">
                                <label
                                    htmlFor="group_id"
                                    className="form-label"
                                >
                                    Grup{" "}
                                    <small className="text-muted">
                                        (Opsional)
                                    </small>
                                </label>
                                <select
                                    id="group_id"
                                    className={`form-select ${
                                        errors.group_id ? "is-invalid" : ""
                                    }`}
                                    value={data.group_id}
                                    onChange={(e) =>
                                        setData("group_id", e.target.value)
                                    }
                                >
                                    <option value="">-- Pilih Grup --</option>
                                    {groups?.map((group) => (
                                        <option key={group.id} value={group.id}>
                                            {group.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.group_id && (
                                    <div className="invalid-feedback">
                                        {errors.group_id}
                                    </div>
                                )}
                                <small className="text-muted">
                                    Pilih grup untuk mengelompokkan penerima
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
                                className="btn btn-success"
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
                                        Simpan Penerima
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
