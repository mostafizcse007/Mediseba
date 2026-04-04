-- MediSeba cleaned schema + data merge
-- Built from the InfinityFree export plus local project requirements.
-- Keeps the original dump untouched and adds the appointment chat table.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `appointment_chat_messages`;
DROP TABLE IF EXISTS `prescription_attachments`;
DROP TABLE IF EXISTS `prescriptions`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `doctor_reviews`;
DROP TABLE IF EXISTS `appointment_status_history`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `doctor_timeoffs`;
DROP TABLE IF EXISTS `doctor_schedules`;
DROP TABLE IF EXISTS `otp_requests`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `system_settings`;
DROP TABLE IF EXISTS `rate_limits`;
DROP TABLE IF EXISTS `patient_profiles`;
DROP TABLE IF EXISTS `doctor_profiles`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role` enum('patient','doctor','admin') NOT NULL DEFAULT 'patient',
  `status` enum('active','inactive','suspended','deleted') NOT NULL DEFAULT 'active',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `doctor_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `specialty` varchar(100) NOT NULL,
  `qualification` text NOT NULL,
  `experience_years` int(10) UNSIGNED DEFAULT 0,
  `consultation_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `clinic_name` varchar(255) DEFAULT NULL,
  `clinic_address` text DEFAULT NULL,
  `clinic_latitude` decimal(10,8) DEFAULT NULL,
  `clinic_longitude` decimal(11,8) DEFAULT NULL,
  `profile_photo` varchar(500) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `languages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `registration_number` varchar(100) DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `average_rating` decimal(3,2) NOT NULL DEFAULT 5.00,
  `total_reviews` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_appointments` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_doctor_user_id` (`user_id`),
  UNIQUE KEY `uniq_doctor_slug` (`slug`),
  KEY `idx_doctor_specialty` (`specialty`),
  KEY `idx_doctor_verified` (`is_verified`),
  KEY `idx_doctor_featured` (`is_featured`),
  KEY `idx_doctor_created_at` (`created_at`),
  CONSTRAINT `doctor_profiles_ibfk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `patient_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other','prefer_not_to_say') DEFAULT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `medical_history_summary` text DEFAULT NULL,
  `allergies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `chronic_conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `profile_photo` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_patient_user_id` (`user_id`),
  KEY `idx_patient_created_at` (`created_at`),
  CONSTRAINT `patient_profiles_ibfk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rate_limits` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) NOT NULL,
  `type` enum('otp_request','login_attempt','api_call') NOT NULL,
  `count` int(10) UNSIGNED DEFAULT 1,
  `reset_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_identifier_type` (`identifier`,`type`),
  KEY `idx_reset_at` (`reset_at`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_settings` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_group` varchar(50) DEFAULT 'general',
  `is_encrypted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_user_id` (`user_id`),
  KEY `idx_activity_entity` (`entity_type`,`entity_id`),
  KEY `idx_activity_created_at` (`created_at`),
  CONSTRAINT `activity_logs_ibfk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('appointment','payment','prescription','system','reminder') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notification_user_id` (`user_id`),
  KEY `idx_notification_type` (`type`),
  KEY `idx_notification_is_read` (`is_read`),
  KEY `idx_notification_created_at` (`created_at`),
  CONSTRAINT `notifications_ibfk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `doctor_schedules` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `weekday` tinyint(3) UNSIGNED NOT NULL COMMENT '0=Sunday, 6=Saturday',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_duration` int(10) UNSIGNED DEFAULT 15 COMMENT 'Duration in minutes',
  `max_patients` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_doctor_weekday` (`doctor_id`,`weekday`),
  KEY `idx_weekday` (`weekday`),
  KEY `idx_available` (`is_available`),
  CONSTRAINT `doctor_schedules_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `doctor_timeoffs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `off_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `is_full_day` tinyint(1) DEFAULT 1,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `idx_off_date` (`off_date`),
  CONSTRAINT `doctor_timeoffs_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appointments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_number` varchar(50) NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `schedule_id` bigint(20) UNSIGNED NOT NULL,
  `appointment_date` date NOT NULL,
  `token_number` int(10) UNSIGNED NOT NULL,
  `estimated_time` time DEFAULT NULL,
  `status` enum('pending','confirmed','in_progress','completed','cancelled','no_show') DEFAULT 'pending',
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_by` enum('patient','doctor','system') DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `appointment_number` (`appointment_number`),
  UNIQUE KEY `unique_doctor_date_token` (`doctor_id`,`appointment_date`,`token_number`),
  KEY `schedule_id` (`schedule_id`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_doctor_date` (`doctor_id`,`appointment_date`),
  KEY `idx_status` (`status`),
  KEY `idx_appointment_date` (`appointment_date`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`schedule_id`) REFERENCES `doctor_schedules` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appointment_status_history` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint(20) UNSIGNED NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `changed_by_type` enum('patient','doctor','admin','system') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_appointment` (`appointment_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_changed_by` (`changed_by`),
  CONSTRAINT `appointment_status_history_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointment_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `doctor_reviews` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `appointment_id` bigint(20) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `review_text` text DEFAULT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_review_appointment` (`appointment_id`),
  KEY `idx_review_doctor` (`doctor_id`),
  KEY `idx_review_patient` (`patient_id`),
  KEY `idx_review_visible` (`is_visible`),
  KEY `idx_review_created_at` (`created_at`),
  CONSTRAINT `doctor_reviews_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doctor_reviews_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doctor_reviews_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(50) NOT NULL,
  `appointment_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'BDT',
  `status` enum('pending','success','failed','refunded','partially_refunded') DEFAULT 'pending',
  `payment_method` enum('cash','card','mobile_banking','online') DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `refund_amount` decimal(10,2) DEFAULT NULL,
  `refund_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_number` (`payment_number`),
  KEY `idx_payment_appointment` (`appointment_id`),
  KEY `idx_payment_patient` (`patient_id`),
  KEY `idx_payment_doctor` (`doctor_id`),
  KEY `idx_payment_status` (`status`),
  KEY `idx_payment_paid_at` (`paid_at`),
  KEY `idx_payment_created_at` (`created_at`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `prescriptions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `prescription_number` varchar(50) NOT NULL,
  `appointment_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `symptoms` text NOT NULL,
  `diagnosis` text NOT NULL,
  `diagnosis_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'ICD-10 codes',
  `medicine_list` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `dosage_instructions` text DEFAULT NULL,
  `advice` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `follow_up_notes` text DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `prescription_number` (`prescription_number`),
  UNIQUE KEY `uniq_prescription_appointment` (`appointment_id`),
  KEY `idx_prescription_patient` (`patient_id`),
  KEY `idx_prescription_doctor` (`doctor_id`),
  KEY `idx_prescription_follow_up_date` (`follow_up_date`),
  KEY `idx_prescription_is_deleted` (`is_deleted`),
  KEY `idx_prescription_created_at` (`created_at`),
  KEY `idx_prescription_deleted_by` (`deleted_by`),
  CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescriptions_ibfk_4` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `prescription_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `prescription_id` bigint(20) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_prescription` (`prescription_id`),
  CONSTRAINT `prescription_attachments_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `otp_requests` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `attempts` tinyint(3) UNSIGNED DEFAULT 0,
  `max_attempts` tinyint(3) UNSIGNED DEFAULT 3,
  `expires_at` timestamp NOT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_email_created` (`email`,`created_at`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_otp_hash` (`otp_hash`),
  CONSTRAINT `otp_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appointment_chat_messages` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint(20) UNSIGNED NOT NULL,
  `sender_user_id` bigint(20) UNSIGNED NOT NULL,
  `sender_role` enum('patient','doctor','admin') NOT NULL,
  `message_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chat_appointment_id` (`appointment_id`),
  KEY `idx_chat_sender_user_id` (`sender_user_id`),
  KEY `idx_chat_appointment_message_id` (`appointment_id`,`id`),
  CONSTRAINT `appointment_chat_messages_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointment_chat_messages_ibfk_2` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `email`, `password_hash`, `role`, `status`, `email_verified_at`, `last_login_at`, `last_login_ip`, `created_at`, `updated_at`) VALUES
(1, 'mostafizurrahmanantu@gmail.com', NULL, 'doctor', 'active', '2026-04-03 08:54:20', '2026-04-03 09:43:03', '103.139.144.204', '2026-04-02 19:54:19', '2026-04-02 20:43:04'),
(2, 'juniormostafiz@gmail.com', NULL, 'patient', 'active', '2026-04-03 09:08:44', '2026-04-03 09:08:44', '103.139.144.204', '2026-04-02 20:08:45', '2026-04-02 20:08:45'),
(3, 'qa_smoke_live_1775162512@example.com', NULL, 'patient', 'active', '2026-04-03 09:42:34', '2026-04-03 09:42:34', '103.139.144.204', '2026-04-02 20:42:34', '2026-04-02 20:42:34');

INSERT INTO `doctor_profiles` (`id`, `user_id`, `full_name`, `slug`, `specialty`, `qualification`, `experience_years`, `consultation_fee`, `clinic_name`, `clinic_address`, `clinic_latitude`, `clinic_longitude`, `profile_photo`, `bio`, `languages`, `registration_number`, `is_verified`, `is_featured`, `average_rating`, `total_reviews`, `total_appointments`, `created_at`, `updated_at`) VALUES
(1, 1, 'Mostafizur Rahman Antu', 'mostafizur-rahman-antu', 'Cardiologist', 'MBBS', 5, '500.00', 'Pabna Mental Hospital', 'Pabna', NULL, NULL, 'uploads/profile-photos/doctors/doctors-1-c7d5a70699bffabd.jpg', 'Laziness is the mother of invention', '["English"]', '12345', 1, 0, '5.0', 0, 0, '2026-04-02 20:05:14', '2026-04-02 21:45:15');

INSERT INTO `patient_profiles` (`id`, `user_id`, `full_name`, `date_of_birth`, `gender`, `blood_group`, `address`, `emergency_contact_name`, `emergency_contact_phone`, `medical_history_summary`, `allergies`, `chronic_conditions`, `profile_photo`, `created_at`, `updated_at`) VALUES
(1, 2, 'MD. MOSTAFIZUR RAHMAN ANTU', '2002-11-17', 'male', 'O+', 'Natore', NULL, NULL, NULL, NULL, NULL, 'uploads/profile-photos/patients/patients-2-90e378e23f170598.jpg', '2026-04-02 20:09:14', '2026-04-02 21:26:38'),
(2, 3, 'QA Smoke Live', '1998-01-01', 'male', 'O+', 'Dhaka', NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-02 20:43:04', '2026-04-02 20:43:04');

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_group`, `is_encrypted`, `created_at`, `updated_at`) VALUES
(1, 'otp_expiry_minutes', '5', 'auth', 0, '2026-04-02 19:45:58', '2026-04-02 19:45:58'),
(2, 'otp_max_attempts', '3', 'auth', 0, '2026-04-02 19:45:58', '2026-04-02 19:45:58'),
(3, 'otp_rate_limit_per_hour', '5', 'auth', 0, '2026-04-02 19:45:58', '2026-04-02 19:45:58'),
(4, 'session_lifetime_hours', '24', 'auth', 0, '2026-04-02 19:45:58', '2026-04-02 19:45:58'),
(5, 'max_appointments_per_day', '3', 'appointments', 0, '2026-04-02 19:45:58', '2026-04-02 19:45:58'),
(6, 'cancellation_window_hours', '2', 'appointments', 0, '2026-04-02 19:45:58', '2026-04-02 19:45:58'),
(7, 'currency_code', 'BDT', 'payment', 0, '2026-04-02 19:45:58', '2026-04-02 19:45:58'),
(8, 'tax_percentage', '0', 'payment', 0, '2026-04-02 19:45:58', '2026-04-02 19:45:58'),
(9, 'platform_fee_percentage', '0', 'payment', 0, '2026-04-02 19:45:58', '2026-04-02 19:45:58'),
(10, 'maintenance_mode', 'false', 'system', 0, '2026-04-02 19:45:58', '2026-04-02 19:45:58');

INSERT INTO `doctor_schedules` (`id`, `doctor_id`, `weekday`, `start_time`, `end_time`, `slot_duration`, `max_patients`, `is_available`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '17:00:00', '21:00:00', 15, 10, 1, '2026-04-02 20:14:46', '2026-04-02 20:14:46'),
(2, 1, 2, '17:00:00', '21:00:00', 15, 10, 1, '2026-04-02 20:14:46', '2026-04-02 20:14:46'),
(3, 1, 3, '17:00:00', '21:00:00', 15, 10, 1, '2026-04-02 20:14:46', '2026-04-02 20:14:46'),
(4, 1, 4, '17:00:00', '21:00:00', 15, 10, 1, '2026-04-02 20:14:46', '2026-04-02 20:14:46'),
(5, 1, 5, '17:00:00', '21:00:00', 15, 10, 1, '2026-04-02 20:14:46', '2026-04-02 20:14:46');

INSERT INTO `appointments` (`id`, `appointment_number`, `patient_id`, `doctor_id`, `schedule_id`, `appointment_date`, `token_number`, `estimated_time`, `status`, `cancellation_reason`, `cancelled_by`, `cancelled_at`, `notes`, `symptoms`, `created_at`, `updated_at`) VALUES
(1, 'APT-20260403-A54A7F', 1, 1, 5, '2026-04-03', 1, '17:00:00', 'completed', NULL, NULL, NULL, 'Joy bangla', 'I have aids', '2026-04-02 20:23:06', '2026-04-02 21:30:48'),
(2, 'APT-20260403-8AF974', 2, 1, 2, '2026-04-07', 1, '17:00:00', 'completed', NULL, NULL, NULL, 'QA smoke booking', 'Mild headache', '2026-04-02 20:43:29', '2026-04-02 20:44:27');

INSERT INTO `payments` (`id`, `payment_number`, `appointment_id`, `patient_id`, `doctor_id`, `amount`, `currency`, `status`, `payment_method`, `transaction_id`, `gateway_response`, `paid_at`, `refunded_at`, `refund_amount`, `refund_reason`, `created_at`, `updated_at`) VALUES
(1, 'PAY-20260403-A5DE82', 1, 1, 1, '500.00', 'BDT', 'success', 'online', 'MSB-ONLINE-20260403023435-C54C69', '{"recorded_in_app":true,"payment_method":"online","completed_at":"2026-04-03T02:34:35+06:00"}', '2026-04-03 09:34:35', NULL, NULL, NULL, '2026-04-02 20:23:06', '2026-04-02 20:34:35'),
(2, 'PAY-20260403-575C85', 2, 2, 1, '500.00', 'BDT', 'success', 'online', 'MSB-ONLINE-20260403024357-247DC3', '{"recorded_in_app":true,"payment_method":"online","completed_at":"2026-04-03T02:43:57+06:00"}', '2026-04-03 09:43:57', NULL, NULL, NULL, '2026-04-02 20:43:29', '2026-04-02 20:43:57');

INSERT INTO `prescriptions` (`id`, `prescription_number`, `appointment_id`, `patient_id`, `doctor_id`, `symptoms`, `diagnosis`, `diagnosis_codes`, `medicine_list`, `dosage_instructions`, `advice`, `follow_up_date`, `follow_up_notes`, `is_deleted`, `deleted_at`, `deleted_by`, `created_at`, `updated_at`) VALUES
(1, 'RX-20260403-2F274E', 1, 1, 1, 'Sympotms', 'Diagnosis', NULL, '[{"name":"Medicines"}]', 'Dosage Instructions', 'Advice', '2026-04-15', 'Hello', 0, NULL, NULL, '2026-04-02 20:24:51', '2026-04-02 20:24:51'),
(2, 'RX-20260403-B2AC22', 2, 2, 1, 'Mild headache for 2 days', 'Tension headache', NULL, '["Paracetamol 500mg - after meals twice daily for 3 days","Hydration and rest"]', 'Take medicine after food.', 'Drink water and sleep on time.', '2026-04-10', 'Review if symptoms continue.', 0, NULL, NULL, '2026-04-02 20:45:02', '2026-04-02 20:45:02');

INSERT INTO `otp_requests` (`id`, `user_id`, `email`, `otp_hash`, `attempts`, `max_attempts`, `expires_at`, `verified_at`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, NULL, 'mostafizurrahmanantu@gmail.com', '$2y$10$LE3wGMeVYpRcxiw5cLZEbeHyjNQpHtPdLOwa7bGPxF.UiIU3zYu2e', 1, 3, '2026-04-03 08:58:49', '2026-04-03 08:54:20', '103.139.144.204', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 19:53:48'),
(2, 1, 'mostafizurrahmanantu@gmail.com', '$2y$10$fMpudhs56cFdxWObNG7Hl.L2JQ8w8HbqPXEthEfG/CRWQdHt.Re1G', 1, 3, '2026-04-03 09:09:34', '2026-04-03 09:04:51', '103.139.144.204', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 20:04:34'),
(3, NULL, 'juniormostafiz@gmail.com', '$2y$10$v6tkaEv29AAwHc/PayxObeHeDJ9pSYG0XRI1A7dQZT9nMlhTeLiGu', 1, 3, '2026-04-03 09:13:30', '2026-04-03 09:08:44', '103.139.144.204', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 20:08:31'),
(4, NULL, 'qa_smoke_live_1775162512@example.com', '$2y$10$5KgQWAWGV3CyW01V3quvjOhZesvD8fdz8.wfYmb97hESLiIoYKHiC', 1, 3, '2026-04-03 09:46:57', '2026-04-03 09:42:34', '103.139.144.204', 'Mozilla/5.0 (Windows NT 10.0; Microsoft Windows 10.0.26200; en-US) PowerShell/7.5.5', '2026-04-02 20:41:58'),
(5, 1, 'mostafizurrahmanantu@gmail.com', '$2y$10$o6uWfcBCagg064OCIkAoSuKsSjCCPao18NJJS8ZxwsrjmDhd1nDl2', 1, 3, '2026-04-03 09:47:34', '2026-04-03 09:43:03', '103.139.144.204', 'Mozilla/5.0 (Windows NT 10.0; Microsoft Windows 10.0.26200; en-US) PowerShell/7.5.5', '2026-04-02 20:42:34');

INSERT INTO `rate_limits` (`id`, `identifier`, `type`, `count`, `reset_at`, `created_at`) VALUES
(1, 'otp:mostafizurrahmanantu@gmail.com', 'otp_request', 3, '2026-04-03 09:53:49', '2026-04-02 19:53:48'),
(3, 'otp:qa_smoke_live_1775162512@example.com', 'otp_request', 1, '2026-04-03 10:41:57', '2026-04-02 20:41:58');

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
