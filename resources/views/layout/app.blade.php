<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Broadcast System') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="d-flex flex-column min-vh-100">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="{{ url('/') }}">
                <i class="bi bi-megaphone-fill me-1"></i> Broadcast System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent"
                aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item">
                        <a href="{{ route('broadcast.form') }}" class="nav-link">
                            <i class="bi bi-broadcast me-1"></i> Broadcast
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('broadcast.logs') ?? '#' }}" class="nav-link">
                            <i class="bi bi-journal-text me-1"></i> Log
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

    <main class="container mb-5 flex-grow-1">
        @yield('content')
    </main>

    <footer class="bg-light text-center py-3 mt-auto border-top">
        <small class="text-muted">
            &copy; {{ date('Y') }} AlifNET Marketing Broadcast
        </small>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>