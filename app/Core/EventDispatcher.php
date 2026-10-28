<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Observer-inspired domain event dispatcher.
 *
 * Listeners run synchronously inside the same database transaction as the
 * business operation, so in-app notifications and audit entries are committed
 * or rolled back with it. These notifications are LOCAL ONLY - no email/SMS is
 * delivered by this prototype.
 */
final class EventDispatcher
{
    /** @var array<string, array<int, callable>> */
    private static array $listeners = [];

    public static function listen(string $event, callable $listener): void
    {
        self::$listeners[$event][] = $listener;
    }

    /** @param array<string, mixed> $payload */
    public static function dispatch(string $event, array $payload = []): void
    {
        foreach (self::$listeners[$event] ?? [] as $listener) {
            $listener($payload);
        }
    }
}
