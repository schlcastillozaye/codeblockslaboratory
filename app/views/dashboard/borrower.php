<?php
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$dueSoon = (int) ($transactions['due_soon'] ?? 0);
$overdueCount = (int) ($transactions['overdue_total'] ?? 0);
$openCount = (int) ($transactions['open_total'] ?? 0);
$recommendedList = array_slice($recommended, 0, 4);
?>
<div class="page-header reveal">
    <div>
        <div class="page-header__eyebrow"><?= icon('sparkles', 'icon icon--sm') ?> <?= e(role_label($currentUser['role'])) ?> workspace</div>
        <h1 class="page-title"><?= e($greeting) ?>, <?= e($currentUser['first_name']) ?>.</h1>
        <p class="page-subtitle">Track your borrowing requests, due dates, and equipment returns — all in one place.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn--ghost" href="<?= e(url('transactions')) ?>" data-testid="dashboard-my-borrowings"><?= icon('arrow-up-right') ?> My borrowings</a>
        <a class="btn btn--primary" href="<?= e(url('borrow/create')) ?>" data-testid="dashboard-new-request"><?= icon('plus') ?> New request</a>
    </div>
</div>

<?php if ($overdueCount > 0): ?>
    <div class="alert alert--error" data-persist role="alert" data-testid="overdue-alert">
        <?= icon('alert-triangle') ?>
        <div class="alert__content">
            <div class="alert__title"><?= $overdueCount ?> overdue borrowing(s)</div>
            <div>Please return the equipment as soon as possible and coordinate with laboratory personnel.</div>
        </div>
        <a class="btn btn--sm btn--danger" href="<?= e(url('transactions', ['overdue' => 1])) ?>">View overdue</a>
    </div>
<?php elseif ($dueSoon > 0): ?>
    <div class="alert alert--warning" data-persist role="status">
        <?= icon('clock') ?>
        <div class="alert__content">
            <div class="alert__title"><?= $dueSoon ?> item(s) due within three days</div>
            <div>Prepare the equipment for return and check its condition before the due date.</div>
        </div>
    </div>
<?php endif; ?>

<div class="stats-grid" style="grid-template-columns: repeat(4, minmax(0,1fr));">
    <article class="stat-card tone-amber reveal">
        <div class="stat-card__top"><span class="stat-card__icon stat-card__icon--amber"><?= icon('clock') ?></span><a class="btn btn--sm btn--ghost" href="<?= e(url('borrow', ['status' => 'pending'])) ?>">View</a></div>
        <div class="stat-card__label">Pending requests</div>
        <div class="stat-card__value" data-counter="<?= (int) $requests['pending'] ?>">0</div>
        <div class="stat-card__meta">Awaiting laboratory approval</div>
    </article>
    <article class="stat-card reveal">
        <div class="stat-card__top"><span class="stat-card__icon"><?= icon('arrow-up-right') ?></span></div>
        <div class="stat-card__label">Active borrowings</div>
        <div class="stat-card__value" data-counter="<?= $openCount ?>">0</div>
        <div class="stat-card__meta">Equipment currently in your care</div>
    </article>
    <article class="stat-card tone-amber reveal">
        <div class="stat-card__top"><span class="stat-card__icon stat-card__icon--amber"><?= icon('calendar') ?></span></div>
        <div class="stat-card__label">Due soon</div>
        <div class="stat-card__value" data-counter="<?= $dueSoon ?>">0</div>
        <div class="stat-card__meta">Within the next three days</div>
    </article>
    <article class="stat-card tone-red reveal">
        <div class="stat-card__top"><span class="stat-card__icon stat-card__icon--red"><?= icon('alert-triangle') ?></span></div>
        <div class="stat-card__label">Overdue</div>
        <div class="stat-card__value" data-counter="<?= $overdueCount ?>">0</div>
        <div class="stat-card__meta">Needs immediate return</div>
    </article>
</div>

<div class="grid grid--main">
    <div>
        <section class="card reveal">
            <div class="card__head card__head--line">
                <div>
                    <h2 class="card__title">Equipment in your care</h2>
                    <p class="card__subtitle">Open borrowings and their due dates</p>
                </div>
                <div class="card__tools"><a class="btn btn--sm btn--ghost" href="<?= e(url('transactions')) ?>">All borrowings</a></div>
            </div>
            <div class="table-wrap">
                <table class="table table-responsive-cards" data-testid="active-borrowings-table">
                    <thead>
                        <tr>
                            <th>Equipment</th>
                            <th>Qty</th>
                            <th>Borrowed</th>
                            <th>Due</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($open === []): ?>
                            <tr><td colspan="5"><div class="table-empty">You have no active borrowings.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($open as $tx): ?>
                                <?php
                                $daysLeft = (int) $tx['days_left'];
                                $isOverdue = $daysLeft < 0;
                                $dueLabel = $isOverdue ? abs($daysLeft) . ' day(s) overdue' : ($daysLeft === 0 ? 'Due today' : 'Due in ' . $daysLeft . ' day(s)');
                                ?>
                                <tr class="<?= $isOverdue ? 'row-overdue' : '' ?>">
                                    <td data-label="Equipment">
                                        <span class="cell-main"><?= e($tx['equipment_name']) ?></span>
                                        <span class="cell-sub"><?= e($tx['transaction_code']) ?> · <?= e($tx['equipment_code']) ?></span>
                                    </td>
                                    <td data-label="Qty" class="num"><?= (int) $tx['outstanding_quantity'] ?> / <?= (int) $tx['quantity'] ?></td>
                                    <td data-label="Borrowed" class="nowrap"><?= e(format_date($tx['borrowed_at'])) ?></td>
                                    <td data-label="Due">
                                        <span class="cell-main nowrap"><?= e(format_date($tx['due_at'])) ?></span>
                                        <span class="cell-sub" style="color: <?= $isOverdue ? 'var(--red)' : 'var(--muted)' ?>;"><?= e($dueLabel) ?></span>
                                    </td>
                                    <td data-label="Status"><?= status_badge($isOverdue ? 'overdue' : $tx['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card reveal" style="margin-top:18px;">
            <div class="card__head card__head--line">
                <div>
                    <h2 class="card__title">Recent requests</h2>
                    <p class="card__subtitle">Your latest borrowing submissions</p>
                </div>
                <div class="card__tools"><a class="btn btn--sm btn--ghost" href="<?= e(url('borrow')) ?>">View all</a></div>
            </div>
            <div class="table-wrap">
                <table class="table table-responsive-cards">
                    <thead><tr><th>Request</th><th>Items</th><th>Expected return</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <?php if ($recent_requests === []): ?>
                            <tr><td colspan="5"><div class="table-empty">No requests yet. Browse the catalog to get started.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_requests as $req): ?>
                                <tr>
                                    <td data-label="Request"><span class="cell-main"><?= e($req['request_code']) ?></span><span class="cell-sub"><?= e(time_ago($req['created_at'])) ?></span></td>
                                    <td data-label="Items"><?= e(truncate($req['items_summary'] ?? '—', 42)) ?></td>
                                    <td data-label="Expected return" class="nowrap"><?= e(format_date($req['expected_return_date'])) ?></td>
                                    <td data-label="Status"><?= status_badge($req['status']) ?></td>
                                    <td data-label="Action"><div class="cell-actions"><a class="btn btn--sm btn--ghost" href="<?= e(url('borrow/' . (int) $req['id'])) ?>">View</a></div></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if ($recommendedList !== []): ?>
            <div class="section-head">
                <h2>Available for borrowing</h2>
                <a class="section-head__link" href="<?= e(url('equipment')) ?>">Browse full catalog <?= icon('arrow-right', 'icon icon--xs') ?></a>
            </div>
            <div class="equipment-grid">
                <?php foreach ($recommendedList as $item): ?>
                    <article class="equipment-card reveal">
                        <div class="equipment-card__head">
                            <span class="equipment-card__icon"><?= icon('box', 'icon icon--lg') ?></span>
                            <div>
                                <a class="equipment-card__title" href="<?= e(url('equipment/' . (int) $item['id'])) ?>"><?= e($item['name']) ?></a>
                                <div class="equipment-card__code"><?= e($item['equipment_code']) ?> · <?= e($item['category_name']) ?></div>
                            </div>
                        </div>
                        <div class="equipment-card__meta">
                            <span class="badge badge--success"><?= icon('check', 'icon icon--xs') ?> <?= (int) $item['available_quantity'] ?> available</span>
                        </div>
                        <div class="equipment-card__foot">
                            <a class="btn btn--sm btn--primary" href="<?= e(url('borrow/create', ['equipment_id' => (int) $item['id']])) ?>">Request this</a>
                            <a class="btn btn--sm btn--ghost" href="<?= e(url('equipment/' . (int) $item['id'])) ?>">Details</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <section class="card reveal">
            <div class="card__head">
                <div><h2 class="card__title">How borrowing works</h2><p class="card__subtitle">Four clear steps</p></div>
            </div>
            <div class="card__body">
                <div class="timeline">
                    <div class="timeline__item">
                        <span class="timeline__dot"></span>
                        <div class="timeline__title">1 · Submit a request</div>
                        <div class="timeline__meta">Choose equipment, quantity, purpose, and a return date.</div>
                    </div>
                    <div class="timeline__item">
                        <span class="timeline__dot"></span>
                        <div class="timeline__title">2 · Laboratory review</div>
                        <div class="timeline__meta">Personnel verify stock and approve or reject the request.</div>
                    </div>
                    <div class="timeline__item">
                        <span class="timeline__dot timeline__dot--teal"></span>
                        <div class="timeline__title">3 · Checkout recorded</div>
                        <div class="timeline__meta">Approval creates a transaction and updates inventory atomically.</div>
                    </div>
                    <div class="timeline__item">
                        <span class="timeline__dot timeline__dot--teal"></span>
                        <div class="timeline__title">4 · Return with condition</div>
                        <div class="timeline__meta">Personnel record the return condition before stock is released.</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card reveal" style="margin-top:18px;">
            <div class="card__head">
                <div><h2 class="card__title">Borrowing summary</h2><p class="card__subtitle">Your records to date</p></div>
            </div>
            <div class="card__body">
                <dl class="definition-list">
                    <div><dt>Total requests</dt><dd><?= array_sum($requests) ?></dd></div>
                    <div><dt>Approved</dt><dd><?= (int) $requests['approved'] ?></dd></div>
                    <div><dt>Completed</dt><dd><?= (int) $requests['completed'] ?></dd></div>
                    <div><dt>Rejected</dt><dd><?= (int) $requests['rejected'] ?></dd></div>
                    <div><dt>Returned transactions</dt><dd><?= (int) ($transactions['returned_total'] ?? 0) ?></dd></div>
                </dl>
            </div>
        </section>

        <section class="card reveal" style="margin-top:18px;">
            <div class="card__head">
                <div><h2 class="card__title">Need help?</h2></div>
            </div>
            <div class="card__body">
                <div class="callout">
                    <?= icon('info') ?>
                    <div>
                        <strong>Laboratory Management Office</strong><br>
                        Visit the lab counter for equipment concerns, damaged units, or due-date extensions.
                        In-app notifications are used for approval and return updates.
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
