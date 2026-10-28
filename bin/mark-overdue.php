<?php

declare(strict_types=1);

/**
 * Mark every open checkout past its due date as overdue.
 *
 * Run from the project root or through Windows Task Scheduler:
 *   php bin/mark-overdue.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Models\BorrowTransaction;

try {
    $updated = BorrowTransaction::markOverdue();
    $open = BorrowTransaction::stats();
    printf("Marked %d transaction(s) overdue. %d open checkout(s) remain.\n", $updated, (int) ($open['open_total'] ?? 0));
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Unable to update overdue transactions: ' . $e->getMessage() . "\n");
    exit(1);
}
