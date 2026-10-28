<?php
$role = $currentUser['role'] ?? null;
$pendingApprovals = $role === 'admin' ? App\Models\BorrowRequest::pendingCount() : 0;
$openStats = App\Models\BorrowTransaction::stats($role === 'admin' ? null : (int) ($currentUser['id'] ?? 0));
$overdueCount = (int) ($openStats['overdue_total'] ?? 0);
$active = $activeNav ?? '';
?>
<aside class="sidebar" id="app-sidebar" aria-label="Main navigation">
    <div class="sidebar__brand">
        <a href="<?= e(url('dashboard')) ?>" aria-label="Code Blocks Laboratory Equipment Borrowing System dashboard">
            <span class="brand-logo-pair">
                <img class="logo-on-light" src="<?= e(asset('assets/images/code-blocks-logo.svg')) ?>" alt="Code Blocks Laboratory Equipment Borrowing System">
                <img class="logo-on-dark" src="<?= e(asset('assets/images/code-blocks-logo-light.svg')) ?>" alt="" aria-hidden="true">
            </span>
        </a>
        <button type="button" class="sidebar__close" data-sidebar-close aria-label="Close navigation">
            <?= icon('x') ?>
        </button>
    </div>

    <div class="sidebar__scroll">
        <nav>
            <div class="nav-group">
                <div class="nav-group__label">Overview</div>
                <a class="nav-link <?= $active === 'dashboard' ? 'is-active' : '' ?>" href="<?= e(url('dashboard')) ?>" data-testid="nav-dashboard">
                    <?= icon('dashboard') ?><span>Dashboard</span>
                </a>
                <a class="nav-link <?= $active === 'equipment' ? 'is-active' : '' ?>" href="<?= e(url('equipment')) ?>" data-testid="nav-equipment">
                    <?= icon('box') ?><span>Equipment Catalog</span>
                </a>
            </div>

            <?php if ($role === 'admin'): ?>
                <div class="nav-group">
                    <div class="nav-group__label">Laboratory Operations</div>
                    <a class="nav-link <?= $active === 'approvals' ? 'is-active' : '' ?>" href="<?= e(url('approvals')) ?>" data-testid="nav-approvals">
                        <?= icon('clipboard') ?><span>Pending Approvals</span>
                        <?php if ($pendingApprovals > 0): ?>
                            <span class="nav-link__badge"><?= $pendingApprovals ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="nav-link <?= $active === 'borrow' ? 'is-active' : '' ?>" href="<?= e(url('borrow')) ?>" data-testid="nav-borrow">
                        <?= icon('file') ?><span>Borrowing Requests</span>
                    </a>
                    <a class="nav-link <?= $active === 'transactions' ? 'is-active' : '' ?>" href="<?= e(url('transactions')) ?>" data-testid="nav-transactions">
                        <?= icon('arrow-up-right') ?><span>Transactions</span>
                        <?php if ($overdueCount > 0): ?>
                            <span class="nav-link__badge nav-link__badge--danger"><?= $overdueCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="nav-link <?= $active === 'returns' ? 'is-active' : '' ?>" href="<?= e(url('returns')) ?>" data-testid="nav-returns">
                        <?= icon('rotate-ccw') ?><span>Returns &amp; Conditions</span>
                    </a>
                </div>

                <div class="nav-group">
                    <div class="nav-group__label">Administration</div>
                    <a class="nav-link <?= $active === 'users' ? 'is-active' : '' ?>" href="<?= e(url('users')) ?>" data-testid="nav-users">
                        <?= icon('users') ?><span>User Accounts</span>
                    </a>
                    <a class="nav-link <?= $active === 'reports' ? 'is-active' : '' ?>" href="<?= e(url('reports')) ?>" data-testid="nav-reports">
                        <?= icon('chart') ?><span>Reports</span>
                    </a>
                    <a class="nav-link <?= $active === 'audit' ? 'is-active' : '' ?>" href="<?= e(url('audit')) ?>" data-testid="nav-audit">
                        <?= icon('shield') ?><span>Audit Trail</span>
                    </a>
                </div>
            <?php else: ?>
                <div class="nav-group">
                    <div class="nav-group__label">My Borrowing</div>
                    <a class="nav-link <?= $active === 'borrow' ? 'is-active' : '' ?>" href="<?= e(url('borrow')) ?>" data-testid="nav-borrow">
                        <?= icon('clipboard') ?><span>My Requests</span>
                    </a>
                    <a class="nav-link <?= $active === 'transactions' ? 'is-active' : '' ?>" href="<?= e(url('transactions')) ?>" data-testid="nav-transactions">
                        <?= icon('arrow-up-right') ?><span>My Borrowings</span>
                        <?php if ($overdueCount > 0): ?>
                            <span class="nav-link__badge nav-link__badge--danger"><?= $overdueCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="nav-link <?= $active === 'returns' ? 'is-active' : '' ?>" href="<?= e(url('returns')) ?>" data-testid="nav-returns">
                        <?= icon('rotate-ccw') ?><span>My Returns</span>
                    </a>
                </div>
            <?php endif; ?>

            <div class="nav-group">
                <div class="nav-group__label">Account</div>
                <a class="nav-link <?= $active === 'notifications' ? 'is-active' : '' ?>" href="<?= e(url('notifications')) ?>" data-testid="nav-notifications">
                    <?= icon('bell') ?><span>Notifications</span>
                    <span class="nav-link__badge" data-notification-nav-count <?= ($unreadNotifications ?? 0) > 0 ? '' : 'hidden' ?>><?= (int) ($unreadNotifications ?? 0) ?></span>
                </a>
                <a class="nav-link <?= $active === 'profile' ? 'is-active' : '' ?>" href="<?= e(url('profile')) ?>" data-testid="nav-profile">
                    <?= icon('user') ?><span>My Profile</span>
                </a>
            </div>
        </nav>
    </div>

    <div class="sidebar__foot">
        <span class="status-dot" aria-hidden="true"></span>
        <span>Laboratory operations online</span>
    </div>
</aside>
