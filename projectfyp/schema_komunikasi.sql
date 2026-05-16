-- ================================================================
-- SCHEMA TAMBAHAN: Modul Komunikasi, Penglibatan Ibu Bapa & Autentikasi
-- Sistem Pengurusan Kanak-Kanak Terpadu (Taska/Tadika/KAFA Care)
-- Tarikh: 2026-05-15
-- ================================================================

USE `childcare_db`;

-- --------------------------------------------------------
-- 1. KEMAS KINI JADUAL `users` - Tambah emel & token reset
-- --------------------------------------------------------
ALTER TABLE `users`
  ADD COLUMN `email` VARCHAR(150) NULL AFTER `username`,
  ADD COLUMN `reset_token` VARCHAR(64) NULL AFTER `status`,
  ADD COLUMN `reset_token_expiry` DATETIME NULL AFTER `reset_token`,
  ADD UNIQUE KEY `uk_email` (`email`);

-- --------------------------------------------------------
-- 2. JADUAL `classes` - Kelas untuk skop pengumuman & hubungan
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `classes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `class_name` VARCHAR(100) NOT NULL,
  `module` ENUM('Taska','Tadika','KAFA Care') NOT NULL,
  `teacher_id` INT(11) NULL COMMENT 'FK ke teachers.id',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_classes_teacher` (`teacher_id`),
  CONSTRAINT `fk_classes_teacher` FOREIGN KEY (`teacher_id`)
    REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 3. JADUAL `student_classes` - Hubungan pelajar-kelas
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_classes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `class_id` INT(11) NOT NULL,
  `enrolled_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_class` (`student_id`, `class_id`),
  KEY `fk_sc_student` (`student_id`),
  KEY `fk_sc_class` (`class_id`),
  CONSTRAINT `fk_sc_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sc_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 4. JADUAL `announcements` - Pengumuman (global / kelas)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `announcements` (
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
  CONSTRAINT `fk_ann_author` FOREIGN KEY (`author_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ann_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 5. JADUAL `calendar_events` - Acara kalendar dengan RSVP
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `calendar_events` (
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
  CONSTRAINT `fk_event_creator` FOREIGN KEY (`created_by`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 6. JADUAL `event_rsvps` - RSVP ibu bapa untuk acara
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `event_rsvps` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `event_id` INT(11) NOT NULL,
  `parent_id` INT(11) NOT NULL COMMENT 'FK ke parents.id',
  `status` ENUM('Hadir','Tidak Hadir') NOT NULL,
  `responded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_event_parent` (`event_id`, `parent_id`),
  KEY `fk_rsvp_event` (`event_id`),
  KEY `fk_rsvp_parent` (`parent_id`),
  CONSTRAINT `fk_rsvp_event` FOREIGN KEY (`event_id`)
    REFERENCES `calendar_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rsvp_parent` FOREIGN KEY (`parent_id`)
    REFERENCES `parents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 7. JADUAL `messages` - Mesej satu-ke-satu
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
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
  CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_receiver` FOREIGN KEY (`receiver_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 8. JADUAL `daily_reports` - Laporan aktiviti harian guru
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `daily_reports` (
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
  CONSTRAINT `fk_dr_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dr_teacher` FOREIGN KEY (`teacher_id`)
    REFERENCES `teachers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- DATA CONTOH
-- --------------------------------------------------------
INSERT INTO `classes` (`class_name`, `module`, `teacher_id`) VALUES
('Kelas Ceria', 'Taska', NULL),
('Kelas Bijak', 'Tadika', NULL),
('Kelas Soleh', 'KAFA Care', NULL);
