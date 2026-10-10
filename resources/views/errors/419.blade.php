<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sesi berakhir — SDrive</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-dvh place-items-center bg-ink px-6 text-paper">
    <div class="max-w-md text-center">
        <p class="text-sm text-accent">419</p>
        <h1 class="mt-2 text-2xl font-semibold">Sesi berakhir</h1>
        <p class="mt-2 text-sm text-mist">Muat ulang halaman, lalu coba lagi.</p>
        <a class="btn-primary mt-6" href="{{ route('login') }}">Ke halaman masuk</a>
    </div>
</body>
</html>
