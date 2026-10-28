<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Models\Notification;
use App\Models\ReturnRecord;
use App\Models\User;

final class DashboardService
{
    public function admin(): array
    {
        $equipment = Equipment::stats();
        $requests = BorrowRequest::countByStatus();
        $transactions = BorrowTransaction::stats();
        $users = User::countByRole();

        return [
            'equipment' => $equipment,
            'requests' => $requests,
            'transactions' => $transactions,
            'users' => $users,
            'returns' => ReturnRecord::count(),
            'pending' => $requests['pending'],
            'overdue' => $transactions['overdue_total'] ?? 0,
            'condition_counts' => ReturnRecord::conditionCounts(),
            'distribution' => Equipment::statusDistribution(),
            'categories' => Equipment::categoryDistribution(),
            'popular' => Equipment::popular(5),
            'trend' => BorrowRequest::monthlyTrend(6),
            'recent_requests' => BorrowRequest::recent(6),
            'pending_requests' => BorrowRequest::paginate(['status' => 'pending'], 1, 5),
            'recent_audit' => AuditLog::recent(8),
            'low_stock' => Equipment::lowStock(4),
            'overdue_list' => BorrowTransaction::overdue(null, 5),
        ];
    }

    public function borrower(int $userId): array
    {
        $requests = BorrowRequest::countByStatus($userId);
        $transactions = BorrowTransaction::stats($userId);
        $open = BorrowTransaction::openForUser($userId);
        $overdue = BorrowTransaction::overdue($userId, 10);

        return [
            'requests' => $requests,
            'transactions' => $transactions,
            'open' => $open,
            'overdue' => $overdue,
            'recent_requests' => BorrowRequest::recent(5, $userId),
            'unread' => Notification::unreadCount($userId),
            'recommended' => Equipment::availableForSelect(),
        ];
    }
}
