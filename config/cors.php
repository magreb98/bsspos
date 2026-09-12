<?php

return [

    'paths' => ['api/*', 'admin/*', 'commerce/*', 'electronics/*', 'mcp/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // No wildcard fallback: an unset CORS_ALLOWED_ORIGINS now means "no
    // cross-origin access" instead of silently allowing every origin, which
    // is what happened before whenever the env var was missing (it's easy to
    // forget in a new environment since it isn't in .env.example).
    'allowed_origins' => array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGINS', ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => (int) env('CORS_MAX_AGE', 86400),

    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),

];
