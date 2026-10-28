<?php
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$statusColors = [
    'available' => '#0FA3A3',
    'borrowed' => '#2563EB',
    'under_repair' => '#FDB022',
    'damaged' => '#F97066',
    'lost' => '#98A2B3',
    'archived' => '#CBD5E1',
    'unavailable' => '#94A3B8',
];
$statusLabels = [
    'available' => 'Available',
    'borrowed' => 'Fully borrowed',
    'under_repair' => 'Under repair',
    'damaged' => 'Damaged',
    'lost' => 'Lost',
    'archived' => 'Archived',
    'unavailable' => 'Unavailable',
];
$distTotal = 0;
foreach ($distribution as $row) { $distTotal += (int) $row['total']; }
$stops = [];
$acc = 0.0;
foreach ($distribution as $row) {
    $pct = $distTotal > 0 ? ((int) $row['total'] / $distTotal) * 100 : 0;
    $start = $acc;
    $acc += $pct;
    $stops[] = ($statusColors[$row['status']] ?? '#94A3B8') . ' ' . round($start, 2) . '% ' . round($acc, 2) . '%';
}
$gradient = $stops !== [] ? implode(', ', $stops) : '#E2E8F0 0 100%';
$maxTrend = 1;
foreach ($trend as $t) { $maxTrend = max($maxTrend, (int) $t['total']); }
$maxCategory = 1;
foreach ($categories as $c) { $maxCategory = max($maxCategory, (int) $c['units']); }
$lowStockCount = count($low_stock);
?>
<div class="page-header reveal">
    <div>
        <div class="page-header__eyebrow"><?= icon('dashboard', 'icon icon--sm') ?> Laboratory command center</div>
        <h1 class="page-title"><?= e($greeting) ?>, <?= e($currentUser['first_name']) ?>.</h1>
        <p class="page-subtitle">
            Here is the current state of the laboratory for <?= e(date('l, F j, Y')) ?>.
            Every figure reflects the latest inventory records.
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn--ghost" href="<?= e(url('reports')) ?>" data-testid="dashboard-reports"><?= icon('chart') ?> Reports</a>
        <a class="btn btn--ghost" href="<?= e(url('approvals')) ?>" data-testid="dashboard-approvals">
            <?= icon('clipboard') ?> Approvals
            <?php if ($pending > 0): ?><span class="badge badge--warning"><?= $pending ?></span><?php endif; ?>
        </a>
        <a class="btn btn--primary" href="<?= e(url('equipment/create')) ?>" data-testid="dashboard-add-equipment"><?= icon('plus') ?> Add equipment</a>
    </div>
</div>

<div class="stats-grid">
    <article class="stat-card reveal">
        <div class="stat-card__top">
            <span class="stat-card__icon stat-card__icon--navy"><?= icon('boxes') ?></span>
            <span class="badge badge--muted"><?= (int) ($equipment['records'] ?? 0) ?> records</span>
        </div>
        <div class="stat-card__label">Total units</div>
        <div class="stat-card__value" data-counter="<?= (int) ($equipment['total_units'] ?? 0) ?>">0</div>
        <div class="stat-card__meta"><?= (int) ($equipment['active_records'] ?? 0) ?> active · <?= (int) ($equipment['archived_records'] ?? 0) ?> archived</div>
    </article>

    <article class="stat-card tone-teal reveal">
        <div class="stat-card__top">
            <span class="stat-card__icon stat-card__icon--teal"><?= icon('check-circle') ?></span>
        </div>
        <div class="stat-card__label">Available now</div>
        <div class="stat-card__value" data-counter="<?= (int) ($equipment['available_units'] ?? 0) ?>">0</div>
        <div class="stat-card__meta">Ready for checkout</div>
    </article>

    <article class="stat-card reveal">
        <div class="stat-card__top">
            <span class="stat-card__icon"><?= icon('arrow-up-right') ?></span>
        </div>
        <div class="stat-card__label">Borrowed</div>
        <div class="stat-card__value" data-counter="<?= (int) ($equipment['borrowed_units'] ?? 0) ?>">0</div>
        <div class="stat-card__meta"><?= (int) ($transactions['open_total'] ?? 0) ?> open transactions</div>
    </article>

    <article class="stat-card tone-amber reveal">
        <div class="stat-card__top">
            <span class="stat-card__icon stat-card__icon--amber"><?= icon('clock') ?></span>
        </div>
        <div class="stat-card__label">Pending requests</div>
        <div class="stat-card__value" data-counter="<?= (int) $pending ?>">0</div>
        <div class="stat-card__meta"><a href="<?= e(url('approvals')) ?>">Review queue →</a></div>
    </article>

    <article class="stat-card tone-red reveal">
        <div class="stat-card__top">
            <span class="stat-card__icon stat-card__icon--red"><?= icon('alert-triangle') ?></span>
        </div>
        <div class="stat-card__label">Overdue</div>
        <div class="stat-card__value" data-counter="<?= (int) $overdue ?>">0</div>
        <div class="stat-card__meta"><a href="<?= e(url('transactions', ['overdue' => 1])) ?>">Follow up →</a></div>
    </article>

    <article class="stat-card reveal">
        <div class="stat-card__top">
            <span class="stat-card__icon stat-card__icon--amber"><?= icon('tool') ?></span>
        </div>
        <div class="stat-card__label">Damage / repair</div>
        <div class="stat-card__value" data-counter="<?= (int) ($equipment['damaged_units'] ?? 0) + (int) ($equipment['repair_units'] ?? 0) ?>">0</div>
        <div class="stat-card__meta"><?= (int) ($equipment['damaged_units'] ?? 0) ?> damaged · <?= (int) ($equipment['repair_units'] ?? 0) ?> in repair</div>
    </article>
</div>

<div class="grid grid--main">
    <div>
        <section class="card reveal">
            <div class="card__head card__head--line">
                <div>
                    <h2 class="card__title">Requests waiting for review</h2>
                    <p class="card__subtitle">Pending requests do not reserve stock until approved.</p>
                </div>
                <div class="card__tools">
                    <a class="btn btn--sm btn--ghost" href="<?= e(url('approvals')) ?>">View queue <?= icon('arrow-right', 'icon icon--xs') ?></a>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table table-responsive-cards" data-testid="pending-requests-table">
                    <thead>
                        <tr>
                            <th>Request</th>
                            <th>Borrower</th>
                            <th>Items</th>
                            <th>Expected return</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pending_requests->items === []): ?>
                            <tr><td colspan="5"><div class="table-empty">No pending requests. The queue is clear.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($pending_requests->items as $req): ?>
                                <tr data-search="<?= e($req['request_code'] . ' ' . $req['first_name'] . ' ' . $req['last_name']) ?>">
                                    <td data-label="Request">
                                        <span class="cell-main"><?= e($req['request_code']) ?></span>
                                        <span class="cell-sub"><?= e(time_ago($req['created_at'])) ?></span>
                                    </td>
                                    <td data-label="Borrower">
                                        <span class="cell-main"><?= e($req['first_name'] . ' ' . $req['last_name']) ?></span>
                                        <span class="cell-sub"><?= e(role_short_label($req['role'])) ?> · <?= e($req['school_id']) ?></span>
                                    </td>
                                    <td data-label="Items"><span class="cell-sub" style="font-size:12px;color:var(--ink-soft);"><?= e($req['items_summary'] ?: '—') ?></span></td>
                                    <td data-label="Expected return" class="nowrap"><?= e(format_date($req['expected_return_date'])) ?></td>
                                    <td data-label="Action">
                                        <div class="cell-actions">
                                            <a class="btn btn--sm btn--soft" href="<?= e(url('borrow/' . (int) $req['id'])) ?>">Review</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid grid--2" style="margin-top:18px;">
            <section class="card reveal">
                <div class="card__head">
                    <div>
                        <h2 class="card__title">Borrowing trend</h2>
                        <p class="card__subtitle">Requests submitted in the last 6 months</p>
                    </div>
                </div>
                <div class="card__body">
                    <div class="bar-chart" role="img" aria-label="Bar chart of monthly borrowing requests">
                        <?php foreach ($trend as $t): ?>
                            <?php $height = max(4, ((int) $t['total'] / $maxTrend) * 100); ?>
                            <div class="bar">
                                <span class="bar__value"><?= (int) $t['total'] ?></span>
                                <div class="bar__track"><div class="bar__fill" style="height: <?= round($height, 1) ?>%"></div></div>
                                <span class="bar__label"><?= e($t['label']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="card reveal">
                <div class="card__head">
                    <div>
                        <h2 class="card__title">Most borrowed equipment</h2>
                        <p class="card__subtitle">All-time checkout quantities</p>
                    </div>
                </div>
                <div class="card__body">
                    <?php if ($popular === []): ?>
                        <div class="empty-state" style="padding:24px;">No borrowing history yet.</div>
                    <?php else: ?>
                        <div class="hbar">
                            <?php foreach ($popular as $p): ?>
                                <div class="hbar__row">
                                    <span title="<?= e($p['name']) ?>"><?= e(truncate($p['name'], 20)) ?></span>
                                    <span class="hbar__track"><span class="hbar__fill" style="width: <?= round(((int) $p['borrowed_units'] / max(1, (int) $popular[0]['borrowed_units'])) * 100) ?>%"></span></span>
                                    <span class="hbar__value"><?= (int) $p['borrowed_units'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <section class="card reveal" style="margin-top:18px;">
            <div class="card__head card__head--line">
                <div>
                    <h2 class="card__title">Recent activity</h2>
                    <p class="card__subtitle">Latest entries from the audit trail</p>
                </div>
                <div class="card__tools"><a class="btn btn--sm btn--ghost" href="<?= e(url('audit')) ?>">Full audit trail</a></div>
            </div>
            <div class="card__body" style="padding-top:16px;">
                <div class="timeline">
                    <?php if ($recent_audit === []): ?>
                        <p class="field__hint">No audit entries yet.</p>
                    <?php else: ?>
                        <?php foreach ($recent_audit as $log): ?>
                            <div class="timeline__item">
                                <span class="timeline__dot <?= str_contains($log['action'], 'failed') || str_contains($log['action'], 'rejected') ? 'timeline__dot--red' : (str_contains($log['action'], 'return') ? 'timeline__dot--teal' : '') ?>"></span>
                                <div class="timeline__title"><?= e($log['description']) ?></div>
                                <div class="timeline__meta">
                                    <?= e(trim(($log['first_name'] ?? 'System') . ' ' . ($log['last_name'] ?? ''))) ?>
                                    · <?= e(time_ago($log['created_at'])) ?>
                                    · <code style="font-family:var(--mono);font-size:10.5px;"><?= e($log['action']) ?></code>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <div>
        <section class="card reveal">
            <div class="card__head">
                <div>
                    <h2 class="card__title">Equipment status mix</h2>
                    <p class="card__subtitle">Records by current availability</p>
                </div>
            </div>
            <div class="card__body">
                <div class="chart-grid" style="grid-template-columns: 160px minmax(0,1fr);">
                    <div class="donut-wrap">
                        <div class="donut" style="width:150px;height:150px;--segments: <?= e($gradient) ?>;" role="img" aria-label="Equipment status distribution">
                            <div class="donut__center">
                                <span class="donut__value"><?= $distTotal ?></span>
                                <span class="donut__label">records</span>
                            </div>
                        </div>
                    </div>
                    <div class="legend">
                        <?php foreach ($distribution as $row): ?>
                            <div class="legend__item">
                                <span class="legend__swatch" style="background:<?= e($statusColors[$row['status']] ?? '#94A3B8') ?>"></span>
                                <span><?= e($statusLabels[$row['status']] ?? ucfirst($row['status'])) ?></span>
                                <span class="legend__value"><?= (int) $row['total'] ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($distribution === []): ?><span class="field__hint">No equipment yet.</span><?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="card reveal" style="margin-top:18px;">
            <div class="card__head">
                <div>
                    <h2 class="card__title">Overdue follow-ups</h2>
                    <p class="card__subtitle">Past their due date right now</p>
                </div>
                <span class="badge badge--danger"><?= count($overdue_list) ?></span>
            </div>
            <div class="card__body">
                <?php if ($overdue_list === []): ?>
                    <div class="empty-state" style="padding:24px 10px;">
                        <div class="empty-state__icon" style="width:48px;height:48px;"><?= icon('check-circle', 'icon icon--lg') ?></div>
                        <p>No overdue transactions. Excellent.</p>
                    </div>
                <?php else: ?>
                    <div style="display:grid; gap:10px;">
                        <?php foreach ($overdue_list as $row): ?>
                            <a href="<?= e(url('borrow/' . (int) $row['request_id'])) ?>" class="callout" style="text-decoration:none; align-items:center;">
                                <span class="avatar avatar--sm"><?= e(initials($row['first_name'], $row['last_name'])) ?></span>
                                <span style="flex:1;min-width:0;">
                                    <strong style="display:block;font-size:12.5px;"><?= e($row['equipment_name']) ?></strong>
                                    <span style="font-size:11px;"><?= e($row['first_name'] . ' ' . $row['last_name']) ?> · <?= (int) $row['days_overdue'] ?> day(s) late</span>
                                </span>
                                <?= icon('chevron-right', 'icon icon--sm') ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card reveal" style="margin-top:18px;">
            <div class="card__head">
                <div>
                    <h2 class="card__title">Low stock</h2>
                    <p class="card__subtitle">Two or fewer units available</p>
                </div>
                <span class="badge badge--warning"><?= $lowStockCount ?></span>
            </div>
            <div class="card__body">
                <?php if ($low_stock === []): ?>
                    <p class="field__hint">No low-stock items right now.</p>
                <?php else: ?>
                    <div style="display:grid; gap:12px;">
                        <?php foreach ($low_stock as $item): ?>
                            <div>
                                <div style="display:flex;justify-content:space-between;gap:10px;font-size:12px;">
                                    <a href="<?= e(url('equipment/' . (int) $item['id'])) ?>" style="font-weight:600;color:var(--ink);"><?= e($item['name']) ?></a>
                                    <span style="color:var(--muted);"><?= (int) $item['available_quantity'] ?> / <?= (int) $item['total_quantity'] ?></span>
                                </div>
                                <div class="progress" style="margin-top:5px;"><div class="progress__bar <?= (int) $item['available_quantity'] === 0 ? 'progress__bar--danger' : 'progress__bar--warning' ?>" style="width: <?= (int) $item['total_quantity'] > 0 ? max(2, ((int) $item['available_quantity'] / (int) $item['total_quantity']) * 100) : 0 ?>%"></div></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card reveal" style="margin-top:18px;">
            <div class="card__head">
                <div>
                    <h2 class="card__title">Category distribution</h2>
                    <p class="card__subtitle">Total units per category</p>
                </div>
            </div>
            <div class="card__body">
                <div class="hbar">
                    <?php foreach ($categories as $cat): ?>
                        <div class="hbar__row">
                            <span title="<?= e($cat['category']) ?>"><?= e(truncate($cat['category'], 18)) ?></span>
                            <span class="hbar__track"><span class="hbar__fill" style="width: <?= (int) $cat['units'] > 0 ? round(((int) $cat['units'] / $maxCategory) * 100) : 2 ?>%"></span></span>
                            <span class="hbar__value"><?= (int) $cat['units'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>
</div>
