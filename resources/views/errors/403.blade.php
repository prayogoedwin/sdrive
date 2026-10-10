<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tidak diizinkan — SDrive</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-dvh place-items-center bg-ink px-6 text-paper">
    <div class="max-w-md text-center">
        <p class="text-sm text-accent">403</p>
        <h1 class="mt-2 text-2xl font-semibold">Anda tidak punya akses</h1>
        <a class="btn-primary mt-6" href="{{ route('drive') }}">Kembali ke drive</a>
    </div>
</body>
</html>
