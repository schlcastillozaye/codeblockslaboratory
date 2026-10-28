<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Notification
{
    public static function create(int $userId, string $type, string $title, string $message, ?string $link = null): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO notifications (user_id, type, title, message, link) VALUES (:user_id, :type, :title, :message, :link)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'link'    => $link,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public static function forUser(int $userId, int $limit = 20, bool $unreadOnly = false): array
    {
        $sql = 'SELECT * FROM notifications
                WHERE user_id = :user_id AND dismissed_at IS NULL';
        if ($unreadOnly) {
            $sql .= ' AND is_read = 0';
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit);

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function unreadCount(int $userId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM notifications
             WHERE user_id = :user_id AND is_read = 0 AND dismissed_at IS NULL'
        );
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function markRead(int $id, int $userId): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE notifications
             SET is_read = 1, read_at = COALESCE(read_at, NOW())
             WHERE id = :id AND user_id = :user_id AND dismissed_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function dismiss(int $id, int $userId): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE notifications
             SET is_read = 1, read_at = COALESCE(read_at, NOW()), dismissed_at = NOW()
             WHERE id = :id AND user_id = :user_id AND dismissed_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function markAllRead(int $userId): int
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE notifications
             SET is_read = 1, read_at = COALESCE(read_at, NOW())
             WHERE user_id = :user_id AND is_read = 0 AND dismissed_at IS NULL'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->rowCount();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM notifications WHERE id = :id AND dismissed_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
}
