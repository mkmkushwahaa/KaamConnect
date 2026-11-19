<?php
/**
 * KaamConnect - Admin Sidebar Component
 * Renders all required sections and dynamic badge counters
 */

declare(strict_types=1);

$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
$currentFilter = $_GET['filter'] ?? '';
?>

<aside class="admin-sidebar" id="adminSidebar">
    <!-- Brand / Logo -->
    <a href="<?= url('/admin/index.php') ?>" class="admin-sidebar-brand d-flex align-items-center gap-2">
        <i class="fas fa-handshake text-warning fs-4"></i>
        <span>Kaam<span style="color: var(--kc-accent);">Connect</span> <span class="badge bg-danger ms-1" style="font-size: 10px;">ADMIN</span></span>
    </a>

    <!-- Navigation List -->
    <nav class="admin-sidebar-nav">
        <!-- Overview -->
        <div class="admin-nav-header">Core Management</div>
        <a href="<?= url('/admin/index.php') ?>" class="admin-nav-link <?= ($currentScript === 'index.php') ? 'active' : '' ?>">
            <span><i class="fas fa-chart-line nav-icon text-primary"></i>Dashboard</span>
        </a>

        <!-- User Management -->
        <div class="admin-nav-header">User & Role Management</div>
        <a href="<?= url('/admin/users.php') ?>" class="admin-nav-link <?= ($currentScript === 'users.php' && empty($currentFilter)) ? 'active' : '' ?>">
            <span><i class="fas fa-users nav-icon text-info"></i>All Users</span>
            <span class="badge bg-secondary rounded-pill" style="font-size: 11px;"><?= $stats['total_users'] ?></span>
        </a>
        <a href="<?= url('/admin/users.php?filter=workers') ?>" class="admin-nav-link <?= ($currentFilter === 'workers') ? 'active' : '' ?>">
            <span><i class="fas fa-user-tie nav-icon text-warning"></i>Workers</span>
            <span class="badge bg-dark rounded-pill" style="font-size: 11px;"><?= $stats['total_workers'] ?></span>
        </a>
        <a href="<?= url('/admin/users.php?filter=customers') ?>" class="admin-nav-link <?= ($currentFilter === 'customers') ? 'active' : '' ?>">
            <span><i class="fas fa-user nav-icon text-success"></i>Customers</span>
            <span class="badge bg-dark rounded-pill" style="font-size: 11px;"><?= $stats['total_customers'] ?></span>
        </a>

        <!-- Manual Identity Verifications -->
        <div class="admin-nav-header">Identity Verifications</div>
        <a href="<?= url('/admin/users.php?filter=pending') ?>" class="admin-nav-link <?= ($currentFilter === 'pending') ? 'active' : '' ?>">
            <span><i class="fas fa-clock nav-icon text-warning"></i>Pending Verifications</span>
            <?php if ($stats['pending_verifications'] > 0): ?>
                <span class="badge bg-warning text-dark rounded-pill fw-bold" style="font-size: 11px;"><?= $stats['pending_verifications'] ?></span>
            <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary rounded-pill" style="font-size: 11px;">0</span>
            <?php endif; ?>
        </a>
        <a href="<?= url('/admin/users.php?filter=under_review') ?>" class="admin-nav-link <?= ($currentFilter === 'under_review') ? 'active' : '' ?>">
            <span><i class="fas fa-spinner nav-icon text-info"></i>Under Review</span>
            <?php if ($stats['under_review'] > 0): ?>
                <span class="badge bg-info text-dark rounded-pill fw-bold" style="font-size: 11px;"><?= $stats['under_review'] ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= url('/admin/users.php?filter=verified') ?>" class="admin-nav-link <?= ($currentFilter === 'verified') ? 'active' : '' ?>">
            <span><i class="fas fa-certificate nav-icon text-success"></i>Verified Users</span>
            <span class="badge bg-success rounded-pill" style="font-size: 11px;"><?= $stats['verified_users'] ?></span>
        </a>
        <a href="<?= url('/admin/users.php?filter=rejected') ?>" class="admin-nav-link <?= ($currentFilter === 'rejected') ? 'active' : '' ?>">
            <span><i class="fas fa-ban nav-icon text-danger"></i>Rejected Users</span>
            <span class="badge bg-danger rounded-pill" style="font-size: 11px;"><?= $stats['rejected_users'] ?></span>
        </a>
        <a href="<?= url('/admin/users.php?filter=resubmission') ?>" class="admin-nav-link <?= ($currentFilter === 'resubmission') ? 'active' : '' ?>">
            <span><i class="fas fa-rotate-left nav-icon text-warning"></i>Resubmission Requests</span>
            <?php if ($stats['resubmission_requests'] > 0): ?>
                <span class="badge bg-warning text-dark rounded-pill fw-bold" style="font-size: 11px;"><?= $stats['resubmission_requests'] ?></span>
            <?php endif; ?>
        </a>

        <!-- Platform Operations -->
        <div class="admin-nav-header">Platform Operations</div>
        <a href="<?= url('/admin/jobs.php') ?>" class="admin-nav-link <?= ($currentScript === 'jobs.php') ? 'active' : '' ?>">
            <span><i class="fas fa-briefcase nav-icon text-secondary"></i>Jobs Management</span>
            <span class="badge bg-dark rounded-pill" style="font-size: 11px;"><?= $stats['total_jobs'] ?></span>
        </a>
        <a href="<?= url('/admin/categories.php') ?>" class="admin-nav-link <?= ($currentScript === 'categories.php') ? 'active' : '' ?>">
            <span><i class="fas fa-tags nav-icon text-secondary"></i>Categories</span>
            <span class="badge bg-dark rounded-pill" style="font-size: 11px;"><?= $stats['total_categories'] ?></span>
        </a>
        <a href="<?= url('/admin/reviews.php') ?>" class="admin-nav-link <?= ($currentScript === 'reviews.php') ? 'active' : '' ?>">
            <span><i class="fas fa-star nav-icon text-secondary"></i>Reviews</span>
            <span class="badge bg-dark rounded-pill" style="font-size: 11px;"><?= $stats['total_reviews'] ?></span>
        </a>
        <a href="<?= url('/admin/notifications.php') ?>" class="admin-nav-link <?= ($currentScript === 'notifications.php') ? 'active' : '' ?>">
            <span><i class="fas fa-bell nav-icon text-secondary"></i>Notifications</span>
            <?php if ($adminUnreadNotifs > 0): ?>
                <span class="badge bg-danger rounded-pill" style="font-size: 11px;"><?= $adminUnreadNotifs ?></span>
            <?php endif; ?>
        </a>

        <!-- Settings & Session -->
        <div class="admin-nav-header">System</div>
        <a href="<?= url('/admin/settings.php') ?>" class="admin-nav-link <?= ($currentScript === 'settings.php') ? 'active' : '' ?>">
            <span><i class="fas fa-gear nav-icon text-secondary"></i>Admin Settings</span>
        </a>
        <a href="<?= url('/logout.php') ?>" class="admin-nav-link text-danger">
            <span><i class="fas fa-sign-out-alt nav-icon text-danger"></i>Logout</span>
        </a>
    </nav>
</aside>

