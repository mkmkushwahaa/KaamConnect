-- Seed Sample Identity Verifications for existing users
-- Ensures Admin Dashboard and User Dashboards have realistic test data across all lifecycle states

-- 1. Ramesh Kumar (Worker, user_id=2): Status = verified
INSERT INTO `identity_verifications` (`user_id`, `status`, `submitted_at`, `reviewed_by`, `reviewed_at`, `admin_notes`, `created_at`) 
VALUES (2, 'verified', DATE_SUB(NOW(), INTERVAL 5 DAY), 1, DATE_SUB(NOW(), INTERVAL 4 DAY), 'Aadhaar & Voter ID verified. Background check clear.', DATE_SUB(NOW(), INTERVAL 5 DAY))
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- 2. Mohammad Aslam (Worker, user_id=3): Status = pending
INSERT INTO `identity_verifications` (`user_id`, `status`, `submitted_at`, `reviewed_by`, `reviewed_at`, `admin_notes`, `created_at`) 
VALUES (3, 'pending', DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY))
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- 3. Sunita Sharma (Customer, user_id=4): Status = verified
INSERT INTO `identity_verifications` (`user_id`, `status`, `submitted_at`, `reviewed_by`, `reviewed_at`, `admin_notes`, `created_at`) 
VALUES (4, 'verified', DATE_SUB(NOW(), INTERVAL 7 DAY), 1, DATE_SUB(NOW(), INTERVAL 6 DAY), 'Identity & residential address confirmed.', DATE_SUB(NOW(), INTERVAL 7 DAY))
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- 4. Vikram Mehta (Customer, user_id=5): Status = under_review
INSERT INTO `identity_verifications` (`user_id`, `status`, `submitted_at`, `reviewed_by`, `reviewed_at`, `admin_notes`, `created_at`) 
VALUES (5, 'under_review', DATE_SUB(NOW(), INTERVAL 1 DAY), 1, DATE_SUB(NOW(), INTERVAL 2 HOUR), 'Review initiated by Admin. Verifying address document.', DATE_SUB(NOW(), INTERVAL 1 DAY))
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- 5. Mukesh Kumar (Worker, user_id=6): Status = resubmission_required
INSERT INTO `identity_verifications` (`user_id`, `status`, `submitted_at`, `reviewed_by`, `reviewed_at`, `admin_notes`, `created_at`) 
VALUES (6, 'resubmission_required', DATE_SUB(NOW(), INTERVAL 3 DAY), 1, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Front image of Voter ID is blurry. Please upload a clear scanned copy.', DATE_SUB(NOW(), INTERVAL 3 DAY))
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- Update customer_profiles is_verified to sync with verification status
UPDATE `customer_profiles` SET `is_verified` = 1 WHERE `user_id` = 4;
UPDATE `worker_profiles` SET `is_verified` = 1 WHERE `user_id` = 2;

-- Populate sample verification documents
INSERT INTO `verification_documents` (`verification_id`, `user_id`, `document_type`, `document_reference_masked`, `front_image_path`, `back_image_path`, `file_mime`, `file_size`, `original_filename`, `created_at`)
SELECT iv.id, iv.user_id, 'voter_id', 'VOT-XXXX-8921', 'assets/uploads/documents/sample_voter_front.jpg', 'assets/uploads/documents/sample_voter_back.jpg', 'image/jpeg', 245100, 'voter_card_scan.jpg', iv.submitted_at
FROM `identity_verifications` iv WHERE iv.user_id = 2;

INSERT INTO `verification_documents` (`verification_id`, `user_id`, `document_type`, `document_reference_masked`, `front_image_path`, `back_image_path`, `file_mime`, `file_size`, `original_filename`, `created_at`)
SELECT iv.id, iv.user_id, 'driving_licence', 'DL-XXXX-5524', 'assets/uploads/documents/sample_dl_front.jpg', 'assets/uploads/documents/sample_dl_back.jpg', 'image/jpeg', 312000, 'driving_licence.jpg', iv.submitted_at
FROM `identity_verifications` iv WHERE iv.user_id = 3;

INSERT INTO `verification_documents` (`verification_id`, `user_id`, `document_type`, `document_reference_masked`, `front_image_path`, `back_image_path`, `file_mime`, `file_size`, `original_filename`, `created_at`)
SELECT iv.id, iv.user_id, 'passport', 'PASS-XXXX-1092', 'assets/uploads/documents/sample_passport.jpg', NULL, 'image/jpeg', 420000, 'passport_first_page.jpg', iv.submitted_at
FROM `identity_verifications` iv WHERE iv.user_id = 4;

INSERT INTO `verification_documents` (`verification_id`, `user_id`, `document_type`, `document_reference_masked`, `front_image_path`, `back_image_path`, `file_mime`, `file_size`, `original_filename`, `created_at`)
SELECT iv.id, iv.user_id, 'voter_id', 'VOT-XXXX-3341', 'assets/uploads/documents/sample_voter_vikram.jpg', NULL, 'image/jpeg', 289000, 'voter_id_doc.jpg', iv.submitted_at
FROM `identity_verifications` iv WHERE iv.user_id = 5;

INSERT INTO `verification_documents` (`verification_id`, `user_id`, `document_type`, `document_reference_masked`, `front_image_path`, `back_image_path`, `file_mime`, `file_size`, `original_filename`, `created_at`)
SELECT iv.id, iv.user_id, 'voter_id', 'VOT-XXXX-7712', 'assets/uploads/documents/sample_blurry.jpg', NULL, 'image/jpeg', 154000, 'voter_card.jpg', iv.submitted_at
FROM `identity_verifications` iv WHERE iv.user_id = 6;

-- Populate sample verification audit history
INSERT INTO `verification_history` (`verification_id`, `user_id`, `action`, `actor_id`, `previous_status`, `new_status`, `remarks`, `created_at`)
SELECT iv.id, iv.user_id, 'submitted', iv.user_id, NULL, 'pending', 'Initial identity documents submitted by user.', iv.submitted_at
FROM `identity_verifications` iv;

INSERT INTO `verification_history` (`verification_id`, `user_id`, `action`, `actor_id`, `previous_status`, `new_status`, `remarks`, `created_at`)
SELECT iv.id, iv.user_id, 'approved', 1, 'pending', 'verified', iv.admin_notes, iv.reviewed_at
FROM `identity_verifications` iv WHERE iv.status = 'verified';

INSERT INTO `verification_history` (`verification_id`, `user_id`, `action`, `actor_id`, `previous_status`, `new_status`, `remarks`, `created_at`)
SELECT iv.id, iv.user_id, 'under_review', 1, 'pending', 'under_review', iv.admin_notes, iv.reviewed_at
FROM `identity_verifications` iv WHERE iv.status = 'under_review';

INSERT INTO `verification_history` (`verification_id`, `user_id`, `action`, `actor_id`, `previous_status`, `new_status`, `remarks`, `created_at`)
SELECT iv.id, iv.user_id, 'resubmission_requested', 1, 'pending', 'resubmission_required', iv.admin_notes, iv.reviewed_at
FROM `identity_verifications` iv WHERE iv.status = 'resubmission_required';

