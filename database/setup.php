<?php

declare(strict_types=1);

/**
 * Code Blocks Laboratory Equipment Borrowing System - database installer.
 *
 * CLI only. Creates the database, imports database/schema.sql, and optionally
 * loads database/seed.sql (which RESETS the demo tables).
 *
 * Examples:
 *   php database/setup.php
 *   php database/setup.php --seed --force
 *   php database/setup.php --fresh --seed --force
 *
 * Connection settings come from config/config.php, config/config.local.php,
 * or environment variables (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This installer must be run from the command line.\n");
}

define('BASE_PATH', dirname(__DIR__));

$options = getopt('', ['seed', 'fresh', 'force', 'help']);
if (isset($options['help'])) {
    echo <<<TXT
Code Blocks Laboratory Equipment Borrowing System database installer

Usage:
  php database/setup.php [--seed] [--fresh] [--force]

Options:
  --seed   Import demo users, equipment, requests, returns, and audit samples.
  --fresh  Drop the existing database before importing the schema.
  --force  Skip the interactive confirmation for destructive options.
  --help   Show this message.

Environment overrides: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
TXT;
    echo "\n";
    exit(0);
}

$seed = isset($options['seed']);
$fresh = isset($options['fresh']);
$force = isset($options['force']);

$config = require BASE_PATH . '/config/config.php';
if (is_file(BASE_PATH . '/config/config.local.php')) {
    $local = require BASE_PATH . '/config/config.local.php';
    if (is_array($local)) {
        $config = array_replace_recursive($config, $local);
    }
}

$db = (array) ($config['db'] ?? []);
$host = (string) ($db['host'] ?? '127.0.0.1');
$port = (string) ($db['port'] ?? '3306');
$name = (string) ($db['name'] ?? 'codeblocks_lab_inventory');
$user = (string) ($db['user'] ?? 'root');
$pass = (string) ($db['password'] ?? '');

if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
    fwrite(STDERR, "Refusing to use an unsafe database name: {$name}\n");
    exit(1);
}

if (($fresh || $seed) && !$force) {
    $action = $fresh ? 'DROP and recreate the database' : 'reset the demo tables';
    fwrite(STDOUT, "This will {$action} in `{$name}` on {$host}:{$port}.\nType 'yes' to continue: ");
    $answer = trim((string) fgets(STDIN));
    if (strtolower($answer) !== 'yes') {
        fwrite(STDOUT, "Aborted. No changes were made.\n");
        exit(0);
    }
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    fwrite(STDOUT, "Connected to MySQL {$host}:{$port} as {$user}.\n");

    if ($fresh) {
        $pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
        fwrite(STDOUT, "Dropped existing database `{$name}` (if present).\n");
    }

    $pdo->exec(
        "CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    $pdo->exec("USE `{$name}`");

    $schemaFile = BASE_PATH . '/database/schema.sql';
    $statements = splitSqlFile($schemaFile);
    foreach ($statements as $index => $statement) {
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Schema statement ' . ($index + 1) . ' failed: ' . $e->getMessage() . "\nSQL: " . substr($statement, 0, 240),
                0,
                $e
            );
        }
    }
    fwrite(STDOUT, 'Imported schema (' . count($statements) . " statements).\n");

    if ($seed) {
        $seedFile = BASE_PATH . '/database/seed.sql';
        $statements = splitSqlFile($seedFile);
        foreach ($statements as $index => $statement) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                throw new RuntimeException(
                    'Seed statement ' . ($index + 1) . ' failed: ' . $e->getMessage() . "\nSQL: " . substr($statement, 0, 240),
                    0,
                    $e
                );
            }
        }
        fwrite(STDOUT, 'Imported demo seed (' . count($statements) . " statements).\n");
    }

    $pdo->exec("USE `{$name}`");

    $counts = [
        'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'categories' => (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
        'equipment' => (int) $pdo->query('SELECT COUNT(*) FROM equipment')->fetchColumn(),
        'borrow_requests' => (int) $pdo->query('SELECT COUNT(*) FROM borrow_requests')->fetchColumn(),
        'borrow_transactions' => (int) $pdo->query('SELECT COUNT(*) FROM borrow_transactions')->fetchColumn(),
        'return_records' => (int) $pdo->query('SELECT COUNT(*) FROM return_records')->fetchColumn(),
        'audit_logs' => (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn(),
    ];

    $broken = (int) $pdo->query(
        'SELECT COUNT(*) FROM equipment
         WHERE total_quantity <> available_quantity + borrowed_quantity
              + damaged_quantity + under_repair_quantity + lost_quantity'
    )->fetchColumn();

    fwrite(STDOUT, "\nVerification:\n");
    foreach ($counts as $table => $count) {
        fwrite(STDOUT, sprintf("  %-20s %d\n", $table, $count));
    }
    if ($broken !== 0) {
        fwrite(STDERR, "\nWARNING: {$broken} equipment row(s) violate the quantity invariant.\n");
        exit(2);
    }

    fwrite(STDOUT, "  quantity invariant    OK\n");
    fwrite(STDOUT, "\nDatabase `{$name}` is ready.\n");
    if ($seed) {
        fwrite(STDOUT, "Demo sign-in: admin@codeblocks.test / Admin@12345\n");
        fwrite(STDOUT, "Change all demo passwords before any real deployment.\n");
    }
} catch (Throwable $e) {
    fwrite(STDERR, "\nSetup failed: " . $e->getMessage() . "\n");
    exit(1);
}

/**
 * Split a .sql file into executable statements while ignoring comments and
 * respecting quoted strings.
 *
 * @return array<int, string>
 */
function splitSqlFile(string $file): array
{
    if (!is_file($file)) {
        throw new RuntimeException('SQL file not found: ' . $file);
    }

    $sql = (string) file_get_contents($file);
    $length = strlen($sql);
    $statements = [];
    $buffer = '';
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($inLineComment) {
            if ($char === "\n") {
                $inLineComment = false;
                $buffer .= "\n";
            }
            continue;
        }

        if ($inBlockComment) {
            if ($char === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }

        if (!$inSingle && !$inDouble && !$inBacktick) {
            if ($char === '-' && $next === '-') {
                $inLineComment = true;
                $i++;
                continue;
            }
            if ($char === '#') {
                $inLineComment = true;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }
        }

        if ($char === "'" && !$inDouble && !$inBacktick) {
            if ($inSingle && $next === "'") {
                $buffer .= "''";
                $i++;
                continue;
            }
            $inSingle = !$inSingle;
            $buffer .= $char;
            continue;
        }

        if ($char === '"' && !$inSingle && !$inBacktick) {
            if ($inDouble && $next === '"') {
                $buffer .= '""';
                $i++;
                continue;
            }
            $inDouble = !$inDouble;
            $buffer .= $char;
            continue;
        }

        if ($char === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
            $buffer .= $char;
            continue;
        }

        if ($char === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }

    return $statements;
}
