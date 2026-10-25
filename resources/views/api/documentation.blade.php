<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @if (request()->hasSession())
            {{-- Read by the try-it proxy (if configured) for Laravel's CSRF check. --}}
            <meta name="csrf-token" content="{{ csrf_token() }}">
        @endif
        <title>API Documentation</title>
        <style>
            body { margin: 0; font-family: system-ui, -apple-system, sans-serif; }
            .api-doc-fallback { max-width: 640px; margin: 64px auto; padding: 0 24px; color: #374151; line-height: 1.6; }
            .api-doc-fallback h1 { font-size: 20px; }
            .api-doc-fallback ul { padding-left: 20px; }
        </style>
    </head>
    <body>
        {{-- Fallback: when the reference's script does not load (no CDN,
             offline), the page does not stay blank — the raw OpenAPI specs are
             listed by the server, with no JavaScript involved. --}}
        <div id="api-doc-fallback" class="api-doc-fallback">
            <h1>API Documentation</h1>
            <p>The interactive documentation is loading… If it does not show up, its
               script is unavailable (no connection to the CDN). The raw OpenAPI specs:</p>
            <ul id="api-doc-fallback-list">
                @foreach ($filesData as $file)
                    <li><a href="{{ $file['url'] }}">{{ $file['name'] ?: $file['url'] }}</a></li>
                @endforeach
            </ul>
        </div>

        <div id="app"></div>
        <script src="{{ $documentationScript }}"></script>
        <script>
            // The options are a JS literal (the Blade json directive escapes
            // quotes and apostrophes): a version's description with "caller's"
            // in it cannot break the script. Each source's `slug` is DocLink's
            // document slug, so the anchors DocLink and the IDE plugin build
            // (`#v1/tag/item/GET/v1/item/list`) open the right operation.
            var options = @json($documentationOptions);
            if (typeof DskOpenApi !== 'undefined' && DskOpenApi.createApiReference) {
                var fallback = document.getElementById('api-doc-fallback');
                if (fallback) { fallback.remove(); }
                DskOpenApi.createApiReference('#app', options);
            }
        </script>
    </body>
</html>
