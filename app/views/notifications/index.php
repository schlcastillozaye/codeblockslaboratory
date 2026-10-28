<?php
$iconClass = static function (string $type): string {
    return match ($type) {
        'approval', 'return' => 'success',
        'rejection', 'condition' => 'danger',
        'due_soon' => 'warning',
        default => '',
    };
};
$iconName = static function (string $type): string {
    return match ($type) {
        'approval' => 'check-circle',
        'rejection' => 'x-circle',
        'return' => 'rotate-ccw',
        'condition' => 'alert-triangle',
        'due_soon' => 'clock',
        default => 'bell',
    };
};
$showAll = (bool) ($showAll ?? false);
$unread = count(array_filter($notifications, static fn (array $n): bool => (int) $n['is_read'] === 0));
?>
<div class="page-header reveal">
    <div>
        <div class="page-header__eyebrow"><?= icon('bell', 'icon icon--sm') ?> Account updates</div>
        <h1 class="page-title">Notifications</h1>
        <p class="page-subtitle">
            Approvals, due-date reminders, and return condition reports appear here. Unread updates are shown first;
            open History to revisit earlier notices.
        </p>
    </div>
    <div class="page-actions">
        <span class="badge badge--<?= $unread > 0 ? 'info' : 'muted' ?>" data-testid="unread-notification-count"
              data-notification-unread-label><?= $unread ?> unread</span>
        <?php if ($unread > 0): ?>
            <form method="post" action="<?= e(url('notifications/read-all')) ?>" data-notification-action="read-all"
                  data-testid="mark-all-read-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--primary" data-testid="mark-all-read"><?= icon('check') ?> Mark all as read</button>
            </form>
        <?php endif; ?>
        <?php if ($showAll): ?>
            <a class="btn btn--ghost" href="<?= e(url('notifications')) ?>" data-testid="notifications-unread-link"><?= icon('bell') ?> Unread only</a>
        <?php else: ?>
            <a class="btn btn--ghost" href="<?= e(url('notifications', ['show' => 'all'])) ?>" data-testid="notifications-history-link"><?= icon('clock') ?> History</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($notifications === []): ?>
    <div class="card reveal" data-notification-empty>
        <div class="empty-state" data-testid="notifications-empty">
            <div class="empty-state__icon"><?= icon('bell', 'icon icon--xl') ?></div>
            <h3><?= $showAll ? 'No notifications on record' : 'You are all caught up' ?></h3>
            <p>
                <?= $showAll
                    ? 'Earlier notifications will appear here once they are available.'
                    : 'New approvals, reminders, and return reports will appear here.' ?>
            </p>
            <a class="btn btn--primary" href="<?= e(url('equipment')) ?>"><?= icon('boxes') ?> Browse equipment</a>
        </div>
    </div>
<?php else: ?>
    <section class="card reveal" data-testid="notifications-list" data-notification-list
             data-notification-mode="<?= $showAll ? 'all' : 'unread' ?>">
        <div class="notification-list">
            <?php foreach ($notifications as $note): ?>
                <?php
                $isRead = (int) $note['is_read'] === 1;
                $link = trim((string) ($note['link'] ?? ''));
                $target = $link !== '' ? url($link) : url('notifications');
                $relativeTime = time_ago($note['created_at']);
                $absoluteTime = format_datetime($note['created_at']);
                $timeText = (str_contains($relativeTime, 'ago') || $relativeTime === 'just now')
                    ? $relativeTime . ' · ' . $absoluteTime
                    : $absoluteTime;
                ?>
                <article class="notification-item <?= $isRead ? 'is-read' : '' ?>" data-notification-item
                         data-notification-id="<?= (int) $note['id'] ?>" data-notification-unread="<?= $isRead ? '0' : '1' ?>"
                         data-testid="notification-<?= (int) $note['id'] ?>">
                    <div class="notification-item__row">
                        <span class="notif__icon notif__icon--<?= e($iconClass($note['type'])) ?>">
                            <?= icon($iconName($note['type']), 'icon icon--sm') ?>
                        </span>
                        <div class="notification-item__body">
                            <form method="post" action="<?= e(url('notifications/' . (int) $note['id'] . '/open')) ?>"
                                  data-notification-open data-notification-link="<?= e($target) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="notification-open">
                                    <span class="notification-open__title">
                                        <?= e($note['title']) ?>
                                        <?php if (!$isRead): ?>
                                            <span class="badge badge--info" data-notification-new>New</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="notification-open__text"><?= e($note['message']) ?></span>
                                    <span class="notification-open__time">
                                        <?= e($timeText) ?>
                                        <?php if (!empty($note['link'])): ?> · Open record →<?php endif; ?>
                                    </span>
                                </button>
                            </form>
                        </div>
                        <div class="notification-actions">
                            <?php if (!$isRead): ?>
                                <form method="post" action="<?= e(url('notifications/' . (int) $note['id'] . '/read')) ?>"
                                      data-notification-action="read">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="notification-action" title="Mark as read"
                                            aria-label="Mark notification as read" data-testid="notification-read-<?= (int) $note['id'] ?>">
                                        <?= icon('check', 'icon icon--sm') ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('notifications/' . (int) $note['id'] . '/dismiss')) ?>"
                                  data-notification-action="dismiss">
                                <?= csrf_field() ?>
                                <button type="submit" class="notification-action notification-action--danger"
                                        title="Dismiss notification" aria-label="Dismiss notification"
                                        data-testid="notification-dismiss-<?= (int) $note['id'] ?>">
                                    <?= icon('x', 'icon icon--sm') ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="empty-state notification-empty" data-notification-empty hidden>
            <div class="empty-state__icon"><?= icon('check-circle', 'icon icon--xl') ?></div>
            <h3>You are all caught up</h3>
            <p>There are no unread notifications right now.</p>
        </div>
    </section>
<?php endif; ?>

<div class="callout reveal" style="margin-top:18px;">
    <?= icon('info', 'icon icon--sm') ?>
    <div>
        Updates stay with your account so you can revisit them at any time. Open History to review notifications
        you have already marked as read.
    </div>
</div>
