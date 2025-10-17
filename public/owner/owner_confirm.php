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

// fetch appointment + related info (include user's email)
$stmt = $pdo->prepare("
    SELECT a.*, sal.owner_id, u.email AS user_email, u.username AS user_name, srv.name AS service_name, sal.name AS salon_name
    FROM appointments a
    JOIN salons sal ON sal.id = a.salon_id
    JOIN users u ON u.id = a.user_id
    JOIN services srv ON srv.id = a.service_id
    WHERE a.id = :id
");
$stmt->execute([':id' => $appointment_id]);
$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    $_SESSION['flash_error'] = "Appointment not found.";
    header('Location: appointments.php');
    exit;
}

if ((int)$appointment['owner_id'] !== (int)$owner_id) {
    $_SESSION['flash_error'] = "You are not allowed to update this appointment.";
    header('Location: appointments.php');
    exit;
}

if ($appointment['status'] !== 'pending') {
    $_SESSION['flash_error'] = "Only pending appointments can be changed.";
    header('Location: appointments.php');
    exit;
}

$userEmail = $appointment['user_email'];
$userName = $appointment['user_name'];
$serviceName = $appointment['service_name'];
$salonName = $appointment['salon_name'];
$date = $appointment['appointment_date'];
$time = substr($appointment['appointment_time'], 0, 5);

// -------------------- REJECT --------------------
if ($action === 'reject') {
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'rejected', updated_at = NOW() WHERE id = :id");
    $stmt->execute([':id' => $appointment_id]);

    // Send rejection email
    $subject = "Appointment Rejected - $salonName";
    $message = "Dear $userName,\n\nYour appointment request for $serviceName at $salonName on $date at $time has been rejected.\n\nPlease try another slot or service.\n\nThank you,\n$salonName";
    $headers = "From: noreply@salonora.com\r\n";

    @mail($userEmail, $subject, $message, $headers);

    $_SESSION['flash_success'] = "Appointment rejected and user notified.";
    header('Location: appointments.php');
    exit;
}

// -------------------- CONFIRM --------------------
try {
    $pdo->beginTransaction();

    // Check if slot already booked
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

    if ($stmt->fetchColumn() > 0) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = "Slot already booked.";
        header('Location: appointments.php');
        exit;
    }

    $stmt = $pdo->prepare("UPDATE appointments SET status = 'confirmed', updated_at = NOW() WHERE id = :id");
    $stmt->execute([':id' => $appointment_id]);
    $pdo->commit();

    // Send confirmation email
    $subject = "Appointment Confirmed - $salonName";
    $message = "Dear $userName,\n\nYour appointment for $serviceName at $salonName has been confirmed.\n\n📅 Date: $date\n🕒 Time: $time\n\nThank you for choosing $salonName!\n\n- Salonora Team";
    $headers = "From: noreply@salonora.com\r\n";

    @mail($userEmail, $subject, $message, $headers);
    // Add notification for user
$msg = "Your appointment for $serviceName at $salonName on $date $time has been CONFIRMED.";
$stmtNotify = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (:uid, :msg)");
$stmtNotify->execute([':uid' => $appointment['user_id'], ':msg' => $msg]);

// Add notification for user
$msg = "Your appointment for $serviceName at $salonName on $date $time has been REJECTED.";
$stmtNotify = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (:uid, :msg)");
$stmtNotify->execute([':uid' => $appointment['user_id'], ':msg' => $msg]);


    $_SESSION['flash_success'] = "Appointment confirmed and user notified.";
    header('Location: appointments.php');
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash_error'] = "Error confirming appointment.";
    header('Location: appointments.php');
    exit;
}
