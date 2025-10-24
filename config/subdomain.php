<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subdomain Configuration
    |--------------------------------------------------------------------------
    |
    | Cấu hình cho subdomain và Safari compatibility
    |
    */

    'domain' => env('SUBDOMAIN_DOMAIN', '.yourdomain.com'),

    'session' => [
        'domain' => env('SESSION_DOMAIN', null), // Bỏ domain để tránh vấn đề WebKit
        'same_site' => env('SESSION_SAME_SITE', 'lax'), // Thay đổi từ 'none' thành 'lax'
        'secure' => env('SESSION_SECURE_COOKIE', false), // Tạm thời tắt cho WebKit
        'http_only' => env('SESSION_HTTP_ONLY', true),
        'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),
    ],

    'csrf' => [
        'domain' => env('CSRF_COOKIE_DOMAIN', null), // Bỏ domain để tránh vấn đề WebKit
        'same_site' => env('CSRF_COOKIE_SAME_SITE', 'lax'), // Thay đổi từ 'none' thành 'lax'
        'secure' => env('CSRF_COOKIE_SECURE', false), // Tạm thời tắt cho WebKit
    ],

    'safari' => [
        'enabled' => env('SAFARI_CSRF_FIX', true),
        'regenerate_token' => env('SAFARI_REGENERATE_TOKEN', true),
        'log_attempts' => env('SAFARI_LOG_CSRF', true),
    ],
];
