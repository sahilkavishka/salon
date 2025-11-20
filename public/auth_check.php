<?php
// includes/auth_check.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check login and (optionally) role.
 * Redirects to login if unauthorized.
 * 
 * @param string|null $requiredRole 'owner' or 'customer' or null
 */
function checkAuth(?string $requiredRole = null): void {
    if (!isset($_SESSION['id']) || !isset($_SESSION['role'])) {
        header('Location: /salonora/public/login.php');
        exit;
    }

    if ($requiredRole && strtolower($_SESSION['role']) !== strtolower($requiredRole)) {
        // wrong role → redirect to home
        if ($_SESSION['role'] === 'owner') {
            header('Location: /salonora/public/owner/dashboard.php');
        } else {
            header('Location: ../index.php');
        }
        exit;
    }
}
