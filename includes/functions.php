<?php
/**
 * KaamConnect - Core Global Functions & Security Library
 * PHP 8+ compatible
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session cookie params before starting session
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    session_set_cookie_params($cookieParams);
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * -------------------------------------------------------------
 * Bilingual Language System (English & Hindi)
 * -------------------------------------------------------------
 */

/**
 * Load translation strings for active language
 */
function getTranslations(): array {
    static $translations = null;

    if ($translations === null) {
        $lang = getCurrentLanguage();
        $langFile = __DIR__ . "/languages/{$lang}.php";
        
        if (file_exists($langFile)) {
            $translations = require $langFile;
        } else {
            $translations = require __DIR__ . "/languages/en.php";
        }
    }

    return $translations;
}

/**
 * Get current active language code ('en' or 'hi')
 */
function getCurrentLanguage(): string {
    // Check URL override
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'hi'], true)) {
        $_SESSION['lang'] = $_GET['lang'];
        
        // If user logged in, persist to database
        if (isLoggedIn()) {
            try {
                dbExecute("UPDATE users SET preferred_language = ? WHERE id = ?", [$_GET['lang'], currentUser()['id']]);
                $_SESSION['user']['preferred_language'] = $_GET['lang'];
            } catch (Exception $e) {
                // Ignore silently
            }
        }
    }

    if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], ['en', 'hi'], true)) {
        return $_SESSION['lang'];
    }

    if (isLoggedIn() && !empty($_SESSION['user']['preferred_language'])) {
        return $_SESSION['user']['preferred_language'];
    }

    return 'en';
}

/**
 * Translate a language key
 */
function __(string $key, ?string $default = null): string {
    $translations = getTranslations();
    if (isset($translations[$key])) {
        return $translations[$key];
    }
    return $default ?? $key;
}

/**
 * -------------------------------------------------------------
 * Security, Sanitization & CSRF
 * -------------------------------------------------------------
 */

/**
 * Escape HTML output to prevent Cross-Site Scripting (XSS)
 */
function e(string|int|float|null $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Generate or retrieve CSRF token
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generate CSRF hidden input field
 */
function csrfField(): string {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Verify CSRF token from POST request
 */
function verifyCsrfToken(?string $token = null): bool {
    $token = $token ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Record a login attempt for rate limiting
 */
function recordLoginAttempt(string $identifier, bool $isSuccess): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    try {
        dbExecute(
            "INSERT INTO login_attempts (ip_address, identifier, is_success, attempted_at) VALUES (?, ?, ?, NOW())",
            [$ip, $identifier, $isSuccess ? 1 : 0]
        );
    } catch (Exception $e) {
        // Fallback
    }
}

/**
 * Check if IP or account is currently rate-limited (Max 5 failed attempts in 15 mins)
 */
function isRateLimited(string $identifier, int $maxAttempts = 5, int $decayMinutes = 15): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    try {
        $row = dbFetchOne(
            "SELECT COUNT(*) AS failed_count FROM login_attempts 
             WHERE (ip_address = ? OR identifier = ?) 
               AND is_success = 0 
               AND attempted_at > (NOW() - INTERVAL ? MINUTE)",
            [$ip, $identifier, $decayMinutes]
        );
        return ($row['failed_count'] ?? 0) >= $maxAttempts;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * -------------------------------------------------------------
 * User Authentication State Helpers
 * -------------------------------------------------------------
 */

function isLoggedIn(): bool {
    return !empty($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function userRole(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

function isWorker(): bool {
    return userRole() === 'worker';
}

function isCustomer(): bool {
    return userRole() === 'customer';
}

function isAdmin(): bool {
    return userRole() === 'admin';
}

/**
 * Check if current or specified user is verified by Admin
 * Admins always return true
 */
function isUserVerified(?int $userId = null): bool {
    if ($userId === null) {
        $user = currentUser();
        if (!$user) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        $userId = (int)$user['id'];
        $role = $user['role'];
    } else {
        $u = dbFetchOne("SELECT role FROM users WHERE id = ?", [$userId]);
        if (!$u) {
            return false;
        }
        if ($u['role'] === 'admin') {
            return true;
        }
        $role = $u['role'];
    }

    try {
        $v = dbFetchOne("SELECT status FROM identity_verifications WHERE user_id = ?", [$userId]);
        if (!$v || $v['status'] !== 'verified') {
            return false;
        }

        if ($role === 'worker') {
            $wp = dbFetchOne("SELECT is_verified FROM worker_profiles WHERE user_id = ?", [$userId]);
            return !empty($wp['is_verified']);
        } elseif ($role === 'customer') {
            $cp = dbFetchOne("SELECT is_verified FROM customer_profiles WHERE user_id = ?", [$userId]);
            return !empty($cp['is_verified']);
        }

        return true;
    } catch (Exception $e) {
        return false;
    }
}

function isVerifiedUser(?int $userId = null): bool {
    return isUserVerified($userId);
}

/**
 * -------------------------------------------------------------
 * URL & Asset Routing Helpers (Subdirectory & Host Agnostic)
 * -------------------------------------------------------------
 */

function getBaseUrl(): string {
    static $baseUrl = null;
    if ($baseUrl !== null) {
        return $baseUrl;
    }

    if (php_sapi_name() === 'cli' || empty($_SERVER['SCRIPT_NAME'])) {
        $baseUrl = '';
        return $baseUrl;
    }

    // Determine subfolder path relative to DocumentRoot
    $appDir = str_replace('\\', '/', dirname(__DIR__));
    $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';
    
    if ($docRoot && str_starts_with(strtolower($appDir), strtolower($docRoot))) {
        $relative = substr($appDir, strlen($docRoot));
        $baseUrl = '/' . trim(str_replace('\\', '/', $relative), '/');
        if ($baseUrl === '/') {
            $baseUrl = '';
        }
    } else {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $parts = explode('/', trim($scriptName, '/'));
        if (!empty($parts) && strcasecmp($parts[0], basename($appDir)) === 0) {
            $baseUrl = '/' . $parts[0];
        } else {
            $baseUrl = '';
        }
    }

    return $baseUrl;
}

function url(string $path = ''): string {
    $base = getBaseUrl();
    if ($path === '' || $path === '/') {
        return ($base === '') ? '/index.php' : $base . '/index.php';
    }
    if (str_starts_with($path, '?') || str_starts_with($path, '#')) {
        return $path;
    }
    $path = '/' . ltrim($path, '/');
    if ($base !== '' && (str_starts_with(strtolower($path), strtolower($base) . '/') || strtolower($path) === strtolower($base))) {
        return $path;
    }
    return $base . $path;
}

function asset(string $path): string {
    $base = getBaseUrl();
    $path = '/' . ltrim($path, '/');
    if ($base !== '' && (str_starts_with(strtolower($path), strtolower($base) . '/') || strtolower($path) === strtolower($base))) {
        return $path;
    }
    return $base . $path;
}

/**
 * Returns full absolute URL with protocol and host
 */
function getAbsoluteUrl(string $path = ''): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
    return $scheme . '://' . $host . url($path);
}

/**
 * Safe redirect helper
 */
function redirect(string $url): void {
    if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
        $url = url($url);
    }
    header("Location: {$url}");
    exit;
}

/**
 * Flash message helper (success, danger, warning, info)
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * -------------------------------------------------------------
 * File Upload Handler (Profiles, Portfolios)
 * -------------------------------------------------------------
 */

/**
 * Securely validate and store an uploaded image file
 *
 * @param array $file $_FILES element
 * @param string $subDir Destination subfolder relative to assets/uploads/
 * @param int $maxBytes Max file size in bytes (default 3MB)
 * @return array ['success' => bool, 'path' => string, 'error' => string]
 */
function uploadImage(array $file, string $subDir = 'avatars', int $maxBytes = 3145728): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid file parameter.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed with error code ' . $file['error']];
    }

    if ($file['size'] > $maxBytes) {
        return ['success' => false, 'error' => 'File exceeds maximum size limit (3MB).'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!array_key_exists($mimeType, $allowedMimes)) {
        return ['success' => false, 'error' => 'Allowed formats are JPG, PNG, and WebP only.'];
    }

    $ext = $allowedMimes[$mimeType];
    $fileName = sprintf('%s_%s.%s', bin2hex(random_bytes(8)), time(), $ext);
    
    $uploadDir = dirname(__DIR__) . "/assets/uploads/{$subDir}";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $targetPath = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file to destination.'];
    }

    return [
        'success' => true,
        'path' => "assets/uploads/{$subDir}/{$fileName}"
    ];
}

/**
 * -------------------------------------------------------------
 * Notification & Event System
 * -------------------------------------------------------------
 */

function createNotification(int $userId, string $title, string $message, ?string $link = null, string $type = 'system'): bool {
    try {
        dbExecute(
            "INSERT INTO notifications (user_id, title, message, link, type, is_read, created_at) 
             VALUES (?, ?, ?, ?, ?, 0, NOW())",
            [$userId, $title, $message, $link, $type]
        );
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function getUnreadNotificationCount(int $userId): int {
    try {
        $row = dbFetchOne("SELECT COUNT(*) AS unread_cnt FROM notifications WHERE user_id = ? AND is_read = 0", [$userId]);
        return (int)($row['unread_cnt'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

function getUnreadMessageCount(int $userId): int {
    try {
        $row = dbFetchOne("SELECT COUNT(*) AS unread_cnt FROM messages WHERE receiver_id = ? AND is_read = 0", [$userId]);
        return (int)($row['unread_cnt'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * -------------------------------------------------------------
 * UI Helpers: Stars, Badges, Time Formatting
 * -------------------------------------------------------------
 */

/**
 * Render FontAwesome 5/6 star rating HTML
 */
function renderStars(float $rating, int $max = 5): string {
    $html = '<div class="rating-stars d-inline-flex align-items-center gap-1 text-warning" title="' . number_format($rating, 1) . ' / 5">';
    for ($i = 1; $i <= $max; $i++) {
        if ($rating >= $i) {
            $html .= '<i class="fas fa-star"></i>';
        } elseif ($rating >= ($i - 0.5)) {
            $html .= '<i class="fas fa-star-half-alt"></i>';
        } else {
            $html .= '<i class="far fa-star text-muted opacity-50"></i>';
        }
    }
    $html .= '<span class="ms-1 fw-bold text-dark small">' . number_format($rating, 1) . '</span></div>';
    return $html;
}

/**
 * Render a styled bootstrap badge for application / job statuses
 */
function renderStatusBadge(string $status): string {
    $map = [
        'open'      => ['bg' => 'bg-success text-white', 'icon' => 'fa-door-open', 'label' => __('status_open')],
        'assigned'  => ['bg' => 'bg-primary text-white', 'icon' => 'fa-user-check', 'label' => __('status_assigned')],
        'completed' => ['bg' => 'bg-secondary text-white', 'icon' => 'fa-check-circle', 'label' => __('status_completed')],
        'cancelled' => ['bg' => 'bg-danger text-white', 'icon' => 'fa-times-circle', 'label' => __('status_cancelled')],
        'pending'   => ['bg' => 'bg-warning text-dark', 'icon' => 'fa-clock', 'label' => __('status_pending')],
        'accepted'  => ['bg' => 'bg-success text-white', 'icon' => 'fa-thumbs-up', 'label' => __('status_accepted')],
        'rejected'  => ['bg' => 'bg-danger text-white', 'icon' => 'fa-thumbs-down', 'label' => __('status_rejected')],
        'withdrawn' => ['bg' => 'bg-secondary text-white', 'icon' => 'fa-undo', 'label' => __('status_withdrawn')],
    ];

    $cfg = $map[strtolower($status)] ?? ['bg' => 'bg-light text-dark', 'icon' => 'fa-circle', 'label' => ucfirst($status)];
    return sprintf(
        '<span class="badge %s px-2 py-1 rounded-pill"><i class="fas %s me-1"></i>%s</span>',
        $cfg['bg'],
        $cfg['icon'],
        e($cfg['label'])
    );
}

/**
 * Format relative time (e.g., "5 mins ago", "2 days ago")
 */
function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . 'd ago';
    }
    return date('d M Y', $time);
}

/**
 * Calculate distance between two coordinates using Haversine formula (km)
 */
function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $earthRadius = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float {
    return haversineDistance($lat1, $lon1, $lat2, $lon2);
}
