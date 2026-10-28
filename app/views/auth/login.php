<?php
$showDemo = (bool) config('app.show_demo_credentials', true);
?>
<div class="auth-shell">
    <button type="button" class="icon-btn theme-toggle auth-theme-toggle" data-theme-toggle
            aria-label="Switch to dark mode" aria-pressed="false" title="Switch to dark mode" data-testid="theme-toggle">
        <span class="theme-toggle__sun"><?= icon('sun', 'icon icon--sm') ?></span>
        <span class="theme-toggle__moon"><?= icon('moon', 'icon icon--sm') ?></span>
    </button>

    <section class="auth-hero" aria-label="About Code Blocks Laboratory Equipment Borrowing System">
        <img class="auth-hero__logo" src="<?= e(asset('assets/images/code-blocks-logo-light.svg')) ?>" alt="Code Blocks Laboratory Equipment Borrowing System">
        <div class="auth-hero__eyebrow"><?= icon('sparkles', 'icon icon--sm') ?> Laboratory Equipment Inventory &amp; Borrowing</div>
        <h1>Laboratory operations, <span>accurate and accountable.</span></h1>
        <p class="auth-hero__lead">
            Track every microscope, multimeter, and laptop from request to return.
            Code Blocks Laboratory Equipment Borrowing System keeps inventory accurate, approvals accountable, and equipment conditions documented.
        </p>

        <div class="auth-features">
            <div class="auth-feature">
                <span class="auth-feature__icon"><?= icon('boxes', 'icon icon--sm') ?></span>
                <div><strong>Live inventory truth</strong><span>Available, borrowed, damaged, and repair stock always reconcile.</span></div>
            </div>
            <div class="auth-feature">
                <span class="auth-feature__icon"><?= icon('shield', 'icon icon--sm') ?></span>
                <div><strong>Authorized approvals</strong><span>Only laboratory personnel can approve or release equipment.</span></div>
            </div>
            <div class="auth-feature">
                <span class="auth-feature__icon"><?= icon('rotate-ccw', 'icon icon--sm') ?></span>
                <div><strong>Condition-first returns</strong><span>Every return requires a condition report before stock moves.</span></div>
            </div>
        </div>

        <div class="auth-hero__foot">
            <span class="status-dot" aria-hidden="true"></span>
            Secure, accountable laboratory operations
        </div>
    </section>

    <section class="auth-panel">
        <div class="auth-panel__inner">
            <div class="auth-panel__logo">
                <span class="brand-logo-pair">
                    <img class="logo-on-light" src="<?= e(asset('assets/images/code-blocks-logo.svg')) ?>" alt="Code Blocks Laboratory Equipment Borrowing System">
                    <img class="logo-on-dark" src="<?= e(asset('assets/images/code-blocks-logo-light.svg')) ?>" alt="" aria-hidden="true">
                </span>
            </div>

            <h2>Welcome back</h2>
            <p class="auth-panel__sub">Sign in with your institutional email or school ID to continue.</p>

            <?php require BASE_PATH . '/app/views/partials/flash.php'; ?>

            <form class="auth-form" method="post" action="<?= e(url('login')) ?>" data-loading novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="login">Email or school / employee ID <span class="req">*</span></label>
                    <input class="input" type="text" id="login" name="login" value="<?= e(old('login')) ?>"
                           placeholder="name@institution.edu" autocomplete="username" required autofocus
                           data-testid="login-input" aria-required="true">
                </div>

                <div class="field">
                    <label class="field__label" for="password">Password <span class="req">*</span></label>
                    <div class="input-group">
                        <input class="input" type="password" id="password" name="password" placeholder="Enter your password"
                               autocomplete="current-password" required data-testid="password-input" aria-required="true">
                        <button type="button" class="input-group__btn" data-password-toggle="password" aria-label="Show password">
                            <?= icon('eye') ?>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn--primary btn--block" data-testid="login-submit">
                    <?= icon('lock') ?> Sign in securely
                </button>
            </form>

            <?php if ($showDemo): ?>
                <details class="demo-box">
                    <summary><?= icon('info', 'icon icon--sm') ?> Sample accounts for evaluation</summary>
                    <div class="demo-box__body">
                        <button type="button" class="demo-account" data-demo-login="admin@codeblocks.test" data-demo-password="Admin@12345">
                            <strong>Laboratory Personnel</strong><code>admin@codeblocks.test</code>
                        </button>
                        <button type="button" class="demo-account" data-demo-login="maria.santos@codeblocks.test" data-demo-password="Instructor@12345">
                            <strong>Instructor</strong><code>maria.santos@codeblocks.test</code>
                        </button>
                        <button type="button" class="demo-account" data-demo-login="ana.reyes@codeblocks.test" data-demo-password="Student@12345">
                            <strong>Student</strong><code>ana.reyes@codeblocks.test</code>
                        </button>
                        <p class="field__hint" style="margin-top:4px;">Sample credentials are provided for evaluation only. Replace them before live use.</p>
                    </div>
                </details>
            <?php endif; ?>

            <p class="auth-legal">
                Unauthorized access is prohibited. Login attempts are logged.<br>
                Need an account? Contact the Laboratory Management Office — public registration is disabled.
            </p>
            <p class="auth-copyright">© 2026 Arcolas, Castillo, Gamboa, Lugena, Matias. All rights reserved.</p>
        </div>
    </section>
</div>
