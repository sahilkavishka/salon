<?php
// public/logout.php
session_start();

// Prevent caching (so user can't go back after logout)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Clear all session data
session_unset();
session_destroy();

// Regenerate session ID for safety
session_start();
session_regenerate_id(true);

// Redirect to login page
header('Location: login.php');
exit;
?>
