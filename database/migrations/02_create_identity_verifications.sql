-- Migration 02: Identity Verification System & Audit Schema
-- Module 3, 4, 5, 7: Manual Identity Verification & Admin Workflow

-- 1. Extend Users table with optional profile demographics (DOB, Gender)
ALTER TABLE `users` 
    ADD COLUMN IF NOT EXISTS `dob` DATE NULL DEFAULT NULL AFTER `preferred_language`,
    ADD COLUMN IF NOT EXISTS `gender` ENUM('male', 'female', 'other') NULL DEFAULT NULL AFTER `dob`;

-- 2. Extend Worker and Customer Profiles with Pincode and Verification fields
ALTER TABLE `worker_profiles` 
    ADD COLUMN IF NOT EXISTS `pincode` VARCHAR(10) NULL DEFAULT NULL AFTER `address`;

ALTER TABLE `customer_profiles` 
    ADD COLUMN IF NOT EXISTS `pincode` VARCHAR(10) NULL DEFAULT NULL AFTER `address`,
    ADD COLUMN IF NOT EXISTS `is_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `address`,
    ADD COLUMN IF NOT EXISTS `verification_notes` TEXT NULL DEFAULT NULL AFTER `is_verified`;

-- 3. Table: identity_verifications
-- Tracks current verification request status and administrative decision
CREATE TABLE IF NOT EXISTS `identity_verifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `status` ENUM('pending', 'under_review', 'verified', 'rejected', 'resubmission_required') NOT NULL DEFAULT 'pending',
    `submitted_at` DATETIME NULL DEFAULT NULL,
    `reviewed_by` INT NULL DEFAULT NULL,
    `reviewed_at` DATETIME NULL DEFAULT NULL,
    `admin_notes` TEXT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_iv_user_id` (`user_id`),
    CONSTRAINT `fk_iv_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_iv_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    INDEX `idx_iv_status` (`status`),
    INDEX `idx_iv_reviewed_by` (`reviewed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table: verification_documents
-- Stores uploaded government-issued identity documents
CREATE TABLE IF NOT EXISTS `verification_documents` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `verification_id` BIGINT UNSIGNED NOT NULL,
    `user_id` INT NOT NULL,
    `document_type` ENUM('voter_id', 'driving_licence', 'passport', 'other') NOT NULL,
    `document_reference_masked` VARCHAR(100) NULL DEFAULT NULL,
    `front_image_path` VARCHAR(255) NOT NULL,
    `back_image_path` VARCHAR(255) NULL DEFAULT NULL,
    `file_mime` VARCHAR(64) NOT NULL DEFAULT 'image/jpeg',
    `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
    `original_filename` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_vd_verification_id` FOREIGN KEY (`verification_id`) REFERENCES `identity_verifications` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vd_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_vd_verification_id` (`verification_id`),
    INDEX `idx_vd_user_id` (`user_id`),
    INDEX `idx_vd_document_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table: verification_history
-- Immutable audit log of all verification events and status transitions
CREATE TABLE IF NOT EXISTS `verification_history` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `verification_id` BIGINT UNSIGNED NOT NULL,
    `user_id` INT NOT NULL,
    `action` ENUM('submitted', 'under_review', 'approved', 'rejected', 'resubmission_requested', 'resubmitted') NOT NULL,
    `actor_id` INT NOT NULL,
    `previous_status` VARCHAR(32) NULL DEFAULT NULL,
    `new_status` VARCHAR(32) NOT NULL,
    `remarks` TEXT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_vh_verification_id` FOREIGN KEY (`verification_id`) REFERENCES `identity_verifications` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vh_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vh_actor_id` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_vh_verification_id` (`verification_id`),
    INDEX `idx_vh_user_id` (`user_id`),
    INDEX `idx_vh_actor_id` (`actor_id`),
    INDEX `idx_vh_action` (`action`),
    INDEX `idx_vh_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

