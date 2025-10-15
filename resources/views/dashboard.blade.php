<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h2>Selamat datang di Dashboard</h2>
        <p>Anda sudah login!</p>
        <a href="{{ route('logout') }}" class="btn btn-danger">Logout</a>
    </div>
</body>
</html>
