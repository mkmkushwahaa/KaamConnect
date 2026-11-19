<?php
/**
 * KaamConnect - Admin Dashboard
 * Displays real-time platform statistics, verification queues, and quick administrative actions.
 */

declare(strict_types=1);

$pageTitle = 'Dashboard Overview - KaamConnect Admin';
require_once __DIR__ . '/includes/header.php';

// Fetch verification queue items
$pdo = getDBConnection();

$queueSql = "SELECT iv.*, u.full_name, u.email, u.phone, u.role, u.created_at AS reg_date,
                    (SELECT COUNT(*) FROM verification_documents vd WHERE vd.verification_id = iv.id) AS doc_count,
                    (SELECT document_type FROM verification_documents vd WHERE vd.verification_id = iv.id ORDER BY id DESC LIMIT 1) AS last_doc_type
             FROM identity_verifications iv
             JOIN users u ON iv.user_id = u.id
             WHERE iv.status IN ('pending', 'under_review', 'resubmission_required')
             ORDER BY CASE WHEN iv.status = 'under_review' THEN 1 WHEN iv.status = 'pending' THEN 2 ELSE 3 END, iv.submitted_at DESC
             LIMIT 10";
$queueItems = dbFetchAll($queueSql);

// Fetch recent user registrations
$recentUsersSql = "SELECT u.*, 
                          CASE 
                              WHEN u.role = 'worker' THEN (SELECT city FROM worker_profiles wp WHERE wp.user_id = u.id)
                              WHEN u.role = 'customer' THEN (SELECT city FROM customer_profiles cp WHERE cp.user_id = u.id)
                              ELSE 'N/A'
                          END AS user_city,
                          COALESCE(iv.status, 'pending') AS v_status
                   FROM users u
                   LEFT JOIN identity_verifications iv ON u.id = iv.user_id
                   WHERE u.role != 'admin'
                   ORDER BY u.created_at DESC
                   LIMIT 6";
$recentUsers = dbFetchAll($recentUsersSql);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h3 fw-bold mb-1 text-dark">
            <i class="fas fa-chart-line text-primary me-2"></i>Platform Dashboard
        </h2>
        <p class="text-muted small mb-0">
            Welcome back, <strong><?= e($adminUser['full_name']) ?></strong>. Live platform analytics and manual verification queue.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/admin/users.php?filter=pending') ?>" class="btn btn-warning text-dark btn-sm fw-bold shadow-sm">
            <i class="fas fa-clock me-1"></i>Review Pending (<?= $stats['pending_verifications'] ?>)
        </a>
        <a href="<?= url('/admin/users.php') ?>" class="btn btn-kc-primary btn-sm fw-bold shadow-sm">
            <i class="fas fa-user-plus me-1"></i>Manage Users
        </a>
    </div>
</div>

<!-- MODULE 3.3: 8 ATTRACTIVE STATISTIC CARDS -->
<div class="row g-3 mb-4">
    <!-- 1. Total Users -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">Total Users</span>
                <span class="p-2 bg-primary-subtle text-primary rounded-circle"><i class="fas fa-users"></i></span>
            </div>
            <div class="d-flex align-items-baseline gap-2">
                <h3 class="fw-extrabold mb-0 text-dark"><?= $stats['total_users'] ?></h3>
                <span class="small text-muted">Accounts</span>
            </div>
            <div class="mt-2 pt-2 border-top small text-muted">
                <i class="fas fa-arrow-right text-primary me-1"></i><a href="<?= url('/admin/users.php') ?>" class="text-decoration-none text-muted">View all accounts</a>
            </div>
        </div>
    </div>

    <!-- 2. Total Workers -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">Total Workers</span>
                <span class="p-2 bg-warning-subtle text-warning-emphasis rounded-circle"><i class="fas fa-user-tie"></i></span>
            </div>
            <div class="d-flex align-items-baseline gap-2">
                <h3 class="fw-extrabold mb-0 text-dark"><?= $stats['total_workers'] ?></h3>
                <span class="small text-muted">Craftsmen</span>
            </div>
            <div class="mt-2 pt-2 border-top small text-muted">
                <i class="fas fa-arrow-right text-warning me-1"></i><a href="<?= url('/admin/users.php?filter=workers') ?>" class="text-decoration-none text-muted">View worker pool</a>
            </div>
        </div>
    </div>

    <!-- 3. Total Customers -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">Total Customers</span>
                <span class="p-2 bg-success-subtle text-success rounded-circle"><i class="fas fa-user"></i></span>
            </div>
            <div class="d-flex align-items-baseline gap-2">
                <h3 class="fw-extrabold mb-0 text-dark"><?= $stats['total_customers'] ?></h3>
                <span class="small text-muted">Clients</span>
            </div>
            <div class="mt-2 pt-2 border-top small text-muted">
                <i class="fas fa-arrow-right text-success me-1"></i><a href="<?= url('/admin/users.php?filter=customers') ?>" class="text-decoration-none text-muted">View customer pool</a>
            </div>
        </div>
    </div>

    <!-- 4. Pending Verifications -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card border-warning">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">Pending Verifications</span>
                <span class="p-2 bg-warning text-dark rounded-circle"><i class="fas fa-clock"></i></span>
            </div>
            <div class="d-flex align-items-baseline gap-2">
                <h3 class="fw-extrabold mb-0 text-warning-emphasis"><?= $stats['pending_verifications'] ?></h3>
                <span class="badge bg-warning text-dark rounded-pill">Action Needed</span>
            </div>
            <div class="mt-2 pt-2 border-top small text-muted">
                <i class="fas fa-bolt text-warning me-1"></i><a href="<?= url('/admin/users.php?filter=pending') ?>" class="text-decoration-none text-warning fw-bold">Review queue</a>
            </div>
        </div>
    </div>

    <!-- 5. Under Review -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">Under Review</span>
                <span class="p-2 bg-info-subtle text-info rounded-circle"><i class="fas fa-spinner"></i></span>
            </div>
            <div class="d-flex align-items-baseline gap-2">
                <h3 class="fw-extrabold mb-0 text-dark"><?= $stats['under_review'] ?></h3>
                <span class="small text-muted">In Progress</span>
            </div>
            <div class="mt-2 pt-2 border-top small text-muted">
                <i class="fas fa-arrow-right text-info me-1"></i><a href="<?= url('/admin/users.php?filter=under_review') ?>" class="text-decoration-none text-muted">Ongoing reviews</a>
            </div>
        </div>
    </div>

    <!-- 6. Verified Users -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">Verified Users</span>
                <span class="p-2 bg-success-subtle text-success rounded-circle"><i class="fas fa-certificate"></i></span>
            </div>
            <div class="d-flex align-items-baseline gap-2">
                <h3 class="fw-extrabold mb-0 text-success"><?= $stats['verified_users'] ?></h3>
                <span class="badge bg-success-subtle text-success border border-success-subtle">Badge Active</span>
            </div>
            <div class="mt-2 pt-2 border-top small text-muted">
                <i class="fas fa-shield-check text-success me-1"></i><a href="<?= url('/admin/users.php?filter=verified') ?>" class="text-decoration-none text-muted">View verified users</a>
            </div>
        </div>
    </div>

    <!-- 7. Rejected Users -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">Rejected Users</span>
                <span class="p-2 bg-danger-subtle text-danger rounded-circle"><i class="fas fa-ban"></i></span>
            </div>
            <div class="d-flex align-items-baseline gap-2">
                <h3 class="fw-extrabold mb-0 text-danger"><?= $stats['rejected_users'] ?></h3>
                <span class="small text-muted">Failed Check</span>
            </div>
            <div class="mt-2 pt-2 border-top small text-muted">
                <i class="fas fa-arrow-right text-danger me-1"></i><a href="<?= url('/admin/users.php?filter=rejected') ?>" class="text-decoration-none text-muted">Inspect rejections</a>
            </div>
        </div>
    </div>

    <!-- 8. Suspended Accounts -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">Suspended Accounts</span>
                <span class="p-2 bg-dark-subtle text-dark rounded-circle"><i class="fas fa-user-lock"></i></span>
            </div>
            <div class="d-flex align-items-baseline gap-2">
                <h3 class="fw-extrabold mb-0 text-dark"><?= $stats['suspended_accounts'] ?></h3>
                <span class="small text-muted">Locked</span>
            </div>
            <div class="mt-2 pt-2 border-top small text-muted">
                <i class="fas fa-shield text-dark me-1"></i><a href="<?= url('/admin/users.php?filter=suspended') ?>" class="text-decoration-none text-muted">Review blocked users</a>
            </div>
        </div>
    </div>
</div>

<!-- MAIN QUEUE & USER REGISTRATION ROWS -->
<div class="row g-4 mb-4">
    <!-- Left Column: Pending Verification Queue -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="p-2 bg-warning-subtle text-warning-emphasis rounded-circle"><i class="fas fa-id-card-clip"></i></span>
                    <h5 class="fw-bold mb-0 text-dark">Identity Verification Queue</h5>
                </div>
                <a href="<?= url('/admin/users.php?filter=pending') ?>" class="btn btn-outline-primary btn-sm">
                    View All (<?= $stats['pending_verifications'] + $stats['under_review'] ?>)
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($queueItems)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-circle-check fa-3x text-success opacity-75 mb-3"></i>
                        <h6 class="fw-bold text-dark">No Pending Verifications</h6>
                        <p class="small mb-0">All submitted identity documents have been processed.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase text-muted">
                                <tr>
                                    <th class="ps-4">User</th>
                                    <th>Role</th>
                                    <th>Document</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($queueItems as $item): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="bg-light text-primary border rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px;">
                                                    <?= strtoupper(substr($item['full_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <a href="<?= url('/admin/verification-details.php?id=' . (int)$item['id']) ?>" class="fw-bold text-dark text-decoration-none d-block">
                                                        <?= e($item['full_name']) ?>
                                                    </a>
                                                    <span class="small text-muted font-monospace" style="font-size: 11px;"><?= e($item['email']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= ($item['role'] === 'worker') ? 'bg-warning-subtle text-warning-emphasis border border-warning' : 'bg-success-subtle text-success border border-success-subtle' ?> text-capitalize">
                                                <?= e($item['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="small text-muted text-uppercase fw-semibold">
                                                <i class="fas fa-file-alt text-primary me-1"></i>
                                                <?= e(str_replace('_', ' ', $item['last_doc_type'] ?? 'ID Proof')) ?>
                                            </span>
                                            <span class="badge bg-light text-secondary border small ms-1"><?= (int)$item['doc_count'] ?> file(s)</span>
                                        </td>
                                        <td>
                                            <span class="small text-muted">
                                                <?= $item['submitted_at'] ? date('d M, h:i A', strtotime($item['submitted_at'])) : 'Draft' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                                $sClass = match($item['status']) {
                                                    'verified' => 'bg-success',
                                                    'under_review' => 'bg-info text-dark',
                                                    'resubmission_required' => 'bg-warning text-dark',
                                                    'rejected' => 'bg-danger',
                                                    default => 'bg-secondary'
                                                };
                                            ?>
                                            <span class="badge <?= $sClass ?>">
                                                <?= str_replace('_', ' ', e($item['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="<?= url('/admin/verification-details.php?id=' . (int)$item['id']) ?>" class="btn btn-kc-primary btn-sm px-3 fw-bold">
                                                Review <i class="fas fa-chevron-right ms-1"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Recent Users & Platform Summary -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="p-2 bg-info-subtle text-info rounded-circle"><i class="fas fa-user-plus"></i></span>
                    <h5 class="fw-bold mb-0 text-dark">Recent Registrations</h5>
                </div>
                <a href="<?= url('/admin/users.php') ?>" class="small text-primary text-decoration-none fw-semibold">
                    View All
                </a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($recentUsers as $ru): ?>
                        <div class="list-group-item p-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <a href="<?= url('/admin/users.php?search=' . urlencode($ru['email'])) ?>" class="fw-bold text-dark text-decoration-none d-block">
                                    <?= e($ru['full_name']) ?>
                                </a>
                                <div class="small text-muted">
                                    <span class="text-capitalize badge bg-light text-dark border me-1"><?= e($ru['role']) ?></span>
                                    <i class="fas fa-location-dot text-danger me-1"></i><?= e($ru['user_city'] ?? 'India') ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge <?= ($ru['v_status'] === 'verified') ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary' ?> small">
                                    <?= e($ru['v_status']) ?>
                                </span>
                                <div class="text-muted" style="font-size: 11px;">
                                    <?= date('d M Y', strtotime($ru['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

