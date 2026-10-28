-- ============================================================================
-- Code Blocks Laboratory Equipment Borrowing System - Laboratory Equipment Inventory and Borrowing Management System
-- Database schema (MySQL 8+ / MariaDB 10.4+)
-- Database: codeblocks_lab_inventory
--
-- Quantity model (enforced by CHECK + application services):
--   total_quantity = available_quantity + borrowed_quantity
--                  + damaged_quantity + under_repair_quantity + lost_quantity
-- A unit is in exactly one bucket at any time. This prevents double-counting.
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+08:00';

CREATE DATABASE IF NOT EXISTS `codeblocks_lab_inventory`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `codeblocks_lab_inventory`;

-- ---------------------------------------------------------------------------
-- Users and authentication
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `school_id`     VARCHAR(30)  NOT NULL,
    `first_name`    VARCHAR(80)  NOT NULL,
    `last_name`     VARCHAR(80)  NOT NULL,
    `email`         VARCHAR(150) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role`          ENUM('admin','student','instructor') NOT NULL DEFAULT 'student',
    `department`    VARCHAR(120) NULL,
    `phone`         VARCHAR(30)  NULL,
    `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `last_login_at` DATETIME NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_school_id` (`school_id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role_status` (`role`, `status`),
    KEY `idx_users_name` (`last_name`, `first_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `login`        VARCHAR(150) NOT NULL,
    `ip_address`   VARCHAR(45)  NOT NULL,
    `success`      TINYINT(1)   NOT NULL DEFAULT 0,
    `attempted_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_login_attempts_lookup` (`login`, `success`, `attempted_at`),
    KEY `idx_login_attempts_ip` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Equipment catalog
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `equipment` (
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `equipment_code`        VARCHAR(40)  NOT NULL,
    `name`                  VARCHAR(150) NOT NULL,
    `category_id`           INT UNSIGNED NOT NULL,
    `description`           TEXT NULL,
    `total_quantity`        INT UNSIGNED NOT NULL DEFAULT 0,
    `available_quantity`    INT UNSIGNED NOT NULL DEFAULT 0,
    `borrowed_quantity`     INT UNSIGNED NOT NULL DEFAULT 0,
    `damaged_quantity`      INT UNSIGNED NOT NULL DEFAULT 0,
    `under_repair_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    `lost_quantity`         INT UNSIGNED NOT NULL DEFAULT 0,
    `condition_status`      ENUM('good','needs_maintenance','damaged','lost') NOT NULL DEFAULT 'good',
    `storage_location`      VARCHAR(150) NOT NULL,
    `barcode`               VARCHAR(100) NULL,
    `record_status`         ENUM('active','archived') NOT NULL DEFAULT 'active',
    `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_equipment_code` (`equipment_code`),
    UNIQUE KEY `uq_equipment_barcode` (`barcode`),
    KEY `idx_equipment_category` (`category_id`),
    KEY `idx_equipment_status` (`record_status`, `condition_status`),
    KEY `idx_equipment_availability` (`available_quantity`),
    KEY `idx_equipment_name` (`name`),
    CONSTRAINT `fk_equipment_category` FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `chk_equipment_quantities` CHECK (
        `total_quantity` >= 0
        AND `available_quantity` >= 0
        AND `borrowed_quantity` >= 0
        AND `damaged_quantity` >= 0
        AND `under_repair_quantity` >= 0
        AND `lost_quantity` >= 0
        AND `total_quantity` = (`available_quantity` + `borrowed_quantity` + `damaged_quantity` + `under_repair_quantity` + `lost_quantity`)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Borrowing workflow
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `borrow_requests` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_code`         VARCHAR(30) NOT NULL,
    `user_id`              INT UNSIGNED NOT NULL,
    `purpose`              TEXT NOT NULL,
    `expected_return_date` DATE NOT NULL,
    `status`               ENUM('pending','approved','rejected','cancelled','completed') NOT NULL DEFAULT 'pending',
    `reviewer_id`          INT UNSIGNED NULL,
    `reviewed_at`          DATETIME NULL,
    `rejection_reason`     VARCHAR(500) NULL,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_borrow_requests_code` (`request_code`),
    KEY `idx_requests_user_status` (`user_id`, `status`),
    KEY `idx_requests_status_created` (`status`, `created_at`),
    KEY `idx_requests_expected` (`expected_return_date`),
    CONSTRAINT `fk_requests_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_requests_reviewer` FOREIGN KEY (`reviewer_id`)
        REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `borrow_request_items` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_id`         INT UNSIGNED NOT NULL,
    `equipment_id`       INT UNSIGNED NOT NULL,
    `quantity_requested` INT UNSIGNED NOT NULL,
    `quantity_approved`  INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_request_equipment` (`request_id`, `equipment_id`),
    KEY `idx_request_items_equipment` (`equipment_id`),
    CONSTRAINT `fk_request_items_request` FOREIGN KEY (`request_id`)
        REFERENCES `borrow_requests` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_request_items_equipment` FOREIGN KEY (`equipment_id`)
        REFERENCES `equipment` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `chk_request_item_qty` CHECK (`quantity_requested` > 0
        AND (`quantity_approved` IS NULL OR `quantity_approved` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `borrow_transactions` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transaction_code`  VARCHAR(30) NOT NULL,
    `request_id`        INT UNSIGNED NOT NULL,
    `request_item_id`   INT UNSIGNED NOT NULL,
    `user_id`           INT UNSIGNED NOT NULL,
    `equipment_id`      INT UNSIGNED NOT NULL,
    `quantity`          INT UNSIGNED NOT NULL,
    `quantity_returned` INT UNSIGNED NOT NULL DEFAULT 0,
    `borrowed_at`       DATETIME NOT NULL,
    `due_at`            DATETIME NOT NULL,
    `returned_at`       DATETIME NULL,
    `status`            ENUM('active','partially_returned','returned','overdue','lost') NOT NULL DEFAULT 'active',
    `processed_by`      INT UNSIGNED NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_transactions_code` (`transaction_code`),
    UNIQUE KEY `uq_transactions_request_item` (`request_item_id`),
    KEY `idx_transactions_user_status` (`user_id`, `status`),
    KEY `idx_transactions_equipment` (`equipment_id`),
    KEY `idx_transactions_due` (`due_at`, `status`),
    KEY `idx_transactions_request` (`request_id`),
    CONSTRAINT `fk_transactions_request` FOREIGN KEY (`request_id`)
        REFERENCES `borrow_requests` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_transactions_request_item` FOREIGN KEY (`request_item_id`)
        REFERENCES `borrow_request_items` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_transactions_equipment` FOREIGN KEY (`equipment_id`)
        REFERENCES `equipment` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_transactions_processor` FOREIGN KEY (`processed_by`)
        REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `chk_transaction_quantity` CHECK (
        `quantity` > 0 AND `quantity_returned` <= `quantity`
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `return_records` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `return_code`       VARCHAR(30) NOT NULL,
    `transaction_id`    INT UNSIGNED NOT NULL,
    `equipment_id`      INT UNSIGNED NOT NULL,
    `quantity_returned` INT UNSIGNED NOT NULL,
    `return_date`       DATE NOT NULL,
    `condition`         ENUM('good','damaged','needs_maintenance','incomplete') NOT NULL,
    `notes`             VARCHAR(500) NULL,
    `processed_by`      INT UNSIGNED NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_return_code` (`return_code`),
    KEY `idx_returns_transaction` (`transaction_id`),
    KEY `idx_returns_equipment` (`equipment_id`),
    KEY `idx_returns_condition` (`condition`),
    KEY `idx_returns_date` (`return_date`),
    CONSTRAINT `fk_returns_transaction` FOREIGN KEY (`transaction_id`)
        REFERENCES `borrow_transactions` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_returns_equipment` FOREIGN KEY (`equipment_id`)
        REFERENCES `equipment` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_returns_processor` FOREIGN KEY (`processed_by`)
        REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `chk_return_quantity` CHECK (`quantity_returned` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `equipment_condition_history` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `equipment_id`        INT UNSIGNED NOT NULL,
    `return_record_id`    INT UNSIGNED NULL,
    `borrow_transaction_id` INT UNSIGNED NULL,
    `previous_condition`  ENUM('good','needs_maintenance','damaged','lost','incomplete') NOT NULL,
    `new_condition`       ENUM('good','needs_maintenance','damaged','lost','incomplete') NOT NULL,
    `quantity_affected`   INT UNSIGNED NOT NULL,
    `notes`               VARCHAR(500) NULL,
    `recorded_by`         INT UNSIGNED NULL,
    `recorded_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_condition_equipment` (`equipment_id`, `recorded_at`),
    KEY `idx_condition_return` (`return_record_id`),
    CONSTRAINT `fk_condition_equipment` FOREIGN KEY (`equipment_id`)
        REFERENCES `equipment` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_condition_return` FOREIGN KEY (`return_record_id`)
        REFERENCES `return_records` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_condition_transaction` FOREIGN KEY (`borrow_transaction_id`)
        REFERENCES `borrow_transactions` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_condition_user` FOREIGN KEY (`recorded_by`)
        REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `chk_condition_quantity` CHECK (`quantity_affected` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Notifications and audit trail
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `type`       VARCHAR(40) NOT NULL,
    `title`      VARCHAR(150) NOT NULL,
    `message`    VARCHAR(500) NOT NULL,
    `link`       VARCHAR(255) NULL,
    `is_read`      TINYINT(1) NOT NULL DEFAULT 0,
    `read_at`      DATETIME NULL,
    `dismissed_at` DATETIME NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_user_read` (`user_id`, `is_read`, `dismissed_at`, `created_at`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NULL,
    `action`      VARCHAR(60) NOT NULL,
    `entity_type` VARCHAR(40) NOT NULL,
    `entity_id`   INT UNSIGNED NULL,
    `description` VARCHAR(500) NOT NULL,
    `old_values`  JSON NULL,
    `new_values`  JSON NULL,
    `ip_address`  VARCHAR(45) NULL,
    `user_agent`  VARCHAR(255) NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_action` (`action`, `created_at`),
    KEY `idx_audit_entity` (`entity_type`, `entity_id`),
    KEY `idx_audit_user` (`user_id`, `created_at`),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Convenience views used by reports (optional; reports may query directly)
-- ---------------------------------------------------------------------------
CREATE OR REPLACE VIEW `v_equipment_inventory` AS
SELECT e.id, e.equipment_code, e.name, c.name AS category, e.total_quantity,
       e.available_quantity, e.borrowed_quantity, e.damaged_quantity,
       e.under_repair_quantity, e.lost_quantity, e.condition_status,
       e.storage_location, e.record_status, e.created_at, e.updated_at
FROM equipment e JOIN categories c ON c.id = e.category_id;

CREATE OR REPLACE VIEW `v_open_borrowings` AS
SELECT bt.id, bt.transaction_code, bt.user_id, u.school_id, CONCAT(u.first_name, ' ', u.last_name) AS borrower,
       bt.equipment_id, e.name AS equipment, bt.quantity, bt.quantity_returned,
       (bt.quantity - bt.quantity_returned) AS outstanding,
       bt.borrowed_at, bt.due_at, bt.status,
       CASE WHEN bt.due_at < NOW() THEN 'YES' ELSE 'NO' END AS is_overdue
FROM borrow_transactions bt
JOIN users u ON u.id = bt.user_id
JOIN equipment e ON e.id = bt.equipment_id
WHERE bt.status IN ('active','overdue','partially_returned');
