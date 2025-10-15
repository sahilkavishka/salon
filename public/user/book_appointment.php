<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/auth_check.php';
checkAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['id'];
    $salon_id = intval($_POST['salon_id'] ?? 0);
    $service_id = intval($_POST['service_id'] ?? 0);
    $date = $_POST['appointment_date'] ?? '';
    $time = $_POST['appointment_time'] ?? '';

    if (!$salon_id || !$service_id || !$date || !$time) {
        echo "All fields are required.";
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO appointments (user_id, salon_id, service_id, appointment_date, appointment_time, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $salon_id, $service_id, $date, $time]);
        echo "Appointment booked successfully!";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
