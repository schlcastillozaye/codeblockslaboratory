<?php

declare(strict_types=1);

/**
 * Create in-app reminders for checkouts due within the next N days.
 * Existing reminders from the last 24 hours are not duplicated.
 *
 * Usage:
 *   php bin/send-due-reminders.php
 *   php bin/send-due-reminders.php --days=3
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Services\NotificationService;

$options = getopt('', ['days:']);
$days = isset($options['days']) ? max(0, (int) $options['days']) : 2;

try {
    $created = NotificationService::notifyDueSoon($days);
    printf("Created %d due-date notification(s) for checkouts due within %d day(s).\n", $created, $days);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Unable to create due-date reminders: ' . $e->getMessage() . "\n");
    exit(1);
}
