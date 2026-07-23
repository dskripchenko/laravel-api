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
            <ul id="api-doc-fallback-list"></ul>
        </div>

        <div id="app"></div>
        <script src="{{ $documentationScript ?? 'https://cdn.jsdelivr.net/npm/@scalar/api-reference' }}"></script>
        <script>
            var sources = JSON.parse('{!! $filesJsonData !!}').map(function (item) {
                return { url: item.url, title: item.name };
            });
            var list = document.getElementById('api-doc-fallback-list');
            sources.forEach(function (s) {
                var li = document.createElement('li');
                var a = document.createElement('a');
                a.href = s.url; a.textContent = s.title || s.url;
                li.appendChild(a); list.appendChild(li);
            });
            if (typeof Scalar !== 'undefined' && Scalar.createApiReference) {
                Scalar.createApiReference('#app', { sources: sources });
                var f = document.getElementById('api-doc-fallback');
                if (f) { f.remove(); }
            }
        </script>
    </body>
</html>
