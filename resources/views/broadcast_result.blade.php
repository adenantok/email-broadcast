<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Hasil Broadcast</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-5">
    <div class="container">
        <h2>Hasil Pengiriman</h2>
        <ul class="list-group mt-3">
            @foreach ($results as $result)
            <li class="list-group-item">{!! $result !!}</li>
            @endforeach
        </ul>
        <a href="{{ route('broadcast.form') }}" class="btn btn-secondary mt-3">Kembali</a>
    </div>
</body>

</html>