<?php
/**
 * KaamConnect - Admin Console Header
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? 'Admin Dashboard - KaamConnect';
$stats = getAdminDashboardStats();
$adminUnreadNotifs = getUnreadNotificationCount((int)$adminUser['id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>

    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Font Awesome 6.5.2 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Leaflet 1.9.4 CSS for Coordinates Map Preview -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />

    <!-- KaamConnect Custom Stylesheet -->
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">

    <style>
        :root {
            --admin-sidebar-width: 270px;
            --admin-header-height: 64px;
        }
        body {
            background-color: #f1f5f9;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        .admin-sidebar {
            width: var(--admin-sidebar-width);
            background: #0f172a;
            color: #94a3b8;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            z-index: 1000;
        }
        .admin-sidebar-brand {
            height: var(--admin-header-height);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            color: #ffffff;
            font-weight: 700;
            font-size: 1.15rem;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            text-decoration: none;
        }
        .admin-sidebar-nav {
            padding: 1rem 0;
            flex-grow: 1;
            overflow-y: auto;
        }
        .admin-nav-header {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            padding: 0.75rem 1.5rem 0.25rem;
            font-weight: 700;
        }
        .admin-nav-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.65rem 1.5rem;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.15s ease-in-out;
            border-left: 3px solid transparent;
        }
        .admin-nav-link:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.05);
        }
        .admin-nav-link.active {
            color: #ffffff;
            background: #1e293b;
            border-left-color: var(--kc-primary);
        }
        .admin-nav-link i.nav-icon {
            width: 22px;
            font-size: 1rem;
            margin-right: 0.75rem;
        }
        .admin-main {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .admin-topbar {
            height: var(--admin-header-height);
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 900;
        }
        .admin-content {
            padding: 1.75rem;
            flex-grow: 1;
        }
        .stat-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            border: 1px solid #e2e8f0;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.06);
        }
        @media (max-width: 991.98px) {
            .admin-sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: -270px;
            }
            .admin-sidebar.show {
                left: 0;
            }
        }
    </style>
</head>
<body>

<div class="admin-wrapper">
    <!-- Sidebar Included -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="admin-main">
        <!-- Topbar Header -->
        <header class="admin-topbar shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-light border d-lg-none" type="button" id="sidebarToggleBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="d-none d-sm-block">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 small fw-bold">
                        <i class="fas fa-shield-halved me-1"></i>Antigravity Security RBAC: Administrator
                    </span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <!-- Public Site Link -->
                <a href="<?= url('/index.php') ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="View Public Marketplace">
                    <i class="fas fa-external-link-alt me-1"></i>View Site
                </a>

                <!-- Admin User Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2 border py-1 px-2 rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 12px; font-weight: bold;">
                            AD
                        </div>
                        <span class="fw-semibold small d-none d-md-inline"><?= e($adminUser['full_name']) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius: 10px;">
                        <li class="px-3 py-2 border-bottom">
                            <div class="fw-bold text-dark"><?= e($adminUser['full_name']) ?></div>
                            <div class="small text-muted font-monospace" style="font-size: 11px;"><?= e($adminUser['email']) ?></div>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="<?= url('/admin/index.php') ?>">
                                <i class="fas fa-tachometer-alt me-2 text-primary"></i>Admin Dashboard
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="<?= url('/admin/users.php') ?>">
                                <i class="fas fa-users-gear me-2 text-info"></i>Manage Users
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item py-2 text-danger" href="<?= url('/logout.php') ?>">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Dynamic Flash Alerts -->
        <?php $flash = getFlash(); ?>
        <?php if ($flash): ?>
            <div class="p-3 pb-0">
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm mb-0" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Page Main Container Starts -->
        <main class="admin-content">

