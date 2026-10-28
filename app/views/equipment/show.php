<?php
$total = max(0, (int) $equipment['total_quantity']);
$available = (int) $equipment['available_quantity'];
$percent = static fn (int $value): float => $total > 0 ? ($value / $total) * 100 : 0;
$canBorrow = $equipment['record_status'] === 'active' && $available > 0 && $equipment['condition_status'] === 'good';
$unavailableReason = match (true) {
    $equipment['record_status'] === 'archived' => 'This record is archived and cannot be borrowed.',
    $equipment['condition_status'] !== 'good' => 'The remaining units are not in borrowable condition.',
    $available === 0 => 'All units are currently borrowed, damaged, under repair, or lost.',
    default => '',
};
?>
<div class="page-header reveal">
    <div>
        <div class="page-header__eyebrow"><a href="<?= e(url('equipment')) ?>" style="color:inherit;">Equipment catalog</a> / <?= e($equipment['equipment_code']) ?></div>
        <h1 class="page-title"><?= e($equipment['name']) ?></h1>
        <p class="page-subtitle">Full inventory record, live quantities, condition history, and borrowing activity.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn--ghost" href="<?= e(url('equipment')) ?>"><?= icon('arrow-left') ?> Back to catalog</a>
        <?php if (is_admin()): ?>
            <a class="btn btn--primary" href="<?= e(url('equipment/' . (int) $equipment['id'] . '/edit')) ?>" data-testid="edit-equipment-button"><?= icon('edit') ?> Edit record</a>
        <?php elseif ($canBorrow): ?>
            <a class="btn btn--primary" href="<?= e(url('borrow/create', ['equipment_id' => (int) $equipment['id']])) ?>" data-testid="detail-borrow-button"><?= icon('plus') ?> Request this equipment</a>
        <?php endif; ?>
    </div>
</div>

<div class="detail-hero reveal">
    <span class="avatar avatar--lg"><?= icon('box', 'icon icon--xl') ?></span>
    <div style="position:relative;z-index:1;">
        <div class="chips">
            <span class="chip"><strong><?= e($equipment['equipment_code']) ?></strong></span>
            <span class="chip"><?= e($equipment['category_name']) ?></span>
            <span class="chip"><?= icon('map-pin', 'icon icon--xs') ?> <?= e($equipment['storage_location']) ?></span>
            <?php if (!empty($equipment['barcode'])): ?><span class="chip"><?= icon('tag', 'icon icon--xs') ?> <?= e($equipment['barcode']) ?></span><?php endif; ?>
        </div>
        <h1 style="margin-top:10px;"><?= e($equipment['name']) ?></h1>
        <p><?= e(truncate($equipment['description'] ?? 'No description recorded.', 160)) ?></p>
    </div>
    <div class="detail-hero__actions">
        <?= status_badge($equipment['record_status'] === 'archived' ? 'archived' : $equipment['condition_status']) ?>
        <?php if (is_admin()): ?>
            <?php if ($equipment['record_status'] === 'active'): ?>
                <form method="post" action="<?= e(url('equipment/' . (int) $equipment['id'] . '/archive')) ?>"
                      data-confirm="Archive this equipment record? It will no longer appear as borrowable."
                      data-confirm-title="Archive equipment" data-confirm-button="Archive" data-confirm-danger="1">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--ghost btn--sm"><?= icon('archive') ?> Archive</button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= e(url('equipment/' . (int) $equipment['id'] . '/restore')) ?>"
                      data-confirm="Restore this equipment to the active catalog?" data-confirm-title="Restore equipment" data-confirm-button="Restore">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--ghost btn--sm"><?= icon('refresh') ?> Restore</button>
                </form>
            <?php endif; ?>
        <?php elseif ($canBorrow): ?>
            <a class="btn btn--primary btn--sm" href="<?= e(url('borrow/create', ['equipment_id' => (int) $equipment['id']])) ?>">Request</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$canBorrow): ?>
    <div class="alert alert--warning" role="status">
        <?= icon('alert-triangle') ?>
        <div class="alert__content">
            <div class="alert__title">Not available for borrowing</div>
            <div><?= e($unavailableReason ?: 'This equipment cannot be borrowed at the moment.') ?></div>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid--sidebar">
    <div>
        <section class="card reveal">
            <div class="card__head card__head--line">
                <div>
                    <h2 class="card__title">Stock breakdown</h2>
                    <p class="card__subtitle">Total = available + borrowed + damaged + under repair + lost</p>
                </div>
                <div class="card__tools"><span class="badge badge--muted"><?= $total ?> total unit(s)</span></div>
            </div>
            <div class="card__body" style="padding-top:16px;">
                <div class="quantity-grid">
                    <div class="quantity-tile"><div class="quantity-tile__value"><?= $total ?></div><div class="quantity-tile__label">Total</div></div>
                    <div class="quantity-tile quantity-tile--available"><div class="quantity-tile__value"><?= $available ?></div><div class="quantity-tile__label">Available</div></div>
                    <div class="quantity-tile quantity-tile--borrowed"><div class="quantity-tile__value"><?= (int) $equipment['borrowed_quantity'] ?></div><div class="quantity-tile__label">Borrowed</div></div>
                    <div class="quantity-tile quantity-tile--damaged"><div class="quantity-tile__value"><?= (int) $equipment['damaged_quantity'] ?></div><div class="quantity-tile__label">Damaged</div></div>
                    <div class="quantity-tile quantity-tile--repair"><div class="quantity-tile__value"><?= (int) $equipment['under_repair_quantity'] ?></div><div class="quantity-tile__label">In repair</div></div>
                    <div class="quantity-tile"><div class="quantity-tile__value"><?= (int) $equipment['lost_quantity'] ?></div><div class="quantity-tile__label">Lost</div></div>
                </div>

                <div class="qty-bar" style="height:10px;margin-top:18px;" aria-hidden="true">
                    <span class="qty-bar__seg qty-bar__seg--available" style="width: <?= round($percent($available), 2) ?>%"></span>
                    <span class="qty-bar__seg qty-bar__seg--borrowed" style="width: <?= round($percent((int) $equipment['borrowed_quantity']), 2) ?>%"></span>
                    <span class="qty-bar__seg qty-bar__seg--damaged" style="width: <?= round($percent((int) $equipment['damaged_quantity']), 2) ?>%"></span>
                    <span class="qty-bar__seg qty-bar__seg--repair" style="width: <?= round($percent((int) $equipment['under_repair_quantity']), 2) ?>%"></span>
                    <span class="qty-bar__seg qty-bar__seg--lost" style="width: <?= round($percent((int) $equipment['lost_quantity']), 2) ?>%"></span>
                </div>
                <div class="field__hint" style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px;">
                    <span><span class="legend__swatch" style="display:inline-block;background:var(--teal-500);"></span> Available</span>
                    <span><span class="legend__swatch" style="display:inline-block;background:var(--royal-500);"></span> Borrowed</span>
                    <span><span class="legend__swatch" style="display:inline-block;background:#f97066;"></span> Damaged</span>
                    <span><span class="legend__swatch" style="display:inline-block;background:#fdb022;"></span> Repair</span>
                    <span><span class="legend__swatch" style="display:inline-block;background:#98a2b3;"></span> Lost</span>
                </div>
            </div>
        </section>

        <section class="card reveal" style="margin-top:18px;">
            <div class="card__head card__head--line">
                <div>
                    <h2 class="card__title">Borrowing history</h2>
                    <p class="card__subtitle">Transactions recorded for this equipment</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table table-responsive-cards" data-testid="equipment-transactions">
                    <thead><tr><th>Transaction</th><th>Borrower</th><th>Qty</th><th>Borrowed</th><th>Due</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if ($transactions === []): ?>
                            <tr><td colspan="6"><div class="table-empty">No borrowing transactions yet.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td data-label="Transaction"><span class="cell-main"><?= e($tx['transaction_code']) ?></span><span class="cell-sub"><?= e($tx['request_code']) ?></span></td>
                                    <td data-label="Borrower"><span class="cell-main"><?= e($tx['first_name'] . ' ' . $tx['last_name']) ?></span><span class="cell-sub"><?= e($tx['school_id']) ?></span></td>
                                    <td data-label="Qty" class="num"><?= (int) $tx['quantity'] ?> <span class="cell-sub">(<?= (int) $tx['quantity'] - (int) $tx['quantity_returned'] ?> out)</span></td>
                                    <td data-label="Borrowed" class="nowrap"><?= e(format_date($tx['borrowed_at'])) ?></td>
                                    <td data-label="Due" class="nowrap"><?= e(format_date($tx['due_at'])) ?></td>
                                    <td data-label="Status"><?= status_badge($tx['status']) ?></td>
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
                    <h2 class="card__title">Condition history</h2>
                    <p class="card__subtitle">Auditable record of inspections, returns, and adjustments</p>
                </div>
            </div>
            <div class="card__body" style="padding-top:16px;">
                <?php if ($conditionHistory === []): ?>
                    <div class="empty-state" style="padding:24px;">
                        <div class="empty-state__icon" style="width:48px;height:48px;"><?= icon('shield', 'icon icon--lg') ?></div>
                        <p>No condition changes recorded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($conditionHistory as $history): ?>
                            <div class="timeline__item">
                                <span class="timeline__dot <?= $history['new_condition'] === 'damaged' || $history['new_condition'] === 'lost' ? 'timeline__dot--red' : ($history['new_condition'] === 'good' ? 'timeline__dot--teal' : 'timeline__dot--amber') ?>"></span>
                                <div class="timeline__title">
                                    <?= e(condition_label($history['previous_condition'])) ?> → <?= e(condition_label($history['new_condition'])) ?>
                                    <span class="badge badge--muted"><?= (int) $history['quantity_affected'] ?> unit(s)</span>
                                </div>
                                <div class="timeline__meta">
                                    <?= e($history['notes'] ?: 'Condition recorded.') ?>
                                    · <?= e(format_datetime($history['recorded_at'])) ?>
                                    <?php if (!empty($history['first_name'])): ?> · by <?= e($history['first_name'] . ' ' . $history['last_name']) ?><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div>
        <section class="card reveal">
            <div class="card__head">
                <div><h2 class="card__title">Record details</h2></div>
            </div>
            <div class="card__body">
                <dl class="definition-list">
                    <div><dt>Equipment ID</dt><dd style="font-family:var(--mono);font-size:12px;"><?= e($equipment['equipment_code']) ?></dd></div>
                    <div><dt>Category</dt><dd><?= e($equipment['category_name']) ?></dd></div>
                    <div><dt>Condition</dt><dd><?= status_badge($equipment['condition_status']) ?></dd></div>
                    <div><dt>Record status</dt><dd><?= status_badge($equipment['record_status'] === 'archived' ? 'archived' : 'active_record') ?></dd></div>
                    <div><dt>Storage location</dt><dd><?= e($equipment['storage_location']) ?></dd></div>
                    <div><dt>Barcode / QR</dt><dd style="font-family:var(--mono);font-size:12px;"><?= e($equipment['barcode'] ?: 'Not assigned') ?></dd></div>
                    <div><dt>Date added</dt><dd><?= e(format_date($equipment['created_at'])) ?></dd></div>
                    <div><dt>Last updated</dt><dd><?= e(format_datetime($equipment['updated_at'])) ?></dd></div>
                </dl>
                <div class="callout" style="margin-top:14px;">
                    <?= icon('info', 'icon icon--sm') ?>
                    <div><?= e($equipment['category_description'] ?: 'No category description.') ?></div>
                </div>
            </div>
        </section>

        <?php if (is_admin()): ?>
            <section class="card reveal" style="margin-top:18px;">
                <div class="card__head">
                    <div>
                        <h2 class="card__title">Adjust condition</h2>
                        <p class="card__subtitle">Move units between statuses without changing the total.</p>
                    </div>
                </div>
                <div class="card__body">
                    <form method="post" action="<?= e(url('equipment/' . (int) $equipment['id'] . '/condition')) ?>" data-loading
                          data-confirm="Apply this condition adjustment? Equipment quantities and history will be updated."
                          data-confirm-title="Adjust inventory condition" data-confirm-button="Apply adjustment">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div class="field">
                                <label class="field__label" for="from_bucket">From</label>
                                <select class="select" id="from_bucket" name="from_bucket" required data-testid="condition-from">
                                    <option value="available">Available (<?= $available ?>)</option>
                                    <option value="damaged">Damaged (<?= (int) $equipment['damaged_quantity'] ?>)</option>
                                    <option value="under_repair">Under repair (<?= (int) $equipment['under_repair_quantity'] ?>)</option>
                                    <option value="lost">Lost (<?= (int) $equipment['lost_quantity'] ?>)</option>
                                </select>
                            </div>
                            <div class="field">
                                <label class="field__label" for="to_bucket">To</label>
                                <select class="select" id="to_bucket" name="to_bucket" required data-testid="condition-to">
                                    <option value="damaged">Damaged</option>
                                    <option value="under_repair">Under repair</option>
                                    <option value="lost">Lost</option>
                                    <option value="available">Available (repaired / restored)</option>
                                </select>
                            </div>
                            <div class="field">
                                <label class="field__label" for="condition_quantity">Quantity</label>
                                <input class="input" type="number" id="condition_quantity" name="quantity" min="1" value="1" required data-testid="condition-quantity">
                            </div>
                            <div class="field">
                                <label class="field__label" for="condition_notes">Notes</label>
                                <input class="input" type="text" id="condition_notes" name="notes" maxlength="500" placeholder="Reason or inspection notes">
                            </div>
                        </div>
                        <div class="field__hint" style="margin-top:10px;">
                            Damaged, lost, and under-repair units are never added to available stock.
                        </div>
                        <button type="submit" class="btn btn--secondary btn--block" style="margin-top:12px;" data-testid="condition-submit"><?= icon('tool') ?> Apply adjustment</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
