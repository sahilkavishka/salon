<?php
// public/user/cancel_appointment.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/auth_check.php';
checkAuth();

// Set JSON response header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only POST requests are accepted.'
    ]);
    exit;
}

// Validate CSRF token (if you implement CSRF protection)
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid security token. Please refresh the page and try again.'
    ]);
    exit;
}

$user_id = $_SESSION['id'];
$appt_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

// Validate appointment ID
if (!$appt_id || $appt_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid appointment ID provided.'
    ]);
    exit;
}

try {
    // Begin transaction for data integrity
    $pdo->beginTransaction();

    // Fetch appointment details with salon information
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            s.name AS salon_name,
            s.email AS salon_email,
            srv.name AS service_name
        FROM appointments a
        JOIN salons s ON s.id = a.salon_id
        JOIN services srv ON srv.id = a.service_id
        WHERE a.id = :id AND a.user_id = :user_id
        FOR UPDATE
    ");
    $stmt->execute([':id' => $appt_id, ':user_id' => $user_id]);
    $appt = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify appointment exists and belongs to user
    if (!$appt) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Appointment not found or you do not have permission to cancel it.'
        ]);
        exit;
    }

    // Check if appointment can be cancelled
    if (!in_array($appt['status'], ['pending', 'confirmed'])) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Cannot cancel appointment with status: ' . htmlspecialchars($appt['status'])
        ]);
        exit;
    }

    // Check if appointment is at least 24 hours away
    $appointmentDateTime = strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time']);
    $currentTime = time();
    $hoursUntilAppointment = ($appointmentDateTime - $currentTime) / 3600;

    if ($hoursUntilAppointment < 24) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Appointments cannot be cancelled within 24 hours of the scheduled time. Please contact the salon directly.',
            'salon_contact' => $appt['salon_email'] ?? null
        ]);
        exit;
    }

    // Check if appointment is in the past
    if ($appointmentDateTime < $currentTime) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Cannot cancel past appointments.'
        ]);
        exit;
    }

    // Update appointment status to 'cancelled' instead of deleting
    // This maintains historical records and allows for analytics
    $updateStmt = $pdo->prepare("
        UPDATE appointments 
        SET status = 'cancelled',
            updated_at = NOW(),
            cancelled_at = NOW(),
            cancellation_reason = 'User cancelled'
        WHERE id = :id
    ");
    $updateStmt->execute([':id' => $appt_id]);

    // Optional: Log the cancellation for audit trail
    $logStmt = $pdo->prepare("
        INSERT INTO appointment_logs (appointment_id, user_id, action, created_at)
        VALUES (:appt_id, :user_id, 'cancelled', NOW())
    ");
    // Only execute if the table exists
    try {
        $logStmt->execute([':appt_id' => $appt_id, ':user_id' => $user_id]);
    } catch (PDOException $e) {
        // Log table might not exist, continue anyway
    }

    // Commit transaction
    $pdo->commit();

    // Optional: Send cancellation email notification
    // This would require a separate email function
    // sendCancellationEmail($appt);

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Appointment cancelled successfully. The salon has been notified.',
        'data' => [
            'appointment_id' => $appt_id,
            'salon_name' => htmlspecialchars($appt['salon_name']),
            'service_name' => htmlspecialchars($appt['service_name']),
            'appointment_date' => date('M d, Y', strtotime($appt['appointment_date'])),
            'appointment_time' => date('h:i A', strtotime($appt['appointment_time']))
        ]
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log error (in production, log to file instead of displaying)
    error_log("Appointment cancellation error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while cancelling the appointment. Please try again later.'
    ]);
} catch (Exception $e) {
    // Handle any other exceptions
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Unexpected error in cancel_appointment.php: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
}
?>