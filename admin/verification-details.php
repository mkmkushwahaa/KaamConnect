<?php
/**
 * KaamConnect - Admin User Verification Details & Review Terminal
 * Displays Personal, Address, Worker/Customer details, Documents, and 4 Workflow Actions
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pdo = getDBConnection();
$adminId = (int)$adminUser['id'];

// Resolve User or Verification Identifier
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$verificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId <= 0 && $verificationId <= 0) {
    setFlash('danger', 'Invalid user or verification identifier specified.');
    redirect('/admin/users.php');
}

if ($userId > 0) {
    $verification = getUserVerification($userId);
} else {
    $row = dbFetchOne("SELECT user_id FROM identity_verifications WHERE id = ?", [$verificationId]);
    if (!$row) {
        setFlash('danger', 'Verification record not found.');
        redirect('/admin/users.php');
    }
    $userId = (int)$row['user_id'];
    $verification = getUserVerification($userId);
}

$user = dbFetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
if (!$user) {
    setFlash('danger', 'User account does not exist.');
    redirect('/admin/users.php');
}

// Fetch Profile Data
$workerProfile = null;
$customerProfile = null;
$skills = [];
$portfolios = [];

if ($user['role'] === 'worker') {
    $workerProfile = getWorkerProfile($userId);
    $skills = dbFetchAll("SELECT skill_name FROM worker_skills WHERE worker_id = ?", [(int)($workerProfile['id'] ?? 0)]);
    $portfolios = dbFetchAll("SELECT * FROM worker_portfolio WHERE worker_id = ?", [(int)($workerProfile['id'] ?? 0)]);
} elseif ($user['role'] === 'customer') {
    $customerProfile = getCustomerProfile($userId);
}

// Fetch Documents & Audit History
$documents = getUserDocuments($userId);
$history = getUserVerificationHistory($userId);

// ── HANDLE ADMIN VERIFICATION ACTIONS ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        setFlash('danger', 'Session security token expired. Please try again.');
        redirect('/admin/verification-details.php?user_id=' . $userId);
    }

    $action = $_POST['action'] ?? '';
    $prevStatus = $verification['status'];
    $vId = (int)$verification['id'];

    if ($action === 'approve') {
        // 1. APPROVE ACTION
        $notes = trim($_POST['approval_notes'] ?? 'Identity documents and profile information verified by Admin.');
        $newStatus = 'verified';

        // Update verification table
        dbExecute(
            "UPDATE identity_verifications 
             SET status = ?, reviewed_by = ?, reviewed_at = NOW(), admin_notes = ?, updated_at = NOW() 
             WHERE id = ?",
            [$newStatus, $adminId, $notes, $vId]
        );

        // Update profile verified status
        if ($user['role'] === 'worker') {
            dbExecute("UPDATE worker_profiles SET is_verified = 1, verification_notes = ? WHERE user_id = ?", [$notes, $userId]);
        } else {
            dbExecute("UPDATE customer_profiles SET is_verified = 1, verification_notes = ? WHERE user_id = ?", [$notes, $userId]);
        }

        // Record history
        dbExecute(
            "INSERT INTO verification_history (verification_id, user_id, action, actor_id, previous_status, new_status, remarks, created_at)
             VALUES (?, ?, 'approved', ?, ?, ?, ?, NOW())",
            [$vId, $userId, $adminId, $prevStatus, $newStatus, $notes]
        );

        // Notify user
        createNotification(
            $userId,
            'Identity Verification Approved!',
            'Congratulations! Your identity documents have been approved by KaamConnect administrators. You now have full verified status.',
            ($user['role'] === 'worker') ? url('/worker/dashboard.php') : url('/customer/dashboard.php'),
            'verification'
        );

        setFlash('success', 'User verification successfully APPROVED! Verified badge is now active.');
        redirect('/admin/verification-details.php?user_id=' . $userId);

    } elseif ($action === 'reject') {
        // 2. REJECT ACTION
        $reason = trim($_POST['rejection_reason'] ?? '');
        if (empty($reason)) {
            setFlash('danger', 'Please provide a clear rejection reason.');
            redirect('/admin/verification-details.php?user_id=' . $userId);
        }

        $newStatus = 'rejected';

        dbExecute(
            "UPDATE identity_verifications 
             SET status = ?, reviewed_by = ?, reviewed_at = NOW(), admin_notes = ?, updated_at = NOW() 
             WHERE id = ?",
            [$newStatus, $adminId, $reason, $vId]
        );

        if ($user['role'] === 'worker') {
            dbExecute("UPDATE worker_profiles SET is_verified = 0, verification_notes = ? WHERE user_id = ?", [$reason, $userId]);
        } else {
            dbExecute("UPDATE customer_profiles SET is_verified = 0, verification_notes = ? WHERE user_id = ?", [$reason, $userId]);
        }

        dbExecute(
            "INSERT INTO verification_history (verification_id, user_id, action, actor_id, previous_status, new_status, remarks, created_at)
             VALUES (?, ?, 'rejected', ?, ?, ?, ?, NOW())",
            [$vId, $userId, $adminId, $prevStatus, $newStatus, $reason]
        );

        createNotification(
            $userId,
            'Verification Not Approved',
            'Your identity verification request was rejected. Reason: ' . $reason,
            ($user['role'] === 'worker') ? url('/worker/verify.php') : url('/customer/verify.php'),
            'verification'
        );

        setFlash('warning', 'User verification marked as REJECTED. Rejection notice sent to user.');
        redirect('/admin/verification-details.php?user_id=' . $userId);

    } elseif ($action === 'resubmission') {
        // 3. REQUEST RESUBMISSION ACTION
        $instructions = trim($_POST['resubmission_instructions'] ?? '');
        if (empty($instructions)) {
            setFlash('danger', 'Please provide clear resubmission instructions for the user.');
            redirect('/admin/verification-details.php?user_id=' . $userId);
        }

        $newStatus = 'resubmission_required';

        dbExecute(
            "UPDATE identity_verifications 
             SET status = ?, reviewed_by = ?, reviewed_at = NOW(), admin_notes = ?, updated_at = NOW() 
             WHERE id = ?",
            [$newStatus, $adminId, $instructions, $vId]
        );

        if ($user['role'] === 'worker') {
            dbExecute("UPDATE worker_profiles SET is_verified = 0, verification_notes = ? WHERE user_id = ?", [$instructions, $userId]);
        } else {
            dbExecute("UPDATE customer_profiles SET is_verified = 0, verification_notes = ? WHERE user_id = ?", [$instructions, $userId]);
        }

        dbExecute(
            "INSERT INTO verification_history (verification_id, user_id, action, actor_id, previous_status, new_status, remarks, created_at)
             VALUES (?, ?, 'resubmission_requested', ?, ?, ?, ?, NOW())",
            [$vId, $userId, $adminId, $prevStatus, $newStatus, $instructions]
        );

        createNotification(
            $userId,
            'Document Resubmission Requested',
            'Action required on your verification: ' . $instructions . ' Please upload corrected documents.',
            ($user['role'] === 'worker') ? url('/worker/verify.php') : url('/customer/verify.php'),
            'verification'
        );

        setFlash('info', 'Resubmission requested. User notified with your specific instructions.');
        redirect('/admin/verification-details.php?user_id=' . $userId);

    } elseif ($action === 'under_review') {
        // 4. MARK UNDER REVIEW ACTION
        $newStatus = 'under_review';
        $reviewNotes = 'Verification review initiated by Admin (' . $adminUser['full_name'] . ').';

        dbExecute(
            "UPDATE identity_verifications 
             SET status = ?, reviewed_by = ?, reviewed_at = NOW(), admin_notes = ?, updated_at = NOW() 
             WHERE id = ?",
            [$newStatus, $adminId, $reviewNotes, $vId]
        );

        if ($user['role'] === 'worker') {
            dbExecute("UPDATE worker_profiles SET is_verified = 0, verification_notes = ? WHERE user_id = ?", [$reviewNotes, $userId]);
        } else {
            dbExecute("UPDATE customer_profiles SET is_verified = 0, verification_notes = ? WHERE user_id = ?", [$reviewNotes, $userId]);
        }

        dbExecute(
            "INSERT INTO verification_history (verification_id, user_id, action, actor_id, previous_status, new_status, remarks, created_at)
             VALUES (?, ?, 'under_review', ?, ?, ?, ?, NOW())",
            [$vId, $userId, $adminId, $prevStatus, $newStatus, $reviewNotes]
        );

        createNotification(
            $userId,
            'Verification Under Review',
            'Your identity verification is currently being reviewed by our administrative team.',
            ($user['role'] === 'worker') ? url('/worker/verify.php') : url('/customer/verify.php'),
            'verification'
        );

        setFlash('info', 'Status updated to UNDER REVIEW.');
        redirect('/admin/verification-details.php?user_id=' . $userId);
    }
}

$pageTitle = 'Verification Details - KaamConnect Admin';
require_once __DIR__ . '/includes/header.php';

// Active Profile and Coordinates
$profile = ($user['role'] === 'worker') ? $workerProfile : $customerProfile;
$hasCoords = (!empty($profile['latitude']) && !empty($profile['longitude']));
$lat = $hasCoords ? (float)$profile['latitude'] : 28.6139;
$lon = $hasCoords ? (float)$profile['longitude'] : 77.2090;

$st = $verification['status'];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="<?= url('/admin/index.php') ?>" class="text-decoration-none">Admin</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/admin/users.php') ?>" class="text-decoration-none">Users</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($user['full_name']) ?></li>
            </ol>
        </nav>
        <h2 class="h3 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <?= e($user['full_name']) ?>
            <span class="badge <?= ($user['role'] === 'worker') ? 'bg-warning-subtle text-warning-emphasis border border-warning' : 'bg-success-subtle text-success border border-success-subtle' ?> fs-6 text-capitalize">
                <?= e($user['role']) ?>
            </span>
        </h2>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/admin/users.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Back to Users
        </a>
    </div>
</div>

<!-- TOP STATUS & ACTION BAR -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="small text-muted fw-bold text-uppercase">Current Verification Status:</span>
                <?php
                    $vBadgeClass = match($st) {
                        'verified' => 'bg-success',
                        'under_review' => 'bg-info text-dark',
                        'resubmission_required' => 'bg-warning text-dark',
                        'rejected' => 'bg-danger',
                        default => 'bg-secondary'
                    };
                ?>
                <span class="badge <?= $vBadgeClass ?> fs-6 px-3 py-1 text-capitalize">
                    <?= str_replace('_', ' ', e($st)) ?>
                </span>
            </div>
            <?php if (!empty($verification['admin_notes'])): ?>
                <div class="small text-muted mt-1">
                    <strong>Admin Note:</strong> <?= e($verification['admin_notes']) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- MODULE 3.6: FOUR ADMINISTRATIVE WORKFLOW BUTTONS -->
        <div class="d-flex flex-wrap gap-2">
            <!-- 1. Under Review -->
            <?php if ($st === 'pending' || $st === 'resubmission_required'): ?>
                <form method="POST" action="" class="d-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="under_review">
                    <button type="submit" class="btn btn-outline-info btn-sm fw-bold px-3">
                        <i class="fas fa-spinner fa-spin me-1"></i>Mark Under Review
                    </button>
                </form>
            <?php endif; ?>

            <!-- 2. Request Resubmission Modal Trigger -->
            <button type="button" class="btn btn-outline-warning text-dark btn-sm fw-bold px-3" data-bs-toggle="modal" data-bs-target="#resubmissionModal">
                <i class="fas fa-rotate-left me-1"></i>Request Resubmission
            </button>

            <!-- 3. Reject Modal Trigger -->
            <button type="button" class="btn btn-outline-danger btn-sm fw-bold px-3" data-bs-toggle="modal" data-bs-target="#rejectModal">
                <i class="fas fa-ban me-1"></i>Reject Verification
            </button>

            <!-- 4. Approve Modal Trigger -->
            <button type="button" class="btn btn-success btn-sm fw-bold px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#approveModal">
                <i class="fas fa-check-circle me-1"></i>Approve Verification
            </button>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- LEFT COLUMN: User Profile & Details -->
    <div class="col-lg-7">
        <!-- 1. PERSONAL INFORMATION (MODULE 3.5) -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-user-circle text-primary me-2"></i>Personal Information
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                    <?php if (!empty($profile['profile_photo']) && file_exists(dirname(__DIR__) . '/' . ltrim($profile['profile_photo'], '/'))): ?>
                        <img src="<?= asset($profile['profile_photo']) ?>" alt="Avatar" width="68" height="68" class="rounded-circle object-fit-cover border shadow-sm">
                    <?php else: ?>
                        <div class="bg-primary text-white fw-bold rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 68px; height: 68px; font-size: 24px;">
                            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= e($user['full_name']) ?></h4>
                        <span class="text-muted small">Account ID: <strong class="text-secondary font-monospace">#<?= (int)$user['id'] ?></strong></span>
                        <div class="small text-muted mt-1">
                            Registered on <?= date('d M Y, h:i A', strtotime($user['created_at'])) ?>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-sm-6">
                        <span class="small text-muted d-block">Mobile Number:</span>
                        <strong class="text-dark font-monospace"><i class="fas fa-phone text-secondary me-1"></i><?= e($user['phone']) ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="small text-muted d-block">Email Address:</span>
                        <strong class="text-dark font-monospace"><i class="fas fa-envelope text-secondary me-1"></i><?= e($user['email']) ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="small text-muted d-block">Date of Birth:</span>
                        <strong class="text-dark"><?= !empty($user['dob']) ? date('d M Y', strtotime($user['dob'])) : '<span class="text-muted fw-normal">Not Provided</span>' ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="small text-muted d-block">Gender:</span>
                        <strong class="text-dark text-capitalize"><?= !empty($user['gender']) ? e($user['gender']) : '<span class="text-muted fw-normal">Not Provided</span>' ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="small text-muted d-block">Preferred Language:</span>
                        <strong class="text-dark"><?= ($user['preferred_language'] === 'hi') ? 'हिंदी (Hindi)' : 'English' ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="small text-muted d-block">Account Status:</span>
                        <span class="badge <?= ($user['status'] === 'active') ? 'bg-success' : 'bg-danger' ?> text-capitalize"><?= e($user['status']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. ADDRESS INFORMATION & MAP (MODULE 3.5) -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-location-dot text-danger me-2"></i>Address & Geolocation Information
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <span class="small text-muted d-block">Locality / Sector:</span>
                        <strong class="text-dark"><?= e($profile['locality'] ?? 'N/A') ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="small text-muted d-block">City:</span>
                        <strong class="text-dark"><?= e($profile['city'] ?? 'N/A') ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="small text-muted d-block">District:</span>
                        <strong class="text-dark"><?= e($profile['district'] ?? 'N/A') ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="small text-muted d-block">State:</span>
                        <strong class="text-dark"><?= e($profile['state'] ?? 'N/A') ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="small text-muted d-block">PIN Code:</span>
                        <strong class="text-dark"><?= e($profile['pincode'] ?? 'N/A') ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="small text-muted d-block">GPS Coordinates:</span>
                        <span class="font-monospace small <?= $hasCoords ? 'text-success fw-bold' : 'text-muted' ?>">
                            <?= $hasCoords ? number_format($lat, 4) . ', ' . number_format($lon, 4) : 'Coordinates not pinned' ?>
                        </span>
                    </div>
                    <?php if (!empty($profile['address'])): ?>
                        <div class="col-12">
                            <span class="small text-muted d-block">Complete Street Address:</span>
                            <div class="p-2 bg-light rounded border small text-dark"><?= nl2br(e($profile['address'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Interactive Coordinates Map Preview -->
                <?php if ($hasCoords): ?>
                    <div class="mt-3">
                        <span class="small text-muted fw-semibold d-block mb-1">
                            <i class="fas fa-map me-1 text-primary"></i>Pinned Map Coordinates:
                        </span>
                        <div id="adminUserMap" style="height: 220px; border-radius: 10px;" class="border shadow-sm"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 3. WORKER OR CUSTOMER SPECIFIC INFORMATION (MODULE 3.5) -->
        <?php if ($user['role'] === 'worker' && $workerProfile): ?>
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom py-3 px-4">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-briefcase text-warning me-2"></i>Worker Skills & Professional Information
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <span class="small text-muted d-block">Primary Trade / Category:</span>
                            <strong class="text-dark"><?= e($workerProfile['category_name_en'] ?? 'Uncategorized') ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="small text-muted d-block">Experience:</span>
                            <strong class="text-dark"><?= (int)($workerProfile['experience_years'] ?? 0) ?> Years</strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="small text-muted d-block">Daily Rate / Wage:</span>
                            <strong class="text-success">₹<?= (int)($workerProfile['daily_wage'] ?? 0) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="small text-muted d-block">Hourly Rate:</span>
                            <strong class="text-success">₹<?= (int)($workerProfile['hourly_wage'] ?? 0) ?></strong>
                        </div>
                    </div>

                    <?php if (!empty($workerProfile['bio'])): ?>
                        <div class="mb-3">
                            <span class="small text-muted d-block">Professional Bio / Description:</span>
                            <p class="small text-secondary mb-0 bg-light p-3 rounded border"><?= nl2br(e($workerProfile['bio'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($skills)): ?>
                        <div>
                            <span class="small text-muted d-block mb-2">Registered Skills:</span>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($skills as $sk): ?>
                                    <span class="badge bg-light text-dark border px-2 py-1"><?= e($sk['skill_name']) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT COLUMN: Identity Documents & Audit Trail -->
    <div class="col-lg-5">
        <!-- 4. SUBMITTED IDENTITY DOCUMENTS (MODULE 3.5) -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-folder-open text-primary me-2"></i>Identity Documents
                </h5>
                <span class="badge bg-light text-dark border"><?= count($documents) ?> File(s)</span>
            </div>
            <div class="card-body p-4">
                <?php if (empty($documents)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-file-circle-xmark fa-3x opacity-50 mb-2"></i>
                        <p class="small mb-0">No identity documents have been submitted yet.</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($documents as $doc): ?>
                            <div class="border rounded-3 p-3 bg-light">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <span class="badge bg-primary-subtle text-primary text-uppercase fw-bold mb-1">
                                            <?= str_replace('_', ' ', e($doc['document_type'])) ?>
                                        </span>
                                        <div class="fw-bold font-monospace text-dark">
                                            <?= e($doc['document_reference_masked'] ?? 'Reference Recorded') ?>
                                        </div>
                                    </div>
                                    <span class="text-muted small">
                                        <?= date('d M Y', strtotime($doc['created_at'])) ?>
                                    </span>
                                </div>

                                <div class="d-flex flex-wrap gap-2 mt-3 pt-2 border-top">
                                    <a href="<?= url('/view_document.php?id=' . (int)$doc['id'] . '&side=front') ?>" target="_blank" class="btn btn-outline-primary btn-sm py-1 px-3">
                                        <i class="fas fa-eye me-1"></i>View Front Side
                                    </a>
                                    <?php if (!empty($doc['back_image_path'])): ?>
                                        <a href="<?= url('/view_document.php?id=' . (int)$doc['id'] . '&side=back') ?>" target="_blank" class="btn btn-outline-secondary btn-sm py-1 px-3">
                                            <i class="fas fa-eye me-1"></i>View Back Side
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 5. VERIFICATION AUDIT TRAIL TIMELINE (MODULE 3.6) -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-clock-rotate-left text-secondary me-2"></i>Verification Audit Trail
                </h5>
            </div>
            <div class="card-body p-4">
                <?php if (empty($history)): ?>
                    <p class="text-muted small mb-0">No prior verification transitions recorded.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($history as $ev): ?>
                            <div class="pb-3 mb-3 border-bottom last:border-0 last:mb-0 last:pb-0">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="small text-dark text-capitalize">
                                        <i class="fas fa-circle-dot text-primary me-1" style="font-size: 8px;"></i>
                                        <?= str_replace('_', ' ', e($ev['action'])) ?>
                                    </strong>
                                    <span class="text-muted" style="font-size: 11px;">
                                        <?= date('d M Y, h:i A', strtotime($ev['created_at'])) ?>
                                    </span>
                                </div>
                                <div class="small text-muted mb-1" style="font-size: 12px;">
                                    Actor: <strong><?= e($ev['actor_name']) ?></strong> (<?= e($ev['actor_role']) ?>) &bull; 
                                    New Status: <span class="badge bg-light text-dark border"><?= e($ev['new_status']) ?></span>
                                </div>
                                <?php if (!empty($ev['remarks'])): ?>
                                    <div class="small p-2 bg-light rounded text-secondary border mt-1" style="font-size: 12px;">
                                        <?= e($ev['remarks']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODALS FOR 4 ACTIONS ================= -->

<!-- 1. APPROVE MODAL -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fw-bold" id="approveModalLabel"><i class="fas fa-check-circle me-2"></i>Approve Verification</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="approve">
                <div class="modal-body p-4">
                    <p class="text-dark mb-3">
                        Are you sure you want to approve <strong><?= e($user['full_name']) ?></strong>?
                    </p>
                    <div class="p-3 bg-light rounded mb-3 small text-muted border">
                        <i class="fas fa-info-circle text-primary me-1"></i> Approving will grant verified badge status, activate bidding permissions, and notify the user via in-app alerts.
                    </div>
                    <div class="mb-3">
                        <label for="approval_notes" class="form-label small fw-semibold text-dark">Approval Notes / Verification Remarks:</label>
                        <textarea class="form-control" name="approval_notes" id="approval_notes" rows="3" placeholder="e.g. Identity documents inspected and verified against registry. Background verified."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm px-4 fw-bold">Confirm & Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. REJECT MODAL -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold" id="rejectModalLabel"><i class="fas fa-ban me-2"></i>Reject Verification</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="reject">
                <div class="modal-body p-4">
                    <p class="text-dark mb-3">
                        Please specify the reason for rejecting verification for <strong><?= e($user['full_name']) ?></strong>.
                    </p>
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label small fw-semibold text-dark">Rejection Reason <span class="text-danger">*</span>:</label>
                        <textarea class="form-control" name="rejection_reason" id="rejection_reason" rows="3" required placeholder="e.g. Identity document appears fraudulent or expired. Name mismatch between profile and ID."></textarea>
                        <div class="form-text small text-muted">This reason will be provided to the user in their rejection notification.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm px-4 fw-bold">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. RESUBMISSION MODAL -->
<div class="modal fade" id="resubmissionModal" tabindex="-1" aria-labelledby="resubmissionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-warning text-dark py-3">
                <h5 class="modal-title fw-bold" id="resubmissionModalLabel"><i class="fas fa-rotate-left me-2"></i>Request Document Resubmission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="resubmission">
                <div class="modal-body p-4">
                    <p class="text-dark mb-3">
                        Request <strong><?= e($user['full_name']) ?></strong> to re-upload clear or updated documents.
                    </p>
                    <div class="mb-3">
                        <label for="resubmission_instructions" class="form-label small fw-semibold text-dark">Instructions for User <span class="text-danger">*</span>:</label>
                        <textarea class="form-control" name="resubmission_instructions" id="resubmission_instructions" rows="3" required placeholder="e.g. Front image of Voter ID is blurry. Please upload a high-resolution, uncropped scan."></textarea>
                        <div class="form-text small text-muted">The user will see these instructions and an upload form on their dashboard.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark btn-sm px-4 fw-bold">Send Resubmission Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($hasCoords): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mapEl = document.getElementById('adminUserMap');
    if (mapEl && typeof L !== 'undefined') {
        const userLat = <?= $lat ?>;
        const userLon = <?= $lon ?>;
        const map = L.map(mapEl).setView([userLat, userLon], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        L.marker([userLat, userLon]).addTo(map)
            .bindPopup("<strong><?= e($user['full_name']) ?></strong><br><?= e($profile['locality'] ?? '') ?>, <?= e($profile['city'] ?? '') ?>")
            .openPopup();
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

