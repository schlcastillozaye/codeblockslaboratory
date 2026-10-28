<?php
$typeLabels = [
    'inventory' => 'Inventory',
    'borrowing' => 'Borrowing',
    'returns' => 'Returns',
    'pending' => 'Pending requests',
    'overdue' => 'Overdue',
    'condition' => 'Condition register',
    'borrowers' => 'Borrowers',
];
$filterQuery = array_filter([
    'type' => $type,
    'q' => $filters['q'] !== '' ? $filters['q'] : null,
    'status' => $filters['status'] !== '' ? $filters['status'] : null,
    'condition' => $filters['condition'] !== '' ? $filters['condition'] : null,
    'category_id' => $filters['category_id'] ?: null,
    'user_id' => $filters['user_id'] ?: null,
    'from' => $filters['from'] !== '' ? $filters['from'] : null,
    'to' => $filters['to'] !== '' ? $filters['to'] : null,
], static fn ($v) => $v !== null);
$hasFilters = count($filterQuery) > 1;
$canExport = in_array($type, ['inventory', 'borrowing', 'returns', 'condition', 'pending', 'overdue', 'borrowers'], true);
$exportQuery = $filterQuery;
$exportQuery['type'] = $type;
$reportTitle = $typeLabels[$type] ?? 'Inventory';
$generatedAt = date('M j, Y g:i A');
?>
<div class="page-header reveal">
    <div>
        <div class="page-header__eyebrow"><?= icon('chart', 'icon icon--sm') ?> Live operations reporting</div>
        <h1 class="page-title">Reports</h1>
        <p class="page-subtitle">
            Every figure and row reflects the current laboratory records. Exports use the same live data shown
            on this page.
        </p>
    </div>
    <div class="page-actions">
        <button type="button" class="btn btn--ghost" data-print data-testid="print-report"><?= icon('print') ?> Print</button>
        <?php if ($canExport): ?>
            <a class="btn btn--primary" href="<?= e(url('reports/export', $exportQuery)) ?>" data-testid="export-report"><?= icon('download') ?> Export CSV</a>
        <?php endif; ?>
    </div>
</div>

<nav class="tabs reveal" aria-label="Report type">
    <?php foreach ($typeLabels as $value => $label): ?>
        <a class="tab <?= $type === $value ? 'is-active' : '' ?>" href="<?= e(url('reports', ['type' => $value])) ?>"
           data-testid="report-tab-<?= e($value) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<div class="metric-strip reveal">
    <div class="metric-strip__item">
        <div class="metric-strip__label">Equipment records</div>
        <div class="metric-strip__value" data-counter="<?= (int) $summary['equipment_records'] ?>">0</div>
    </div>
    <div class="metric-strip__item">
        <div class="metric-strip__label">Total units</div>
        <div class="metric-strip__value" data-counter="<?= (int) $summary['total_units'] ?>">0</div>
    </div>
    <div class="metric-strip__item">
        <div class="metric-strip__label">Available</div>
        <div class="metric-strip__value" data-counter="<?= (int) $summary['available_units'] ?>">0</div>
    </div>
    <div class="metric-strip__item">
        <div class="metric-strip__label">Borrowed</div>
        <div class="metric-strip__value" data-counter="<?= (int) $summary['borrowed_units'] ?>">0</div>
    </div>
    <div class="metric-strip__item">
        <div class="metric-strip__label">Pending requests</div>
        <div class="metric-strip__value" data-counter="<?= (int) $summary['pending_requests'] ?>">0</div>
    </div>
    <div class="metric-strip__item">
        <div class="metric-strip__label">Overdue checkouts</div>
        <div class="metric-strip__value" data-counter="<?= (int) $summary['overdue_transactions'] ?>">0</div>
    </div>
</div>

<?php if (in_array($type, ['inventory', 'borrowing', 'returns'], true)): ?>
    <form class="toolbar reveal" method="get" action="<?= e(url('reports')) ?>" data-testid="report-filters">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <div class="field field--grow">
            <label class="field__label" for="report-search">Search</label>
            <div class="input-icon">
                <?= icon('search') ?>
                <input class="input" type="search" id="report-search" name="q" value="<?= e($filters['q']) ?>"
                       placeholder="Equipment, code, or category…" data-testid="report-search">
            </div>
        </div>
        <?php if ($type === 'inventory' || $type === 'borrowing'): ?>
            <div class="field field--sm">
                <label class="field__label" for="report-category">Category</label>
                <select class="select" id="report-category" name="category_id">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= selected($filters['category_id'], $category['id']) ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <?php if ($type === 'inventory'): ?>
            <div class="field field--sm">
                <label class="field__label" for="report-condition">Condition</label>
                <select class="select" id="report-condition" name="condition">
                    <option value="">Any condition</option>
                    <option value="good" <?= selected($filters['condition'], 'good') ?>>Good / Working</option>
                    <option value="needs_maintenance" <?= selected($filters['condition'], 'needs_maintenance') ?>>Needs Maintenance</option>
                    <option value="damaged" <?= selected($filters['condition'], 'damaged') ?>>Damaged</option>
                    <option value="lost" <?= selected($filters['condition'], 'lost') ?>>Lost</option>
                </select>
            </div>
            <div class="field field--sm">
                <label class="field__label" for="report-status">Record status</label>
                <select class="select" id="report-status" name="status">
                    <option value="">All records</option>
                    <option value="active" <?= selected($filters['status'], 'active') ?>>Active</option>
                    <option value="archived" <?= selected($filters['status'], 'archived') ?>>Archived</option>
                </select>
            </div>
        <?php elseif ($type === 'borrowing'): ?>
            <div class="field field--sm">
                <label class="field__label" for="report-status">Request status</label>
                <select class="select" id="report-status" name="status">
                    <option value="">Any status</option>
                    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'completed' => 'Completed', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= selected($filters['status'], $value) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field--sm">
                <label class="field__label" for="report-user">Borrower</label>
                <select class="select" id="report-user" name="user_id">
                    <option value="">All borrowers</option>
                    <?php foreach ($users as $account): ?>
                        <option value="<?= (int) $account['id'] ?>" <?= selected($filters['user_id'], $account['id']) ?>><?= e($account['last_name'] . ', ' . $account['first_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php elseif ($type === 'returns'): ?>
            <div class="field field--sm">
                <label class="field__label" for="report-condition">Condition</label>
                <select class="select" id="report-condition" name="condition">
                    <option value="">Any condition</option>
                    <option value="good" <?= selected($filters['condition'], 'good') ?>>Good / Working</option>
                    <option value="damaged" <?= selected($filters['condition'], 'damaged') ?>>Damaged</option>
                    <option value="needs_maintenance" <?= selected($filters['condition'], 'needs_maintenance') ?>>Needs Maintenance</option>
                    <option value="incomplete" <?= selected($filters['condition'], 'incomplete') ?>>Incomplete</option>
                </select>
            </div>
            <div class="field field--sm">
                <label class="field__label" for="report-user">Borrower</label>
                <select class="select" id="report-user" name="user_id">
                    <option value="">All borrowers</option>
                    <?php foreach ($users as $account): ?>
                        <option value="<?= (int) $account['id'] ?>" <?= selected($filters['user_id'], $account['id']) ?>><?= e($account['last_name'] . ', ' . $account['first_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="field field--sm">
            <label class="field__label" for="report-from">From</label>
            <input class="input" type="date" id="report-from" name="from" value="<?= e($filters['from']) ?>">
        </div>
        <div class="field field--sm">
            <label class="field__label" for="report-to">To</label>
            <input class="input" type="date" id="report-to" name="to" value="<?= e($filters['to']) ?>">
        </div>
        <div class="toolbar__actions">
            <button type="submit" class="btn btn--secondary"><?= icon('filter') ?> Apply</button>
            <?php if ($hasFilters): ?><a class="btn btn--ghost" href="<?= e(url('reports', ['type' => $type])) ?>">Reset</a><?php endif; ?>
        </div>
    </form>
<?php endif; ?>

<div class="report-header">
    <img src="<?= e(asset('assets/images/code-blocks-logo.svg')) ?>" alt="Code Blocks Laboratory Equipment Borrowing System">
    <div>
        <div class="report-title"><?= e($reportTitle) ?> report</div>
        <div class="report-meta">Code Blocks Laboratory Equipment Inventory and Borrowing Management System</div>
    </div>
    <div class="report-header__meta">
        Generated <?= e($generatedAt) ?><br>
        By <?= e(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) ?><br>
Live inventory records
    </div>
</div>

<section class="card reveal" data-testid="report-<?= e($type) ?>">
    <div class="card__head card__head--line">
        <div>
            <h2 class="card__title"><?= e($reportTitle) ?> report</h2>
            <p class="card__subtitle">
                <?php
                $rowCount = match ($type) {
                    'inventory' => count($inventory ?? []),
                    'borrowing' => count($borrowing ?? []),
                    'returns' => count($returns ?? []),
                    'pending' => count($pending ?? []),
                    'overdue' => count($overdue ?? []),
                    'condition' => count($conditionRegister ?? []),
                    'borrowers' => count($borrowers ?? []),
                    default => 0,
                };
                ?>
                <?= $rowCount ?> row(s) · generated <?= e($generatedAt) ?>
                <?php if ($hasFilters): ?> · filtered<?php endif; ?>
            </p>
        </div>
        <div class="card__tools no-print">
            <button type="button" class="btn btn--sm btn--ghost" data-print><?= icon('print', 'icon icon--xs') ?> Print</button>
            <?php if ($canExport): ?>
                <a class="btn btn--sm btn--soft" href="<?= e(url('reports/export', $exportQuery)) ?>"><?= icon('download', 'icon icon--xs') ?> CSV</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($type === 'inventory'): ?>
        <div class="table-wrap">
            <table class="table table-responsive-cards" data-testid="inventory-report-table">
                <thead>
                    <tr><th>Equipment</th><th>Category</th><th>Total</th><th>Available</th><th>Borrowed</th><th>Damaged</th><th>Repair</th><th>Lost</th><th>Condition</th><th>Location</th></tr>
                </thead>
                <tbody>
                    <?php if (($inventory ?? []) === []): ?>
                        <tr><td colspan="10"><div class="table-empty">No equipment matches these filters.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($inventory as $item): ?>
                            <tr>
                                <td data-label="Equipment">
                                    <span class="cell-main"><?= e($item['name']) ?></span>
                                    <span class="cell-sub"><?= e($item['equipment_code']) ?><?= $item['record_status'] === 'archived' ? ' · archived' : '' ?></span>
                                </td>
                                <td data-label="Category"><?= e($item['category_name']) ?></td>
                                <td data-label="Total" class="num"><?= (int) $item['total_quantity'] ?></td>
                                <td data-label="Available" class="num"><?= (int) $item['available_quantity'] ?></td>
                                <td data-label="Borrowed" class="num"><?= (int) $item['borrowed_quantity'] ?></td>
                                <td data-label="Damaged" class="num"><?= (int) $item['damaged_quantity'] ?></td>
                                <td data-label="Repair" class="num"><?= (int) $item['under_repair_quantity'] ?></td>
                                <td data-label="Lost" class="num"><?= (int) $item['lost_quantity'] ?></td>
                                <td data-label="Condition"><?= status_badge($item['condition_status']) ?></td>
                                <td data-label="Location"><span class="cell-sub"><?= e($item['storage_location']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($type === 'borrowing'): ?>
        <div class="table-wrap">
            <table class="table table-responsive-cards" data-testid="borrowing-report-table">
                <thead>
                    <tr><th>Request</th><th>Borrower</th><th>Equipment</th><th>Qty requested</th><th>Qty approved</th><th>Transaction</th><th>Borrowed</th><th>Due</th><th>Returned</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php if (($borrowing ?? []) === []): ?>
                        <tr><td colspan="10"><div class="table-empty">No borrowing request matches these filters.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($borrowing as $row): ?>
                            <tr>
                                <td data-label="Request">
                                    <span class="cell-main"><?= e($row['request_code']) ?></span>
                                    <span class="cell-sub"><?= e(format_date($row['created_at'])) ?></span>
                                </td>
                                <td data-label="Borrower">
                                    <span class="cell-main"><?= e($row['first_name'] . ' ' . $row['last_name']) ?></span>
                                    <span class="cell-sub"><?= e(role_short_label($row['role'])) ?> · <?= e($row['school_id']) ?></span>
                                </td>
                                <td data-label="Equipment">
                                    <span class="cell-main"><?= e($row['equipment_name']) ?></span>
                                    <span class="cell-sub"><?= e($row['equipment_code']) ?> · <?= e($row['category_name']) ?></span>
                                </td>
                                <td data-label="Qty requested" class="num"><?= (int) $row['quantity_requested'] ?></td>
                                <td data-label="Qty approved" class="num"><?= $row['quantity_approved'] !== null ? (int) $row['quantity_approved'] : '—' ?></td>
                                <td data-label="Transaction">
                                    <?php if (!empty($row['transaction_code'])): ?>
                                        <span class="cell-main" style="font-family:var(--mono);font-size:11px;"><?= e($row['transaction_code']) ?></span>
                                        <span class="cell-sub"><?= e($row['tx_status'] ? ucfirst($row['tx_status']) : '') ?></span>
                                    <?php else: ?>
                                        <span class="cell-sub">Not checked out</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Borrowed" class="nowrap"><?= e(format_date($row['borrowed_at'])) ?></td>
                                <td data-label="Due" class="nowrap"><?= e(format_date($row['due_at'])) ?></td>
                                <td data-label="Returned" class="num"><?= (int) $row['quantity_returned'] ?></td>
                                <td data-label="Status"><?= status_badge($row['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($type === 'returns'): ?>
        <div class="table-wrap">
            <table class="table table-responsive-cards" data-testid="returns-report-table">
                <thead>
                    <tr><th>Return</th><th>Date</th><th>Equipment</th><th>Qty</th><th>Condition</th><th>Borrower</th><th>Transaction</th><th>Processed by</th><th>Notes</th></tr>
                </thead>
                <tbody>
                    <?php if (($returns ?? []) === []): ?>
                        <tr><td colspan="9"><div class="table-empty">No return record matches these filters.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($returns as $row): ?>
                            <?php $processor = trim(($row['processor_first_name'] ?? '') . ' ' . ($row['processor_last_name'] ?? '')); ?>
                            <tr>
                                <td data-label="Return"><span class="cell-main"><?= e($row['return_code']) ?></span></td>
                                <td data-label="Date" class="nowrap"><?= e(format_date($row['return_date'])) ?></td>
                                <td data-label="Equipment">
                                    <span class="cell-main"><?= e($row['equipment_name']) ?></span>
                                    <span class="cell-sub"><?= e($row['equipment_code']) ?></span>
                                </td>
                                <td data-label="Qty" class="num"><?= (int) $row['quantity_returned'] ?></td>
                                <td data-label="Condition"><?= status_badge($row['condition']) ?></td>
                                <td data-label="Borrower">
                                    <span class="cell-main"><?= e($row['first_name'] . ' ' . $row['last_name']) ?></span>
                                    <span class="cell-sub"><?= e($row['school_id']) ?></span>
                                </td>
                                <td data-label="Transaction"><span class="cell-sub" style="font-family:var(--mono);font-size:11px;"><?= e($row['transaction_code']) ?></span></td>
                                <td data-label="Processed by"><span class="cell-sub"><?= e($processor !== '' ? $processor : '—') ?></span></td>
                                <td data-label="Notes"><span class="cell-sub"><?= e(truncate($row['notes'] ?: '—', 70)) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($type === 'pending'): ?>
        <div class="table-wrap">
            <table class="table table-responsive-cards" data-testid="pending-report-table">
                <thead><tr><th>Request</th><th>Borrower</th><th>Items</th><th>Expected return</th><th>Submitted</th><th>Purpose</th></tr></thead>
                <tbody>
                    <?php if (($pending ?? []) === []): ?>
                        <tr><td colspan="6"><div class="table-empty">No pending borrowing requests. The queue is clear.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($pending as $row): ?>
                            <tr>
                                <td data-label="Request">
                                    <a class="cell-main" href="<?= e(url('borrow/' . (int) $row['id'])) ?>"><?= e($row['request_code']) ?></a>
                                    <span class="cell-sub"><?= e(time_ago($row['created_at'])) ?></span>
                                </td>
                                <td data-label="Borrower">
                                    <span class="cell-main"><?= e($row['first_name'] . ' ' . $row['last_name']) ?></span>
                                    <span class="cell-sub"><?= e(role_short_label($row['role'])) ?> · <?= e($row['school_id']) ?></span>
                                </td>
                                <td data-label="Items"><span class="cell-sub"><?= e($row['items_summary'] ?: '—') ?></span></td>
                                <td data-label="Expected return" class="nowrap"><?= e(format_date($row['expected_return_date'])) ?></td>
                                <td data-label="Submitted" class="nowrap"><?= e(format_datetime($row['created_at'])) ?></td>
                                <td data-label="Purpose"><span class="cell-sub"><?= e(truncate($row['purpose'], 90)) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($type === 'overdue'): ?>
        <div class="table-wrap">
            <table class="table table-responsive-cards" data-testid="overdue-report-table">
                <thead><tr><th>Transaction</th><th>Borrower</th><th>Equipment</th><th>Qty</th><th>Outstanding</th><th>Due</th><th>Days overdue</th><th>Request</th></tr></thead>
                <tbody>
                    <?php if (($overdue ?? []) === []): ?>
                        <tr><td colspan="8"><div class="table-empty">No overdue checkout right now.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($overdue as $row): ?>
                            <tr class="row-overdue">
                                <td data-label="Transaction"><span class="cell-main" style="font-family:var(--mono);font-size:11px;"><?= e($row['transaction_code']) ?></span></td>
                                <td data-label="Borrower">
                                    <span class="cell-main"><?= e($row['first_name'] . ' ' . $row['last_name']) ?></span>
                                    <span class="cell-sub"><?= e($row['school_id']) ?></span>
                                </td>
                                <td data-label="Equipment">
                                    <span class="cell-main"><?= e($row['equipment_name']) ?></span>
                                    <span class="cell-sub"><?= e($row['equipment_code']) ?></span>
                                </td>
                                <td data-label="Qty" class="num"><?= (int) $row['quantity'] ?></td>
                                <td data-label="Outstanding" class="num"><?= (int) $row['quantity'] - (int) $row['quantity_returned'] ?></td>
                                <td data-label="Due" class="nowrap"><?= e(format_date($row['due_at'])) ?></td>
                                <td data-label="Days overdue"><span class="badge badge--danger"><?= (int) $row['days_overdue'] ?> day(s)</span></td>
                                <td data-label="Request"><a class="cell-sub" href="<?= e(url('borrow/' . (int) $row['request_id'])) ?>"><?= e($row['request_code']) ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($type === 'condition'): ?>
        <div class="table-wrap">
            <table class="table table-responsive-cards" data-testid="condition-register-table">
                <thead><tr><th>Equipment</th><th>Category</th><th>Total</th><th>Available</th><th>Borrowed</th><th>Damaged</th><th>Repair</th><th>Lost</th><th>Condition</th></tr></thead>
                <tbody>
                    <?php if (($conditionRegister ?? []) === []): ?>
                        <tr><td colspan="9"><div class="table-empty">No damaged, repair, or lost units. Inventory is fully healthy.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($conditionRegister as $item): ?>
                            <tr>
                                <td data-label="Equipment">
                                    <a class="cell-main" href="<?= e(url('equipment/' . (int) $item['id'])) ?>"><?= e($item['name']) ?></a>
                                    <span class="cell-sub"><?= e($item['equipment_code']) ?></span>
                                </td>
                                <td data-label="Category"><?= e($item['category_name']) ?></td>
                                <td data-label="Total" class="num"><?= (int) $item['total_quantity'] ?></td>
                                <td data-label="Available" class="num"><?= (int) $item['available_quantity'] ?></td>
                                <td data-label="Borrowed" class="num"><?= (int) $item['borrowed_quantity'] ?></td>
                                <td data-label="Damaged" class="num"><?= (int) $item['damaged_quantity'] ?></td>
                                <td data-label="Repair" class="num"><?= (int) $item['under_repair_quantity'] ?></td>
                                <td data-label="Lost" class="num"><?= (int) $item['lost_quantity'] ?></td>
                                <td data-label="Condition"><?= status_badge($item['condition_status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card__head card__head--line" style="margin-top:6px;">
            <div>
                <h2 class="card__title">Recent condition history</h2>
                <p class="card__subtitle">Last 100 recorded condition changes, newest first.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table table-responsive-cards" data-testid="condition-history-table">
                <thead><tr><th>Recorded</th><th>Equipment</th><th>Previous</th><th>New</th><th>Qty</th><th>Return</th><th>Recorded by</th><th>Notes</th></tr></thead>
                <tbody>
                    <?php if (($conditionHistory ?? []) === []): ?>
                        <tr><td colspan="8"><div class="table-empty">No condition history has been recorded yet.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($conditionHistory as $entry): ?>
                            <tr>
                                <td data-label="Recorded" class="nowrap"><span class="cell-sub"><?= e(format_datetime($entry['recorded_at'])) ?></span></td>
                                <td data-label="Equipment">
                                    <span class="cell-main"><?= e($entry['equipment_name']) ?></span>
                                    <span class="cell-sub"><?= e($entry['equipment_code']) ?></span>
                                </td>
                                <td data-label="Previous"><?= status_badge($entry['previous_condition']) ?></td>
                                <td data-label="New"><?= status_badge($entry['new_condition']) ?></td>
                                <td data-label="Qty" class="num"><?= (int) $entry['quantity_affected'] ?></td>
                                <td data-label="Return"><span class="cell-sub"><?= e($entry['return_code'] ?: 'Manual adjustment') ?></span></td>
                                <td data-label="Recorded by"><span class="cell-sub"><?= e(trim(($entry['first_name'] ?? '') . ' ' . ($entry['last_name'] ?? '')) ?: 'System') ?></span></td>
                                <td data-label="Notes"><span class="cell-sub"><?= e(truncate($entry['notes'] ?: '—', 70)) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($type === 'borrowers'): ?>
        <div class="table-wrap">
            <table class="table table-responsive-cards" data-testid="borrowers-report-table">
                <thead><tr><th>Borrower</th><th>Role</th><th>Requests</th><th>Outstanding units</th><th>Overdue checkouts</th><th>Completed returns</th></tr></thead>
                <tbody>
                    <?php if (($borrowers ?? []) === []): ?>
                        <tr><td colspan="6"><div class="table-empty">No borrower activity has been recorded yet.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($borrowers as $row): ?>
                            <tr>
                                <td data-label="Borrower">
                                    <span class="cell-main"><?= e($row['first_name'] . ' ' . $row['last_name']) ?></span>
                                    <span class="cell-sub"><?= e($row['school_id']) ?></span>
                                </td>
                                <td data-label="Role"><?= e(role_label($row['role'])) ?></td>
                                <td data-label="Requests" class="num"><?= (int) $row['requests'] ?></td>
                                <td data-label="Outstanding units" class="num"><?= (int) $row['outstanding_units'] ?></td>
                                <td data-label="Overdue checkouts" class="num"><?= (int) $row['overdue_count'] ?></td>
                                <td data-label="Completed returns" class="num"><?= (int) $row['completed_returns'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<div class="print-footer" style="display:none;">
    Code Blocks Laboratory Equipment Borrowing System · Laboratory Equipment Inventory and Borrowing Management System · Generated <?= e($generatedAt) ?> from live inventory records.
</div>
