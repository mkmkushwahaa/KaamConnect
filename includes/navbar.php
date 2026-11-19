<?php
/**
 * KaamConnect - Global Top Navigation Bar
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$currentLang = getCurrentLanguage();
$user = currentUser();
$unreadNotifCount = $user ? getUnreadNotificationCount((int)$user['id']) : 0;
$unreadMsgCount = $user ? getUnreadMessageCount((int)$user['id']) : 0;

// Determine Dashboard URI based on active role
$dashboardUri = url('/index.php');
if ($user) {
    if ($user['role'] === 'worker') {
        $dashboardUri = url('/worker/dashboard.php');
    } elseif ($user['role'] === 'customer') {
        $dashboardUri = url('/customer/dashboard.php');
    } elseif ($user['role'] === 'admin') {
        $dashboardUri = url('/admin/index.php');
    }
}
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>

<nav class="navbar navbar-expand-lg kc-navbar sticky-top">
    <div class="container">
        <!-- Brand Logo -->
        <a class="navbar-brand" href="<?= url('/index.php') ?>">
            <i class="fas fa-handshake"></i>
            <span>Kaam<span style="color: var(--kc-accent);">Connect</span></span>
        </a>

        <!-- Mobile Hamburger Toggle -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#kcNavbarContent" aria-controls="kcNavbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="kcNavbarContent">
            <!-- Left Public Navigation Links -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage === 'index.php') ? 'active' : '' ?>" href="<?= url('/index.php') ?>">
                        <i class="fas fa-home me-1 text-muted"></i><?= __('nav_home') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage === 'workers.php') ? 'active' : '' ?>" href="<?= url('/workers.php') ?>">
                        <i class="fas fa-user-tie me-1 text-muted"></i><?= __('nav_find_workers') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage === 'jobs.php') ? 'active' : '' ?>" href="<?= url('/jobs.php') ?>">
                        <i class="fas fa-briefcase me-1 text-muted"></i><?= __('nav_find_jobs') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage === 'about.php') ? 'active' : '' ?>" href="<?= url('/about.php') ?>"><?= __('nav_about') ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage === 'contact.php') ? 'active' : '' ?>" href="<?= url('/contact.php') ?>"><?= __('nav_contact') ?></a>
                </li>
            </ul>

            <!-- Right Actions: Language Switcher & Authentication -->
            <div class="d-flex align-items-center gap-3">
                <!-- Language Selector Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-language fs-6 text-primary"></i>
                        <span><?= ($currentLang === 'hi') ? 'हिंदी' : 'English' ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li>
                            <a class="dropdown-item d-flex align-items-center justify-content-between <?= ($currentLang === 'en') ? 'active' : '' ?>" href="?lang=en">
                                <span>English</span>
                                <?php if ($currentLang === 'en'): ?><i class="fas fa-check small"></i><?php endif; ?>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center justify-content-between <?= ($currentLang === 'hi') ? 'active' : '' ?>" href="?lang=hi">
                                <span>हिंदी (Hindi)</span>
                                <?php if ($currentLang === 'hi'): ?><i class="fas fa-check small"></i><?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </div>

                <?php if ($user): ?>
                    <!-- Authenticated User Menu -->

                    <!-- Notifications Link -->
                    <a href="<?= ($user['role'] === 'worker') ? url('/worker/notifications.php') : url('/customer/notifications.php') ?>" class="btn btn-light position-relative p-2 rounded-circle" title="<?= __('nav_notifications') ?>">
                        <i class="fas fa-bell text-secondary"></i>
                        <?php if ($unreadNotifCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= $unreadNotifCount ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <!-- Messages Link -->
                    <a href="<?= ($user['role'] === 'worker') ? url('/worker/messages.php') : url('/customer/messages.php') ?>" class="btn btn-light position-relative p-2 rounded-circle" title="<?= __('nav_messages') ?>">
                        <i class="fas fa-comment-dots text-secondary"></i>
                        <?php if ($unreadMsgCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary">
                                <?= $unreadMsgCount ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <!-- User Profile Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2 border py-1 px-2 rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?= asset('assets/images/default-avatar.svg') ?>" alt="Avatar" width="28" height="28" class="rounded-circle">
                            <span class="fw-semibold text-truncate d-none d-md-inline" style="max-width: 130px;"><?= e($user['full_name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li class="px-3 py-2 border-bottom">
                                <div class="fw-bold text-dark"><?= e($user['full_name']) ?></div>
                                <div class="small text-muted text-capitalize"><i class="fas fa-shield-alt text-primary me-1"></i><?= e($user['role']) ?></div>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="<?= e($dashboardUri) ?>">
                                    <i class="fas fa-tachometer-alt me-2 text-primary"></i><?= __('nav_dashboard') ?>
                                </a>
                            </li>
                            <?php if ($user['role'] === 'worker'): ?>
                                <li>
                                    <a class="dropdown-item py-2" href="<?= url('/worker/profile.php') ?>">
                                        <i class="fas fa-id-card me-2 text-info"></i><?= __('nav_profile') ?>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2" href="<?= url('/worker/my-applications.php') ?>">
                                        <i class="fas fa-file-contract me-2 text-success"></i><?= __('nav_my_applications') ?>
                                    </a>
                                </li>
                            <?php elseif ($user['role'] === 'customer'): ?>
                                <li>
                                    <a class="dropdown-item py-2" href="<?= url('/customer/post-job.php') ?>">
                                        <i class="fas fa-plus-circle me-2 text-success"></i><?= __('nav_post_job') ?>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2" href="<?= url('/customer/manage-jobs.php') ?>">
                                        <i class="fas fa-tasks me-2 text-warning"></i><?= __('nav_manage_jobs') ?>
                                    </a>
                                </li>
                            <?php elseif ($user['role'] === 'admin'): ?>
                                <li>
                                    <a class="dropdown-item py-2" href="<?= url('/admin/index.php') ?>">
                                        <i class="fas fa-cogs me-2 text-danger"></i><?= __('nav_admin_panel') ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item py-2 text-danger" href="<?= url('/logout.php') ?>">
                                    <i class="fas fa-sign-out-alt me-2"></i><?= __('nav_logout') ?>
                                </a>
                            </li>
                        </ul>
                    </div>

                <?php else: ?>
                    <!-- Guest Actions -->
                    <a href="<?= url('/login.php') ?>" class="btn btn-kc-outline btn-sm">
                        <i class="fas fa-sign-in-alt me-1"></i><?= __('nav_login') ?>
                    </a>
                    <a href="<?= url('/register.php') ?>" class="btn btn-kc-primary btn-sm">
                        <i class="fas fa-user-plus me-1"></i><?= __('nav_register') ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
