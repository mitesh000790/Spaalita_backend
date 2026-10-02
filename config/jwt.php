<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JWT Secret
    |--------------------------------------------------------------------------
    |
    | Secret used to sign and verify HS256 access tokens. It must be at least
    | 32 characters long. Generate one with: php artisan jwt:secret
    |
    */

    'secret' => env('JWT_SECRET'),

    'algorithm' => 'HS256',

    /*
    |--------------------------------------------------------------------------
    | Token Lifetimes (seconds)
    |--------------------------------------------------------------------------
    |
    | Admin tokens expire after one hour and user tokens after one day,
    | the same lifetimes the original Node.js API used.
    |
    */

    'ttl' => [
        'admin' => (int) env('JWT_ADMIN_TTL', 3600),
        'user' => (int) env('JWT_USER_TTL', 86400),
    ],

];
