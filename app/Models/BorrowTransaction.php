<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Paginator;

final class BorrowTransaction
{
    public const OPEN_STATUSES = ['active', 'overdue', 'partially_returned'];

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT bt.*, e.name AS equipment_name, e.equipment_code, e.category_id, e.available_quantity,
                    c.name AS category_name, u.first_name, u.last_name, u.school_id, u.email,
                    br.request_code, br.purpose,
                    processor.first_name AS processor_first_name, processor.last_name AS processor_last_name
             FROM borrow_transactions bt
             JOIN equipment e ON e.id = bt.equipment_id
             JOIN categories c ON c.id = e.category_id
             JOIN users u ON u.id = bt.user_id
             JOIN borrow_requests br ON br.id = bt.request_id
             LEFT JOIN users processor ON processor.id = bt.processed_by
             WHERE bt.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function lock(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM borrow_transactions WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(
        int $requestId,
        int $requestItemId,
        int $userId,
        int $equipmentId,
        int $quantity,
        string $borrowedAt,
        string $dueAt,
        int $processedBy
    ): int {
        $pdo = Database::pdo();
        $tempCode = 'TMP-' . strtoupper(bin2hex(random_bytes(6)));

        $stmt = $pdo->prepare(
            'INSERT INTO borrow_transactions
                (transaction_code, request_id, request_item_id, user_id, equipment_id, quantity,
                 quantity_returned, borrowed_at, due_at, status, processed_by)
             VALUES (:code, :request_id, :request_item_id, :user_id, :equipment_id, :quantity,
                 0, :borrowed_at, :due_at, :status, :processed_by)'
        );
        $stmt->execute([
            'code'            => $tempCode,
            'request_id'      => $requestId,
            'request_item_id' => $requestItemId,
            'user_id'         => $userId,
            'equipment_id'    => $equipmentId,
            'quantity'        => $quantity,
            'borrowed_at'     => $borrowedAt,
            'due_at'          => $dueAt,
            'status'          => 'active',
            'processed_by'    => $processedBy,
        ]);
        $id = (int) $pdo->lastInsertId();
        $code = 'TX-' . date('Y') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE borrow_transactions SET transaction_code = :code WHERE id = :id')
            ->execute(['code' => $code, 'id' => $id]);

        return $id;
    }

    /** @param array<string, mixed> $filters */
    public static function paginate(array $filters = [], int $page = 1, int $perPage = 10): Paginator
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'bt.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'bt.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['equipment_id'])) {
            $where[] = 'bt.equipment_id = :equipment_id';
            $params['equipment_id'] = (int) $filters['equipment_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(bt.transaction_code LIKE :q_transaction OR e.name LIKE :q_equipment OR e.equipment_code LIKE :q_equipment_code
                         OR CONCAT(u.first_name, " ", u.last_name) LIKE :q_borrower OR u.school_id LIKE :q_school
                         OR br.request_code LIKE :q_request)';
            $needle = '%' . trim((string) $filters['q']) . '%';
            $params['q_transaction'] = $needle;
            $params['q_equipment'] = $needle;
            $params['q_equipment_code'] = $needle;
            $params['q_borrower'] = $needle;
            $params['q_school'] = $needle;
            $params['q_request'] = $needle;
        }
        if (!empty($filters['from'])) {
            $where[] = 'DATE(bt.borrowed_at) >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'DATE(bt.borrowed_at) <= :to';
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['overdue'])) {
            $where[] = "bt.status IN ('active','overdue','partially_returned') AND bt.due_at < NOW()";
        }

        $whereSql = implode(' AND ', $where);
        $countSql = "SELECT COUNT(*) FROM borrow_transactions bt
                     JOIN equipment e ON e.id = bt.equipment_id
                     JOIN users u ON u.id = bt.user_id
                     JOIN borrow_requests br ON br.id = bt.request_id
                     WHERE $whereSql";
        $dataSql = "SELECT bt.*, e.name AS equipment_name, e.equipment_code, c.name AS category_name,
                    u.first_name, u.last_name, u.school_id, br.request_code,
                    (bt.quantity - bt.quantity_returned) AS outstanding_quantity,
                    (bt.status IN ('active','overdue','partially_returned') AND bt.due_at < NOW()) AS is_overdue
                    FROM borrow_transactions bt
                    JOIN equipment e ON e.id = bt.equipment_id
                    JOIN categories c ON c.id = e.category_id
                    JOIN users u ON u.id = bt.user_id
                    JOIN borrow_requests br ON br.id = bt.request_id
                    WHERE $whereSql
                    ORDER BY FIELD(bt.status, 'overdue', 'active', 'partially_returned', 'returned', 'lost'), bt.due_at ASC";

        return Paginator::fromQuery($countSql, $dataSql, $params, $page, $perPage);
    }

    /** @return array<int, array<string, mixed>> */
    public static function forRequest(int $requestId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT bt.*, e.name AS equipment_name, e.equipment_code,
                    (SELECT GROUP_CONCAT(rr.return_code ORDER BY rr.id SEPARATOR ", ") FROM return_records rr WHERE rr.transaction_id = bt.id) AS return_code,
                    (bt.quantity - bt.quantity_returned) AS outstanding_quantity
             FROM borrow_transactions bt
             JOIN equipment e ON e.id = bt.equipment_id
             WHERE bt.request_id = :id
             ORDER BY bt.id ASC'
        );
        $stmt->execute(['id' => $requestId]);
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public static function forEquipment(int $equipmentId, int $limit = 15): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT bt.*, u.first_name, u.last_name, u.school_id, br.request_code
             FROM borrow_transactions bt
             JOIN users u ON u.id = bt.user_id
             JOIN borrow_requests br ON br.id = bt.request_id
             WHERE bt.equipment_id = :id
             ORDER BY bt.borrowed_at DESC, bt.id DESC LIMIT ' . (int) $limit
        );
        $stmt->execute(['id' => $equipmentId]);
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public static function openForUser(int $userId): array    {
        $stmt = Database::pdo()->prepare(
            "SELECT bt.*, e.name AS equipment_name, e.equipment_code,
                    (bt.quantity - bt.quantity_returned) AS outstanding_quantity,
                    DATEDIFF(bt.due_at, CURDATE()) AS days_left
             FROM borrow_transactions bt JOIN equipment e ON e.id = bt.equipment_id
             WHERE bt.user_id = :user_id AND bt.status IN ('active','overdue','partially_returned')
             ORDER BY bt.due_at ASC"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function updateAfterReturn(int $id, int $quantityReturned, int $newReturned, string $status): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE borrow_transactions SET quantity_returned = :returned, status = :status,
                returned_at = CASE WHEN :returned2 >= quantity THEN NOW() ELSE NULL END
             WHERE id = :id'
        );
        $stmt->execute([
            'returned'  => $newReturned,
            'returned2' => $newReturned,
            'status'    => $status,
            'id'        => $id,
        ]);
    }

    public static function stats(?int $userId = null): array
    {
        $sql = "SELECT
                    COUNT(*) AS total,
                    COALESCE(SUM(status IN ('active','overdue','partially_returned')), 0) AS open_total,
                    COALESCE(SUM(status = 'overdue' OR (status IN ('active','partially_returned') AND due_at < NOW())), 0) AS overdue_total,
                    COALESCE(SUM(status IN ('active','overdue','partially_returned') AND due_at >= NOW() AND due_at <= DATE_ADD(NOW(), INTERVAL 3 DAY)), 0) AS due_soon,
                    COALESCE(SUM(status = 'returned'), 0) AS returned_total
                FROM borrow_transactions";
        $params = [];
        if ($userId !== null) {
            $sql .= ' WHERE user_id = :user_id';
            $params['user_id'] = $userId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', $stmt->fetch() ?: []);
    }

    /** @return array<int, array<string, mixed>> */
    public static function overdue(?int $userId = null, int $limit = 50): array
    {
        $sql = "SELECT bt.*, e.name AS equipment_name, e.equipment_code, u.first_name, u.last_name, u.school_id,
                       DATEDIFF(NOW(), bt.due_at) AS days_overdue, br.request_code
                FROM borrow_transactions bt
                JOIN equipment e ON e.id = bt.equipment_id
                JOIN users u ON u.id = bt.user_id
                JOIN borrow_requests br ON br.id = bt.request_id
                WHERE bt.status IN ('active','overdue','partially_returned') AND bt.due_at < NOW()";
        $params = [];
        if ($userId !== null) {
            $sql .= ' AND bt.user_id = :user_id';
            $params['user_id'] = $userId;
        }
        $sql .= ' ORDER BY bt.due_at ASC LIMIT ' . (int) $limit;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function markOverdue(): int
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE borrow_transactions SET status = 'overdue'
             WHERE status IN ('active','partially_returned') AND due_at < NOW()"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    /** @return array<string, int> */
    public static function activeQuantityByEquipment(int $equipmentId): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COALESCE(SUM(quantity - quantity_returned), 0) AS outstanding
             FROM borrow_transactions
             WHERE equipment_id = :id AND status IN ('active','overdue','partially_returned')"
        );
        $stmt->execute(['id' => $equipmentId]);
        return ['outstanding' => (int) $stmt->fetchColumn()];
    }
}
