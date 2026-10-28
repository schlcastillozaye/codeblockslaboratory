<?php

declare(strict_types=1);

use App\Controllers\AuditController;
use App\Controllers\AuthController;
use App\Controllers\BorrowController;
use App\Controllers\DashboardController;
use App\Controllers\EquipmentController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;
use App\Controllers\ReportController;
use App\Controllers\ReturnController;
use App\Controllers\UserController;
use App\Core\Auth;
use App\Core\Router;

/** @var Router $router */

$router->get('/', static function (): void {
    redirect(Auth::check() ? url('dashboard') : url('login'));
});

// Authentication
$router->get('/login', [new AuthController(), 'showLogin']);
$router->post('/login', [new AuthController(), 'login']);
$router->post('/logout', [new AuthController(), 'logout'], ['auth']);

// Dashboard
$router->get('/dashboard', [new DashboardController(), 'index'], ['auth']);

// Equipment
$router->get('/equipment', [new EquipmentController(), 'index'], ['auth']);
$router->get('/equipment/create', [new EquipmentController(), 'create'], ['auth', 'role:admin']);
$router->post('/equipment', [new EquipmentController(), 'store'], ['auth', 'role:admin']);
$router->get('/equipment/{id}', [new EquipmentController(), 'show'], ['auth']);
$router->get('/equipment/{id}/edit', [new EquipmentController(), 'edit'], ['auth', 'role:admin']);
$router->post('/equipment/{id}', [new EquipmentController(), 'update'], ['auth', 'role:admin']);
$router->post('/equipment/{id}/archive', [new EquipmentController(), 'archive'], ['auth', 'role:admin']);
$router->post('/equipment/{id}/restore', [new EquipmentController(), 'restore'], ['auth', 'role:admin']);
$router->post('/equipment/{id}/condition', [new EquipmentController(), 'adjustCondition'], ['auth', 'role:admin']);

// Borrowing requests
$router->get('/borrow', [new BorrowController(), 'index'], ['auth']);
$router->get('/borrow/create', [new BorrowController(), 'create'], ['auth', 'role:student|instructor']);
$router->post('/borrow', [new BorrowController(), 'store'], ['auth', 'role:student|instructor']);
$router->get('/borrow/{id}', [new BorrowController(), 'show'], ['auth']);
$router->post('/borrow/{id}/approve', [new BorrowController(), 'approve'], ['auth', 'role:admin']);
$router->post('/borrow/{id}/reject', [new BorrowController(), 'reject'], ['auth', 'role:admin']);
$router->post('/borrow/{id}/cancel', [new BorrowController(), 'cancel'], ['auth']);

// Approvals & transactions
$router->get('/approvals', [new BorrowController(), 'approvals'], ['auth', 'role:admin']);
$router->get('/transactions', [new BorrowController(), 'transactions'], ['auth']);

// Returns
$router->get('/returns', [new ReturnController(), 'index'], ['auth']);
$router->get('/returns/create', [new ReturnController(), 'create'], ['auth', 'role:admin']);
$router->post('/returns', [new ReturnController(), 'store'], ['auth', 'role:admin']);

// User management
$router->get('/users', [new UserController(), 'index'], ['auth', 'role:admin']);
$router->get('/users/create', [new UserController(), 'create'], ['auth', 'role:admin']);
$router->post('/users', [new UserController(), 'store'], ['auth', 'role:admin']);
$router->get('/users/{id}/edit', [new UserController(), 'edit'], ['auth', 'role:admin']);
$router->post('/users/{id}', [new UserController(), 'update'], ['auth', 'role:admin']);

// Reports
$router->get('/reports', [new ReportController(), 'index'], ['auth', 'role:admin']);
$router->get('/reports/export', [new ReportController(), 'export'], ['auth', 'role:admin']);

// Audit trail
$router->get('/audit', [new AuditController(), 'index'], ['auth', 'role:admin']);

// Notifications
$router->get('/notifications', [new NotificationController(), 'index'], ['auth']);
$router->post('/notifications/read-all', [new NotificationController(), 'markAllRead'], ['auth']);
$router->post('/notifications/{id}/open', [new NotificationController(), 'open'], ['auth']);
$router->post('/notifications/{id}/read', [new NotificationController(), 'markRead'], ['auth']);
$router->post('/notifications/{id}/dismiss', [new NotificationController(), 'dismiss'], ['auth']);

// Profile
$router->get('/profile', [new ProfileController(), 'index'], ['auth']);
$router->post('/profile', [new ProfileController(), 'update'], ['auth']);
$router->post('/profile/password', [new ProfileController(), 'password'], ['auth']);
