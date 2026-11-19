<?php
/**
 * KaamConnect - Verification & Document Management Service
 * Technology: PHP 8+, MySQL PDO, Secure File Validation
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Get or initialize user verification status
 */
function getUserVerification(int $userId): array {
    $sql = "SELECT iv.*, u.full_name, u.email, u.phone, u.role,
                   admin_u.full_name AS reviewer_name
            FROM identity_verifications iv
            JOIN users u ON iv.user_id = u.id
            LEFT JOIN users admin_u ON iv.reviewed_by = admin_u.id
            WHERE iv.user_id = ?
            LIMIT 1";
    $record = dbFetchOne($sql, [$userId]);

    if (!$record) {
        // Automatically initialize pending / incomplete record
        dbExecute(
            "INSERT INTO identity_verifications (user_id, status, created_at) VALUES (?, 'pending', NOW())
             ON DUPLICATE KEY UPDATE updated_at = NOW()",
            [$userId]
        );
        $record = dbFetchOne($sql, [$userId]) ?: [
            'id' => 0,
            'user_id' => $userId,
            'status' => 'pending',
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'admin_notes' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'reviewer_name' => null
        ];
    }

    return $record;
}

/**
 * Get all submitted documents for a specific user
 */
function getUserDocuments(int $userId): array {
    $sql = "SELECT * FROM verification_documents WHERE user_id = ? ORDER BY created_at DESC";
    return dbFetchAll($sql, [$userId]);
}

/**
 * Get verification history audit events for a user
 */
function getUserVerificationHistory(int $userId): array {
    $sql = "SELECT vh.*, u.full_name AS actor_name, u.role AS actor_role
            FROM verification_history vh
            JOIN users u ON vh.actor_id = u.id
            WHERE vh.user_id = ?
            ORDER BY vh.created_at DESC";
    return dbFetchAll($sql, [$userId]);
}

/**
 * Mask document reference number for privacy
 * Example: 'ABC1234567' -> 'XXXX-XXXX-4567'
 */
function maskDocumentNumber(string $rawNumber, string $docType): string {
    $clean = trim($rawNumber);
    if (strlen($clean) <= 4) {
        return 'XXXX-' . $clean;
    }
    $last4 = substr($clean, -4);
    $prefix = match ($docType) {
        'voter_id' => 'VOT',
        'driving_licence' => 'DL',
        'passport' => 'PASS',
        default => 'ID'
    };
    return $prefix . '-XXXX-' . $last4;
}

/**
 * Validate an uploaded identity document file
 * Checks: upload error, file size (<= 5MB), MIME type, extension, magic bytes
 */
function validateUploadedFile(array $file): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['valid' => false, 'error' => 'Invalid file upload parameters.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server maximum upload size.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum upload size.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary upload folder on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
        ];
        return ['valid' => false, 'error' => $messages[$file['error']] ?? 'Unknown upload error occurred.'];
    }

    // Maximum file size: 5 MB (5 * 1024 * 1024 bytes)
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'error' => 'File size exceeds maximum limit of 5 MB.'];
    }

    // Extension check
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
    if (!in_array($ext, $allowedExts, true)) {
        return ['valid' => false, 'error' => 'Invalid file extension. Only JPG, PNG, and PDF files are accepted.'];
    }

    // MIME type check via finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'application/pdf' => ['pdf']
    ];

    if (!array_key_exists($mimeType, $allowedMimes)) {
        return ['valid' => false, 'error' => 'Invalid file format. Detected type: ' . htmlspecialchars($mimeType)];
    }

    // Magic Bytes Verification
    $handle = @fopen($file['tmp_name'], 'rb');
    if (!$handle) {
        return ['valid' => false, 'error' => 'Unable to read uploaded file signature.'];
    }
    $bytes = fread($handle, 8);
    fclose($handle);

    $isSignatureValid = false;
    if ($mimeType === 'image/jpeg' && str_starts_with($bytes, "\xFF\xD8\xFF")) {
        $isSignatureValid = true;
    } elseif ($mimeType === 'image/png' && str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
        $isSignatureValid = true;
    } elseif ($mimeType === 'application/pdf' && str_starts_with($bytes, "%PDF")) {
        $isSignatureValid = true;
    }

    if (!$isSignatureValid) {
        return ['valid' => false, 'error' => 'File content signature does not match its reported format.'];
    }

    return [
        'valid' => true,
        'mime' => $mimeType,
        'ext' => $ext,
        'size' => (int)$file['size'],
        'name' => basename($file['name'])
    ];
}

/**
 * Handle submission of identity documents
 */
function submitIdentityDocument(int $userId, string $docType, string $docNumber, array $frontFile, ?array $backFile = null): array {
    $allowedTypes = ['voter_id', 'driving_licence', 'passport', 'other'];
    if (!in_array($docType, $allowedTypes, true)) {
        return ['success' => false, 'error' => 'Please select a valid identity document type.'];
    }

    if (empty(trim($docNumber))) {
        return ['success' => false, 'error' => 'Please provide the document identification number.'];
    }

    // Validate front file
    $frontCheck = validateUploadedFile($frontFile);
    if (!$frontCheck['valid']) {
        return ['success' => false, 'error' => 'Front document: ' . $frontCheck['error']];
    }

    // Validate back file if provided
    $backCheck = null;
    if ($backFile && !empty($backFile['name']) && $backFile['error'] !== UPLOAD_ERR_NO_FILE) {
        $backCheck = validateUploadedFile($backFile);
        if (!$backCheck['valid']) {
            return ['success' => false, 'error' => 'Back document: ' . $backCheck['error']];
        }
    }

    // Target storage directory (protected storage outside public root)
    $storageDir = dirname(__DIR__) . '/storage/documents';
    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0777, true);
        @file_put_contents($storageDir . '/.htaccess', "Deny from all\n");
    }

    // Generate random filenames to prevent directory traversal or overwrites
    $frontFilename = 'doc_' . bin2hex(random_bytes(16)) . '.' . $frontCheck['ext'];
    $frontDest = $storageDir . '/' . $frontFilename;

    $savedFront = is_uploaded_file($frontFile['tmp_name']) 
        ? move_uploaded_file($frontFile['tmp_name'], $frontDest) 
        : copy($frontFile['tmp_name'], $frontDest);

    if (!$savedFront) {
        return ['success' => false, 'error' => 'Failed to save front document to secure storage.'];
    }

    $backFilename = null;
    if ($backCheck) {
        $backFilename = 'doc_' . bin2hex(random_bytes(16)) . '.' . $backCheck['ext'];
        $backDest = $storageDir . '/' . $backFilename;
        $savedBack = is_uploaded_file($backFile['tmp_name']) 
            ? move_uploaded_file($backFile['tmp_name'], $backDest) 
            : copy($backFile['tmp_name'], $backDest);

        if (!$savedBack) {
            // Cleanup front file if back fails
            @unlink($frontDest);
            return ['success' => false, 'error' => 'Failed to save back document to secure storage.'];
        }
    }

    // Mask the reference
    $maskedRef = maskDocumentNumber($docNumber, $docType);

    try {
        $pdo = getDBConnection();

        // 1. Ensure verification record exists
        $verification = getUserVerification($userId);
        $verificationId = (int)$verification['id'];
        $prevStatus = $verification['status'];

        // 2. Insert into verification_documents
        $stmtDoc = $pdo->prepare(
            "INSERT INTO verification_documents 
             (verification_id, user_id, document_type, document_reference_masked, front_image_path, back_image_path, file_mime, file_size, original_filename, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmtDoc->execute([
            $verificationId,
            $userId,
            $docType,
            $maskedRef,
            $frontFilename,
            $backFilename,
            $frontCheck['mime'],
            $frontCheck['size'],
            $frontCheck['name']
        ]);

        // 3. Update identity_verifications to 'pending' with submission timestamp
        $newStatus = 'pending';
        $stmtUpdate = $pdo->prepare(
            "UPDATE identity_verifications 
             SET status = ?, submitted_at = NOW(), admin_notes = NULL, updated_at = NOW() 
             WHERE id = ?"
        );
        $stmtUpdate->execute([$newStatus, $verificationId]);

        // 4. Record audit history
        $action = ($prevStatus === 'resubmission_required') ? 'resubmitted' : 'submitted';
        $remarks = 'User submitted ' . strtoupper(str_replace('_', ' ', $docType)) . ' document for verification.';
        $stmtHist = $pdo->prepare(
            "INSERT INTO verification_history 
             (verification_id, user_id, action, actor_id, previous_status, new_status, remarks, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmtHist->execute([
            $verificationId,
            $userId,
            $action,
            $userId,
            $prevStatus,
            $newStatus,
            $remarks
        ]);

        // 5. Create in-app notifications
        createNotification(
            $userId,
            'Identity Documents Submitted',
            'Your identity documents have been submitted successfully. Our admin team will review your information. You will receive a notification upon verification.',
            ($verification['role'] === 'worker') ? url('/worker/verify.php') : url('/customer/verify.php'),
            'verification'
        );

        // Notify Admins
        $admins = dbFetchAll("SELECT id FROM users WHERE role = 'admin'");
        foreach ($admins as $adm) {
            createNotification(
                (int)$adm['id'],
                'New Identity Verification Request',
                "User (ID: {$userId}) submitted documents for identity verification.",
                url('/admin/verification-details.php?id=' . $verificationId),
                'admin'
            );
        }

        return ['success' => true, 'message' => 'Identity documents submitted successfully!'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Database error recording document: ' . $e->getMessage()];
    }
}
