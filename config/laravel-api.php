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

    // The API as an MCP server, for agents: one per version, at
    // /{prefix}/{path}/{version}. Every action is a tool named
    // `controller.action`, called through the same router, middleware and
    // validation as over HTTP, as the caller of the MCP request. Leave an
    // action or a controller out with `'mcp' => false` in getMethods().
    'mcp' => [
        'enabled' => env('LARAVEL_API_MCP', false),

        // The versions served; empty serves every one not in hidden_versions.
        'versions' => [],

        'path' => 'mcp',

        // Run on the MCP endpoint itself — authentication, rate limiting.
        'middleware' => [],

        // Browser origins allowed besides the application's own (`*` for any).
        // A request without an Origin — a client that is not a browser — passes.
        'allowed_origins' => [],

        // The server name agents see; the application's name when empty.
        'name' => env('LARAVEL_API_MCP_NAME'),
    ],

    // The API over JSON-RPC 2.0: one endpoint per version, at
    // /{prefix}/{path}/{version}. Every action is a method named
    // `controller.action`, its input passed by name; `rpc.discover` answers
    // with the OpenRPC document. Leave an action or a controller out with
    // `'jsonrpc' => false` in getMethods().
    'jsonrpc' => [
        'enabled' => env('LARAVEL_API_JSONRPC', false),

        // The versions served; empty serves every one not in hidden_versions.
        'versions' => [],

        'path' => 'rpc',

        // Run on the endpoint itself — authentication, rate limiting.
        'middleware' => [],
    ],

    // The documentation page on /api/doc: @dskripchenko/openapi's standalone
    // reference — the versions with a switcher, search, try-it, code samples,
    // TypeScript types, PDF, and links that keep working (DocLink's anchors).
    'documentation' => [
        // The script: one file, styles included. A pinned version on jsdelivr
        // by default; `php artisan api:doc-assets` copies it into public/ for
        // environments with no access to an external CDN.
        'script' => env('LARAVEL_API_DOC_SCRIPT', 'https://cdn.jsdelivr.net/npm/@dskripchenko/openapi@1.0.0/dist/standalone.js'),

        // The page title; the open version's title when empty.
        'title' => env('LARAVEL_API_DOC_TITLE'),

        // 'en', 'ru', or 'auto' (the browser's language).
        'locale' => env('LARAVEL_API_DOC_LOCALE', 'auto'),

        // 'light', 'dark', or 'system' (follows the OS); the reader can switch.
        'theme' => env('LARAVEL_API_DOC_THEME', 'system'),

        // The "Try it" panel. Requests go from the reader's browser.
        'try_it' => env('LARAVEL_API_DOC_TRY_IT', true),

        // A URL that relays try-it requests (for APIs on another origin
        // without CORS). Null sends them straight from the page.
        'proxy' => env('LARAVEL_API_DOC_PROXY'),

        // The OAuth2 redirect URI the try-it sign-in comes back to; the page's
        // own URL when empty. The identity provider must allow it.
        'oauth_redirect_url' => env('LARAVEL_API_DOC_OAUTH_REDIRECT_URL'),

        // Keep the keys and tokens a reader types: false, 'session' or 'local'.
        'persist_credentials' => env('LARAVEL_API_DOC_PERSIST_CREDENTIALS', false),

        // The languages offered for code samples, in order; null offers all.
        'snippet_targets' => null,

        // The language shown first.
        'default_snippet' => 'curl',
    ],
];
