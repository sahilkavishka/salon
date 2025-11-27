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

// Check HTTP method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check authorization
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Validate CSRF token using timing-safe comparison
$csrf = $_POST['csrf_token'] ?? '';
if (!$csrf || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token. Please refresh the page.']);
    exit;
}

// Validate and sanitize input
$appt_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
$action = trim($_POST['action'] ?? '');

if (!$appt_id || $appt_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid appointment ID']);
    exit;
}

// Define valid status transitions
$validTransitions = [
    'confirm' => [
        'from' => 'pending', 
        'to' => 'confirmed', 
        'message' => 'Appointment confirmed successfully',
        'notification' => 'Your appointment at {salon} for {service} has been confirmed!'
    ],
    'reject' => [
        'from' => 'pending', 
        'to' => 'cancelled', 
        'message' => 'Appointment rejected',
        'notification' => 'Your appointment at {salon} for {service} has been rejected.'
    ],
    'complete' => [
        'from' => 'confirmed', 
        'to' => 'completed', 
        'message' => 'Appointment marked as completed',
        'notification' => 'Your appointment at {salon} has been completed. Please leave a review!'
    ]
];

if (!array_key_exists($action, $validTransitions)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action specified']);
    exit;
}

$owner_id = $_SESSION['id'];

try {
    // Start transaction for data consistency
    $pdo->beginTransaction();
    
    // Verify appointment belongs to owner's salon and lock the row
    $stmt = $pdo->prepare("
        SELECT 
            a.*, 
            s.owner_id, 
            s.name as salon_name, 
            u.username, 
            u.email as user_email,
            serv.name as service_name
        FROM appointments a
        JOIN salons s ON s.id = a.salon_id
        JOIN users u ON u.id = a.user_id
        JOIN services serv ON serv.id = a.service_id
        WHERE a.id = ? AND s.owner_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$appt_id, $owner_id]);
    $appt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appt) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Appointment not found or access denied']);
        exit;
    }
    
    // Validate status transition
    $expectedStatus = $validTransitions[$action]['from'];
    if ($appt['status'] !== $expectedStatus) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'error' => "Cannot {$action} appointment with status '{$appt['status']}'. Expected status: '{$expectedStatus}'"
        ]);
        exit;
    }
    
    // Additional validation for completing appointments
    if ($action === 'complete') {
        $apptDateTime = new DateTime($appt['appointment_date'] . ' ' . $appt['appointment_time']);
        $now = new DateTime();
        
        // Check if appointment is in the future (with 15-minute grace period)
        $gracePeriod = new DateInterval('PT15M');
        $apptDateTime->sub($gracePeriod);
        
        if ($apptDateTime > $now) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['error' => 'Cannot complete future appointments']);
            exit;
        }
    }
    
    // Update appointment status
    $newStatus = $validTransitions[$action]['to'];
    $stmt = $pdo->prepare("
        UPDATE appointments 
        SET status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$newStatus, $appt_id]);
    
    // Log the action in appointment_logs table
    try {
        $stmt = $pdo->prepare("
            INSERT INTO appointment_logs 
            (appointment_id, old_status, new_status, changed_by, action_type, changed_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $appt_id, 
            $appt['status'], 
            $newStatus, 
            $owner_id, 
            $action
        ]);
    } catch (PDOException $e) {
        // Log error but don't fail the main transaction
        error_log("Failed to log appointment action: " . $e->getMessage());
    }
    
    // Create notification for the user
    $notificationTemplate = $validTransitions[$action]['notification'];
    $notificationMessage = str_replace(
        ['{salon}', '{service}'],
        [$appt['salon_name'], $appt['service_name']],
        $notificationTemplate
    );
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, message, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$appt['user_id'], $notificationMessage]);
    } catch (PDOException $e) {
        // Log error but don't fail the main transaction
        error_log("Failed to create notification: " . $e->getMessage());
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $validTransitions[$action]['message'],
        'new_status' => $newStatus,
        'appointment_id' => $appt_id,
        'user_name' => $appt['username'],
        'service_name' => $appt['service_name']
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Appointment action error (PDO): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'A database error occurred. Please try again.']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Appointment action error (General): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred. Please try again.']);
}
?>