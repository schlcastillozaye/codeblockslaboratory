<?php
$queryBase = array_filter([
    'q' => $filters['q'] !== '' ? $filters['q'] : null,
    'action' => $filters['action'] !== '' ? $filters['action'] : null,
    'user_id' => $filters['user_id'] ?: null,
    'from' => $filters['from'] !== '' ? $filters['from'] : null,
    'to' => $filters['to'] !== '' ? $filters['to'] : null,
], static fn ($v) => $v !== null);
$decode = static function (?string $json): ?array {
    if ($json === null || $json === '') {
        return null;
    }
    $value = json_decode($json, true);
    return is_array($value) ? $value : null;
};
?>
<div class="page-header reveal">
    <div>
        <div class="page-header__eyebrow"><?= icon('shield', 'icon icon--sm') ?> Accountability</div>
        <h1 class="page-title">Audit trail</h1>
        <p class="page-subtitle">
            A read-only record of security-relevant and inventory-changing actions. Entries include the actor,
            IP address, affected entity, and before/after values where applicable.
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn--ghost" href="<?= e(url('reports')) ?>"><?= icon('chart') ?> Reports</a>
    </div>
</div>

<form class="toolbar reveal" method="get" action="<?= e(url('audit')) ?>" data-testid="audit-filters">
    <div class="field field--grow">
        <label class="field__label" for="audit-search">Search descriptions</label>
        <div class="input-icon">
            <?= icon('search') ?>
            <input class="input" type="search" id="audit-search" name="q" value="<?= e($filters['q']) ?>"
                   placeholder="Description, action, or actor name…" data-testid="audit-search">
        </div>
    </div>
    <div class="field field--sm">
        <label class="field__label" for="audit-action">Action</label>
        <select class="select" id="audit-action" name="action" data-testid="audit-action-filter">
            <option value="">All actions</option>
            <?php foreach ($actions as $action): ?>
                <option value="<?= e($action) ?>" <?= selected($filters['action'], $action) ?>><?= e($action) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field field--sm">
        <label class="field__label" for="audit-user">Actor</label>
        <select class="select" id="audit-user" name="user_id">
            <option value="">All users</option>
            <?php foreach ($users as $account): ?>
                <option value="<?= (int) $account['id'] ?>" <?= selected($filters['user_id'], $account['id']) ?>>
                    <?= e($account['last_name'] . ', ' . $account['first_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field field--sm">
        <label class="field__label" for="audit-from">From</label>
        <input class="input" type="date" id="audit-from" name="from" value="<?= e($filters['from']) ?>">
    </div>
    <div class="field field--sm">
        <label class="field__label" for="audit-to">To</label>
        <input class="input" type="date" id="audit-to" name="to" value="<?= e($filters['to']) ?>">
    </div>
    <div class="toolbar__actions">
        <button type="submit" class="btn btn--secondary"><?= icon('filter') ?> Filter</button>
        <?php if ($queryBase !== []): ?><a class="btn btn--ghost" href="<?= e(url('audit')) ?>">Reset</a><?php endif; ?>
    </div>
</form>

<section class="card reveal">
    <div class="table-wrap">
        <table class="table table-responsive-cards" data-testid="audit-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>Entity</th>
                    <th>Source</th>
                    <th>Changes</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($paginator->items === []): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state" style="padding:32px;">
                                <div class="empty-state__icon"><?= icon('shield', 'icon icon--xl') ?></div>
                                <h3>No audit entries found</h3>
                                <p>No audit record matches the current filters.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($paginator->items as $log): ?>
                        <?php
                        $actor = trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''));
                        $oldValues = $decode($log['old_values'] ?? null);
                        $newValues = $decode($log['new_values'] ?? null);
                        $searchText = ($log['action'] ?? '') . ' ' . ($log['description'] ?? '') . ' ' . $actor . ' Authorized client';
                        ?>
                        <tr data-search="<?= e($searchText) ?>">
                            <td data-label="When" class="nowrap">
                                <span class="cell-main"><?= e(format_datetime($log['created_at'])) ?></span>
                                <span class="cell-sub"><?= e(time_ago($log['created_at'])) ?></span>
                            </td>
                            <td data-label="Actor">
                                <span class="cell-main"><?= e($actor !== '' ? $actor : 'System') ?></span>
                                <span class="cell-sub"><?= e($log['role'] ? role_short_label($log['role']) : 'Automated / guest') ?></span>
                            </td>
                            <td data-label="Action">
                                <span class="badge badge--muted" style="font-family:var(--mono);font-size:10px;"><?= e($log['action']) ?></span>
                            </td>
                            <td data-label="Description">
                                <span style="font-size:12.5px;color:var(--ink-soft);"><?= e($log['description']) ?></span>
                            </td>
                            <td data-label="Entity">
                                <span class="cell-sub">
                                    <?= e($log['entity_type']) ?><?= $log['entity_id'] !== null ? ' #' . (int) $log['entity_id'] : '' ?>
                                </span>
                            </td>
                            <td data-label="Source">
                                <span class="cell-sub">Authorized client</span>
                            </td>
                            <td data-label="Changes">
                                <?php if ($oldValues !== null || $newValues !== null): ?>
                                    <details class="json-details">
                                        <summary>View values</summary>
                                        <?php if ($oldValues !== null): ?>
                                            <div class="field__hint" style="margin-top:5px;">Before</div>
                                            <pre><?= e(json_encode($oldValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
                                        <?php endif; ?>
                                        <?php if ($newValues !== null): ?>
                                            <div class="field__hint" style="margin-top:5px;">After</div>
                                            <pre><?= e(json_encode($newValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
                                        <?php endif; ?>
                                    </details>
                                <?php else: ?>
                                    <span class="cell-sub">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?= pagination($paginator, 'audit', $queryBase) ?>

<div class="callout reveal" style="margin-top:18px;">
    <?= icon('lock', 'icon icon--sm') ?>
    <div>Audit entries are recorded automatically and can never be edited from the interface. Only authorized administrators can review them.</div>
</div>
