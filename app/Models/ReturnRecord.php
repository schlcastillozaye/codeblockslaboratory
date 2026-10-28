<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Paginator;

final class ReturnRecord
{
    public const CONDITIONS = ['good', 'damaged', 'needs_maintenance', 'incomplete'];

    public static function create(
        int $transactionId,
        int $equipmentId,
        int $quantity,
        string $returnDate,
        string $condition,
        ?string $notes,
        int $processedBy
    ): int {
        $pdo = Database::pdo();
        $tempCode = 'TMP-' . strtoupper(bin2hex(random_bytes(6)));

        $stmt = $pdo->prepare(
            'INSERT INTO return_records
                (return_code, transaction_id, equipment_id, quantity_returned, return_date, `condition`, notes, processed_by)
             VALUES (:code, :transaction_id, :equipment_id, :quantity, :return_date, :condition, :notes, :processed_by)'
        );
        $stmt->execute([
            'code'           => $tempCode,
            'transaction_id' => $transactionId,
            'equipment_id'   => $equipmentId,
            'quantity'       => $quantity,
            'return_date'    => $returnDate,
            'condition'      => $condition,
            'notes'          => $notes,
            'processed_by'   => $processedBy,
        ]);
        $id = (int) $pdo->lastInsertId();
        $code = 'RET-' . date('Y') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE return_records SET return_code = :code WHERE id = :id')
            ->execute(['code' => $code, 'id' => $id]);

        return $id;
    }

    /** @param array<string, mixed> $filters */
    public static function paginate(array $filters = [], int $page = 1, int $perPage = 10): Paginator
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['condition'])) {
            $where[] = 'rr.`condition` = :condition';
            $params['condition'] = $filters['condition'];
        }
        if (!empty($filters['equipment_id'])) {
            $where[] = 'rr.equipment_id = :equipment_id';
            $params['equipment_id'] = (int) $filters['equipment_id'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'bt.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(rr.return_code LIKE :q_return OR e.name LIKE :q_equipment OR bt.transaction_code LIKE :q_transaction
                         OR CONCAT(u.first_name, " ", u.last_name) LIKE :q_borrower)';
            $needle = '%' . trim((string) $filters['q']) . '%';
            $params['q_return'] = $needle;
            $params['q_equipment'] = $needle;
            $params['q_transaction'] = $needle;
            $params['q_borrower'] = $needle;
        }
        if (!empty($filters['from'])) {
            $where[] = 'rr.return_date >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'rr.return_date <= :to';
            $params['to'] = $filters['to'];
        }

        $whereSql = implode(' AND ', $where);
        $countSql = "SELECT COUNT(*) FROM return_records rr
                     JOIN borrow_transactions bt ON bt.id = rr.transaction_id
                     JOIN equipment e ON e.id = rr.equipment_id
                     JOIN users u ON u.id = bt.user_id
                     WHERE $whereSql";
        $dataSql = "SELECT rr.*, e.name AS equipment_name, e.equipment_code, bt.transaction_code,
                    bt.due_at, u.first_name, u.last_name, u.school_id,
                    p.first_name AS processor_first_name, p.last_name AS processor_last_name
                    FROM return_records rr
                    JOIN borrow_transactions bt ON bt.id = rr.transaction_id
                    JOIN equipment e ON e.id = rr.equipment_id
                    JOIN users u ON u.id = bt.user_id
                    LEFT JOIN users p ON p.id = rr.processed_by
                    WHERE $whereSql
                    ORDER BY rr.return_date DESC, rr.id DESC";

        return Paginator::fromQuery($countSql, $dataSql, $params, $page, $perPage);
    }

    /** @return array<int, array<string, mixed>> */
    public static function forTransaction(int $transactionId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT rr.*, p.first_name AS processor_first_name, p.last_name AS processor_last_name
             FROM return_records rr LEFT JOIN users p ON p.id = rr.processed_by
             WHERE rr.transaction_id = :id ORDER BY rr.id ASC'
        );
        $stmt->execute(['id' => $transactionId]);
        return $stmt->fetchAll();
    }

    /** @return array<string, int> */
    public static function conditionCounts(): array
    {
        $rows = Database::pdo()->query(
            'SELECT `condition`, COUNT(*) AS total FROM return_records GROUP BY `condition`'
        )->fetchAll();
        $counts = array_fill_keys(self::CONDITIONS, 0);
        foreach ($rows as $row) {
            $counts[$row['condition']] = (int) $row['total'];
        }
        return $counts;
    }

    public static function count(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM return_records')->fetchColumn();
    }
}
