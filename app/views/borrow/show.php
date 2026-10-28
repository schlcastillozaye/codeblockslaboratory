<?php
$isOwner = (int) $request['user_id'] === (int) ($currentUser['id'] ?? 0);
$canReview = is_admin() && $request['status'] === 'pending' && !$isOwner;
$openTransactions = array_values(array_filter($transactions, static fn (array $tx): bool => in_array($tx['status'], ['active', 'overdue', 'partially_returned'], true)));
$firstCheckout = $transactions[0]['borrowed_at'] ?? null;
$allReturned = $transactions !== [] && count($openTransactions) === 0;
?>
<div class="page-header reveal">
    <div>
        <div class="page-header__eyebrow"><a href="<?= e(url('borrow')) ?>" style="color:inherit;">Borrowing requests</a> / <?= e($request['request_code']) ?></div>
        <h1 class="page-title">Request <?= e($request['request_code']) ?></h1>
        <p class="page-subtitle">Submitted <?= e(format_datetime($request['created_at'])) ?> by <?= e($request['first_name'] . ' ' . $request['last_name']) ?>.</p>
    </div>
    <div class="page-actions">
        <?= status_badge($request['status']) ?>
        <a class="btn btn--ghost" href="<?= e(url('borrow')) ?>"><?= icon('arrow-left') ?> Back</a>
    </div>
</div>

<?php if ($request['status'] === 'rejected' && !empty($request['rejection_reason'])): ?>
    <div class="alert alert--error" data-persist role="status">
        <?= icon('x-circle') ?>
        <div class="alert__content">
            <div class="alert__title">Request rejected</div>
            <div><?= e($request['rejection_reason']) ?></div>
        </div>
    </div>
<?php elseif ($request['status'] === 'pending'): ?>
    <div class="alert alert--info" data-persist role="status">
        <?= icon('clock') ?>
        <div class="alert__content">
            <div class="alert__title">Pending laboratory review</div>
            <div>This request is not a checkout yet. Stock is not reserved until authorized personnel approve it.</div>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid--main">
    <div>
        <section class="card reveal">
            <div class="card__head card__head--line">
                <div>
                    <h2 class="card__title">Requested equipment</h2>
                    <p class="card__subtitle">Availability shown is live and may change before approval.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table table-responsive-cards">
                    <thead><tr><th>Equipment</th><th>Requested</th><th>Approved</th><th>Available now</th><th>Condition</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td data-label="Equipment">
                                    <a class="cell-main" href="<?= e(url('equipment/' . (int) $item['equipment_id'])) ?>"><?= e($item['name']) ?></a>
                                    <span class="cell-sub"><?= e($item['equipment_code']) ?> · <?= e($item['category_name']) ?> · <?= e($item['storage_location']) ?></span>
                                </td>
                                <td data-label="Requested" class="num"><?= (int) $item['quantity_requested'] ?></td>
                                <td data-label="Approved" class="num"><?= $item['quantity_approved'] !== null ? (int) $item['quantity_approved'] : '—' ?></td>
                                <td data-label="Available now" class="num"><?= (int) $item['available_quantity'] ?></td>
                                <td data-label="Condition"><?= status_badge($item['condition_status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card reveal" style="margin-top:18px;">
            <div class="card__head card__head--line">
                <div>
                    <h2 class="card__title">Purpose</h2>
                </div>
            </div>
            <div class="card__body" style="padding-top:14px;">
                <p style="font-size:13.5px;color:var(--ink-soft);white-space:pre-line;"><?= e($request['purpose']) ?></p>
            </div>
        </section>

        <section class="card reveal" style="margin-top:18px;">
            <div class="card__head card__head--line">
                <div>
                    <h2 class="card__title">Checkout transactions</h2>
                    <p class="card__subtitle">Created only after approval, never from a pending request.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table table-responsive-cards" data-testid="request-transactions">
                    <thead><tr><th>Transaction</th><th>Equipment</th><th>Qty</th><th>Borrowed</th><th>Due</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <?php if ($transactions === []): ?>
                            <tr><td colspan="7"><div class="table-empty">No transaction yet — this request has not been approved.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $tx): ?>
                                <?php $open = in_array($tx['status'], ['active', 'overdue', 'partially_returned'], true); ?>
                                <tr class="<?= $tx['status'] === 'overdue' ? 'row-overdue' : '' ?>">
                                    <td data-label="Transaction"><span class="cell-main"><?= e($tx['transaction_code']) ?></span><?php if (!empty($tx['return_code'])): ?><span class="cell-sub"><?= e($tx['return_code']) ?></span><?php endif; ?></td>
                                    <td data-label="Equipment"><?= e($tx['equipment_name']) ?></td>
                                    <td data-label="Qty" class="num"><?= (int) $tx['quantity'] ?> <span class="cell-sub">(<?= (int) $tx['outstanding_quantity'] ?> out)</span></td>
                                    <td data-label="Borrowed" class="nowrap"><?= e(format_date($tx['borrowed_at'])) ?></td>
                                    <td data-label="Due" class="nowrap"><?= e(format_date($tx['due_at'])) ?></td>
                                    <td data-label="Status"><?= status_badge($tx['status']) ?></td>
                                    <td data-label="Action">
                                        <div class="cell-actions">
                                            <?php if (is_admin() && $open): ?>
                                                <a class="btn btn--sm btn--primary" href="<?= e(url('returns/create', ['transaction' => (int) $tx['id']])) ?>" data-testid="return-transaction-<?= (int) $tx['id'] ?>"><?= icon('rotate-ccw', 'icon icon--xs') ?> Process return</a>
                                            <?php elseif (is_admin()): ?>
                                                <a class="btn btn--sm btn--ghost" href="<?= e(url('returns')) ?>">Returns</a>
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
    </div>

    <aside>
        <section class="card reveal">
            <div class="card__head">
                <div><h2 class="card__title">Request timeline</h2></div>
            </div>
            <div class="card__body">
                <div class="timeline">
                    <div class="timeline__item">
                        <span class="timeline__dot timeline__dot--teal"></span>
                        <div class="timeline__title">Submitted</div>
                        <div class="timeline__meta"><?= e(format_datetime($request['created_at'])) ?> · <?= e($request['first_name'] . ' ' . $request['last_name']) ?></div>
                    </div>
                    <div class="timeline__item">
                        <span class="timeline__dot <?= $request['status'] === 'pending' ? 'timeline__dot--amber' : ($request['status'] === 'rejected' ? 'timeline__dot--red' : '') ?>"></span>
                        <div class="timeline__title">
                            <?= $request['status'] === 'pending' ? 'Awaiting review' : ($request['status'] === 'rejected' ? 'Rejected' : 'Reviewed') ?>
                        </div>
                        <div class="timeline__meta">
                            <?php if ($request['reviewed_at']): ?>
                                <?= e(format_datetime($request['reviewed_at'])) ?>
                                <?php if (!empty($request['reviewer_first_name'])): ?> · by <?= e($request['reviewer_first_name'] . ' ' . $request['reviewer_last_name']) ?><?php endif; ?>
                            <?php else: ?>
                                Laboratory personnel have not acted on this request yet.
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="timeline__item">
                        <span class="timeline__dot <?= $firstCheckout ? 'timeline__dot--teal' : '' ?>"></span>
                        <div class="timeline__title">Checkout</div>
                        <div class="timeline__meta"><?= $firstCheckout ? e(format_datetime($firstCheckout)) : 'Not checked out yet.' ?></div>
                    </div>
                    <div class="timeline__item">
                        <span class="timeline__dot <?= $allReturned ? 'timeline__dot--teal' : '' ?>"></span>
                        <div class="timeline__title">Return</div>
                        <div class="timeline__meta">
                            <?php if ($allReturned): ?>
                                All units returned. Condition recorded in the return record.
                            <?php elseif ($openTransactions !== []): ?>
                                <?= count($openTransactions) ?> transaction(s) still open.
                            <?php else: ?>
                                Not applicable yet.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card reveal" style="margin-top:18px;">
            <div class="card__head">
                <div><h2 class="card__title">Borrower</h2></div>
            </div>
            <div class="card__body">
                <div style="display:flex;gap:11px;align-items:center;margin-bottom:12px;">
                    <span class="avatar"><?= e(initials($request['first_name'], $request['last_name'])) ?></span>
                    <div>
                        <div style="font-weight:650;"><?= e($request['first_name'] . ' ' . $request['last_name']) ?></div>
                        <div class="field__hint"><?= e(role_label($request['role'])) ?></div>
                    </div>
                </div>
                <dl class="definition-list">
                    <div><dt>School / employee ID</dt><dd><?= e($request['school_id']) ?></dd></div>
                    <div><dt>Email</dt><dd style="font-size:11.5px;"><?= e($request['email']) ?></dd></div>
                    <div><dt>Department</dt><dd><?= e($request['department'] ?: '—') ?></dd></div>
                    <div><dt>Expected return</dt><dd><?= e(format_date($request['expected_return_date'])) ?></dd></div>
                </dl>
            </div>
        </section>

        <?php if ($canReview): ?>
            <section class="card reveal" style="margin-top:18px;">
                <div class="card__head">
                    <div><h2 class="card__title">Review decision</h2><p class="card__subtitle">Restricted to laboratory personnel.</p></div>
                </div>
                <div class="card__body">
                    <form method="post" action="<?= e(url('borrow/' . (int) $request['id'] . '/approve')) ?>" style="margin-bottom:12px;"
                          data-confirm="Approve this request and create the checkout? Current availability will be verified again before stock is released."
                          data-confirm-title="Approve borrowing request" data-confirm-button="Approve & checkout">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--primary btn--block" data-testid="approve-request"><?= icon('check-circle') ?> Approve request</button>
                    </form>

                    <form method="post" action="<?= e(url('borrow/' . (int) $request['id'] . '/reject')) ?>"
                          data-confirm="Reject this request? The borrower will be notified in-app."
                          data-confirm-title="Reject borrowing request" data-confirm-button="Reject request" data-confirm-danger="1">
                        <?= csrf_field() ?>
                        <div class="field">
                            <label class="field__label" for="rejection_reason">Rejection reason (optional)</label>
                            <textarea class="textarea" id="rejection_reason" name="rejection_reason" maxlength="500" rows="3"
                                      placeholder="Explain why this request cannot be approved…" data-testid="rejection-reason"></textarea>
                        </div>
                        <button type="submit" class="btn btn--danger btn--block" style="margin-top:10px;" data-testid="reject-request"><?= icon('x-circle') ?> Reject request</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($isOwner && $request['status'] === 'pending'): ?>
            <section class="card reveal" style="margin-top:18px;">
                <div class="card__head"><div><h2 class="card__title">Cancel request</h2></div></div>
                <div class="card__body">
                    <form method="post" action="<?= e(url('borrow/' . (int) $request['id'] . '/cancel')) ?>"
                          data-confirm="Cancel this pending request? This cannot be undone."
                          data-confirm-title="Cancel request" data-confirm-button="Cancel request" data-confirm-danger="1">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--danger btn--block" data-testid="cancel-request"><?= icon('ban') ?> Cancel my request</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <?php if (is_admin() && $openTransactions !== []): ?>
            <div class="callout reveal" style="margin-top:18px;">
                <?= icon('rotate-ccw', 'icon icon--sm') ?>
                <div>Process returns from the <a href="<?= e(url('transactions')) ?>">transactions page</a>. A condition is mandatory before stock is released.</div>
            </div>
        <?php endif; ?>
    </aside>
</div>
