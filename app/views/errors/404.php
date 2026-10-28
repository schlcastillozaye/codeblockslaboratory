<?php
$detail = $message ?? 'The page you are looking for does not exist or may have been moved.';
?>
<div class="empty-state" style="padding:0;" data-testid="error-404">
    <div class="empty-state__icon" style="background:var(--blue-bg);color:var(--royal-600);"><?= icon('search', 'icon icon--xl') ?></div>
    <div class="page-header__eyebrow" style="justify-content:center;margin-bottom:6px;">Record unavailable</div>
    <h1 class="page-title" style="margin-bottom:8px;">Page not found</h1>
    <p style="max-width:480px;margin:0 auto 6px;color:var(--muted);"><?= e($detail) ?></p>
    <p class="field__hint" style="max-width:480px;margin:0 auto;">
        Check the address or return to your dashboard. If you followed a link inside the system, the record may have been removed.
    </p>
</div>
