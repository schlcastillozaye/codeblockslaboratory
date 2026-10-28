<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Paginator;

final class Equipment
{
    /**
     * Quantity invariant:
     * total = available + borrowed + damaged + under_repair + lost
     */
    public const BORROWABLE_CONDITIONS = ['good'];

    /** @param array<string, mixed> $filters */
    public static function paginate(array $filters = [], int $page = 1, int $perPage = 10): Paginator
    {
        [$whereSql, $params] = self::buildFilters($filters);

        $countSql = "SELECT COUNT(*) FROM equipment e JOIN categories c ON c.id = e.category_id WHERE $whereSql";
        $dataSql = "SELECT e.*, c.name AS category_name
                    FROM equipment e
                    JOIN categories c ON c.id = e.category_id
                    WHERE $whereSql
                    ORDER BY " . self::sortSql((string) ($filters['sort'] ?? 'name'));

        return Paginator::fromQuery($countSql, $dataSql, $params, $page, $perPage);
    }

    /** @param array<string, mixed> $filters @return array{0:string,1:array<string,mixed>} */
    private static function buildFilters(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(e.name LIKE :q_name OR e.equipment_code LIKE :q_code OR e.barcode LIKE :q_barcode
                         OR c.name LIKE :q_category OR e.storage_location LIKE :q_location OR e.description LIKE :q_description)';
            $needle = '%' . trim((string) $filters['q']) . '%';
            $params['q_name'] = $needle;
            $params['q_code'] = $needle;
            $params['q_barcode'] = $needle;
            $params['q_category'] = $needle;
            $params['q_location'] = $needle;
            $params['q_description'] = $needle;
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'e.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['condition'])) {
            $where[] = 'e.condition_status = :condition';
            $params['condition'] = $filters['condition'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'e.record_status = :record_status';
            $params['record_status'] = $filters['status'];
        }

        $availability = $filters['availability'] ?? '';
        if ($availability === 'available') {
            $where[] = "e.available_quantity > 0 AND e.condition_status = 'good' AND e.record_status = 'active'";
        } elseif ($availability === 'unavailable') {
            $where[] = "(e.available_quantity = 0 OR e.condition_status <> 'good' OR e.record_status = 'archived')";
        } elseif ($availability === 'borrowed') {
            $where[] = 'e.borrowed_quantity > 0';
        } elseif ($availability === 'repair') {
            $where[] = '(e.under_repair_quantity > 0 OR e.damaged_quantity > 0)';
        }

        return [implode(' AND ', $where), $params];
    }

    private static function sortSql(string $sort): string
    {
        return match ($sort) {
            'newest'    => 'e.created_at DESC, e.name ASC',
            'available' => 'e.available_quantity DESC, e.name ASC',
            'total'     => 'e.total_quantity DESC, e.name ASC',
            'category'  => 'c.name ASC, e.name ASC',
            default     => 'e.name ASC',
        };
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT e.*, c.name AS category_name, c.description AS category_description
             FROM equipment e JOIN categories c ON c.id = e.category_id
             WHERE e.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function findByCode(string $code): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM equipment WHERE equipment_code = :code LIMIT 1');
        $stmt->execute(['code' => trim($code)]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Lock a row for the duration of a transaction (SELECT ... FOR UPDATE).
     * @return array<string, mixed>|null
     */
    public static function lock(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM equipment WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM equipment WHERE equipment_code = :code';
        $params = ['code' => trim($code)];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function barcodeExists(string $barcode, ?int $exceptId = null): bool
    {
        if (trim($barcode) === '') {
            return false;
        }
        $sql = 'SELECT COUNT(*) FROM equipment WHERE barcode = :barcode';
        $params = ['barcode' => trim($barcode)];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $data */
    public static function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO equipment
                (equipment_code, name, category_id, description, total_quantity, available_quantity,
                 borrowed_quantity, damaged_quantity, under_repair_quantity, lost_quantity,
                 condition_status, storage_location, barcode, record_status)
             VALUES
                (:code, :name, :category_id, :description, :total, :available,
                 0, 0, 0, 0, :condition, :location, :barcode, :record_status)'
        );
        $stmt->execute([
            'code'          => trim($data['equipment_code']),
            'name'          => trim($data['name']),
            'category_id'   => (int) $data['category_id'],
            'description'   => $data['description'] ?: null,
            'total'         => (int) $data['total_quantity'],
            'available'     => (int) $data['total_quantity'],
            'condition'     => $data['condition_status'] ?? 'good',
            'location'      => $data['storage_location'],
            'barcode'       => $data['barcode'] ?: null,
            'record_status' => $data['record_status'] ?? 'active',
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE equipment SET equipment_code = :code, name = :name, category_id = :category_id,
                description = :description, total_quantity = :total, available_quantity = :available,
                condition_status = :condition, storage_location = :location, barcode = :barcode,
                record_status = :record_status
             WHERE id = :id'
        );
        $stmt->execute([
            'id'            => $id,
            'code'          => trim($data['equipment_code']),
            'name'          => trim($data['name']),
            'category_id'   => (int) $data['category_id'],
            'description'   => $data['description'] ?: null,
            'total'         => (int) $data['total_quantity'],
            'available'     => (int) $data['available_quantity'],
            'condition'     => $data['condition_status'],
            'location'      => $data['storage_location'],
            'barcode'       => $data['barcode'] ?: null,
            'record_status' => $data['record_status'],
        ]);
    }

    public static function updateQuantities(int $id, array $q): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE equipment SET total_quantity = :total, available_quantity = :available,
                borrowed_quantity = :borrowed, damaged_quantity = :damaged,
                under_repair_quantity = :repair, lost_quantity = :lost,
                condition_status = :condition
             WHERE id = :id'
        );
        $stmt->execute([
            'id'        => $id,
            'total'     => $q['total_quantity'],
            'available' => $q['available_quantity'],
            'borrowed'  => $q['borrowed_quantity'],
            'damaged'   => $q['damaged_quantity'],
            'repair'    => $q['under_repair_quantity'],
            'lost'      => $q['lost_quantity'],
            'condition' => $q['condition_status'],
        ]);
    }

    public static function decrementAvailable(int $id, int $quantity): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE equipment
             SET available_quantity = available_quantity - :qty_sub, borrowed_quantity = borrowed_quantity + :qty_add
             WHERE id = :id AND available_quantity >= :qty_guard'
        );
        $stmt->execute(['qty_sub' => $quantity, 'qty_add' => $quantity, 'id' => $id, 'qty_guard' => $quantity]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Stock changed while processing. Please retry.');
        }
    }

    public static function incrementReturned(int $id, int $quantity, string $bucket): void
    {
        $column = match ($bucket) {
            'available' => 'available_quantity',
            'damaged'   => 'damaged_quantity',
            'repair'    => 'under_repair_quantity',
            'lost'      => 'lost_quantity',
            default     => throw new \InvalidArgumentException('Invalid inventory bucket.'),
        };

        $stmt = Database::pdo()->prepare(
            "UPDATE equipment
             SET borrowed_quantity = borrowed_quantity - :qty_sub, $column = $column + :qty_add
             WHERE id = :id AND borrowed_quantity >= :qty_guard"
        );
        $stmt->execute(['qty_sub' => $quantity, 'qty_add' => $quantity, 'id' => $id, 'qty_guard' => $quantity]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Return quantity exceeds borrowed stock. Please retry.');
        }
    }

    /** @return array<string, int> */
    public static function stats(): array
    {
        $row = Database::pdo()->query(
            "SELECT
                COUNT(*) AS records,
                COALESCE(SUM(total_quantity), 0) AS total_units,
                COALESCE(SUM(available_quantity), 0) AS available_units,
                COALESCE(SUM(borrowed_quantity), 0) AS borrowed_units,
                COALESCE(SUM(damaged_quantity), 0) AS damaged_units,
                COALESCE(SUM(under_repair_quantity), 0) AS repair_units,
                COALESCE(SUM(lost_quantity), 0) AS lost_units,
                COALESCE(SUM(record_status = 'active'), 0) AS active_records,
                COALESCE(SUM(record_status = 'archived'), 0) AS archived_records
             FROM equipment"
        )->fetch();

        return array_map('intval', $row ?: []);
    }

    /** @return array<int, array<string, mixed>> */
    public static function statusDistribution(): array
    {
        $rows = Database::pdo()->query(
            "SELECT status, COUNT(*) AS total FROM (
                SELECT CASE
                    WHEN record_status = 'archived' THEN 'archived'
                    WHEN available_quantity > 0 THEN 'available'
                    WHEN borrowed_quantity > 0 AND borrowed_quantity = total_quantity THEN 'borrowed'
                    WHEN under_repair_quantity > 0 THEN 'under_repair'
                    WHEN damaged_quantity > 0 THEN 'damaged'
                    WHEN lost_quantity > 0 THEN 'lost'
                    ELSE 'unavailable'
                END AS status
                FROM equipment
             ) AS t GROUP BY status ORDER BY total DESC"
        )->fetchAll();
        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    public static function categoryDistribution(): array
    {
        return Database::pdo()->query(
            "SELECT c.name AS category, COALESCE(SUM(e.total_quantity), 0) AS units
             FROM categories c LEFT JOIN equipment e ON e.category_id = c.id AND e.record_status = 'active'
             GROUP BY c.id, c.name ORDER BY units DESC, c.name ASC"
        )->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public static function popular(int $limit = 5): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT e.id, e.name, e.equipment_code, COALESCE(SUM(bt.quantity), 0) AS borrowed_units
             FROM equipment e
             LEFT JOIN borrow_transactions bt ON bt.equipment_id = e.id
             GROUP BY e.id, e.name, e.equipment_code
             HAVING borrowed_units > 0
             ORDER BY borrowed_units DESC, e.name ASC
             LIMIT $limit"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public static function recent(int $limit = 5): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT e.*, c.name AS category_name FROM equipment e
             JOIN categories c ON c.id = e.category_id
             ORDER BY e.created_at DESC, e.id DESC LIMIT ' . (int) $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public static function availableForSelect(): array
    {
        return Database::pdo()->query(
            "SELECT e.id, e.name, e.equipment_code, e.available_quantity, e.condition_status,
                    c.name AS category_name
             FROM equipment e JOIN categories c ON c.id = e.category_id
             WHERE e.available_quantity > 0 AND e.record_status = 'active' AND e.condition_status = 'good'
             ORDER BY e.name ASC"
        )->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public static function lowStock(int $limit = 5): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT e.*, c.name AS category_name FROM equipment e
             JOIN categories c ON c.id = e.category_id
             WHERE e.record_status = 'active' AND e.available_quantity <= 2
             ORDER BY e.available_quantity ASC, e.name ASC LIMIT " . (int) $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Derive the aggregate condition label from the quantity buckets.
     * Available good stock => 'good'; otherwise the dominant unavailable reason.
     */
    public static function deriveConditionStatus(array $q): string
    {
        if ((int) ($q['available_quantity'] ?? 0) > 0) {
            return 'good';
        }
        if ((int) ($q['borrowed_quantity'] ?? 0) > 0) {
            return 'good';
        }
        if ((int) ($q['damaged_quantity'] ?? 0) > 0) {
            return 'damaged';
        }
        if ((int) ($q['under_repair_quantity'] ?? 0) > 0) {
            return 'needs_maintenance';
        }
        if ((int) ($q['lost_quantity'] ?? 0) > 0) {
            return 'lost';
        }
        return 'good';
    }
}
