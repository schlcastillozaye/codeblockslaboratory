<?php
$queryBase = array_filter(['q' => $filters['q'] !== '' ? $filters['q'] : null], static fn ($v) => $v !== null);
$pendingTotal = (int) $paginator->total;
?>
<div class="page-header reveal">
    <div>
        <div class="page-header__eyebrow"><?= icon('clock', 'icon icon--sm') ?> Laboratory operations</div>
        <h1 class="page-title">Pending approvals</h1>
        <p class="page-subtitle">
            Review requests before any stock moves. Approval re-checks live availability and records the checkout
            in one safe step, so stock cannot be allocated twice.
        </p>
    </div>
    <div class="page-actions">
        <span class="badge badge--warning" data-testid="pending-approval-count"><?= $pendingTotal ?> waiting</span>
        <a class="btn btn--ghost" href="<?= e(url('borrow')) ?>"><?= icon('file') ?> All requests</a>
        <a class="btn btn--ghost" href="<?= e(url('reports', ['type' => 'pending'])) ?>"><?= icon('chart') ?> Pending report</a>
    </div>
</div>

<?php if ($pendingTotal === 0): ?>
    <div class="card reveal">
        <div class="empty-state" data-testid="approvals-empty">
            <div class="empty-state__icon"><?= icon('check-circle', 'icon icon--xl') ?></div>
            <h3>The approval queue is clear</h3>
            <p>No borrowing requests are waiting for laboratory personnel. New submissions will appear here immediately.</p>
            <a class="btn btn--primary" href="<?= e(url('equipment')) ?>"><?= icon('boxes') ?> Browse equipment</a>
        </div>
    </div>
<?php else: ?>
    <form class="toolbar reveal" method="get" action="<?= e(url('approvals')) ?>" data-testid="approvals-filters">
        <div class="field field--grow">
            <label class="field__label" for="approval-search">Search the queue</label>
            <div class="input-icon">
                <?= icon('search') ?>
                <input class="input" type="search" id="approval-search" name="q" value="<?= e($filters['q']) ?>"
                       placeholder="Request code, borrower, school ID, purpose, or equipment…"
                       data-testid="approvals-search">
            </div>
        </div>
        <div class="toolbar__actions">
            <button type="submit" class="btn btn--secondary"><?= icon('search') ?> Search</button>
            <?php if ($queryBase !== []): ?><a class="btn btn--ghost" href="<?= e(url('approvals')) ?>">Reset</a><?php endif; ?>
        </div>
    </form>

    <section class="card reveal">
        <div class="card__head card__head--line">
            <div>
                <h2 class="card__title">Requests awaiting a decision</h2>
                <p class="card__subtitle">Oldest requests appear first. Nothing is reserved until approval.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table table-responsive-cards" data-testid="approvals-table">
                <thead>
                    <tr>
                        <th>Request</th>
                        <th>Borrower</th>
                        <th>Items</th>
                        <th>Units</th>
                        <th>Expected return</th>
                        <th>Submitted</th>
                        <th>Decision</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paginator->items as $req): ?>
                        <tr data-search="<?= e($req['request_code'] . ' ' . $req['first_name'] . ' ' . $req['last_name'] . ' ' . $req['school_id'] . ' ' . $req['purpose'] . ' ' . ($req['items_summary'] ?? '')) ?>">
                            <td data-label="Request">
                                <a class="cell-main" href="<?= e(url('borrow/' . (int) $req['id'])) ?>" data-testid="approval-open-<?= (int) $req['id'] ?>"><?= e($req['request_code']) ?></a>
                                <span class="cell-sub"><?= e(time_ago($req['created_at'])) ?></span>
                            </td>
                            <td data-label="Borrower">
                                <span class="cell-main"><?= e($req['first_name'] . ' ' . $req['last_name']) ?></span>
                                <span class="cell-sub"><?= e(role_short_label($req['role'])) ?> · <?= e($req['school_id']) ?></span>
                            </td>
                            <td data-label="Items">
                                <span class="cell-sub" style="font-size:12px;color:var(--ink-soft);"><?= e(truncate($req['items_summary'] ?: '—', 60)) ?></span>
                            </td>
                            <td data-label="Units" class="num"><?= (int) $req['total_units'] ?></td>
                            <td data-label="Expected return" class="nowrap"><?= e(format_date($req['expected_return_date'])) ?></td>
                            <td data-label="Submitted" class="nowrap"><?= e(format_datetime($req['created_at'])) ?></td>
                            <td data-label="Decision">
                                <div class="cell-actions">
                                    <a class="btn btn--sm btn--ghost" href="<?= e(url('borrow/' . (int) $req['id'])) ?>">Review</a>
                                    <form method="post" action="<?= e(url('borrow/' . (int) $req['id'] . '/approve')) ?>"
                                          data-confirm="Approve <?= e($req['request_code']) ?> for <?= e($req['first_name'] . ' ' . $req['last_name']) ?>? Live stock will be checked again and a checkout transaction created."
                                          data-confirm-title="Approve borrowing request" data-confirm-button="Approve & checkout">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn--sm btn--primary" data-testid="approve-<?= (int) $req['id'] ?>">
                                            <?= icon('check', 'icon icon--xs') ?> Approve
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?= pagination($paginator, 'approvals', $queryBase) ?>
<?php endif; ?>

<div class="callout reveal" style="margin-top:18px;" data-persist>
    <?= icon('shield', 'icon icon--sm') ?>
    <div>
        Approval is restricted to laboratory personnel and is recorded in the audit trail. A request can only be
        approved once, and each request item can be checked out only once.
    </div>
</div>
