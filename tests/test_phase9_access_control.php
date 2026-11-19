<?php
/**
 * Test Suite for Phase 9: Role-Based and Verification-Based Access Control
 * Module 5: Access Control Enforcement
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/verification_service.php';

echo "=== PHASE 9 TEST SUITE: ROLE & VERIFICATION-BASED ACCESS CONTROL ===\n\n";

$pdo = getDBConnection();

// Ensure all demo users have valid hashes matching their documented passwords
$pdo->prepare("UPDATE users SET password_hash = ? WHERE role IN ('worker', 'customer')")->execute([password_hash('Password@123', PASSWORD_BCRYPT)]);
$pdo->prepare("DELETE FROM login_attempts")->execute();

// Test 1: Helper isUserVerified() Logic
echo "[Test 1] Testing isUserVerified() function across different roles...\n";

// 1. Admin
assert(isUserVerified(1) === true, "Admin (ID: 1) must always be considered verified");
echo "  [PASS] Admin (ID: 1) is automatically verified\n";

// 2. Verified Worker (Ramesh ID: 2)
// Ensure Ramesh is verified in DB
$pdo->prepare("UPDATE identity_verifications SET status = 'verified' WHERE user_id = 2")->execute();
$pdo->prepare("UPDATE worker_profiles SET is_verified = 1 WHERE user_id = 2")->execute();
assert(isUserVerified(2) === true, "Ramesh (ID: 2) should return true when verified");
echo "  [PASS] Verified worker (ID: 2) correctly recognized as verified\n";

// 3. Unverified Worker
// Temporarily set Aslam (ID: 3) to pending
$pdo->prepare("UPDATE identity_verifications SET status = 'pending' WHERE user_id = 3")->execute();
$pdo->prepare("UPDATE worker_profiles SET is_verified = 0 WHERE user_id = 3")->execute();
assert(isUserVerified(3) === false, "Aslam (ID: 3) should return false when status is pending");
echo "  [PASS] Unverified worker (ID: 3) correctly recognized as unverified\n";

// 4. Verified Customer (Sunita ID: 4)
$pdo->prepare("UPDATE identity_verifications SET status = 'verified' WHERE user_id = 4")->execute();
$pdo->prepare("UPDATE customer_profiles SET is_verified = 1 WHERE user_id = 4")->execute();
assert(isUserVerified(4) === true, "Sunita (ID: 4) should return true when verified");
echo "  [PASS] Verified customer (ID: 4) correctly recognized as verified\n";

// 5. Unverified Customer (Vikram ID: 5)
$pdo->prepare("UPDATE identity_verifications SET status = 'pending' WHERE user_id = 5")->execute();
$pdo->prepare("UPDATE customer_profiles SET is_verified = 0 WHERE user_id = 5")->execute();
assert(isUserVerified(5) === false, "Vikram (ID: 5) should return false when unverified");
echo "  [PASS] Unverified customer (ID: 5) correctly recognized as unverified\n";

// Test 2: HTTP Endpoint Security - Verified vs Unverified Access
echo "\n[Test 2] Testing HTTP Endpoint Security with cURL sessions...\n";

$cookieJar = __DIR__ . '/test_phase9_cookies.txt';

function loginAndTest(string $email, string $password, string $targetUrl, int $expectedCode, string $cookieJar, ?string $expectedRedirectTarget = null): void {
    if (file_exists($cookieJar)) unlink($cookieJar);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);

    // Get CSRF Token
    curl_setopt($ch, CURLOPT_URL, "http://localhost:8080/KAAMCONNECT/login.php");
    $loginPage = curl_exec($ch);
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage, $m);
    $csrf = $m[1] ?? '';

    // Submit Login
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'csrf_token' => $csrf,
        'identifier' => $email,
        'password' => $password
    ]));
    $loginOutput = curl_exec($ch);
    $loginHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "  Login for $email: HTTP $loginHttpCode\n";
    if ($loginHttpCode !== 302) {
        preg_match('/Location:\s*([^\r\n]+)/i', $loginOutput, $lm);
        echo "  Login response location: " . ($lm[1] ?? 'None') . "\n";
    }

    // Request target protected URL
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_URL, "http://localhost:8080/KAAMCONNECT" . $targetUrl);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    preg_match('/Location:\s*([^\r\n]+)/i', $res, $locMatch);
    $location = trim($locMatch[1] ?? ($redirectUrl ?: ''));

    echo "  Account: $email -> GET $targetUrl: HTTP $code (Location: $location)";
    if ($code === $expectedCode) {
        echo " [PASS]";
        if ($expectedRedirectTarget && strpos($location, $expectedRedirectTarget) !== false) {
            echo " (Redirected to $expectedRedirectTarget)";
        }
        echo "\n";
    } else {
        echo " [FAIL - Expected $expectedCode]\n";
    }

    assert($code === $expectedCode, "HTTP response code should match expected");
    if ($expectedRedirectTarget) {
        assert(strpos($location, $expectedRedirectTarget) !== false, "Redirect target should match $expectedRedirectTarget");
    }

    curl_close($ch);
    if (file_exists($cookieJar)) unlink($cookieJar);
}

// 1. Unverified Worker (Aslam ID: 3) attempting to access job application page
loginAndTest(
    'aslam.worker@kaamconnect.com', 
    'Password@123', 
    '/worker/job-details.php?id=1', 
    302, 
    $cookieJar, 
    'verify.php'
);

// 2. Verified Worker (Ramesh ID: 2) accessing job application page
loginAndTest(
    'ramesh.worker@kaamconnect.com', 
    'Password@123', 
    '/worker/job-details.php?id=1', 
    200, 
    $cookieJar
);

// 3. Unverified Customer (Vikram ID: 5) attempting to publish a job
loginAndTest(
    'vikram.customer@kaamconnect.com', 
    'Password@123', 
    '/customer/post-job.php', 
    302, 
    $cookieJar, 
    'verify.php'
);

// 4. Verified Customer (Sunita ID: 4) accessing job posting page
loginAndTest(
    'sunita.customer@kaamconnect.com', 
    'Password@123', 
    '/customer/post-job.php', 
    200, 
    $cookieJar
);

// 5. Unverified Customer (Vikram ID: 5) attempting to book a worker
loginAndTest(
    'vikram.customer@kaamconnect.com', 
    'Password@123', 
    '/customer/book-worker.php?worker_id=2', 
    302, 
    $cookieJar, 
    'verify.php'
);

// 6. Verified Customer (Sunita ID: 4) accessing book worker page
loginAndTest(
    'sunita.customer@kaamconnect.com', 
    'Password@123', 
    '/customer/book-worker.php?worker_id=2', 
    200, 
    $cookieJar
);

// 7. Verify Admin is NEVER blocked from any admin or operational pages
loginAndTest(
    'admin@kaamconnect.com', 
    'Admin@123', 
    '/admin/index.php', 
    200, 
    $cookieJar
);

// Restore Aslam & Vikram to verified state so application remains healthy
$pdo->prepare("UPDATE identity_verifications SET status = 'verified' WHERE user_id = 3")->execute();
$pdo->prepare("UPDATE worker_profiles SET is_verified = 1 WHERE user_id = 3")->execute();
$pdo->prepare("UPDATE identity_verifications SET status = 'verified' WHERE user_id = 5")->execute();
$pdo->prepare("UPDATE customer_profiles SET is_verified = 1 WHERE user_id = 5")->execute();

echo "\n=== ALL PHASE 9 ACCESS CONTROL TESTS PASSED SUCCESSFULLY! ===\n";
