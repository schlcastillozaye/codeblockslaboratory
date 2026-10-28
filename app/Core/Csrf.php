<?php

declare(strict_types=1);

namespace App\Core;

/**
 * CSRF token generation and validation for all state-changing requests.
 */
final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf_token');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set('_csrf_token', $token);
        }
        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function validate(?string $token = null): bool
    {
        $expected = Session::get('_csrf_token');
        $token ??= $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        return is_string($expected) && is_string($token) && $expected !== '' && hash_equals($expected, $token);
    }

    public static function rotate(): void
    {
        Session::set('_csrf_token', bin2hex(random_bytes(32)));
    }
}
