<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>API Documentation</title>
    </head>
    <body>
        <div id="app"></div>
        <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
        <script>
            var sources = JSON.parse('{!! $filesJsonData !!}').map(function (item) {
                return { url: item.url, title: item.name };
            });
            Scalar.createApiReference('#app', { sources: sources });
        </script>
    </body>
</html>
