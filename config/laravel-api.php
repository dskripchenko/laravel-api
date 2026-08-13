<?php

return [
    'prefix' => 'api',

    'uri_pattern' => '{version}/{controller}/{action}',

    'available_methods' => ['get', 'post', 'put', 'patch', 'delete'],

    'openapi_path' => 'public/openapi',

    'doc_middleware' => [],

    // The versions that are not shown in the list on /api/doc. The internal
    // surfaces (the panels, the service APIs) live in the same module, and
    // without this their full endpoint map lands on a public page.
    'hidden_versions' => [],

    // Whether to put the controller's FQCN into every endpoint's description.
    // A debugging detail: in a public spec it exposes the internal structure of
    // the namespaces, so it is off by default.
    'expose_controller_class' => env('LARAVEL_API_EXPOSE_CONTROLLER_CLASS', false),

    // The URL of the Scalar bundle for the documentation page. An external CDN
    // (jsdelivr) by default; set a local self-hosted path for environments with
    // no access to an external CDN (`/vendor/scalar/api-reference.js`, say).
    'documentation_script' => env('LARAVEL_API_SCALAR_SCRIPT', 'https://cdn.jsdelivr.net/npm/@scalar/api-reference'),
];
