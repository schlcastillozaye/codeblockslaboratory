<?php

declare(strict_types=1);

/**
 * Code Blocks Laboratory Equipment Borrowing System - front controller.
 *
 * XAMPP URL:
 *   http://localhost/codeblocks-lab-inventory/public/
 *
 * All requests are routed here; assets are served directly by Apache.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Csrf;
use App\Core\Router;
use App\Core\View;

// Every state-changing request requires a valid CSRF token.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !Csrf::validate()) {
    header('HTTP/1.1 419 Page Expired', true, 419);
    View::render('errors/419', ['title' => 'Page Expired'], 'layouts/error');
    exit;
}

$router = new Router();
require BASE_PATH . '/routes.php';

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
