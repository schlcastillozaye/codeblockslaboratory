<?php
$messages = flash_messages();
if ($messages === []) {
    return;
}
$icons = ['success' => 'check-circle', 'error' => 'alert-triangle', 'warning' => 'alert-triangle', 'info' => 'info'];
$titles = ['success' => 'Success', 'error' => 'Action not completed', 'warning' => 'Attention', 'info' => 'Information'];
?>
<div class="flash-stack" aria-live="polite">
    <?php foreach ($messages as $message): ?>
        <?php $type = in_array($message['type'], ['success', 'error', 'warning', 'info'], true) ? $message['type'] : 'info'; ?>
        <div class="alert alert--<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>" data-testid="flash-<?= e($type) ?>">
            <?= icon($icons[$type], 'icon') ?>
            <div class="alert__content">
                <div class="alert__title"><?= e($titles[$type]) ?></div>
                <div><?= e($message['message']) ?></div>
            </div>
            <button type="button" class="alert__close" data-dismiss-alert aria-label="Dismiss message"><?= icon('x', 'icon icon--sm') ?></button>
        </div>
    <?php endforeach; ?>
</div>
