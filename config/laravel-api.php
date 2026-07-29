<?php

return [
    'prefix' => 'api',

    'uri_pattern' => '{version}/{controller}/{action}',

    'available_methods' => ['get', 'post', 'put', 'patch', 'delete'],

    'openapi_path' => 'public/openapi',

    'doc_middleware' => [],

    // Версии, которые не показываются в списке на /api/doc. Внутренние
    // контуры (панели, служебные API) живут в том же модуле, и без этого
    // их полная карта endpoint'ов попадает в публичную страницу.
    'hidden_versions' => [],

    // Подставлять FQCN контроллера в description каждого endpoint'а.
    // Отладочная деталь: в публичной спеке светит внутреннюю структуру
    // namespace'ов, поэтому по умолчанию выключено.
    'expose_controller_class' => env('LARAVEL_API_EXPOSE_CONTROLLER_CLASS', false),

    // URL Scalar-бандла для страницы документации. По умолчанию внешний CDN
    // (jsdelivr); задайте локальный self-host путь для окружений без доступа
    // к внешнему CDN (например `/vendor/scalar/api-reference.js`).
    'documentation_script' => env('LARAVEL_API_SCALAR_SCRIPT', 'https://cdn.jsdelivr.net/npm/@scalar/api-reference'),
];
