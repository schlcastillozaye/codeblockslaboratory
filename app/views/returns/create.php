<?php
$errors = form_errors();
$outstanding = (int) $transaction['quantity'] - (int) $transaction['quantity_returned'];
$borrowedDate = date('Y-m-d', strtotime((string) $transaction['borrowed_at']));
$today = date('Y-m-d');
$returnDate = (string) old('return_date', $today);
$quantity = (string) old('quantity', (string) max(1, $outstanding));
$condition = (string) old('condition', '');
$notes = (string) old('notes', '');
$borrowerName = $transaction['first_name'] . ' ' . $transaction['last_name'];
$conditionOptions = [
    'good' => 'Good / Working — return to available stock',
    'damaged' => 'Damaged — move to damaged stock',
    'needs_maintenance' => 'Needs maintenance — move to repair stock',
    'incomplete' => 'Incomplete / missing parts — held as damaged',
];
?>
<div class="page-header reveal">
    <div>
        <div class="page-header__eyebrow"><a href="<?= e(url('transactions')) ?>" style="color:inherit;">Transactions</a> / <?= e($transaction['transaction_code']) ?></div>
        <h1 class="page-title">Process equipment return</h1>
        <p class="page-subtitle">
            Record the condition of every returned unit. The inventory update, return record, condition history,
            audit entry, and notification are committed together or not at all.
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn--ghost" href="<?= e(url('borrow/' . (int) $transaction['request_id'])) ?>"><?= icon('file') ?> View request</a>
        <a class="btn btn--ghost" href="<?= e(url('transactions')) ?>"><?= icon('arrow-left') ?> Back</a>
    </div>
</div>

<div class="grid grid--main">
    <div>
        <section class="card reveal">
            <div class="card__head card__head--line">
                <div>
                    <h2 class="card__title">Return details</h2>
                    <p class="card__subtitle">Fields marked * are required. The return is recorded only after the details are verified.</p>
                </div>
                <?= status_badge($transaction['status']) ?>
            </div>
            <div class="card__body" style="padding-top:18px;">
                <form method="post" action="<?= e(url('returns')) ?>" data-loading data-testid="return-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="transaction_id" value="<?= (int) $transaction['id'] ?>">

                    <div class="form-grid">
                        <div class="field">
                            <label class="field__label" for="quantity">Quantity returning <span class="req">*</span></label>
                            <input class="input <?= isset($errors['quantity']) ? 'is-invalid' : '' ?>" type="number" id="quantity" name="quantity"
                                   value="<?= e($quantity) ?>" min="1" max="<?= $outstanding ?>" required
                                   data-testid="return-quantity" aria-required="true">
                            <span class="field__hint"><?= $outstanding ?> unit(s) still outstanding on this transaction.</span>
                            <?php if (isset($errors['quantity'])): ?><span class="field__error"><?= e($errors['quantity']) ?></span><?php endif; ?>
                        </div>

                        <div class="field">
                            <label class="field__label" for="return_date">Return date <span class="req">*</span></label>
                            <input class="input <?= isset($errors['return_date']) ? 'is-invalid' : '' ?>" type="date" id="return_date" name="return_date"
                                   value="<?= e($returnDate) ?>" min="<?= e($borrowedDate) ?>" max="<?= e($today) ?>" required
                                   data-testid="return-date" aria-required="true">
                            <span class="field__hint">Cannot be before the checkout date or after today.</span>
                            <?php if (isset($errors['return_date'])): ?><span class="field__error"><?= e($errors['return_date']) ?></span><?php endif; ?>
                        </div>

                        <div class="field field--full">
                            <label class="field__label" for="condition">Equipment condition <span class="req">*</span></label>
                            <select class="select <?= isset($errors['condition']) ? 'is-invalid' : '' ?>" id="condition" name="condition" required
                                    data-testid="return-condition" aria-required="true" aria-describedby="condition-help">
                                <option value="">Select the inspected condition…</option>
                                <?php foreach ($conditionOptions as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= selected($condition, $value) ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field__hint" id="condition-help">
                                A return cannot be submitted without a condition. Select the condition that matches the returned equipment.
                            </span>
                            <?php if (isset($errors['condition'])): ?><span class="field__error" data-testid="condition-error"><?= e($errors['condition']) ?></span><?php endif; ?>
                        </div>

                        <div class="field field--full">
                            <label class="field__label" for="notes">Condition notes</label>
                            <textarea class="textarea <?= isset($errors['notes']) ? 'is-invalid' : '' ?>" id="notes" name="notes" maxlength="500" rows="3"
                                      placeholder="Describe damage, missing accessories, or other inspection findings…"
                                      data-testid="return-notes"><?= e($notes) ?></textarea>
                            <span class="field__hint">Optional, up to 500 characters. Recorded permanently in the condition history.</span>
                            <?php if (isset($errors['notes'])): ?><span class="field__error"><?= e($errors['notes']) ?></span><?php endif; ?>
                        </div>
                    </div>

                    <div class="alert alert--warning" data-persist role="note" style="margin-top:18px;">
                        <?= icon('alert-triangle') ?>
                        <div class="alert__content">
                            <div class="alert__title">Condition controls stock placement</div>
                            <div>
                                Only <strong>Good / Working</strong> returns are released to available stock.
                                Damaged and incomplete units go to damaged stock; maintenance units go to repair.
                            </div>
                        </div>
                    </div>

                    <div class="btn-row" style="margin-top:20px;">
                        <button type="submit" class="btn btn--primary" data-testid="return-submit">
                            <?= icon('rotate-ccw') ?> Record return
                        </button>
                        <a class="btn btn--ghost" href="<?= e(url('transactions')) ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </section>

        <section class="card reveal" style="margin-top:18px;">
            <div class="card__head card__head--line">
                <div>
                    <h2 class="card__title">Previous returns on this transaction</h2>
                    <p class="card__subtitle">Partial returns are supported; each one is recorded separately.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table table-responsive-cards" data-testid="previous-returns">
                    <thead><tr><th>Return</th><th>Date</th><th>Qty</th><th>Condition</th><th>Notes</th><th>Processed by</th></tr></thead>
                    <tbody>
                        <?php if ($previousReturns === []): ?>
                            <tr><td colspan="6"><div class="table-empty">No units have been returned on this transaction yet.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($previousReturns as $record): ?>
                                <?php $processor = trim(($record['processor_first_name'] ?? '') . ' ' . ($record['processor_last_name'] ?? '')); ?>
                                <tr>
                                    <td data-label="Return"><span class="cell-main"><?= e($record['return_code']) ?></span></td>
                                    <td data-label="Date" class="nowrap"><?= e(format_date($record['return_date'])) ?></td>
                                    <td data-label="Qty" class="num"><?= (int) $record['quantity_returned'] ?></td>
                                    <td data-label="Condition"><?= status_badge($record['condition']) ?></td>
                                    <td data-label="Notes"><span class="cell-sub"><?= e($record['notes'] ?: '—') ?></span></td>
                                    <td data-label="Processed by"><span class="cell-sub"><?= e($processor !== '' ? $processor : '—') ?></span></td>
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
                <div><h2 class="card__title">Transaction summary</h2></div>
            </div>
            <div class="card__body">
                <div style="display:flex;gap:11px;align-items:center;margin-bottom:14px;">
                    <span class="avatar"><?= e(initials($transaction['first_name'], $transaction['last_name'])) ?></span>
                    <div>
                        <div style="font-weight:650;"><?= e($borrowerName) ?></div>
                        <div class="field__hint"><?= e($transaction['school_id']) ?> · <?= e($transaction['email']) ?></div>
                    </div>
                </div>
                <dl class="definition-list">
                    <div><dt>Equipment</dt><dd><?= e($transaction['equipment_name']) ?></dd></div>
                    <div><dt>Equipment ID</dt><dd style="font-family:var(--mono);font-size:11.5px;"><?= e($transaction['equipment_code']) ?></dd></div>
                    <div><dt>Category</dt><dd><?= e($transaction['category_name']) ?></dd></div>
                    <div><dt>Request</dt><dd><?= e($transaction['request_code']) ?></dd></div>
                    <div><dt>Checked out</dt><dd><?= (int) $transaction['quantity'] ?> unit(s)</dd></div>
                    <div><dt>Already returned</dt><dd><?= (int) $transaction['quantity_returned'] ?> unit(s)</dd></div>
                    <div><dt>Outstanding</dt><dd><strong><?= $outstanding ?> unit(s)</strong></dd></div>
                    <div><dt>Borrowed on</dt><dd><?= e(format_date($transaction['borrowed_at'])) ?></dd></div>
                    <div><dt>Due on</dt><dd><?= e(format_date($transaction['due_at'])) ?></dd></div>
                </dl>
            </div>
        </section>

        <section class="card reveal" style="margin-top:18px;">
            <div class="card__head">
                <div><h2 class="card__title">Atomicity</h2><p class="card__subtitle">Why this form is safe</p></div>
            </div>
            <div class="card__body">
                <div class="timeline">
                    <div class="timeline__item">
                        <span class="timeline__dot timeline__dot--teal"></span>
                        <div class="timeline__title">Validate first</div>
                        <div class="timeline__meta">Condition, quantity, and date are checked before the return is saved.</div>
                    </div>
                    <div class="timeline__item">
                        <span class="timeline__dot"></span>
                        <div class="timeline__title">Lock the transaction</div>
                        <div class="timeline__meta">A row lock prevents two staff members from over-returning the same units.</div>
                    </div>
                    <div class="timeline__item">
                        <span class="timeline__dot timeline__dot--amber"></span>
                        <div class="timeline__title">Update the stock mix</div>
                        <div class="timeline__meta">Borrowed stock decreases and the selected condition category increases.</div>
                    </div>
                    <div class="timeline__item">
                        <span class="timeline__dot timeline__dot--teal"></span>
                        <div class="timeline__title">Commit together</div>
                        <div class="timeline__meta">Any failure rolls back inventory, return record, history, audit, and notification.</div>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</div>
