<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BusinessException;
use App\Core\Flash;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Core\View;

abstract class Controller
{
    /** @param array<string, mixed> $data */
    protected function render(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        View::render($view, $data, $layout);
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    protected function intInput(string $key, int $default = 0): int
    {
        $value = $this->input($key, (string) $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    /** @param array<string, string> $rules @return array<string, mixed> */
    protected function validate(array $rules): array
    {
        $validator = Validator::make($_POST, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }
        return $validator->validated();
    }

    protected function validationFailed(ValidationException $e, string $fallback = ''): never
    {
        Flash::setErrors($e->errors());
        Flash::setOld($_POST);
        Flash::set('error', $e->getMessage());
        back($fallback ?: 'dashboard');
    }

    protected function businessFailed(BusinessException $e, string $fallback = ''): never
    {
        Flash::set('error', $e->getMessage());
        Flash::setOld($_POST);
        back($fallback ?: 'dashboard');
    }

    protected function notFound(string $message = 'The requested record could not be found.'): never
    {
        http_response_code(404);
        View::render('errors/404', ['title' => 'Not Found', 'message' => $message], 'layouts/error');
        exit;
    }

    protected function forbidden(string $message = 'You do not have permission to perform this action.'): never
    {
        http_response_code(403);
        View::render('errors/403', ['title' => 'Access Denied', 'message' => $message], 'layouts/error');
        exit;
    }

    protected function requireOwnerOrAdmin(int $ownerId): void
    {
        if (!is_admin() && (int) ($_SESSION['auth_user_id'] ?? 0) !== $ownerId) {
            $this->forbidden('This record belongs to another user.');
        }
    }

    protected function isPost(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    protected function wantsJson(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
    }

    /** @param array<string, mixed> $payload */
    protected function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
