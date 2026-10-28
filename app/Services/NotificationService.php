<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\EventDispatcher;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;

/**
 * Local, in-app notification observer.
 *
 * This is an Observer-inspired mechanism: services dispatch domain events and
 * this listener writes notifications for the affected users. NO email or SMS
 * is sent. Those channels are documented as future integrations.
 */
final class NotificationService
{
    public static function registerObservers(): void
    {
        EventDispatcher::listen('borrow.submitted', static function (array $p): void {
            $admins = self::adminIds();
            $title = 'New borrowing request';
            $message = sprintf('%s submitted %s for %s.', $p['requester_name'], $p['request_code'], $p['items_summary']);
            foreach ($admins as $adminId) {
                Notification::create($adminId, 'borrow_request', $title, $message, url('borrow/' . $p['request_id']));
            }
            Notification::create(
                (int) $p['user_id'],
                'borrow_request',
                'Request submitted',
                sprintf('Your request %s is pending laboratory personnel review.', $p['request_code']),
                url('borrow/' . $p['request_id'])
            );
        });

        EventDispatcher::listen('borrow.approved', static function (array $p): void {
            Notification::create(
                (int) $p['user_id'],
                'approval',
                'Borrowing request approved',
                sprintf('%s was approved. %s', $p['request_code'], $p['message'] ?? ''),
                url('borrow/' . $p['request_id'])
            );
        });

        EventDispatcher::listen('borrow.rejected', static function (array $p): void {
            Notification::create(
                (int) $p['user_id'],
                'rejection',
                'Borrowing request rejected',
                sprintf('%s was rejected. %s', $p['request_code'], $p['reason'] ? 'Reason: ' . $p['reason'] : 'Contact the laboratory for details.'),
                url('borrow/' . $p['request_id'])
            );
        });

        EventDispatcher::listen('return.processed', static function (array $p): void {
            Notification::create(
                (int) $p['user_id'],
                'return',
                'Return recorded',
                sprintf('%s: %d unit(s) of %s recorded as %s.', $p['return_code'], $p['quantity'], $p['equipment_name'], $p['condition_label']),
                url('borrow/' . $p['request_id'])
            );

            if ($p['condition'] !== 'good') {
                foreach (self::adminIds() as $adminId) {
                    Notification::create(
                        $adminId,
                        'condition',
                        'Equipment condition attention',
                        sprintf('%s returned as %s. Review the repair/damage queue.', $p['equipment_name'], $p['condition_label']),
                        url('equipment/' . $p['equipment_id'])
                    );
                }
            }
        });

        EventDispatcher::listen('equipment.condition_changed', static function (array $p): void {
            foreach (self::adminIds() as $adminId) {
                if ($adminId === (int) ($p['actor_id'] ?? 0)) {
                    continue;
                }
                Notification::create(
                    $adminId,
                    'condition',
                    'Inventory condition updated',
                    sprintf('%s: %s → %s (%d unit(s)).', $p['equipment_name'], $p['from_label'], $p['to_label'], $p['quantity']),
                    url('equipment/' . $p['equipment_id'])
                );
            }
        });

        EventDispatcher::listen('equipment.archived', static function (array $p): void {
            foreach (self::adminIds() as $adminId) {
                Notification::create(
                    $adminId,
                    'inventory',
                    'Equipment archived',
                    sprintf('%s (%s) was archived.', $p['equipment_name'], $p['equipment_code']),
                    url('equipment/' . $p['equipment_id'])
                );
            }
        });
    }

    /** @return array<int, int> */
    public static function adminIds(): array
    {
        return array_map(
            'intval',
            Database::pdo()->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")
                ->fetchAll(\PDO::FETCH_COLUMN)
        );
    }

    public static function notifyDueSoon(int $days = 2): int
    {
        $stmt = Database::pdo()->prepare(
            "SELECT bt.id, bt.user_id, bt.due_at, e.name AS equipment_name
             FROM borrow_transactions bt JOIN equipment e ON e.id = bt.equipment_id
             WHERE bt.status IN ('active','overdue','partially_returned')
               AND bt.due_at >= NOW() AND bt.due_at <= DATE_ADD(NOW(), INTERVAL :days DAY)
               AND NOT EXISTS (
                   SELECT 1 FROM notifications n
                   WHERE n.user_id = bt.user_id AND n.type = 'due_soon'
                     AND n.message LIKE CONCAT('%', e.name, '%')
                     AND n.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
               )"
        );
        $stmt->execute(['days' => $days]);
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            Notification::create(
                (int) $row['user_id'],
                'due_soon',
                'Equipment due soon',
                sprintf('%s is due on %s.', $row['equipment_name'], date('M j, Y', strtotime((string) $row['due_at']))),
                url('transactions')
            );
        }

        return count($rows);
    }
}
