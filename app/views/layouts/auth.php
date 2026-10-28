<?php
/** @var string $content */
/** @var string|null $title */
$pageTitle = $title ?? 'Sign in';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#eef3fb">
    <meta name="description" content="Sign in to the Code Blocks Laboratory Equipment Borrowing System laboratory equipment inventory and borrowing system.">
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
<body class="auth-body">
<?= $content ?>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
