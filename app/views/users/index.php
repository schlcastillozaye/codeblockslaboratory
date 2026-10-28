<?php
$queryBase = array_filter([
    'q' => $filters['q'] !== '' ? $filters['q'] : null,
    'role' => $filters['role'] !== '' ? $filters['role'] : null,
    'status' => $filters['status'] !== '' ? $filters['status'] : null,
], static fn ($v) => $v !== null);
$roles = [
    '' => 'All roles',
    'admin' => 'Laboratory Personnel',
    'instructor' => 'Instructor',
    'student' => 'Student',
];
?>
<div class="page-header reveal">
    <div>
        <div class="page-header__eyebrow"><?= icon('users', 'icon icon--sm') ?> Administration</div>
        <h1 class="page-title">User accounts</h1>
        <p class="page-subtitle">
            Public registration is disabled. Laboratory personnel create accounts and assign the role that controls
            access to approvals, inventory, reports, and the audit trail.
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn--primary" href="<?= e(url('users/create')) ?>" data-testid="add-user-button"><?= icon('plus') ?> Add user</a>
    </div>
</div>

<div class="metric-strip reveal">
    <div class="metric-strip__item">
        <div class="metric-strip__label">Laboratory personnel</div>
        <div class="metric-strip__value"><?= (int) ($roleCounts['admin'] ?? 0) ?></div>
    </div>
    <div class="metric-strip__item">
        <div class="metric-strip__label">Instructors</div>
        <div class="metric-strip__value"><?= (int) ($roleCounts['instructor'] ?? 0) ?></div>
    </div>
    <div class="metric-strip__item">
        <div class="metric-strip__label">Students</div>
        <div class="metric-strip__value"><?= (int) ($roleCounts['student'] ?? 0) ?></div>
    </div>
    <div class="metric-strip__item">
        <div class="metric-strip__label">Matching accounts</div>
        <div class="metric-strip__value"><?= (int) $paginator->total ?></div>
    </div>
</div>

<form class="toolbar reveal" method="get" action="<?= e(url('users')) ?>" data-testid="user-filters">
    <div class="field field--grow">
        <label class="field__label" for="user-search">Search accounts</label>
        <div class="input-icon">
            <?= icon('search') ?>
            <input class="input" type="search" id="user-search" name="q" value="<?= e($filters['q']) ?>"
                   placeholder="Name, email, or school / employee ID…" data-testid="user-search">
        </div>
    </div>
    <div class="field field--sm">
        <label class="field__label" for="user-role">Role</label>
        <select class="select" id="user-role" name="role" data-testid="user-role-filter">
            <?php foreach ($roles as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= selected($filters['role'], $value) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field field--sm">
        <label class="field__label" for="user-status">Status</label>
        <select class="select" id="user-status" name="status">
            <option value="">Any status</option>
            <option value="active" <?= selected($filters['status'], 'active') ?>>Active</option>
            <option value="inactive" <?= selected($filters['status'], 'inactive') ?>>Inactive</option>
        </select>
    </div>
    <div class="toolbar__actions">
        <button type="submit" class="btn btn--secondary"><?= icon('filter') ?> Filter</button>
        <?php if ($queryBase !== []): ?><a class="btn btn--ghost" href="<?= e(url('users')) ?>">Reset</a><?php endif; ?>
    </div>
</form>

<section class="card reveal">
    <div class="table-wrap">
        <table class="table table-responsive-cards" data-testid="users-table">
            <thead>
                <tr>
                    <th>Account</th>
                    <th>School / employee ID</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Last sign-in</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($paginator->items === []): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state" style="padding:32px;">
                                <div class="empty-state__icon"><?= icon('users', 'icon icon--xl') ?></div>
                                <h3>No accounts found</h3>
                                <p>No user account matches the current filters.</p>
                                <a class="btn btn--primary" href="<?= e(url('users/create')) ?>">Add a user account</a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($paginator->items as $account): ?>
                        <?php $isSelf = (int) $account['id'] === (int) ($currentUser['id'] ?? 0); ?>
                        <tr data-search="<?= e($account['first_name'] . ' ' . $account['last_name'] . ' ' . $account['email'] . ' ' . $account['school_id'] . ' ' . $account['role'] . ' ' . ($account['department'] ?? '')) ?>">
                            <td data-label="Account">
                                <div class="cell-person">
                                    <span class="avatar avatar--sm"><?= e(initials($account['first_name'], $account['last_name'])) ?></span>
                                    <span>
                                        <span class="cell-main">
                                            <?= e($account['first_name'] . ' ' . $account['last_name']) ?>
                                            <?php if ($isSelf): ?><span class="badge badge--info" style="margin-left:5px;">You</span><?php endif; ?>
                                        </span>
                                        <span class="cell-sub"><?= e($account['email']) ?></span>
                                    </span>
                                </div>
                            </td>
                            <td data-label="School / employee ID"><span class="cell-main" style="font-family:var(--mono);font-size:11.5px;"><?= e($account['school_id']) ?></span></td>
                            <td data-label="Role">
                                <span class="badge badge--<?= $account['role'] === 'admin' ? 'primary' : ($account['role'] === 'instructor' ? 'info' : 'muted') ?>">
                                    <?= e(role_short_label($account['role'])) ?>
                                </span>
                            </td>
                            <td data-label="Department"><span class="cell-sub"><?= e($account['department'] ?: '—') ?></span></td>
                            <td data-label="Status"><?= status_badge($account['status'] === 'active' ? 'active_record' : 'inactive', $account['status'] === 'active' ? 'Active' : 'Inactive') ?></td>
                            <td data-label="Last sign-in" class="nowrap">
                                <span class="cell-sub"><?= $account['last_login_at'] ? e(time_ago($account['last_login_at'])) : 'Never' ?></span>
                            </td>
                            <td data-label="Action">
                                <div class="cell-actions">
                                    <a class="btn btn--sm btn--ghost" href="<?= e(url('users/' . (int) $account['id'] . '/edit')) ?>"
                                       data-testid="edit-user-<?= (int) $account['id'] ?>"><?= icon('edit', 'icon icon--xs') ?> Edit</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?= pagination($paginator, 'users', $queryBase) ?>

<div class="callout reveal" style="margin-top:18px;">
    <?= icon('shield', 'icon icon--sm') ?>
    <div>
        The system always keeps at least one active administrator. Passwords are stored securely using one-way
        hashing, and every account change is recorded in the audit trail.
    </div>
</div>
