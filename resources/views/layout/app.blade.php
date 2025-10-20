<!-- resources/views/layout/main.blade.php -->
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Broadcast System') }}</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        html,
        body {
            height: 100%;
        }

        .wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        main {
            flex: 1;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
            <div class="container">
                <a href="/" class="navbar-brand fw-semibold">
                    <i class="bi bi-megaphone-fill me-1"></i> Broadcast System
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarContent">
                    <ul class="navbar-nav ms-auto align-items-lg-center">
                        <li class="nav-item">
                            <a href="/broadcast" class="nav-link">
                                <i class="bi bi-broadcast me-1"></i> Broadcast
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/broadcast/logs" class="nav-link">
                                <i class="bi bi-journal-text me-1"></i> Broadcast Log
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/unsubscribe/logs" class="nav-link">
                                <i class="bi bi-journal-text me-1"></i> Unsubscribe Log
                            </a>
                        </li>
                        <li class="nav-item ms-lg-3">
                            <a href="/logout" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-box-arrow-right me-1"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="container mb-5">
            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="bg-light text-center py-3 mt-auto border-top">
            <small class="text-muted">
                &copy; {{ date('Y') }} AlifNET Marketing Broadcast
            </small>
        </footer>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>