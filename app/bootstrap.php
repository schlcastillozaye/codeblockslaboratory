<?php

declare(strict_types=1);

/**
 * Code Blocks Laboratory Equipment Borrowing System application bootstrap.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require BASE_PATH . '/app/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$config = require BASE_PATH . '/config/config.php';
if (is_file(BASE_PATH . '/config/config.local.php')) {
    $local = require BASE_PATH . '/config/config.local.php';
    if (is_array($local)) {
        $config = array_replace_recursive($config, $local);
    }
}
App\Core\Config::set($config);

date_default_timezone_set((string) ($config['app']['timezone'] ?? 'Asia/Manila'));
mb_internal_encoding('UTF-8');

if (PHP_SAPI !== 'cli') {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
}

App\Core\Session::start();
App\Core\Database::configure((array) ($config['db'] ?? []));

// Observer-inspired listeners: local in-app notifications only.
App\Services\NotificationService::registerObservers();
