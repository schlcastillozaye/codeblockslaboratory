<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Session;

/**
 * Global view/controller helpers.
 */

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    $configured = (string) Config::get('app.url', '');
    if ($configured !== '') {
        $path = parse_url($configured, PHP_URL_PATH) ?: '';
        return rtrim($path, '/');
    }

    if (PHP_SAPI === 'cli') {
        return '';
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = str_replace('\\', '/', dirname($script));
    $dir = rtrim($dir, '/');
    return ($dir === '' || $dir === '.' || $dir === '/') ? '' : $dir;
}

function url(string $path = '', array $query = []): string
{
    $path = ltrim($path, '/');
    $configured = (string) Config::get('app.url', '');

    if ($configured !== '') {
        $base = rtrim($configured, '/');
    } else {
        $base = base_path();
    }

    if (!Config::get('app.pretty_urls', true)) {
        $target = ($base === '' ? '' : $base) . '/index.php';
        if ($path !== '') {
            $query = ['route' => $path] + $query;
        }
    } else {
        $target = ($base === '' ? '' : $base) . '/' . $path;
    }

    if ($query !== []) {
        $target .= (str_contains($target, '?') ? '&' : '?') . http_build_query($query);
    }

    return $target === '' ? '/' : $target;
}

function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = BASE_PATH . '/public/' . $path;
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return url($path) . '?v=' . $version;
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return Csrf::field();
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function back(string $fallback = 'dashboard'): never
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($referer !== '' && $host !== '' && (parse_url($referer, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST))) {
        redirect($referer);
    }
    redirect(url($fallback));
}

function current_user(): ?array
{
    return Auth::user();
}

function is_admin(): bool
{
    return Auth::isAdmin();
}

function role_label(?string $role): string
{
    return match ($role) {
        'admin' => 'Laboratory Personnel',
        'instructor' => 'Instructor',
        'student' => 'Student',
        default => 'User',
    };
}

function role_short_label(?string $role): string
{
    return match ($role) {
        'admin' => 'Admin',
        'instructor' => 'Instructor',
        'student' => 'Student',
        default => 'User',
    };
}

function format_date(?string $date, string $fallback = '—'): string
{
    if (!$date || $date === '0000-00-00') {
        return $fallback;
    }
    $ts = strtotime($date);
    return $ts ? date('M j, Y', $ts) : $fallback;
}

function format_datetime(?string $date, string $fallback = '—'): string
{
    if (!$date) {
        return $fallback;
    }
    $ts = strtotime($date);
    return $ts ? date('M j, Y g:i A', $ts) : $fallback;
}

function time_ago(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    if (!$ts) {
        return '—';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . 'd ago';
    }
    return format_date($date);
}

function days_until(?string $date): ?int
{
    if (!$date) {
        return null;
    }
    $ts = strtotime($date);
    if (!$ts) {
        return null;
    }
    $today = strtotime(date('Y-m-d'));
    return (int) floor(($ts - $today) / 86400);
}

function initials(string $firstName, string $lastName = ''): string
{
    $first = mb_substr(trim($firstName), 0, 1);
    $last = mb_substr(trim($lastName), 0, 1);
    return mb_strtoupper($first . $last);
}

function status_meta(string $status): array
{
    return match ($status) {
        'pending'          => ['label' => 'Pending', 'class' => 'warning', 'icon' => 'clock'],
        'approved'         => ['label' => 'Approved', 'class' => 'info', 'icon' => 'check-circle'],
        'active'           => ['label' => 'Borrowed', 'class' => 'primary', 'icon' => 'arrow-up-right'],
        'overdue'          => ['label' => 'Overdue', 'class' => 'danger', 'icon' => 'alert-triangle'],
        'partially_returned' => ['label' => 'Partially Returned', 'class' => 'warning', 'icon' => 'rotate-ccw'],
        'returned'         => ['label' => 'Returned', 'class' => 'success', 'icon' => 'check-circle'],
        'completed'        => ['label' => 'Completed', 'class' => 'success', 'icon' => 'check-circle'],
        'rejected'         => ['label' => 'Rejected', 'class' => 'danger', 'icon' => 'x-circle'],
        'cancelled'        => ['label' => 'Cancelled', 'class' => 'muted', 'icon' => 'ban'],
        'lost'             => ['label' => 'Lost', 'class' => 'danger', 'icon' => 'alert-octagon'],
        'good'             => ['label' => 'Good / Working', 'class' => 'success', 'icon' => 'check'],
        'damaged'          => ['label' => 'Damaged', 'class' => 'danger', 'icon' => 'alert-triangle'],
        'needs_maintenance' => ['label' => 'Needs Maintenance', 'class' => 'warning', 'icon' => 'tool'],
        'incomplete'       => ['label' => 'Incomplete / Missing Parts', 'class' => 'warning', 'icon' => 'package'],
        'active_record'    => ['label' => 'Active', 'class' => 'success', 'icon' => 'check'],
        'archived'         => ['label' => 'Archived', 'class' => 'muted', 'icon' => 'archive'],
        default            => ['label' => ucwords(str_replace('_', ' ', $status)), 'class' => 'muted', 'icon' => 'info'],
    };
}

function status_badge(string $status, ?string $label = null): string
{
    $meta = status_meta($status);
    return '<span class="badge badge--' . e($meta['class']) . '" data-testid="status-' . e($status) . '">'
        . icon($meta['icon'], 'icon icon--xs') . '<span>' . e($label ?? $meta['label']) . '</span></span>';
}

function condition_label(string $condition): string
{
    return match ($condition) {
        'good' => 'Good / Working',
        'needs_maintenance' => 'Needs Maintenance',
        'damaged' => 'Damaged',
        'incomplete' => 'Incomplete / Missing Parts',
        'lost' => 'Lost',
        default => ucwords(str_replace('_', ' ', $condition)),
    };
}

/** @return array<int, array{type:string,message:string}> */
function flash_messages(): array
{
    static $messages = null;
    if ($messages === null) {
        $messages = Flash::pullAll();
    }
    return $messages;
}

/** @return array<string, string> */
function form_errors(): array
{
    static $errors = null;
    if ($errors === null) {
        $errors = Flash::errors();
    }
    return $errors;
}

function error_for(string $field): string
{
    $errors = form_errors();
    return $errors[$field] ?? '';
}

/** @return array<string, mixed> */
function old_input(): array
{
    static $old = null;
    if ($old === null) {
        $old = Flash::old();
    }
    return $old;
}

function old(string $field, mixed $default = ''): mixed
{
    return old_input()[$field] ?? $default;
}

function checked(bool $condition): string
{
    return $condition ? 'checked' : '';
}

function selected(mixed $value, mixed $expected): string
{
    return (string) $value === (string) $expected ? 'selected' : '';
}

function truncate(?string $text, int $length = 90): string
{
    $text = trim((string) $text);
    return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
}

/**
 * Render pagination links while preserving current query string.
 */
function pagination(App\Core\Paginator $paginator, string $path, array $query = []): string
{
    if ($paginator->total === 0) {
        return '';
    }

    $pageUrl = static function (int $page) use ($path, $query): string {
        $query['page'] = $page;
        return url($path, $query);
    };

    $html = '<nav class="pagination" aria-label="Pagination">';
    $html .= '<span class="pagination__summary">Showing ' . $paginator->from() . '–' . $paginator->to() . ' of ' . $paginator->total . '</span>';
    $html .= '<div class="pagination__controls">';
    $html .= $paginator->hasPrevious()
        ? '<a class="pagination__btn" href="' . e($pageUrl($paginator->page - 1)) . '" rel="prev" aria-label="Previous page">' . icon('chevron-left') . '</a>'
        : '<span class="pagination__btn is-disabled" aria-hidden="true">' . icon('chevron-left') . '</span>';

    $start = max(1, $paginator->page - 2);
    $end = min($paginator->lastPage(), $paginator->page + 2);
    if ($start > 1) {
        $html .= '<a class="pagination__btn" href="' . e($pageUrl(1)) . '">1</a>';
        if ($start > 2) {
            $html .= '<span class="pagination__ellipsis">…</span>';
        }
    }
    for ($page = $start; $page <= $end; $page++) {
        $current = $page === $paginator->page;
        $html .= '<a class="pagination__btn' . ($current ? ' is-active' : '') . '" href="' . e($pageUrl($page)) . '"'
            . ($current ? ' aria-current="page"' : '') . '>' . $page . '</a>';
    }
    if ($end < $paginator->lastPage()) {
        if ($end < $paginator->lastPage() - 1) {
            $html .= '<span class="pagination__ellipsis">…</span>';
        }
        $html .= '<a class="pagination__btn" href="' . e($pageUrl($paginator->lastPage())) . '">' . $paginator->lastPage() . '</a>';
    }

    $html .= $paginator->hasNext()
        ? '<a class="pagination__btn" href="' . e($pageUrl($paginator->page + 1)) . '" rel="next" aria-label="Next page">' . icon('chevron-right') . '</a>'
        : '<span class="pagination__btn is-disabled" aria-hidden="true">' . icon('chevron-right') . '</span>';
    $html .= '</div></nav>';

    return $html;
}

/**
 * Inline SVG icon set (locally bundled, no external icon font).
 */
function icon(string $name, string $class = 'icon'): string
{
    $paths = [
        'dashboard'    => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'box'          => '<path d="M21 8.5 12 3 3 8.5v7L12 21l9-5.5v-7Z"/><path d="m3.3 8.6 8.7 5 8.7-5"/><path d="M12 21v-7.4"/>',
        'boxes'        => '<path d="m12 3 4.5 2.6v5.2L12 13.4 7.5 10.8V5.6L12 3Z"/><path d="m5 12 4 2.3v4.6L5 21.2l-4-2.3v-4.6L5 12Z" transform="translate(3)"/>',
        'plus'         => '<path d="M12 5v14M5 12h14"/>',
        'search'       => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/>',
        'filter'       => '<path d="M4 5h16l-6.5 7.5V20l-3 1v-8.5L4 5Z"/>',
        'users'        => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
        'user'         => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'clipboard'    => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1"/><path d="M9 10h6M9 14h6M9 18h4"/>',
        'check'        => '<path d="m5 12 4.5 4.5L19 7"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 2.7 2.7L16 9.5"/>',
        'x'            => '<path d="M6 6l12 12M18 6 6 18"/>',
        'x-circle'     => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
        'ban'          => '<circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/>',
        'rotate-ccw'   => '<path d="M3 3v6h6"/><path d="M3.5 9a9 9 0 1 1 1.3 8.5"/>',
        'arrow-up-right' => '<path d="M7 17 17 7M8 7h9v9"/>',
        'alert-triangle' => '<path d="M10.3 3.7 2.4 17.3A2 2 0 0 0 4.1 20h15.8a2 2 0 0 0 1.7-2.7L13.7 3.7a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
        'alert-octagon' => '<path d="M7.9 2h8.2L22 7.9v8.2L16.1 22H7.9L2 16.1V7.9L7.9 2Z"/><path d="M12 8v4M12 16h.01"/>',
        'clock'        => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'tool'         => '<path d="M14.7 6.3a4 4 0 0 0 5 5L21 21H3l7.3-7.3a4 4 0 0 1 4.4-7.4Z"/><path d="m14 7 3 3"/>',
        'package'      => '<path d="m12 2 9 5v10l-9 5-9-5V7l9-5Z"/><path d="m3 7 9 5 9-5M12 12v10"/>',
        'archive'      => '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v11h14V9M10 13h4"/>',
        'chart'        => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'shield'       => '<path d="M12 3 4 6v6c0 5 3.4 8.2 8 9 4.6-.8 8-4 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
        'bell'         => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'logout'       => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'menu'         => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'edit'         => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/>',
        'eye'          => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'      => '<path d="M3 3l18 18"/><path d="M10.6 5.2A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3 3.9M6.7 6.8C3.8 8.7 2 12 2 12s3.5 7 10 7a11 11 0 0 0 4.3-.8"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
        'calendar'     => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'download'     => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 21h16"/>',
        'print'        => '<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M6 14h12v7H6z"/>',
        'arrow-left'   => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'arrow-right'  => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'info'         => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'home'         => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V10Z"/>',
        'file'         => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Z"/><path d="M14 2v6h6M8 13h8M8 17h6"/>',
        'trending-up'  => '<path d="m3 17 6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        'trending-down' => '<path d="m3 7 6 6 4-4 8 8"/><path d="M15 17h6v-6"/>',
        'mail'         => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'phone'        => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.8 2Z"/>',
        'lock'         => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'unlock'       => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 7.5-2"/>',
        'ban-circle'   => '<circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/>',
        'refresh'      => '<path d="M21 3v6h-6"/><path d="M3 21v-6h6"/><path d="M3.5 9a9 9 0 0 1 14.8-3.4L21 9M21 15a9 9 0 0 1-14.8 3.4L3 15"/>',
        'sparkles'     => '<path d="M12 3l1.6 4.4L18 9l-4.4 1.6L12 15l-1.6-4.4L6 9l4.4-1.6L12 3Z"/><path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z"/>',
        'sun'          => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'         => '<path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11Z"/>',
        'code'         => '<path d="m9 18-6-6 6-6M15 6l6 6-6 6"/>',
        'grid'         => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'list'         => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'clock-fast'   => '<path d="M12 8v4l2 2"/><circle cx="12" cy="12" r="9"/><path d="M2 5h5"/>',
        'map-pin'      => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'tag'          => '<path d="m20 12-8 8-9-9V3h8l9 9Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'external'     => '<path d="M14 3h7v7M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/>',
        'sliders'      => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3"/><path d="M1 14h6M9 8h6M17 16h6"/>',
        'trash'        => '<path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14M10 11v6M14 11v6"/>',
    ];

    $body = $paths[$name] ?? $paths['info'];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
}
