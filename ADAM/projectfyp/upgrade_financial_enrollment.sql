-- ============================================================
-- Migration: Financial & Enrollment Upgrade
-- Project: Childcare Management System (TADIKA)
-- Date: 2026-06-07
-- Description: Adds financial, enrollment, and sibling
--              management tables and columns.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- SAFE ADD COLUMN PROCEDURE
-- Wraps ALTER TABLE ADD COLUMN in a check so it won't fail
-- if the column already exists.
-- ============================================================
DROP PROCEDURE IF EXISTS safe_add_column;
DELIMITER //
CREATE PROCEDURE safe_add_column(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    SET @col_exists = (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    );
    IF @col_exists = 0 THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- ============================================================
-- SAFE MODIFY COLUMN PROCEDURE
-- Modifies a column definition (column must already exist).
-- ============================================================
DROP PROCEDURE IF EXISTS safe_modify_column;
DELIMITER //
CREATE PROCEDURE safe_modify_column(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    SET @col_exists = (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    );
    IF @col_exists > 0 THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` MODIFY COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- ============================================================
-- ALTER TABLE: applications
-- ============================================================
CALL safe_add_column('applications', 'priority_score',           'INT DEFAULT 0');
CALL safe_add_column('applications', 'enrollment_offer_expiry',  'DATETIME NULL');
CALL safe_add_column('applications', 'rejection_reason',         'TEXT NULL');
CALL safe_add_column('applications', 'internal_notes',           'TEXT NULL');

CALL safe_modify_column('applications', 'status',
    "ENUM('Pending','Under Review','Waitlisted','Offer Sent','Enrolled','Approved','Rejected') DEFAULT 'Pending'");

-- ============================================================
-- ALTER TABLE: invoices
-- ============================================================
CALL safe_add_column('invoices', 'issued_date',      'DATE NULL');
CALL safe_add_column('invoices', 'due_date',         'DATE NULL');
CALL safe_add_column('invoices', 'subtotal',         'DECIMAL(10,2) DEFAULT 0.00');
CALL safe_add_column('invoices', 'discount_amount',  'DECIMAL(10,2) DEFAULT 0.00');
CALL safe_add_column('invoices', 'total_amount',     'DECIMAL(10,2) DEFAULT 0.00');
CALL safe_add_column('invoices', 'paid_amount',      'DECIMAL(10,2) DEFAULT 0.00');
CALL safe_add_column('invoices', 'balance_due',      'DECIMAL(10,2) DEFAULT 0.00');
CALL safe_add_column('invoices', 'pdf_path',         'VARCHAR(255) NULL');
CALL safe_add_column('invoices', 'period_month',     'VARCHAR(7) NULL');

CALL safe_modify_column('invoices', 'status',
    "ENUM('Draft','Sent','Paid','Partial','Overdue','Void','Refunded','Pending') DEFAULT 'Pending'");

-- ============================================================
-- ALTER TABLE: payments
-- ============================================================
CALL safe_add_column('payments', 'payment_reference', 'VARCHAR(100) NULL');
CALL safe_add_column('payments', 'gateway',           "ENUM('FPX','Manual','Cash','Card','Sandbox') NULL");
CALL safe_add_column('payments', 'gateway_response',  'JSON NULL');
CALL safe_add_column('payments', 'receipt_number',    'VARCHAR(50) NULL');
CALL safe_add_column('payments', 'receipt_pdf_path',  'VARCHAR(255) NULL');
CALL safe_add_column('payments', 'refund_status',     "ENUM('None','Requested','Processing','Refunded') DEFAULT 'None'");
CALL safe_add_column('payments', 'refunded_amount',   'DECIMAL(10,2) DEFAULT 0.00');

-- ============================================================
-- ALTER TABLE: fee_structures
-- ============================================================
CALL safe_add_column('fee_structures', 'sibling_discount_pct', 'DECIMAL(5,2) DEFAULT 0.00');
CALL safe_add_column('fee_structures', 'applicable_to',       'VARCHAR(255) NULL');
CALL safe_add_column('fee_structures', 'valid_from',           'DATE NULL');
CALL safe_add_column('fee_structures', 'valid_until',          'DATE NULL');

-- ============================================================
-- ALTER TABLE: classes
-- ============================================================
CALL safe_add_column('classes', 'capacity', 'INT DEFAULT 25');

-- ============================================================
-- CREATE TABLE: invoice_line_items
-- ============================================================
CREATE TABLE IF NOT EXISTS `invoice_line_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `description` VARCHAR(255) NULL,
    `fee_structure_id` INT NULL,
    `quantity` INT DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `discount_pct` DECIMAL(5,2) DEFAULT 0.00,
    `line_total` DECIMAL(10,2) NOT NULL,
    CONSTRAINT `fk_line_items_invoice` FOREIGN KEY (`invoice_id`)
        REFERENCES `invoices`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_line_items_fee` FOREIGN KEY (`fee_structure_id`)
        REFERENCES `fee_structures`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CREATE TABLE: payment_gateway_log
-- ============================================================
CREATE TABLE IF NOT EXISTS `payment_gateway_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT NULL,
    `invoice_id` INT NULL,
    `parent_id` INT NOT NULL,
    `gateway` VARCHAR(50) DEFAULT 'Sandbox',
    `transaction_id` VARCHAR(100) NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(3) DEFAULT 'MYR',
    `status` ENUM('Initiated','Pending','Success','Failed','Cancelled','Refunded') NOT NULL DEFAULT 'Initiated',
    `request_payload` JSON NULL,
    `response_payload` JSON NULL,
    `ip_address` VARCHAR(50) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_transaction` (`transaction_id`),
    CONSTRAINT `fk_gwlog_payment` FOREIGN KEY (`payment_id`)
        REFERENCES `payments`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_gwlog_parent` FOREIGN KEY (`parent_id`)
        REFERENCES `parents`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CREATE TABLE: sibling_links
-- ============================================================
CREATE TABLE IF NOT EXISTS `sibling_links` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `parent_id` INT NOT NULL,
    `student_id_1` INT NOT NULL,
    `student_id_2` INT NOT NULL,
    `verified_by_admin` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_sibling` (`student_id_1`, `student_id_2`),
    CONSTRAINT `fk_sibling_parent` FOREIGN KEY (`parent_id`)
        REFERENCES `parents`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sibling_student1` FOREIGN KEY (`student_id_1`)
        REFERENCES `students`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sibling_student2` FOREIGN KEY (`student_id_2`)
        REFERENCES `students`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CREATE TABLE: reenrollments
-- ============================================================
CREATE TABLE IF NOT EXISTS `reenrollments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `parent_id` INT NOT NULL,
    `academic_year` VARCHAR(9) NULL,
    `requested_class_id` INT NULL,
    `status` ENUM('Pending','Confirmed','Cancelled') DEFAULT 'Pending',
    `notes` TEXT NULL,
    `confirmed_by` INT NULL,
    `confirmed_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_reenroll` (`student_id`, `academic_year`),
    CONSTRAINT `fk_reenroll_student` FOREIGN KEY (`student_id`)
        REFERENCES `students`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_reenroll_parent` FOREIGN KEY (`parent_id`)
        REFERENCES `parents`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_reenroll_class` FOREIGN KEY (`requested_class_id`)
        REFERENCES `classes`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reenroll_confirmer` FOREIGN KEY (`confirmed_by`)
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CREATE TABLE: application_documents
-- ============================================================
CREATE TABLE IF NOT EXISTS `application_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `application_id` INT NOT NULL,
    `document_name` VARCHAR(100) NULL,
    `document_type` ENUM('MyKid','Parent IC','Passport Photo','Vaccination Record','Health Declaration') NOT NULL,
    `file_path` VARCHAR(255) NULL,
    `status` ENUM('Pending','Verified','Rejected') DEFAULT 'Pending',
    `rejection_reason` TEXT NULL,
    `verified_by` INT NULL,
    `verified_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_appdoc_application` FOREIGN KEY (`application_id`)
        REFERENCES `applications`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_appdoc_verifier` FOREIGN KEY (`verified_by`)
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CREATE TABLE: sibling_discount_rules
-- ============================================================
CREATE TABLE IF NOT EXISTS `sibling_discount_rules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `child_order` INT NOT NULL COMMENT '2=anak kedua, 3=anak ketiga+',
    `discount_pct` DECIMAL(5,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default sibling discount rules (only if table is empty)
INSERT INTO `sibling_discount_rules` (`child_order`, `discount_pct`)
SELECT 2, 10.00 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `sibling_discount_rules` WHERE `child_order` = 2);

INSERT INTO `sibling_discount_rules` (`child_order`, `discount_pct`)
SELECT 3, 15.00 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `sibling_discount_rules` WHERE `child_order` = 3);

-- ============================================================
-- CREATE TABLE: late_payment_rules
-- ============================================================
CREATE TABLE IF NOT EXISTS `late_payment_rules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `grace_period_days` INT DEFAULT 7,
    `late_fee_type` ENUM('Fixed','Percentage') DEFAULT 'Fixed',
    `late_fee_amount` DECIMAL(10,2) DEFAULT 10.00,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default late payment rule (only if table is empty)
INSERT INTO `late_payment_rules` (`grace_period_days`, `late_fee_type`, `late_fee_amount`, `is_active`)
SELECT 7, 'Fixed', 10.00, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `late_payment_rules` LIMIT 1);

-- ============================================================
-- CLEANUP: Drop helper procedures
-- ============================================================
DROP PROCEDURE IF EXISTS safe_add_column;
DROP PROCEDURE IF EXISTS safe_modify_column;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- END OF MIGRATION
-- ============================================================
