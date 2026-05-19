-- ================================================================
-- MIGRATION: Complete Unified Schema for Childcare Management System
-- Covers all 5 modules. Run AFTER the base childcare_db + schema_komunikasi.
-- Date: 2026-05-20
-- ================================================================

USE `childcare_db`;

-- ================================================================
-- FIX 1: Add missing status column to users (safe - skips if exists)
-- ================================================================
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA='childcare_db' AND TABLE_NAME='users' AND COLUMN_NAME='status');
SET @sql = IF(@col_exists = 0, 
    "ALTER TABLE `users` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'approved'", 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ================================================================
-- FIX 2: Add missing email/reset columns to users (safe)
-- ================================================================
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA='childcare_db' AND TABLE_NAME='users' AND COLUMN_NAME='email');
SET @sql = IF(@col_exists = 0, 
    "ALTER TABLE `users` ADD COLUMN `email` VARCHAR(150) NULL AFTER `username`", 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA='childcare_db' AND TABLE_NAME='users' AND COLUMN_NAME='reset_token');
SET @sql = IF(@col_exists = 0, 
    "ALTER TABLE `users` ADD COLUMN `reset_token` VARCHAR(64) NULL", 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA='childcare_db' AND TABLE_NAME='users' AND COLUMN_NAME='reset_token_expiry');
SET @sql = IF(@col_exists = 0, 
    "ALTER TABLE `users` ADD COLUMN `reset_token_expiry` DATETIME NULL", 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ================================================================
-- FIX 3: Add missing FK on activity_schedules.teacher_id
-- ================================================================
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA='childcare_db' AND TABLE_NAME='activity_schedules' 
    AND COLUMN_NAME='teacher_id' AND REFERENCED_TABLE_NAME IS NOT NULL);
SET @sql = IF(@fk_exists = 0, 
    "ALTER TABLE `activity_schedules` ADD KEY `fk_as_teacher` (`teacher_id`), ADD CONSTRAINT `fk_as_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE", 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ================================================================
-- FIX 4: Add missing FK on lesson_plans.teacher_id
-- ================================================================
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA='childcare_db' AND TABLE_NAME='lesson_plans' 
    AND COLUMN_NAME='teacher_id' AND REFERENCED_TABLE_NAME IS NOT NULL);
SET @sql = IF(@fk_exists = 0, 
    "ALTER TABLE `lesson_plans` ADD KEY `fk_lp_teacher` (`teacher_id`), ADD CONSTRAINT `fk_lp_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE", 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ================================================================
-- FIX 5: Add emergency_contact & relationship to parents
-- ================================================================
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA='childcare_db' AND TABLE_NAME='parents' AND COLUMN_NAME='emergency_contact');
SET @sql = IF(@col_exists = 0, 
    "ALTER TABLE `parents` ADD COLUMN `emergency_contact` VARCHAR(15) NULL, ADD COLUMN `relationship` VARCHAR(50) DEFAULT 'Ibu/Bapa'", 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ================================================================
-- FIX 6: Add specialization to teachers
-- ================================================================
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA='childcare_db' AND TABLE_NAME='teachers' AND COLUMN_NAME='specialization');
SET @sql = IF(@col_exists = 0, 
    "ALTER TABLE `teachers` ADD COLUMN `specialization` VARCHAR(100) NULL, ADD COLUMN `ic_number` VARCHAR(15) NULL", 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ================================================================
-- MODULE 1: Student & Academic — Milestone Tracking
-- ================================================================
CREATE TABLE IF NOT EXISTS `milestone_categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL COMMENT 'e.g. Motor Kasar, Motor Halus, Sosial, Bahasa',
  `description` TEXT NULL,
  `age_group` VARCHAR(50) NULL COMMENT 'e.g. 0-2 tahun, 3-4 tahun',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `milestones` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `category_id` INT(11) NOT NULL,
  `milestone_name` VARCHAR(255) NOT NULL,
  `status` ENUM('Belum Capai','Sedang Berkembang','Telah Capai') DEFAULT 'Belum Capai',
  `observed_date` DATE NULL,
  `teacher_id` INT(11) NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_ms_student` (`student_id`),
  KEY `fk_ms_category` (`category_id`),
  KEY `fk_ms_teacher` (`teacher_id`),
  CONSTRAINT `fk_ms_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ms_category` FOREIGN KEY (`category_id`) REFERENCES `milestone_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ms_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ================================================================
-- MODULE 2: Communication — Notifications
-- ================================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info','warning','success','alert') DEFAULT 'info',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `link` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_notif_user` (`user_id`),
  KEY `idx_notif_read` (`user_id`, `is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ================================================================
-- MODULE 3: Financial & Enrollment
-- ================================================================
CREATE TABLE IF NOT EXISTS `applications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `parent_id` INT(11) NOT NULL,
  `child_name` VARCHAR(100) NOT NULL,
  `child_mykid` VARCHAR(20) NOT NULL,
  `child_dob` DATE NOT NULL,
  `module` ENUM('Taska','Tadika','KAFA Care') NOT NULL,
  `health_record` TEXT NULL,
  `allergies` TEXT NULL,
  `status` ENUM('Pending','Approved','Rejected','Waitlisted') DEFAULT 'Pending',
  `waitlist_position` INT(11) NULL,
  `reviewed_by` INT(11) NULL COMMENT 'FK ke users.id (admin)',
  `reviewed_at` DATETIME NULL,
  `notes` TEXT NULL,
  `documents_verified` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_app_parent` (`parent_id`),
  KEY `fk_app_reviewer` (`reviewed_by`),
  KEY `idx_app_status` (`status`),
  CONSTRAINT `fk_app_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_app_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `fee_structures` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `module` ENUM('Taska','Tadika','KAFA Care') NOT NULL,
  `fee_name` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `frequency` ENUM('Monthly','Yearly','One-Time') DEFAULT 'Monthly',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` INT(11) NOT NULL,
  `parent_id` INT(11) NOT NULL,
  `amount_paid` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('FPX','Manual Transfer','Cash','Online') NOT NULL,
  `transaction_ref` VARCHAR(100) NULL,
  `receipt_file` VARCHAR(255) NULL,
  `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `verified_by` INT(11) NULL,
  `verified_at` DATETIME NULL,
  `status` ENUM('Pending','Verified','Rejected') DEFAULT 'Pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_pay_invoice` (`invoice_id`),
  KEY `fk_pay_parent` (`parent_id`),
  CONSTRAINT `fk_pay_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `expense_date` DATE NOT NULL,
  `receipt_file` VARCHAR(255) NULL,
  `recorded_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_exp_user` (`recorded_by`),
  CONSTRAINT `fk_exp_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ================================================================
-- MODULE 4: Operations & Resource Management
-- ================================================================
CREATE TABLE IF NOT EXISTS `staff` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `ic_number` VARCHAR(15) NULL,
  `phone_number` VARCHAR(15) NULL,
  `position` VARCHAR(100) NOT NULL,
  `department` ENUM('Teaching','Admin','Support','Kitchen','Transport') NOT NULL,
  `employment_type` ENUM('Full-Time','Part-Time','Contract') DEFAULT 'Full-Time',
  `hire_date` DATE NULL,
  `status` ENUM('Active','Inactive','Terminated') DEFAULT 'Active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_staff_user` (`user_id`),
  CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `payroll` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `staff_id` INT(11) NOT NULL,
  `month` VARCHAR(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `basic_salary` DECIMAL(10,2) NOT NULL,
  `allowances` DECIMAL(10,2) DEFAULT 0.00,
  `deductions` DECIMAL(10,2) DEFAULT 0.00,
  `net_salary` DECIMAL(10,2) NOT NULL,
  `payment_status` ENUM('Pending','Paid') DEFAULT 'Pending',
  `paid_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payroll` (`staff_id`, `month`),
  KEY `fk_pr_staff` (`staff_id`),
  CONSTRAINT `fk_pr_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `transportation` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `route_name` VARCHAR(100) NOT NULL,
  `vehicle_plate` VARCHAR(20) NOT NULL,
  `driver_name` VARCHAR(100) NOT NULL,
  `driver_phone` VARCHAR(15) NOT NULL,
  `capacity` INT(11) DEFAULT 20,
  `status` ENUM('Active','Inactive') DEFAULT 'Active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `student_transport` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `route_id` INT(11) NOT NULL,
  `pickup_address` TEXT NULL,
  `pickup_time` TIME NULL,
  `dropoff_time` TIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_st_student` (`student_id`),
  KEY `fk_st_route` (`route_id`),
  CONSTRAINT `fk_st_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_st_route` FOREIGN KEY (`route_id`) REFERENCES `transportation` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `bus_tracking` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `route_id` INT(11) NOT NULL,
  `latitude` DECIMAL(10,8) NULL,
  `longitude` DECIMAL(11,8) NULL,
  `eta_minutes` INT(11) NULL,
  `status` ENUM('En Route','Arrived','Completed') DEFAULT 'En Route',
  `tracked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_bt_route` (`route_id`),
  CONSTRAINT `fk_bt_route` FOREIGN KEY (`route_id`) REFERENCES `transportation` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `meal_plans` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `meal_date` DATE NOT NULL,
  `meal_type` ENUM('Breakfast','Lunch','Snack') NOT NULL,
  `menu_description` TEXT NOT NULL,
  `allergens` TEXT NULL COMMENT 'Known allergens in this meal',
  `class_id` INT(11) NULL,
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_mp_class` (`class_id`),
  KEY `fk_mp_creator` (`created_by`),
  KEY `idx_mp_date` (`meal_date`),
  CONSTRAINT `fk_mp_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mp_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `inventory` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `item_name` VARCHAR(150) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `quantity` INT(11) NOT NULL DEFAULT 0,
  `unit` VARCHAR(30) DEFAULT 'pcs',
  `min_stock_level` INT(11) DEFAULT 5,
  `location` VARCHAR(100) NULL,
  `last_restocked` DATE NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `inventory_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `requested_by` INT(11) NOT NULL COMMENT 'FK ke users.id (teacher)',
  `item_name` VARCHAR(150) NOT NULL,
  `quantity` INT(11) NOT NULL DEFAULT 1,
  `reason` TEXT NULL,
  `status` ENUM('Pending','Approved','Rejected','Fulfilled') DEFAULT 'Pending',
  `approved_by` INT(11) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_ir_requester` (`requested_by`),
  CONSTRAINT `fk_ir_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `facility_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `requested_by` INT(11) NOT NULL,
  `location` VARCHAR(150) NOT NULL,
  `issue_description` TEXT NOT NULL,
  `priority` ENUM('Low','Medium','High','Urgent') DEFAULT 'Medium',
  `status` ENUM('Pending','In Progress','Completed') DEFAULT 'Pending',
  `photo_path` VARCHAR(255) NULL,
  `resolved_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_fr_requester` (`requested_by`),
  CONSTRAINT `fk_fr_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ================================================================
-- MODULE 5: Dashboard, Security & System Admin
-- ================================================================
CREATE TABLE IF NOT EXISTS `checkin_checkout` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `checkin_time` DATETIME NULL,
  `checkout_time` DATETIME NULL,
  `checkin_by` INT(11) NULL COMMENT 'User who checked in the child',
  `checkout_by` INT(11) NULL COMMENT 'User who checked out the child',
  `guardian_name` VARCHAR(100) NULL,
  `date` DATE NOT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_cio_student` (`student_id`),
  KEY `fk_cio_checkin_by` (`checkin_by`),
  KEY `idx_cio_date` (`date`),
  CONSTRAINT `fk_cio_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cio_checkin_by` FOREIGN KEY (`checkin_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `visitor_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `visitor_name` VARCHAR(100) NOT NULL,
  `ic_number` VARCHAR(15) NULL,
  `purpose` VARCHAR(255) NOT NULL,
  `visiting_whom` VARCHAR(100) NULL,
  `check_in` DATETIME NOT NULL,
  `check_out` DATETIME NULL,
  `badge_number` VARCHAR(20) NULL,
  `recorded_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_vl_recorder` (`recorded_by`),
  CONSTRAINT `fk_vl_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `authorized_guardians` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `guardian_name` VARCHAR(100) NOT NULL,
  `relationship` VARCHAR(50) NOT NULL,
  `ic_number` VARCHAR(15) NOT NULL,
  `phone_number` VARCHAR(15) NOT NULL,
  `photo_path` VARCHAR(255) NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `added_by` INT(11) NOT NULL COMMENT 'Parent who added this guardian',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_ag_student` (`student_id`),
  KEY `fk_ag_addedby` (`added_by`),
  CONSTRAINT `fk_ag_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ag_addedby` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ================================================================
-- SEED DATA: Milestone Categories
-- ================================================================
INSERT IGNORE INTO `milestone_categories` (`id`, `category_name`, `description`, `age_group`) VALUES
(1, 'Motor Kasar', 'Perkembangan pergerakan besar (berjalan, melompat)', '0-6 tahun'),
(2, 'Motor Halus', 'Perkembangan pergerakan halus (menulis, melukis)', '2-6 tahun'),
(3, 'Bahasa & Komunikasi', 'Perkembangan pertuturan dan komunikasi', '0-6 tahun'),
(4, 'Sosial & Emosi', 'Perkembangan sosial dan pengurusan emosi', '0-6 tahun'),
(5, 'Kognitif', 'Perkembangan pemikiran dan penyelesaian masalah', '2-6 tahun'),
(6, 'Penjagaan Diri', 'Kebolehan menjaga diri sendiri (makan, pakaian)', '2-6 tahun');

-- ================================================================
-- SEED DATA: Fee Structures
-- ================================================================
INSERT IGNORE INTO `fee_structures` (`id`, `module`, `fee_name`, `amount`, `frequency`) VALUES
(1, 'Taska', 'Yuran Bulanan Taska', 350.00, 'Monthly'),
(2, 'Tadika', 'Yuran Bulanan Tadika', 250.00, 'Monthly'),
(3, 'KAFA Care', 'Yuran Bulanan KAFA Care', 200.00, 'Monthly'),
(4, 'Taska', 'Yuran Pendaftaran', 150.00, 'One-Time'),
(5, 'Tadika', 'Yuran Pendaftaran', 100.00, 'One-Time'),
(6, 'KAFA Care', 'Yuran Pendaftaran', 80.00, 'One-Time');

-- ================================================================
-- SEED DATA: Sample Transportation Routes
-- ================================================================
INSERT IGNORE INTO `transportation` (`id`, `route_name`, `vehicle_plate`, `driver_name`, `driver_phone`) VALUES
(1, 'Laluan A - Taman Melati', 'WKL 1234', 'Encik Razak', '013-4567890'),
(2, 'Laluan B - Bandar Baru', 'WKL 5678', 'Encik Hakim', '014-9876543');

-- ================================================================
-- SEED DATA: Sample Inventory Items
-- ================================================================
INSERT IGNORE INTO `inventory` (`id`, `item_name`, `category`, `quantity`, `unit`, `min_stock_level`) VALUES
(1, 'Kertas Lukisan A4', 'Alat Tulis', 500, 'helai', 100),
(2, 'Krayon Warna (12 warna)', 'Alat Tulis', 30, 'kotak', 10),
(3, 'Susu Kotak (200ml)', 'Makanan', 100, 'kotak', 30),
(4, 'Sabun Tangan', 'Kebersihan', 20, 'botol', 5),
(5, 'Tuala Kecil', 'Kebersihan', 50, 'helai', 15);

-- ================================================================
-- SEED DATA: Sample Meal Plans
-- ================================================================
INSERT IGNORE INTO `meal_plans` (`id`, `meal_date`, `meal_type`, `menu_description`, `allergens`, `created_by`) VALUES
(1, CURDATE(), 'Breakfast', 'Roti bakar + susu', 'Gluten, Lactose', 2),
(2, CURDATE(), 'Lunch', 'Nasi + ayam masak merah + sayur bayam', NULL, 2),
(3, CURDATE(), 'Snack', 'Biskut + jus oren', 'Gluten', 2);

-- ================================================================
-- DONE
-- ================================================================
SELECT 'Migration completed successfully!' AS result;
