-- ========================================================
-- KaamConnect Database Schema
-- Local Worker Hiring and Employment Marketplace
-- Database: kaamconnect_db
-- Compatibility: MySQL 5.7+ / MySQL 8.0+ / MariaDB 10.3+
-- Engine: InnoDB | Character Set: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ========================================================

CREATE DATABASE IF NOT EXISTS `kaamconnect_db` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `kaamconnect_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. Table: users
-- Core table storing credentials and roles for workers, customers, and admins
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NULL UNIQUE,
  `phone` VARCHAR(20) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('worker', 'customer', 'admin') NOT NULL,
  `preferred_language` ENUM('en', 'hi') DEFAULT 'en',
  `status` ENUM('active', 'suspended', 'pending') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_phone` (`phone`),
  INDEX `idx_users_email` (`email`),
  INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Table: categories
-- Standardized worker service categories with bilingual labels and FontAwesome icons
-- --------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name_en` VARCHAR(100) NOT NULL UNIQUE,
  `name_hi` VARCHAR(100) NOT NULL,
  `icon` VARCHAR(50) NOT NULL DEFAULT 'fa-tools',
  `description` TEXT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Table: worker_profiles
-- Professional profile data for service providers
-- --------------------------------------------------------
DROP TABLE IF EXISTS `worker_profiles`;
CREATE TABLE `worker_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `profile_photo` VARCHAR(255) DEFAULT 'assets/images/default-avatar.svg',
  `primary_category_id` INT NULL,
  `experience_years` INT DEFAULT 0,
  `daily_wage` DECIMAL(10,2) DEFAULT 0.00,
  `hourly_wage` DECIMAL(10,2) DEFAULT 0.00,
  `wage_type` ENUM('daily', 'hourly', 'negotiable') DEFAULT 'daily',
  `bio` TEXT NULL,
  `city` VARCHAR(100) NOT NULL,
  `locality` VARCHAR(150) NOT NULL,
  `address` TEXT NULL,
  `is_available` TINYINT(1) DEFAULT 1,
  `is_verified` TINYINT(1) DEFAULT 0,
  `verification_notes` TEXT NULL,
  `rating_avg` DECIMAL(3,2) DEFAULT 0.00,
  `rating_count` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_worker_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_worker_category` FOREIGN KEY (`primary_category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  INDEX `idx_worker_city_locality` (`city`, `locality`),
  INDEX `idx_worker_avail` (`is_available`),
  INDEX `idx_worker_verified` (`is_verified`),
  INDEX `idx_worker_rating` (`rating_avg`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Table: customer_profiles
-- Customer profile details and location info
-- --------------------------------------------------------
DROP TABLE IF EXISTS `customer_profiles`;
CREATE TABLE `customer_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `profile_photo` VARCHAR(255) DEFAULT 'assets/images/default-avatar.svg',
  `city` VARCHAR(100) NOT NULL,
  `locality` VARCHAR(150) NOT NULL,
  `address` TEXT NULL,
  `alternative_phone` VARCHAR(20) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_customer_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_customer_city_locality` (`city`, `locality`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Table: worker_skills
-- Multi-skill tagging for workers
-- --------------------------------------------------------
DROP TABLE IF EXISTS `worker_skills`;
CREATE TABLE `worker_skills` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `worker_id` INT NOT NULL,
  `skill_name` VARCHAR(100) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_skills_worker` FOREIGN KEY (`worker_id`) REFERENCES `worker_profiles` (`id`) ON DELETE CASCADE,
  INDEX `idx_skills_name` (`skill_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Table: jobs
-- Job postings created by customers
-- --------------------------------------------------------
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `required_skills` VARCHAR(255) NULL,
  `city` VARCHAR(100) NOT NULL,
  `locality` VARCHAR(150) NOT NULL,
  `budget` DECIMAL(10,2) NOT NULL,
  `wage_type` ENUM('fixed', 'daily', 'hourly') DEFAULT 'fixed',
  `preferred_date` DATE NOT NULL,
  `status` ENUM('open', 'assigned', 'completed', 'cancelled') DEFAULT 'open',
  `hired_worker_id` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_job_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_job_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_job_hired_worker` FOREIGN KEY (`hired_worker_id`) REFERENCES `worker_profiles` (`id`) ON DELETE SET NULL,
  INDEX `idx_jobs_status` (`status`),
  INDEX `idx_jobs_city_locality` (`city`, `locality`),
  INDEX `idx_jobs_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. Table: job_applications
-- Applications submitted by workers for specific jobs
-- --------------------------------------------------------
DROP TABLE IF EXISTS `job_applications`;
CREATE TABLE `job_applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `job_id` INT NOT NULL,
  `worker_id` INT NOT NULL,
  `proposed_wage` DECIMAL(10,2) NOT NULL,
  `cover_note` TEXT NULL,
  `status` ENUM('pending', 'accepted', 'rejected', 'withdrawn') DEFAULT 'pending',
  `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_app_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_app_worker` FOREIGN KEY (`worker_id`) REFERENCES `worker_profiles` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_job_worker_app` (`job_id`, `worker_id`),
  INDEX `idx_app_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. Table: conversations
-- Threaded message channels between a customer and worker
-- --------------------------------------------------------
DROP TABLE IF EXISTS `conversations`;
CREATE TABLE `conversations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `job_id` INT NULL,
  `customer_id` INT NOT NULL,
  `worker_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_conv_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_conv_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conv_worker` FOREIGN KEY (`worker_id`) REFERENCES `worker_profiles` (`id`) ON DELETE CASCADE,
  INDEX `idx_conv_participants` (`customer_id`, `worker_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 9. Table: messages
-- Private chat messages within a conversation
-- --------------------------------------------------------
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` INT NOT NULL,
  `sender_id` INT NOT NULL,
  `receiver_id` INT NOT NULL,
  `message_text` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_msg_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_msg_conv` (`conversation_id`),
  INDEX `idx_msg_unread` (`receiver_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 10. Table: reviews
-- Post-job ratings (1-5 stars) and feedback given by customer
-- --------------------------------------------------------
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `job_id` INT NOT NULL UNIQUE,
  `customer_id` INT NOT NULL,
  `worker_id` INT NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `review_text` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_rev_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_worker` FOREIGN KEY (`worker_id`) REFERENCES `worker_profiles` (`id`) ON DELETE CASCADE,
  INDEX `idx_rev_worker` (`worker_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 11. Table: notifications
-- In-app notifications for lifecycle events
-- --------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `link` VARCHAR(255) NULL,
  `type` VARCHAR(50) DEFAULT 'system',
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_notif_user_unread` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 12. Table: worker_portfolio
-- Portfolio photographs showcasing previous client work
-- --------------------------------------------------------
DROP TABLE IF EXISTS `worker_portfolio`;
CREATE TABLE `worker_portfolio` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `worker_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_port_worker` FOREIGN KEY (`worker_id`) REFERENCES `worker_profiles` (`id`) ON DELETE CASCADE,
  INDEX `idx_port_worker` (`worker_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 13. Table: reports
-- User complaints and issue flagging for moderation
-- --------------------------------------------------------
DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reporter_id` INT NOT NULL,
  `reported_user_id` INT NULL,
  `job_id` INT NULL,
  `reason` VARCHAR(150) NOT NULL,
  `details` TEXT NOT NULL,
  `status` ENUM('pending', 'investigating', 'resolved', 'dismissed') DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_rep_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rep_reported` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rep_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE SET NULL,
  INDEX `idx_rep_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 14. Table: login_attempts
-- Brute-force protection and IP rate limiting tracker
-- --------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `identifier` VARCHAR(150) NOT NULL,
  `is_success` TINYINT(1) DEFAULT 0,
  `attempted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_login_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ========================================================
-- SEED DATA
-- ========================================================

-- Insert Required Worker Categories with FontAwesome 6 icons and English/Hindi titles
INSERT INTO `categories` (`id`, `name_en`, `name_hi`, `icon`, `description`, `is_active`) VALUES
(1, 'Painter', 'पेंटर (रंगाई मिस्त्री)', 'fa-paint-roller', 'Interior & exterior wall painting, waterproofing, texture design, and wood polish.', 1),
(2, 'Plumber', 'प्लंबर (नलसाज)', 'fa-wrench', 'Pipe fittings, water leakage repair, sanitary installations, drainage, and geyser setup.', 1),
(3, 'Electrician', 'इलेक्ट्रीशियन (बिजली मिस्त्री)', 'fa-bolt', 'House wiring, circuit breaker fixing, appliance installation, fans, lights, and switchboards.', 1),
(4, 'Carpenter', 'बढ़ई (कारपेंटर)', 'fa-hammer', 'Custom furniture crafting, door & window repairs, modular kitchen woodwork, and locks.', 1),
(5, 'Construction Labourer', 'निर्माण श्रमिक (मजदूर)', 'fa-hard-hat', 'Demolition, site clearing, brick carriage, concrete mixing, and general construction labor.', 1),
(6, 'Cleaner', 'सफाई कर्मचारी (क्लीनर)', 'fa-broom', 'Deep home cleaning, sofa & carpet shampooing, bathroom sanitization, and floor scrubbing.', 1),
(7, 'Welder', 'वेल्डर', 'fa-fire-burner', 'Iron gates, security grills, metal frame fabrication, steel welding, and repair works.', 1),
(8, 'Gardener', 'माली', 'fa-seedling', 'Lawn mowing, tree trimming, garden maintenance, plant potting, and pest treatment.', 1),
(9, 'Mason', 'राजमिस्त्री', 'fa-trowel-bricks', 'Bricklaying, plastering, tile and marble installation, flooring, and structural repair.', 1),
(10, 'AC Technician', 'एसी तकनीशियन', 'fa-snowflake', 'Air conditioner servicing, gas refilling, cooling diagnostics, and split/window unit setup.', 1),
(11, 'Mechanic', 'मैकेनिक', 'fa-screwdriver-wrench', 'Two-wheeler and four-wheeler repair, engine tuning, puncture, and roadside assistance.', 1),
(12, 'Other', 'अन्य सेवाएं', 'fa-handshake', 'Various specialized local helper services, loading/unloading, and household assistance.', 1);

-- Default Super Administrator
-- Password for all seed accounts: Admin@123 (for admin) and Password@123 (for worker & customer)
-- Stored standard bcrypt hash ($2y$10$wT4nZ33k.6Kx1sYvQyU2a.Qz0L.5Pj9eG5uUu1YvO3vL7uN4cEaIq)
INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `password_hash`, `role`, `preferred_language`, `status`, `created_at`) VALUES
(1, 'KaamConnect Admin', 'admin@kaamconnect.com', '9876543210', '$2y$10$wT4nZ33k.6Kx1sYvQyU2a.Qz0L.5Pj9eG5uUu1YvO3vL7uN4cEaIq', 'admin', 'en', 'active', NOW()),
(2, 'Ramesh Kumar (Worker)', 'ramesh.worker@kaamconnect.com', '9876500001', '$2y$10$wT4nZ33k.6Kx1sYvQyU2a.Qz0L.5Pj9eG5uUu1YvO3vL7uN4cEaIq', 'worker', 'hi', 'active', NOW()),
(3, 'Mohammad Aslam (Worker)', 'aslam.worker@kaamconnect.com', '9876500002', '$2y$10$wT4nZ33k.6Kx1sYvQyU2a.Qz0L.5Pj9eG5uUu1YvO3vL7uN4cEaIq', 'worker', 'en', 'active', NOW()),
(4, 'Sunita Sharma (Customer)', 'sunita.customer@kaamconnect.com', '9876500003', '$2y$10$wT4nZ33k.6Kx1sYvQyU2a.Qz0L.5Pj9eG5uUu1YvO3vL7uN4cEaIq', 'customer', 'en', 'active', NOW()),
(5, 'Vikram Mehta (Customer)', 'vikram.customer@kaamconnect.com', '9876500004', '$2y$10$wT4nZ33k.6Kx1sYvQyU2a.Qz0L.5Pj9eG5uUu1YvO3vL7uN4cEaIq', 'customer', 'hi', 'active', NOW());

-- Seed Worker Profiles
INSERT INTO `worker_profiles` (`id`, `user_id`, `profile_photo`, `primary_category_id`, `experience_years`, `daily_wage`, `hourly_wage`, `wage_type`, `bio`, `city`, `locality`, `address`, `is_available`, `is_verified`, `verification_notes`, `rating_avg`, `rating_count`) VALUES
(1, 2, 'assets/images/default-avatar.svg', 3, 7, 750.00, 150.00, 'daily', 'Experienced certified electrician with 7 years of expertise in commercial and residential electrical installations, inverter repair, and smart switchboards.', 'Noida', 'Sector 15', 'House No. 42, Gali No. 3, Sector 15', 1, 1, 'Aadhaar verified and practical skill confirmed', 4.80, 15),
(2, 3, 'assets/images/default-avatar.svg', 2, 5, 600.00, 120.00, 'daily', 'Master plumber specializing in bathroom fixture upgrades, high-pressure pipeline repair, underground drainage, and water motor installation.', 'Delhi', 'Lajpat Nagar', 'B-12, Central Market, Lajpat Nagar IV', 1, 1, 'Govt ITI certificate verified', 4.65, 8);

-- Seed Customer Profiles
INSERT INTO `customer_profiles` (`id`, `user_id`, `profile_photo`, `city`, `locality`, `address`, `alternative_phone`) VALUES
(1, 4, 'assets/images/default-avatar.svg', 'Noida', 'Sector 62', 'Tower 4, Flat 702, Stellar Park, Sector 62', '9811223344'),
(2, 5, 'assets/images/default-avatar.svg', 'Delhi', 'Lajpat Nagar', 'Block C, House 55, Lajpat Nagar III', '9822334455');

-- Seed Worker Skills
INSERT INTO `worker_skills` (`worker_id`, `skill_name`) VALUES
(1, 'House Wiring'),
(1, 'MCB Installation'),
(1, 'Inverter Repair'),
(1, 'Ceiling Fan Fixing'),
(2, 'Pipe Threading'),
(2, 'CPVC Fitting'),
(2, 'Sanitaryware'),
(2, 'Water Motor Overhaul');

-- Seed Sample Jobs
INSERT INTO `jobs` (`id`, `customer_id`, `category_id`, `title`, `description`, `required_skills`, `city`, `locality`, `budget`, `wage_type`, `preferred_date`, `status`, `hired_worker_id`, `created_at`) VALUES
(1, 1, 3, 'Full 3BHK Apartment Rewiring & Switchboard Upgrade', 'Need a professional electrician to check short-circuit issues, install modular switch plates across 3 bedrooms and living room, and test the inverter line.', 'House Wiring, Modular Switches', 'Noida', 'Sector 62', 2500.00, 'fixed', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'open', NULL, NOW()),
(2, 1, 2, 'Kitchen Sink Drain Replacement and Tap Leakage Repair', 'The main PVC drain pipe under the kitchen sink is leaking and two mixer taps in the bathroom require cartridge replacement.', 'PVC Fitting, Tap Repair', 'Noida', 'Sector 62', 650.00, 'fixed', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'assigned', 2, NOW()),
(3, 2, 1, 'Living Room & Balcony Wall Painting (Royale Shyne)', 'Living room double-height ceiling and exterior balcony wall require primer and 2 coats of Asian Paints Royale Shyne. Material is already purchased.', 'Interior Painting, Wall Putty', 'Delhi', 'Lajpat Nagar', 4500.00, 'fixed', DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'open', NULL, NOW());

-- Seed Job Applications
INSERT INTO `job_applications` (`id`, `job_id`, `worker_id`, `proposed_wage`, `cover_note`, `status`, `applied_at`) VALUES
(1, 1, 1, 2400.00, 'Hello Sunita ji, I have worked in Sector 62 residential towers frequently. I carry complete electrical diagnostic meters and will deliver clean, safe work.', 'pending', NOW()),
(2, 2, 2, 600.00, 'I can come tomorrow morning at 10 AM with genuine brass fittings and seals. Work will be completed within 2 hours.', 'accepted', NOW());

-- Seed Reviews
INSERT INTO `reviews` (`id`, `job_id`, `customer_id`, `worker_id`, `rating`, `review_text`, `created_at`) VALUES
(1, 2, 1, 2, 5, 'Mohammad Aslam did a fantastic job! Replaced the pipe cleanly without leaving any mess. Very polite and charges fair rates.', NOW());
