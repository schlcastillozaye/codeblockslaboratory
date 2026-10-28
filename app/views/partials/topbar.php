<?php
$user = $currentUser ?? null;
$notifications = $navNotifications ?? [];
$unread = (int) ($unreadNotifications ?? 0);
$pageTitle = $title ?? 'Dashboard';
$crumb = $activeNav ?? '';
$crumbLabels = [
    'dashboard' => 'Overview',
    'equipment' => 'Equipment',
    'borrow' => 'Borrowing',
    'approvals' => 'Approvals',
    'transactions' => 'Transactions',
    'returns' => 'Returns',
    'users' => 'Users',
    'reports' => 'Reports',
    'audit' => 'Audit Trail',
    'notifications' => 'Notifications',
    'profile' => 'Profile',
];
$crumbLabel = $crumbLabels[$crumb] ?? 'Workspace';
$notifIconClass = static function (string $type): string {
    return match ($type) {
        'approval', 'return' => 'success',
        'rejection', 'condition' => 'danger',
        'due_soon' => 'warning',
        default => '',
    };
};
$notifIconName = static function (string $type): string {
    return match ($type) {
        'approval' => 'check-circle',
        'rejection' => 'x-circle',
        'return' => 'rotate-ccw',
        'condition' => 'alert-triangle',
        'due_soon' => 'clock',
        default => 'bell',
    };
};
?>
<header class="topbar">
    <button type="button" class="topbar__menu" data-sidebar-toggle aria-label="Open navigation" aria-controls="app-sidebar">
        <?= icon('menu') ?>
    </button>

    <div class="topbar__titles">
        <div class="topbar__title"><?= e($pageTitle) ?></div>
        <div class="topbar__crumb">Code Blocks Laboratory Equipment Borrowing System / <?= e($crumbLabel) ?></div>
    </div>

    <div class="topbar__right">
        <div class="dropdown" data-dropdown>
            <button type="button" class="icon-btn" data-dropdown-trigger aria-label="Notifications (<?= $unread ?> unread)" aria-expanded="false" aria-haspopup="true" data-testid="notification-bell">
                <?= icon('bell') ?>
                <?php if ($unread > 0): ?>
                    <span class="icon-btn__count" data-testid="notification-count"><?= $unread > 99 ? '99+' : $unread ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown__menu notif-menu" role="region" aria-label="Notifications">
                <div class="notif-menu__head">
                    <?= icon('bell', 'icon icon--sm') ?> Notifications
                    <?php if ($unread > 0): ?>
                        <span class="badge badge--info" data-notification-dropdown-count><?= $unread ?> new</span>
                    <?php endif; ?>
                    <a href="<?= e(url('notifications')) ?>">View all</a>
                </div>
                <div class="notif-list" data-notification-dropdown-list>
                    <?php if ($notifications === []): ?>
                        <div class="empty-state" style="padding: 26px 18px;" data-notification-dropdown-empty>
                            <div class="empty-state__icon" style="width:44px;height:44px;margin-bottom:8px;"><?= icon('check-circle', 'icon icon--sm') ?></div>
                            <p style="font-size:12px;margin:0;">You are all caught up.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $note): ?>
                            <?php $noteLink = trim((string) ($note['link'] ?? '')); ?>
                            <div class="notif <?= ((int) $note['is_read'] === 1) ? 'is-read' : '' ?>"
                                 data-notification-item data-notification-id="<?= (int) $note['id'] ?>"
                                 data-notification-unread="<?= ((int) $note['is_read'] === 0) ? '1' : '0' ?>">
                                <span class="notif__icon notif__icon--<?= e($notifIconClass($note['type'])) ?>"><?= icon($notifIconName($note['type']), 'icon icon--sm') ?></span>
                                <form method="post" action="<?= e(url('notifications/' . (int) $note['id'] . '/open')) ?>"
                                      class="notif__open-form" data-notification-open
                                      data-notification-link="<?= e($noteLink !== '' ? url($noteLink) : url('notifications')) ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="notif__open">
                                        <span class="notif__content">
                                            <span class="notif__title"><?= e($note['title']) ?></span>
                                            <span class="notif__text"><?= e(truncate($note['message'], 90)) ?></span>
                                            <span class="notif__time"><?= e(time_ago($note['created_at'])) ?></span>
                                        </span>
                                        <?php if ((int) $note['is_read'] === 0): ?><span class="notif__dot" aria-label="Unread"></span><?php endif; ?>
                                    </button>
                                </form>
                                <form method="post" action="<?= e(url('notifications/' . (int) $note['id'] . '/dismiss')) ?>"
                                      class="notif__dismiss-form" data-notification-action="dismiss">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="notif__dismiss" title="Dismiss notification"
                                            aria-label="Dismiss notification" data-testid="notification-dismiss-<?= (int) $note['id'] ?>">
                                        <?= icon('x', 'icon icon--xs') ?>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="notif-menu__foot">
                    <a href="<?= e(url('notifications')) ?>" class="btn btn--sm btn--ghost btn--block">Open notification center</a>
                </div>
            </div>
        </div>

        <button type="button" class="icon-btn theme-toggle" data-theme-toggle aria-label="Switch to dark mode"
                aria-pressed="false" title="Switch to dark mode" data-testid="theme-toggle">
            <span class="theme-toggle__sun"><?= icon('sun', 'icon icon--sm') ?></span>
            <span class="theme-toggle__moon"><?= icon('moon', 'icon icon--sm') ?></span>
        </button>

        <div class="dropdown" data-dropdown>
            <button type="button" class="user-chip" data-dropdown-trigger aria-expanded="false" aria-haspopup="menu" aria-label="Account menu" data-testid="user-menu-toggle">
                <span class="avatar"><?= e(initials($user['first_name'] ?? 'G', $user['last_name'] ?? '')) ?></span>
                <span class="user-chip__meta">
                    <span class="user-chip__name"><?= e(($user['first_name'] ?? 'Guest') . ' ' . ($user['last_name'] ?? '')) ?></span>
                    <span class="user-chip__role"><?= e(role_short_label($user['role'] ?? null)) ?></span>
                </span>
                <?= icon('chevron-down', 'icon icon--xs') ?>
            </button>
            <div class="dropdown__menu" role="menu">
                <div class="dropdown__header">Signed in as <?= e($user['email'] ?? '') ?></div>
                <a class="dropdown__item" href="<?= e(url('profile')) ?>" role="menuitem"><?= icon('user') ?> My profile</a>
                <a class="dropdown__item" href="<?= e(url('notifications')) ?>" role="menuitem"><?= icon('bell') ?> Notifications <?php if ($unread > 0): ?><span class="badge badge--info" style="margin-left:auto;"><?= $unread ?></span><?php endif; ?></a>
                <div class="dropdown__divider"></div>
                <form method="post" action="<?= e(url('logout')) ?>" data-loading>
                    <?= csrf_field() ?>
                    <button type="submit" class="dropdown__item" role="menuitem" data-testid="logout-button"><?= icon('logout') ?> Sign out</button>
                </form>
            </div>
        </div>
    </div>
</header>
