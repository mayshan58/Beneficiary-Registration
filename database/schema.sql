-- ========================================================
-- NGO Beneficiary Registration System Database Schema
-- Designed for Humanitarian Operations in South Sudan
-- ========================================================

CREATE DATABASE IF NOT EXISTS `ngo_beneficiary_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ngo_beneficiary_db`;

-- Drop tables in proper order if they already exist
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `duplicate_logs`;
DROP TABLE IF EXISTS `aid_distributions`;
DROP TABLE IF EXISTS `program_enrollments`;
DROP TABLE IF EXISTS `beneficiaries`;
DROP TABLE IF EXISTS `aid_programs`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `ngos`;

-- 1. NGOs Table
CREATE TABLE `ngos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `acronym` VARCHAR(30) NOT NULL,
  `registration_number` VARCHAR(60) NOT NULL UNIQUE,
  `contact_person` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(40) NOT NULL,
  `office_address` TEXT NOT NULL,
  `headquarters` VARCHAR(100) NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. System Users Table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ngo_id` INT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'registration_officer', 'distribution_officer') NOT NULL DEFAULT 'registration_officer',
  `phone` VARCHAR(40) NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `last_login` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_users_ngo` FOREIGN KEY (`ngo_id`) REFERENCES `ngos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Aid Programs Table
CREATE TABLE `aid_programs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ngo_id` INT NOT NULL,
  `program_code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL,
  `category` ENUM('Food Assistance', 'Cash & Voucher (CVA)', 'WASH', 'Health & Nutrition', 'Shelter & NFI', 'Livelihoods & Agriculture', 'Protection & Education') NOT NULL,
  `description` TEXT NULL,
  `target_beneficiaries` INT NOT NULL DEFAULT 0,
  `item_description` VARCHAR(255) NOT NULL,
  `allocation_quota` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit` VARCHAR(50) NOT NULL DEFAULT 'Ration / Kit',
  `target_state` VARCHAR(100) NOT NULL,
  `target_county` VARCHAR(100) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `status` ENUM('Planned', 'Active', 'Completed', 'Suspended') NOT NULL DEFAULT 'Active',
  `created_by` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_programs_ngo` FOREIGN KEY (`ngo_id`) REFERENCES `ngos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_programs_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Beneficiaries Table
CREATE TABLE `beneficiaries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `beneficiary_id` VARCHAR(50) NOT NULL UNIQUE,
  `full_name` VARCHAR(150) NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `date_of_birth` DATE NULL,
  `estimated_age` INT NULL,
  `phone` VARCHAR(40) NULL,
  `id_document_type` VARCHAR(100) NOT NULL DEFAULT 'Chief / Local Recommendation Letter',
  `id_document_number` VARCHAR(100) NULL,
  `state` VARCHAR(100) NOT NULL,
  `county` VARCHAR(100) NOT NULL,
  `payam_boma` VARCHAR(150) NOT NULL,
  `community_camp` VARCHAR(150) NULL,
  `household_size` INT NOT NULL DEFAULT 1,
  `children_under_5` INT NOT NULL DEFAULT 0,
  `elderly_members` INT NOT NULL DEFAULT 0,
  `disabled_members` INT NOT NULL DEFAULT 0,
  `pregnant_lactating` INT NOT NULL DEFAULT 0,
  `vulnerability_category` ENUM(
    'IDP (Internally Displaced Person)',
    'Returnee',
    'Host Community Vulnerable',
    'Female-Headed Household',
    'Child-Headed Household',
    'Elderly-Headed Household',
    'Person with Disability (PWD)',
    'Chronically Ill'
  ) NOT NULL DEFAULT 'Host Community Vulnerable',
  `registration_date` DATE NOT NULL,
  `verification_status` ENUM('Pending', 'Verified', 'Requires Correction', 'Rejected') NOT NULL DEFAULT 'Pending',
  `verified_by` INT NULL,
  `verified_at` DATETIME NULL,
  `verification_notes` TEXT NULL,
  `registered_by` INT NULL,
  `ngo_id` INT NULL,
  `is_duplicate_flag` TINYINT(1) NOT NULL DEFAULT 0,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_ben_name` (`full_name`),
  INDEX `idx_ben_location` (`state`, `county`),
  INDEX `idx_ben_status` (`verification_status`),
  CONSTRAINT `fk_ben_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ben_registered_by` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ben_ngo` FOREIGN KEY (`ngo_id`) REFERENCES `ngos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Program Enrollments Table
CREATE TABLE `program_enrollments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `program_id` INT NOT NULL,
  `beneficiary_id` INT NOT NULL,
  `enrollment_date` DATE NOT NULL,
  `status` ENUM('Enrolled', 'Completed', 'Discontinued') NOT NULL DEFAULT 'Enrolled',
  `enrolled_by` INT NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_program_ben` (`program_id`, `beneficiary_id`),
  CONSTRAINT `fk_enroll_program` FOREIGN KEY (`program_id`) REFERENCES `aid_programs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_enroll_beneficiary` FOREIGN KEY (`beneficiary_id`) REFERENCES `beneficiaries` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_enroll_user` FOREIGN KEY (`enrolled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Aid Distributions Table
CREATE TABLE `aid_distributions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `receipt_no` VARCHAR(50) NOT NULL UNIQUE,
  `program_id` INT NOT NULL,
  `beneficiary_id` INT NOT NULL,
  `distribution_date` DATE NOT NULL,
  `distribution_cycle` VARCHAR(50) NOT NULL DEFAULT 'Cycle 1',
  `item_distributed` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `unit` VARCHAR(50) NOT NULL,
  `distribution_point` VARCHAR(150) NOT NULL,
  `collection_type` ENUM('Direct by Beneficiary', 'Authorized Proxy') NOT NULL DEFAULT 'Direct by Beneficiary',
  `proxy_name` VARCHAR(150) NULL,
  `proxy_relationship` VARCHAR(100) NULL,
  `proxy_phone` VARCHAR(40) NULL,
  `distributed_by` INT NULL,
  `remarks` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_dist_date` (`distribution_date`),
  CONSTRAINT `fk_dist_program` FOREIGN KEY (`program_id`) REFERENCES `aid_programs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dist_beneficiary` FOREIGN KEY (`beneficiary_id`) REFERENCES `beneficiaries` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dist_officer` FOREIGN KEY (`distributed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Duplicate Logs Table
CREATE TABLE `duplicate_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `primary_beneficiary_id` INT NOT NULL,
  `matched_beneficiary_id` INT NOT NULL,
  `match_type` VARCHAR(100) NOT NULL,
  `confidence_score` INT NOT NULL,
  `details` TEXT NULL,
  `status` ENUM('Flagged', 'Reviewed - Distinct', 'Reviewed - Confirmed Duplicate', 'Resolved') NOT NULL DEFAULT 'Flagged',
  `reviewed_by` INT NULL,
  `review_notes` TEXT NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_dup_primary` FOREIGN KEY (`primary_beneficiary_id`) REFERENCES `beneficiaries` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dup_matched` FOREIGN KEY (`matched_beneficiary_id`) REFERENCES `beneficiaries` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dup_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Audit Logs Table
CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `record_id` VARCHAR(50) NULL,
  `description` TEXT NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_audit_module` (`module`),
  INDEX `idx_audit_created` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

