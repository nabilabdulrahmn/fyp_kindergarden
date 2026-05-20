-- ================================================================
-- SISTEM PENGURUSAN KANAK-KANAK TERPADU (TASKA/TADIKA/KAFA CARE)
-- UNIFIED DATABASE SETUP SCRIPT (36 TABLES & SEED DATA)
-- Tarikh: 2026-05-20
-- ================================================================

CREATE DATABASE IF NOT EXISTS `childcare_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `childcare_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. DROP ALL EXISTING TABLES FOR CLEAN RE-IMPORT
-- --------------------------------------------------------
DROP TABLE IF EXISTS `lesson_plans`;
DROP TABLE IF EXISTS `activity_schedules`;
DROP TABLE IF EXISTS `system_logs`;
DROP TABLE IF EXISTS `authorized_guardians`;
DROP TABLE IF EXISTS `visitor_logs`;
DROP TABLE IF EXISTS `checkin_checkout`;
DROP TABLE IF EXISTS `facility_requests`;
DROP TABLE IF EXISTS `inventory_requests`;
DROP TABLE IF EXISTS `inventory`;
DROP TABLE IF EXISTS `meal_plans`;
DROP TABLE IF EXISTS `bus_tracking`;
DROP TABLE IF EXISTS `student_transport`;
DROP TABLE IF EXISTS `transportation`;
DROP TABLE IF EXISTS `payroll`;
DROP TABLE IF EXISTS `staff`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `fee_structures`;
DROP TABLE IF EXISTS `applications`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `daily_reports`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `event_rsvps`;
DROP TABLE IF EXISTS `calendar_events`;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `milestones`;
DROP TABLE IF EXISTS `milestone_categories`;
DROP TABLE IF EXISTS `report_cards`;
DROP TABLE IF EXISTS `invoices`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `student_classes`;
DROP TABLE IF EXISTS `classes`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `teachers`;
DROP TABLE IF EXISTS `parents`;
DROP TABLE IF EXISTS `users`;

-- --------------------------------------------------------
-- 2. CREATE SCHEMAS
-- --------------------------------------------------------

-- 1. users
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','pengetua','teacher','parent') NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'approved',
  `email` VARCHAR(150) NULL UNIQUE,
  `reset_token` VARCHAR(64) NULL,
  `reset_token_expiry` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. parents
CREATE TABLE `parents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `full_name` VARCHAR(100) DEFAULT NULL,
  `ic_number` VARCHAR(15) DEFAULT NULL,
  `phone_number` VARCHAR(15) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `emergency_contact` VARCHAR(15) NULL,
  `relationship` VARCHAR(50) DEFAULT 'Ibu/Bapa',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `parents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. teachers
CREATE TABLE `teachers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `full_name` VARCHAR(100) DEFAULT NULL,
  `phone_number` VARCHAR(15) DEFAULT NULL,
  `specialization` VARCHAR(100) NULL,
  `ic_number` VARCHAR(15) NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. students
CREATE TABLE `students` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `parent_id` INT(11) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `mykid_number` VARCHAR(20) NOT NULL,
  `module` ENUM('Taska','Tadika','KAFA Care') NOT NULL,
  `health_record` TEXT DEFAULT NULL,
  `allergies` TEXT DEFAULT NULL,
  `status` ENUM('Active','Graduated','Withdrawn') DEFAULT 'Active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. classes
CREATE TABLE `classes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `class_name` VARCHAR(100) NOT NULL,
  `module` ENUM('Taska','Tadika','KAFA Care') NOT NULL,
  `teacher_id` INT(11) NULL COMMENT 'FK ke teachers.id',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_classes_teacher` (`teacher_id`),
  CONSTRAINT `fk_classes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. student_classes
CREATE TABLE `student_classes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `class_id` INT(11) NOT NULL,
  `enrolled_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_class` (`student_id`, `class_id`),
  KEY `fk_sc_student` (`student_id`),
  KEY `fk_sc_class` (`class_id`),
  CONSTRAINT `fk_sc_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sc_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. attendance
CREATE TABLE `attendance` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `date` DATE NOT NULL,
  `status` ENUM('Present','Absent','MC') NOT NULL,
  `mc_file_path` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 8. invoices
CREATE TABLE `invoices` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `parent_id` INT(11) NOT NULL,
  `student_id` INT(11) NOT NULL,
  `invoice_number` VARCHAR(50) DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `type` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('Pending','Paid','Overdue') DEFAULT 'Pending',
  `payment_method` ENUM('FPX','Manual Transfer','Cash') DEFAULT NULL,
  `receipt_file` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `parent_id` (`parent_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 9. payments
CREATE TABLE `payments` (
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

-- 10. report_cards
CREATE TABLE `report_cards` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `term` ENUM('Mid-Term','Final-Term') NOT NULL,
  `reading_score` VARCHAR(50) DEFAULT NULL,
  `writing_score` VARCHAR(50) DEFAULT NULL,
  `behaviour_score` VARCHAR(50) DEFAULT NULL,
  `interaction_score` VARCHAR(50) DEFAULT NULL,
  `teacher_comment` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `report_cards_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 11. milestone_categories
CREATE TABLE `milestone_categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL COMMENT 'e.g. Motor Kasar, Motor Halus, Sosial, Bahasa',
  `description` TEXT NULL,
  `age_group` VARCHAR(50) NULL COMMENT 'e.g. 0-2 tahun, 3-4 tahun',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 12. milestones
CREATE TABLE `milestones` (
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

-- 13. announcements
CREATE TABLE `announcements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `author_id` INT(11) NOT NULL COMMENT 'FK ke users.id',
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `scope` ENUM('global','class') NOT NULL DEFAULT 'global',
  `class_id` INT(11) NULL COMMENT 'NULL jika scope=global',
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_ann_author` (`author_id`),
  KEY `fk_ann_class` (`class_id`),
  KEY `idx_ann_scope` (`scope`, `class_id`),
  CONSTRAINT `fk_ann_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ann_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 14. calendar_events
CREATE TABLE `calendar_events` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `event_date` DATE NOT NULL,
  `event_time` TIME NULL,
  `location` VARCHAR(255) NULL,
  `created_by` INT(11) NOT NULL COMMENT 'FK ke users.id (admin)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_event_creator` (`created_by`),
  KEY `idx_event_date` (`event_date`),
  CONSTRAINT `fk_event_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 15. event_rsvps
CREATE TABLE `event_rsvps` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `event_id` INT(11) NOT NULL,
  `parent_id` INT(11) NOT NULL COMMENT 'FK ke parents.id',
  `status` ENUM('Hadir','Tidak Hadir') NOT NULL,
  `responded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_event_parent` (`event_id`, `parent_id`),
  KEY `fk_rsvp_event` (`event_id`),
  KEY `fk_rsvp_parent` (`parent_id`),
  CONSTRAINT `fk_rsvp_event` FOREIGN KEY (`event_id`) REFERENCES `calendar_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rsvp_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 16. messages
CREATE TABLE `messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `sender_id` INT(11) NOT NULL COMMENT 'FK ke users.id',
  `receiver_id` INT(11) NOT NULL COMMENT 'FK ke users.id',
  `subject` VARCHAR(255) NULL,
  `body` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_msg_sender` (`sender_id`),
  KEY `fk_msg_receiver` (`receiver_id`),
  KEY `idx_msg_thread` (`sender_id`, `receiver_id`, `created_at`),
  CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 17. daily_reports
CREATE TABLE `daily_reports` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `class_id` INT(11) NOT NULL,
  `teacher_id` INT(11) NOT NULL COMMENT 'FK ke teachers.id',
  `report_date` DATE NOT NULL,
  `activities` TEXT NOT NULL,
  `meals` TEXT NULL,
  `notes` TEXT NULL,
  `photo_path` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_dr_class` (`class_id`),
  KEY `fk_dr_teacher` (`teacher_id`),
  UNIQUE KEY `uk_daily_report` (`class_id`, `report_date`),
  CONSTRAINT `fk_dr_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dr_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 18. notifications
CREATE TABLE `notifications` (
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

-- 19. applications
CREATE TABLE `applications` (
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

-- 20. fee_structures
CREATE TABLE `fee_structures` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `module` ENUM('Taska','Tadika','KAFA Care') NOT NULL,
  `fee_name` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `frequency` ENUM('Monthly','Yearly','One-Time') DEFAULT 'Monthly',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 21. expenses
CREATE TABLE `expenses` (
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

-- 22. staff
CREATE TABLE `staff` (
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

-- 23. payroll
CREATE TABLE `payroll` (
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

-- 24. transportation
CREATE TABLE `transportation` (
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

-- 25. student_transport
CREATE TABLE `student_transport` (
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

-- 26. bus_tracking
CREATE TABLE `bus_tracking` (
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

-- 27. meal_plans
CREATE TABLE `meal_plans` (
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

-- 28. inventory
CREATE TABLE `inventory` (
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

-- 29. inventory_requests
CREATE TABLE `inventory_requests` (
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

-- 30. facility_requests
CREATE TABLE `facility_requests` (
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

-- 31. checkin_checkout
CREATE TABLE `checkin_checkout` (
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

-- 32. visitor_logs
CREATE TABLE `visitor_logs` (
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

-- 33. authorized_guardians
CREATE TABLE `authorized_guardians` (
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

-- 34. system_logs
CREATE TABLE `system_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NULL,
  `username` VARCHAR(50) NULL,
  `action` VARCHAR(255) NOT NULL,
  `status` ENUM('Success','Failed') NOT NULL,
  `ip_address` VARCHAR(50) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 35. activity_schedules
CREATE TABLE `activity_schedules` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` INT(11) NOT NULL,
  `class_group` ENUM('Tadika 4-5 Tahun','Tadika 6 Tahun','KAFA Care','Aktiviti Taska') NOT NULL,
  `activity_date` DATE NOT NULL,
  `activity_time` VARCHAR(20) NOT NULL,
  `activity_name` VARCHAR(255) NOT NULL,
  `status` ENUM('Pending','Completed') DEFAULT 'Pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_as_teacher` (`teacher_id`),
  CONSTRAINT `fk_as_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 36. lesson_plans
CREATE TABLE `lesson_plans` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` INT(11) NOT NULL,
  `class_group` ENUM('Tadika 4-5 Tahun','Tadika 6 Tahun','KAFA Care','Aktiviti Taska') NOT NULL,
  `subject` VARCHAR(100) NOT NULL,
  `teaching_date` DATE NOT NULL,
  `topic` VARCHAR(255) NOT NULL,
  `learning_objective` TEXT NOT NULL,
  `activities` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_lp_teacher` (`teacher_id`),
  CONSTRAINT `fk_lp_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 3. INSERT SEED/DEFAULT DATA
-- --------------------------------------------------------

-- Users
INSERT INTO `users` (`id`, `username`, `password`, `role`, `status`, `created_at`) VALUES
(1, 'akmallo', '$2y$10$hLg/5nnJ3ddqHkiBm1bi6eFI29V5uCzERTLDOYMknkjjDhcujk58S', 'parent', 'approved', '2026-05-13 01:02:03'),
(2, 'nabil', '$2y$10$h51C1F0LBkwD/BEom9ktSuHOyCBH1XNKyvdb3CmNcD36k9BhJrxUG', 'admin', 'approved', '2026-05-13 01:07:14'),
(3, 'aunie', '$2y$10$o.8rRdZQjiycrbJrOuYDLelKpmfFAif8NJoen4PvZZKCbJ79HXSA6', 'teacher', 'approved', '2026-05-13 01:08:11'),
(4, 'bapa_ali', '$2y$10$8v5Z2s.p2Z4Z2s.p2Z4Z2O.8v5Z2s.p2Z4Z2s.p2Z4Z2O', 'parent', 'approved', '2026-05-13 01:12:00'),
(5, 'ibu_siti', '$2y$10$8v5Z2s.p2Z4Z2s.p2Z4Z2O.8v5Z2s.p2Z4Z2s.p2Z4Z2O', 'parent', 'approved', '2026-05-13 01:12:00'),
(6, 'cikgu_mimi', '$2y$10$8v5Z2s.p2Z4Z2s.p2Z4Z2O.8v5Z2s.p2Z4Z2s.p2Z4Z2O', 'teacher', 'approved', '2026-05-13 01:12:00');

-- Parents
INSERT INTO `parents` (`id`, `user_id`, `full_name`, `ic_number`, `phone_number`, `address`, `emergency_contact`, `relationship`) VALUES
(1, 1, 'Ahmad bin Abu', '800101-14-1234', '012-3456789', 'Taman Melati, Kuala Lumpur', '012-3456789', 'Ibu/Bapa'),
(2, 2, 'Siti binti Awang', '850202-14-5555', '011-2223334', 'Bandar Baru Selayang', '011-2223334', 'Ibu/Bapa');

-- Teachers
INSERT INTO `teachers` (`id`, `user_id`, `full_name`, `phone_number`, `specialization`, `ic_number`) VALUES
(3, 3, 'Cikgu Aunie', '012-3456780', 'Pendidikan Awal Kanak-kanak', '900101-14-9999'),
(6, 6, 'Cikgu Mimi', '013-9998887', 'Pengajian Agama & KAFA', '920202-14-8888');

-- Students
INSERT INTO `students` (`id`, `parent_id`, `full_name`, `mykid_number`, `module`, `health_record`, `allergies`, `status`, `created_at`) VALUES
(1, 1, 'Ali bin Ahmad', '200101-14-0001', 'Taska', 'Normal', 'Tiada', 'Active', '2026-05-13 01:12:00'),
(2, 1, 'Abu bin Ahmad', '180505-14-0002', 'Tadika', 'Asma Ringan', 'Habuk', 'Active', '2026-05-13 01:12:00'),
(3, 2, 'Aisyah binti Osman', '190303-14-0003', 'KAFA Care', 'Normal', 'Tiada', 'Active', '2026-05-13 01:12:00'),
(4, 2, 'Aminah binti Osman', '210606-14-0004', 'Taska', 'Normal', 'Laktosa', 'Active', '2026-05-13 01:12:00'),
(5, 1, 'Aiman bin Ahmad', '170707-14-0005', 'Tadika', 'Normal', 'Tiada', 'Active', '2026-05-13 01:12:00'),
(6, 1, 'Ali Abbas', '070198-01-1699', 'Taska', 'Normal', 'Tiada', 'Active', '2026-05-13 01:26:43');

-- Classes
INSERT INTO `classes` (`id`, `class_name`, `module`, `teacher_id`) VALUES
(1, 'Kelas Ceria', 'Taska', 3),
(2, 'Kelas Bijak', 'Tadika', 6),
(3, 'Kelas Soleh', 'KAFA Care', 3);

-- Student Classes Map
INSERT INTO `student_classes` (`id`, `student_id`, `class_id`) VALUES
(1, 1, 1),
(2, 2, 2),
(3, 3, 3),
(4, 4, 1),
(5, 5, 2),
(6, 6, 1);

-- Attendance Records
INSERT INTO `attendance` (`id`, `student_id`, `date`, `status`, `mc_file_path`) VALUES
(1, 2, '2026-05-13', 'Absent', NULL),
(2, 5, '2026-05-13', 'Absent', NULL),
(3, 3, '2026-05-13', 'Absent', NULL),
(4, 1, '2026-05-13', 'Absent', NULL),
(5, 4, '2026-05-13', 'Absent', NULL),
(6, 6, '2026-05-13', 'Present', NULL);

-- Milestone Categories
INSERT INTO `milestone_categories` (`id`, `category_name`, `description`, `age_group`) VALUES
(1, 'Motor Kasar', 'Perkembangan pergerakan besar (berjalan, melompat)', '0-6 tahun'),
(2, 'Motor Halus', 'Perkembangan pergerakan halus (menulis, melukis)', '2-6 tahun'),
(3, 'Bahasa & Komunikasi', 'Perkembangan pertuturan dan komunikasi', '0-6 tahun'),
(4, 'Sosial & Emosi', 'Perkembangan sosial dan pengurusan emosi', '0-6 tahun'),
(5, 'Kognitif', 'Perkembangan pemikiran dan penyelesaian masalah', '2-6 tahun'),
(6, 'Penjagaan Diri', 'Kebolehan menjaga diri sendiri (makan, pakaian)', '2-6 tahun');

-- Fee Structures
INSERT INTO `fee_structures` (`id`, `module`, `fee_name`, `amount`, `frequency`) VALUES
(1, 'Taska', 'Yuran Bulanan Taska', 350.00, 'Monthly'),
(2, 'Tadika', 'Yuran Bulanan Tadika', 250.00, 'Monthly'),
(3, 'KAFA Care', 'Yuran Bulanan KAFA Care', 200.00, 'Monthly'),
(4, 'Taska', 'Yuran Pendaftaran', 150.00, 'One-Time'),
(5, 'Tadika', 'Yuran Pendaftaran', 100.00, 'One-Time'),
(6, 'KAFA Care', 'Yuran Pendaftaran', 80.00, 'One-Time');

-- Transportation Routes
INSERT INTO `transportation` (`id`, `route_name`, `vehicle_plate`, `driver_name`, `driver_phone`) VALUES
(1, 'Laluan A - Taman Melati', 'WKL 1234', 'Encik Razak', '013-4567890'),
(2, 'Laluan B - Bandar Baru', 'WKL 5678', 'Encik Hakim', '014-9876543');

-- Inventory
INSERT INTO `inventory` (`id`, `item_name`, `category`, `quantity`, `unit`, `min_stock_level`) VALUES
(1, 'Kertas Lukisan A4', 'Alat Tulis', 500, 'helai', 100),
(2, 'Krayon Warna (12 warna)', 'Alat Tulis', 30, 'kotak', 10),
(3, 'Susu Kotak (200ml)', 'Makanan', 100, 'kotak', 30),
(4, 'Sabun Tangan', 'Kebersihan', 20, 'botol', 5),
(5, 'Tuala Kecil', 'Kebersihan', 50, 'helai', 15);

-- Meal Plans
INSERT INTO `meal_plans` (`id`, `meal_date`, `meal_type`, `menu_description`, `allergens`, `created_by`) VALUES
(1, CURDATE(), 'Breakfast', 'Roti bakar + susu', 'Gluten, Lactose', 2),
(2, CURDATE(), 'Lunch', 'Nasi + ayam masak merah + sayur bayam', NULL, 2),
(3, CURDATE(), 'Snack', 'Biskut + jus oren', 'Gluten', 2);

-- Staff Registry
INSERT INTO `staff` (`id`, `user_id`, `full_name`, `ic_number`, `phone_number`, `position`, `department`, `employment_type`, `hire_date`, `status`) VALUES
(1, 3, 'Cikgu Aunie', '900101-14-9999', '012-3456780', 'Guru Kelas Ceria', 'Teaching', 'Full-Time', '2025-01-01', 'Active'),
(2, 6, 'Cikgu Mimi', '920202-14-8888', '013-9998887', 'Guru Kelas Bijak', 'Teaching', 'Full-Time', '2025-01-15', 'Active');

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
