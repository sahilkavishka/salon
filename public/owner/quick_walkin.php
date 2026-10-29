<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $_SESSION['flash_error'] = 'Security validation failed. Please try again.';
    header('Location: dashboard.php');
    exit;
}

$salon_id = intval($_POST['salon_id'] ?? 0);
$service_id = intval($_POST['service_id'] ?? 0);
$staff_id = !empty($_POST['staff_id']) ? intval($_POST['staff_id']) : null;
$customer_name = trim($_POST['customer_name'] ?? '');
$customer_phone = trim($_POST['customer_phone'] ?? '');
$appointment_date = trim($_POST['appointment_date'] ?? date('Y-m-d'));
$appointment_time = trim($_POST['appointment_time'] ?? date('H:i:00'));
$booking_status = in_array($_POST['status'] ?? '', ['confirmed', 'completed']) ? $_POST['status'] : 'confirmed';
$notes = trim($_POST['notes'] ?? 'Walk-in customer booked at reception');

if (empty($salon_id) || empty($service_id) || empty($customer_name)) {
    $_SESSION['flash_error'] = 'Please fill in all required walk-in details (Salon, Service, Customer Name).';
    header('Location: dashboard.php');
    exit;
}

// Verify salon ownership
$stmt = $pdo->prepare("SELECT id, name FROM salons WHERE id = ? AND owner_id = ?");
$stmt->execute([$salon_id, $owner_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$salon) {
    $_SESSION['flash_error'] = 'Unauthorized salon selection.';
    header('Location: dashboard.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Check if user already exists with this phone or find/create a walk-in user record
    $user_id = null;
    if (!empty($customer_phone)) {
        $uStmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
        $uStmt->execute([$customer_phone]);
        $existing = $uStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $user_id = $existing['id'];
        }
    }

    if (!$user_id) {
        // Create user record for this walk-in client
        $cleanPhone = preg_replace('/[^0-9]/', '', $customer_phone);
        $emailPrefix = !empty($cleanPhone) ? $cleanPhone : uniqid('client_');
        $walkin_email = "walkin_{$emailPrefix}@salonora.local";

        // Check if generated email exists
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$walkin_email]);
        $chkUser = $chk->fetch(PDO::FETCH_ASSOC);

        if ($chkUser) {
            $user_id = $chkUser['id'];
        } else {
            $dummyPass = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
            $insUser = $pdo->prepare("INSERT INTO users (username, email, password, phone, role, created_at) VALUES (?, ?, ?, ?, 'user', NOW())");
            $insUser->execute([$customer_name, $walkin_email, $dummyPass, $customer_phone]);
            $user_id = $pdo->lastInsertId();
        }
    }

    // Insert appointment
    $insApt = $pdo->prepare("
        INSERT INTO appointments (salon_id, user_id, service_id, staff_id, appointment_date, appointment_time, notes, status, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $insApt->execute([
        $salon_id,
        $user_id,
        $service_id,
        $staff_id,
        $appointment_date,
        $appointment_time,
        $notes,
        $booking_status
    ]);
    $appointment_id = $pdo->lastInsertId();

    // Log the appointment
    $logStmt = $pdo->prepare("
        INSERT INTO appointment_logs (appointment_id, user_id, changed_by, old_status, new_status, action, action_type, changed_at) 
        VALUES (?, ?, ?, NULL, ?, 'walkin_create', 'create', NOW())
    ");
    $logStmt->execute([$appointment_id, $user_id, $owner_id, $booking_status]);

    $pdo->commit();

    $_SESSION['flash_success'] = "Walk-in appointment #{$appointment_id} for {$customer_name} created successfully!";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Quick walk-in error: " . $e->getMessage());
    $_SESSION['flash_error'] = 'Failed to create walk-in appointment: ' . $e->getMessage();
}

header('Location: dashboard.php');
exit;
