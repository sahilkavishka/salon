<?php
/**
 * get_user_bookings.php
 * Returns user's existing bookings for calendar display
 */

session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

header('Content-Type: application/json');

$user_id = $_SESSION['id'];
$salon_id = isset($_GET['salon_id']) ? intval($_GET['salon_id']) : 0;

if ($salon_id <= 0) {
    echo json_encode(['error' => 'Invalid salon ID', 'bookings' => []]);
    exit;
}

try {
    // Fetch user's upcoming bookings for this salon
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            s.name as service_name,
            s.duration,
            s.price
        FROM appointments a
        INNER JOIN services s ON a.service_id = s.id
        WHERE a.user_id = ? 
        AND a.salon_id = ?
        AND a.appointment_date >= CURDATE()
        AND a.status NOT IN ('cancelled', 'rejected')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    
    $stmt->execute([$user_id, $salon_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'bookings' => $bookings,
        'total' => count($bookings)
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching user bookings: " . $e->getMessage());
    echo json_encode([
        'error' => 'Failed to load bookings',
        'bookings' => []
    ]);
}
?>