<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Covers the API (including the anonymous public-site endpoints under
    | /api/v1/site/*) and the Sanctum CSRF cookie. Origins are pinned to the
    | in-app SPA (FRONTEND_URL) and the public marketing site (PUBLIC_SITE_URL);
    | credentials are supported so the Sanctum cookie session keeps working, which
    | forbids a wildcard origin — hence the explicit list.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_URL'),
        env('PUBLIC_SITE_URL'),
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['content-type', 'accept', 'x-requested-with', 'authorization', 'x-xsrf-token'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
