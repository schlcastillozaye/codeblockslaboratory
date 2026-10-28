<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\EventDispatcher;
use App\Core\ValidationException;
use App\Models\AuditLog;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\ConditionHistory;
use App\Models\Equipment;
use App\Models\ReturnRecord;

final class ReturnService
{
    /**
     * Process a return atomically.
     *
     * The condition is mandatory and is validated BEFORE any write. The
     * transaction row is locked to prevent double returns and over-returning.
     * Inventory buckets, transaction, return record, condition history, audit
     * log and notifications either all commit or all roll back.
     *
     * @param array{quantity:int, return_date:string, condition:string, notes:?string} $data
     * @return array{transaction:array<string,mixed>, return:array<string,mixed>}
     */
    public function process(int $transactionId, array $data, int $adminId): array
    {
        $condition = trim((string) ($data['condition'] ?? ''));
        if ($condition === '') {
            throw new ValidationException(
                ['condition' => 'Select the condition of the returned equipment. This is required.'],
                'A return cannot be completed without recording the equipment condition.'
            );
        }
        if (!in_array($condition, ReturnRecord::CONDITIONS, true)) {
            throw new ValidationException(['condition' => 'Select a valid equipment condition.']);
        }

        $quantity = (int) ($data['quantity'] ?? 0);
        if ($quantity < 1) {
            throw new ValidationException(['quantity' => 'Return quantity must be at least 1.']);
        }

        $returnDate = (string) ($data['return_date'] ?? '');
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $returnDate);
        if (!$parsed || $parsed->format('Y-m-d') !== $returnDate) {
            throw new ValidationException(['return_date' => 'Enter a valid return date.']);
        }
        if ($parsed > new \DateTimeImmutable('today')) {
            throw new ValidationException(['return_date' => 'The return date cannot be in the future.']);
        }
        if (mb_strlen((string) ($data['notes'] ?? '')) > 500) {
            throw new ValidationException(['notes' => 'Notes must not exceed 500 characters.']);
        }

        return Database::transaction(function () use ($transactionId, $data, $condition, $quantity, $returnDate, $adminId): array {
            $tx = BorrowTransaction::lock($transactionId);
            if (!$tx) {
                throw new BusinessException('Borrowing transaction not found.');
            }
            if ($tx['status'] === 'returned') {
                throw new BusinessException('This transaction has already been fully returned.');
            }
            if (!in_array($tx['status'], BorrowTransaction::OPEN_STATUSES, true)) {
                throw new BusinessException('This transaction is not open for return.');
            }
            if ($returnDate < date('Y-m-d', strtotime((string) $tx['borrowed_at']))) {
                throw new ValidationException(['return_date' => 'The return date cannot be earlier than the checkout date.']);
            }

            $outstanding = (int) $tx['quantity'] - (int) $tx['quantity_returned'];
            if ($quantity > $outstanding) {
                throw new ValidationException([
                    'quantity' => 'Return quantity exceeds the outstanding quantity (' . $outstanding . ').',
                ]);
            }

            $equipment = Equipment::lock((int) $tx['equipment_id']);
            if (!$equipment) {
                throw new BusinessException('The equipment record for this transaction no longer exists.');
            }

            $previousCondition = (string) $equipment['condition_status'];
            $bucketColumn = match ($condition) {
                'good' => 'available_quantity',
                'damaged', 'incomplete' => 'damaged_quantity',
                'needs_maintenance' => 'under_repair_quantity',
                default => throw new ValidationException(['condition' => 'Unsupported condition.']),
            };

            if ((int) $equipment['borrowed_quantity'] < $quantity) {
                throw new BusinessException('Inventory shows fewer borrowed units than this return. Contact the administrator.');
            }

            $equipment['borrowed_quantity'] = (int) $equipment['borrowed_quantity'] - $quantity;
            $equipment[$bucketColumn] = (int) $equipment[$bucketColumn] + $quantity;
            $equipment['condition_status'] = Equipment::deriveConditionStatus($equipment);

            Equipment::updateQuantities((int) $equipment['id'], [
                'total_quantity' => (int) $equipment['total_quantity'],
                'available_quantity' => (int) $equipment['available_quantity'],
                'borrowed_quantity' => (int) $equipment['borrowed_quantity'],
                'damaged_quantity' => (int) $equipment['damaged_quantity'],
                'under_repair_quantity' => (int) $equipment['under_repair_quantity'],
                'lost_quantity' => (int) $equipment['lost_quantity'],
                'condition_status' => $equipment['condition_status'],
            ]);

            $newReturned = (int) $tx['quantity_returned'] + $quantity;
            $newStatus = $newReturned >= (int) $tx['quantity'] ? 'returned' : 'partially_returned';
            BorrowTransaction::updateAfterReturn($transactionId, $quantity, $newReturned, $newStatus);

            $returnId = ReturnRecord::create(
                $transactionId,
                (int) $equipment['id'],
                $quantity,
                $returnDate,
                $condition,
                $data['notes'] ?: null,
                $adminId
            );

            ConditionHistory::create(
                (int) $equipment['id'],
                $returnId,
                $transactionId,
                $previousCondition,
                $condition,
                $quantity,
                $data['notes'] ?: null,
                $adminId
            );

            $returnRecord = null;
            foreach (ReturnRecord::forTransaction($transactionId) as $record) {
                if ((int) $record['id'] === $returnId) {
                    $returnRecord = $record;
                    break;
                }
            }

            // Mark the parent request completed when every checkout is returned.
            if ($newStatus === 'returned' && BorrowRequest::completedFor((int) $tx['request_id'])) {
                $stmt = Database::pdo()->prepare("UPDATE borrow_requests SET status = 'completed' WHERE id = :id AND status = 'approved'");
                $stmt->execute(['id' => (int) $tx['request_id']]);
            }

            AuditLog::record(
                'return.processed',
                'return_record',
                $returnId,
                sprintf(
                    'Return %s: %d × %s recorded as %s (transaction %s).',
                    $returnRecord['return_code'] ?? ('#' . $returnId),
                    $quantity,
                    $equipment['name'],
                    condition_label($condition),
                    $tx['transaction_code']
                ),
                ['borrowed_quantity' => (int) $tx['quantity'], 'status' => $tx['status']],
                ['returned_quantity' => $quantity, 'condition' => $condition, 'status' => $newStatus],
                $adminId
            );

            EventDispatcher::dispatch('return.processed', [
                'transaction_id' => $transactionId,
                'request_id' => (int) $tx['request_id'],
                'return_id' => $returnId,
                'return_code' => $returnRecord['return_code'] ?? '',
                'user_id' => (int) $tx['user_id'],
                'equipment_id' => (int) $equipment['id'],
                'equipment_name' => $equipment['name'],
                'quantity' => $quantity,
                'condition' => $condition,
                'condition_label' => condition_label($condition),
            ]);

            return [
                'transaction' => BorrowTransaction::find($transactionId) ?? [],
                'return' => $returnRecord ?? [],
            ];
        });
    }
}
