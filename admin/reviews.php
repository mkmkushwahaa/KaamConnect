<?php
/**
 * KaamConnect - Admin Reviews Management
 * Audit customer ratings and feedback for workers
 */

declare(strict_types=1);

$pageTitle = 'Reviews Management - KaamConnect Admin';
require_once __DIR__ . '/includes/header.php';

$pdo = getDBConnection();

$sql = "SELECT r.*, 
               c.full_name AS customer_name, c.email AS customer_email,
               w.full_name AS worker_name, w.email AS worker_email,
               j.title AS job_title
        FROM reviews r
        JOIN users c ON r.customer_id = c.id
        JOIN users w ON r.worker_id = w.id
        LEFT JOIN jobs j ON r.job_id = j.id
        ORDER BY r.created_at DESC";
$reviews = dbFetchAll($sql);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h3 fw-bold mb-1 text-dark">
            <i class="fas fa-star text-warning me-2"></i>Platform Reviews & Ratings
        </h2>
        <p class="text-muted small mb-0">
            Audit customer feedback and ratings submitted for verified marketplace workers.
        </p>
    </div>
    <span class="badge bg-white text-dark border px-3 py-2 rounded-pill fw-bold">
        Total: <?= count($reviews) ?> Review<?= count($reviews) === 1 ? '' : 's' ?>
    </span>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-muted small text-uppercase fw-bold">
                <tr>
                    <th class="ps-4">ID</th>
                    <th>Customer (Reviewer)</th>
                    <th>Worker (Reviewed)</th>
                    <th>Job Reference</th>
                    <th>Rating</th>
                    <th>Review Feedback</th>
                    <th class="text-end pe-4">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reviews)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fas fa-star-half-stroke fa-3x mb-3 text-secondary opacity-50"></i>
                            <p class="mb-0 fw-semibold">No reviews recorded on the platform yet.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reviews as $rev): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-muted">#<?= $rev['id'] ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($rev['customer_name']) ?></div>
                                <div class="small text-muted"><?= e($rev['customer_email']) ?></div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($rev['worker_name']) ?></div>
                                <div class="small text-muted"><?= e($rev['worker_email']) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($rev['job_title'])): ?>
                                    <span class="text-dark fw-semibold"><?= e($rev['job_title']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">Direct Booking</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-warning small text-nowrap">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa<?= $i <= (int)$rev['rating'] ? 's' : 'r' ?> fa-star"></i>
                                    <?php endfor; ?>
                                    <span class="fw-bold text-dark ms-1"><?= $rev['rating'] ?>.0</span>
                                </div>
                            </td>
                            <td>
                                <div class="text-dark small text-break" style="max-width: 320px;">
                                    <?= nl2br(e($rev['review_text'])) ?>
                                </div>
                            </td>
                            <td class="text-end pe-4 small text-muted text-nowrap">
                                <?= date('d M Y', strtotime($rev['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

