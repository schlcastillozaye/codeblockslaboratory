<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Equipment;
use App\Models\ReturnRecord;

/**
 * Reporting queries. Every number in the UI comes from these live queries.
 */
final class ReportService
{
    public function summary(): array
    {
        $pdo = Database::pdo();
        return [
            'equipment_records' => (int) $pdo->query('SELECT COUNT(*) FROM equipment')->fetchColumn(),
            'total_units' => (int) $pdo->query('SELECT COALESCE(SUM(total_quantity),0) FROM equipment')->fetchColumn(),
            'available_units' => (int) $pdo->query('SELECT COALESCE(SUM(available_quantity),0) FROM equipment')->fetchColumn(),
            'borrowed_units' => (int) $pdo->query('SELECT COALESCE(SUM(borrowed_quantity),0) FROM equipment')->fetchColumn(),
            'damaged_units' => (int) $pdo->query('SELECT COALESCE(SUM(damaged_quantity),0) FROM equipment')->fetchColumn(),
            'repair_units' => (int) $pdo->query('SELECT COALESCE(SUM(under_repair_quantity),0) FROM equipment')->fetchColumn(),
            'lost_units' => (int) $pdo->query('SELECT COALESCE(SUM(lost_quantity),0) FROM equipment')->fetchColumn(),
            'pending_requests' => (int) $pdo->query("SELECT COUNT(*) FROM borrow_requests WHERE status = 'pending'")->fetchColumn(),
            'overdue_transactions' => (int) $pdo->query("SELECT COUNT(*) FROM borrow_transactions WHERE status IN ('active','overdue','partially_returned') AND due_at < NOW()")->fetchColumn(),
            'return_records' => ReturnRecord::count(),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function inventory(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['category_id'])) {
            $where[] = 'e.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['condition'])) {
            $where[] = 'e.condition_status = :condition';
            $params['condition'] = $filters['condition'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'e.record_status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(e.name LIKE :q OR e.equipment_code LIKE :q OR c.name LIKE :q)';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        $sql = 'SELECT e.*, c.name AS category_name FROM equipment e
                JOIN categories c ON c.id = e.category_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY c.name ASC, e.name ASC LIMIT 1000';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @param array<string, mixed> $filters */
    public function borrowing(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'br.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'br.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'e.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'DATE(br.created_at) >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'DATE(br.created_at) <= :to';
            $params['to'] = $filters['to'];
        }

        $sql = 'SELECT br.request_code, br.status, br.purpose, br.expected_return_date, br.created_at,
                       br.reviewed_at, br.rejection_reason,
                       u.first_name, u.last_name, u.school_id, u.role,
                       e.name AS equipment_name, e.equipment_code, c.name AS category_name,
                       bri.quantity_requested, bri.quantity_approved,
                       tx.transaction_code, tx.status AS tx_status, tx.borrowed_at, tx.due_at,
                       tx.quantity AS tx_quantity, tx.quantity_returned
                FROM borrow_requests br
                JOIN users u ON u.id = br.user_id
                JOIN borrow_request_items bri ON bri.request_id = br.id
                JOIN equipment e ON e.id = bri.equipment_id
                JOIN categories c ON c.id = e.category_id
                LEFT JOIN borrow_transactions tx ON tx.request_item_id = bri.id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY br.created_at DESC LIMIT 1000';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @param array<string, mixed> $filters */
    public function returns(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['condition'])) {
            $where[] = 'rr.`condition` = :condition';
            $params['condition'] = $filters['condition'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'rr.return_date >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'rr.return_date <= :to';
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'bt.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }

        $sql = 'SELECT rr.*, e.name AS equipment_name, e.equipment_code,
                       bt.transaction_code, bt.due_at, u.first_name, u.last_name, u.school_id,
                       p.first_name AS processor_first_name, p.last_name AS processor_last_name
                FROM return_records rr
                JOIN borrow_transactions bt ON bt.id = rr.transaction_id
                JOIN equipment e ON e.id = rr.equipment_id
                JOIN users u ON u.id = bt.user_id
                LEFT JOIN users p ON p.id = rr.processed_by
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY rr.return_date DESC, rr.id DESC LIMIT 1000';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function pending(): array
    {
        return Database::pdo()->query(
            "SELECT br.*, u.first_name, u.last_name, u.school_id, u.role,
                    GROUP_CONCAT(CONCAT(e.name, ' ×', bri.quantity_requested) SEPARATOR ', ') AS items_summary
             FROM borrow_requests br
             JOIN users u ON u.id = br.user_id
             JOIN borrow_request_items bri ON bri.request_id = br.id
             JOIN equipment e ON e.id = bri.equipment_id
             WHERE br.status = 'pending'
             GROUP BY br.id
             ORDER BY br.created_at ASC"
        )->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function overdue(): array
    {
        return Database::pdo()->query(
            "SELECT bt.*, e.name AS equipment_name, e.equipment_code, u.first_name, u.last_name, u.school_id,
                    DATEDIFF(NOW(), bt.due_at) AS days_overdue, br.request_code
             FROM borrow_transactions bt
             JOIN equipment e ON e.id = bt.equipment_id
             JOIN users u ON u.id = bt.user_id
             JOIN borrow_requests br ON br.id = bt.request_id
             WHERE bt.status IN ('active','overdue','partially_returned') AND bt.due_at < NOW()
             ORDER BY bt.due_at ASC"
        )->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function conditionRegister(): array
    {
        return Database::pdo()->query(
            "SELECT e.id, e.equipment_code, e.name, c.name AS category_name,
                    e.total_quantity, e.available_quantity, e.borrowed_quantity,
                    e.damaged_quantity, e.under_repair_quantity, e.lost_quantity, e.condition_status
             FROM equipment e JOIN categories c ON c.id = e.category_id
             WHERE e.damaged_quantity > 0 OR e.under_repair_quantity > 0 OR e.lost_quantity > 0
             ORDER BY (e.damaged_quantity + e.under_repair_quantity + e.lost_quantity) DESC, e.name ASC"
        )->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function conditionHistory(int $limit = 100): array
    {
        return Database::pdo()->query(
            "SELECT h.*, e.name AS equipment_name, e.equipment_code, u.first_name, u.last_name, rr.return_code
             FROM equipment_condition_history h
             JOIN equipment e ON e.id = h.equipment_id
             LEFT JOIN users u ON u.id = h.recorded_by
             LEFT JOIN return_records rr ON rr.id = h.return_record_id
             ORDER BY h.recorded_at DESC, h.id DESC LIMIT " . (int) $limit
        )->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function borrowerSummary(): array
    {
        return Database::pdo()->query(
            "SELECT u.id, u.school_id, u.first_name, u.last_name, u.role,
                    COUNT(DISTINCT br.id) AS requests,
                    COALESCE(SUM(tx.quantity - tx.quantity_returned), 0) AS outstanding_units,
                    COALESCE(SUM(tx.status IN ('active','overdue','partially_returned') AND tx.due_at < NOW()), 0) AS overdue_count,
                    COALESCE(SUM(tx.status = 'returned'), 0) AS completed_returns
             FROM users u
             LEFT JOIN borrow_requests br ON br.user_id = u.id
             LEFT JOIN borrow_transactions tx ON tx.user_id = u.id
             WHERE u.role IN ('student','instructor')
             GROUP BY u.id
             HAVING requests > 0 OR outstanding_units > 0
             ORDER BY outstanding_units DESC, u.last_name ASC"
        )->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function usersForFilter(): array
    {
        return Database::pdo()->query(
            "SELECT id, first_name, last_name, school_id, role FROM users ORDER BY last_name ASC"
        )->fetchAll();
    }
}
