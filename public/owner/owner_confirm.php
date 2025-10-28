<?php
// public/owner/owner_confirm.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

// Only allow owner role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    $_SESSION['error_message'] = 'Unauthorized access.';
    header('Location: ../login.php');
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method.';
    header('Location: appointments.php');
    exit;
}

$owner_id = $_SESSION['id'];
$appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

// Validate inputs
if (!$appointment_id || !$action) {
    $_SESSION['error_message'] = 'Invalid appointment ID or action.';
    header('Location: appointments.php');
    exit;
}

// Validate action
$valid_actions = ['confirm', 'reject', 'complete'];
if (!in_array($action, $valid_actions)) {
    $_SESSION['error_message'] = 'Invalid action specified.';
    header('Location: appointments.php');
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Verify appointment exists and belongs to owner's salon
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            s.owner_id,
            s.name AS salon_name,
            srv.name AS service_name,
            u.username AS user_name,
            u.email AS user_email
        FROM appointments a
        JOIN salons s ON s.id = a.salon_id
        JOIN services srv ON srv.id = a.service_id
        JOIN users u ON u.id = a.user_id
        WHERE a.id = :appointment_id
        FOR UPDATE
    ");
    $stmt->execute([':appointment_id' => $appointment_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if appointment exists
    if (!$appointment) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Appointment not found.';
        header('Location: appointments.php');
        exit;
    }

    // Verify owner owns this salon
    if ($appointment['owner_id'] != $owner_id) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'You do not have permission to manage this appointment.';
        header('Location: appointments.php');
        exit;
    }

    $current_status = $appointment['status'];
    $new_status = '';
    $success_message = '';

    // Handle different actions
    switch ($action) {
        case 'confirm':
            // Can only confirm pending appointments
            if ($current_status !== 'pending') {
                $pdo->rollBack();
                $_SESSION['error_message'] = 'Only pending appointments can be confirmed.';
                header('Location: appointments.php');
                exit;
            }

            // Check if the appointment time is still in the future
            $appointmentDateTime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
            if ($appointmentDateTime < time()) {
                $pdo->rollBack();
                $_SESSION['error_message'] = 'Cannot confirm past appointments.';
                header('Location: appointments.php');
                exit;
            }

            $new_status = 'confirmed';
            $success_message = 'Appointment confirmed successfully! Customer has been notified.';
            break;

        case 'reject':
            // Can only reject pending appointments
            if ($current_status !== 'pending') {
                $pdo->rollBack();
                $_SESSION['error_message'] = 'Only pending appointments can be rejected.';
                header('Location: appointments.php');
                exit;
            }

            $new_status = 'rejected';
            $success_message = 'Appointment rejected. Customer has been notified.';
            break;

        case 'complete':
            // Can only complete confirmed appointments
            if ($current_status !== 'confirmed') {
                $pdo->rollBack();
                $_SESSION['error_message'] = 'Only confirmed appointments can be marked as completed.';
                header('Location: appointments.php');
                exit;
            }

            $new_status = 'completed';
            $success_message = 'Appointment marked as completed successfully!';
            break;
    }

    // Update appointment status
    $updateStmt = $pdo->prepare("
        UPDATE appointments 
        SET status = :status, 
            updated_at = NOW()
        WHERE id = :appointment_id
    ");
    $updateStmt->execute([
        ':status' => $new_status,
        ':appointment_id' => $appointment_id
    ]);

    // Log the action (optional - only if log table exists)
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO appointment_logs (appointment_id, user_id, action, created_at)
            VALUES (:appointment_id, :user_id, :action, NOW())
        ");
        $logStmt->execute([
            ':appointment_id' => $appointment_id,
            ':user_id' => $owner_id,
            ':action' => $action
        ]);
    } catch (PDOException $e) {
        // Log table might not exist, continue anyway
    }

    // Commit transaction
    $pdo->commit();

    // Optional: Send email notification to customer
    // sendAppointmentNotification($appointment, $action);

    // Set success message
    $_SESSION['success_message'] = $success_message;

    // Optional: Return JSON for AJAX requests
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $success_message,
            'appointment_id' => $appointment_id,
            'new_status' => $new_status
        ]);
        exit;
    }

} catch (PDOException $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log error
    error_log("Appointment action error: " . $e->getMessage());

    $_SESSION['error_message'] = 'An error occurred while processing the appointment. Please try again.';

    // Return JSON error for AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred. Please try again.'
        ]);
        exit;
    }
} catch (Exception $e) {
    // Handle other exceptions
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Unexpected error in owner_confirm.php: " . $e->getMessage());
    $_SESSION['error_message'] = 'An unexpected error occurred. Please try again.';
}

// Redirect back to appointments page
header('Location: appointments.php');
exit;

/**
 * Optional: Send email notification to customer
 * Implement this function based on your email service
 */
function sendAppointmentNotification($appointment, $action) {
    // Example implementation:
    // $to = $appointment['user_email'];
    // $subject = "Appointment " . ucfirst($action) . " - " . $appointment['salon_name'];
    // $message = "Your appointment has been " . $action . "ed.";
    // mail($to, $subject, $message);
}
?>