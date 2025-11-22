<?php
// public/owner/appointment_action.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

// Only allow owner role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header('Location: ../login.php');
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: appointments.php');
    exit;
}

// CSRF token validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = 'Invalid request. Please try again.';
    header('Location: appointments.php');
    exit;
}

// Get and validate input
$appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($appointment_id <= 0 || !in_array($action, ['confirm', 'reject', 'complete'])) {
    $_SESSION['error_message'] = 'Invalid request parameters.';
    header('Location: appointments.php');
    exit;
}

try {
    // Verify the appointment belongs to the owner's salon
    $stmt = $pdo->prepare("
        SELECT a.*, s.name as salon_name, u.username, u.email 
        FROM appointments a
        JOIN salons sal ON sal.id = a.salon_id
        JOIN services s ON s.id = a.service_id
        JOIN users u ON u.id = a.user_id
        WHERE a.id = :appointment_id AND sal.owner_id = :owner_id
    ");
    $stmt->execute([
        ':appointment_id' => $appointment_id,
        ':owner_id' => $_SESSION['id']
    ]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        $_SESSION['error_message'] = 'Appointment not found or you do not have permission to modify it.';
        header('Location: appointments.php');
        exit;
    }

    // Process the action
    switch ($action) {
        case 'confirm':
            if ($appointment['status'] !== 'pending') {
                $_SESSION['error_message'] = 'Only pending appointments can be confirmed.';
                break;
            }

            $stmt = $pdo->prepare("UPDATE appointments SET status = 'confirmed', updated_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => $appointment_id]);
            
            $_SESSION['success_message'] = 'Appointment confirmed successfully! Customer will be notified.';
            
            // Optional: Send email notification to customer
            // Uncomment and configure your email settings to enable
            /*
            $to = $appointment['email'];
            $subject = 'Appointment Confirmed - ' . $appointment['salon_name'];
            $message = "Dear " . $appointment['username'] . ",\n\n";
            $message .= "Your appointment has been confirmed!\n\n";
            $message .= "Details:\n";
            $message .= "Service: " . $appointment['name'] . "\n";
            $message .= "Date: " . date('F j, Y', strtotime($appointment['appointment_date'])) . "\n";
            $message .= "Time: " . date('h:i A', strtotime($appointment['appointment_time'])) . "\n";
            $message .= "\nThank you for choosing " . $appointment['salon_name'] . "!";
            $headers = 'From: noreply@salonora.com' . "\r\n" .
                       'Reply-To: noreply@salonora.com' . "\r\n" .
                       'X-Mailer: PHP/' . phpversion();
            mail($to, $subject, $message, $headers);
            */
            break;

        case 'reject':
            if ($appointment['status'] !== 'pending') {
                $_SESSION['error_message'] = 'Only pending appointments can be rejected.';
                break;
            }

            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = 'cancelled', 
                    updated_at = NOW() 
                WHERE id = :id
            ");
            $stmt->execute([':id' => $appointment_id]);
            
            $_SESSION['success_message'] = 'Appointment request rejected.';
            
            // Optional: Send email notification to customer
            /*
            $to = $appointment['email'];
            $subject = 'Appointment Request Update - ' . $appointment['salon_name'];
            $message = "Dear " . $appointment['username'] . ",\n\n";
            $message .= "Unfortunately, we are unable to confirm your appointment request.\n\n";
            $message .= "Please contact us directly or try booking a different time slot.\n\n";
            $message .= "Thank you for your understanding.";
            $headers = 'From: noreply@salonora.com' . "\r\n" .
                       'Reply-To: noreply@salonora.com' . "\r\n" .
                       'X-Mailer: PHP/' . phpversion();
            mail($to, $subject, $message, $headers);
            */
            break;

        case 'complete':
            if ($appointment['status'] !== 'confirmed') {
                $_SESSION['error_message'] = 'Only confirmed appointments can be marked as completed.';
                break;
            }

            $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed', updated_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => $appointment_id]);
            
            $_SESSION['success_message'] = 'Appointment marked as completed successfully!';
            
            // Optional: Send thank you email to customer
            /*
            $to = $appointment['email'];
            $subject = 'Thank You - ' . $appointment['salon_name'];
            $message = "Dear " . $appointment['username'] . ",\n\n";
            $message .= "Thank you for visiting " . $appointment['salon_name'] . "!\n\n";
            $message .= "We hope you enjoyed your " . $appointment['name'] . " service.\n\n";
            $message .= "We'd love to see you again soon!\n\n";
            $message .= "Best regards,\n";
            $message .= $appointment['salon_name'];
            $headers = 'From: noreply@salonora.com' . "\r\n" .
                       'Reply-To: noreply@salonora.com' . "\r\n" .
                       'X-Mailer: PHP/' . phpversion();
            mail($to, $subject, $message, $headers);
            */
            break;

        default:
            $_SESSION['error_message'] = 'Invalid action.';
    }

} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Database error: Unable to process your request.';
    error_log('Appointment action error: ' . $e->getMessage());
}

// Redirect back to appointments page
header('Location: appointments.php');
exit;
?>