<?php
$queryBase = array_filter([
    'q' => $filters['q'] !== '' ? $filters['q'] : null,
    'status' => $filters['status'] !== '' ? $filters['status'] : null,
], static fn ($v) => $v !== null);
$tabs = [
    '' => 'All',
    'pending' => 'Pending',
    'approved' => 'Approved',
    'completed' => 'Completed',
    'rejected' => 'Rejected',
    'cancelled' => 'Cancelled',
];
?>
<div class="page-header reveal">
    <div>
        <div class="page-header__eyebrow"><?= icon('clipboard', 'icon icon--sm') ?> <?= $isAdmin ? 'Request administration' : 'My borrowing' ?></div>
        <h1 class="page-title"><?= $isAdmin ? 'Borrowing Requests' : 'My Borrowing Requests' ?></h1>
        <p class="page-subtitle">
            <?= $isAdmin
                ? 'Review the full request history. Only pending requests can be approved or rejected.'
                : 'Track pending, approved, rejected, and completed requests. Pending does not reserve stock.' ?>
        </p>
    </div>
    <div class="page-actions">
        <?php if (!$isAdmin): ?>
            <a class="btn btn--primary" href="<?= e(url('borrow/create')) ?>" data-testid="new-request-button"><?= icon('plus') ?> New request</a>
        <?php else: ?>
            <a class="btn btn--ghost" href="<?= e(url('approvals')) ?>"><?= icon('clock') ?> Pending queue</a>
            <a class="btn btn--ghost" href="<?= e(url('reports', ['type' => 'borrowing'])) ?>"><?= icon('chart') ?> Borrowing report</a>
        <?php endif; ?>
    </div>
</div>

<nav class="tabs reveal" aria-label="Request status filters">
    <?php foreach ($tabs as $value => $label): ?>
        <a class="tab <?= (string) $filters['status'] === (string) $value ? 'is-active' : '' ?>"
           href="<?= e(url('borrow', array_filter(['status' => $value ?: null, 'q' => $filters['q'] ?: null]))) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<form class="toolbar reveal" method="get" action="<?= e(url('borrow')) ?>" data-testid="request-filters">
    <?php if ($filters['status'] !== ''): ?>
        <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <?php endif; ?>
    <div class="field field--grow">
        <label class="field__label" for="request-search">Search requests</label>
        <div class="input-icon">
            <?= icon('search') ?>
            <input class="input" type="search" id="request-search" name="q" value="<?= e($filters['q']) ?>"
                   placeholder="Request code, borrower, purpose, or equipment…" data-testid="request-search">
        </div>
    </div>
    <div class="toolbar__actions">
        <button type="submit" class="btn btn--secondary"><?= icon('search') ?> Search</button>
        <?php if ($queryBase !== []): ?><a class="btn btn--ghost" href="<?= e(url('borrow')) ?>">Reset</a><?php endif; ?>
    </div>
</form>

<section class="card reveal">
    <div class="table-wrap">
        <table class="table table-responsive-cards" data-testid="requests-table">
            <thead>
                <tr>
                    <th>Request</th>
                    <?php if ($isAdmin): ?><th>Borrower</th><?php endif; ?>
                    <th>Items</th>
                    <th>Expected return</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($paginator->items === []): ?>
                    <tr><td colspan="<?= $isAdmin ? 7 : 6 ?>">
                        <div class="empty-state" style="padding:32px;">
                            <div class="empty-state__icon"><?= icon('clipboard', 'icon icon--xl') ?></div>
                            <h3>No requests found</h3>
                            <p><?= $isAdmin ? 'No borrowing requests match the current filters.' : 'You have not submitted any requests matching this filter.' ?></p>
                            <?php if (!$isAdmin): ?><a class="btn btn--primary" href="<?= e(url('borrow/create')) ?>">Submit a request</a><?php endif; ?>
                        </div>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($paginator->items as $req): ?>
                        <tr data-search="<?= e($req['request_code'] . ' ' . $req['first_name'] . ' ' . $req['last_name'] . ' ' . ($req['items_summary'] ?? '') . ' ' . $req['purpose']) ?>">
                            <td data-label="Request">
                                <a class="cell-main" href="<?= e(url('borrow/' . (int) $req['id'])) ?>"><?= e($req['request_code']) ?></a>
                                <span class="cell-sub"><?= e($req['total_units']) ?> unit(s)</span>
                            </td>
                            <?php if ($isAdmin): ?>
                                <td data-label="Borrower">
                                    <span class="cell-main"><?= e($req['first_name'] . ' ' . $req['last_name']) ?></span>
                                    <span class="cell-sub"><?= e(role_short_label($req['role'])) ?> · <?= e($req['school_id']) ?></span>
                                </td>
                            <?php endif; ?>
                            <td data-label="Items"><span style="font-size:12px;color:var(--ink-soft);"><?= e(truncate($req['items_summary'] ?: '—', 52)) ?></span></td>
                            <td data-label="Expected return" class="nowrap"><?= e(format_date($req['expected_return_date'])) ?></td>
                            <td data-label="Submitted" class="nowrap"><?= e(time_ago($req['created_at'])) ?></td>
                            <td data-label="Status"><?= status_badge($req['status']) ?></td>
                            <td data-label="Action">
                                <div class="cell-actions">
                                    <a class="btn btn--sm btn--ghost" href="<?= e(url('borrow/' . (int) $req['id'])) ?>">View</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?= pagination($paginator, 'borrow', $queryBase) ?>
