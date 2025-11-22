<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

// Set JSON header
header('Content-Type: application/json');

// Verify AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Check authorization
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$owner_id = $_SESSION['id'];

try {
    // Get all salon IDs for this owner
    $stmt = $pdo->prepare("SELECT id FROM salons WHERE owner_id = ?");
    $stmt->execute([$owner_id]);
    $salons = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // If no salons, return empty arrays
    if (empty($salons)) {
        echo json_encode([
            'pending' => [],
            'confirmed' => [],
            'completed' => [],
            'cancelled' => []
        ]);
        exit;
    }
    
    $statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
    $result = [];
    
    foreach ($statuses as $status) {
        $placeholders = implode(',', array_fill(0, count($salons), '?'));
        
        // Special handling for confirmed appointments (only show upcoming)
        if ($status === 'confirmed') {
            $sql = "SELECT 
                        a.id,
                        a.user_id,
                        a.service_id,
                        a.salon_id,
                        a.appointment_date,
                        a.appointment_time,
                        a.status,
                        a.created_at,
                        u.username AS user_name,
                        u.email AS user_email,
                        s.name AS service_name,
                        s.price AS service_price,
                        s.duration AS service_duration,
                        sal.name AS salon_name
                    FROM appointments a
                    JOIN users u ON u.id = a.user_id
                    JOIN services s ON s.id = a.service_id
                    JOIN salons sal ON sal.id = a.salon_id
                    WHERE a.status = 'confirmed' 
                    AND a.salon_id IN ($placeholders)
                    AND CONCAT(a.appointment_date, ' ', a.appointment_time) >= NOW()
                    ORDER BY a.appointment_date ASC, a.appointment_time ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($salons);
        } else {
            $sql = "SELECT 
                        a.id,
                        a.user_id,
                        a.service_id,
                        a.salon_id,
                        a.appointment_date,
                        a.appointment_time,
                        a.status,
                        a.created_at,
                        u.username AS user_name,
                        u.email AS user_email,
                        s.name AS service_name,
                        s.price AS service_price,
                        s.duration AS service_duration,
                        sal.name AS salon_name
                    FROM appointments a
                    JOIN users u ON u.id = a.user_id
                    JOIN services s ON s.id = a.service_id
                    JOIN salons sal ON sal.id = a.salon_id
                    WHERE a.status = ?
                    AND a.salon_id IN ($placeholders)
                    ORDER BY a.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $params = array_merge([$status], $salons);
            $stmt->execute($params);
        }
        
        $result[$status] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode($result);
    
} catch (PDOException $e) {
    error_log("Fetch appointments error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
}
?>