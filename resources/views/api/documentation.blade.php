<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>API Documentation</title>
        <style>
            body { margin: 0; font-family: system-ui, -apple-system, sans-serif; }
            .api-doc-fallback { max-width: 640px; margin: 64px auto; padding: 0 24px; color: #374151; line-height: 1.6; }
            .api-doc-fallback h1 { font-size: 20px; }
            .api-doc-fallback ul { padding-left: 20px; }
        </style>
    </head>
    <body>
        {{-- Fallback: если Scalar-бандл не загрузился (нет CDN / офлайн), страница
             не остаётся белой — показываем прямые ссылки на сырые OpenAPI-спеки. --}}
        <div id="api-doc-fallback" class="api-doc-fallback">
            <h1>API Documentation</h1>
            <p>Интерактивная документация загружается… Если она не появилась —
               рендерер недоступен (нет соединения с CDN). Сырые OpenAPI-спеки:</p>
            <ul id="api-doc-fallback-list">
                @foreach ($filesData as $file)
                    <li><a href="{{ $file['url'] }}">{{ $file['name'] ?: $file['url'] }}</a></li>
                @endforeach
            </ul>
        </div>

        <div id="app"></div>
        <script src="{{ $documentationScript ?? 'https://cdn.jsdelivr.net/npm/@scalar/api-reference' }}"></script>
        <script>
            // The specs are embedded as a JS literal (the Blade json
            // directive, JSON_HEX_APOS|JSON_HEX_QUOT). The JSON used to be put
            // into a string in SINGLE quotes: any apostrophe in a version's
            // description ("caller's") brought the script down with a syntax
            // error, and an escaped line break brought down JSON.parse itself.
            // The page stayed empty, and even the fallback list was not built —
            // it was filled by that same broken script (the list is now rendered
            // on the server).
            var sources = @json($filesData).map(function (item) {
                return { url: item.url, title: item.name };
            });
            if (typeof Scalar !== 'undefined' && Scalar.createApiReference) {
                Scalar.createApiReference('#app', { sources: sources });
                var f = document.getElementById('api-doc-fallback');
                if (f) { f.remove(); }
            }
        </script>
    </body>
</html>
