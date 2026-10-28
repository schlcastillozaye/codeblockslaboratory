<?php
$queryBase = array_filter([
    'q' => $filters['q'] !== '' ? $filters['q'] : null,
    'status' => $filters['status'] !== '' ? $filters['status'] : null,
    'overdue' => $filters['overdue'] !== '' ? $filters['overdue'] : null,
], static fn ($v) => $v !== null);
$tabs = [
    '' => 'All',
    'active' => 'Active',
    'overdue' => 'Overdue',
    'partially_returned' => 'Partially returned',
    'returned' => 'Returned',
    'lost' => 'Lost',
];
$overdueMode = $filters['overdue'] !== '';
?>
<div class="page-header reveal">
    <div>
        <div class="page-header__eyebrow"><?= icon('arrow-up-right', 'icon icon--sm') ?> <?= $isAdmin ? 'Checkout records' : 'My borrowed equipment' ?></div>
        <h1 class="page-title"><?= $isAdmin ? 'Borrowing transactions' : 'My borrowed equipment' ?></h1>
        <p class="page-subtitle">
            <?= $isAdmin
                ? 'Every approved checkout is listed here. Returns must record the equipment condition before stock is released.'
                : 'These are the equipment units currently checked out to you, plus your complete borrowing history.' ?>
        </p>
    </div>
    <div class="page-actions">
        <?php if ($isAdmin): ?>
            <a class="btn btn--ghost" href="<?= e(url('transactions', ['overdue' => 1])) ?>" data-testid="overdue-filter"><?= icon('alert-triangle') ?> Overdue only</a>
            <a class="btn btn--ghost" href="<?= e(url('returns')) ?>"><?= icon('rotate-ccw') ?> Returns</a>
        <?php endif; ?>
    </div>
</div>

<nav class="tabs reveal" aria-label="Transaction status filters">
    <?php foreach ($tabs as $value => $label): ?>
        <a class="tab <?= !$overdueMode && (string) $filters['status'] === (string) $value ? 'is-active' : '' ?>"
           href="<?= e(url('transactions', array_filter(['status' => $value ?: null, 'q' => $filters['q'] ?: null]))) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($overdueMode): ?>
    <div class="alert alert--warning reveal" data-persist role="status">
        <?= icon('alert-triangle') ?>
        <div class="alert__content">
            <div class="alert__title">Showing overdue checkouts only</div>
            <div>These units are past their due date and need follow-up. Open a transaction to process the return once the equipment is back.</div>
        </div>
    </div>
<?php endif; ?>

<form class="toolbar reveal" method="get" action="<?= e(url('transactions')) ?>" data-testid="transaction-filters">
    <?php if ($filters['status'] !== ''): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif; ?>
    <?php if ($overdueMode): ?><input type="hidden" name="overdue" value="1"><?php endif; ?>
    <div class="field field--grow">
        <label class="field__label" for="transaction-search">Search transactions</label>
        <div class="input-icon">
            <?= icon('search') ?>
            <input class="input" type="search" id="transaction-search" name="q" value="<?= e($filters['q']) ?>"
                   placeholder="Transaction code, request code, equipment, or borrower…"
                   data-testid="transaction-search">
        </div>
    </div>
    <div class="field field--sm">
        <label class="field__label" for="transaction-status">Status</label>
        <select class="select" id="transaction-status" name="status">
            <option value="">Any status</option>
            <?php foreach ($tabs as $value => $label): ?>
                <?php if ($value === '' || $value === 'overdue') { continue; } ?>
                <option value="<?= e($value) ?>" <?= selected($filters['status'], $value) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar__actions">
        <button type="submit" class="btn btn--secondary"><?= icon('filter') ?> Apply</button>
        <?php if ($queryBase !== []): ?><a class="btn btn--ghost" href="<?= e(url('transactions')) ?>">Reset</a><?php endif; ?>
    </div>
</form>

<section class="card reveal">
    <div class="table-wrap">
        <table class="table table-responsive-cards" data-testid="transactions-table">
            <thead>
                <tr>
                    <th>Transaction</th>
                    <?php if ($isAdmin): ?><th>Borrower</th><?php endif; ?>
                    <th>Equipment</th>
                    <th>Qty</th>
                    <th>Borrowed</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($paginator->items === []): ?>
                    <tr>
                        <td colspan="<?= $isAdmin ? 8 : 7 ?>">
                            <div class="empty-state" style="padding:32px;">
                                <div class="empty-state__icon"><?= icon('arrow-up-right', 'icon icon--xl') ?></div>
                                <h3>No transactions found</h3>
                                <p><?= $isAdmin ? 'No checkout matches the current filters.' : 'You have no borrowed equipment matching these filters.' ?></p>
                                <?php if (!$isAdmin): ?><a class="btn btn--primary" href="<?= e(url('equipment')) ?>">Browse equipment</a><?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($paginator->items as $tx): ?>
                        <?php
                        $isOpen = in_array($tx['status'], ['active', 'overdue', 'partially_returned'], true);
                        $effectiveStatus = ((int) ($tx['is_overdue'] ?? 0) === 1 && $isOpen) ? 'overdue' : $tx['status'];
                        $daysLeft = days_until($tx['due_at']);
                        $outstanding = (int) ($tx['outstanding_quantity'] ?? ((int) $tx['quantity'] - (int) $tx['quantity_returned']));
                        ?>
                        <tr class="<?= $effectiveStatus === 'overdue' ? 'row-overdue' : '' ?>"
                            data-search="<?= e($tx['transaction_code'] . ' ' . $tx['request_code'] . ' ' . $tx['equipment_name'] . ' ' . $tx['equipment_code'] . ' ' . $tx['first_name'] . ' ' . $tx['last_name'] . ' ' . $tx['school_id']) ?>">
                            <td data-label="Transaction">
                                <a class="cell-main" href="<?= e(url('borrow/' . (int) $tx['request_id'])) ?>"><?= e($tx['transaction_code']) ?></a>
                                <span class="cell-sub"><?= e($tx['request_code']) ?></span>
                            </td>
                            <?php if ($isAdmin): ?>
                                <td data-label="Borrower">
                                    <div class="cell-person">
                                        <span class="avatar avatar--sm"><?= e(initials($tx['first_name'], $tx['last_name'])) ?></span>
                                        <span>
                                            <span class="cell-main"><?= e($tx['first_name'] . ' ' . $tx['last_name']) ?></span>
                                            <span class="cell-sub"><?= e($tx['school_id']) ?></span>
                                        </span>
                                    </div>
                                </td>
                            <?php endif; ?>
                            <td data-label="Equipment">
                                <a class="cell-main" href="<?= e(url('equipment/' . (int) $tx['equipment_id'])) ?>"><?= e($tx['equipment_name']) ?></a>
                                <span class="cell-sub"><?= e($tx['equipment_code']) ?> · <?= e($tx['category_name']) ?></span>
                            </td>
                            <td data-label="Qty" class="num">
                                <span class="cell-main"><?= (int) $tx['quantity'] ?></span>
                                <span class="cell-sub"><?= $outstanding ?> out · <?= (int) $tx['quantity_returned'] ?> returned</span>
                            </td>
                            <td data-label="Borrowed" class="nowrap"><?= e(format_date($tx['borrowed_at'])) ?></td>
                            <td data-label="Due" class="nowrap">
                                <span class="cell-main"><?= e(format_date($tx['due_at'])) ?></span>
                                <?php if ($isOpen && $daysLeft !== null): ?>
                                    <span class="cell-sub" style="color:<?= $daysLeft < 0 ? 'var(--red)' : ($daysLeft <= 3 ? 'var(--amber)' : 'var(--muted)') ?>;">
                                        <?= $daysLeft < 0
                                            ? abs($daysLeft) . ' day(s) overdue'
                                            : ($daysLeft === 0 ? 'Due today' : ($daysLeft . ' day(s) left')) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status"><?= status_badge($effectiveStatus) ?></td>
                            <td data-label="Action">
                                <div class="cell-actions">
                                    <a class="btn btn--sm btn--ghost" href="<?= e(url('borrow/' . (int) $tx['request_id'])) ?>">Request</a>
                                    <?php if ($isAdmin && $isOpen): ?>
                                        <a class="btn btn--sm btn--primary" href="<?= e(url('returns/create', ['transaction' => (int) $tx['id']])) ?>"
                                           data-testid="return-transaction-<?= (int) $tx['id'] ?>"><?= icon('rotate-ccw', 'icon icon--xs') ?> Return</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?= pagination($paginator, 'transactions', $queryBase) ?>

<?php if (!$isAdmin): ?>
    <div class="callout reveal" style="margin-top:18px;">
        <?= icon('info', 'icon icon--sm') ?>
        <div>Returns are processed in person by laboratory personnel so the equipment condition can be inspected and recorded.</div>
    </div>
<?php endif; ?>
