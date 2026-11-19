<?php
/**
 * KaamConnect - Admin Authentication Guard & Helper Utilities
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/verification_service.php';

// Strict server-side authorization check: Only users with role = 'admin' are permitted
requireAdmin();

$adminUser = currentUser();

/**
 * Get aggregated statistics for the admin dashboard
 */
function getAdminDashboardStats(): array {
    $pdo = getDBConnection();

    // Total Users
    $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();
    $totalWorkers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'worker'")->fetchColumn();
    $totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();

    // Verifications Breakdown
    $pendingVerifications = (int)$pdo->query("SELECT COUNT(*) FROM identity_verifications WHERE status = 'pending'")->fetchColumn();
    $underReview = (int)$pdo->query("SELECT COUNT(*) FROM identity_verifications WHERE status = 'under_review'")->fetchColumn();
    $verifiedUsers = (int)$pdo->query("SELECT COUNT(*) FROM identity_verifications WHERE status = 'verified'")->fetchColumn();
    $rejectedUsers = (int)$pdo->query("SELECT COUNT(*) FROM identity_verifications WHERE status = 'rejected'")->fetchColumn();
    $resubmissionRequests = (int)$pdo->query("SELECT COUNT(*) FROM identity_verifications WHERE status = 'resubmission_required'")->fetchColumn();

    // Suspended accounts
    $suspendedAccounts = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'suspended'")->fetchColumn();

    // Marketplace Stats
    $totalJobs = (int)$pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
    $totalCategories = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $totalReviews = (int)$pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();

    return [
        'total_users' => $totalUsers,
        'total_workers' => $totalWorkers,
        'total_customers' => $totalCustomers,
        'pending_verifications' => $pendingVerifications,
        'under_review' => $underReview,
        'verified_users' => $verifiedUsers,
        'rejected_users' => $rejectedUsers,
        'resubmission_requests' => $resubmissionRequests,
        'suspended_accounts' => $suspendedAccounts,
        'total_jobs' => $totalJobs,
        'total_categories' => $totalCategories,
        'total_reviews' => $totalReviews
    ];
}

