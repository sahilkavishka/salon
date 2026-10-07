<?php
session_start();
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

if (!isset($_GET['salon_id']) || !isset($_GET['date'])) {
    echo json_encode([]);
    exit;
}

$salon_id = intval($_GET['salon_id']);
$date = trim($_GET['date']);

// Validate date format YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([]);
    exit;
}

// Cannot book past dates
$today = date('Y-m-d');
if ($date < $today) {
    echo json_encode([]);
    exit;
}

// Fetch salon working hours & operating days
$stmt = $pdo->prepare("SELECT opening_time, closing_time, slot_duration, operating_days FROM salons WHERE id=?");
$stmt->execute([$salon_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$salon) {
    echo json_encode([]);
    exit;
}

// Check operating days
$dayOfWeek = date('l', strtotime($date));
$operatingDays = !empty($salon['operating_days']) 
    ? array_map('trim', explode(',', $salon['operating_days'])) 
    : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

if (!in_array($dayOfWeek, $operatingDays)) {
    echo json_encode([]);
    exit;
}

$open = $salon['opening_time'];
$close = $salon['closing_time'];
$duration = intval($salon['slot_duration']) > 0 ? intval($salon['slot_duration']) : 30;

// Get booked times (active bookings only, excluding cancelled and rejected)
$bookStmt = $pdo->prepare("
    SELECT appointment_time 
    FROM appointments 
    WHERE salon_id = ? AND appointment_date = ? AND status NOT IN ('cancelled', 'rejected')
");
$bookStmt->execute([$salon_id, $date]);
$rawBooked = $bookStmt->fetchAll(PDO::FETCH_COLUMN);

// Normalize booked times to 'H:i' format to avoid mismatch between '09:00:00' and '09:00'
$bookedTimes = array_map(function($t) {
    return date("H:i", strtotime($t));
}, $rawBooked);

$availableSlots = [];
$current = strtotime($open);
$end = strtotime($close);
$currentTime = time();

while ($current < $end) {
    $slot = date("H:i", $current);

    // If date is today, check if slot has already passed
    $isPast = false;
    if ($date === $today) {
        $slotTimestamp = strtotime("$date $slot");
        if ($slotTimestamp <= $currentTime) {
            $isPast = true;
        }
    }

    if (!$isPast && !in_array($slot, $bookedTimes)) {
        $availableSlots[] = $slot;
    }

    $current = strtotime("+$duration minutes", $current);
}

echo json_encode($availableSlots);
exit;
