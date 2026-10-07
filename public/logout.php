<?php
// public/logout.php
session_start();

// Prevent caching (so user can't go back after logout)
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

// Store user info for logging before destroying session
$user_id = $_SESSION['id'] ?? null;
$user_role = $_SESSION['role'] ?? 'guest';
$user_name = $_SESSION['user_name'] ?? 'Unknown';

// Log logout activity
if ($user_id) {
    error_log("User logout - ID: {$user_id}, Name: {$user_name}, Role: {$user_role}, Time: " . date('Y-m-d H:i:s'));
}

// Clear all session data
session_unset();
session_destroy();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Clear any remember me cookies if they exist
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Regenerate session ID for safety
session_start();
session_regenerate_id(true);

// Set flash message for logout confirmation
$_SESSION['logout_message'] = 'You have been successfully logged out. See you soon!';

// Redirect to login page with success parameter
header('Location: login.php?logout=success');
exit;
?>