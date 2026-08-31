-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 28, 2026 at 04:39 AM
-- Server version: 11.8.8-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u850523537_BodaERP26`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name_snapshot` varchar(150) DEFAULT NULL,
  `role_snapshot` varchar(20) DEFAULT NULL,
  `city_id` char(3) DEFAULT NULL,
  `action` enum('LOGIN','LOGOUT','LOGIN_FAILED','CREATE','UPDATE','DELETE') NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` varchar(50) DEFAULT NULL,
  `details` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `user_name_snapshot`, `role_snapshot`, `city_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`) VALUES
(1, NULL, 'admin@bodaerp.com', NULL, NULL, 'LOGIN_FAILED', NULL, NULL, 'Invalid email or password', '41.210.147.174', '2026-08-26 11:25:38'),
(2, NULL, 'admin@bodaerp.com', NULL, NULL, 'LOGIN_FAILED', NULL, NULL, 'Invalid email or password', '41.210.147.174', '2026-08-26 11:25:49'),
(3, NULL, 'admin@bodaerp.com', NULL, NULL, 'LOGIN_FAILED', NULL, NULL, 'Invalid email or password', '41.210.141.115', '2026-08-27 04:00:23'),
(4, NULL, 'admin@bodaerp.com', NULL, NULL, 'LOGIN_FAILED', NULL, NULL, 'Invalid email or password', '41.210.141.115', '2026-08-27 04:00:47'),
(5, NULL, 'admin@bodaerp.com', NULL, NULL, 'LOGIN_FAILED', NULL, NULL, 'Invalid email or password', '41.190.148.251', '2026-08-27 05:03:01');

-- --------------------------------------------------------

--
-- Table structure for table `cities`
--

CREATE TABLE `cities` (
  `id` char(3) NOT NULL,
  `name` varchar(120) NOT NULL,
  `country` varchar(80) NOT NULL DEFAULT 'Uganda',
  `currency` char(3) NOT NULL DEFAULT 'UGX',
  `logo_path` varchar(255) DEFAULT NULL,
  `annual_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `fiscal_year` varchar(20) NOT NULL DEFAULT '2026/2027',
  `id_prefix` varchar(20) NOT NULL,
  `status` enum('active','pending','suspended') NOT NULL DEFAULT 'pending',
  `contact_email` varchar(150) DEFAULT NULL,
  `contact_phone` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `payment_gateway` varchar(60) DEFAULT NULL,
  `sms_gateway` varchar(60) DEFAULT NULL,
  `revenue_split_city` tinyint(3) UNSIGNED NOT NULL DEFAULT 60,
  `revenue_split_association` tinyint(3) UNSIGNED NOT NULL DEFAULT 26,
  `revenue_split_platform` tinyint(3) UNSIGNED NOT NULL DEFAULT 14,
  `compliance_target` tinyint(3) UNSIGNED NOT NULL DEFAULT 70,
  `grace_period_days` smallint(5) UNSIGNED NOT NULL DEFAULT 30,
  `reminder_days` smallint(5) UNSIGNED NOT NULL DEFAULT 14,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `enforcement_actions`
--

CREATE TABLE `enforcement_actions` (
  `id` int(11) NOT NULL,
  `action_code` varchar(20) NOT NULL,
  `rider_id` int(11) DEFAULT NULL,
  `city_id` char(3) NOT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `plate` varchar(20) DEFAULT NULL,
  `type` enum('warning','fine','suspension','impound') NOT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `action_date` date NOT NULL,
  `status` enum('pending','resolved','closed') NOT NULL DEFAULT 'pending',
  `officer_user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_sessions`
--

CREATE TABLE `login_sessions` (
  `id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_activity` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `type` enum('Renewal Reminder','Payment Confirmed','Important Update','ID Card Issued','Registration Complete') NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `city_id` char(3) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('Mobile Money','Cash','Bank Transfer') NOT NULL,
  `receipt_number` varchar(30) NOT NULL,
  `status` enum('Confirmed','Pending','Failed') NOT NULL DEFAULT 'Confirmed',
  `fiscal_year` varchar(20) NOT NULL,
  `paid_at` datetime NOT NULL,
  `collected_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `platform_settings`
--

CREATE TABLE `platform_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `platform_name` varchar(100) NOT NULL DEFAULT 'BodaERP',
  `operator_name` varchar(150) NOT NULL DEFAULT 'Kakebe Technologies Limited',
  `support_email` varchar(150) DEFAULT NULL,
  `support_phone` varchar(30) DEFAULT NULL,
  `default_split_city` tinyint(3) UNSIGNED NOT NULL DEFAULT 60,
  `default_split_association` tinyint(3) UNSIGNED NOT NULL DEFAULT 26,
  `default_split_platform` tinyint(3) UNSIGNED NOT NULL DEFAULT 14,
  `require_2fa` tinyint(1) NOT NULL DEFAULT 1,
  `maintenance_mode` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `platform_settings`
--

INSERT INTO `platform_settings` (`id`, `platform_name`, `operator_name`, `support_email`, `support_phone`, `default_split_city`, `default_split_association`, `default_split_platform`, `require_2fa`, `maintenance_mode`, `updated_at`) VALUES
(1, 'BodaERP', 'Kakebe Technologies Limited', 'support@kakebe.tech', '+256 700 000 000', 60, 26, 14, 1, 0, '2026-08-08 08:12:40');

-- --------------------------------------------------------

--
-- Table structure for table `riders`
--

CREATE TABLE `riders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `city_id` char(3) NOT NULL,
  `stage_id` int(11) NOT NULL,
  `id_number` varchar(30) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `nin` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `marital_status` enum('Single','Married','Divorced','Widowed') DEFAULT NULL,
  `phone` varchar(30) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `physical_address` varchar(255) DEFAULT NULL,
  `next_of_kin_name` varchar(150) DEFAULT NULL,
  `next_of_kin_contact` varchar(30) DEFAULT NULL,
  `bike_plate` varchar(20) NOT NULL,
  `bike_model` varchar(100) DEFAULT NULL,
  `route` varchar(150) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `status` enum('active','expired','pending') NOT NULL DEFAULT 'pending',
  `member_since` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `annual_tax` decimal(12,2) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stages`
--

CREATE TABLE `stages` (
  `id` int(11) NOT NULL,
  `city_id` char(3) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(120) NOT NULL,
  `location` varchar(150) DEFAULT NULL,
  `route` varchar(150) DEFAULT NULL,
  `chairperson_name` varchar(150) DEFAULT NULL,
  `chairperson_phone` varchar(30) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','city_admin','chairperson','rider') NOT NULL,
  `city_id` char(3) DEFAULT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `v_stage_stats`
--

CREATE TABLE `v_stage_stats` (
  `stage_id` int(11) DEFAULT NULL,
  `city_id` char(3) DEFAULT NULL,
  `code` varchar(20) DEFAULT NULL,
  `name` varchar(120) DEFAULT NULL,
  `rider_count` bigint(21) DEFAULT NULL,
  `active_count` decimal(23,0) DEFAULT NULL,
  `expired_count` decimal(23,0) DEFAULT NULL,
  `pending_count` decimal(23,0) DEFAULT NULL,
  `compliance_pct` decimal(28,1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_created` (`created_at`),
  ADD KEY `idx_audit_action` (`action`);

--
-- Indexes for table `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cities_created_by` (`created_by`);

--
-- Indexes for table `enforcement_actions`
--
ALTER TABLE `enforcement_actions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `action_code` (`action_code`),
  ADD KEY `rider_id` (`rider_id`),
  ADD KEY `stage_id` (`stage_id`),
  ADD KEY `officer_user_id` (`officer_user_id`),
  ADD KEY `idx_enforcement_city` (`city_id`),
  ADD KEY `idx_enforcement_status` (`status`);

--
-- Indexes for table `login_sessions`
--
ALTER TABLE `login_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sessions_user` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_rider` (`rider_id`,`is_read`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `collected_by` (`collected_by`),
  ADD KEY `idx_payments_rider` (`rider_id`),
  ADD KEY `idx_payments_city_status` (`city_id`,`status`),
  ADD KEY `idx_payments_paidat` (`paid_at`);

--
-- Indexes for table `platform_settings`
--
ALTER TABLE `platform_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `riders`
--
ALTER TABLE `riders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD UNIQUE KEY `nin` (`nin`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_riders_stage` (`stage_id`),
  ADD KEY `idx_riders_city_status` (`city_id`,`status`),
  ADD KEY `idx_riders_expiry` (`expiry_date`);

--
-- Indexes for table `stages`
--
ALTER TABLE `stages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_stage_city` (`city_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `stage_id` (`stage_id`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_city` (`city_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `enforcement_actions`
--
ALTER TABLE `enforcement_actions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `riders`
--
ALTER TABLE `riders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stages`
--
ALTER TABLE `stages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cities`
--
ALTER TABLE `cities`
  ADD CONSTRAINT `fk_cities_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `enforcement_actions`
--
ALTER TABLE `enforcement_actions`
  ADD CONSTRAINT `enforcement_actions_ibfk_1` FOREIGN KEY (`rider_id`) REFERENCES `riders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `enforcement_actions_ibfk_2` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  ADD CONSTRAINT `enforcement_actions_ibfk_3` FOREIGN KEY (`stage_id`) REFERENCES `stages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `enforcement_actions_ibfk_4` FOREIGN KEY (`officer_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `login_sessions`
--
ALTER TABLE `login_sessions`
  ADD CONSTRAINT `login_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`rider_id`) REFERENCES `riders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`rider_id`) REFERENCES `riders` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `riders`
--
ALTER TABLE `riders`
  ADD CONSTRAINT `riders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `riders_ibfk_2` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  ADD CONSTRAINT `riders_ibfk_3` FOREIGN KEY (`stage_id`) REFERENCES `stages` (`id`),
  ADD CONSTRAINT `riders_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stages`
--
ALTER TABLE `stages`
  ADD CONSTRAINT `stages_ibfk_1` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`stage_id`) REFERENCES `stages` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
