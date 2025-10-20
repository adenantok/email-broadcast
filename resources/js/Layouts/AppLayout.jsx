import React from "react";
import { Link } from "@inertiajs/react";

export default function AppLayout({ children }) {
    const currentYear = new Date().getFullYear();

    return (
        <div className="d-flex flex-column min-vh-100">
            {/* Navbar */}
            <nav className="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
                <div className="container">
                    <Link href="/" className="navbar-brand fw-semibold">
                        <i className="bi bi-megaphone-fill me-1"></i> Broadcast
                        System
                    </Link>
                    <button
                        className="navbar-toggler"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#navbarContent"
                        aria-controls="navbarContent"
                        aria-expanded="false"
                        aria-label="Toggle navigation"
                    >
                        <span className="navbar-toggler-icon"></span>
                    </button>
                    <div
                        className="collapse navbar-collapse"
                        id="navbarContent"
                    >
                        <ul className="navbar-nav ms-auto align-items-lg-center">
                            <li className="nav-item">
                                <Link href="/broadcast" className="nav-link">
                                    <i className="bi bi-broadcast me-1"></i>{" "}
                                    Broadcast
                                </Link>
                            </li>
                            <li className="nav-item">
                                <a href="/broadcast/logs" className="nav-link">
                                    <i className="bi bi-journal-text me-1"></i>{" "}
                                    Broadcast Log
                                </a>
                            </li>
                            <li className="nav-item">
                                <a
                                    href="/unsubscribe/logs"
                                    className="nav-link"
                                >
                                    <i className="bi bi-journal-text me-1"></i>{" "}
                                    Unsubscribe Log
                                </a>
                            </li>
                            <li className="nav-item ms-lg-3">
                                <Link
                                    href="/logout"
                                    className="btn btn-outline-light btn-sm"
                                >
                                    <i className="bi bi-box-arrow-right me-1"></i>{" "}
                                    Logout
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            {/* Main Content */}
            <main className="container mb-5 flex-grow-1">{children}</main>

            {/* Footer */}
            <footer className="bg-light text-center py-3 mt-auto border-top">
                <small className="text-muted">
                    &copy; {currentYear} AlifNET Marketing Broadcast
                </small>
            </footer>
        </div>
    );
}
