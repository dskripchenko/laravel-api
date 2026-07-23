<?php

return [
    'prefix' => 'api',

    'uri_pattern' => '{version}/{controller}/{action}',

    'available_methods' => ['get', 'post', 'put', 'patch', 'delete'],

    'openapi_path' => 'public/openapi',

    'doc_middleware' => [],

    // URL Scalar-бандла для страницы документации. По умолчанию внешний CDN
    // (jsdelivr); задайте локальный self-host путь для окружений без доступа
    // к внешнему CDN (например `/vendor/scalar/api-reference.js`).
    'documentation_script' => env('LARAVEL_API_SCALAR_SCRIPT', 'https://cdn.jsdelivr.net/npm/@scalar/api-reference'),
];
