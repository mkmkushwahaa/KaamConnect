<?php
/**
 * KaamConnect - Admin Jobs Management
 * Overview and audit of all posted jobs on the platform
 */

declare(strict_types=1);

$pageTitle = 'Jobs Management - KaamConnect Admin';
require_once __DIR__ . '/includes/header.php';

$pdo = getDBConnection();

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');

$where = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(j.title LIKE ? OR j.city LIKE ? OR u.full_name LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($statusFilter !== 'all' && in_array($statusFilter, ['open', 'assigned', 'completed', 'cancelled'], true)) {
    $where[] = "j.status = ?";
    $params[] = $statusFilter;
}

$whereSql = implode(' AND ', $where);

$sql = "SELECT j.*, 
               u.full_name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
               c.name_en AS category_name,
               hw.full_name AS hired_worker_name
        FROM jobs j
        JOIN users u ON j.customer_id = u.id
        LEFT JOIN categories c ON j.category_id = c.id
        LEFT JOIN users hw ON j.hired_worker_id = hw.id
        WHERE {$whereSql}
        ORDER BY j.created_at DESC";

$jobs = dbFetchAll($sql, $params);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h3 fw-bold mb-1 text-dark">
            <i class="fas fa-briefcase text-primary me-2"></i>Jobs Management
        </h2>
        <p class="text-muted small mb-0">
            Monitor and audit all customer job postings, assigned contracts, and completion statuses.
        </p>
    </div>
    <span class="badge bg-white text-dark border px-3 py-2 rounded-pill fw-bold">
        Total: <?= count($jobs) ?> Job<?= count($jobs) === 1 ? '' : 's' ?>
    </span>
</div>

<!-- Search & Filter Card -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3 p-md-4">
        <!-- Status Filter Tabs -->
        <ul class="nav nav-pills gap-1 mb-3 pb-3 border-bottom overflow-x-auto flex-nowrap">
            <li class="nav-item">
                <a class="nav-link <?= ($statusFilter === 'all') ? 'active' : '' ?>" href="<?= url('/admin/jobs.php') ?>">
                    All Jobs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($statusFilter === 'open') ? 'active' : '' ?>" href="<?= url('/admin/jobs.php?status=open') ?>">
                    <span class="badge bg-success-subtle text-success me-1"><i class="fas fa-circle"></i></span> Open
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($statusFilter === 'assigned') ? 'active' : '' ?>" href="<?= url('/admin/jobs.php?status=assigned') ?>">
                    <span class="badge bg-warning-subtle text-warning me-1"><i class="fas fa-circle"></i></span> Assigned
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($statusFilter === 'completed') ? 'active' : '' ?>" href="<?= url('/admin/jobs.php?status=completed') ?>">
                    <span class="badge bg-primary-subtle text-primary me-1"><i class="fas fa-circle"></i></span> Completed
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($statusFilter === 'cancelled') ? 'active' : '' ?>" href="<?= url('/admin/jobs.php?status=cancelled') ?>">
                    <span class="badge bg-danger-subtle text-danger me-1"><i class="fas fa-circle"></i></span> Cancelled
                </a>
            </li>
        </ul>

        <!-- Search Form -->
        <form method="GET" action="<?= url('/admin/jobs.php') ?>" class="row g-2">
            <?php if ($statusFilter !== 'all'): ?>
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
            <?php endif; ?>
            <div class="col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control bg-light border-start-0" 
                           placeholder="Search by job title, location, or customer name..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-kc-primary flex-grow-1 fw-semibold">
                    <i class="fas fa-filter me-1"></i>Apply Filter
                </button>
                <?php if ($search !== '' || $statusFilter !== 'all'): ?>
                    <a href="<?= url('/admin/jobs.php') ?>" class="btn btn-outline-secondary" title="Clear Filters">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Jobs Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-muted small text-uppercase fw-bold">
                <tr>
                    <th class="ps-4">Job ID</th>
                    <th>Job Title & Category</th>
                    <th>Customer</th>
                    <th>Location</th>
                    <th>Budget</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th class="text-end pe-4">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($jobs)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fas fa-briefcase fa-3x mb-3 text-secondary opacity-50"></i>
                            <p class="mb-0 fw-semibold">No jobs found matching the selected criteria.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-muted">#<?= $job['id'] ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($job['title']) ?></div>
                                <span class="badge bg-light text-dark border small mt-1">
                                    <i class="fas fa-tag text-primary me-1"></i><?= e($job['category_name'] ?? 'General') ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($job['customer_name']) ?></div>
                                <div class="small text-muted"><?= e($job['customer_phone'] ?? $job['customer_email']) ?></div>
                            </td>
                            <td>
                                <div><i class="fas fa-location-dot text-danger me-1"></i><?= e($job['city']) ?></div>
                                <?php if (!empty($job['locality'])): ?>
                                    <div class="small text-muted"><?= e($job['locality']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold text-success">&#8377;<?= number_format((float)$job['budget'], 2) ?></div>
                                <span class="small text-muted text-capitalize"><?= e($job['wage_type'] ?? 'fixed') ?></span>
                            </td>
                            <td>
                                <?php
                                $badgeClass = match ($job['status']) {
                                    'open' => 'bg-success-subtle text-success border border-success-subtle',
                                    'assigned' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                    'completed' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                    'cancelled' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                    default => 'bg-secondary text-white'
                                };
                                ?>
                                <span class="badge <?= $badgeClass ?> px-2 py-1 rounded-pill text-capitalize fw-semibold">
                                    <?= e($job['status']) ?>
                                </span>
                                <?php if (!empty($job['hired_worker_name'])): ?>
                                    <div class="small text-muted mt-1">
                                        <i class="fas fa-user-check text-success me-1"></i><?= e($job['hired_worker_name']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?= date('d M Y', strtotime($job['created_at'])) ?>
                            </td>
                            <td class="text-end pe-4">
                                <a href="<?= url('/jobs.php') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                    <i class="fas fa-eye me-1"></i>Public View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

