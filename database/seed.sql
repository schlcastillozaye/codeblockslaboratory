-- ============================================================================
-- Code Blocks Laboratory Equipment Borrowing System - demo seed data
--
-- IMPORTANT: this file RESETS the demo tables (it deletes their rows first).
-- It contains fictional users and equipment only. Demo passwords are for a
-- local classroom environment and MUST be changed before any real deployment.
--
--   Administrator : admin@codeblocks.test      / Admin@12345
--   Instructor    : maria.santos@codeblocks.test / Instructor@12345
--   Student       : juan.delacruz@codeblocks.test / Student@12345
--   Student       : ana.reyes@codeblocks.test   / Student@12345
-- ============================================================================

USE `codeblocks_lab_inventory`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `equipment_condition_history`;
DELETE FROM `return_records`;
DELETE FROM `borrow_transactions`;
DELETE FROM `borrow_request_items`;
DELETE FROM `borrow_requests`;
DELETE FROM `notifications`;
DELETE FROM `audit_logs`;
DELETE FROM `login_attempts`;
DELETE FROM `equipment`;
DELETE FROM `categories`;
DELETE FROM `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- Users (fictional)
-- ---------------------------------------------------------------------------
INSERT INTO `users`
    (`id`, `school_id`, `first_name`, `last_name`, `email`, `password_hash`, `role`, `department`, `phone`, `status`, `last_login_at`, `created_at`)
VALUES
    (1, 'ADMIN-001', 'Elena', 'Marquez', 'admin@codeblocks.test', '$2y$10$0Zw6v1tEQWN3PuORNGqcq.SyjowSnMcO.zfZxbQp0DZvyWh.sjoki', 'admin', 'Laboratory Management Office', '0917-000-1001', 'active', DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 90 DAY)),
    (2, 'INST-1001', 'Maria', 'Santos', 'maria.santos@codeblocks.test', '$2y$10$FmklAAzLUWjvyyxCOnocqOEkCZJ6rvEsjNMedvwrBtydndVNeIRjm', 'instructor', 'College of Engineering', '0917-000-2001', 'active', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 80 DAY)),
    (3, '2023-00123', 'Juan', 'Dela Cruz', 'juan.delacruz@codeblocks.test', '$2y$10$Q2K4uVO9LRTAtIIXvGTocu3acfk.7KmDUwHj2dqmZlD8IE7wd5SBe', 'student', 'BS Information Technology', '0917-000-3001', 'active', DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 70 DAY)),
    (4, '2023-00456', 'Ana', 'Reyes', 'ana.reyes@codeblocks.test', '$2y$10$Q2K4uVO9LRTAtIIXvGTocu3acfk.7KmDUwHj2dqmZlD8IE7wd5SBe', 'student', 'BS Computer Science', '0917-000-4001', 'active', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 65 DAY)),
    (5, 'INST-1002', 'Paolo', 'Garcia', 'paolo.garcia@codeblocks.test', '$2y$10$FmklAAzLUWjvyyxCOnocqOEkCZJ6rvEsjNMedvwrBtydndVNeIRjm', 'instructor', 'College of Engineering', '0917-000-5001', 'active', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 60 DAY)),
    (6, '2022-00999', 'Mia', 'Torres', 'mia.torres@codeblocks.test', '$2y$10$Q2K4uVO9LRTAtIIXvGTocu3acfk.7KmDUwHj2dqmZlD8IE7wd5SBe', 'student', 'BS Information Systems', '0917-000-6001', 'inactive', NULL, DATE_SUB(NOW(), INTERVAL 120 DAY));

-- ---------------------------------------------------------------------------
-- Categories
-- ---------------------------------------------------------------------------
INSERT INTO `categories` (`id`, `name`, `description`) VALUES
    (1, 'Microscopy', 'Optical microscopes, slides, and microscopy accessories'),
    (2, 'Electronics & Circuits', 'Meters, oscilloscopes, soldering, and circuit tools'),
    (3, 'Chemistry & Analysis', 'Balances, meters, sterilizers, and lab glassware'),
    (4, 'Computing & Networking', 'Laptops, network devices, and microcontroller kits'),
    (5, 'Measurement & Testing', 'Projectors, thermal cameras, and precision instruments'),
    (6, 'General Laboratory', 'Burners, safety stations, and general-purpose supplies');

-- ---------------------------------------------------------------------------
-- Equipment
-- total = available + borrowed + damaged + under_repair + lost
-- ---------------------------------------------------------------------------
INSERT INTO `equipment`
    (`id`, `equipment_code`, `name`, `category_id`, `description`, `total_quantity`,
     `available_quantity`, `borrowed_quantity`, `damaged_quantity`, `under_repair_quantity`, `lost_quantity`,
     `condition_status`, `storage_location`, `barcode`, `record_status`, `created_at`)
VALUES
    (1, 'BIO-001', 'Binocular Microscope', 1, 'LED-illuminated binocular microscope with 40x–1000x magnification. Includes dust cover and carrying case.', 10, 7, 2, 1, 0, 0, 'good', 'Science Lab A – Cabinet 3', 'CB-BIO-001', 'active', DATE_SUB(NOW(), INTERVAL 85 DAY)),
    (2, 'BIO-002', 'Stereo Microscope', 1, 'Low-power stereo microscope for dissection and material inspection.', 4, 4, 0, 0, 0, 0, 'good', 'Science Lab A – Cabinet 4', 'CB-BIO-002', 'active', DATE_SUB(NOW(), INTERVAL 84 DAY)),
    (3, 'ELE-001', 'Digital Multimeter', 2, 'True-RMS digital multimeter with voltage, current, resistance, and continuity modes.', 12, 9, 2, 1, 0, 0, 'good', 'Electronics Lab – Tool Room B', 'CB-ELE-001', 'active', DATE_SUB(NOW(), INTERVAL 83 DAY)),
    (4, 'ELE-002', 'Digital Oscilloscope', 2, 'Four-channel 100 MHz digital oscilloscope with probes and USB export.', 6, 4, 1, 0, 1, 0, 'good', 'Electronics Lab – Bench 2', 'CB-ELE-002', 'active', DATE_SUB(NOW(), INTERVAL 82 DAY)),
    (5, 'ELE-003', 'Soldering Station', 2, 'Temperature-controlled soldering station with spare tips and fume extractor.', 10, 10, 0, 0, 0, 0, 'good', 'Electronics Lab – Tool Room B', 'CB-ELE-003', 'active', DATE_SUB(NOW(), INTERVAL 81 DAY)),
    (6, 'CHM-001', 'Analytical Balance', 3, 'Precision analytical balance, 0.0001 g readability, with calibration weights.', 4, 3, 1, 0, 0, 0, 'good', 'Chemistry Lab – Instrument Room', 'CB-CHM-001', 'active', DATE_SUB(NOW(), INTERVAL 80 DAY)),
    (7, 'CHM-002', 'Benchtop pH Meter', 3, 'Digital pH meter with temperature compensation and replacement electrode.', 6, 5, 0, 0, 0, 1, 'good', 'Chemistry Lab – Instrument Room', 'CB-CHM-002', 'active', DATE_SUB(NOW(), INTERVAL 79 DAY)),
    (8, 'CHM-003', 'Autoclave Sterilizer', 3, '25 L benchtop autoclave. Currently withdrawn from service pending seal replacement.', 2, 0, 0, 2, 0, 0, 'damaged', 'Chemistry Lab – Sterilization Bay', 'CB-CHM-003', 'active', DATE_SUB(NOW(), INTERVAL 78 DAY)),
    (9, 'CHM-004', 'Laboratory Centrifuge', 3, 'Benchtop centrifuge, 8 × 15 mL rotor. Under maintenance due to motor noise.', 3, 0, 0, 0, 3, 0, 'needs_maintenance', 'Chemistry Lab – Instrument Room', 'CB-CHM-004', 'active', DATE_SUB(NOW(), INTERVAL 77 DAY)),
    (10, 'CHM-005', 'Glassware Set', 3, 'Borosilicate beakers, flasks, and graduated cylinders in a storage rack.', 25, 25, 0, 0, 0, 0, 'good', 'Chemistry Lab – Cabinet 1', 'CB-CHM-005', 'active', DATE_SUB(NOW(), INTERVAL 76 DAY)),
    (11, 'ICT-001', 'Student Laptop', 4, 'Laboratory laptop with 16 GB RAM and development tools preinstalled.', 15, 12, 3, 0, 0, 0, 'good', 'ICT Laboratory – Charging Cart', 'CB-ICT-001', 'active', DATE_SUB(NOW(), INTERVAL 75 DAY)),
    (12, 'ICT-002', '24-Port Network Switch', 4, 'Managed 24-port gigabit switch reserved for the networking capstone laboratory.', 4, 4, 0, 0, 0, 0, 'good', 'ICT Laboratory – Storage Cage', 'CB-ICT-002', 'archived', DATE_SUB(NOW(), INTERVAL 74 DAY)),
    (13, 'ICT-003', 'Arduino Starter Kit', 4, 'Arduino Uno kit with breadboard, sensors, jumper wires, and project guide.', 20, 15, 5, 0, 0, 0, 'good', 'ICT Laboratory – Cabinet 2', 'CB-ICT-003', 'active', DATE_SUB(NOW(), INTERVAL 73 DAY)),
    (14, 'MEA-001', 'Portable Projector', 5, 'Full HD portable projector with HDMI and VGA inputs.', 5, 4, 1, 0, 0, 0, 'good', 'AV Room – Shelf 1', 'CB-MEA-001', 'active', DATE_SUB(NOW(), INTERVAL 72 DAY)),
    (15, 'MEA-002', 'Thermal Imaging Camera', 5, 'Handheld thermal camera for electronics fault inspection.', 2, 2, 0, 0, 0, 0, 'good', 'Electronics Lab – Locked Cabinet', 'CB-MEA-002', 'active', DATE_SUB(NOW(), INTERVAL 71 DAY)),
    (16, 'MEA-003', 'Digital Vernier Caliper', 5, 'Stainless steel digital caliper, 0.01 mm resolution.', 10, 10, 0, 0, 0, 0, 'good', 'Measurement Lab – Drawer 2', 'CB-MEA-003', 'active', DATE_SUB(NOW(), INTERVAL 70 DAY)),
    (17, 'GEN-001', 'Bunsen Burner', 6, 'Laboratory gas burner with flame control valve and safety tubing.', 8, 8, 0, 0, 0, 0, 'good', 'Chemistry Lab – Cabinet 5', 'CB-GEN-001', 'active', DATE_SUB(NOW(), INTERVAL 69 DAY)),
    (18, 'GEN-002', 'Emergency Eyewash Station', 6, 'Wall-mounted eyewash station with weekly flush log.', 3, 3, 0, 0, 0, 0, 'good', 'Science Lab A – Safety Corner', 'CB-GEN-002', 'active', DATE_SUB(NOW(), INTERVAL 68 DAY));

-- ---------------------------------------------------------------------------
-- Borrowing requests
-- ---------------------------------------------------------------------------
INSERT INTO `borrow_requests`
    (`id`, `request_code`, `user_id`, `purpose`, `expected_return_date`, `status`, `reviewer_id`, `reviewed_at`, `rejection_reason`, `created_at`)
VALUES
    (1, 'REQ-2026-0001', 2, 'Microscopy demonstration for the Grade 11 immersion class.', DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'approved', 1, DATE_SUB(NOW(), INTERVAL 3 DAY), NULL, DATE_SUB(NOW(), INTERVAL 4 DAY)),
    (2, 'REQ-2026-0002', 2, 'Mobile application development workshop for third-year students.', DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'approved', 1, DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
    (3, 'REQ-2026-0003', 3, 'Electronics laboratory activity on series and parallel circuits.', DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'approved', 1, DATE_SUB(NOW(), INTERVAL 10 DAY), NULL, DATE_SUB(NOW(), INTERVAL 11 DAY)),
    (4, 'REQ-2026-0004', 4, 'IoT prototype build for the SIA final project demonstration.', DATE_ADD(CURDATE(), INTERVAL 6 DAY), 'approved', 1, DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, DATE_SUB(NOW(), INTERVAL 3 DAY)),
    (5, 'REQ-2026-0005', 5, 'Signal analysis and precision measurement laboratory session.', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'approved', 1, DATE_SUB(NOW(), INTERVAL 4 DAY), NULL, DATE_SUB(NOW(), INTERVAL 5 DAY)),
    (6, 'REQ-2026-0006', 3, 'Capstone project presentation rehearsal and documentation defense.', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'approved', 1, DATE_SUB(NOW(), INTERVAL 5 DAY), NULL, DATE_SUB(NOW(), INTERVAL 6 DAY)),
    (7, 'REQ-2026-0007', 4, 'Microscopy practical examination preparation.', DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'completed', 1, DATE_SUB(NOW(), INTERVAL 12 DAY), NULL, DATE_SUB(NOW(), INTERVAL 13 DAY)),
    (8, 'REQ-2026-0008', 3, 'Biology laboratory activity on plant cell structures.', DATE_SUB(CURDATE(), INTERVAL 25 DAY), 'completed', 1, DATE_SUB(NOW(), INTERVAL 30 DAY), NULL, DATE_SUB(NOW(), INTERVAL 31 DAY)),
    (9, 'REQ-2026-0009', 2, 'Electrical measurement refresher for laboratory assistants.', DATE_SUB(CURDATE(), INTERVAL 35 DAY), 'completed', 1, DATE_SUB(NOW(), INTERVAL 40 DAY), NULL, DATE_SUB(NOW(), INTERVAL 41 DAY)),
    (10, 'REQ-2026-0010', 4, 'Comparison of specimen slides for the integrative project.', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'pending', NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
    (11, 'REQ-2026-0011', 5, 'Preparing a programming laboratory session for next week.', DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'pending', NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 6 HOUR)),
    (12, 'REQ-2026-0012', 3, 'Blood separation exercise for the chemistry elective.', DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'rejected', 1, DATE_SUB(NOW(), INTERVAL 6 DAY), 'The centrifuge is under maintenance. Please use the alternative unit once it is released by the service technician.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
    (13, 'REQ-2026-0013', 4, 'General glassware preparation for a weekend review class.', DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'cancelled', NULL, DATE_SUB(NOW(), INTERVAL 9 DAY), NULL, DATE_SUB(NOW(), INTERVAL 9 DAY));

-- ---------------------------------------------------------------------------
-- Request items
-- ---------------------------------------------------------------------------
INSERT INTO `borrow_request_items`
    (`id`, `request_id`, `equipment_id`, `quantity_requested`, `quantity_approved`)
VALUES
    (1, 1, 1, 2, 2),
    (2, 2, 11, 3, 3),
    (3, 3, 3, 2, 2),
    (4, 4, 13, 5, 5),
    (5, 5, 4, 1, 1),
    (6, 5, 6, 1, 1),
    (7, 6, 14, 1, 1),
    (8, 7, 1, 1, 1),
    (9, 8, 1, 1, 1),
    (10, 9, 3, 1, 1),
    (11, 10, 1, 2, NULL),
    (12, 11, 11, 1, NULL),
    (13, 12, 9, 1, NULL),
    (14, 13, 10, 1, NULL);

-- ---------------------------------------------------------------------------
-- Transactions (approved requests become actual checkouts)
-- ---------------------------------------------------------------------------
INSERT INTO `borrow_transactions`
    (`id`, `transaction_code`, `request_id`, `request_item_id`, `user_id`, `equipment_id`, `quantity`,
     `quantity_returned`, `borrowed_at`, `due_at`, `returned_at`, `status`, `processed_by`)
VALUES
    (1, 'TX-2026-0001', 1, 1, 2, 1, 2, 0, DATE_SUB(NOW(), INTERVAL 3 DAY), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 4 DAY), '17:00:00'), NULL, 'active', 1),
    (2, 'TX-2026-0002', 2, 2, 2, 11, 3, 0, DATE_SUB(NOW(), INTERVAL 1 DAY), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 2 DAY), '17:00:00'), NULL, 'active', 1),
    (3, 'TX-2026-0003', 3, 3, 3, 3, 2, 0, DATE_SUB(NOW(), INTERVAL 10 DAY), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '17:00:00'), NULL, 'overdue', 1),
    (4, 'TX-2026-0004', 4, 4, 4, 13, 5, 0, DATE_SUB(NOW(), INTERVAL 2 DAY), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 6 DAY), '17:00:00'), NULL, 'active', 1),
    (5, 'TX-2026-0005', 5, 5, 5, 4, 1, 0, DATE_SUB(NOW(), INTERVAL 4 DAY), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 3 DAY), '17:00:00'), NULL, 'active', 1),
    (6, 'TX-2026-0006', 5, 6, 5, 6, 1, 0, DATE_SUB(NOW(), INTERVAL 4 DAY), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 3 DAY), '17:00:00'), NULL, 'active', 1),
    (7, 'TX-2026-0007', 6, 7, 3, 14, 1, 0, DATE_SUB(NOW(), INTERVAL 5 DAY), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '17:00:00'), NULL, 'active', 1),
    (8, 'TX-2026-0008', 7, 8, 4, 1, 1, 1, DATE_SUB(NOW(), INTERVAL 12 DAY), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '17:00:00'), DATE_SUB(NOW(), INTERVAL 11 DAY), 'returned', 1),
    (9, 'TX-2026-0009', 8, 9, 3, 1, 1, 1, DATE_SUB(NOW(), INTERVAL 30 DAY), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 25 DAY), '17:00:00'), DATE_SUB(NOW(), INTERVAL 24 DAY), 'returned', 1),
    (10, 'TX-2026-0010', 9, 10, 2, 3, 1, 1, DATE_SUB(NOW(), INTERVAL 40 DAY), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 35 DAY), '17:00:00'), DATE_SUB(NOW(), INTERVAL 34 DAY), 'returned', 1);

-- ---------------------------------------------------------------------------
-- Return records (condition is mandatory)
-- ---------------------------------------------------------------------------
INSERT INTO `return_records`
    (`id`, `return_code`, `transaction_id`, `equipment_id`, `quantity_returned`, `return_date`, `condition`, `notes`, `processed_by`, `created_at`)
VALUES
    (1, 'RET-2026-0001', 8, 1, 1, DATE_SUB(CURDATE(), INTERVAL 11 DAY), 'good', 'Complete set returned; lenses, stage, and carrying case intact.', 1, DATE_SUB(NOW(), INTERVAL 11 DAY)),
    (2, 'RET-2026-0002', 9, 1, 1, DATE_SUB(CURDATE(), INTERVAL 24 DAY), 'damaged', 'Objective lens cracked. Unit forwarded to the repair shelf.', 1, DATE_SUB(NOW(), INTERVAL 24 DAY)),
    (3, 'RET-2026-0003', 10, 3, 1, DATE_SUB(CURDATE(), INTERVAL 34 DAY), 'damaged', 'Display flickering and one probe terminal loose. Display module damaged.', 1, DATE_SUB(NOW(), INTERVAL 34 DAY));

-- ---------------------------------------------------------------------------
-- Equipment condition history
-- ---------------------------------------------------------------------------
INSERT INTO `equipment_condition_history`
    (`id`, `equipment_id`, `return_record_id`, `borrow_transaction_id`, `previous_condition`, `new_condition`,
     `quantity_affected`, `notes`, `recorded_by`, `recorded_at`)
VALUES
    (1, 1, 1, 8, 'good', 'good', 1, 'Return inspection passed: lenses, stage, and case intact.', 1, DATE_SUB(NOW(), INTERVAL 11 DAY)),
    (2, 1, 2, 9, 'good', 'damaged', 1, 'Objective lens cracked on return. Moved to damaged stock.', 1, DATE_SUB(NOW(), INTERVAL 24 DAY)),
    (3, 3, 3, 10, 'good', 'damaged', 1, 'Display flickering; unit moved to damaged stock.', 1, DATE_SUB(NOW(), INTERVAL 34 DAY)),
    (4, 8, NULL, NULL, 'good', 'damaged', 2, 'Pressure seal failure found during quarterly inspection. Withdrawn from service.', 1, DATE_SUB(NOW(), INTERVAL 20 DAY)),
    (5, 9, NULL, NULL, 'good', 'needs_maintenance', 3, 'Motor noise and rotor imbalance detected. Scheduled for maintenance.', 1, DATE_SUB(NOW(), INTERVAL 15 DAY)),
    (6, 7, NULL, NULL, 'good', 'lost', 1, 'One unit declared lost after the physical inventory count. Documented write-off.', 1, DATE_SUB(NOW(), INTERVAL 30 DAY));

-- ---------------------------------------------------------------------------
-- In-app notifications (local only; no email/SMS delivery)
-- ---------------------------------------------------------------------------
INSERT INTO `notifications`
    (`user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`)
VALUES
    (1, 'borrow_request', 'New borrowing request', 'Ana Reyes submitted REQ-2026-0010 for 2 × Binocular Microscope.', 'borrow/10', 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
    (1, 'borrow_request', 'New borrowing request', 'Paolo Garcia submitted REQ-2026-0011 for 1 × Student Laptop.', 'borrow/11', 0, DATE_SUB(NOW(), INTERVAL 6 HOUR)),
    (1, 'condition', 'Equipment condition attention', 'Autoclave Sterilizer has 2 damaged unit(s) awaiting service.', 'equipment/8', 0, DATE_SUB(NOW(), INTERVAL 20 DAY)),
    (4, 'borrow_request', 'Request submitted', 'Your request REQ-2026-0010 is pending laboratory personnel review.', 'borrow/10', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
    (4, 'approval', 'Borrowing request approved', 'REQ-2026-0004 was approved. 5 × Arduino Starter Kit checked out.', 'borrow/4', 0, DATE_SUB(NOW(), INTERVAL 2 DAY)),
    (4, 'return', 'Return recorded', 'RET-2026-0001: 1 unit of Binocular Microscope recorded as Good / Working.', 'borrow/7', 1, DATE_SUB(NOW(), INTERVAL 11 DAY)),
    (3, 'due_soon', 'Equipment overdue', CONCAT('Digital Multimeter was due on ', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '%b %d, %Y'), '. Please return it immediately.'), 'transactions', 0, DATE_SUB(NOW(), INTERVAL 2 DAY)),
    (3, 'rejection', 'Borrowing request rejected', 'REQ-2026-0012 was rejected. Reason: The centrifuge is under maintenance.', 'borrow/12', 1, DATE_SUB(NOW(), INTERVAL 6 DAY)),
    (5, 'approval', 'Borrowing request approved', 'REQ-2026-0005 was approved. 1 × Digital Oscilloscope and 1 × Analytical Balance checked out.', 'borrow/5', 0, DATE_SUB(NOW(), INTERVAL 4 DAY));

-- ---------------------------------------------------------------------------
-- Audit trail samples
-- ---------------------------------------------------------------------------
INSERT INTO `audit_logs`
    (`user_id`, `action`, `entity_type`, `entity_id`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`)
VALUES
    (1, 'auth.login', 'user', 1, 'User signed in: admin@codeblocks.test', NULL, NULL, '127.0.0.1', 'Seed data', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
    (4, 'borrow.submitted', 'borrow_request', 10, 'Ana Reyes submitted REQ-2026-0010 for 2 × Binocular Microscope.', NULL, '{"status":"pending"}', '127.0.0.1', 'Seed data', DATE_SUB(NOW(), INTERVAL 1 DAY)),
    (1, 'borrow.approved', 'borrow_request', 4, 'Approved REQ-2026-0004 and created checkout: 5 × Arduino Starter Kit (TX-2026-0004).', '{"status":"pending"}', '{"status":"approved"}', '127.0.0.1', 'Seed data', DATE_SUB(NOW(), INTERVAL 2 DAY)),
    (1, 'return.processed', 'return_record', 2, 'Return RET-2026-0002: 1 × Binocular Microscope recorded as Damaged (transaction TX-2026-0009).', NULL, '{"condition":"damaged"}', '127.0.0.1', 'Seed data', DATE_SUB(NOW(), INTERVAL 24 DAY)),
    (1, 'equipment.condition_changed', 'equipment', 8, 'Adjusted Autoclave Sterilizer: 2 unit(s) moved from available to damaged.', NULL, NULL, '127.0.0.1', 'Seed data', DATE_SUB(NOW(), INTERVAL 20 DAY)),
    (1, 'borrow.rejected', 'borrow_request', 12, 'Rejected REQ-2026-0012. Reason: The centrifuge is under maintenance.', '{"status":"pending"}', '{"status":"rejected"}', '127.0.0.1', 'Seed data', DATE_SUB(NOW(), INTERVAL 6 DAY)),
    (4, 'borrow.cancelled', 'borrow_request', 13, 'Cancelled REQ-2026-0013.', '{"status":"pending"}', '{"status":"cancelled"}', '127.0.0.1', 'Seed data', DATE_SUB(NOW(), INTERVAL 9 DAY)),
    (1, 'auth.login_failed', 'user', NULL, 'Wrong password for faculty account maria.santos@codeblocks.test', NULL, NULL, '127.0.0.1', 'Seed data', DATE_SUB(NOW(), INTERVAL 2 DAY)),
    (1, 'equipment.updated', 'equipment', 12, 'Updated equipment 24-Port Network Switch (ICT-002).', NULL, '{"record_status":"archived"}', '127.0.0.1', 'Seed data', DATE_SUB(NOW(), INTERVAL 10 DAY));
