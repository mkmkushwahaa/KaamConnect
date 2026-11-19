<?php
/**
 * KaamConnect - Secure Session Logout Handler
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

// Clear session variables
$_SESSION = [];

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Start fresh session for flash message
session_start();
setFlash('info', __('logout_success'));

// Redirect to login or home
redirect('/login.php');

