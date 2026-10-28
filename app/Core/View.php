<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight view renderer: view + layout.
 */
final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $viewFile = BASE_PATH . '/app/views/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException('View not found: ' . $view);
        }

        $data['currentUser'] = Auth::user();
        $data['unreadNotifications'] = Auth::check() ? \App\Models\Notification::unreadCount((int) Auth::id()) : 0;
        $data['navNotifications'] = Auth::check() ? \App\Models\Notification::forUser((int) Auth::id(), 6, true) : [];

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutFile = BASE_PATH . '/app/views/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            throw new \RuntimeException('Layout not found: ' . $layout);
        }
        require $layoutFile;
    }

    /** @param array<string, mixed> $data */
    public static function partial(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require BASE_PATH . '/app/views/' . $view . '.php';
    }
}
