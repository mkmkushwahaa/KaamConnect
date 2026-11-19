<?php
/**
 * KaamConnect - Authentication & Role-Based Authorization Guard
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Enforce that the user must be authenticated
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        $currentUri = $_SERVER['REQUEST_URI'] ?? '/index.php';
        setFlash('warning', 'Please sign in to access this page.');
        redirect('/login.php?redirect=' . urlencode($currentUri));
    }
}

/**
 * Enforce specific role-based authorization
 */
function requireRole(string|array $roles): void {
    requireLogin();

    $user = currentUser();
    $roles = (array)$roles;

    if (!in_array($user['role'], $roles, true)) {
        setFlash('danger', 'Unauthorized access! You do not have permission to view that page.');
        
        // Redirect to their respective authorized area
        if ($user['role'] === 'worker') {
            redirect('/worker/dashboard.php');
        } elseif ($user['role'] === 'customer') {
            redirect('/customer/dashboard.php');
        } elseif ($user['role'] === 'admin') {
            redirect('/admin/index.php');
        } else {
            redirect('/index.php');
        }
    }
}

function requireWorker(): void {
    requireRole('worker');
}

function requireCustomer(): void {
    requireRole('customer');
}

function requireAdmin(): void {
    requireRole('admin');
}

/**
 * Enforce that worker must be verified to perform marketplace actions (e.g. apply for jobs)
 */
function requireVerifiedWorker(): void {
    requireWorker();
    $user = currentUser();
    if (!isUserVerified((int)$user['id'])) {
        setFlash('warning', 'Marketplace action restricted! Identity verification required before you can apply for jobs or submit bids.');
        redirect('/worker/verify.php');
    }
}

/**
 * Enforce that customer must be verified to perform marketplace actions (e.g. post jobs, book workers)
 */
function requireVerifiedCustomer(): void {
    requireCustomer();
    $user = currentUser();
    if (!isUserVerified((int)$user['id'])) {
        setFlash('warning', 'Marketplace action restricted! Identity verification required before you can post jobs or book workers.');
        redirect('/customer/verify.php');
    }
}

/**
 * General verified user guard
 */
function requireVerifiedUser(): void {
    requireLogin();
    $user = currentUser();
    if ($user['role'] === 'admin') {
        return;
    }
    if ($user['role'] === 'worker') {
        requireVerifiedWorker();
    } elseif ($user['role'] === 'customer') {
        requireVerifiedCustomer();
    }
}

/**
 * If user is already authenticated, redirect to their home dashboard
 */
function redirectIfLoggedIn(): void {
    if (isLoggedIn()) {
        $user = currentUser();
        if ($user['role'] === 'worker') {
            redirect('/worker/dashboard.php');
        } elseif ($user['role'] === 'customer') {
            redirect('/customer/dashboard.php');
        } elseif ($user['role'] === 'admin') {
            redirect('/admin/index.php');
        } else {
            redirect('/index.php');
        }
    }
}

/**
 * Retrieve worker profile along with user data
 */
function getWorkerProfile(int $userId): ?array {
    $sql = "SELECT w.*, u.full_name, u.email, u.phone, u.status, u.preferred_language, c.name_en AS category_name_en, c.name_hi AS category_name_hi, c.icon AS category_icon
            FROM worker_profiles w
            JOIN users u ON w.user_id = u.id
            LEFT JOIN categories c ON w.primary_category_id = c.id
            WHERE u.id = ?";
    $profile = dbFetchOne($sql, [$userId]);
    
    // Auto-create empty profile row if user is worker but profile not yet inserted
    if (!$profile && isWorker()) {
        dbExecute(
            "INSERT INTO worker_profiles (user_id, city, locality, created_at) VALUES (?, 'Not Set', 'Not Set', NOW())",
            [$userId]
        );
        $profile = dbFetchOne($sql, [$userId]);
    }
    
    return $profile;
}

/**
 * Retrieve customer profile along with user data
 */
function getCustomerProfile(int $userId): ?array {
    $sql = "SELECT c.*, u.full_name, u.email, u.phone, u.status, u.preferred_language
            FROM customer_profiles c
            JOIN users u ON c.user_id = u.id
            WHERE u.id = ?";
    $profile = dbFetchOne($sql, [$userId]);

    // Auto-create empty profile row if user is customer but profile not yet inserted
    if (!$profile && isCustomer()) {
        dbExecute(
            "INSERT INTO customer_profiles (user_id, city, locality, created_at) VALUES (?, 'Not Set', 'Not Set', NOW())",
            [$userId]
        );
        $profile = dbFetchOne($sql, [$userId]);
    }

    return $profile;
}
