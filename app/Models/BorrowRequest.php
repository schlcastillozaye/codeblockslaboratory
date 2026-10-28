<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Paginator;

final class BorrowRequest
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled', 'completed'];

    /**
     * Create a request header + items. Must be called inside a DB transaction
     * when part of a larger workflow.
     * @param array<int, array{equipment_id:int, quantity:int}> $items
     */
    public static function create(int $userId, string $purpose, string $expectedReturnDate, array $items): int
    {
        $pdo = Database::pdo();
        $tempCode = 'TMP-' . strtoupper(bin2hex(random_bytes(6)));

        $stmt = $pdo->prepare(
            'INSERT INTO borrow_requests (request_code, user_id, purpose, expected_return_date, status)
             VALUES (:code, :user_id, :purpose, :expected, :status)'
        );
        $stmt->execute([
            'code'     => $tempCode,
            'user_id'  => $userId,
            'purpose'  => $purpose,
            'expected' => $expectedReturnDate,
            'status'   => 'pending',
        ]);
        $requestId = (int) $pdo->lastInsertId();
        $code = 'REQ-' . date('Y') . '-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT);

        $pdo->prepare('UPDATE borrow_requests SET request_code = :code WHERE id = :id')
            ->execute(['code' => $code, 'id' => $requestId]);

        $itemStmt = $pdo->prepare(
            'INSERT INTO borrow_request_items (request_id, equipment_id, quantity_requested)
             VALUES (:request_id, :equipment_id, :quantity)'
        );
        foreach ($items as $item) {
            $itemStmt->execute([
                'request_id'   => $requestId,
                'equipment_id' => (int) $item['equipment_id'],
                'quantity'     => (int) $item['quantity'],
            ]);
        }

        return $requestId;
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT br.*, u.first_name, u.last_name, u.school_id, u.email, u.role, u.department,
                    rv.first_name AS reviewer_first_name, rv.last_name AS reviewer_last_name
             FROM borrow_requests br
             JOIN users u ON u.id = br.user_id
             LEFT JOIN users rv ON rv.id = br.reviewer_id
             WHERE br.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function lock(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM borrow_requests WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public static function items(int $requestId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT bri.*, e.name, e.equipment_code, e.available_quantity, e.condition_status,
                    e.storage_location, c.name AS category_name
             FROM borrow_request_items bri
             JOIN equipment e ON e.id = bri.equipment_id
             JOIN categories c ON c.id = e.category_id
             WHERE bri.request_id = :id ORDER BY bri.id ASC'
        );
        $stmt->execute(['id' => $requestId]);
        return $stmt->fetchAll();
    }

    /** @param array<string, mixed> $filters */
    public static function paginate(array $filters = [], int $page = 1, int $perPage = 10): Paginator
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'br.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'br.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(br.request_code LIKE :q_code OR br.purpose LIKE :q_purpose
                         OR CONCAT(u.first_name, " ", u.last_name) LIKE :q_borrower OR u.school_id LIKE :q_school
                         OR EXISTS (SELECT 1 FROM borrow_request_items x JOIN equipment xe ON xe.id = x.equipment_id
                                    WHERE x.request_id = br.id AND (xe.name LIKE :q_equipment OR xe.equipment_code LIKE :q_equipment_code)))';
            $needle = '%' . trim((string) $filters['q']) . '%';
            $params['q_code'] = $needle;
            $params['q_purpose'] = $needle;
            $params['q_borrower'] = $needle;
            $params['q_school'] = $needle;
            $params['q_equipment'] = $needle;
            $params['q_equipment_code'] = $needle;
        }
        if (!empty($filters['from'])) {
            $where[] = 'DATE(br.created_at) >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'DATE(br.created_at) <= :to';
            $params['to'] = $filters['to'];
        }

        $whereSql = implode(' AND ', $where);
        $countSql = "SELECT COUNT(DISTINCT br.id) FROM borrow_requests br JOIN users u ON u.id = br.user_id WHERE $whereSql";
        $dataSql = "SELECT br.*, u.first_name, u.last_name, u.school_id, u.role,
                    GROUP_CONCAT(CONCAT(e.name, ' ×', bri.quantity_requested) ORDER BY bri.id SEPARATOR ', ') AS items_summary,
                    COALESCE(SUM(bri.quantity_requested), 0) AS total_units
                    FROM borrow_requests br
                    JOIN users u ON u.id = br.user_id
                    LEFT JOIN borrow_request_items bri ON bri.request_id = br.id
                    LEFT JOIN equipment e ON e.id = bri.equipment_id
                    WHERE $whereSql
                    GROUP BY br.id
                    ORDER BY FIELD(br.status, 'pending', 'approved', 'rejected', 'completed', 'cancelled'), br.created_at DESC";

        return Paginator::fromQuery($countSql, $dataSql, $params, $page, $perPage);
    }

    public static function updateStatus(int $id, string $status, ?int $reviewerId = null, ?string $reason = null): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE borrow_requests SET status = :status, reviewer_id = :reviewer_id,
                reviewed_at = NOW(), rejection_reason = :reason WHERE id = :id'
        );
        $stmt->execute([
            'status'      => $status,
            'reviewer_id' => $reviewerId,
            'reason'      => $reason,
            'id'          => $id,
        ]);
    }

    public static function setItemApproved(int $requestId, int $equipmentId, int $quantity): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE borrow_request_items SET quantity_approved = :qty
             WHERE request_id = :request_id AND equipment_id = :equipment_id'
        );
        $stmt->execute([
            'qty'          => $quantity,
            'request_id'   => $requestId,
            'equipment_id' => $equipmentId,
        ]);
    }

    public static function isDuplicateRecent(int $userId, int $equipmentId, int $seconds = 60): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM borrow_requests br
             JOIN borrow_request_items bri ON bri.request_id = br.id
             WHERE br.user_id = :user_id AND bri.equipment_id = :equipment_id
               AND br.status = "pending" AND br.created_at >= DATE_SUB(NOW(), INTERVAL :seconds SECOND)'
        );
        $stmt->execute(['user_id' => $userId, 'equipment_id' => $equipmentId, 'seconds' => $seconds]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function countByStatus(?int $userId = null): array
    {
        $sql = 'SELECT status, COUNT(*) AS total FROM borrow_requests';
        $params = [];
        if ($userId !== null) {
            $sql .= ' WHERE user_id = :user_id';
            $params['user_id'] = $userId;
        }
        $sql .= ' GROUP BY status';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }
        return $counts;
    }

    public static function pendingCount(): int
    {
        return (int) Database::pdo()->query("SELECT COUNT(*) FROM borrow_requests WHERE status = 'pending'")->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    public static function recent(int $limit = 5, ?int $userId = null): array
    {
        $sql = 'SELECT br.*, u.first_name, u.last_name,
                (SELECT GROUP_CONCAT(CONCAT(e.name, " ×", bri.quantity_requested) SEPARATOR ", ")
                 FROM borrow_request_items bri JOIN equipment e ON e.id = bri.equipment_id
                 WHERE bri.request_id = br.id) AS items_summary
                FROM borrow_requests br JOIN users u ON u.id = br.user_id';
        $params = [];
        if ($userId !== null) {
            $sql .= ' WHERE br.user_id = :user_id';
            $params['user_id'] = $userId;
        }
        $sql .= ' ORDER BY br.created_at DESC LIMIT ' . (int) $limit;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function monthlyTrend(int $months = 6): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
             FROM borrow_requests
             WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL :months MONTH)
             GROUP BY month ORDER BY month ASC"
        );
        $stmt->bindValue('months', $months, \PDO::PARAM_INT);
        $stmt->execute();

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[$row['month']] = (int) $row['total'];
        }

        $trend = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $key = date('Y-m', strtotime("-$i months"));
            $trend[] = ['month' => $key, 'label' => date('M', strtotime($key . '-01')), 'total' => $map[$key] ?? 0];
        }
        return $trend;
    }

    public static function completedFor(int $requestId): bool
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM borrow_transactions
             WHERE request_id = :id AND status NOT IN ('returned')"
        );
        $stmt->execute(['id' => $requestId]);
        return (int) $stmt->fetchColumn() === 0;
    }
}
