<?php
$errors = form_errors();
$selectedId = (int) old('equipment_id', $selectedEquipmentId);
$noEquipment = $equipmentList === [];
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$maxDate = date('Y-m-d', strtotime('+' . (int) $maxDays . ' days'));
?>
<div class="page-header reveal">
    <div>
        <div class="page-header__eyebrow"><?= icon('clipboard', 'icon icon--sm') ?> New borrowing request</div>
        <h1 class="page-title">Request laboratory equipment</h1>
        <p class="page-subtitle">Your request stays pending until authorized laboratory personnel approve it.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn--ghost" href="<?= e(url('equipment')) ?>"><?= icon('boxes') ?> Browse catalog</a>
    </div>
</div>

<?php if ($noEquipment): ?>
    <div class="card reveal">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('box', 'icon icon--xl') ?></div>
            <h3>No equipment is currently available</h3>
            <p>All catalog items are borrowed, under repair, damaged, or archived. Please check back later or contact the laboratory.</p>
            <a class="btn btn--primary" href="<?= e(url('equipment')) ?>">View catalog</a>
        </div>
    </div>
<?php else: ?>
    <div class="grid grid--sidebar">
        <section class="card reveal">
            <div class="card__head card__head--line">
                <div>
                    <h2 class="card__title">Request details</h2>
                    <p class="card__subtitle">Complete every required field before submitting your request.</p>
                </div>
            </div>
            <div class="card__body" style="padding-top:18px;">
                <form method="post" action="<?= e(url('borrow')) ?>" data-loading data-testid="borrow-form">
                    <?= csrf_field() ?>

                    <div class="form-grid">
                        <div class="field field--full">
                            <label class="field__label" for="equipment_id">Equipment <span class="req">*</span></label>
                            <select class="select <?= isset($errors['equipment_id']) ? 'is-invalid' : '' ?>" id="equipment_id" name="equipment_id"
                                    required data-equipment-select data-testid="borrow-equipment" aria-required="true">
                                <option value="">Select available equipment</option>
                                <?php foreach ($equipmentList as $item): ?>
                                    <option value="<?= (int) $item['id'] ?>" <?= selected($selectedId, $item['id']) ?>
                                            data-stock="<?= (int) $item['available_quantity'] ?>"
                                            data-category="<?= e($item['category_name']) ?>"
                                            data-location="">
                                        <?= e($item['name']) ?> (<?= e($item['equipment_code']) ?>) — <?= (int) $item['available_quantity'] ?> available
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field__hint" data-stock-hint>Select equipment to see current availability.</span>
                            <?php if (isset($errors['equipment_id'])): ?><span class="field__error"><?= e($errors['equipment_id']) ?></span><?php endif; ?>
                        </div>

                        <div class="field">
                            <label class="field__label" for="quantity">Quantity <span class="req">*</span></label>
                            <input class="input <?= isset($errors['quantity']) ? 'is-invalid' : '' ?>" type="number" id="quantity" name="quantity"
                                   value="<?= e(old('quantity', '1')) ?>" min="1" max="<?= (int) $maxQuantity ?>" required
                                   data-quantity-input data-testid="borrow-quantity" aria-required="true">
                            <span class="field__hint">Maximum <?= (int) $maxQuantity ?> unit(s) per request.</span>
                            <?php if (isset($errors['quantity'])): ?><span class="field__error"><?= e($errors['quantity']) ?></span><?php endif; ?>
                        </div>

                        <div class="field">
                            <label class="field__label" for="expected_return_date">Expected return date <span class="req">*</span></label>
                            <input class="input <?= isset($errors['expected_return_date']) ? 'is-invalid' : '' ?>" type="date" id="expected_return_date" name="expected_return_date"
                                   value="<?= e(old('expected_return_date', $tomorrow)) ?>" min="<?= e($tomorrow) ?>" max="<?= e($maxDate) ?>" required
                                   data-min-days="1" data-max-days="<?= (int) $maxDays ?>" data-testid="borrow-return-date" aria-required="true">
                            <span class="field__hint">Between tomorrow and <?= e(format_date($maxDate)) ?> (<?= (int) $maxDays ?>-day limit).</span>
                            <?php if (isset($errors['expected_return_date'])): ?><span class="field__error"><?= e($errors['expected_return_date']) ?></span><?php endif; ?>
                        </div>

                        <div class="field field--full">
                            <label class="field__label" for="purpose">Purpose <span class="req">*</span></label>
                            <textarea class="textarea <?= isset($errors['purpose']) ? 'is-invalid' : '' ?>" id="purpose" name="purpose" maxlength="1000" required
                                      placeholder="Describe the class activity, experiment, or project. Minimum 10 characters."
                                      data-testid="borrow-purpose" aria-required="true"><?= e(old('purpose')) ?></textarea>
                            <span class="field__hint">Be specific so laboratory personnel can review the request properly.</span>
                            <?php if (isset($errors['purpose'])): ?><span class="field__error"><?= e($errors['purpose']) ?></span><?php endif; ?>
                        </div>
                    </div>

                    <div class="btn-row" style="margin-top:20px;">
                        <button type="submit" class="btn btn--primary" data-testid="borrow-submit"><?= icon('check') ?> Submit borrowing request</button>
                        <a class="btn btn--ghost" href="<?= e(url('equipment')) ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </section>

        <aside>
            <section class="card reveal">
                <div class="card__head">
                    <div><h2 class="card__title">Borrowing policy</h2><p class="card__subtitle">Before you submit</p></div>
                </div>
                <div class="card__body">
                    <div class="timeline">
                        <div class="timeline__item">
                            <span class="timeline__dot"></span>
                            <div class="timeline__title">Pending is not a checkout</div>
                            <div class="timeline__meta">Stock is not reserved while a request is pending. Availability is re-checked at approval.</div>
                        </div>
                        <div class="timeline__item">
                            <span class="timeline__dot timeline__dot--teal"></span>
                            <div class="timeline__title">Up to <?= (int) $maxDays ?> days</div>
                            <div class="timeline__meta">Choose a return date within the borrowing window.</div>
                        </div>
                        <div class="timeline__item">
                            <span class="timeline__dot timeline__dot--amber"></span>
                            <div class="timeline__title">Return condition required</div>
                            <div class="timeline__meta">Personnel record the condition on return. Damaged units never return to available stock automatically.</div>
                        </div>
                        <div class="timeline__item">
                            <span class="timeline__dot timeline__dot--teal"></span>
                            <div class="timeline__title">Track it in-app</div>
                            <div class="timeline__meta">You will receive a notification when the request is approved or rejected.</div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="callout reveal" style="margin-top:18px;">
                <?= icon('info', 'icon icon--sm') ?>
                <div>Requests are reviewed by the Laboratory Management Office. Approval creates the actual checkout transaction.</div>
            </div>
        </aside>
    </div>
<?php endif; ?>
