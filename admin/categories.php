<?php
/**
 * KaamConnect - Admin Categories Management
 * View and audit service categories
 */

declare(strict_types=1);

$pageTitle = 'Categories Management - KaamConnect Admin';
require_once __DIR__ . '/includes/header.php';

$pdo = getDBConnection();

$sql = "SELECT c.*, 
               (SELECT COUNT(*) FROM worker_profiles wp WHERE wp.primary_category_id = c.id) AS worker_count,
               (SELECT COUNT(*) FROM jobs j WHERE j.category_id = c.id) AS job_count
        FROM categories c
        ORDER BY c.id ASC";
$categories = dbFetchAll($sql);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h3 fw-bold mb-1 text-dark">
            <i class="fas fa-tags text-primary me-2"></i>Service Categories
        </h2>
        <p class="text-muted small mb-0">
            Work classifications, skill categories, and associated worker / job metrics.
        </p>
    </div>
    <span class="badge bg-white text-dark border px-3 py-2 rounded-pill fw-bold">
        <?= count($categories) ?> Active Categories
    </span>
</div>

<div class="row g-3">
    <?php foreach ($categories as $cat): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-3 bg-primary-subtle text-primary rounded-3 fs-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                            <i class="<?= e($cat['icon'] ?: 'fas fa-briefcase') ?>"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark"><?= e($cat['name_en']) ?></h5>
                            <span class="text-muted small"><?= e($cat['name_hi'] ?? '') ?></span>
                        </div>
                    </div>
                    <p class="text-muted small mb-3">
                        <?= e($cat['description'] ?: 'Professional and reliable services for home and commercial requirements.') ?>
                    </p>
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top small">
                        <span class="text-muted">
                            <i class="fas fa-user-tie text-warning me-1"></i><strong><?= (int)$cat['worker_count'] ?></strong> Workers
                        </span>
                        <span class="text-muted">
                            <i class="fas fa-briefcase text-info me-1"></i><strong><?= (int)$cat['job_count'] ?></strong> Jobs
                        </span>
                        <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1">
                            Active
                        </span>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

