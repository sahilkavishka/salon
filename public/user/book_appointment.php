<?php
// public/user/book_appointment.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth(); // ensures user logged in

// Only regular users should book; allow players/users but prevent owner booking their own salon? optional
$userId = $_SESSION['id'];
$role = $_SESSION['role'] ?? 'user';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

// collect and sanitize
$salon_id = isset($_POST['salon_id']) ? (int)$_POST['salon_id'] : 0;
$service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
$appointment_date = $_POST['appointment_date'] ?? '';
$appointment_time = $_POST['appointment_time'] ?? '';

// basic validation
$errors = [];
if ($salon_id <= 0) $errors[] = "Invalid salon selected.";
if ($service_id <= 0) $errors[] = "Invalid service selected.";
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) $errors[] = "Invalid date.";
if (!preg_match('/^\d{2}:\d{2}$/', $appointment_time)) $errors[] = "Invalid time.";
// ensure date not in past
if (strtotime($appointment_date . ' ' . $appointment_time) < time()) $errors[] = "Cannot book a past date/time.";

// optional: prevent user from booking for their own salon (if role=owner and salon belongs to them)
// fetch salon owner if needed
if ($errors) {
    // redirect back with error messages (simple)
    $_SESSION['flash_error'] = implode(' ', $errors);
    header("Location: ../user/salon_details.php?id={$salon_id}");
    exit;
}

try {
    // Insert pending appointment
    $stmt = $pdo->prepare("
        INSERT INTO appointments (user_id, salon_id, service_id, appointment_date, appointment_time, status)
        VALUES (:user_id, :salon_id, :service_id, :appointment_date, :appointment_time, 'pending')
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':salon_id' => $salon_id,
        ':service_id' => $service_id,
        ':appointment_date' => $appointment_date,
        ':appointment_time' => $appointment_time,
    ]);

    $_SESSION['flash_success'] = "Appointment request sent. The salon owner will confirm if the slot is available.";
    header("Location: ../user/salon_details.php?id={$salon_id}");
    exit;
} catch (PDOException $e) {
    // log error in real app; return friendly message
    $_SESSION['flash_error'] = "Could not create appointment request. Try again.";
    header("Location: ../user/salon_details.php?id={$salon_id}");
    exit;
}
