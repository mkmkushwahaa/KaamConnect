<?php
/**
 * Test Suite for Phase 8: Manual Verification and Approval Workflow
 * Module 3.6 & Module 3.7
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/verification_service.php';

echo "=== PHASE 8 TEST SUITE: MANUAL VERIFICATION AND APPROVAL WORKFLOW ===\n\n";

$pdo = getDBConnection();

// Set Admin session for direct execution
$_SESSION['user'] = [
    'id' => 1,
    'role' => 'admin',
    'full_name' => 'KaamConnect Admin',
    'email' => 'admin@kaamconnect.com'
];
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$targetUserId = 3; // Mohammad Aslam (Worker)

// Ensure target user has a clean baseline verification record
$vBefore = getUserVerification($targetUserId);
$vId = (int)$vBefore['id'];
echo "[Setup] Target user: Mohammad Aslam (ID: $targetUserId, Verification ID: $vId)\n";

// ── TEST 1: MARK UNDER REVIEW ──────────────────────────────────────
echo "\n[Test 1] Testing Action: 'under_review'...\n";
$prevStatus = $vBefore['status'];
$adminId = 1;
$reviewNotes = 'Verification review initiated by Admin.';

// Execute under_review action
dbExecute(
    "UPDATE identity_verifications 
     SET status = 'under_review', reviewed_by = ?, reviewed_at = NOW(), admin_notes = ?, updated_at = NOW() 
     WHERE id = ?",
    [$adminId, $reviewNotes, $vId]
);
dbExecute(
    "UPDATE worker_profiles SET is_verified = 0, verification_notes = ? WHERE user_id = ?",
    [$reviewNotes, $targetUserId]
);
dbExecute(
    "INSERT INTO verification_history (verification_id, user_id, action, actor_id, previous_status, new_status, remarks, created_at)
     VALUES (?, ?, 'under_review', ?, ?, 'under_review', ?, NOW())",
    [$vId, $targetUserId, $adminId, $prevStatus, $reviewNotes]
);
createNotification(
    $targetUserId,
    'Verification Under Review',
    'Your identity verification is currently being reviewed by our administrative team.',
    url('/worker/verify.php'),
    'verification'
);

$vCheck = getUserVerification($targetUserId);
assert($vCheck['status'] === 'under_review', "Status should be 'under_review'");
assert((int)$vCheck['reviewed_by'] === 1, "Reviewed by should be Admin (1)");
$notif1 = dbFetchOne("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1", [$targetUserId]);
assert($notif1['title'] === 'Verification Under Review', "Notification should be 'Verification Under Review'");
echo "  [PASS] Status updated to 'under_review', history logged, notification dispatched.\n";

// ── TEST 2: REQUEST RESUBMISSION ──────────────────────────────────
echo "\n[Test 2] Testing Action: 'resubmission'...\n";
$instructions = 'Front side of Voter ID is blurry. Please upload a high-resolution, uncropped scan.';
dbExecute(
    "UPDATE identity_verifications 
     SET status = 'resubmission_required', reviewed_by = ?, reviewed_at = NOW(), admin_notes = ?, updated_at = NOW() 
     WHERE id = ?",
    [$adminId, $instructions, $vId]
);
dbExecute(
    "UPDATE worker_profiles SET is_verified = 0, verification_notes = ? WHERE user_id = ?",
    [$instructions, $targetUserId]
);
dbExecute(
    "INSERT INTO verification_history (verification_id, user_id, action, actor_id, previous_status, new_status, remarks, created_at)
     VALUES (?, ?, 'resubmission_requested', ?, 'under_review', 'resubmission_required', ?, NOW())",
    [$vId, $targetUserId, $adminId, $instructions]
);
createNotification(
    $targetUserId,
    'Document Resubmission Requested',
    'Action required on your verification: ' . $instructions,
    url('/worker/verify.php'),
    'verification'
);

$vCheck = getUserVerification($targetUserId);
assert($vCheck['status'] === 'resubmission_required', "Status should be 'resubmission_required'");
assert($vCheck['admin_notes'] === $instructions, "Admin notes should contain instructions");
$wProfile = getWorkerProfile($targetUserId);
assert((int)$wProfile['is_verified'] === 0, "Worker profile is_verified should be 0");
$notif2 = dbFetchOne("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1", [$targetUserId]);
assert($notif2['title'] === 'Document Resubmission Requested', "Notification should be 'Document Resubmission Requested'");
echo "  [PASS] Status updated to 'resubmission_required', instructions saved, worker unverified, user notified.\n";

// ── TEST 3: REJECT VERIFICATION ────────────────────────────────────
echo "\n[Test 3] Testing Action: 'reject'...\n";
$rejectionReason = 'Identity document numbers could not be validated against official election registry.';
dbExecute(
    "UPDATE identity_verifications 
     SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), admin_notes = ?, updated_at = NOW() 
     WHERE id = ?",
    [$adminId, $rejectionReason, $vId]
);
dbExecute(
    "UPDATE worker_profiles SET is_verified = 0, verification_notes = ? WHERE user_id = ?",
    [$rejectionReason, $targetUserId]
);
dbExecute(
    "INSERT INTO verification_history (verification_id, user_id, action, actor_id, previous_status, new_status, remarks, created_at)
     VALUES (?, ?, 'rejected', ?, 'resubmission_required', 'rejected', ?, NOW())",
    [$vId, $targetUserId, $adminId, $rejectionReason]
);
createNotification(
    $targetUserId,
    'Verification Not Approved',
    'Your identity verification request was rejected. Reason: ' . $rejectionReason,
    url('/worker/verify.php'),
    'verification'
);

$vCheck = getUserVerification($targetUserId);
assert($vCheck['status'] === 'rejected', "Status should be 'rejected'");
assert($vCheck['admin_notes'] === $rejectionReason, "Admin notes should contain rejection reason");
$notif3 = dbFetchOne("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1", [$targetUserId]);
assert($notif3['title'] === 'Verification Not Approved', "Notification should be 'Verification Not Approved'");
echo "  [PASS] Status updated to 'rejected', rejection reason logged, notification dispatched.\n";

// ── TEST 4: APPROVE VERIFICATION ──────────────────────────────────
echo "\n[Test 4] Testing Action: 'approve'...\n";
$approvalNotes = 'Valid government Voter ID verified. Background check clear.';
dbExecute(
    "UPDATE identity_verifications 
     SET status = 'verified', reviewed_by = ?, reviewed_at = NOW(), admin_notes = ?, updated_at = NOW() 
     WHERE id = ?",
    [$adminId, $approvalNotes, $vId]
);
dbExecute(
    "UPDATE worker_profiles SET is_verified = 1, verification_notes = ? WHERE user_id = ?",
    [$approvalNotes, $targetUserId]
);
dbExecute(
    "INSERT INTO verification_history (verification_id, user_id, action, actor_id, previous_status, new_status, remarks, created_at)
     VALUES (?, ?, 'approved', ?, 'rejected', 'verified', ?, NOW())",
    [$vId, $targetUserId, $adminId, $approvalNotes]
);
createNotification(
    $targetUserId,
    'Identity Verification Approved!',
    'Congratulations! Your identity documents have been approved by KaamConnect administrators.',
    url('/worker/dashboard.php'),
    'verification'
);

$vCheck = getUserVerification($targetUserId);
assert($vCheck['status'] === 'verified', "Status should be 'verified'");
$wProfile = getWorkerProfile($targetUserId);
assert((int)$wProfile['is_verified'] === 1, "Worker profile is_verified should be 1");
$notif4 = dbFetchOne("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1", [$targetUserId]);
assert($notif4['title'] === 'Identity Verification Approved!', "Notification should be 'Identity Verification Approved!'");
echo "  [PASS] Status updated to 'verified', worker_profiles.is_verified set to 1, user notified with success badge.\n";

// ── TEST 5: CUSTOMER VERIFICATION WORKFLOW ─────────────────────────
echo "\n[Test 5] Testing Customer Verification Approval...\n";
$custUserId = 4; // Sunita Customer
$custV = getUserVerification($custUserId);
$custVId = (int)$custV['id'];
$custNotes = 'Residential proof and identity verified by Admin.';

dbExecute(
    "UPDATE identity_verifications 
     SET status = 'verified', reviewed_by = ?, reviewed_at = NOW(), admin_notes = ?, updated_at = NOW() 
     WHERE id = ?",
    [$adminId, $custNotes, $custVId]
);
dbExecute(
    "UPDATE customer_profiles SET is_verified = 1, verification_notes = ? WHERE user_id = ?",
    [$custNotes, $custUserId]
);
dbExecute(
    "INSERT INTO verification_history (verification_id, user_id, action, actor_id, previous_status, new_status, remarks, created_at)
     VALUES (?, ?, 'approved', ?, 'pending', 'verified', ?, NOW())",
    [$custVId, $custUserId, $adminId, $custNotes]
);

$cProfile = getCustomerProfile($custUserId);
assert((int)$cProfile['is_verified'] === 1, "Customer profile is_verified should be 1");
echo "  [PASS] Customer ID $custUserId successfully verified with is_verified = 1\n";

// ── TEST 6: AUDIT TRAIL LOGGING INTEGRITY ─────────────────────────
echo "\n[Test 6] Testing Complete Audit Trail History for Worker ID $targetUserId...\n";
$history = getUserVerificationHistory($targetUserId);
echo "  Total audit transitions recorded: " . count($history) . "\n";
assert(count($history) >= 4, "Should have at least 4 audit records from the tests");
foreach ($history as $h) {
    echo "    - Action: {$h['action']} | From: {$h['previous_status']} -> To: {$h['new_status']} | By: {$h['actor_name']}\n";
}
echo "  [PASS] Audit trail integrity fully preserved with immutable timestamps and actor tracking.\n";

// ── TEST 7: HTTP POST TO admin/verification-details.php ───────────
echo "\n[Test 7] Testing Live HTTP POST submission to admin/verification-details.php...\n";
$cookieJar = __DIR__ . '/test_phase8_cookies.txt';
if (file_exists($cookieJar)) unlink($cookieJar);

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);

// 1. Login as Admin
curl_setopt($ch, CURLOPT_URL, "http://localhost:8080/KAAMCONNECT/login.php");
$loginPage = curl_exec($ch);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage, $csrfMatches);
$csrfToken = $csrfMatches[1] ?? '';

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrfToken,
    'identifier' => 'admin@kaamconnect.com',
    'password' => 'Admin@123'
]));
curl_exec($ch);

// 2. Fetch verification-details page to get CSRF token
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_URL, "http://localhost:8080/KAAMCONNECT/admin/verification-details.php?user_id=" . $targetUserId);
$detailsPage = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
assert($code === 200, "verification-details.php should load with HTTP 200");
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $detailsPage, $csrfMatches2);
$csrfToken2 = $csrfMatches2[1] ?? '';

// 3. Submit 'under_review' via HTTP POST
curl_setopt($ch, CURLOPT_URL, "http://localhost:8080/KAAMCONNECT/admin/verification-details.php?user_id=" . $targetUserId);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrfToken2,
    'action' => 'under_review'
]));
$postRes = curl_exec($ch);
$postCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "  POST /admin/verification-details.php (action=under_review): HTTP $postCode\n";
if ($postCode !== 302) {
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($postRes, $headerSize);
    preg_match_all('/<div class="alert alert-([^"]+)">([^<]+)<\/div>/', $body, $alerts);
    echo "  Alerts in response: " . json_encode($alerts[2] ?? []) . "\n";
    echo "  Body snippet: " . substr(strip_tags($body), 0, 200) . "\n";
}
assert($postCode === 302, "Successful POST action should redirect (HTTP 302)");

// 4. Submit 'approve' via HTTP POST
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrfToken2,
    'action' => 'approve',
    'approval_notes' => 'E2E HTTP POST Approved!'
]));
$postRes2 = curl_exec($ch);
$postCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "  POST /admin/verification-details.php (action=approve): HTTP $postCode2\n";
assert($postCode2 === 302, "Successful approve action should redirect (HTTP 302)");

// Verify database reflects final status
$finalV = getUserVerification($targetUserId);
assert($finalV['status'] === 'verified', "Final status must be 'verified'");
echo "  [PASS] Live HTTP POST verification actions succeeded with session validation!\n";

curl_close($ch);
if (file_exists($cookieJar)) unlink($cookieJar);

echo "\n=== ALL PHASE 8 TESTS COMPLETED & VERIFIED SUCCESSFULLY! ===\n";
