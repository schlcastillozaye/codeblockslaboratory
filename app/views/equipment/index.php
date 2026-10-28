<?php
$queryBase = array_filter([
    'q' => $filters['q'] !== '' ? $filters['q'] : null,
    'category_id' => $filters['category_id'] ?: null,
    'availability' => $filters['availability'] !== '' ? $filters['availability'] : null,
    'condition' => $filters['condition'] !== '' ? $filters['condition'] : null,
    'status' => $filters['status'] !== '' ? $filters['status'] : null,
    'sort' => $filters['sort'] !== 'name' ? $filters['sort'] : null,
], static fn ($v) => $v !== null);
$hasFilters = $queryBase !== [];
?>
<div class="page-header reveal">
    <div>
        <div class="page-header__eyebrow"><?= icon('boxes', 'icon icon--sm') ?> Inventory catalog</div>
        <h1 class="page-title">Laboratory Equipment</h1>
        <p class="page-subtitle">Search the catalog, inspect quantities and conditions, and submit a borrowing request.</p>
    </div>
    <div class="page-actions">
        <?php if (is_admin()): ?>
            <a class="btn btn--ghost" href="<?= e(url('reports', ['type' => 'inventory'])) ?>"><?= icon('download') ?> Inventory report</a>
            <a class="btn btn--primary" href="<?= e(url('equipment/create')) ?>" data-testid="add-equipment-button"><?= icon('plus') ?> Add equipment</a>
        <?php endif; ?>
    </div>
</div>

<form class="toolbar reveal" method="get" action="<?= e(url('equipment')) ?>" data-testid="equipment-filters">
    <div class="field field--grow">
        <label class="field__label" for="equipment-search">Search</label>
        <div class="input-icon">
            <?= icon('search') ?>
            <input class="input" type="search" id="equipment-search" name="q" value="<?= e($filters['q']) ?>"
                   placeholder="Name, equipment ID, category, barcode, location…"
                   data-live-filter="#equipment-grid" data-count-target="#equipment-visible-count"
                   data-testid="equipment-search">
        </div>
    </div>
    <div class="field field--sm">
        <label class="field__label" for="filter-category">Category</label>
        <select class="select" id="filter-category" name="category_id" data-testid="equipment-category-filter">
            <option value="">All categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id'] ?>" <?= selected($filters['category_id'], $category['id']) ?>><?= e($category['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field field--sm">
        <label class="field__label" for="filter-availability">Availability</label>
        <select class="select" id="filter-availability" name="availability">
            <option value="">Any availability</option>
            <option value="available" <?= selected($filters['availability'], 'available') ?>>Available now</option>
            <option value="borrowed" <?= selected($filters['availability'], 'borrowed') ?>>Currently borrowed</option>
            <option value="unavailable" <?= selected($filters['availability'], 'unavailable') ?>>Unavailable</option>
            <option value="repair" <?= selected($filters['availability'], 'repair') ?>>Damaged / repair</option>
        </select>
    </div>
    <div class="field field--sm">
        <label class="field__label" for="filter-condition">Condition</label>
        <select class="select" id="filter-condition" name="condition">
            <option value="">Any condition</option>
            <option value="good" <?= selected($filters['condition'], 'good') ?>>Good / Working</option>
            <option value="needs_maintenance" <?= selected($filters['condition'], 'needs_maintenance') ?>>Needs Maintenance</option>
            <option value="damaged" <?= selected($filters['condition'], 'damaged') ?>>Damaged</option>
            <option value="lost" <?= selected($filters['condition'], 'lost') ?>>Lost</option>
        </select>
    </div>
    <?php if (is_admin()): ?>
        <div class="field field--sm">
            <label class="field__label" for="filter-status">Record status</label>
            <select class="select" id="filter-status" name="status">
                <option value="">All records</option>
                <option value="active" <?= selected($filters['status'], 'active') ?>>Active</option>
                <option value="archived" <?= selected($filters['status'], 'archived') ?>>Archived</option>
            </select>
        </div>
    <?php endif; ?>
    <div class="field field--sm">
        <label class="field__label" for="filter-sort">Sort</label>
        <select class="select" id="filter-sort" name="sort">
            <option value="name" <?= selected($filters['sort'], 'name') ?>>Name A–Z</option>
            <option value="newest" <?= selected($filters['sort'], 'newest') ?>>Newest first</option>
            <option value="available" <?= selected($filters['sort'], 'available') ?>>Most available</option>
            <option value="total" <?= selected($filters['sort'], 'total') ?>>Largest stock</option>
            <option value="category" <?= selected($filters['sort'], 'category') ?>>Category</option>
        </select>
    </div>
    <div class="toolbar__actions">
        <button type="submit" class="btn btn--secondary" data-testid="equipment-filter-submit"><?= icon('filter') ?> Filter</button>
        <?php if ($hasFilters): ?>
            <a class="btn btn--ghost" href="<?= e(url('equipment')) ?>">Reset</a>
        <?php endif; ?>
    </div>
</form>

<div class="section-head" style="margin-top:0;">
    <div class="field__hint">
        <strong id="equipment-visible-count" style="color:var(--ink);"><?= $resultCount ?></strong>
        matching equipment record(s)<?= $hasFilters ? ' · filtered' : '' ?>
    </div>
    <span class="chip" style="margin-left:auto;"><?= icon('grid', 'icon icon--xs') ?> Catalog view</span>
</div>

<?php if ($paginator->items === []): ?>
    <div class="card reveal">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('search', 'icon icon--xl') ?></div>
            <h3>No equipment matches your filters</h3>
            <p>Try a different keyword, category, or availability filter. Archived records are only visible to laboratory personnel.</p>
            <a class="btn btn--primary" href="<?= e(url('equipment')) ?>">Clear filters</a>
        </div>
    </div>
<?php else: ?>
    <div class="equipment-grid" id="equipment-grid">
        <?php foreach ($paginator->items as $item): ?>
            <?php
            $total = max(0, (int) $item['total_quantity']);
            $percent = static fn (int $value): float => $total > 0 ? ($value / $total) * 100 : 0;
            $available = (int) $item['available_quantity'];
            $canBorrow = $item['record_status'] === 'active' && $available > 0 && $item['condition_status'] === 'good';
            $searchText = $item['name'] . ' ' . $item['equipment_code'] . ' ' . $item['category_name'] . ' ' . $item['storage_location'] . ' ' . ($item['barcode'] ?? '') . ' ' . ($item['description'] ?? '');
            ?>
            <article class="equipment-card reveal" data-search="<?= e($searchText) ?>" data-testid="equipment-card-<?= (int) $item['id'] ?>">
                <div class="equipment-card__head">
                    <span class="equipment-card__icon"><?= icon('box', 'icon icon--lg') ?></span>
                    <div style="min-width:0;">
                        <a class="equipment-card__title" href="<?= e(url('equipment/' . (int) $item['id'])) ?>"><?= e($item['name']) ?></a>
                        <div class="equipment-card__code"><?= e($item['equipment_code']) ?> · <?= e($item['category_name']) ?></div>
                    </div>
                    <span style="margin-left:auto;"><?= status_badge($item['record_status'] === 'archived' ? 'archived' : ($canBorrow ? 'good' : $item['condition_status'])) ?></span>
                </div>

                <p class="equipment-card__desc"><?= e(truncate($item['description'] ?? '', 110)) ?></p>

                <div class="equipment-card__meta">
                    <span class="chip"><?= icon('map-pin', 'icon icon--xs') ?> <?= e(truncate($item['storage_location'], 24)) ?></span>
                    <?php if ((int) $item['damaged_quantity'] > 0): ?><span class="chip" style="color:var(--red);"><?= icon('alert-triangle', 'icon icon--xs') ?> <?= (int) $item['damaged_quantity'] ?> damaged</span><?php endif; ?>
                    <?php if ((int) $item['under_repair_quantity'] > 0): ?><span class="chip" style="color:var(--amber);"><?= icon('tool', 'icon icon--xs') ?> <?= (int) $item['under_repair_quantity'] ?> repair</span><?php endif; ?>
                    <?php if ((int) $item['lost_quantity'] > 0): ?><span class="chip"><?= icon('alert-octagon', 'icon icon--xs') ?> <?= (int) $item['lost_quantity'] ?> lost</span><?php endif; ?>
                </div>

                <div class="qty-bar" aria-hidden="true">
                    <span class="qty-bar__seg qty-bar__seg--available" style="width: <?= round($percent($available), 2) ?>%"></span>
                    <span class="qty-bar__seg qty-bar__seg--borrowed" style="width: <?= round($percent((int) $item['borrowed_quantity']), 2) ?>%"></span>
                    <span class="qty-bar__seg qty-bar__seg--damaged" style="width: <?= round($percent((int) $item['damaged_quantity']), 2) ?>%"></span>
                    <span class="qty-bar__seg qty-bar__seg--repair" style="width: <?= round($percent((int) $item['under_repair_quantity']), 2) ?>%"></span>
                    <span class="qty-bar__seg qty-bar__seg--lost" style="width: <?= round($percent((int) $item['lost_quantity']), 2) ?>%"></span>
                </div>

                <div class="equipment-card__foot">
                    <span class="equipment-card__avail"><strong><?= $available ?></strong> / <?= $total ?> available</span>
                    <div class="btn-row" style="margin-left:auto;">
                        <a class="btn btn--sm btn--ghost" href="<?= e(url('equipment/' . (int) $item['id'])) ?>">Details</a>
                        <?php if ($canBorrow): ?>
                            <a class="btn btn--sm btn--primary" href="<?= e(url('borrow/create', ['equipment_id' => (int) $item['id']])) ?>" data-testid="borrow-equipment-<?= (int) $item['id'] ?>">Request</a>
                        <?php else: ?>
                            <span class="badge badge--muted">Unavailable</span>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div id="equipment-grid-empty" class="card" style="display:none; margin-top:16px;">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('search', 'icon icon--xl') ?></div>
            <h3>No matches on this page</h3>
            <p>Clear the search box to see the current page of equipment again.</p>
        </div>
    </div>

    <?= pagination($paginator, 'equipment', $queryBase) ?>
<?php endif; ?>
