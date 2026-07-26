<?php
/**
 * 🛡️ ADVANCED SECURE LOGOUT PROTOCOL
 * Optimized for Academic & Professional Standard Security
 */

// Initialize the session if it's not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session data
$_SESSION = array();

// Completely destroy the session cookie in the browser for maximum security
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

// Destroy the session on the server side
session_destroy();

// 🚀 PROFESSIONAL CACHE CONTROL
// Prevents the browser from showing the dashboard via the 'Back' button after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to login gateway
header("Location: login.php");
exit();
?>
