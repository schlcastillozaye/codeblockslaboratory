<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Session-backed authentication state.
 */
final class Auth
{
    private static ?array $cachedUser = null;
    private static bool $loaded = false;

    public static function attempt(string $login, string $password): bool
    {
        $user = User::findByLogin($login);
        if (!$user || $user['status'] !== 'active') {
            return false;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            User::updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        Session::regenerate();
        Csrf::rotate();
        Session::set('auth_user_id', (int) $user['id']);
        Session::set('auth_role', $user['role']);
        Session::set('_last_activity', time());

        User::touchLogin((int) $user['id']);
        self::$cachedUser = null;
        self::$loaded = false;

        return true;
    }

    public static function logout(): void
    {
        Session::forget('auth_user_id');
        Session::forget('auth_role');
        self::$cachedUser = null;
        self::$loaded = false;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$cachedUser;
        }
        self::$loaded = true;

        $id = Session::get('auth_user_id');
        if (!$id) {
            return self::$cachedUser = null;
        }

        $user = User::find((int) $id);
        if (!$user || $user['status'] !== 'active') {
            self::logout();
            return self::$cachedUser = null;
        }

        return self::$cachedUser = $user;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user['role'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isStudent(): bool
    {
        return self::role() === 'student';
    }

    public static function isInstructor(): bool
    {
        return self::role() === 'instructor';
    }

    /** @param array<int, string>|string $roles */
    public static function hasRole(array|string $roles): bool
    {
        $roles = (array) $roles;
        return in_array(self::role(), $roles, true);
    }
}
