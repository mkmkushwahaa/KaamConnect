<?php
/**
 * KaamConnect - Customer Book / Contact Worker
 * Strictly protected: Identity-verified customers only
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

// Strict backend authorization guard for Module 5
requireVerifiedCustomer();

$user = currentUser();
$userId = (int)$user['id'];

$workerUserId = isset($_GET['worker_id']) ? (int)$_GET['worker_id'] : 0;
if ($workerUserId <= 0) {
    setFlash('danger', 'Invalid worker selected.');
    redirect('/workers.php');
}

$pdo = getDBConnection();

$worker = dbFetchOne(
    "SELECT u.id, u.full_name, u.email, u.phone,
            wp.id AS worker_profile_id, wp.city, wp.locality, wp.daily_wage, wp.hourly_wage, wp.experience_years,
            c.name_en AS category_name, c.icon AS category_icon
     FROM users u
     JOIN worker_profiles wp ON u.id = wp.user_id
     LEFT JOIN categories c ON wp.primary_category_id = c.id
     WHERE u.id = ? AND u.role = 'worker'",
    [$workerUserId]
);

if (!$worker) {
    setFlash('danger', 'Worker profile not found.');
    redirect('/workers.php');
}

// Fetch open jobs posted by this customer to optionally link
$customerJobs = dbFetchAll(
    "SELECT id, title, budget FROM jobs WHERE customer_id = ? AND status = 'open' ORDER BY created_at DESC",
    [$userId]
);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $errors[] = 'Session security token expired. Please try again.';
    } else {
        $bookingMessage = trim($_POST['booking_message'] ?? '');
        $linkedJobId = !empty($_POST['job_id']) ? (int)$_POST['job_id'] : null;

        if (empty($bookingMessage)) {
            $errors[] = 'Please provide details about your service request.';
        }

        if (empty($errors)) {
            // Trigger Notification to Worker
            createNotification(
                $workerUserId,
                'New Booking / Hire Request!',
                $user['full_name'] . ' sent a booking request: "' . substr($bookingMessage, 0, 100) . '...". Contact: ' . $user['phone'],
                url('/worker/dashboard.php'),
                'booking_request'
            );

            setFlash('success', 'Your booking request has been sent to ' . e($worker['full_name']) . '! They will contact you shortly.');
            redirect('/workers.php');
        }
    }
}

$pageTitle = 'Book Worker: ' . e($worker['full_name']) . ' - KaamConnect';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-primary text-white py-4 mb-4">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-dark mb-1 small">
                        <li class="breadcrumb-item"><a href="<?= url('/workers.php') ?>" class="text-white-50">Workers</a></li>
                        <li class="breadcrumb-item active text-white" aria-current="page"><?= e($worker['full_name']) ?></li>
                    </ol>
                </nav>
                <h1 class="h3 fw-bold mb-0">Book / Contact Worker</h1>
            </div>
            <div>
                <a href="<?= url('/workers.php') ?>" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Back to Workers
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="kc-card p-4 p-md-5">
                <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                    <div class="bg-primary text-white fw-bold rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; font-size: 22px;">
                        <?= strtoupper(substr($worker['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= e($worker['full_name']) ?></h4>
                        <span class="badge bg-light text-dark border">
                            <i class="<?= e($worker['category_icon'] ?? 'fas fa-briefcase') ?> text-primary me-1"></i><?= e($worker['category_name'] ?? 'Craftsman') ?>
                        </span>
                        <div class="small text-muted mt-1">
                            <i class="fas fa-location-dot text-danger me-1"></i><?= e($worker['city']) ?><?= !empty($worker['locality']) ? ', ' . e($worker['locality']) : '' ?>
                            &bull; <strong>₹<?= number_format((float)($worker['daily_wage'] > 0 ? $worker['daily_wage'] : $worker['hourly_wage'])) ?></strong>/<?= $worker['daily_wage'] > 0 ? 'day' : 'hr' ?>
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

                <form method="POST" action="">
                    <?= csrfField() ?>

                    <?php if (!empty($customerJobs)): ?>
                        <div class="mb-3">
                            <label for="job_id" class="form-label small fw-semibold text-dark">
                                Link to an Existing Job Listing (Optional):
                            </label>
                            <select name="job_id" id="job_id" class="form-select">
                                <option value="">Direct Booking (No Job Linked)</option>
                                <?php foreach ($customerJobs as $cj): ?>
                                    <option value="<?= (int)$cj['id'] ?>">
                                        #<?= $cj['id'] ?>: <?= e($cj['title']) ?> (Budget: ₹<?= number_format((float)$cj['budget']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <label for="booking_message" class="form-label small fw-semibold text-dark">
                            Work Description & Schedule Requirements <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="booking_message" name="booking_message" rows="5" 
                                  placeholder="Describe the work required, preferred dates/timings, and address..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-kc-primary btn-lg w-100 py-3 fw-bold">
                        <i class="fas fa-paper-plane me-1"></i>Send Booking Request
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
