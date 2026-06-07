-- ============================================================
-- MIGRATION: Financial & Enrollment System Upgrade
-- Database: childcare_db
-- Run this script ONCE against your existing database.
-- ============================================================

-- ============================================================
-- 1. ALTER EXISTING TABLES
-- ============================================================

-- 1a. students — add documents_verified, enrollment_date, date_of_birth
ALTER TABLE `students`
  ADD COLUMN `documents_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
  ADD COLUMN `enrollment_date` DATE DEFAULT NULL AFTER `documents_verified`,
  ADD COLUMN `date_of_birth` DATE DEFAULT NULL AFTER `mykid_number`;

-- 1b. invoices — add due_date, paid_at, notes, generated_by
ALTER TABLE `invoices`
  ADD COLUMN `due_date` DATE DEFAULT NULL AFTER `status`,
  ADD COLUMN `paid_at` DATETIME DEFAULT NULL AFTER `due_date`,
  ADD COLUMN `notes` TEXT DEFAULT NULL AFTER `paid_at`,
  ADD COLUMN `generated_by` INT(11) DEFAULT NULL AFTER `notes`;

-- 1c. teachers — add ic_number, address, qualification, experience, specialization, email, status
ALTER TABLE `teachers`
  ADD COLUMN `ic_number` VARCHAR(15) DEFAULT NULL AFTER `full_name`,
  ADD COLUMN `email` VARCHAR(100) DEFAULT NULL AFTER `phone_number`,
  ADD COLUMN `address` TEXT DEFAULT NULL AFTER `email`,
  ADD COLUMN `qualification` VARCHAR(255) DEFAULT NULL AFTER `address`,
  ADD COLUMN `experience` VARCHAR(50) DEFAULT NULL AFTER `qualification`,
  ADD COLUMN `specialization` VARCHAR(255) DEFAULT NULL AFTER `experience`,
  ADD COLUMN `status` ENUM('Pending','Active','Inactive') NOT NULL DEFAULT 'Active' AFTER `specialization`;

-- ============================================================
-- 2. CREATE NEW TABLES
-- ============================================================

-- 2a. student_documents — Track uploaded documents and verification
CREATE TABLE IF NOT EXISTS `student_documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `document_type` ENUM('MyKid Copy','Birth Certificate','Health Record','Vaccination Card','Photo') NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `original_filename` VARCHAR(255) DEFAULT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `verified_by` INT(11) DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `verification_status` ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
  `rejection_reason` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_sd_student` (`student_id`),
  KEY `fk_sd_verifier` (`verified_by`),
  CONSTRAINT `fk_sd_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sd_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2b. enrollment_history — Audit trail for enrollment status changes
CREATE TABLE IF NOT EXISTS `enrollment_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `from_status` VARCHAR(50) DEFAULT NULL,
  `to_status` VARCHAR(50) NOT NULL,
  `changed_by` INT(11) DEFAULT NULL,
  `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_eh_student` (`student_id`),
  KEY `fk_eh_user` (`changed_by`),
  CONSTRAINT `fk_eh_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_eh_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2c. payments — Payment records per invoice
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` INT(11) NOT NULL,
  `parent_id` INT(11) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('FPX','Credit Card','Manual Transfer','Cash') NOT NULL,
  `transaction_ref` VARCHAR(100) DEFAULT NULL,
  `payment_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `receipt_number` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('Pending','Completed','Failed') NOT NULL DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_pay_invoice` (`invoice_id`),
  KEY `fk_pay_parent` (`parent_id`),
  CONSTRAINT `fk_pay_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2d. expenses — Operational expense tracking
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category` ENUM('Salary','Utilities','Food & Supplies','Equipment','Maintenance','Transportation','Miscellaneous') NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `expense_date` DATE NOT NULL,
  `receipt_file` VARCHAR(255) DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_exp_user` (`created_by`),
  CONSTRAINT `fk_exp_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2e. fee_structures — Fee templates per module
CREATE TABLE IF NOT EXISTS `fee_structures` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `module` ENUM('Taska','Tadika','KAFA Care') NOT NULL,
  `fee_type` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_recurring` TINYINT(1) NOT NULL DEFAULT 0,
  `billing_cycle` ENUM('One-time','Monthly','Yearly') NOT NULL DEFAULT 'One-time',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2f. waitlist — Waitlist management
CREATE TABLE IF NOT EXISTS `waitlist` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `module` ENUM('Taska','Tadika','KAFA Care') NOT NULL,
  `position` INT(11) NOT NULL DEFAULT 0,
  `added_date` DATE NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` ENUM('Waiting','Offered','Accepted','Expired') NOT NULL DEFAULT 'Waiting',
  PRIMARY KEY (`id`),
  KEY `fk_wl_student` (`student_id`),
  CONSTRAINT `fk_wl_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. SEED DATA — Default Fee Structures
-- ============================================================

INSERT INTO `fee_structures` (`module`, `fee_type`, `amount`, `description`, `is_recurring`, `billing_cycle`) VALUES
-- Taska fees
('Taska', 'Registration Fee', 200.00, 'One-time registration fee for Taska program', 0, 'One-time'),
('Taska', 'Monthly Tuition', 350.00, 'Monthly tuition fee for Taska (0-4 years)', 1, 'Monthly'),
('Taska', 'Material & Books', 150.00, 'Learning materials and activity books', 0, 'Yearly'),
('Taska', 'Uniform', 80.00, 'School uniform set', 0, 'One-time'),
('Taska', 'Activity Fee', 50.00, 'Monthly activity and enrichment fee', 1, 'Monthly'),

-- Tadika fees
('Tadika', 'Registration Fee', 250.00, 'One-time registration fee for Tadika program', 0, 'One-time'),
('Tadika', 'Monthly Tuition', 300.00, 'Monthly tuition fee for Tadika (5-6 years)', 1, 'Monthly'),
('Tadika', 'Material & Books', 180.00, 'Learning materials, workbooks and stationery', 0, 'Yearly'),
('Tadika', 'Uniform', 80.00, 'School uniform set', 0, 'One-time'),
('Tadika', 'Activity Fee', 40.00, 'Monthly activity fee', 1, 'Monthly'),

-- KAFA Care fees
('KAFA Care', 'Registration Fee', 180.00, 'One-time registration fee for KAFA Care', 0, 'One-time'),
('KAFA Care', 'Monthly Tuition', 280.00, 'Monthly tuition fee for KAFA Care program', 1, 'Monthly'),
('KAFA Care', 'Material & Books', 120.00, 'Islamic education materials and books', 0, 'Yearly'),
('KAFA Care', 'Uniform', 70.00, 'School uniform set', 0, 'One-time'),
('KAFA Care', 'Activity Fee', 30.00, 'Monthly activity fee', 1, 'Monthly');

-- ============================================================
-- 4. ADD INDEX FOR PERFORMANCE
-- ============================================================

-- Index for faster enrollment queries
ALTER TABLE `students` ADD INDEX `idx_students_status` (`status`);
ALTER TABLE `invoices` ADD INDEX `idx_invoices_status` (`status`);
ALTER TABLE `invoices` ADD INDEX `idx_invoices_due_date` (`due_date`);

-- ============================================================
-- MIGRATION COMPLETE
-- ============================================================
