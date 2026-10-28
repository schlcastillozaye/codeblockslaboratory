<?php
$isEdit = $equipment !== null;
$action = $isEdit ? url('equipment/' . (int) $equipment['id']) : url('equipment');
$value = static function (string $field, mixed $default = '') use ($equipment) {
    return old($field, $equipment[$field] ?? $default);
};
$errors = form_errors();
?>
<div class="page-header reveal">
    <div>
        <div class="page-header__eyebrow"><a href="<?= e(url('equipment')) ?>" style="color:inherit;">Equipment catalog</a> / <?= $isEdit ? 'Edit' : 'Create' ?></div>
        <h1 class="page-title"><?= $isEdit ? 'Edit equipment record' : 'Add laboratory equipment' ?></h1>
        <p class="page-subtitle">
            <?= $isEdit
                ? 'Update catalog details. Quantity changes respect borrowed, damaged, repair, and lost units.'
                : 'Register a new equipment type. New records start fully available and in good condition.' ?>
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn--ghost" href="<?= e($isEdit ? url('equipment/' . (int) $equipment['id']) : url('equipment')) ?>"><?= icon('arrow-left') ?> Cancel</a>
    </div>
</div>

<div class="grid grid--sidebar">
    <section class="card reveal">
        <div class="card__head card__head--line">
            <div>
                <h2 class="card__title">Catalog information</h2>
                <p class="card__subtitle">Fields marked * are required.</p>
            </div>
        </div>
        <div class="card__body" style="padding-top:18px;">
            <form method="post" action="<?= e($action) ?>" data-loading data-testid="equipment-form" novalidate>
                <?= csrf_field() ?>

                <div class="form-grid">
                    <div class="field">
                        <label class="field__label" for="equipment_code">Equipment ID <span class="req">*</span></label>
                        <input class="input <?= isset($errors['equipment_code']) ? 'is-invalid' : '' ?>" type="text" id="equipment_code" name="equipment_code"
                               value="<?= e($value('equipment_code')) ?>" maxlength="40" placeholder="e.g. BIO-001" required
                               data-testid="equipment-code" aria-required="true">
                        <?php if (isset($errors['equipment_code'])): ?><span class="field__error"><?= e($errors['equipment_code']) ?></span><?php else: ?><span class="field__hint">Unique code, letters/numbers/dash/underscore (2–40 chars).</span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="field__label" for="name">Equipment name <span class="req">*</span></label>
                        <input class="input <?= isset($errors['name']) ? 'is-invalid' : '' ?>" type="text" id="name" name="name"
                               value="<?= e($value('name')) ?>" maxlength="150" placeholder="e.g. Binocular Microscope" required
                               data-testid="equipment-name" aria-required="true">
                        <?php if (isset($errors['name'])): ?><span class="field__error"><?= e($errors['name']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="field__label" for="category_id">Category <span class="req">*</span></label>
                        <select class="select <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>" id="category_id" name="category_id" required data-testid="equipment-category">
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>" <?= selected($value('category_id'), $category['id']) ?>><?= e($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['category_id'])): ?><span class="field__error"><?= e($errors['category_id']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="field__label" for="total_quantity">Total quantity <span class="req">*</span></label>
                        <input class="input <?= isset($errors['total_quantity']) ? 'is-invalid' : '' ?>" type="number" id="total_quantity" name="total_quantity"
                               value="<?= e($value('total_quantity', '1')) ?>" min="1" max="10000" required
                               data-testid="equipment-total" aria-required="true">
                        <?php if (isset($errors['total_quantity'])): ?>
                            <span class="field__error"><?= e($errors['total_quantity']) ?></span>
                        <?php elseif ($isEdit): ?>
                            <span class="field__hint">
                                Currently <?= (int) $equipment['borrowed_quantity'] ?> borrowed, <?= (int) $equipment['damaged_quantity'] ?> damaged,
                                <?= (int) $equipment['under_repair_quantity'] ?> in repair, <?= (int) $equipment['lost_quantity'] ?> lost.
                            </span>
                        <?php else: ?>
                            <span class="field__hint">All units start as available.</span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="field__label" for="storage_location">Storage location <span class="req">*</span></label>
                        <input class="input <?= isset($errors['storage_location']) ? 'is-invalid' : '' ?>" type="text" id="storage_location" name="storage_location"
                               value="<?= e($value('storage_location')) ?>" maxlength="150" placeholder="e.g. Science Lab A – Cabinet 3" required
                               data-testid="equipment-location" aria-required="true">
                        <?php if (isset($errors['storage_location'])): ?><span class="field__error"><?= e($errors['storage_location']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="field__label" for="barcode">Barcode / QR identifier</label>
                        <input class="input <?= isset($errors['barcode']) ? 'is-invalid' : '' ?>" type="text" id="barcode" name="barcode"
                               value="<?= e($value('barcode')) ?>" maxlength="100" placeholder="Optional, e.g. CB-BIO-001"
                               data-testid="equipment-barcode">
                        <?php if (isset($errors['barcode'])): ?><span class="field__error"><?= e($errors['barcode']) ?></span><?php else: ?><span class="field__hint">Optional. Must be unique if provided; useful for quick identification when scanning.</span><?php endif; ?>
                    </div>

                    <?php if ($isEdit): ?>
                        <div class="field">
                            <label class="field__label" for="record_status">Record status</label>
                            <select class="select" id="record_status" name="record_status" data-testid="equipment-status">
                                <option value="active" <?= selected($value('record_status', 'active'), 'active') ?>>Active (borrowable when available)</option>
                                <option value="archived" <?= selected($value('record_status', 'active'), 'archived') ?>>Archived (not borrowable)</option>
                            </select>
                            <span class="field__hint">Archiving is blocked while units are still borrowed.</span>
                        </div>
                    <?php endif; ?>

                    <div class="field field--full">
                        <label class="field__label" for="description">Description</label>
                        <textarea class="textarea <?= isset($errors['description']) ? 'is-invalid' : '' ?>" id="description" name="description" maxlength="2000"
                                  placeholder="Model, accessories, and handling notes…" data-testid="equipment-description"><?= e($value('description')) ?></textarea>
                        <?php if (isset($errors['description'])): ?><span class="field__error"><?= e($errors['description']) ?></span><?php endif; ?>
                    </div>
                </div>

                <div class="btn-row" style="margin-top:20px;">
                    <button type="submit" class="btn btn--primary" data-testid="equipment-submit">
                        <?= icon('check') ?> <?= $isEdit ? 'Save changes' : 'Create equipment record' ?>
                    </button>
                    <a class="btn btn--ghost" href="<?= e($isEdit ? url('equipment/' . (int) $equipment['id']) : url('equipment')) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </section>

    <aside>
        <section class="card reveal">
            <div class="card__head">
                <div><h2 class="card__title">Quantity rules</h2></div>
            </div>
            <div class="card__body">
                <div class="callout" style="display:block;">
                    <strong style="display:block;margin-bottom:6px;">Balanced at all times</strong>
                    Total quantity always equals available + borrowed + damaged + under repair + lost.
                    The total must always equal the sum of available, borrowed, damaged, in-repair, and lost units.
                </div>
                <div class="timeline" style="margin-top:16px;">
                    <div class="timeline__item">
                        <span class="timeline__dot timeline__dot--teal"></span>
                        <div class="timeline__title">New records</div>
                        <div class="timeline__meta">All units begin as available and in good condition.</div>
                    </div>
                    <div class="timeline__item">
                        <span class="timeline__dot"></span>
                        <div class="timeline__title">Increasing total</div>
                        <div class="timeline__meta">Added units go straight to available stock.</div>
                    </div>
                    <div class="timeline__item">
                        <span class="timeline__dot timeline__dot--amber"></span>
                        <div class="timeline__title">Decreasing total</div>
                        <div class="timeline__meta">Total cannot drop below units already borrowed, damaged, in repair, or lost.</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card reveal" style="margin-top:18px;">
            <div class="card__head"><div><h2 class="card__title">Condition changes</h2></div></div>
            <div class="card__body">
                <p class="field__hint" style="margin-bottom:10px;">After saving, use <strong>Adjust condition</strong> on the equipment page to move units to damaged, repair, or lost.</p>
                <div class="callout">
                    <?= icon('shield', 'icon icon--sm') ?>
                    <div>Every condition change is written to condition history and the audit trail.</div>
                </div>
            </div>
        </section>
    </aside>
</div>
