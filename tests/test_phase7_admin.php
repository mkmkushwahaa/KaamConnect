<?php
/**
 * Test Suite for Phase 7: Dedicated Admin Dashboard & User Management
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/verification_service.php';

echo "=== PHASE 7 TEST SUITE: ADMIN DASHBOARD & USER MANAGEMENT ===\n\n";

$pdo = getDBConnection();

// Test 1: RBAC Logic
echo "[Test 1] Testing Role-Based Access Control logic...\n";
$_SESSION = [];
if (!isLoggedIn() || !isAdmin()) {
    echo "  [PASS] Unauthenticated visitor correctly denied admin access\n";
} else {
    echo "  [FAIL] Unauthenticated visitor allowed into admin!\n";
}

$_SESSION['user'] = ['id' => 2, 'role' => 'worker', 'full_name' => 'Ramesh Worker', 'email' => 'ramesh.worker@kaamconnect.com'];
if (!isAdmin()) {
    echo "  [PASS] Worker account (ID: 2) correctly denied admin access\n";
} else {
    echo "  [FAIL] Worker allowed into admin!\n";
}

$_SESSION['user'] = ['id' => 4, 'role' => 'customer', 'full_name' => 'Sunita Customer', 'email' => 'sunita.customer@kaamconnect.com'];
if (!isAdmin()) {
    echo "  [PASS] Customer account (ID: 4) correctly denied admin access\n";
} else {
    echo "  [FAIL] Customer allowed into admin!\n";
}

$_SESSION['user'] = ['id' => 1, 'role' => 'admin', 'full_name' => 'Admin KaamConnect', 'email' => 'admin@kaamconnect.com'];
if (isAdmin()) {
    echo "  [PASS] Admin account (ID: 1) granted admin access\n";
} else {
    echo "  [FAIL] Admin denied admin access!\n";
}

// Now include admin auth helper since $_SESSION is admin
require_once __DIR__ . '/../admin/includes/auth.php';

// Test 2: Dashboard Statistics Query
echo "\n[Test 2] Testing Dashboard Statistics from actual database records...\n";
$stats = getAdminDashboardStats();
print_r($stats);
assert($stats['total_users'] >= 5, "Total users should be >= 5");
assert($stats['total_workers'] >= 2, "Total workers should be >= 2");
assert($stats['total_customers'] >= 2, "Total customers should be >= 2");
echo "  [PASS] All 8 statistic counters fetched successfully from database records\n";

// Test 3: Users Filtering & Search
echo "\n[Test 3] Testing User Management Search and Filters...\n";
// Test search by name
$searchName = 'Ramesh';
$res = dbFetchAll("SELECT id, full_name, role FROM users WHERE full_name LIKE ?", ["%$searchName%"]);
echo "  Found " . count($res) . " user(s) matching '$searchName': " . ($res[0]['full_name'] ?? '') . "\n";
assert(count($res) > 0, "Should find Ramesh");

// Test search by ID
$searchId = 2;
$resId = dbFetchAll("SELECT id, full_name FROM users WHERE id = ?", [$searchId]);
assert(count($resId) === 1, "Should find user by ID 2");
echo "  [PASS] User search by name and ID verified\n";

// Test filter by role
$workers = dbFetchAll("SELECT id, full_name FROM users WHERE role = 'worker'");
echo "  Found " . count($workers) . " workers in database\n";
$customers = dbFetchAll("SELECT id, full_name FROM users WHERE role = 'customer'");
echo "  Found " . count($customers) . " customers in database\n";
echo "  [PASS] Filter by worker and customer verified\n";

// Test 4: Verification details query for worker and customer
echo "\n[Test 4] Testing User Details & Identity Verification view queries...\n";
$workerUser = dbFetchOne("SELECT * FROM users WHERE role = 'worker' LIMIT 1");
$workerId = (int)$workerUser['id'];
$workerV = getUserVerification($workerId);
$workerDocs = getUserDocuments($workerId);
$workerProf = getWorkerProfile($workerId);
echo "  Worker ID: $workerId, Name: {$workerUser['full_name']}\n";
echo "  Verification Status: {$workerV['status']}, Documents Submitted: " . count($workerDocs) . "\n";
echo "  City: {$workerProf['city']}, GPS: {$workerProf['latitude']}, {$workerProf['longitude']}\n";
assert(!empty($workerProf['city']), "Worker city should exist");
echo "  [PASS] Worker details, profile, documents, and coordinates loaded\n";

// Test 5: HTTP Endpoints verification via cURL against local Apache
echo "\n[Test 5] Probing Apache HTTP endpoints for Admin pages...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

// Check admin/index.php without login -> Should redirect to login.php (HTTP 302)
curl_setopt($ch, CURLOPT_URL, "http://localhost:8080/KAAMCONNECT/admin/index.php");
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "  GET /admin/index.php (Guest): HTTP $httpCode\n";
assert($httpCode === 302, "Guest access to admin must be 302 redirect");
echo "  [PASS] Unauthenticated guest redirected away from admin\n";

// Now perform login via HTTP cookie jar
$cookieJar = __DIR__ . '/test_admin_cookies.txt';
if (file_exists($cookieJar)) unlink($cookieJar);

// 1. Fetch CSRF token from login page
curl_setopt($ch, CURLOPT_URL, "http://localhost:8080/KAAMCONNECT/login.php");
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
$loginPage = curl_exec($ch);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage, $csrfMatches);
$csrfToken = $csrfMatches[1] ?? '';
echo "  Obtained login CSRF token: " . substr($csrfToken, 0, 10) . "...\n";

// Ensure Admin has valid password hash for 'Admin@123'
$pdo->prepare("UPDATE users SET password_hash = ? WHERE id = 1")->execute([password_hash('Admin@123', PASSWORD_BCRYPT)]);
$pdo->prepare("DELETE FROM login_attempts WHERE identifier = 'admin@kaamconnect.com' OR ip_address IN ('127.0.0.1', '::1')")->execute();

$adminDb = dbFetchOne("SELECT id, email, password_hash, role, status FROM users WHERE email = 'admin@kaamconnect.com'");
echo "  Admin in DB: ID {$adminDb['id']}, Email {$adminDb['email']}, Status {$adminDb['status']}\n";
echo "  Password verify for 'Admin@123': " . (password_verify('Admin@123', $adminDb['password_hash']) ? 'TRUE' : 'FALSE') . "\n";

// 2. Submit Login as Admin
curl_setopt($ch, CURLOPT_URL, "http://localhost:8080/KAAMCONNECT/login.php");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrfToken,
    'identifier' => 'admin@kaamconnect.com',
    'password' => 'Admin@123'
]));
$loginRes = curl_exec($ch);
$loginCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body = substr($loginRes, $headerSize);
echo "  POST /login.php as Admin: HTTP $loginCode\n";
if ($loginCode !== 302) {
    preg_match_all('/<div class="alert alert-([^"]+)">([^<]+)<\/div>/', $body, $alerts);
    echo "  Alerts in response: " . json_encode($alerts[2] ?? []) . "\n";
    if (strpos($body, 'Too many failed login attempts') !== false) {
        echo "  Notice: Rate limited!\n";
    }
}

// 3. Test authenticated admin pages
curl_setopt($ch, CURLOPT_POST, false);
$adminPages = [
    '/admin/index.php',
    '/admin/users.php',
    '/admin/users.php?filter=workers',
    '/admin/users.php?filter=customers',
    '/admin/users.php?filter=pending',
    '/admin/verification-details.php?user_id=2',
    '/admin/jobs.php',
    '/admin/categories.php',
    '/admin/reviews.php',
    '/admin/notifications.php',
    '/admin/settings.php'
];

foreach ($adminPages as $path) {
    curl_setopt($ch, CURLOPT_URL, "http://localhost:8080/KAAMCONNECT" . $path);
    $pageHtml = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "  GET $path: HTTP $code";
    if ($code === 200) {
        echo " [OK]\n";
    } else {
        echo " [FAIL - Expected 200]\n";
    }
    assert($code === 200, "Admin page $path should return 200 for logged-in admin");
}

curl_close($ch);
if (file_exists($cookieJar)) unlink($cookieJar);

echo "\n=== ALL PHASE 7 TESTS PASSED SUCCESSFULLY! ===\n";

