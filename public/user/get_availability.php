<?php
/**
 * get_availability.php
 * Returns salon availability data for calendar display
 * Shows available slots for each date in the range
 */

session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

header('Content-Type: application/json');

$salon_id = isset($_GET['salon_id']) ? intval($_GET['salon_id']) : 0;
$start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
$end_date = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d', strtotime('+30 days'));

if ($salon_id <= 0) {
    echo json_encode(['error' => 'Invalid salon ID', 'availability' => []]);
    exit;
}

try {
    // Fetch salon details
    $salonStmt = $pdo->prepare("
        SELECT opening_time, closing_time, slot_duration 
        FROM salons 
        WHERE id = ?
    ");
    $salonStmt->execute([$salon_id]);
    $salon = $salonStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$salon) {
        echo json_encode(['error' => 'Salon not found', 'availability' => []]);
        exit;
    }
    
    // Fetch booked appointments in date range
    $bookingsStmt = $pdo->prepare("
        SELECT 
            appointment_date,
            appointment_time,
            COUNT(*) as booked_count
        FROM appointments
        WHERE salon_id = ?
        AND appointment_date BETWEEN ? AND ?
        AND status NOT IN ('cancelled', 'rejected')
        GROUP BY appointment_date, appointment_time
    ");
    $bookingsStmt->execute([$salon_id, $start_date, $end_date]);
    $bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a map of booked slots
    $bookedSlots = [];
    foreach ($bookings as $booking) {
        $date = $booking['appointment_date'];
        if (!isset($bookedSlots[$date])) {
            $bookedSlots[$date] = [];
        }
        $bookedSlots[$date][$booking['appointment_time']] = $booking['booked_count'];
    }
    
    // Generate availability for each date
    $availability = [];
    $current = strtotime($start_date);
    $end = strtotime($end_date);
    
    while ($current <= $end) {
        $date = date('Y-m-d', $current);
        
        // Calculate total slots for the day
        $totalSlots = calculateDailySlots(
            $salon['opening_time'],
            $salon['closing_time'],
            $salon['slot_duration']
        );
        
        // Calculate booked slots
        $bookedCount = 0;
        if (isset($bookedSlots[$date])) {
            $bookedCount = array_sum($bookedSlots[$date]);
        }
        
        // Available slots
        $availableSlots = max(0, $totalSlots - $bookedCount);
        
        $availability[$date] = [
            'date' => $date,
            'total' => $totalSlots,
            'booked' => $bookedCount,
            'available' => $availableSlots,
            'percentage' => $totalSlots > 0 ? round(($availableSlots / $totalSlots) * 100) : 0
        ];
        
        $current = strtotime('+1 day', $current);
    }
    
    echo json_encode([
        'success' => true,
        'availability' => $availability,
        'salon' => [
            'opening_time' => $salon['opening_time'],
            'closing_time' => $salon['closing_time'],
            'slot_duration' => $salon['slot_duration']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching availability: " . $e->getMessage());
    echo json_encode([
        'error' => 'Failed to load availability',
        'availability' => []
    ]);
}

/**
 * Calculate total number of slots in a day
 */
function calculateDailySlots($opening_time, $closing_time, $slot_duration) {
    $open = strtotime($opening_time);
    $close = strtotime($closing_time);
    $duration_seconds = $slot_duration * 60;
    
    $total_seconds = $close - $open;
    $slots = floor($total_seconds / $duration_seconds);
    
    return max(0, $slots);
}
?>