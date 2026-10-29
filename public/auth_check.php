<?php
// public/auth_check.php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check login authentication and role authorization.
 * Redirects to login or role home if unauthorized.
 * 
 * @param string|null $requiredRole 'owner' or 'user' or null
 */
function checkAuth(?string $requiredRole = null): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 1. Not logged in at all -> redirect to login
    if (!isset($_SESSION['id']) || !isset($_SESSION['role'])) {
        $_SESSION['flash_error'] = 'Please log in to access this page.';
        header('Location: ' . url('login.php'));
        exit;
    }

    $currentRole = strtolower(trim((string)$_SESSION['role']));

    // 2. Auto-detect if script is inside owner directory
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (strpos($script, '/owner/') !== false) {
        $requiredRole = 'owner';
    }

    // 3. Strict owner check
    if ($requiredRole === 'owner' && $currentRole !== 'owner') {
        $_SESSION['flash_error'] = 'Access Denied: You do not have Salon Owner permissions.';
        header('Location: ' . url('index.php'));
        exit;
    }

    // 4. Strict customer (user) check
    if ($requiredRole === 'user' && $currentRole !== 'user') {
        if ($currentRole === 'owner') {
            $_SESSION['flash_error'] = 'Redirected: You are logged in as a Salon Owner.';
            header('Location: ' . url('owner/dashboard.php'));
        } else {
            $_SESSION['flash_error'] = 'Access Denied: Customer access only.';
            header('Location: ' . url('index.php'));
        }
        exit;
    }
}
