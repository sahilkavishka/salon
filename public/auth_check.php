<?php
// includes/auth_check.php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check login and (optionally) role.
 * Redirects to login if unauthorized.
 * 
 * @param string|null $requiredRole 'owner' or 'user' or null
 */
function checkAuth(?string $requiredRole = null): void {
    if (!isset($_SESSION['id']) || !isset($_SESSION['role'])) {
        header('Location: ' . url('login.php'));
        exit;
    }

    if ($requiredRole && strtolower($_SESSION['role']) !== strtolower($requiredRole)) {
        // wrong role → redirect to appropriate home
        if ($_SESSION['role'] === 'owner') {
            header('Location: ' . url('owner/dashboard.php'));
        } else {
            header('Location: ' . url('index.php'));
        }
        exit;
    }
}
