<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ConditionHistory
{
    public static function create(
        int $equipmentId,
        ?int $returnRecordId,
        ?int $transactionId,
        string $previousCondition,
        string $newCondition,
        int $quantityAffected,
        ?string $notes,
        int $recordedBy
    ): int {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO equipment_condition_history
                (equipment_id, return_record_id, borrow_transaction_id, previous_condition, new_condition,
                 quantity_affected, notes, recorded_by)
             VALUES (:equipment_id, :return_record_id, :transaction_id, :previous, :new,
                 :quantity, :notes, :recorded_by)'
        );
        $stmt->execute([
            'equipment_id' => $equipmentId,
            'return_record_id' => $returnRecordId,
            'transaction_id' => $transactionId,
            'previous' => $previousCondition,
            'new' => $newCondition,
            'quantity' => $quantityAffected,
            'notes' => $notes,
            'recorded_by' => $recordedBy,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public static function forEquipment(int $equipmentId, int $limit = 20): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT h.*, u.first_name, u.last_name
             FROM equipment_condition_history h
             LEFT JOIN users u ON u.id = h.recorded_by
             WHERE h.equipment_id = :id
             ORDER BY h.recorded_at DESC, h.id DESC LIMIT ' . (int) $limit
        );
        $stmt->execute(['id' => $equipmentId]);
        return $stmt->fetchAll();
    }
}
