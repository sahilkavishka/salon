<?php
// public/book_appointment.php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['id'];
    $salon_id = intval($_POST['salon_id'] ?? 0);
    $service_id = intval($_POST['service_id'] ?? 0);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';

    if ($salon_id && $service_id && $appointment_date && $appointment_time) {
        $stmt = $pdo->prepare("INSERT INTO appointments (user_id, salon_id, service_id, appointment_date, appointment_time, status, created_at)
                               VALUES (?, ?, ?, ?, ?, 'Pending', NOW())");
        $stmt->execute([$user_id, $salon_id, $service_id, $appointment_date, $appointment_time]);
        header("Location: user/profile.php");
        exit;
    } else {
        die("Missing fields!");
    }
}
?>
