<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

if (!isset($_SESSION['id'], $_POST['salon_id'], $_POST['service_id'], $_POST['appointment_date'], $_POST['appointment_time'])) {
    die("Invalid request.");
}

// Fetch salon owner
$salon_id = intval($_POST['salon_id']);
$stmt = $pdo->prepare("SELECT owner_id FROM salons WHERE id = ?");
$stmt->execute([$salon_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$salon) die("Salon not found.");

// Prevent owner from booking their own salon
if ($_SESSION['id'] == $salon['owner_id']) {
    die("You cannot book an appointment at your own salon.");
}

// Insert appointment
$stmt = $pdo->prepare("
    INSERT INTO appointments (salon_id, service_id, user_id, appointment_date, appointment_time)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([
    $salon_id,
    intval($_POST['service_id']),
    $_SESSION['id'],
    $_POST['appointment_date'],
    $_POST['appointment_time']
]);

header("Location: salon_details.php?id=$salon_id&msg=booked");
exit;
