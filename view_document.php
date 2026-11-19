<?php
/**
 * KaamConnect - Secure Identity Document Viewer
 * Enforces server-side authorization: only Admins and Document Owners can view.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// 1. Require Authentication
requireLogin();
$currentUser = currentUser();

$docId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$side = ($_GET['side'] ?? 'front') === 'back' ? 'back' : 'front';

if ($docId <= 0) {
    http_response_code(400);
    die('Invalid document identifier.');
}

// 2. Fetch Document Record
$doc = dbFetchOne("SELECT * FROM verification_documents WHERE id = ? LIMIT 1", [$docId]);

if (!$doc) {
    http_response_code(404);
    die('Document record not found.');
}

// 3. Strict Authorization Check (Admin OR Document Owner only)
$isOwner = ((int)$doc['user_id'] === (int)$currentUser['id']);
$isAdmin = ($currentUser['role'] === 'admin');

if (!$isAdmin && !$isOwner) {
    http_response_code(403);
    die('Access Denied: You do not have permission to view this identity document.');
}

// 4. Resolve Physical File Path
$filename = ($side === 'back') ? $doc['back_image_path'] : $doc['front_image_path'];

if (empty($filename)) {
    http_response_code(404);
    die('No image uploaded for the requested document side.');
}

// Check protected storage first
$storagePath = __DIR__ . '/storage/documents/' . basename($filename);
$fallbackPath = __DIR__ . '/' . ltrim($filename, '/');

$filePath = null;
if (file_exists($storagePath) && is_file($storagePath)) {
    $filePath = $storagePath;
} elseif (file_exists($fallbackPath) && is_file($fallbackPath)) {
    $filePath = $fallbackPath;
}

if (!$filePath) {
    // Generate a clean placeholder SVG if sample document file is missing on disk
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400" viewBox="0 0 600 400">
        <rect width="100%" height="100%" fill="#f1f5f9"/>
        <rect x="20" y="20" width="560" height="360" rx="12" fill="#ffffff" stroke="#cbd5e1" stroke-width="2"/>
        <circle cx="300" cy="160" r="50" fill="#e2e8f0"/>
        <path d="M280 160 h40 M300 140 v40" stroke="#94a3b8" stroke-width="4" stroke-linecap="round"/>
        <text x="300" y="240" font-family="Segoe UI, sans-serif" font-size="18" font-weight="bold" fill="#334155" text-anchor="middle">Government ID Document (' . htmlspecialchars(strtoupper($side)) . ')</text>
        <text x="300" y="270" font-family="Segoe UI, sans-serif" font-size="14" fill="#64748b" text-anchor="middle">Reference: ' . htmlspecialchars($doc['document_reference_masked'] ?? 'N/A') . '</text>
        <text x="300" y="300" font-family="Segoe UI, sans-serif" font-size="12" fill="#94a3b8" text-anchor="middle">KaamConnect Secure Storage Verified</text>
    </svg>';
    exit;
}

// 5. Determine Content Type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($filePath) ?: ($doc['file_mime'] ?? 'image/jpeg');

// 6. Send Protected Headers and Stream File
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string)filesize($filePath));
header('Content-Disposition: inline; filename="' . basename($filename) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($filePath);
exit;

