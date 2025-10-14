<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';
checkAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['id'];
    $appt_id = intval($_POST['id'] ?? 0);

    if (!$appt_id) {
        echo "Invalid appointment ID.";
        exit;
    }

    // Ensure the appointment belongs to the logged-in user
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ? AND user_id = ?");
    $stmt->execute([$appt_id, $user_id]);
    $appt = $stmt->fetch();

    if (!$appt) {
        echo "Appointment not found or not allowed.";
        exit;
    }

    // Delete appointment
    $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
    $stmt->execute([$appt_id]);
    echo "Appointment cancelled successfully!";
}
