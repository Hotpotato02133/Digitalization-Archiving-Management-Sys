-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 07, 2026 at 07:46 AM
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
-- Database: `dats`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

-- No activity log data remains after the sample/demo activity cleanup.

-- --------------------------------------------------------

--
-- Table structure for table `colleges`
--

CREATE TABLE `colleges` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `short_name` varchar(30) DEFAULT NULL,
  `logo_path` varchar(500) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `colleges`
--

INSERT INTO `colleges` (`id`, `name`, `short_name`, `logo_path`, `status`, `created_at`, `updated_at`) VALUES
(2, 'College of Education', 'COED', NULL, 'Active', '2026-09-03 03:33:04', '2026-09-03 03:33:04'),
(3, 'College of Business & Accountancy', 'CBA', NULL, 'Active', '2026-09-03 03:33:04', '2026-09-03 03:33:04'),
(4, 'College of Information and Computing Sciences', NULL, NULL, 'Active', '2026-09-03 03:58:09', '2026-09-03 03:58:09'),
(5, 'College of Engineering and Technology', NULL, NULL, 'Active', '2026-09-03 04:12:23', '2026-09-03 04:12:23');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `college_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(180) NOT NULL,
  `short_name` varchar(40) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `college_id`, `name`, `short_name`, `status`, `created_at`, `updated_at`) VALUES
(2, 2, 'Bachelor of Elementary Education', 'BEED', 'Active', '2026-09-03 03:33:04', '2026-09-03 03:33:04'),
(4, 4, 'Bachelor of Science in Information System', NULL, 'Active', '2026-09-03 03:59:43', '2026-09-03 03:59:43'),
(5, 4, 'Bachelor of Science in Data Science', NULL, 'Active', '2026-09-03 04:00:01', '2026-09-03 04:00:01'),
(6, 5, 'Bachelor of Science in Computer Technology', NULL, 'Active', '2026-09-03 04:14:48', '2026-09-03 04:14:48'),
(7, 5, 'Bachelor of Science in Electrical Technology', NULL, 'Active', '2026-09-03 05:14:55', '2026-09-03 05:14:55'),
(9, 4, 'BS Information Technology', NULL, 'Active', '2026-09-06 02:35:07', '2026-09-06 02:35:07'),
(14, 3, 'BS Marketing', NULL, 'Active', '2026-09-07 12:46:18', '2026-09-07 12:46:18');

-- --------------------------------------------------------

--
-- Table structure for table `course_majors`
--

CREATE TABLE `course_majors` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `uploaded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size_bytes` bigint(20) UNSIGNED DEFAULT NULL,
  `academic_year` year(4) DEFAULT NULL,
  `upload_source` enum('Camera','Manual upload') NOT NULL DEFAULT 'Manual upload',
  `status` enum('Processing','Needs review','Uploaded','Rejected','Archived') NOT NULL DEFAULT 'Archived',
  `deleted_at` datetime DEFAULT NULL,
  `ocr_status` enum('Pending','Processing','Completed','Failed') NOT NULL DEFAULT 'Pending',
  `ocr_text` longtext DEFAULT NULL,
  `content_hash` char(64) DEFAULT NULL,
  `ocr_error` text DEFAULT NULL,
  `ocr_processed_at` datetime DEFAULT NULL,
  `ai_summary` text DEFAULT NULL,
  `ai_detected_category` varchar(150) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `student_id`, `category_id`, `uploaded_by`, `original_filename`, `stored_filename`, `file_path`, `mime_type`, `file_size_bytes`, `academic_year`, `upload_source`, `status`, `uploaded_at`, `archived_at`, `updated_at`) VALUES
(11, 1, 7, NULL, 'camera-capture.jpg', NULL, NULL, 'image/jpeg', 44123, '2026', 'Camera', 'Rejected', '2026-09-03 05:01:18', NULL, '2026-09-03 05:11:32'),
(14, 9, 5, NULL, 'camera-capture.jpg', NULL, NULL, 'image/jpeg', 40451, '2026', 'Camera', 'Rejected', '2026-09-06 02:39:50', NULL, '2026-09-06 02:40:34'),
(16, 2, 3, NULL, 'Bariles 1.png', NULL, NULL, 'image/png', 1098473, '2026', 'Manual upload', 'Rejected', '2026-09-07 13:20:42', NULL, '2026-09-07 13:33:53');

-- --------------------------------------------------------

--
-- Table structure for table `document_categories`
--

CREATE TABLE `document_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(12) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_categories`
--

INSERT INTO `document_categories` (`id`, `code`, `name`, `description`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'TOR', 'Transcript of Records', 'Official academic history, grades, and course credits.', 'Active', NULL, '2026-09-03 03:33:05', '2026-09-03 03:33:05'),
(2, 'COR', 'Certificate of Registration', 'Proof of a student registration for an academic term.', 'Active', NULL, '2026-09-03 03:33:05', '2026-09-03 03:33:05'),
(3, 'COE', 'Certificate of Enrollment', 'Confirms current enrollment and student status.', 'Active', NULL, '2026-09-03 03:33:05', '2026-09-03 03:33:05'),
(4, 'COG', 'Certificate of Graduation', 'Confirms completion of an academic program.', 'Active', NULL, '2026-09-03 03:33:05', '2026-09-03 03:33:05'),
(5, 'GMC', 'Good Moral Certificate', 'Registrar-issued certification of student conduct.', 'Active', NULL, '2026-09-03 03:33:05', '2026-09-03 03:33:05'),
(6, 'DIP', 'Diploma', 'Formal award issued after program completion.', 'Active', NULL, '2026-09-03 03:33:05', '2026-09-03 03:33:05'),
(7, 'ACR', 'Academic Clearance', 'Clearance records completed before graduation.', 'Active', NULL, '2026-09-03 03:33:05', '2026-09-03 03:33:05'),
(8, 'OTH', 'Other Registrar Records', 'Supporting forms and approved academic documents.', 'Active', NULL, '2026-09-03 03:33:05', '2026-09-03 03:33:05'),
(16, 'MC', 'Medical Certificate', 'For Enrollment', 'Active', 2, '2026-09-03 04:35:42', '2026-09-03 04:35:42'),
(17, 'INC FORM', 'Incomplete Form', 'INC FORM', 'Active', 2, '2026-09-03 05:16:20', '2026-09-03 05:16:20'),
(18, 'GRD', 'Grade Sheet', 'Per-semester subject grade report used to build a student''s digitized transcript.', 'Active', NULL, '2026-09-25 00:00:00', '2026-09-25 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `document_reviews`
--

CREATE TABLE `document_reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `document_id` bigint(20) UNSIGNED NOT NULL,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `decision` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Approved',
  `notes` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_reviews`
--

INSERT INTO `document_reviews` (`id`, `document_id`, `reviewed_by`, `decision`, `notes`, `reviewed_at`, `created_at`) VALUES
(11, 11, NULL, 'Rejected', NULL, '2026-09-03 05:11:32', '2026-09-03 05:01:18'),
(14, 14, NULL, 'Rejected', NULL, '2026-09-06 02:40:34', '2026-09-06 02:39:50'),
(16, 16, NULL, 'Rejected', NULL, '2026-09-07 13:33:53', '2026-09-07 13:20:42');

-- --------------------------------------------------------

--
-- Table structure for table `gradesheet_entries`
--

CREATE TABLE `gradesheet_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `document_id` bigint(20) UNSIGNED DEFAULT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `student_id` bigint(20) UNSIGNED DEFAULT NULL,
  `student_name` varchar(160) DEFAULT NULL,
  `student_number` varchar(30) DEFAULT NULL,
  `year_course` varchar(80) DEFAULT NULL,
  `academic_year` year(4) DEFAULT NULL,
  `semester` varchar(30) DEFAULT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(255) NOT NULL,
  `source_pages` text DEFAULT NULL,
  `units` decimal(4,2) DEFAULT NULL,
  `grade` varchar(10) DEFAULT NULL,
  `remarks` varchar(100) DEFAULT NULL,
  `needs_review` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gradesheet_batches`
--

CREATE TABLE `gradesheet_batches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size_bytes` bigint(20) UNSIGNED DEFAULT NULL,
  `uploaded_by` bigint(20) UNSIGNED NOT NULL,
  `ocr_status` enum('Pending','Processing','Completed','Failed') NOT NULL DEFAULT 'Pending',
  `ocr_text` longtext DEFAULT NULL,
  `ocr_error` text DEFAULT NULL,
  `status` enum('Needs Review','Completed','Failed') NOT NULL DEFAULT 'Needs Review',
  `detected_student_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `committed_entry_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `recipient_id` bigint(20) UNSIGNED DEFAULT NULL,
  `actor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(120) NOT NULL,
  `message` varchar(255) NOT NULL,
  `resource` varchar(50) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `recipient_id`, `actor_id`, `title`, `message`, `resource`, `entity_id`, `is_read`, `created_at`) VALUES
(1, NULL, 2, 'Document update received', 'Administrator submitted a document upload update.', 'documents', 15, 1, '2026-09-07 13:13:40');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_number` varchar(30) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `middle_initial` varchar(10) DEFAULT NULL,
  `last_name` varchar(80) NOT NULL,
  `gender` enum('Female','Male','Other','Undisclosed') NOT NULL DEFAULT 'Undisclosed',
  `birthdate` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `nationality` varchar(80) DEFAULT NULL,
  `civil_status` enum('Single','Married','Widowed','Separated','Undisclosed') NOT NULL DEFAULT 'Undisclosed',
  `contact_number` varchar(30) DEFAULT NULL,
  `college_id` bigint(20) UNSIGNED DEFAULT NULL,
  `course_id` bigint(20) UNSIGNED DEFAULT NULL,
  `major_id` bigint(20) UNSIGNED DEFAULT NULL,
  `academic_year` year(4) DEFAULT NULL,
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year','5th Year','Graduate','Undisclosed') NOT NULL DEFAULT 'Undisclosed',
  `status` enum('Active','Inactive','Graduated') NOT NULL DEFAULT 'Active',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_number`, `first_name`, `middle_initial`, `last_name`, `gender`, `birthdate`, `address`, `nationality`, `civil_status`, `contact_number`, `college_id`, `course_id`, `academic_year`, `year_level`, `status`, `created_at`, `updated_at`) VALUES
(1, '2026-001234', 'Juanita', 'R.', 'Dela Cruz', 'Female', '2004-01-14', 'San Roque, Zamboanga City', NULL, 'Single', '0917 555 0134', 4, 9, '2026', '3rd Year', 'Active', '2026-09-03 03:33:05', '2026-09-07 12:15:50'),
(2, '2026-001198', 'Miguel', 'T.', 'Ramos', 'Male', '2003-08-22', 'Tetuan, Zamboanga City', NULL, 'Single', '0920 424 7882', 2, 2, '2026', '4th Year', 'Active', '2026-09-03 03:33:05', '2026-09-03 03:33:05'),
(3, '2026-000987', 'Angela', 'L.', 'Santos', 'Female', '2005-05-03', 'Guiwan, Zamboanga City', NULL, 'Single', '0918 302 6641', 2, 2, '2026', '2nd Year', 'Active', '2026-09-03 03:33:05', '2026-09-07 12:16:12'),
(4, '2026-000876', 'Mark', 'J.', 'Salazar', 'Male', '2004-11-19', 'Tumaga, Zamboanga City', NULL, 'Single', '0919 448 2207', 5, 6, '2026', '3rd Year', 'Active', '2026-09-03 03:33:05', '2026-09-07 13:19:03'),
(9, '23-76339', 'Michael Angelo', 'B', 'Jayuma', 'Male', '2003-12-17', 'Ayala Z.C', NULL, 'Single', '09533227808', 4, 9, '2026', '4th Year', 'Active', '2026-09-06 02:36:34', '2026-09-06 02:37:44');

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_years`
--

CREATE TABLE `enrollment_years` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollment_years`
--

INSERT INTO `enrollment_years` (`id`, `year`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '2026', 1, '2026-09-09 00:00:00', '2026-09-09 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `id_number` varchar(30) DEFAULT NULL,
  `email` varchar(190) NOT NULL,
  `role` enum('Admin','Staff') NOT NULL DEFAULT 'Admin',
  `password_hash` varchar(255) NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `id_number`, `email`, `role`, `password_hash`, `status`, `last_login_at`, `created_at`, `updated_at`) VALUES
(2, 'Administrator', NULL, 'admin@zppsu.edu.ph', 'Admin', '$2y$10$IXI7oFzMkALp.yhV0ECsQOS1uvU5sBcUJ7LciyPXwAdJyVvQ3o/rW', 'Active', '2026-09-07 13:34:39', '2026-09-03 03:33:04', '2026-09-07 13:34:39'),
(5, 'Registrar Staff', NULL, 'staff@zppsu.edu.ph', 'Staff', '$2y$10$3kxwlFT0BEKWSCTCWTXQk.ij7U.6ezB1TF.GO0S9IwYphV3h/ihTW', 'Active', NULL, '2026-10-08 00:00:00', '2026-10-08 00:00:00');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_archive_analytics`
-- (See below for the actual view)
--
CREATE TABLE `vw_archive_analytics` (
`total_students` bigint(21)
,`total_documents` bigint(21)
,`total_colleges` bigint(21)
,`total_courses` bigint(21)
,`completed_documents` bigint(21)
,`processing_documents` bigint(21)
,`documents_needing_review` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_recent_uploads`
-- (See below for the actual view)
--
CREATE TABLE `vw_recent_uploads` (
`document_id` bigint(20) unsigned
,`original_filename` varchar(255)
,`mime_type` varchar(100)
,`file_size_bytes` bigint(20) unsigned
,`student_id` bigint(20) unsigned
,`student_name` varchar(161)
,`student_number` varchar(30)
,`category_code` varchar(12)
,`category_name` varchar(150)
,`uploaded_by` bigint(20) unsigned
,`uploaded_by_name` varchar(120)
,`status` enum('Processing','Needs review','Uploaded','Rejected','Archived')
,`upload_source` enum('Camera','Manual upload')
,`ocr_status` enum('Pending','Processing','Completed','Failed')
,`ocr_text` longtext
,`ocr_error` text
,`ocr_processed_at` datetime
,`ai_summary` text
,`ai_detected_category` varchar(150)
,`uploaded_at` datetime
,`updated_at` datetime
);

-- --------------------------------------------------------

--
-- Structure for view `vw_archive_analytics`
--
DROP TABLE IF EXISTS `vw_archive_analytics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_archive_analytics`  AS SELECT (select count(0) from `students` where `students`.`status` <> 'Inactive' and `students`.`deleted_at` is null) AS `total_students`, (select count(0) from `documents` where `documents`.`status` = 'Archived') AS `total_documents`, (select count(0) from `colleges` where `colleges`.`status` = 'Active') AS `total_colleges`, (select count(0) from `courses` where `courses`.`status` = 'Active') AS `total_courses`, (select count(0) from `documents` where `documents`.`status` = 'Archived') AS `completed_documents`, (select count(0) from `documents` where `documents`.`status` = 'Processing') AS `processing_documents`, (select count(0) from `documents` where `documents`.`status` = 'Needs review') AS `documents_needing_review` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_recent_uploads`
--
DROP TABLE IF EXISTS `vw_recent_uploads`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_recent_uploads`  AS SELECT `d`.`id` AS `document_id`, `d`.`original_filename` AS `original_filename`, `d`.`mime_type` AS `mime_type`, `d`.`file_size_bytes` AS `file_size_bytes`, `d`.`student_id` AS `student_id`, concat(`s`.`first_name`,' ',`s`.`last_name`) AS `student_name`, `s`.`student_number` AS `student_number`, `c`.`code` AS `category_code`, `c`.`name` AS `category_name`, `u`.`id` AS `uploaded_by`, `u`.`full_name` AS `uploaded_by_name`, `d`.`status` AS `status`, `d`.`upload_source` AS `upload_source`, `d`.`ocr_status` AS `ocr_status`, `d`.`ocr_text` AS `ocr_text`, `d`.`ocr_error` AS `ocr_error`, `d`.`ocr_processed_at` AS `ocr_processed_at`, `d`.`ai_summary` AS `ai_summary`, `d`.`ai_detected_category` AS `ai_detected_category`, `d`.`uploaded_at` AS `uploaded_at`, `d`.`updated_at` AS `updated_at` FROM (((`documents` `d` join `students` `s` on(`s`.`id` = `d`.`student_id`)) join `document_categories` `c` on(`c`.`id` = `d`.`category_id`)) join `users` `u` on(`u`.`id` = `d`.`uploaded_by`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_user_date` (`user_id`,`created_at`),
  ADD KEY `idx_activity_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `colleges`
--
ALTER TABLE `colleges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_course_college_name` (`college_id`,`name`);

--
-- Indexes for table `course_majors`
--
ALTER TABLE `course_majors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_course_major_name` (`course_id`,`name`),
  ADD KEY `fk_course_majors_course` (`course_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_documents_status_date` (`status`,`uploaded_at`),
  ADD KEY `idx_documents_student` (`student_id`),
  ADD KEY `idx_documents_uploader` (`uploaded_by`,`uploaded_at`),
  ADD KEY `fk_documents_category` (`category_id`);

--
-- Indexes for table `document_categories`
--
ALTER TABLE `document_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `fk_categories_creator` (`created_by`);

--
-- Indexes for table `document_reviews`
--
ALTER TABLE `document_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_review_document` (`document_id`),
  ADD KEY `fk_reviews_user` (`reviewed_by`);

--
-- Indexes for table `gradesheet_entries`
--
ALTER TABLE `gradesheet_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gradesheet_student` (`student_id`,`academic_year`,`semester`),
  ADD KEY `idx_gradesheet_student_name` (`student_name`),
  ADD KEY `idx_gradesheet_document` (`document_id`),
  ADD KEY `idx_gradesheet_batch` (`batch_id`);

--
-- Indexes for table `gradesheet_batches`
--
ALTER TABLE `gradesheet_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_batch_status` (`status`,`uploaded_at`),
  ADD KEY `fk_gradesheet_batch_uploader` (`uploaded_by`);

--
-- Indexes for table `enrollment_years`
--
ALTER TABLE `enrollment_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_enrollment_year` (`year`),
  ADD KEY `fk_years_creator` (`created_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_recipient_read` (`recipient_id`,`is_read`,`created_at`),
  ADD KEY `idx_notifications_actor` (`actor_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD KEY `idx_students_name` (`last_name`,`first_name`),
  ADD KEY `idx_students_college` (`college_id`),
  ADD KEY `fk_students_course` (`course_id`),
  ADD KEY `fk_students_major` (`major_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `colleges`
--
ALTER TABLE `colleges`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `course_majors`
--
ALTER TABLE `course_majors`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `document_categories`
--
ALTER TABLE `document_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `document_reviews`
--
ALTER TABLE `document_reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `gradesheet_entries`
--
ALTER TABLE `gradesheet_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gradesheet_batches`
--
ALTER TABLE `gradesheet_batches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollment_years`
--
ALTER TABLE `enrollment_years`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `fk_courses_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `course_majors`
--
ALTER TABLE `course_majors`
  ADD CONSTRAINT `fk_course_majors_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `fk_documents_category` FOREIGN KEY (`category_id`) REFERENCES `document_categories` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_documents_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_documents_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `gradesheet_entries`
--
ALTER TABLE `gradesheet_entries`
  ADD CONSTRAINT `fk_gradesheet_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_gradesheet_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_gradesheet_entries_batch` FOREIGN KEY (`batch_id`) REFERENCES `gradesheet_batches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `gradesheet_batches`
--
ALTER TABLE `gradesheet_batches`
  ADD CONSTRAINT `fk_gradesheet_batch_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `document_categories`
--
ALTER TABLE `document_categories`
  ADD CONSTRAINT `fk_categories_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `document_reviews`
--
ALTER TABLE `document_reviews`
  ADD CONSTRAINT `fk_reviews_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reviews_user` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notifications_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_students_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_students_major` FOREIGN KEY (`major_id`) REFERENCES `course_majors` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
