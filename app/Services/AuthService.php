<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Flash;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\User;

final class AuthService
{
    /** @return array{ok:bool, message:string} */
    public function login(string $login, string $password): array
    {
        $login = trim($login);
        $maxAttempts = (int) Config::get('security.max_login_attempts', 5);
        $lockoutMinutes = (int) Config::get('security.lockout_minutes', 15);

        if ($login === '' || $password === '') {
            return ['ok' => false, 'message' => 'Enter your email/school ID and password.'];
        }

        $failures = LoginAttempt::recentFailures($login, $lockoutMinutes);
        if ($failures >= $maxAttempts) {
            AuditLog::record('auth.login_locked', 'user', null, 'Login blocked after repeated failures for ' . $login, null, null, null);
            return ['ok' => false, 'message' => 'Too many failed attempts. Please wait ' . $lockoutMinutes . ' minutes before trying again.'];
        }

        $user = User::findByLogin($login);

        // Constant-ish time: verify against a dummy hash when the account does not exist.
        if (!$user) {
            password_verify($password, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
            LoginAttempt::record($login, false);
            AuditLog::record('auth.login_failed', 'user', null, 'Failed login for unknown account: ' . $login, null, null, null);
            return ['ok' => false, 'message' => 'Invalid credentials or inactive account.'];
        }

        if ($user['status'] !== 'active') {
            LoginAttempt::record($login, false);
            AuditLog::record('auth.login_failed', 'user', (int) $user['id'], 'Inactive account attempted to sign in: ' . $login, null, null, (int) $user['id']);
            return ['ok' => false, 'message' => 'Invalid credentials or inactive account.'];
        }

        if (!Auth::attempt($login, $password)) {
            LoginAttempt::record($login, false);
            AuditLog::record('auth.login_failed', 'user', (int) $user['id'], 'Wrong password for ' . $login, null, null, (int) $user['id']);
            $remaining = max(0, $maxAttempts - ($failures + 1));
            return ['ok' => false, 'message' => 'Invalid credentials or inactive account.' . ($remaining <= 2 ? " $remaining attempt(s) remaining before a temporary lock." : '')];
        }

        LoginAttempt::record($login, true);
        AuditLog::record('auth.login', 'user', (int) $user['id'], 'User signed in: ' . $user['email'], null, null, (int) $user['id']);
        return ['ok' => true, 'message' => 'Welcome back, ' . $user['first_name'] . '.'];
    }

    public function logout(): void
    {
        $user = Auth::user();
        if ($user) {
            AuditLog::record('auth.logout', 'user', (int) $user['id'], 'User signed out: ' . $user['email']);
        }
        Auth::logout();
        Session::regenerate();
        \App\Core\Csrf::rotate();
        Flash::set('success', 'You have been signed out securely.');
    }
}
