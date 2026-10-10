<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $item->name }} — SDrive</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="{{ $serverUrl }}/web-apps/apps/api/documents/api.js"></script>
</head>
<body class="h-dvh bg-ink text-paper">
    <div class="flex h-12 items-center gap-3 border-b border-line px-4">
        <a class="btn-ghost" href="{{ route('drive') }}">Kembali</a>
        <p class="truncate text-sm">{{ $item->name }}</p>
    </div>
    <div id="editor" class="h-[calc(100dvh-3rem)]"></div>
    <script>
        let editor;
        const config = @json($config);
        config.events = {
            onRequestRefreshFile: function () {
                fetch(@json($configUrl), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }).then(function (response) {
                    return response.json();
                }).then(function (fresh) {
                    editor.refreshFile(fresh);
                });
            },
        };
        editor = new DocsAPI.DocEditor('editor', config);
    </script>
</body>
</html>
