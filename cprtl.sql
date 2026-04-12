-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 05, 2026 at 04:25 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cprtl`
--

-- --------------------------------------------------------

--
-- Table structure for table `allowed_roles`
--

CREATE TABLE `allowed_roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `allowed_roles`
--

INSERT INTO `allowed_roles` (`role_id`, `role_name`) VALUES
(9, 'admin'),
(14, 'driver'),
(10, 'electrition'),
(7, 'house_keeping'),
(3, 'maintenance'),
(8, 'network/it_team'),
(5, 'rector'),
(6, 'security'),
(2, 'staff'),
(1, 'student'),
(15, 'sub_hk'),
(16, 'sub_house_keeping'),
(13, 'sub_it'),
(12, 'sub_security'),
(11, 'super_visor'),
(4, 'warden');

-- --------------------------------------------------------

--
-- Table structure for table `attachments`
--

CREATE TABLE `attachments` (
  `attachment_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attachments`
--

INSERT INTO `attachments` (`attachment_id`, `file_path`, `file_type`, `file_size`, `uploaded_at`) VALUES
(25, 'uploads/1753707582_Screenshot_2025-07-27_152141.png', 'image/png', 17095, '2025-07-28 12:59:42'),
(26, 'uploads/1753761665_NMIMS_Logo.jpg', 'image/jpeg', 16366, '2025-07-29 04:01:05'),
(27, 'uploads/1753764594_NMIMS_Logo.jpg', 'image/jpeg', 16366, '2025-07-29 04:49:54'),
(28, 'uploads/1754369541_Screenshot_2023-08-23_215018.png', 'image/png', 410903, '2025-08-05 04:52:21'),
(29, 'uploads/1760418505_Screenshot_2025-10-14_103727.png', 'image/png', 281411, '2025-10-14 05:08:25'),
(30, 'uploads/1760421883_Screenshot_2025-10-14_103727.png', 'image/png', 281411, '2025-10-14 06:04:43'),
(31, 'uploads/1767019191_Screenshot_2025-10-28_153400.png', 'image/png', 891584, '2025-12-29 14:39:51'),
(32, 'uploads/1767025172_Screenshot_2025-10-28_112425.png', 'image/png', 404697, '2025-12-29 16:19:32'),
(33, 'uploads/1768192587_Screenshot_2026-01-11_205539.png', 'image/png', 235357, '2026-01-12 04:36:27'),
(34, 'uploads/1768194561_Screenshot__226_.png', 'image/png', 263816, '2026-01-12 05:09:21'),
(35, 'uploads/1768195226_Screenshot__226_.png', 'image/png', 263816, '2026-01-12 05:20:26'),
(36, 'uploads/1768209598_Screenshot_2026-01-12_104245.png', 'image/png', 322926, '2026-01-12 09:19:58'),
(37, 'uploads/1768290300_Screenshot__226_.png', 'image/png', 263816, '2026-01-13 07:45:00'),
(38, 'uploads/1772690724_Screenshot_2026-02-24_120938.png', 'image/png', 85876, '2026-03-05 06:05:24'),
(39, 'uploads/1772691058_Screenshot__233_.png', 'image/png', 294838, '2026-03-05 06:10:58');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `feedback_text` text NOT NULL,
  `rating` tinyint(1) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `ticket_id`, `user_id`, `feedback_text`, `rating`, `created_at`) VALUES
(19, 70, 40, '', 5, '2026-01-12 10:52:52');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `ticket_id`, `message`, `is_read`, `created_at`) VALUES
(150, 37, 55, 'New ticket #55 created by user 7057230002.', 1, '2025-07-29 04:01:05'),
(151, 30, 55, 'Ticket #55 assigned to you.', 1, '2025-07-29 04:03:20'),
(152, 30, 55, 'Unsatisfied feedback on Ticket #55 from user 7057230002: \"do it again\"', 1, '2025-07-29 04:06:47'),
(153, 37, 55, 'Unsatisfied feedback on Ticket #55 from user 7057230002: \"do it again\"', 1, '2025-07-29 04:06:51'),
(154, 39, 55, 'Ticket #55 assigned to you.', 1, '2025-07-29 04:48:32'),
(187, 37, 69, 'New ticket #69 created by user 0123456.', 1, '2026-01-12 05:09:21'),
(188, 40, 69, 'Ticket #69 has been assigned to you by user 0123456.', 1, '2026-01-12 05:09:25'),
(189, 37, 70, 'New ticket #70 created by user 7057230003.', 1, '2026-01-12 05:20:26'),
(190, 39, 70, 'Ticket #70 has been assigned to you by user 7057230003.', 1, '2026-01-12 05:20:30'),
(191, 37, 71, 'New ticket #71 created by user 7057230003.', 1, '2026-01-12 07:49:02'),
(192, 40, 71, 'Ticket #71 has been assigned to you by user 7057230003.', 1, '2026-01-12 07:49:06'),
(193, 37, 72, 'New ticket #72 created by user 7057230003.', 1, '2026-01-12 07:50:21'),
(194, 37, 73, 'New ticket #73 created by user 70572300022.', 1, '2026-01-12 09:19:58'),
(195, 40, 73, 'Ticket #73 has been assigned to you by user 70572300022.', 0, '2026-01-12 09:20:04'),
(196, 37, 74, 'New ticket #74 created by user 70572300022.', 1, '2026-01-13 07:45:01'),
(197, 30, 74, 'Ticket #74 has been assigned to you by user 70572300022.', 1, '2026-01-13 07:45:06'),
(198, 37, 75, 'New ticket #75 created by user 7057230003.', 1, '2026-01-13 10:52:13'),
(199, 40, 75, 'Ticket #75 has been assigned to you by user 7057230003.', 0, '2026-01-13 10:52:16'),
(200, 37, 76, 'New ticket #76 created by user 90972300021.', 1, '2026-01-13 13:44:48'),
(201, 30, 76, 'Ticket #76 has been assigned to you by user 90972300021.', 1, '2026-01-13 13:44:53'),
(202, 37, 77, 'New ticket #77 created by user 90972300021.', 1, '2026-01-13 14:21:39'),
(203, 30, 77, 'Ticket #77 has been assigned to you by user 90972300021.', 1, '2026-01-13 14:21:44'),
(204, 37, 78, 'New ticket #78 created by user 00000.', 1, '2026-01-13 14:23:00'),
(205, 30, 78, 'Ticket #78 has been assigned to you by user 00000.', 1, '2026-01-13 14:23:05'),
(206, 37, 79, 'New ticket #79 created by user 00000.', 1, '2026-01-13 14:23:08'),
(207, 30, 79, 'Ticket #79 has been assigned to you by user 00000.', 1, '2026-01-13 14:23:11'),
(208, 37, 80, 'New ticket #80 created by user 70572300022.', 1, '2026-03-05 05:05:18'),
(209, 30, 80, 'Ticket #80 has been assigned to you by user 70572300022.', 0, '2026-03-05 05:05:19'),
(210, 37, 81, 'New ticket #81 created by user 70572300022.', 1, '2026-03-05 05:44:43'),
(211, 39, 81, 'Ticket #81 has been assigned to you by user 70572300022.', 1, '2026-03-05 05:44:43'),
(212, 37, 82, 'New ticket #82 created by user 70572300022.', 1, '2026-03-05 06:05:24'),
(213, 39, 82, 'Ticket #82 has been assigned to you by user 70572300022.', 1, '2026-03-05 06:05:25'),
(214, 37, 83, 'New ticket #83 created by user 70572300015.', 1, '2026-03-05 06:10:58'),
(215, 39, 83, 'Ticket #83 has been assigned to you by user 70572300015.', 1, '2026-03-05 06:10:58');

-- --------------------------------------------------------

--
-- Table structure for table `statushistory`
--

CREATE TABLE `statushistory` (
  `history_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `statushistory`
--

INSERT INTO `statushistory` (`history_id`, `ticket_id`, `status`, `timestamp`) VALUES
(1, 70, 'Received', '2026-01-12 10:50:26'),
(2, 70, 'In Progress', '2026-01-12 10:51:38'),
(3, 70, 'Solution Proposed', '2026-01-12 10:51:50'),
(4, 70, 'Resolved', '2026-01-12 10:52:14'),
(5, 70, 'Closed', '2026-01-12 10:52:52'),
(6, 71, 'Received', '2026-01-12 13:19:02'),
(7, 71, 'Resolved', '2026-01-12 13:19:46'),
(8, 72, 'Received', '2026-01-12 13:20:21'),
(9, 72, 'In Progress', '2026-01-12 13:24:45'),
(10, 72, 'Solution Proposed', '2026-01-12 13:24:55'),
(11, 72, 'Resolved', '2026-01-12 13:25:11'),
(12, 73, 'Received', '2026-01-12 14:49:58'),
(13, 73, 'In Progress', '2026-01-12 14:57:18'),
(14, 74, 'Received', '2026-01-13 13:15:00'),
(15, 74, 'In Progress', '2026-01-13 13:17:45'),
(16, 74, 'Resolved', '2026-01-13 13:18:26'),
(17, 75, 'Received', '2026-01-13 16:22:13'),
(18, 76, 'Received', '2026-01-13 19:14:48'),
(19, 76, 'In Progress', '2026-01-13 19:26:25'),
(20, 76, 'Solution Proposed', '2026-01-13 19:26:38'),
(21, 76, 'Resolved', '2026-01-13 19:27:04'),
(22, 77, 'Received', '2026-01-13 19:51:39'),
(23, 78, 'Received', '2026-01-13 19:53:00'),
(24, 79, 'Received', '2026-01-13 19:53:08'),
(25, 77, 'In Progress', '2026-01-13 19:54:08'),
(26, 77, 'Solution Proposed', '2026-01-13 19:54:12'),
(27, 77, 'Resolved', '2026-01-13 19:54:16'),
(28, 77, 'In Progress', '2026-01-13 20:15:15'),
(29, 77, 'Solution Proposed', '2026-01-13 20:15:21'),
(30, 76, 'Solution Proposed', '2026-01-13 20:20:19'),
(31, 76, 'Resolved', '2026-01-13 20:20:59'),
(32, 77, 'Resolved', '2026-01-13 20:34:14'),
(33, 80, 'Received', '2026-03-05 10:35:18'),
(34, 80, 'In Progress', '2026-03-05 10:39:50'),
(35, 80, 'Solution Proposed', '2026-03-05 10:40:40'),
(36, 80, 'Resolved', '2026-03-05 10:46:11'),
(37, 81, 'Received', '2026-03-05 11:14:43'),
(38, 81, 'Solution Proposed', '2026-03-05 11:25:41'),
(39, 81, 'Solution Proposed', '2026-03-05 11:25:55'),
(40, 81, 'Resolved', '2026-03-05 11:34:22'),
(41, 82, 'Received', '2026-03-05 11:35:24'),
(42, 82, 'Solution Proposed', '2026-03-05 11:37:35'),
(43, 82, 'Solution Proposed', '2026-03-05 11:38:02'),
(44, 82, 'Resolved', '2026-03-05 11:38:40'),
(45, 82, 'Resolved', '2026-03-05 11:40:25'),
(46, 82, 'Resolved', '2026-03-05 11:40:29'),
(47, 83, 'Received', '2026-03-05 11:40:58'),
(48, 83, 'Solution Proposed', '2026-03-05 11:42:10'),
(49, 83, 'In Progress', '2026-03-05 11:42:43'),
(50, 83, 'In Progress', '2026-03-05 11:48:18'),
(51, 83, 'Solution Proposed', '2026-03-05 11:48:30'),
(52, 83, 'Solution Proposed', '2026-03-05 11:48:41'),
(53, 83, 'Resolved', '2026-03-05 11:51:09');

-- --------------------------------------------------------

--
-- Table structure for table `substaffapprovals`
--

CREATE TABLE `substaffapprovals` (
  `approval_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `sub_staff_id` int(11) NOT NULL,
  `parent_staff_id` int(11) NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `parent_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `substaffapprovals`
--

INSERT INTO `substaffapprovals` (`approval_id`, `ticket_id`, `sub_staff_id`, `parent_staff_id`, `submitted_at`, `approved_at`, `status`, `parent_notes`) VALUES
(1, 77, 63, 30, '2026-01-13 14:24:23', '2026-01-13 14:24:57', 'approved', NULL),
(2, 77, 63, 30, '2026-01-13 14:45:33', '2026-01-13 14:46:26', 'approved', NULL),
(3, 77, 63, 30, '2026-01-13 14:47:01', '2026-01-13 14:47:27', 'approved', NULL),
(4, 76, 63, 30, '2026-01-13 14:50:29', '2026-01-13 14:50:59', 'approved', NULL),
(5, 77, 63, 30, '2026-01-13 15:03:36', '2026-01-13 15:04:14', 'approved', NULL),
(6, 80, 63, 30, '2026-03-05 05:13:00', '2026-03-05 05:16:11', 'approved', NULL),
(7, 81, 65, 39, '2026-03-05 05:55:57', '2026-03-05 06:04:22', 'approved', NULL),
(8, 82, 65, 39, '2026-03-05 06:08:03', '2026-03-05 06:10:29', 'approved', NULL),
(9, 83, 65, 39, '2026-03-05 06:12:13', NULL, 'rejected', 'once check again'),
(10, 83, 65, 39, '2026-03-05 06:20:42', '2026-03-05 06:21:09', 'approved', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('low','medium','high') DEFAULT NULL,
  `location` varchar(255) NOT NULL,
  `status` enum('Received','In Progress','Solution Proposed','Resolved','Closed') NOT NULL DEFAULT 'Received',
  `sub_staff_status` varchar(50) DEFAULT NULL,
  `attachment_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_to` int(11) DEFAULT NULL,
  `reassigned_to` int(11) DEFAULT NULL,
  `place` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`ticket_id`, `user_id`, `title`, `category`, `description`, `priority`, `location`, `status`, `sub_staff_status`, `attachment_id`, `created_at`, `assigned_to`, `reassigned_to`, `place`) VALUES
(55, 33, 'LAB SWITCH Boards not working', 'infrastructure', 'SWITCH Boards not working', 'high', '3rd floore (lab 1)', 'Closed', NULL, 26, '2025-07-29 04:01:05', 42, NULL, ''),
(69, 37, 'test_iisue', 'hostel', 'sjyfgksfuhk', 'medium', 'at main gate', 'Closed', NULL, 34, '2026-01-12 05:09:21', 40, NULL, ''),
(70, 40, 'test time stamp', 'other', 'test time stamp', 'medium', 'at main gate', 'Closed', NULL, 35, '2026-01-12 05:20:26', 40, NULL, ''),
(71, 40, 'test-2', 'infrastructure', 'kujyhg', 'medium', 'hfvg', 'Resolved', NULL, NULL, '2026-01-12 07:49:02', 40, NULL, ''),
(72, 40, 'fij', 'infrastructure', 'rt', 'medium', 'ert', 'Resolved', NULL, NULL, '2026-01-12 07:50:21', 40, NULL, ''),
(73, 33, 'test issiu', 'hostel', 'test dec', 'medium', 'hostal block -2', 'Closed', NULL, 36, '2026-01-12 09:19:58', 40, NULL, ''),
(74, 33, 'test_issue', 'security', 'test_dec', 'medium', 'at acadamic block 2nd floor l21', 'Resolved', NULL, 37, '2026-01-13 07:45:00', 30, 63, ''),
(75, 40, 'skdhfju', 'hostel', 'sefkuh', 'medium', 'skfuh', 'Closed', NULL, NULL, '2026-01-13 10:52:13', 40, NULL, ''),
(76, 63, 'rguhfd', 'security', 'skguhr', 'medium', 'rgsuhk', 'Resolved', 'approved_by_parent', NULL, '2026-01-13 13:44:48', 30, 63, ''),
(77, 63, 'tgdf', 'security', 'gdfxhu', 'medium', 'ertyu', 'Resolved', 'approved_by_parent', NULL, '2026-01-13 14:21:39', 30, 63, ''),
(78, 30, 'fgh', 'security', 'dfgh', 'medium', 'erfgth', 'Closed', NULL, NULL, '2026-01-13 14:23:00', 30, NULL, ''),
(79, 30, 'fgh', 'security', 'dfgh', 'high', 'erfgth', 'Closed', NULL, NULL, '2026-01-13 14:23:08', 30, 63, ''),
(80, 33, 'any_bugs', 'other', 'dkuv', 'medium', 'dkfuvh', 'Resolved', 'approved_by_parent', NULL, '2026-03-05 05:05:18', 30, 63, ''),
(81, 33, 'bug_test-2', 'hygiene', 'wefkiuhjsloef', 'medium', 'erifuyh', 'Resolved', 'approved_by_parent', NULL, '2026-03-05 05:44:43', 39, 65, ''),
(82, 33, 'bug_test-3', 'security', 'wsedfrgtyui', 'medium', '1234567', 'Resolved', 'approved_by_parent', 38, '2026-03-05 06:05:24', 39, 65, ''),
(83, 39, 'bug test _4', 'hostel', 'sdgerger', 'medium', 'dfg', 'Resolved', 'approved_by_parent', 39, '2026-03-05 06:10:58', 39, 65, '');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_history`
--

CREATE TABLE `ticket_history` (
  `history_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_status_history`
--

CREATE TABLE `ticket_status_history` (
  `history_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `sap_id` varchar(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL,
  `parent_staff_id` int(11) DEFAULT NULL,
  `is_sub_staff` tinyint(1) DEFAULT 0,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `sap_id`, `email`, `role`, `parent_staff_id`, `is_sub_staff`, `password`, `created_at`, `otp_code`, `otp_expiry`) VALUES
(30, '00000', 'boyini.dhanush09@nmims.in', 'security', NULL, 0, '$2y$10$hUi0lAZbAIq6eVEjG4ZJRe30icNy3hT/zVJIMSU8C6gAf1nAswWVy', '2025-07-25 16:59:20', NULL, NULL),
(33, '70572300022', 'sainath.reddy65@nmims.in', 'student', NULL, 0, '$2y$10$O8Ws.yVWqLJml5zuoQKOO.qteJibWgm7KzBcvpKDNzyf6lGvEQ2e.', '2025-07-26 05:00:34', NULL, NULL),
(37, '0123456', 'dasumanoj.datta19@nmims.in', 'admin', NULL, 0, '$2y$10$BzqLrEGvGUbG9mwC8sDniuOq3UPEjjzoJ/1Kk5xmH.Dn1hEePLgzS', '2025-07-26 05:07:01', NULL, NULL),
(39, '70572300015', 'sai.rishitha15@nmims.in', 'house_keeping', NULL, 0, '$2y$10$jG8WBMAok.D450QLMJX5D.zbXNaBQx../aXoGHtlwaboB8YlqJqA2', '2025-07-29 04:19:46', NULL, NULL),
(40, '7057230003', 'canil.kumar65@nmims.in', 'warden', NULL, 0, '$2y$10$AYXX3Whs2EmHsy6HsrjWLu.Z636rjbp7T2wGH5rSJF0e2BqryWFhO', '2025-07-29 04:22:23', NULL, NULL),
(42, '7057230004', 'naguboyina.divya59@nmims.in', 'staff', NULL, 0, '$2y$10$o7S6Qm6TGywdaJoU34AwyecF7N93MXElj1rzv91RbufoljQU2h93u', '2025-07-29 04:27:56', NULL, NULL),
(43, '000011', 'lam.jahnavy23@nmims.in', 'network/it_team', NULL, 0, '$2y$10$uZEwTk9omFS/CUvqJW48uOIlG4ZZ1.59TkGMoCebeHL98Hkxm/Gay', '2025-10-14 05:01:32', NULL, NULL),
(46, '7057230007', 'bunny.vikram18@gmail.com', 'rector', NULL, 0, '$2y$10$xzYgN.acArT6ue9NxRLHJ.3O41IKVBPz6.lG9/qfqzv91vI07i4Ce', '2025-10-14 06:22:39', NULL, NULL),
(47, '09876543211', 'bunny@nmims.edu.in', 'electrition', NULL, 0, '$2y$10$rSjmzWASaO4LOsP.IV5rOuc4ORT3ysdT/n/ArhjmBpREptXNaVmVq', '2026-01-02 11:12:05', NULL, NULL),
(48, '70572300000', 'abc@nmims.in', 'super_visor', NULL, 0, '$2y$10$ipOuQdLHe28z.F9YDcPbxudzHXrKYUe53bIU59T9hwlXtlu.rJvxe', '2026-01-06 06:15:09', NULL, NULL),
(53, '8057230001', 'ixy@nmims.in', 'student', NULL, 0, '$2y$10$rX2CSrnWFo.ycvzc6kFhYeTfCRCcVoT//KkbKwefQ7700ohuH1lKm', '2026-01-06 08:32:37', NULL, NULL),
(54, '8057230002', 'jxy@nmims.in', 'student', NULL, 0, '$2y$10$2KCubx66gm.DKNtQXDMdJenClGkFIVLQAmAWH0lmW5t7YcRls2WzC', '2026-01-06 08:32:38', NULL, NULL),
(63, '90972300021', 'ksdu@nmims.in', 'sub_security', 30, 1, '$2y$10$WWw7yvNl13M00arP7DqbCOzto.WePlxMbY6X6/Jw045u5ts2n5rxC', '2026-01-13 13:42:47', NULL, NULL),
(64, '456789', 'jgyh@nmims.in', 'driver', NULL, 0, '$2y$10$EAXW.Z49SQMuayd0bZQ0wOEbyOVqmA8aZidFKyVLQxTqfNFvQ2B46', '2026-01-16 09:28:10', NULL, NULL),
(65, '90972300015', 'vikrm.gu16@nmims.in', 'sub_house_keeping', 39, 1, '$2y$10$Q7n25E54198rI7iplEJMWuuCSods16r5OsgSK/yJ6OlYYqFMsf1EK', '2026-03-05 05:50:07', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `allowed_roles`
--
ALTER TABLE `allowed_roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`attachment_id`),
  ADD KEY `idx_file_path` (`file_path`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `feedback_ibfk_1` (`ticket_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_ticket_id` (`ticket_id`);

--
-- Indexes for table `statushistory`
--
ALTER TABLE `statushistory`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `ticket_id` (`ticket_id`);

--
-- Indexes for table `substaffapprovals`
--
ALTER TABLE `substaffapprovals`
  ADD PRIMARY KEY (`approval_id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `sub_staff_id` (`sub_staff_id`),
  ADD KEY `parent_staff_id` (`parent_staff_id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`ticket_id`),
  ADD KEY `attachment_id` (`attachment_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `ticket_history`
--
ALTER TABLE `ticket_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `idx_ticket_id` (`ticket_id`);

--
-- Indexes for table `ticket_status_history`
--
ALTER TABLE `ticket_status_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `ticket_id` (`ticket_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `sap_id` (`sap_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_sap_id` (`sap_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `parent_staff_id` (`parent_staff_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `allowed_roles`
--
ALTER TABLE `allowed_roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `attachments`
--
ALTER TABLE `attachments`
  MODIFY `attachment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=216;

--
-- AUTO_INCREMENT for table `statushistory`
--
ALTER TABLE `statushistory`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `substaffapprovals`
--
ALTER TABLE `substaffapprovals`
  MODIFY `approval_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `ticket_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `ticket_history`
--
ALTER TABLE `ticket_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_status_history`
--
ALTER TABLE `ticket_status_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE CASCADE;

--
-- Constraints for table `statushistory`
--
ALTER TABLE `statushistory`
  ADD CONSTRAINT `statushistory_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE CASCADE;

--
-- Constraints for table `substaffapprovals`
--
ALTER TABLE `substaffapprovals`
  ADD CONSTRAINT `substaffapprovals_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`ticket_id`),
  ADD CONSTRAINT `substaffapprovals_ibfk_2` FOREIGN KEY (`sub_staff_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `substaffapprovals_ibfk_3` FOREIGN KEY (`parent_staff_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`attachment_id`) REFERENCES `attachments` (`attachment_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `ticket_history`
--
ALTER TABLE `ticket_history`
  ADD CONSTRAINT `ticket_history_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_status_history`
--
ALTER TABLE `ticket_status_history`
  ADD CONSTRAINT `ticket_status_history_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`parent_staff_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
