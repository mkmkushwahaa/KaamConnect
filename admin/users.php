<?php
/**
 * KaamConnect - Admin User Management & Verification Overview
 * Filterable directory of all platform Workers and Customers
 */

declare(strict_types=1);

$pageTitle = 'User Management - KaamConnect Admin';
require_once __DIR__ . '/includes/header.php';

$pdo = getDBConnection();

// 1. Capture Search and Filter Parameters
$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['filter'] ?? 'all');

// 2. Build Query
$where = ["u.role != 'admin'"];
$params = [];

if ($search !== '') {
    if (is_numeric($search)) {
        $where[] = "(u.id = ? OR u.phone LIKE ?)";
        $params[] = (int)$search;
        $params[] = '%' . $search . '%';
    } else {
        $where[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
}

// Role / Status Filters
match ($filter) {
    'workers' => ($where[] = "u.role = 'worker'"),
    'customers' => ($where[] = "u.role = 'customer'"),
    'pending' => ($where[] = "iv.status = 'pending'"),
    'under_review' => ($where[] = "iv.status = 'under_review'"),
    'verified' => ($where[] = "iv.status = 'verified'"),
    'rejected' => ($where[] = "iv.status = 'rejected'"),
    'resubmission' => ($where[] = "iv.status = 'resubmission_required'"),
    'suspended' => ($where[] = "u.status = 'suspended'"),
    default => null
};

$whereSql = implode(' AND ', $where);

$sql = "SELECT u.id, u.full_name, u.email, u.phone, u.role, u.status AS account_status, u.created_at AS reg_date,
               COALESCE(wp.profile_photo, cp.profile_photo) AS profile_photo,
               COALESCE(wp.city, cp.city, 'N/A') AS city,
               COALESCE(wp.locality, cp.locality, '') AS locality,
               COALESCE(iv.id, 0) AS verification_id,
               COALESCE(iv.status, 'pending') AS v_status,
               iv.submitted_at,
               (SELECT COUNT(*) FROM verification_documents vd WHERE vd.user_id = u.id) AS doc_count
        FROM users u
        LEFT JOIN worker_profiles wp ON u.id = wp.user_id AND u.role = 'worker'
        LEFT JOIN customer_profiles cp ON u.id = cp.user_id AND u.role = 'customer'
        LEFT JOIN identity_verifications iv ON u.id = iv.user_id
        WHERE {$whereSql}
        ORDER BY u.created_at DESC";

$users = dbFetchAll($sql, $params);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h3 fw-bold mb-1 text-dark">
            <i class="fas fa-users-gear text-primary me-2"></i>User & Identity Management
        </h2>
        <p class="text-muted small mb-0">
            Browse registered workers and customers, inspect verification documents, and audit platform members.
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-white text-dark border px-3 py-2 rounded-pill fw-bold">
            Showing <?= count($users) ?> User<?= count($users) === 1 ? '' : 's' ?>
        </span>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3 p-md-4">
        <!-- Filter Tabs -->
        <ul class="nav nav-pills gap-1 mb-3 pb-3 border-bottom overflow-x-auto flex-nowrap" id="userFilterTabs">
            <li class="nav-item">
                <a class="nav-link <?= ($filter === 'all') ? 'active' : '' ?>" href="<?= url('/admin/users.php') ?>">
                    All Users <span class="badge bg-white text-dark ms-1"><?= $stats['total_users'] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($filter === 'workers') ? 'active' : '' ?>" href="<?= url('/admin/users.php?filter=workers') ?>">
                    <i class="fas fa-user-tie me-1"></i>Workers <span class="badge bg-white text-dark ms-1"><?= $stats['total_workers'] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($filter === 'customers') ? 'active' : '' ?>" href="<?= url('/admin/users.php?filter=customers') ?>">
                    <i class="fas fa-user me-1"></i>Customers <span class="badge bg-white text-dark ms-1"><?= $stats['total_customers'] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($filter === 'pending') ? 'active bg-warning text-dark' : 'text-warning-emphasis' ?>" href="<?= url('/admin/users.php?filter=pending') ?>">
                    <i class="fas fa-clock me-1"></i>Pending <span class="badge bg-warning text-dark ms-1"><?= $stats['pending_verifications'] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($filter === 'under_review') ? 'active bg-info text-dark' : 'text-info' ?>" href="<?= url('/admin/users.php?filter=under_review') ?>">
                    <i class="fas fa-spinner me-1"></i>Under Review <span class="badge bg-info text-dark ms-1"><?= $stats['under_review'] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($filter === 'verified') ? 'active bg-success' : 'text-success' ?>" href="<?= url('/admin/users.php?filter=verified') ?>">
                    <i class="fas fa-certificate me-1"></i>Verified <span class="badge bg-success ms-1"><?= $stats['verified_users'] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($filter === 'rejected') ? 'active bg-danger' : 'text-danger' ?>" href="<?= url('/admin/users.php?filter=rejected') ?>">
                    <i class="fas fa-ban me-1"></i>Rejected <span class="badge bg-danger ms-1"><?= $stats['rejected_users'] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($filter === 'resubmission') ? 'active bg-warning text-dark' : '' ?>" href="<?= url('/admin/users.php?filter=resubmission') ?>">
                    <i class="fas fa-rotate-left me-1"></i>Resubmission <span class="badge bg-warning text-dark ms-1"><?= $stats['resubmission_requests'] ?></span>
                </a>
            </li>
        </ul>

        <!-- Search Form -->
        <form method="GET" action="<?= url('/admin/users.php') ?>" class="row g-2 align-items-center">
            <?php if ($filter !== 'all'): ?>
                <input type="hidden" name="filter" value="<?= e($filter) ?>">
            <?php endif; ?>
            <div class="col-md-9 col-lg-10">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by Full Name, Email, Mobile Phone, or User ID..." value="<?= e($search) ?>">
                    <?php if ($search !== ''): ?>
                        <a href="<?= url('/admin/users.php' . ($filter !== 'all' ? '?filter=' . urlencode($filter) : '')) ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-3 col-lg-2 d-grid">
                <button type="submit" class="btn btn-kc-primary fw-bold">
                    Search
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Users Table Card -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-body p-0">
        <?php if (empty($users)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-user-slash fa-3x opacity-50 mb-3"></i>
                <h5 class="fw-bold text-dark">No Users Found</h5>
                <p class="small text-muted mb-3">No registered members match your search criteria or active filter.</p>
                <a href="<?= url('/admin/users.php') ?>" class="btn btn-outline-primary btn-sm">
                    Clear Search & Filters
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th class="ps-4">User ID</th>
                            <th>Profile</th>
                            <th>Full Name</th>
                            <th>Contact Info</th>
                            <th>Account Type</th>
                            <th>City / Locality</th>
                            <th>Registered</th>
                            <th>Verification Status</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="ps-4 font-monospace fw-bold text-secondary">
                                    #<?= (int)$u['id'] ?>
                                </td>
                                <td>
                                    <?php if (!empty($u['profile_photo']) && file_exists(dirname(__DIR__) . '/' . ltrim($u['profile_photo'], '/'))): ?>
                                        <img src="<?= asset($u['profile_photo']) ?>" alt="Avatar" width="38" height="38" class="rounded-circle object-fit-cover border">
                                    <?php else: ?>
                                        <div class="bg-primary-subtle text-primary fw-bold rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; font-size: 14px;">
                                            <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark d-block"><?= e($u['full_name']) ?></span>
                                    <?php if ($u['account_status'] === 'suspended'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 10px;">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small text-muted font-monospace"><i class="fas fa-envelope text-secondary me-1"></i><?= e($u['email']) ?></div>
                                    <div class="small text-muted font-monospace"><i class="fas fa-phone text-secondary me-1"></i><?= e($u['phone']) ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= ($u['role'] === 'worker') ? 'bg-warning-subtle text-warning-emphasis border border-warning' : 'bg-success-subtle text-success border border-success-subtle' ?> text-capitalize px-2 py-1">
                                        <i class="fas <?= ($u['role'] === 'worker') ? 'fa-user-tie' : 'fa-user' ?> me-1"></i><?= e($u['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="small text-dark fw-semibold d-block"><?= e($u['city']) ?></span>
                                    <span class="small text-muted" style="font-size: 11px;"><?= e($u['locality']) ?></span>
                                </td>
                                <td>
                                    <span class="small text-muted">
                                        <?= date('d M Y', strtotime($u['reg_date'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                        $vClass = match($u['v_status']) {
                                            'verified' => 'bg-success',
                                            'under_review' => 'bg-info text-dark',
                                            'resubmission_required' => 'bg-warning text-dark',
                                            'rejected' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                    ?>
                                    <span class="badge <?= $vClass ?> text-capitalize px-2 py-1">
                                        <i class="fas <?= ($u['v_status'] === 'verified') ? 'fa-check-circle' : (($u['v_status'] === 'rejected') ? 'fa-times-circle' : 'fa-clock') ?> me-1"></i>
                                        <?= str_replace('_', ' ', e($u['v_status'])) ?>
                                    </span>
                                    <?php if ((int)$u['doc_count'] > 0): ?>
                                        <div class="small text-muted" style="font-size: 11px;">
                                            <i class="fas fa-paperclip me-1"></i><?= (int)$u['doc_count'] ?> doc(s)
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="<?= url('/admin/verification-details.php?user_id=' . (int)$u['id']) ?>" class="btn btn-outline-primary btn-sm py-1 px-3 fw-bold">
                                        View Details <i class="fas fa-arrow-right ms-1"></i>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>

