<?php
// public/book_appointment.php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $salon_id = intval($_POST['salon_id']);
    $service_id = intval($_POST['service_id']);
    $date = $_POST['appointment_date'];
    $time = $_POST['appointment_time'];

    // Optional: check double-booking here (same salon/service and time)
    $stmt = $pdo->prepare("INSERT INTO appointments (user_id, salon_id, service_id, appointment_date, appointment_time) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $salon_id, $service_id, $date, $time]);
    header("Location: ../public/index.php?msg=appointment_requested");
    exit;
}
?>
