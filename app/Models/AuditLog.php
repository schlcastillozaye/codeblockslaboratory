<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Paginator;

final class AuditLog
{
    /**
     * @param array<string, mixed>|null $old
     * @param array<string, mixed>|null $new
     */
    public static function record(
        string $action,
        string $entityType,
        ?int $entityId,
        string $description,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null
    ): void {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO audit_logs
                (user_id, action, entity_type, entity_id, description, old_values, new_values, ip_address, user_agent)
             VALUES (:user_id, :action, :entity_type, :entity_id, :description, :old_values, :new_values, :ip_address, :user_agent)'
        );
        $stmt->execute([
            'user_id'     => $userId ?? Auth::id(),
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'description' => mb_substr($description, 0, 500),
            'old_values'  => $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            'new_values'  => $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            'ip_address'  => self::ip(),
            'user_agent'  => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? 'cli', 0, 255),
        ]);
    }

    /** @param array<string, mixed> $filters */
    public static function paginate(array $filters = [], int $page = 1, int $perPage = 15): Paginator
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = 'a.action = :action';
            $params['action'] = $filters['action'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['entity_type'])) {
            $where[] = 'a.entity_type = :entity_type';
            $params['entity_type'] = $filters['entity_type'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'DATE(a.created_at) >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'DATE(a.created_at) <= :to';
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(a.description LIKE :q_description OR a.action LIKE :q_action
                         OR CONCAT(u.first_name, " ", u.last_name) LIKE :q_actor)';
            $needle = '%' . trim((string) $filters['q']) . '%';
            $params['q_description'] = $needle;
            $params['q_action'] = $needle;
            $params['q_actor'] = $needle;
        }

        $whereSql = implode(' AND ', $where);
        $countSql = "SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE $whereSql";
        $dataSql = "SELECT a.*, u.first_name, u.last_name, u.role
                    FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
                    WHERE $whereSql ORDER BY a.created_at DESC, a.id DESC";

        return Paginator::fromQuery($countSql, $dataSql, $params, $page, $perPage);
    }

    /** @return array<int, array<string, mixed>> */
    public static function recent(int $limit = 8): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT a.*, u.first_name, u.last_name FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC, a.id DESC LIMIT ' . (int) $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int, string> */
    public static function actions(): array
    {
        return Database::pdo()->query('SELECT DISTINCT action FROM audit_logs ORDER BY action ASC')
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    private static function ip(): string
    {
        return mb_substr($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 0, 45);
    }
}
