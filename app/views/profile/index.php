<?php
$errors = form_errors();
$value = static function (string $field, mixed $default = '') use ($user) {
    return old($field, $user[$field] ?? $default);
};
$displayName = $user['first_name'] . ' ' . $user['last_name'];
?>
<div class="page-header reveal">
    <div>
        <div class="page-header__eyebrow"><?= icon('user', 'icon icon--sm') ?> Account settings</div>
        <h1 class="page-title">My profile</h1>
        <p class="page-subtitle">Keep your contact details current. Your role and school ID are managed by laboratory personnel.</p>
    </div>
    <div class="page-actions">
        <?= status_badge($user['status'] === 'active' ? 'active_record' : 'inactive', $user['status'] === 'active' ? 'Active account' : 'Inactive account') ?>
    </div>
</div>

<div class="grid grid--main">
    <div>
        <section class="card reveal">
            <div class="card__head card__head--line">
                <div>
                    <h2 class="card__title">Profile information</h2>
                    <p class="card__subtitle">Changes are checked and recorded in the audit trail.</p>
                </div>
            </div>
            <div class="card__body" style="padding-top:18px;">
                <form method="post" action="<?= e(url('profile')) ?>" data-loading data-testid="profile-form" novalidate>
                    <?= csrf_field() ?>
                    <div class="form-grid">
                        <div class="field">
                            <label class="field__label" for="first_name">First name <span class="req">*</span></label>
                            <input class="input <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>" type="text" id="first_name" name="first_name"
                                   value="<?= e($value('first_name')) ?>" maxlength="80" required data-testid="profile-first-name" aria-required="true">
                            <?php if (isset($errors['first_name'])): ?><span class="field__error"><?= e($errors['first_name']) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label class="field__label" for="last_name">Last name <span class="req">*</span></label>
                            <input class="input <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>" type="text" id="last_name" name="last_name"
                                   value="<?= e($value('last_name')) ?>" maxlength="80" required data-testid="profile-last-name" aria-required="true">
                            <?php if (isset($errors['last_name'])): ?><span class="field__error"><?= e($errors['last_name']) ?></span><?php endif; ?>
                        </div>
                        <div class="field field--full">
                            <label class="field__label" for="email">Email address <span class="req">*</span></label>
                            <input class="input <?= isset($errors['email']) ? 'is-invalid' : '' ?>" type="email" id="email" name="email"
                                   value="<?= e($value('email')) ?>" maxlength="150" required data-testid="profile-email" aria-required="true">
                            <?php if (isset($errors['email'])): ?><span class="field__error"><?= e($errors['email']) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label class="field__label" for="department">Department</label>
                            <input class="input <?= isset($errors['department']) ? 'is-invalid' : '' ?>" type="text" id="department" name="department"
                                   value="<?= e($value('department')) ?>" maxlength="120" data-testid="profile-department">
                            <?php if (isset($errors['department'])): ?><span class="field__error"><?= e($errors['department']) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label class="field__label" for="phone">Phone</label>
                            <input class="input <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" type="tel" id="phone" name="phone"
                                   value="<?= e($value('phone')) ?>" maxlength="30" data-testid="profile-phone">
                            <?php if (isset($errors['phone'])): ?><span class="field__error"><?= e($errors['phone']) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label class="field__label" for="school_id_readonly">School / employee ID</label>
                            <input class="input" type="text" id="school_id_readonly" value="<?= e($user['school_id']) ?>" disabled>
                            <span class="field__hint">Managed by laboratory personnel; used to sign in.</span>
                        </div>
                        <div class="field">
                            <label class="field__label" for="role_readonly">Role</label>
                            <input class="input" type="text" id="role_readonly" value="<?= e(role_label($user['role'])) ?>" disabled>
                            <span class="field__hint">Role changes require an administrator.</span>
                        </div>
                    </div>
                    <div class="btn-row" style="margin-top:20px;">
                        <button type="submit" class="btn btn--primary" data-testid="profile-save"><?= icon('check') ?> Save profile</button>
                        <a class="btn btn--ghost" href="<?= e(url('dashboard')) ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </section>

        <section class="card reveal" style="margin-top:18px;">
            <div class="card__head card__head--line">
                <div>
                    <h2 class="card__title">Change password</h2>
                    <p class="card__subtitle">For your security, your session is renewed after a password change.</p>
                </div>
            </div>
            <div class="card__body" style="padding-top:18px;">
                <form method="post" action="<?= e(url('profile/password')) ?>" data-loading data-testid="password-form" novalidate>
                    <?= csrf_field() ?>
                    <div class="form-grid">
                        <div class="field field--full">
                            <label class="field__label" for="current_password">Current password <span class="req">*</span></label>
                            <div class="input-group">
                                <input class="input <?= isset($errors['current_password']) ? 'is-invalid' : '' ?>" type="password" id="current_password" name="current_password"
                                       autocomplete="current-password" required data-testid="current-password" aria-required="true">
                                <button type="button" class="input-group__btn" data-password-toggle="current_password" aria-label="Show password"><?= icon('eye') ?></button>
                            </div>
                            <?php if (isset($errors['current_password'])): ?><span class="field__error"><?= e($errors['current_password']) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label class="field__label" for="password">New password <span class="req">*</span></label>
                            <div class="input-group">
                                <input class="input <?= isset($errors['password']) ? 'is-invalid' : '' ?>" type="password" id="password" name="password"
                                       autocomplete="new-password" required data-testid="new-password" aria-describedby="password-rules" aria-required="true">
                                <button type="button" class="input-group__btn" data-password-toggle="password" aria-label="Show password"><?= icon('eye') ?></button>
                            </div>
                            <span class="field__hint" id="password-rules">At least 8 characters with uppercase, lowercase, number, and symbol.</span>
                            <?php if (isset($errors['password'])): ?><span class="field__error"><?= e($errors['password']) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label class="field__label" for="password_confirm">Confirm new password <span class="req">*</span></label>
                            <div class="input-group">
                                <input class="input <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" type="password" id="password_confirm" name="password_confirm"
                                       autocomplete="new-password" required data-testid="confirm-password" aria-required="true">
                                <button type="button" class="input-group__btn" data-password-toggle="password_confirm" aria-label="Show password"><?= icon('eye') ?></button>
                            </div>
                            <?php if (isset($errors['password_confirm'])): ?><span class="field__error"><?= e($errors['password_confirm']) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div class="btn-row" style="margin-top:20px;">
                        <button type="submit" class="btn btn--primary" data-testid="password-save"><?= icon('lock') ?> Update password</button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <aside>
        <section class="card reveal">
            <div class="card__head"><div><h2 class="card__title">Account summary</h2></div></div>
            <div class="card__body">
                <div style="display:flex;gap:13px;align-items:center;margin-bottom:15px;">
                    <span class="avatar avatar--lg"><?= e(initials($user['first_name'], $user['last_name'])) ?></span>
                    <div>
                        <div style="font-weight:700;font-size:15px;"><?= e($displayName) ?></div>
                        <div class="field__hint"><?= e(role_label($user['role'])) ?></div>
                    </div>
                </div>
                <dl class="definition-list">
                    <div><dt>Email</dt><dd style="font-size:11.5px;"><?= e($user['email']) ?></dd></div>
                    <div><dt>School / employee ID</dt><dd><?= e($user['school_id']) ?></dd></div>
                    <div><dt>Department</dt><dd><?= e($user['department'] ?: '—') ?></dd></div>
                    <div><dt>Phone</dt><dd><?= e($user['phone'] ?: '—') ?></dd></div>
                    <div><dt>Last sign-in</dt><dd><?= $user['last_login_at'] ? e(format_datetime($user['last_login_at'])) : 'Never' ?></dd></div>
                    <div><dt>Member since</dt><dd><?= e(format_date($user['created_at'])) ?></dd></div>
                </dl>
            </div>
        </section>

        <div class="callout reveal" style="margin-top:18px;">
            <?= icon('shield', 'icon icon--sm') ?>
            <div>Never share your password. All sessions are tied to your account, and role changes are controlled by laboratory personnel.</div>
        </div>
    </aside>
</div>
