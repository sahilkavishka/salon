<?php
// includes/auth_check.php
session_start();

/**
 * Protects pages based on login and role
 * Usage:
 *   require_once '../includes/auth_check.php';
 *   checkAuth('owner'); // or checkAuth('customer');
 */

function checkAuth($requiredRole = null) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit;
    }

    if ($requiredRole && $_SESSION['role'] !== $requiredRole) {
        // Optional: redirect to 403 page or home
        header("Location: ../index.php");
        exit;
    }
}
