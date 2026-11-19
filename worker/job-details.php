<?php
/**
 * KaamConnect - Worker Job Details & Proposal Submission
 * Strictly protected: Identity-verified workers only
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

// Strict backend authorization guard for Module 5
requireVerifiedWorker();

$user = currentUser();
$userId = (int)$user['id'];
$workerProfile = getWorkerProfile($userId);
$workerProfileId = (int)($workerProfile['id'] ?? 0);

$jobId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($jobId <= 0) {
    setFlash('danger', 'Invalid job posting requested.');
    redirect('/jobs.php');
}

$pdo = getDBConnection();

$job = dbFetchOne(
    "SELECT j.*, 
            u.full_name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
            c.name_en AS category_name, c.name_hi AS category_name_hi, c.icon AS category_icon
     FROM jobs j
     JOIN users u ON j.customer_id = u.id
     LEFT JOIN categories c ON j.category_id = c.id
     WHERE j.id = ?",
    [$jobId]
);

if (!$job) {
    setFlash('danger', 'Job posting not found or has been removed.');
    redirect('/jobs.php');
}

// Check existing application by this worker
$existingApp = dbFetchOne(
    "SELECT * FROM job_applications WHERE job_id = ? AND worker_id = ?",
    [$jobId, $workerProfileId]
);

$errors = [];
$success = '';

// Handle Proposal / Bid Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $errors[] = 'Session security token expired. Please try again.';
    } elseif ($existingApp) {
        $errors[] = 'You have already submitted a proposal for this job.';
    } else {
        $proposedWage = (float)($_POST['proposed_wage'] ?? 0);
        $coverNote = trim($_POST['cover_note'] ?? '');

        if ($proposedWage <= 0) {
            $errors[] = 'Please enter a valid proposed wage amount.';
        }

        if (empty($errors)) {
            dbExecute(
                "INSERT INTO job_applications (job_id, worker_id, proposed_wage, cover_note, status, applied_at)
                 VALUES (?, ?, ?, ?, 'pending', NOW())",
                [$jobId, $workerProfileId, $proposedWage, $coverNote]
            );

            // Notify Customer
            createNotification(
                (int)$job['customer_id'],
                'New Job Proposal Received!',
                $user['full_name'] . ' submitted a proposal of ₹' . number_format($proposedWage, 2) . ' for "' . $job['title'] . '".',
                url('/customer/dashboard.php'),
                'job_application'
            );

            setFlash('success', 'Your proposal has been submitted successfully to the customer!');
            redirect('/worker/job-details.php?id=' . $jobId);
        }
    }
}

$pageTitle = e($job['title']) . ' - KaamConnect';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-primary text-white py-4 mb-4">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-dark mb-1 small">
                        <li class="breadcrumb-item"><a href="<?= url('/jobs.php') ?>" class="text-white-50">Jobs</a></li>
                        <li class="breadcrumb-item active text-white" aria-current="page"><?= e($job['title']) ?></li>
                    </ol>
                </nav>
                <h1 class="h3 fw-bold mb-0"><?= e($job['title']) ?></h1>
            </div>
            <div>
                <a href="<?= url('/jobs.php') ?>" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Back to Jobs List
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <div class="row g-4">
        <!-- Job Details Card -->
        <div class="col-lg-7">
            <div class="kc-card p-4 p-md-5 mb-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <span class="badge bg-primary-subtle text-primary fs-6 px-3 py-2 rounded-pill">
                        <i class="<?= e($job['category_icon'] ?? 'fas fa-briefcase') ?> me-1"></i><?= e($job['category_name'] ?? 'General') ?>
                    </span>
                    <span class="badge bg-success-subtle text-success fs-6 px-3 py-2 rounded-pill text-capitalize">
                        <?= e($job['status']) ?>
                    </span>
                </div>

                <h3 class="fw-bold mb-3 text-dark"><?= e($job['title']) ?></h3>
                
                <div class="mb-4 text-secondary lh-lg">
                    <?= nl2br(e($job['description'])) ?>
                </div>

                <?php if (!empty($job['required_skills'])): ?>
                    <div class="mb-4">
                        <h6 class="fw-bold text-dark mb-2">Required Skills / Expertise:</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach (explode(',', $job['required_skills']) as $sk): ?>
                                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill"><?= e(trim($sk)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row g-3 pt-3 border-top small">
                    <div class="col-sm-6">
                        <span class="text-muted d-block">Posted By Customer:</span>
                        <strong class="text-dark"><i class="fas fa-user text-primary me-1"></i><?= e($job['customer_name']) ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted d-block">Location:</span>
                        <strong class="text-dark"><i class="fas fa-location-dot text-danger me-1"></i><?= e($job['city']) ?><?= !empty($job['locality']) ? ', ' . e($job['locality']) : '' ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted d-block">Customer Budget:</span>
                        <strong class="text-success fs-5">₹<?= number_format((float)$job['budget'], 2) ?></strong>
                        <span class="text-muted text-capitalize">(<?= e($job['wage_type'] ?? 'fixed') ?>)</span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted d-block">Date Posted:</span>
                        <strong class="text-dark"><?= date('d M Y', strtotime($job['created_at'])) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Proposal Submission Card -->
        <div class="col-lg-5">
            <div class="kc-card p-4 p-md-5">
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fas fa-paper-plane text-primary me-2"></i><?= $existingApp ? 'Your Submitted Proposal' : 'Apply for this Job' ?>
                </h4>
                <p class="text-muted small mb-4">
                    Submit your proposed wage and work proposal directly to the customer.
                </p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger p-3 mb-4">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($existingApp): ?>
                    <div class="alert alert-success border-success p-3 rounded-3 mb-3">
                        <h6 class="fw-bold mb-1"><i class="fas fa-check-circle me-1"></i>Proposal Submitted</h6>
                        <p class="small mb-2">You have already submitted a proposal for this job on <?= date('d M Y, h:i A', strtotime($existingApp['applied_at'])) ?>.</p>
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="small text-muted">Your Proposed Wage:</span>
                            <strong class="text-success fs-5">₹<?= number_format((float)$existingApp['proposed_wage'], 2) ?></strong>
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-warning-subtle text-warning-emphasis text-capitalize px-3 py-1 rounded-pill">
                                Status: <?= e($existingApp['status']) ?>
                            </span>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST" action="">
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label for="proposed_wage" class="form-label small fw-semibold text-dark">
                                Your Proposed Wage (₹) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">₹</span>
                                <input type="number" step="10" min="50" class="form-control" id="proposed_wage" name="proposed_wage" 
                                       value="<?= e((string)(float)$job['budget']) ?>" required>
                            </div>
                            <div class="form-text small text-muted">Customer budget is ₹<?= number_format((float)$job['budget'], 2) ?>.</div>
                        </div>

                        <div class="mb-4">
                            <label for="cover_note" class="form-label small fw-semibold text-dark">
                                Proposal Note / Experience Pitch
                            </label>
                            <textarea class="form-control" id="cover_note" name="cover_note" rows="4" 
                                      placeholder="Explain your relevant experience, tools available, and why you are the best fit for this work..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-kc-primary w-100 py-2 fw-bold">
                            <i class="fas fa-paper-plane me-1"></i>Submit Proposal
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
