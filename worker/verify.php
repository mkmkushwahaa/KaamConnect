<?php
/**
 * KaamConnect - Worker Identity Verification & Document Submission
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/verification_service.php';

requireWorker();
$user = currentUser();
$userId = (int)$user['id'];

$pageTitle = 'Identity Verification - KaamConnect';
$errors = [];
$success = '';

// Handle Document Submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyCsrfToken()) {
        $errors[] = 'Session security token expired. Please try again.';
    } else {
        $docType = trim($_POST['document_type'] ?? '');
        $docNumber = trim($_POST['document_number'] ?? '');
        $frontFile = $_FILES['front_document'] ?? [];
        $backFile = !empty($_FILES['back_document']['name']) ? $_FILES['back_document'] : null;

        $result = submitIdentityDocument($userId, $docType, $docNumber, $frontFile, $backFile);

        if ($result['success']) {
            setFlash('success', 'Your identity documents have been submitted successfully! Our admin team is reviewing your information.');
            redirect('/worker/verify.php');
        } else {
            $errors[] = $result['error'];
        }
    }
}

$verification = getUserVerification($userId);
$documents = getUserDocuments($userId);
$history = getUserVerificationHistory($userId);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-primary text-white py-4 mb-4">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-dark mb-1 small">
                        <li class="breadcrumb-item"><a href="<?= url('/worker/dashboard.php') ?>" class="text-white-50">Dashboard</a></li>
                        <li class="breadcrumb-item active text-white" aria-current="page">Identity Verification</li>
                    </ol>
                </nav>
                <h1 class="h3 fw-bold mb-0">
                    <i class="fas fa-id-card-clip text-warning me-2"></i>Identity Verification
                </h1>
            </div>
            <div>
                <a href="<?= url('/worker/dashboard.php') ?>" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <!-- Status Banner Component -->
    <?php 
        $st = $verification['status'];
        $badgeClass = match($st) {
            'verified' => 'bg-success',
            'under_review' => 'bg-info text-dark',
            'resubmission_required' => 'bg-warning text-dark',
            'rejected' => 'bg-danger',
            default => 'bg-secondary'
        };
        $statusLabel = match($st) {
            'verified' => 'Account Verified',
            'under_review' => 'Under Administrative Review',
            'resubmission_required' => 'Resubmission Required',
            'rejected' => 'Verification Rejected',
            default => 'Verification Pending'
        };
    ?>

    <div class="kc-card p-4 mb-4 border-start border-4 <?= ($st === 'verified') ? 'border-success' : (($st === 'rejected') ? 'border-danger' : (($st === 'resubmission_required') ? 'border-warning' : 'border-primary')) ?>">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge <?= $badgeClass ?> px-3 py-2 rounded-pill fs-6 fw-bold">
                        <i class="fas <?= ($st === 'verified') ? 'fa-check-circle' : (($st === 'rejected') ? 'fa-times-circle' : 'fa-clock') ?> me-1"></i>
                        <?= $statusLabel ?>
                    </span>
                    <?php if ($verification['submitted_at']): ?>
                        <span class="text-muted small">Submitted on <?= date('d M Y, h:i A', strtotime($verification['submitted_at'])) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($st === 'verified'): ?>
                    <p class="text-success fw-semibold mb-0">
                        <i class="fas fa-shield-halved me-1"></i>Congratulations! Your identity has been verified. You now enjoy full marketplace hiring privileges, direct job bids, and a Verified badge on your profile.
                    </p>
                <?php elseif ($st === 'under_review'): ?>
                    <p class="text-dark mb-0">
                        <i class="fas fa-spinner fa-spin text-info me-1"></i>Our administrative verification team is actively reviewing your submitted documents. You will receive an on-platform notification once the review is complete.
                    </p>
                <?php elseif ($st === 'resubmission_required'): ?>
                    <div class="alert alert-warning mb-0 p-3 mt-2">
                        <strong class="d-block mb-1"><i class="fas fa-triangle-exclamation me-1"></i>Action Required from Admin:</strong>
                        <span><?= e($verification['admin_notes'] ?? 'Please review your document images and re-upload clear, legible copies.') ?></span>
                    </div>
                <?php elseif ($st === 'rejected'): ?>
                    <div class="alert alert-danger mb-0 p-3 mt-2">
                        <strong class="d-block mb-1"><i class="fas fa-ban me-1"></i>Verification Not Approved:</strong>
                        <span><?= e($verification['admin_notes'] ?? 'The submitted identity documents could not be validated. Please contact support.') ?></span>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">
                        Your identity documents have been submitted successfully. Our admin team is reviewing your information. You will receive a notification after verification.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger p-3 mb-4">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Document Upload Card (Only show if not verified or resubmission requested) -->
        <?php if ($st !== 'verified' || empty($documents)): ?>
            <div class="col-lg-7">
                <div class="kc-card p-4 p-md-5">
                    <h4 class="fw-bold mb-1 text-dark">
                        <i class="fas fa-upload text-primary me-2"></i><?= ($st === 'resubmission_required') ? 'Upload Corrected Documents' : 'Submit Identity Documents' ?>
                    </h4>
                    <p class="text-muted small mb-4">
                        In accordance with government safety guidelines, verified workers receive higher search placement, verified badges, and priority customer hiring.
                    </p>

                    <form method="POST" action="<?= url('/worker/verify.php') ?>" enctype="multipart/form-data" id="docUploadForm">
                        <?= csrfField() ?>

                        <!-- Document Type -->
                        <div class="mb-3">
                            <label for="document_type" class="form-label small fw-semibold text-dark">
                                Document Type <span class="text-danger">*</span>
                            </label>
                            <select name="document_type" id="document_type" class="form-select" required>
                                <option value="">Select Government Document Type</option>
                                <option value="voter_id">Voter ID Card (Election Commission of India)</option>
                                <option value="driving_licence">Driving Licence (State Transport Authority)</option>
                                <option value="passport">Indian Passport</option>
                                <option value="other">Other Legally Acceptable Government ID</option>
                            </select>
                            <div class="form-text small text-muted">
                                We avoid collecting unnecessary sensitive information or full Aadhaar numbers for privacy protection.
                            </div>
                        </div>

                        <!-- Document Reference Number -->
                        <div class="mb-3">
                            <label for="document_number" class="form-label small fw-semibold text-dark">
                                Document Number <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="document_number" name="document_number" placeholder="e.g. EPIC / DL Number" required autocomplete="off">
                            <div class="form-text small text-muted">
                                <i class="fas fa-user-shield text-success me-1"></i>This number will be masked in the database for privacy (e.g. VOT-XXXX-4521).
                            </div>
                        </div>

                        <!-- Front Side Upload -->
                        <div class="mb-3">
                            <label for="front_document" class="form-label small fw-semibold text-dark">
                                Front Side of Document <span class="text-danger">*</span>
                            </label>
                            <input type="file" class="form-control" id="front_document" name="front_document" accept=".jpg,.jpeg,.png,.pdf" required>
                            <div class="form-text small text-muted">Clear, legible color photo or scan (JPEG, PNG, or PDF, max 5 MB).</div>
                        </div>

                        <!-- Back Side Upload -->
                        <div class="mb-4">
                            <label for="back_document" class="form-label small fw-semibold text-dark">
                                Back Side of Document <span class="text-muted">(Optional for single-sided IDs)</span>
                            </label>
                            <input type="file" class="form-control" id="back_document" name="back_document" accept=".jpg,.jpeg,.png,.pdf">
                            <div class="form-text small text-muted">Required if address or details are on the back side.</div>
                        </div>

                        <!-- Privacy & Terms Notice -->
                        <div class="p-3 bg-light rounded-3 mb-4 small text-muted border">
                            <div class="d-flex align-items-center gap-2 mb-1 fw-bold text-dark">
                                <i class="fas fa-lock text-primary"></i>Privacy & Document Security Assurance
                            </div>
                            Documents are securely stored outside public directories and are exclusively accessible to authorized platform administrators for identity verification.
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-kc-primary py-2 fw-bold" id="btnSubmitDoc">
                                <i class="fas fa-cloud-arrow-up me-2"></i>Submit Documents for Verification
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Submitted Documents & History Column -->
        <div class="<?= ($st !== 'verified' || empty($documents)) ? 'col-lg-5' : 'col-lg-12' ?>">
            <!-- Submitted Documents List -->
            <div class="kc-card p-4 mb-4">
                <h5 class="fw-bold mb-3 text-dark">
                    <i class="fas fa-folder-closed text-primary me-2"></i>Submitted Documents
                </h5>

                <?php if (empty($documents)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-file-circle-question fa-3x opacity-50 mb-2"></i>
                        <p class="small mb-0">No identity documents uploaded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($documents as $d): ?>
                            <div class="border rounded-3 p-3 bg-light">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <span class="badge bg-primary-subtle text-primary text-uppercase fw-bold mb-1">
                                            <?= str_replace('_', ' ', e($d['document_type'])) ?>
                                        </span>
                                        <div class="fw-bold text-dark font-monospace">
                                            <?= e($d['document_reference_masked'] ?? 'Reference Recorded') ?>
                                        </div>
                                    </div>
                                    <span class="text-muted small">
                                        <?= date('d M Y', strtotime($d['created_at'])) ?>
                                    </span>
                                </div>

                                <div class="d-flex gap-2 mt-2">
                                    <a href="<?= url('/view_document.php?id=' . (int)$d['id'] . '&side=front') ?>" 
                                       target="_blank" 
                                       class="btn btn-outline-primary btn-sm py-1 px-2">
                                        <i class="fas fa-file-image me-1"></i>View Front
                                    </a>
                                    <?php if (!empty($d['back_image_path'])): ?>
                                        <a href="<?= url('/view_document.php?id=' . (int)$d['id'] . '&side=back') ?>" 
                                           target="_blank" 
                                           class="btn btn-outline-secondary btn-sm py-1 px-2">
                                            <i class="fas fa-file-image me-1"></i>View Back
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Verification Audit Timeline -->
            <div class="kc-card p-4">
                <h5 class="fw-bold mb-3 text-dark">
                    <i class="fas fa-timeline text-primary me-2"></i>Verification Activity Log
                </h5>
                <?php if (empty($history)): ?>
                    <p class="small text-muted mb-0">No verification activity recorded yet.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0 position-relative">
                        <?php foreach ($history as $h): ?>
                            <li class="pb-3 border-bottom mb-3 last:border-0 last:mb-0 last:pb-0">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="text-dark small text-capitalize">
                                        <?= str_replace('_', ' ', e($h['action'])) ?>
                                    </strong>
                                    <span class="text-muted" style="font-size: 11px;">
                                        <?= date('d M Y, h:i A', strtotime($h['created_at'])) ?>
                                    </span>
                                </div>
                                <div class="small text-muted mb-1">
                                    Status: <span class="badge bg-light text-dark border"><?= e($h['new_status']) ?></span>
                                    by <strong><?= e($h['actor_name']) ?></strong> (<?= e($h['actor_role']) ?>)
                                </div>
                                <?php if (!empty($h['remarks'])): ?>
                                    <div class="small bg-white p-2 rounded border text-secondary" style="font-size: 12px;">
                                        <?= e($h['remarks']) ?>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
