<?php
$isEdit = $user !== null;
$action = $isEdit ? url('users/' . (int) $user['id']) : url('users');
$errors = form_errors();
$value = static function (string $field, mixed $default = '') use ($user) {
    return old($field, $user[$field] ?? $default);
};
$isSelf = $isEdit && (int) $user['id'] === (int) ($currentUser['id'] ?? 0);
?>
<div class="page-header reveal">
    <div>
        <div class="page-header__eyebrow"><a href="<?= e(url('users')) ?>" style="color:inherit;">User accounts</a> / <?= $isEdit ? 'Edit' : 'Create' ?></div>
        <h1 class="page-title"><?= $isEdit ? 'Edit user account' : 'Add a user account' ?></h1>
        <p class="page-subtitle">
            <?= $isEdit
                ? 'Update account details, role, or status. Leave the password fields blank to keep the current password.'
                : 'Create an account for a student, instructor, or fellow laboratory staff member. Share the initial password through a secure channel.' ?>
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn--ghost" href="<?= e(url('users')) ?>"><?= icon('arrow-left') ?> Back to accounts</a>
    </div>
</div>

<div class="grid grid--sidebar">
    <section class="card reveal">
        <div class="card__head card__head--line">
            <div>
                <h2 class="card__title">Account information</h2>
                <p class="card__subtitle">Every field is checked before the account is saved.</p>
            </div>
            <?php if ($isEdit): ?><?= status_badge($user['status'] === 'active' ? 'active_record' : 'inactive', $user['status'] === 'active' ? 'Active' : 'Inactive') ?><?php endif; ?>
        </div>
        <div class="card__body" style="padding-top:18px;">
            <form method="post" action="<?= e($action) ?>" data-loading data-testid="user-form" novalidate>
                <?= csrf_field() ?>

                <div class="form-grid">
                    <div class="field">
                        <label class="field__label" for="first_name">First name <span class="req">*</span></label>
                        <input class="input <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>" type="text" id="first_name" name="first_name"
                               value="<?= e($value('first_name')) ?>" maxlength="80" required data-testid="user-first-name" aria-required="true">
                        <?php if (isset($errors['first_name'])): ?><span class="field__error"><?= e($errors['first_name']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="field__label" for="last_name">Last name <span class="req">*</span></label>
                        <input class="input <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>" type="text" id="last_name" name="last_name"
                               value="<?= e($value('last_name')) ?>" maxlength="80" required data-testid="user-last-name" aria-required="true">
                        <?php if (isset($errors['last_name'])): ?><span class="field__error"><?= e($errors['last_name']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="field__label" for="school_id">School / employee ID <span class="req">*</span></label>
                        <input class="input <?= isset($errors['school_id']) ? 'is-invalid' : '' ?>" type="text" id="school_id" name="school_id"
                               value="<?= e($value('school_id')) ?>" maxlength="30" required data-testid="user-school-id" aria-required="true">
                        <span class="field__hint">Unique identifier used to sign in. Letters, numbers, and dashes (3–30 chars).</span>
                        <?php if (isset($errors['school_id'])): ?><span class="field__error"><?= e($errors['school_id']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="field__label" for="email">Email address <span class="req">*</span></label>
                        <input class="input <?= isset($errors['email']) ? 'is-invalid' : '' ?>" type="email" id="email" name="email"
                               value="<?= e($value('email')) ?>" maxlength="150" required data-testid="user-email" aria-required="true">
                        <?php if (isset($errors['email'])): ?><span class="field__error"><?= e($errors['email']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="field__label" for="role">Role <span class="req">*</span></label>
                        <select class="select <?= isset($errors['role']) ? 'is-invalid' : '' ?>" id="role" name="role" required
                                data-testid="user-role" aria-required="true" <?= $isSelf ? 'disabled' : '' ?>>
                            <option value="student" <?= selected($value('role', 'student'), 'student') ?>>Student — request equipment</option>
                            <option value="instructor" <?= selected($value('role', 'student'), 'instructor') ?>>Instructor — request equipment</option>
                            <option value="admin" <?= selected($value('role', 'student'), 'admin') ?>>Laboratory Personnel — manage system</option>
                        </select>
                        <?php if ($isSelf): ?>
                            <input type="hidden" name="role" value="<?= e($value('role', 'admin')) ?>">
                            <span class="field__hint">You cannot demote your own administrator account.</span>
                        <?php endif; ?>
                        <?php if (isset($errors['role'])): ?><span class="field__error"><?= e($errors['role']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="field__label" for="status">Account status <span class="req">*</span></label>
                        <select class="select <?= isset($errors['status']) ? 'is-invalid' : '' ?>" id="status" name="status" required
                                data-testid="user-status" aria-required="true" <?= $isSelf ? 'disabled' : '' ?>>
                            <option value="active" <?= selected($value('status', 'active'), 'active') ?>>Active — can sign in</option>
                            <option value="inactive" <?= selected($value('status', 'active'), 'inactive') ?>>Inactive — sign-in blocked</option>
                        </select>
                        <?php if ($isSelf): ?>
                            <input type="hidden" name="status" value="<?= e($value('status', 'active')) ?>">
                            <span class="field__hint">You cannot deactivate your own account.</span>
                        <?php endif; ?>
                        <?php if (isset($errors['status'])): ?><span class="field__error"><?= e($errors['status']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="field__label" for="department">Department</label>
                        <input class="input <?= isset($errors['department']) ? 'is-invalid' : '' ?>" type="text" id="department" name="department"
                               value="<?= e($value('department')) ?>" maxlength="120" placeholder="e.g. College of Computer Studies"
                               data-testid="user-department">
                        <?php if (isset($errors['department'])): ?><span class="field__error"><?= e($errors['department']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="field__label" for="phone">Phone</label>
                        <input class="input <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" type="tel" id="phone" name="phone"
                               value="<?= e($value('phone')) ?>" maxlength="30" placeholder="Optional contact number"
                               data-testid="user-phone">
                        <?php if (isset($errors['phone'])): ?><span class="field__error"><?= e($errors['phone']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="field__label" for="password"><?= $isEdit ? 'New password' : 'Initial password' ?> <?= $isEdit ? '' : '<span class="req">*</span>' ?></label>
                        <div class="input-group">
                            <input class="input <?= isset($errors['password']) ? 'is-invalid' : '' ?>" type="password" id="password" name="password"
                                   autocomplete="new-password" <?= $isEdit ? '' : 'required' ?> data-testid="user-password"
                                   aria-describedby="password-help" aria-required="<?= $isEdit ? 'false' : 'true' ?>">
                            <button type="button" class="input-group__btn" data-password-toggle="password" aria-label="Show password"><?= icon('eye') ?></button>
                        </div>
                        <span class="field__hint" id="password-help">
                            <?= $isEdit ? 'Leave blank to keep the current password.' : 'At least 8 characters with uppercase, lowercase, number, and symbol.' ?>
                        </span>
                        <?php if (isset($errors['password'])): ?><span class="field__error"><?= e($errors['password']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="field__label" for="password_confirm">Confirm password <?= $isEdit ? '' : '<span class="req">*</span>' ?></label>
                        <div class="input-group">
                            <input class="input <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" type="password" id="password_confirm" name="password_confirm"
                                   autocomplete="new-password" <?= $isEdit ? '' : 'required' ?> data-testid="user-password-confirm">
                            <button type="button" class="input-group__btn" data-password-toggle="password_confirm" aria-label="Show password"><?= icon('eye') ?></button>
                        </div>
                        <?php if (isset($errors['password_confirm'])): ?><span class="field__error"><?= e($errors['password_confirm']) ?></span><?php endif; ?>
                    </div>
                </div>

                <div class="btn-row" style="margin-top:20px;">
                    <button type="submit" class="btn btn--primary" data-testid="user-submit">
                        <?= icon('check') ?> <?= $isEdit ? 'Save account changes' : 'Create user account' ?>
                    </button>
                    <a class="btn btn--ghost" href="<?= e(url('users')) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </section>

    <aside>
        <section class="card reveal">
            <div class="card__head">
                <div><h2 class="card__title">Role permissions</h2><p class="card__subtitle">Applied to every account</p></div>
            </div>
            <div class="card__body">
                <div class="timeline">
                    <div class="timeline__item">
                        <span class="timeline__dot timeline__dot--teal"></span>
                        <div class="timeline__title">Laboratory Personnel</div>
                        <div class="timeline__meta">Approve requests, manage equipment and users, process returns, view reports and audit trail.</div>
                    </div>
                    <div class="timeline__item">
                        <span class="timeline__dot"></span>
                        <div class="timeline__title">Instructor &amp; Student</div>
                        <div class="timeline__meta">Browse the catalog, submit borrowing requests, cancel their own pending requests, and view their own history.</div>
                    </div>
                    <div class="timeline__item">
                        <span class="timeline__dot timeline__dot--amber"></span>
                        <div class="timeline__title">No self-service registration</div>
                        <div class="timeline__meta">Only an active administrator can create accounts or change roles.</div>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($isEdit): ?>
            <section class="card reveal" style="margin-top:18px;">
                <div class="card__head"><div><h2 class="card__title">Account details</h2></div></div>
                <div class="card__body">
                    <dl class="definition-list">
                        <div><dt>Created</dt><dd><?= e(format_datetime($user['created_at'])) ?></dd></div>
                        <div><dt>Last updated</dt><dd><?= e(format_datetime($user['updated_at'])) ?></dd></div>
                        <div><dt>Last sign-in</dt><dd><?= $user['last_login_at'] ? e(format_datetime($user['last_login_at'])) : 'Never' ?></dd></div>
                    </dl>
                </div>
            </section>
        <?php endif; ?>

        <div class="callout reveal" style="margin-top:18px;">
            <?= icon('lock', 'icon icon--sm') ?>
            <div>Passwords are never displayed. Resetting a password does not reveal the previous one.</div>
        </div>
    </aside>
</div>
