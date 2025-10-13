<?php
// includes/auth_check.php
if (session_status() === PHP_SESSION_NONE) session_start();

function checkAuth($requiredRole = null) {
    if (!isset($_SESSION['id'])) {
        header('Location: ../login.php');
        exit;
    }
    if ($requiredRole !== null) {
        $current = strtolower(trim((string)($_SESSION['role'] ?? '')));
        if ($current !== strtolower(trim((string)$requiredRole))) {
            header('Location: ../login.php');
            exit;
        }
    }
}
