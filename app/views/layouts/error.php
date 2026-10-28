<?php
/** @var string $content */
/** @var string|null $title */
$pageTitle = $title ?? 'Error';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#eef3fb">
    <title><?= e($pageTitle) ?> · Code Blocks Laboratory Equipment Borrowing System</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <script>
      (function () {
        var t = null;
        try { t = localStorage.getItem('codeblocks-theme'); } catch (e) {}
        if (!t) t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', t);
        document.documentElement.style.colorScheme = t;
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.setAttribute('content', t === 'dark' ? '#06101f' : '#eef3fb');
      })();
    </script>
</head>
<body class="app-body" style="background: var(--canvas);">
<button type="button" class="icon-btn theme-toggle auth-theme-toggle" data-theme-toggle
        aria-label="Switch to dark mode" aria-pressed="false" title="Switch to dark mode" data-testid="theme-toggle">
    <span class="theme-toggle__sun"><?= icon('sun', 'icon icon--sm') ?></span>
    <span class="theme-toggle__moon"><?= icon('moon', 'icon icon--sm') ?></span>
</button>
<main class="page" style="max-width: 760px; padding-top: 8vh;">
    <div class="card" style="text-align:center; padding: 40px 24px;">
        <span class="brand-logo-pair" style="margin: 0 auto 26px; display: flex; justify-content: center;">
            <img class="logo-on-light" src="<?= e(asset('assets/images/code-blocks-logo.svg')) ?>" alt="Code Blocks Laboratory Equipment Borrowing System" style="width: 210px;">
            <img class="logo-on-dark" src="<?= e(asset('assets/images/code-blocks-logo-light.svg')) ?>" alt="" aria-hidden="true" style="width: 210px;">
        </span>
        <?= $content ?>
        <div class="btn-row" style="justify-content:center; margin-top: 26px;">
            <a class="btn btn--ghost" href="<?= e(url('dashboard')) ?>"><?= icon('dashboard') ?> Dashboard</a>
            <?php if (App\Core\Auth::check()): ?>
                <form method="post" action="<?= e(url('logout')) ?>" data-loading>
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--primary" data-testid="logout-button"><?= icon('logout') ?> Sign out</button>
                </form>
            <?php else: ?>
                <a class="btn btn--primary" href="<?= e(url('login')) ?>"><?= icon('lock') ?> Sign in</a>
            <?php endif; ?>
        </div>
    </div>
</main>
<footer class="app-footer error-footer">© 2026 Arcolas, Castillo, Gamboa, Lugena, Matias. All rights reserved.</footer>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
