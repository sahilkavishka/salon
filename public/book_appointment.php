<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('customer');

$user_id = $_SESSION['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $salon_id = intval($_POST['salon_id']);
    $service_id = intval($_POST['service_id']);
    $date = $_POST['appointment_date'] ?? '';
    $time = $_POST['appointment_time'] ?? '';

    if (!$salon_id || !$service_id || !$date || !$time) {
        die("Missing required fields.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO appointments (user_id, salon_id, service_id, appointment_date, appointment_time, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'Pending', NOW())
    ");
    $stmt->execute([$user_id, $salon_id, $service_id, $date, $time]);

    header("Location: user/appointments.php?success=1");
    exit;
}
?>
