<?php
/** @var string $content */
/** @var string|null $title */
/** @var string|null $bodyClass */
$pageTitle = $title ?? 'Dashboard';
$role = $currentUser['role'] ?? null;
$displayName = $currentUser ? $currentUser['first_name'] . ' ' . $currentUser['last_name'] : 'Guest';
$bodyClass = $bodyClass ?? 'app-body';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#eef3fb">
    <meta name="description" content="Code Blocks Laboratory Equipment Borrowing System laboratory equipment inventory and borrowing management system.">
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
<body class="<?= e($bodyClass) ?>" data-role="<?= e($role ?? 'guest') ?>">
<a class="skip-link" href="#main-content">Skip to main content</a>

<div class="app-shell">
    <?php require BASE_PATH . '/app/views/partials/sidebar.php'; ?>
    <div class="sidebar-overlay" data-sidebar-close></div>

    <div class="app-main">
        <?php require BASE_PATH . '/app/views/partials/topbar.php'; ?>

        <main id="main-content" class="page" tabindex="-1">
            <?php require BASE_PATH . '/app/views/partials/flash.php'; ?>
            <?= $content ?>
        </main>

        <footer class="app-footer">
            <span class="brand-logo-pair">
                <img class="logo-on-light" src="<?= e(asset('assets/images/code-blocks-logo.svg')) ?>" alt="Code Blocks Laboratory Equipment Borrowing System">
                <img class="logo-on-dark" src="<?= e(asset('assets/images/code-blocks-logo-light.svg')) ?>" alt="" aria-hidden="true">
            </span>
            <span>© 2026 Arcolas, Castillo, Gamboa, Lugena, Matias. All rights reserved.</span>
            <span class="app-footer__right">
                <span>Secure laboratory operations</span>
                <span>Notifications delivered in-app</span>
            </span>
        </footer>
    </div>
</div>

<div class="toast-stack" id="toast-stack" aria-live="polite" aria-atomic="true"></div>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
