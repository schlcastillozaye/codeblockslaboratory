<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class LoginAttempt
{
    public static function record(string $login, bool $success): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO login_attempts (login, ip_address, success) VALUES (:login, :ip, :success)'
        );
        $stmt->execute([
            'login'   => mb_substr(strtolower(trim($login)), 0, 150),
            'ip'      => mb_substr($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 0, 45),
            'success' => $success ? 1 : 0,
        ]);
    }

    public static function recentFailures(string $login, int $minutes): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE login = :login AND success = 0
               AND attempted_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
        );
        $stmt->execute(['login' => mb_substr(strtolower(trim($login)), 0, 150), 'minutes' => $minutes]);
        return (int) $stmt->fetchColumn();
    }
}
