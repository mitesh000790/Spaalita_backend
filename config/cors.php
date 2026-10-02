<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | Same policy as the original Express `cors()` middleware: every route
    | accepts requests from any origin, so the website (e.g. localhost:5173)
    | and the admin panel can call the API from another host or port.
    |
    | To restrict access, list the allowed sites in CORS_ALLOWED_ORIGINS
    | (comma separated), e.g. "https://spaalita.ca,https://admin.spaalita.ca".
    |
    */

    'paths' => ['*'],

    'allowed_methods' => ['GET', 'HEAD', 'PUT', 'PATCH', 'POST', 'DELETE'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    // No Access-Control-Max-Age header: browsers use their default preflight cache.
    'max_age' => null,

    'supports_credentials' => false,

];
