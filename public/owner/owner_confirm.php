<?php
// public/owner/owner_confirm.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header('Location: ../login.php');
    exit;
}

$owner_id = $_SESSION['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: appointments.php');
    exit;
}

$appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
$action = $_POST['action'] ?? '';

if ($appointment_id <= 0 || !in_array($action, ['confirm','reject'])) {
    $_SESSION['flash_error'] = "Invalid action.";
    header('Location: appointments.php');
    exit;
}

// fetch appointment and salon owner info
$stmt = $pdo->prepare("
    SELECT a.*, sal.owner_id
    FROM appointments a
    JOIN salons sal ON sal.id = a.salon_id
    WHERE a.id = :id
");
$stmt->execute([':id' => $appointment_id]);
$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    $_SESSION['flash_error'] = "Appointment not found.";
    header('Location: appointments.php');
    exit;
}

// ensure this owner owns the salon
if ((int)$appointment['owner_id'] !== (int)$owner_id) {
    $_SESSION['flash_error'] = "You are not allowed to change this appointment.";
    header('Location: appointments.php');
    exit;
}

// only pending appointments can be confirmed or rejected
if ($appointment['status'] !== 'pending') {
    $_SESSION['flash_error'] = "Only pending appointments can be acted upon.";
    header('Location: appointments.php');
    exit;
}

if ($action === 'reject') {
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'rejected', updated_at = NOW() WHERE id = :id");
    $stmt->execute([':id' => $appointment_id]);
    $_SESSION['flash_success'] = "Appointment rejected.";
    header('Location: appointments.php');
    exit;
}

// if confirm -> check availability: no existing confirmed appointment at same salon/service/date/time
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM appointments
        WHERE salon_id = :salon_id
          AND service_id = :service_id
          AND appointment_date = :appointment_date
          AND appointment_time = :appointment_time
          AND status = 'confirmed'
        FOR UPDATE
    ");
    $stmt->execute([
        ':salon_id' => $appointment['salon_id'],
        ':service_id' => $appointment['service_id'],
        ':appointment_date' => $appointment['appointment_date'],
        ':appointment_time' => $appointment['appointment_time'],
    ]);
    $count = (int)$stmt->fetchColumn();

    if ($count > 0) {
        // slot already taken
        $pdo->rollBack();
        $_SESSION['flash_error'] = "The selected slot is already booked. Consider rejecting or notifying the user.";
        header('Location: appointments.php');
        exit;
    }

    // update to confirmed
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'confirmed', updated_at = NOW() WHERE id = :id");
    $stmt->execute([':id' => $appointment_id]);

    $pdo->commit();
    $_SESSION['flash_success'] = "Appointment confirmed and added to booked appointments.";
    header('Location: appointments.php');
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash_error'] = "Could not confirm appointment. Try again.";
    header('Location: appointments.php');
    exit;
}
