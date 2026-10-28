<?php

/**
 * Central PDO connection service.
 *
 * The connection is created once per request (lazy loaded) and shared through
 * App\Core\Database. This is a lightweight Singleton-style connection service:
 * its only purpose is to avoid opening duplicate connections and to expose one
 * configured PDO instance for every model. No domain state is stored globally.
 *
 * Usage: $pdo = App\Core\Database::pdo();
 */

use App\Core\Config;
use App\Core\Database;

Database::configure(Config::get('db'));

return Database::pdo();
