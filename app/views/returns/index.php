<?php
$queryBase = array_filter([
    'q' => $filters['q'] !== '' ? $filters['q'] : null,
    'condition' => $filters['condition'] !== '' ? $filters['condition'] : null,
    'from' => $filters['from'] !== '' ? $filters['from'] : null,
    'to' => $filters['to'] !== '' ? $filters['to'] : null,
], static fn ($v) => $v !== null);
$conditions = [
    '' => 'Any condition',
    'good' => 'Good / Working',
    'damaged' => 'Damaged',
    'needs_maintenance' => 'Needs Maintenance',
    'incomplete' => 'Incomplete / Missing Parts',
];
?>
<div class="page-header reveal">
    <div>
        <div class="page-header__eyebrow"><?= icon('rotate-ccw', 'icon icon--sm') ?> Condition documentation</div>
        <h1 class="page-title"><?= $isAdmin ? 'Returns & condition reports' : 'My return history' ?></h1>
        <p class="page-subtitle">
            <?= $isAdmin
                ? 'Every return requires a condition. Damaged, incomplete, and maintenance units are moved out of available stock automatically.'
                : 'These are the returns processed for your borrowing transactions, including the condition recorded by laboratory personnel.' ?>
        </p>
    </div>
    <div class="page-actions">
        <?php if ($isAdmin): ?>
            <a class="btn btn--ghost" href="<?= e(url('transactions', ['status' => 'active'])) ?>"><?= icon('arrow-up-right') ?> Open checkouts</a>
            <a class="btn btn--ghost" href="<?= e(url('reports', ['type' => 'returns'])) ?>"><?= icon('chart') ?> Returns report</a>
        <?php endif; ?>
    </div>
</div>

<form class="toolbar reveal" method="get" action="<?= e(url('returns')) ?>" data-testid="returns-filters">
    <div class="field field--grow">
        <label class="field__label" for="return-search">Search returns</label>
        <div class="input-icon">
            <?= icon('search') ?>
            <input class="input" type="search" id="return-search" name="q" value="<?= e($filters['q']) ?>"
                   placeholder="Return code, transaction, equipment, or borrower…"
                   data-testid="returns-search">
        </div>
    </div>
    <div class="field field--sm">
        <label class="field__label" for="return-condition">Condition</label>
        <select class="select" id="return-condition" name="condition" data-testid="returns-condition-filter">
            <?php foreach ($conditions as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= selected($filters['condition'], $value) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field field--sm">
        <label class="field__label" for="return-from">From</label>
        <input class="input" type="date" id="return-from" name="from" value="<?= e($filters['from']) ?>" data-testid="returns-from">
    </div>
    <div class="field field--sm">
        <label class="field__label" for="return-to">To</label>
        <input class="input" type="date" id="return-to" name="to" value="<?= e($filters['to']) ?>" data-testid="returns-to">
    </div>
    <div class="toolbar__actions">
        <button type="submit" class="btn btn--secondary"><?= icon('filter') ?> Filter</button>
        <?php if ($queryBase !== []): ?><a class="btn btn--ghost" href="<?= e(url('returns')) ?>">Reset</a><?php endif; ?>
    </div>
</form>

<section class="card reveal">
    <div class="table-wrap">
        <table class="table table-responsive-cards" data-testid="returns-table">
            <thead>
                <tr>
                    <th>Return</th>
                    <th>Equipment</th>
                    <th>Qty</th>
                    <th>Condition</th>
                    <?php if ($isAdmin): ?><th>Borrower</th><?php endif; ?>
                    <th>Return date</th>
                    <th>Transaction</th>
                    <?php if ($isAdmin): ?><th>Processed by</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($paginator->items === []): ?>
                    <tr>
                        <td colspan="<?= $isAdmin ? 8 : 6 ?>">
                            <div class="empty-state" style="padding:32px;">
                                <div class="empty-state__icon"><?= icon('rotate-ccw', 'icon icon--xl') ?></div>
                                <h3>No returns found</h3>
                                <p><?= $isAdmin ? 'No return record matches the current filters.' : 'You have no processed returns matching these filters.' ?></p>
                                <?php if (!$isAdmin): ?><a class="btn btn--primary" href="<?= e(url('transactions')) ?>">View my borrowings</a><?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($paginator->items as $record): ?>
                        <?php
                        $processor = trim(($record['processor_first_name'] ?? '') . ' ' . ($record['processor_last_name'] ?? ''));
                        $searchText = $record['return_code'] . ' ' . $record['transaction_code'] . ' ' . $record['equipment_name'] . ' '
                            . $record['equipment_code'] . ' ' . $record['first_name'] . ' ' . $record['last_name'] . ' ' . $record['school_id'];
                        ?>
                        <tr data-search="<?= e($searchText) ?>">
                            <td data-label="Return">
                                <span class="cell-main"><?= e($record['return_code']) ?></span>
                                <span class="cell-sub"><?= e(time_ago($record['created_at'])) ?></span>
                            </td>
                            <td data-label="Equipment">
                                <a class="cell-main" href="<?= e(url('equipment/' . (int) $record['equipment_id'])) ?>"><?= e($record['equipment_name']) ?></a>
                                <span class="cell-sub"><?= e($record['equipment_code']) ?></span>
                            </td>
                            <td data-label="Qty" class="num"><?= (int) $record['quantity_returned'] ?></td>
                            <td data-label="Condition">
                                <?= status_badge($record['condition']) ?>
                                <?php if (!empty($record['notes'])): ?>
                                    <span class="cell-sub" style="display:block;margin-top:5px;max-width:240px;"><?= e(truncate($record['notes'], 80)) ?></span>
                                <?php endif; ?>
                            </td>
                            <?php if ($isAdmin): ?>
                                <td data-label="Borrower">
                                    <span class="cell-main"><?= e($record['first_name'] . ' ' . $record['last_name']) ?></span>
                                    <span class="cell-sub"><?= e($record['school_id']) ?></span>
                                </td>
                            <?php endif; ?>
                            <td data-label="Return date" class="nowrap"><?= e(format_date($record['return_date'])) ?></td>
                            <td data-label="Transaction">
                                <a class="cell-main" href="<?= e(url('borrow/' . (int) $record['transaction_id'])) ?>" style="font-family:var(--mono);font-size:11.5px;"><?= e($record['transaction_code']) ?></a>
                                <span class="cell-sub">Due <?= e(format_date($record['due_at'])) ?></span>
                            </td>
                            <?php if ($isAdmin): ?>
                                <td data-label="Processed by">
                                    <span class="cell-sub"><?= e($processor !== '' ? $processor : 'System') ?></span>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?= pagination($paginator, 'returns', $queryBase) ?>
