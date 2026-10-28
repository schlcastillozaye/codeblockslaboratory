<?php

/**
 * Code Blocks Laboratory Equipment Borrowing System - Laboratory Equipment Inventory and Borrowing Management System
 *
 * Base configuration. Do NOT put production secrets here.
 *
 * Local overrides can be placed in config/config.local.php (git-ignored), or set
 * through environment variables:
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, APP_URL, APP_ENV, APP_DEBUG
 *
 * XAMPP defaults: host 127.0.0.1, port 3306, user root, empty password.
 */

return [
    'app' => [
        'name'        => 'Code Blocks Laboratory Equipment Borrowing System',
        'short_name'  => 'Code Blocks Laboratory Equipment Borrowing System',
        'tagline'     => 'Laboratory Equipment Inventory & Borrowing',
        'institution' => 'Institute of Computing Studies',
        'env'         => getenv('APP_ENV') ?: 'local',
        'debug'       => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),
        'url'         => getenv('APP_URL') ?: '', // e.g. http://localhost/codeblocks-lab-inventory/public
        'pretty_urls' => filter_var(getenv('APP_PRETTY_URLS') ?: 'true', FILTER_VALIDATE_BOOL),
        'timezone'    => 'Asia/Manila',
        'locale'      => 'en_PH',
        'session_name' => 'codeblocks_session',
        'show_demo_credentials' => filter_var(getenv('APP_SHOW_DEMO_CREDENTIALS') ?: 'true', FILTER_VALIDATE_BOOL),
    ],

    'db' => [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => getenv('DB_PORT') ?: '3306',
        'name'     => getenv('DB_NAME') ?: 'codeblocks_lab_inventory',
        'user'     => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
        'charset'  => 'utf8mb4',
    ],

    'security' => [
        'max_login_attempts'    => 5,
        'lockout_minutes'       => 15,
        'session_idle_minutes'  => 120,
        'password_min_length'   => 8,
        'duplicate_request_seconds' => 60,
    ],

    'borrowing' => [
        'max_days'        => 14,
        'max_quantity'    => 20,
        'max_active_items' => 10,
    ],

    'uploads' => [
        'directory'   => BASE_PATH . '/public/uploads',
        'max_bytes'   => 2 * 1024 * 1024,
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp'],
    ],
];
