<?php
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_GET['salon_id']) || !isset($_GET['date'])) {
    echo json_encode([]);
    exit;
}

$salon_id = intval($_GET['salon_id']);
$date = $_GET['date'];

// Fetch salon working hours
$stmt = $pdo->prepare("SELECT opening_time, closing_time, slot_duration FROM salons WHERE id=?");
$stmt->execute([$salon_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$salon) {
    echo json_encode([]);
    exit;
}

$open = $salon['opening_time'];
$close = $salon['closing_time'];
$duration = intval($salon['slot_duration']);

// Get booked times
$bookStmt = $pdo->prepare("
    SELECT appointment_time 
    FROM appointments 
    WHERE salon_id=? AND appointment_date=? AND status!='cancelled'
");
$bookStmt->execute([$salon_id, $date]);
$bookedTimes = $bookStmt->fetchAll(PDO::FETCH_COLUMN);

$availableSlots = [];
$current = strtotime($open);
$end = strtotime($close);

while ($current < $end) {
    $slot = date("H:i", $current);

    if (!in_array($slot, $bookedTimes)) {
        $availableSlots[] = $slot;
    }

    $current = strtotime("+$duration minutes", $current);
}

echo json_encode($availableSlots);
exit;
