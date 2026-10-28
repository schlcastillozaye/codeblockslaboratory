<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Models\Notification;

final class NotificationController extends Controller
{
    public function index(): void
    {
        $showAll = ($_GET['show'] ?? '') === 'all';
        $notifications = Notification::forUser((int) Auth::id(), 100, !$showAll);

        $this->render('notifications/index', [
            'title' => 'Notifications',
            'activeNav' => 'notifications',
            'notifications' => $notifications,
            'showAll' => $showAll,
        ]);
    }

    public function open(string $id): void
    {
        $notification = $this->ownedNotification((int) $id);
        Notification::markRead((int) $notification['id'], (int) Auth::id());

        $link = trim((string) ($notification['link'] ?? ''));
        $target = ($link !== '' && !str_contains($link, '://') && !str_starts_with($link, 'javascript:'))
            ? url($link)
            : url('notifications');

        if ($this->wantsJson()) {
            $this->json([
                'ok' => true,
                'id' => (int) $notification['id'],
                'unread' => Notification::unreadCount((int) Auth::id()),
                'link' => $target,
            ]);
        }

        redirect($target);
    }

    public function markRead(string $id): void
    {
        $notification = $this->ownedNotification((int) $id);
        Notification::markRead((int) $notification['id'], (int) Auth::id());

        if ($this->wantsJson()) {
            $this->json([
                'ok' => true,
                'id' => (int) $notification['id'],
                'unread' => Notification::unreadCount((int) Auth::id()),
                'message' => 'Notification marked as read.',
            ]);
        }

        Flash::set('success', 'Notification marked as read.');
        back('notifications');
    }

    public function dismiss(string $id): void
    {
        $notification = $this->ownedNotification((int) $id);
        Notification::dismiss((int) $notification['id'], (int) Auth::id());

        if ($this->wantsJson()) {
            $this->json([
                'ok' => true,
                'id' => (int) $notification['id'],
                'unread' => Notification::unreadCount((int) Auth::id()),
                'message' => 'Notification dismissed.',
            ]);
        }

        Flash::set('success', 'Notification dismissed.');
        back('notifications');
    }

    public function markAllRead(): void
    {
        $count = Notification::markAllRead((int) Auth::id());

        if ($this->wantsJson()) {
            $this->json([
                'ok' => true,
                'count' => $count,
                'unread' => 0,
                'message' => $count > 0
                    ? 'All notifications marked as read.'
                    : 'You have no unread notifications.',
            ]);
        }

        Flash::set('success', $count > 0
            ? $count . ' notification(s) marked as read.'
            : 'You have no unread notifications.');
        redirect(url('notifications'));
    }

    /** @return array<string, mixed> */
    private function ownedNotification(int $id): array
    {
        $notification = Notification::find($id);
        if (!$notification || (int) $notification['user_id'] !== (int) Auth::id()) {
            if ($this->wantsJson()) {
                $this->json(['ok' => false, 'message' => 'That notification is not available.'], 404);
            }
            $this->forbidden('That notification does not belong to your account.');
        }

        return $notification;
    }
}
