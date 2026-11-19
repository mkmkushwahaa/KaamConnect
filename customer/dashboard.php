<?php
/**
 * KaamConnect - Customer Dashboard with Identity Verification Status
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/verification_service.php';

requireCustomer();
$user = currentUser();
$userId = (int)$user['id'];
$customer = getCustomerProfile($userId);
$verification = getUserVerification($userId);
$documents = getUserDocuments($userId);

$pageTitle = 'Customer Dashboard - KaamConnect';
require_once __DIR__ . '/../includes/header.php';

$st = $verification['status'];

// Fetch customer jobs count
$jobCount = 0;
try {
    $row = dbFetchOne("SELECT COUNT(*) AS cnt FROM jobs WHERE customer_id = ?", [(int)($customer['id'] ?? 0)]);
    $jobCount = (int)($row['cnt'] ?? 0);
} catch (Exception $e) {
    $jobCount = 0;
}
?>

<div class="bg-primary text-white py-4 mb-4">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1 class="h3 fw-bold mb-1">
                    Welcome back, <?= e($user['full_name']) ?>!
                </h1>
                <p class="text-white-50 mb-0 small">
                    <i class="fas fa-user-circle text-warning me-1"></i>Verified Customer Account &bull; 
                    <i class="fas fa-map-marker-alt text-info me-1"></i><?= e($customer['locality'] ?? '') ?>, <?= e($customer['city'] ?? 'India') ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= url('/workers.php') ?>" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-search me-1"></i>Hire Workers
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <!-- MODULE 4: VERIFICATION STATUS SECTION -->
    <div class="kc-card p-4 mb-4 border-0 shadow-sm" style="border-radius: var(--kc-radius-lg);">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3 border-bottom pb-3">
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-shield-alt text-primary me-2"></i>Customer Identity Verification Status
                    </h5>
                    <?php if ($st === 'verified'): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill fw-bold">
                            <i class="fas fa-check-circle me-1"></i>Verified Customer
                        </span>
                    <?php elseif ($st === 'under_review'): ?>
                        <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1 rounded-pill fw-bold">
                            <i class="fas fa-spinner fa-spin me-1"></i>Under Administrative Review
                        </span>
                    <?php elseif ($st === 'resubmission_required'): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-1 rounded-pill fw-bold">
                            <i class="fas fa-triangle-exclamation me-1"></i>Resubmission Required
                        </span>
                    <?php elseif ($st === 'rejected'): ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill fw-bold">
                            <i class="fas fa-times-circle me-1"></i>Not Verified
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary border px-3 py-1 rounded-pill fw-bold">
                            <i class="fas fa-clock me-1"></i>Verification Pending
                        </span>
                    <?php endif; ?>
                </div>
                <p class="text-muted small mb-0">
                    Identity verification builds trust with workers and unlocks priority hiring and job posts.
                </p>
            </div>
            <div>
                <a href="<?= url('/customer/verify.php') ?>" class="btn btn-kc-primary btn-sm px-3 fw-bold">
                    <i class="fas fa-id-card me-1"></i><?= ($st === 'verified') ? 'View Documents' : (($st === 'resubmission_required') ? 'Upload New Documents' : 'Manage Verification') ?>
                </a>
            </div>
        </div>

        <!-- Verification Steps Feedback -->
        <div class="row g-3">
            <div class="col-md-4">
                <div class="p-3 bg-light rounded-3 border h-100">
                    <div class="small text-muted mb-1 fw-semibold">1. Profile Details</div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="fw-bold text-dark">Complete</span>
                        <i class="fas fa-check-circle text-success fs-5"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-light rounded-3 border h-100">
                    <div class="small text-muted mb-1 fw-semibold">2. Document Submission</div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="fw-bold text-dark"><?= count($documents) > 0 ? count($documents) . ' Uploaded' : 'Not Uploaded' ?></span>
                        <i class="fas <?= count($documents) > 0 ? 'fa-check-circle text-success' : 'fa-circle-exclamation text-warning' ?> fs-5"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-light rounded-3 border h-100">
                    <div class="small text-muted mb-1 fw-semibold">3. Admin Approval</div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="fw-bold text-capitalize text-dark"><?= str_replace('_', ' ', $st) ?></span>
                        <i class="fas <?= ($st === 'verified') ? 'fa-circle-check text-success' : (($st === 'rejected') ? 'fa-circle-xmark text-danger' : 'fa-clock text-primary') ?> fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Feedback Alert if Any -->
        <?php if (!empty($verification['admin_notes'])): ?>
            <div class="alert <?= ($st === 'resubmission_required') ? 'alert-warning' : (($st === 'rejected') ? 'alert-danger' : 'alert-info') ?> mt-3 mb-0 small">
                <strong><i class="fas fa-comment-dots me-1"></i>Admin Feedback:</strong>
                <?= e($verification['admin_notes']) ?>
            </div>
        <?php endif; ?>

        <!-- Marketplace Restriction Callout for Pending/Unverified Customers -->
        <?php if ($st !== 'verified'): ?>
            <div class="alert alert-warning border-start border-4 border-warning mt-3 mb-0 small d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <i class="fas fa-lock text-warning me-1"></i>
                    <strong>Marketplace Activities Restricted:</strong> Job posting and direct worker bookings are locked until customer identity verification is completed.
                </div>
                <a href="<?= url('/customer/verify.php') ?>" class="btn btn-warning text-dark btn-sm fw-bold">
                    <i class="fas fa-upload me-1"></i><?= ($st === 'resubmission_required') ? 'Upload Corrected ID' : 'Submit Documents' ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="kc-card p-3 d-flex align-items-center gap-3">
                <div class="p-3 bg-primary-subtle text-primary rounded-circle">
                    <i class="fas fa-clipboard-list fa-2x"></i>
                </div>
                <div>
                    <span class="text-muted small d-block">Posted Jobs</span>
                    <h4 class="fw-bold mb-0 text-dark"><?= $jobCount ?></h4>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="kc-card p-3 d-flex align-items-center gap-3">
                <div class="p-3 bg-success-subtle text-success rounded-circle">
                    <i class="fas fa-shield-halved fa-2x"></i>
                </div>
                <div>
                    <span class="text-muted small d-block">Account Status</span>
                    <h4 class="fw-bold mb-0 text-dark text-capitalize"><?= str_replace('_', ' ', $st) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="kc-card p-3 d-flex align-items-center gap-3">
                <div class="p-3 bg-info-subtle text-info rounded-circle">
                    <i class="fas fa-map-pin fa-2x"></i>
                </div>
                <div>
                    <span class="text-muted small d-block">Default City</span>
                    <h4 class="fw-bold mb-0 text-dark"><?= e($customer['city'] ?? 'Noida') ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Marketplace Actions -->
    <div class="kc-card p-4">
        <h5 class="fw-bold text-dark mb-3"><i class="fas fa-bolt text-warning me-2"></i>Customer Actions</h5>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($st === 'verified'): ?>
                <a href="<?= url('/customer/post-job.php') ?>" class="btn btn-warning text-dark fw-bold">
                    <i class="fas fa-plus-circle me-1"></i>Post a New Job
                </a>
            <?php else: ?>
                <a href="<?= url('/customer/verify.php') ?>" class="btn btn-outline-warning text-dark fw-bold">
                    <i class="fas fa-lock me-1"></i>Post a Job (Verify ID to Unlock)
                </a>
            <?php endif; ?>
            <a href="<?= url('/workers.php') ?>" class="btn btn-kc-primary fw-bold">
                <i class="fas fa-users me-1"></i>Find & Hire Workers
            </a>
            <a href="<?= url('/jobs.php') ?>" class="btn btn-outline-secondary fw-semibold">
                <i class="fas fa-briefcase me-1"></i>View Open Job Listings
            </a>
            <a href="<?= url('/customer/verify.php') ?>" class="btn btn-outline-secondary fw-semibold">
                <i class="fas fa-id-card me-1"></i>Identity Verification Center
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

