<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\EventDispatcher;
use App\Core\ValidationException;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\ConditionHistory;
use App\Models\Equipment;

final class EquipmentService
{
    public const BUCKETS = ['available', 'damaged', 'under_repair', 'lost'];

    /** @param array<string, mixed> $data */
    public function create(array $data, int $actorId): int
    {
        $this->assertCatalogFields($data);

        if (Equipment::codeExists((string) $data['equipment_code'])) {
            throw new ValidationException(['equipment_code' => 'This equipment ID is already in use.']);
        }
        if (Equipment::barcodeExists((string) ($data['barcode'] ?? ''))) {
            throw new ValidationException(['barcode' => 'This barcode/QR identifier is already assigned.']);
        }

        $total = (int) $data['total_quantity'];
        if ($total < 1 || $total > 10000) {
            throw new ValidationException(['total_quantity' => 'Total quantity must be between 1 and 10,000.']);
        }

        $id = Database::transaction(function () use ($data, $actorId, $total): int {
            $id = Equipment::create([
                'equipment_code'   => $data['equipment_code'],
                'name'             => $data['name'],
                'category_id'      => $data['category_id'],
                'description'      => $data['description'] ?? '',
                'total_quantity'   => $total,
                'condition_status' => 'good',
                'storage_location' => $data['storage_location'],
                'barcode'          => $data['barcode'] ?? '',
                'record_status'    => 'active',
            ]);

            AuditLog::record(
                'equipment.created',
                'equipment',
                $id,
                'Created equipment ' . $data['name'] . ' (' . $data['equipment_code'] . ') with ' . $total . ' unit(s).',
                null,
                ['total_quantity' => $total, 'available_quantity' => $total],
                $actorId
            );

            return $id;
        });

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, int $actorId): void
    {
        $this->assertCatalogFields($data);

        if (Equipment::codeExists((string) $data['equipment_code'], $id)) {
            throw new ValidationException(['equipment_code' => 'This equipment ID is already in use.']);
        }
        if (Equipment::barcodeExists((string) ($data['barcode'] ?? ''), $id)) {
            throw new ValidationException(['barcode' => 'This barcode/QR identifier is already assigned.']);
        }
        if (!in_array($data['record_status'] ?? 'active', ['active', 'archived'], true)) {
            throw new ValidationException(['record_status' => 'Select a valid record status.']);
        }

        $newTotal = (int) $data['total_quantity'];
        if ($newTotal < 1 || $newTotal > 10000) {
            throw new ValidationException(['total_quantity' => 'Total quantity must be between 1 and 10,000.']);
        }

        Database::transaction(function () use ($id, $data, $actorId, $newTotal): void {
            $existing = Equipment::lock($id);
            if (!$existing) {
                throw new BusinessException('Equipment record not found.');
            }

            if (($data['record_status'] ?? 'active') === 'archived' && (int) $existing['borrowed_quantity'] > 0) {
                throw new ValidationException([
                    'record_status' => 'Cannot archive equipment while units are still borrowed. Process the returns first.',
                ]);
            }

            $committed = (int) $existing['borrowed_quantity']
                + (int) $existing['damaged_quantity']
                + (int) $existing['under_repair_quantity']
                + (int) $existing['lost_quantity'];

            if ($newTotal < $committed) {
                throw new ValidationException([
                    'total_quantity' => 'Total cannot be lower than ' . $committed
                        . ' unit(s) already borrowed, damaged, under repair, or lost.',
                ]);
            }

            $newAvailable = $newTotal - $committed;
            $quantities = [
                'total_quantity'       => $newTotal,
                'available_quantity'   => $newAvailable,
                'borrowed_quantity'    => (int) $existing['borrowed_quantity'],
                'damaged_quantity'     => (int) $existing['damaged_quantity'],
                'under_repair_quantity' => (int) $existing['under_repair_quantity'],
                'lost_quantity'        => (int) $existing['lost_quantity'],
            ];
            $quantities['condition_status'] = Equipment::deriveConditionStatus($quantities);

            Equipment::update($id, [
                'equipment_code'   => $data['equipment_code'],
                'name'             => $data['name'],
                'category_id'      => $data['category_id'],
                'description'      => $data['description'] ?? '',
                'total_quantity'   => $newTotal,
                'available_quantity' => $newAvailable,
                'condition_status' => $quantities['condition_status'],
                'storage_location' => $data['storage_location'],
                'barcode'          => $data['barcode'] ?? '',
                'record_status'    => $data['record_status'],
            ]);

            AuditLog::record(
                'equipment.updated',
                'equipment',
                $id,
                'Updated equipment ' . $data['name'] . ' (' . $data['equipment_code'] . ').',
                [
                    'name' => $existing['name'],
                    'total_quantity' => (int) $existing['total_quantity'],
                    'available_quantity' => (int) $existing['available_quantity'],
                    'record_status' => $existing['record_status'],
                ],
                [
                    'name' => $data['name'],
                    'total_quantity' => $newTotal,
                    'available_quantity' => $newAvailable,
                    'record_status' => $data['record_status'],
                ],
                $actorId
            );
        });
    }

    /**
     * Move units between inventory buckets without changing the total.
     * Damaged / under-repair / lost units are never placed in available stock.
     */
    public function adjustCondition(int $id, string $from, string $to, int $quantity, ?string $notes, int $actorId): array
    {
        if (!in_array($from, self::BUCKETS, true) || !in_array($to, self::BUCKETS, true)) {
            throw new ValidationException(['bucket' => 'Select a valid inventory bucket.']);
        }
        if ($from === $to) {
            throw new ValidationException(['to_bucket' => 'Source and destination condition must differ.']);
        }
        if ($quantity < 1) {
            throw new ValidationException(['quantity' => 'Quantity must be at least 1.']);
        }

        return Database::transaction(function () use ($id, $from, $to, $quantity, $notes, $actorId): array {
            $eq = Equipment::lock($id);
            if (!$eq) {
                throw new BusinessException('Equipment record not found.');
            }

            $columns = [
                'available'    => 'available_quantity',
                'damaged'      => 'damaged_quantity',
                'under_repair' => 'under_repair_quantity',
                'lost'         => 'lost_quantity',
            ];

            $fromColumn = $columns[$from];
            if ((int) $eq[$fromColumn] < $quantity) {
                throw new BusinessException('Only ' . (int) $eq[$fromColumn] . ' unit(s) are currently in that condition.');
            }

            $previousCondition = (string) $eq['condition_status'];
            $eq[$fromColumn] = (int) $eq[$fromColumn] - $quantity;
            $eq[$columns[$to]] = (int) $eq[$columns[$to]] + $quantity;
            $eq['condition_status'] = Equipment::deriveConditionStatus($eq);

            Equipment::updateQuantities($id, [
                'total_quantity' => (int) $eq['total_quantity'],
                'available_quantity' => (int) $eq['available_quantity'],
                'borrowed_quantity' => (int) $eq['borrowed_quantity'],
                'damaged_quantity' => (int) $eq['damaged_quantity'],
                'under_repair_quantity' => (int) $eq['under_repair_quantity'],
                'lost_quantity' => (int) $eq['lost_quantity'],
                'condition_status' => $eq['condition_status'],
            ]);

            $conditionMap = [
                'available' => 'good',
                'damaged' => 'damaged',
                'under_repair' => 'needs_maintenance',
                'lost' => 'lost',
            ];
            $fromCondition = $conditionMap[$from];
            $toCondition = $conditionMap[$to];

            ConditionHistory::create(
                $id,
                null,
                null,
                $previousCondition,
                $toCondition,
                $quantity,
                $notes ?: ('Moved ' . $quantity . ' unit(s) from ' . $from . ' to ' . $to . '.'),
                $actorId
            );

            AuditLog::record(
                'equipment.condition_changed',
                'equipment',
                $id,
                sprintf('Adjusted %s: %d unit(s) moved from %s to %s.', $eq['name'], $quantity, $from, $to),
                [$from => (int) $eq[$fromColumn] + $quantity],
                [$to => (int) $eq[$columns[$to]]],
                $actorId
            );

            EventDispatcher::dispatch('equipment.condition_changed', [
                'equipment_id' => $id,
                'equipment_name' => $eq['name'],
                'equipment_code' => $eq['equipment_code'],
                'from_label' => condition_label($from),
                'to_label' => condition_label($to),
                'quantity' => $quantity,
                'actor_id' => $actorId,
            ]);

            return Equipment::find($id) ?? $eq;
        });
    }

    public function archive(int $id, int $actorId): void
    {
        Database::transaction(function () use ($id, $actorId): void {
            $eq = Equipment::lock($id);
            if (!$eq) {
                throw new BusinessException('Equipment record not found.');
            }
            if ($eq['record_status'] === 'archived') {
                throw new BusinessException('This equipment is already archived.');
            }
            if ((int) $eq['borrowed_quantity'] > 0) {
                throw new BusinessException('Cannot archive equipment while units are still borrowed. Process the returns first.');
            }

            $stmt = Database::pdo()->prepare("UPDATE equipment SET record_status = 'archived' WHERE id = :id");
            $stmt->execute(['id' => $id]);

            AuditLog::record(
                'equipment.archived',
                'equipment',
                $id,
                'Archived equipment ' . $eq['name'] . ' (' . $eq['equipment_code'] . ').',
                ['record_status' => 'active'],
                ['record_status' => 'archived'],
                $actorId
            );

            EventDispatcher::dispatch('equipment.archived', [
                'equipment_id' => $id,
                'equipment_name' => $eq['name'],
                'equipment_code' => $eq['equipment_code'],
            ]);
        });
    }

    public function restore(int $id, int $actorId): void
    {
        Database::transaction(function () use ($id, $actorId): void {
            $eq = Equipment::lock($id);
            if (!$eq) {
                throw new BusinessException('Equipment record not found.');
            }
            if ($eq['record_status'] === 'active') {
                throw new BusinessException('This equipment is already active.');
            }

            $stmt = Database::pdo()->prepare("UPDATE equipment SET record_status = 'active' WHERE id = :id");
            $stmt->execute(['id' => $id]);

            AuditLog::record(
                'equipment.restored',
                'equipment',
                $id,
                'Restored equipment ' . $eq['name'] . ' (' . $eq['equipment_code'] . ') to the active catalog.',
                ['record_status' => 'archived'],
                ['record_status' => 'active'],
                $actorId
            );
        });
    }

    /** @param array<string, mixed> $data */
    private function assertCatalogFields(array $data): void
    {
        if (!Category::find((int) ($data['category_id'] ?? 0))) {
            throw new ValidationException(['category_id' => 'Select a valid category.']);
        }
        if (trim((string) ($data['storage_location'] ?? '')) === '') {
            throw new ValidationException(['storage_location' => 'Storage location is required.']);
        }
    }
}
