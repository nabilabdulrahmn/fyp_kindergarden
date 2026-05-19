-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 17, 2026 at 04:06 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `childcare_db`
--
CREATE DATABASE IF NOT EXISTS `childcare_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `childcare_db`;

-- --------------------------------------------------------

--
-- Table structure for table `activity_schedules`
--

CREATE TABLE `activity_schedules` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `class_group` enum('Tadika 4-5 Tahun','Tadika 6 Tahun','KAFA Care','Aktiviti Taska') NOT NULL,
  `activity_date` date NOT NULL,
  `activity_time` varchar(20) NOT NULL,
  `activity_name` varchar(255) NOT NULL,
  `status` enum('Pending','Completed') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_schedules`
--

INSERT INTO `activity_schedules` (`id`, `teacher_id`, `class_group`, `activity_date`, `activity_time`, `activity_name`, `status`, `created_at`) VALUES
(1, 3, 'Tadika 4-5 Tahun', '2026-05-17', '09:00 AM', 'Breakfast', 'Pending', '2026-05-16 23:50:55'),
(2, 3, 'Tadika 4-5 Tahun', '2026-05-17', '10:00 AM', 'Senaman Pagi', 'Pending', '2026-05-16 23:51:14'),
(3, 3, 'Tadika 4-5 Tahun', '2026-05-17', '11:00 AM', 'Aktiviti Nyanyi', 'Pending', '2026-05-16 23:52:00');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('Present','Absent','MC') NOT NULL,
  `mc_file_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `date`, `status`, `mc_file_path`) VALUES
(1, 2, '2026-05-13', 'Absent', NULL),
(2, 5, '2026-05-13', 'Absent', NULL),
(3, 3, '2026-05-13', 'Absent', NULL),
(4, 1, '2026-05-13', 'Absent', NULL),
(5, 4, '2026-05-13', 'Present', NULL),
(6, 6, '2026-05-13', 'Absent', NULL),
(7, 2, '2026-05-17', 'Absent', NULL),
(8, 5, '2026-05-17', 'Absent', NULL),
(9, 6, '2026-05-17', 'Absent', NULL),
(10, 1, '2026-05-17', 'Absent', NULL),
(11, 7, '2026-05-17', 'Present', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `status` enum('Pending','Paid','Overdue') DEFAULT 'Pending',
  `payment_method` enum('FPX','Manual Transfer','Cash') DEFAULT NULL,
  `receipt_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lesson_plans`
--

CREATE TABLE `lesson_plans` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `class_group` enum('Tadika 4-5 Tahun','Tadika 6 Tahun','KAFA Care','Aktiviti Taska') NOT NULL,
  `subject` varchar(100) NOT NULL,
  `teaching_date` date NOT NULL,
  `topic` varchar(255) NOT NULL,
  `learning_objective` text NOT NULL,
  `activities` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lesson_plans`
--

INSERT INTO `lesson_plans` (`id`, `teacher_id`, `class_group`, `subject`, `teaching_date`, `topic`, `learning_objective`, `activities`, `created_at`) VALUES
(1, 3, 'Tadika 4-5 Tahun', 'Bahasa Melayu (Asas Huruf)', '2026-05-16', 'ABC', 'student hafal ABC', 'nyanyi', '2026-05-16 23:45:50');

-- --------------------------------------------------------

--
-- Table structure for table `parents`
--

CREATE TABLE `parents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `ic_number` varchar(15) DEFAULT NULL,
  `phone_number` varchar(15) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parents`
--

INSERT INTO `parents` (`id`, `user_id`, `full_name`, `ic_number`, `phone_number`, `address`) VALUES
(1, 1, 'Ahmad bin Abu', '800101-14-1234', '012-3456789', NULL),
(2, 2, 'Siti binti Awang', '850202-14-5555', '011-2223334', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `report_cards`
--

CREATE TABLE `report_cards` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `term` enum('Mid-Term','Final-Term') NOT NULL,
  `reading_score` varchar(50) DEFAULT NULL,
  `writing_score` varchar(50) DEFAULT NULL,
  `behaviour_score` varchar(50) DEFAULT NULL,
  `interaction_score` varchar(50) DEFAULT NULL,
  `teacher_comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `mykid_number` varchar(20) NOT NULL,
  `module` enum('Taska','Tadika','KAFA Care') NOT NULL,
  `health_record` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `status` enum('Pending','Active','Graduated','Withdrawn') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `parent_name` varchar(100) NOT NULL,
  `parent_ic` varchar(15) NOT NULL,
  `parent_phone` varchar(15) NOT NULL,
  `parent_email` varchar(100) NOT NULL,
  `employer_name` varchar(100) NOT NULL,
  `employer_address` text NOT NULL,
  `address` text NOT NULL,
  `postcode` varchar(10) NOT NULL,
  `state` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `parent_id`, `full_name`, `mykid_number`, `module`, `health_record`, `allergies`, `status`, `created_at`, `parent_name`, `parent_ic`, `parent_phone`, `parent_email`, `employer_name`, `employer_address`, `address`, `postcode`, `state`) VALUES
(1, 1, 'Ali bin Ahmad', '200101-14-0001', 'Taska', NULL, NULL, 'Active', '2026-05-13 01:12:00', '', '', '', '', '', '', '', '', ''),
(2, 1, 'Abu bin Ahmad', '180505-14-0002', 'Tadika', NULL, NULL, 'Active', '2026-05-13 01:12:00', '', '', '', '', '', '', '', '', ''),
(3, 2, 'Aisyah binti Osman', '190303-14-0003', 'KAFA Care', NULL, NULL, 'Withdrawn', '2026-05-13 01:12:00', '', '', '', '', '', '', '', '', ''),
(4, 2, 'Aminah binti Osman', '210606-14-0004', 'Taska', NULL, NULL, 'Graduated', '2026-05-13 01:12:00', '', '', '', '', '', '', '', '', ''),
(5, 1, 'Aiman bin Ahmad', '170707-14-0005', 'Tadika', NULL, NULL, 'Active', '2026-05-13 01:12:00', '', '', '', '', '', '', '', '', ''),
(6, 1, 'Ali Abbas', '070198-01-1699', 'Taska', 'tiada', 'tiada', 'Active', '2026-05-13 01:26:43', '', '', '', '', '', '', '', '', ''),
(7, 1, 'MUHAMMAD AKMAL BIN SHAHRIL IZWANI', '040727011699', 'Tadika', 'asma', 'kacang', 'Active', '2026-05-13 03:04:32', 'Shahril Izwani', '771212085823', '0134809876', 'aimalfast@gmail.com', 'Daikin Sdn Bhd', 'Lot 27492-6 Lorong Tanjung, Kuang', 'Lot 27492-6 Lorong Tanjung, Kuang', '48050', 'Selangor');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `status` enum('Success','Failed') NOT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `username`, `action`, `status`, `ip_address`, `created_at`) VALUES
(1, 2, 'nabil', 'Memberhentikan/Membuang pelajar ID: 3', 'Success', '::1', '2026-05-13 02:55:06'),
(2, 2, 'nabil', 'Meluluskan pendaftaran pelajar ID: 7', 'Success', '::1', '2026-05-16 23:26:21'),
(3, 2, 'nabil', 'Menamatkan pembelajaran (Graduated) pelajar ID: 4', 'Success', '::1', '2026-05-16 23:33:25');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `phone_number` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','pengetua','teacher','parent') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'akmallo', '$2y$10$hLg/5nnJ3ddqHkiBm1bi6eFI29V5uCzERTLDOYMknkjjDhcujk58S', 'parent', '2026-05-13 01:02:03'),
(2, 'nabil', '$2y$10$h51C1F0LBkwD/BEom9ktSuHOyCBH1XNKyvdb3CmNcD36k9BhJrxUG', 'admin', '2026-05-13 01:07:14'),
(3, 'aunie', '$2y$10$o.8rRdZQjiycrbJrOuYDLelKpmfFAif8NJoen4PvZZKCbJ79HXSA6', 'teacher', '2026-05-13 01:08:11'),
(4, 'bapa_ali', '$2y$10$8v5Z2s.p2Z4Z2s.p2Z4Z2O.8v5Z2s.p2Z4Z2s.p2Z4Z2O', 'parent', '2026-05-13 01:12:00'),
(5, 'ibu_siti', '$2y$10$8v5Z2s.p2Z4Z2s.p2Z4Z2O.8v5Z2s.p2Z4Z2s.p2Z4Z2O', 'parent', '2026-05-13 01:12:00'),
(6, 'cikgu_mimi', '$2y$10$8v5Z2s.p2Z4Z2s.p2Z4Z2O.8v5Z2s.p2Z4Z2s.p2Z4Z2O', 'teacher', '2026-05-13 01:12:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_schedules`
--
ALTER TABLE `activity_schedules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `lesson_plans`
--
ALTER TABLE `lesson_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `report_cards`
--
ALTER TABLE `report_cards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_schedules`
--
ALTER TABLE `activity_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lesson_plans`
--
ALTER TABLE `lesson_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `report_cards`
--
ALTER TABLE `report_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`),
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Constraints for table `parents`
--
ALTER TABLE `parents`
  ADD CONSTRAINT `parents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `report_cards`
--
ALTER TABLE `report_cards`
  ADD CONSTRAINT `report_cards_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
