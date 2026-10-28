<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessException;
use App\Core\Config;
use App\Core\Database;
use App\Core\EventDispatcher;
use App\Models\AuditLog;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Models\User;

final class BorrowService
{
    /**
     * Submit a borrowing request. Pending requests do NOT reserve stock; the
     * availability re-check happens again inside the approval transaction.
     *
     * @param array{equipment_id:int, quantity:int, purpose:string, expected_return_date:string} $data
     */
    public function submit(int $userId, array $data): int
    {
        $equipmentId = (int) $data['equipment_id'];
        $quantity = (int) $data['quantity'];
        $purpose = trim($data['purpose']);
        $expected = $data['expected_return_date'];

        $equipment = Equipment::find($equipmentId);
        if (!$equipment) {
            throw new BusinessException('The selected equipment was not found.');
        }
        if ($equipment['record_status'] !== 'active') {
            throw new BusinessException('This equipment is archived and cannot be borrowed.');
        }
        if ($equipment['condition_status'] !== 'good') {
            throw new BusinessException('This equipment is not in borrowable condition.');
        }
        if ((int) $equipment['available_quantity'] < 1) {
            throw new BusinessException('This equipment is currently unavailable.');
        }
        if ($quantity < 1) {
            throw new BusinessException('Quantity must be at least 1.');
        }
        if ($quantity > (int) Config::get('borrowing.max_quantity', 20)) {
            throw new BusinessException('You may request at most ' . Config::get('borrowing.max_quantity', 20) . ' units per item.');
        }
        if ($quantity > (int) $equipment['available_quantity']) {
            throw new BusinessException('Only ' . (int) $equipment['available_quantity'] . ' unit(s) are currently available.');
        }
        if (mb_strlen($purpose) < 10) {
            throw new BusinessException('Describe the purpose in at least 10 characters.');
        }

        $today = new \DateTimeImmutable('today');
        $expectedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $expected) ?: null;
        if (!$expectedDate || $expectedDate->format('Y-m-d') !== $expected) {
            throw new BusinessException('Enter a valid expected return date.');
        }
        $maxDays = (int) Config::get('borrowing.max_days', 14);
        if ($expectedDate <= $today) {
            throw new BusinessException('The expected return date must be after today.');
        }
        if ($expectedDate > $today->modify('+' . $maxDays . ' days')) {
            throw new BusinessException('The expected return date cannot be more than ' . $maxDays . ' days from today.');
        }

        if (BorrowRequest::isDuplicateRecent($userId, $equipmentId, (int) Config::get('security.duplicate_request_seconds', 60))) {
            throw new BusinessException('A similar request was just submitted. Please wait a moment before trying again.');
        }

        return Database::transaction(function () use ($userId, $equipment, $quantity, $purpose, $expected): int {
            $requestId = BorrowRequest::create($userId, $purpose, $expected, [
                ['equipment_id' => (int) $equipment['id'], 'quantity' => $quantity],
            ]);

            $request = BorrowRequest::find($requestId);
            $user = User::find($userId);
            $requestCode = (string) ($request['request_code'] ?? '');
            $requesterName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

            AuditLog::record(
                'borrow.submitted',
                'borrow_request',
                $requestId,
                sprintf('%s submitted %s for %d × %s.', $requesterName, $requestCode, $quantity, $equipment['name']),
                null,
                ['status' => 'pending', 'equipment_id' => (int) $equipment['id'], 'quantity' => $quantity],
                $userId
            );

            EventDispatcher::dispatch('borrow.submitted', [
                'request_id' => $requestId,
                'request_code' => $requestCode,
                'user_id' => $userId,
                'requester_name' => $requesterName,
                'items_summary' => $quantity . ' × ' . $equipment['name'],
            ]);

            return $requestId;
        });
    }

    /**
     * Approve a pending request and create checkout transactions atomically.
     *
     * Concurrency control:
     *  - request row is locked, so double approval is impossible;
     *  - equipment rows are locked in id order before availability is checked;
     *  - availability is re-verified inside the transaction;
     *  - UNIQUE(request_item_id) on borrow_transactions blocks duplicate rows.
     *
     * @return array{request:array<string,mixed>, transactions:array<int,array<string,mixed>>}
     */
    public function approve(int $requestId, int $adminId): array
    {
        return Database::transaction(function () use ($requestId, $adminId): array {
            $request = BorrowRequest::lock($requestId);
            if (!$request) {
                throw new BusinessException('Borrowing request not found.');
            }
            if ($request['status'] !== 'pending') {
                throw new BusinessException('This request has already been processed (' . $request['status'] . ').');
            }
            if ((int) $request['user_id'] === $adminId) {
                throw new BusinessException('You cannot approve your own borrowing request.');
            }
            if ((string) $request['expected_return_date'] < date('Y-m-d')) {
                throw new BusinessException('The expected return date has already passed. Reject this request and ask the borrower to submit a new one.');
            }

            $items = BorrowRequest::items($requestId);
            if ($items === []) {
                throw new BusinessException('This request has no items to approve.');
            }

            usort($items, static fn (array $a, array $b): int => (int) $a['equipment_id'] <=> (int) $b['equipment_id']);

            $createdTransactions = [];
            $summaryParts = [];

            foreach ($items as $item) {
                $equipment = Equipment::lock((int) $item['equipment_id']);
                if (!$equipment) {
                    throw new BusinessException('Equipment in this request no longer exists.');
                }
                if ($equipment['record_status'] !== 'active') {
                    throw new BusinessException($equipment['name'] . ' is archived and cannot be checked out.');
                }
                if ($equipment['condition_status'] !== 'good') {
                    throw new BusinessException($equipment['name'] . ' is not in borrowable condition.');
                }

                $requested = (int) $item['quantity_requested'];
                $available = (int) $equipment['available_quantity'];
                if ($requested < 1 || $requested > $available) {
                    throw new BusinessException(
                        'Insufficient stock for ' . $equipment['name'] . '. Requested ' . $requested
                        . ', available ' . $available . '.'
                    );
                }

                Equipment::decrementAvailable((int) $equipment['id'], $requested);

                $transactionId = BorrowTransaction::create(
                    $requestId,
                    (int) $item['id'],
                    (int) $request['user_id'],
                    (int) $equipment['id'],
                    $requested,
                    date('Y-m-d H:i:s'),
                    $request['expected_return_date'] . ' 17:00:00',
                    $adminId
                );

                BorrowRequest::setItemApproved($requestId, (int) $equipment['id'], $requested);

                $transaction = BorrowTransaction::find($transactionId) ?? [];
                $createdTransactions[] = $transaction;
                $summaryParts[] = $requested . ' × ' . $equipment['name'] . ' (' . $transaction['transaction_code'] . ')';
            }

            BorrowRequest::updateStatus($requestId, 'approved', $adminId);

            AuditLog::record(
                'borrow.approved',
                'borrow_request',
                $requestId,
                'Approved ' . $request['request_code'] . ' and created checkout: ' . implode(', ', $summaryParts) . '.',
                ['status' => 'pending'],
                ['status' => 'approved', 'reviewer_id' => $adminId],
                $adminId
            );

            $borrower = User::find((int) $request['user_id']);
            EventDispatcher::dispatch('borrow.approved', [
                'request_id' => $requestId,
                'request_code' => $request['request_code'],
                'user_id' => (int) $request['user_id'],
                'borrower_name' => trim(($borrower['first_name'] ?? '') . ' ' . ($borrower['last_name'] ?? '')),
                'message' => 'Checkout: ' . implode(', ', $summaryParts) . '. Due ' . date('M j, Y', strtotime((string) $request['expected_return_date'])) . '.',
            ]);

            return [
                'request' => BorrowRequest::find($requestId) ?? [],
                'transactions' => $createdTransactions,
            ];
        });
    }

    public function reject(int $requestId, int $adminId, ?string $reason): void
    {
        $reason = $reason !== null ? trim($reason) : null;

        Database::transaction(function () use ($requestId, $adminId, $reason): void {
            $request = BorrowRequest::lock($requestId);
            if (!$request) {
                throw new BusinessException('Borrowing request not found.');
            }
            if ($request['status'] !== 'pending') {
                throw new BusinessException('This request has already been processed (' . $request['status'] . ').');
            }
            if ((int) $request['user_id'] === $adminId) {
                throw new BusinessException('You cannot review your own borrowing request.');
            }

            BorrowRequest::updateStatus($requestId, 'rejected', $adminId, $reason);

            AuditLog::record(
                'borrow.rejected',
                'borrow_request',
                $requestId,
                'Rejected ' . $request['request_code'] . ($reason ? '. Reason: ' . $reason : '.'),
                ['status' => 'pending'],
                ['status' => 'rejected', 'reason' => $reason],
                $adminId
            );

            EventDispatcher::dispatch('borrow.rejected', [
                'request_id' => $requestId,
                'request_code' => $request['request_code'],
                'user_id' => (int) $request['user_id'],
                'reason' => $reason,
            ]);
        });
    }

    public function cancel(int $requestId, int $userId): void
    {
        Database::transaction(function () use ($requestId, $userId): void {
            $request = BorrowRequest::lock($requestId);
            if (!$request) {
                throw new BusinessException('Borrowing request not found.');
            }
            if ((int) $request['user_id'] !== $userId) {
                throw new BusinessException('You can only cancel your own request.');
            }
            if ($request['status'] !== 'pending') {
                throw new BusinessException('Only pending requests can be cancelled.');
            }

            $stmt = Database::pdo()->prepare(
                "UPDATE borrow_requests SET status = 'cancelled', reviewed_at = NOW() WHERE id = :id"
            );
            $stmt->execute(['id' => $requestId]);

            AuditLog::record(
                'borrow.cancelled',
                'borrow_request',
                $requestId,
                'Cancelled ' . $request['request_code'] . '.',
                ['status' => 'pending'],
                ['status' => 'cancelled'],
                $userId
            );
        });
    }
}
