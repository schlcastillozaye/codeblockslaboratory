<?php
$detail = $message ?? 'Your account does not have permission to open this page or perform this action.';
?>
<div class="empty-state" style="padding:0;" data-testid="error-403">
    <div class="empty-state__icon" style="background:var(--red-bg);color:var(--red);"><?= icon('shield', 'icon icon--xl') ?></div>
    <div class="page-header__eyebrow" style="justify-content:center;margin-bottom:6px;">Restricted access</div>
    <h1 class="page-title" style="margin-bottom:8px;">Access denied</h1>
    <p style="max-width:480px;margin:0 auto 6px;color:var(--muted);"><?= e($detail) ?></p>
    <p class="field__hint" style="max-width:480px;margin:0 auto;">
        This area is limited to your assigned responsibilities. If you believe you should have access, contact the Laboratory Management Office.
    </p>
</div>
