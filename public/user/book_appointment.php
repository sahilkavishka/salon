<?php
// public/user/book_appointment.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

// Set header for JSON response (if AJAX) or handle regular POST
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json');
}

// Response helper function
function sendResponse($success, $message, $data = [], $isAjax = false, $redirect = null) {
    if ($isAjax) {
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    } else {
        if ($success) {
            $_SESSION['flash_success'] = $message;
        } else {
            $_SESSION['flash_error'] = $message;
        }
        header('Location: ' . ($redirect ?? '../index.php'));
        exit;
    }
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', [], $isAjax);
}

$user_id = $_SESSION['id'];
$user_role = $_SESSION['role'] ?? 'user';

// Collect and sanitize inputs
$salon_id = isset($_POST['salon_id']) ? (int)$_POST['salon_id'] : 0;
$service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
$appointment_date = trim($_POST['appointment_date'] ?? '');
$appointment_time = trim($_POST['appointment_time'] ?? '');

// Comprehensive validation
$errors = [];

if ($salon_id <= 0) {
    $errors[] = "Invalid salon selected";
}

if ($service_id <= 0) {
    $errors[] = "Invalid service selected";
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
    $errors[] = "Invalid date format";
}

if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $appointment_time)) {
    $errors[] = "Invalid time format";
}

// Validate date is not in the past
if (!empty($appointment_date) && !empty($appointment_time)) {
    $appointment_datetime = strtotime($appointment_date . ' ' . $appointment_time);
    if ($appointment_datetime < time()) {
        $errors[] = "Cannot book appointments in the past";
    }
    
    // Check if date is too far in the future (e.g., 90 days)
    $max_advance_booking = strtotime('+90 days');
    if ($appointment_datetime > $max_advance_booking) {
        $errors[] = "Cannot book more than 90 days in advance";
    }
}

// Return early if validation fails
if (!empty($errors)) {
    $redirect = "salon_details.php?id={$salon_id}";
    sendResponse(false, implode('. ', $errors), [], $isAjax, $redirect);
}

try {
    // Verify salon exists
    $stmt = $pdo->prepare("SELECT id, name, owner_id FROM salons WHERE id = ?");
    $stmt->execute([$salon_id]);
    $salon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$salon) {
        $redirect = "salon_view.php";
        sendResponse(false, 'Salon not found', [], $isAjax, $redirect);
    }

    // Prevent salon owners from booking their own salons
    if ($salon['owner_id'] == $user_id) {
        $redirect = "salon_details.php?id={$salon_id}";
        sendResponse(false, 'You cannot book appointments at your own salon', [], $isAjax, $redirect);
    }

    // Verify service exists and belongs to the salon
    $stmt = $pdo->prepare("
        SELECT id, name, price, duration 
        FROM services 
        WHERE id = ? AND salon_id = ?
    ");
    $stmt->execute([$service_id, $salon_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        $redirect = "salon_details.php?id={$salon_id}";
        sendResponse(false, 'Service not found or does not belong to this salon', [], $isAjax, $redirect);
    }

    // Check if user already has a pending appointment for the same slot
    $stmt = $pdo->prepare("
        SELECT id FROM appointments 
        WHERE user_id = ? 
        AND salon_id = ? 
        AND appointment_date = ? 
        AND appointment_time = ? 
        AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$user_id, $salon_id, $appointment_date, $appointment_time]);
    
    if ($stmt->fetch()) {
        $redirect = "salon_details.php?id={$salon_id}";
        sendResponse(false, 'You already have an appointment for this time slot', [], $isAjax, $redirect);
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Insert appointment
    $stmt = $pdo->prepare("
        INSERT INTO appointments 
        (user_id, salon_id, service_id, appointment_date, appointment_time, status, created_at) 
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([
        $user_id,
        $salon_id,
        $service_id,
        $appointment_date,
        $appointment_time
    ]);
    
    $appointment_id = $pdo->lastInsertId();

    // Get user information
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Create detailed notification message for salon owner
    $formatted_date = date('l, F j, Y', strtotime($appointment_date));
    $formatted_time = date('g:i A', strtotime($appointment_time));
    
    $notification_msg = sprintf(
        "New appointment request from %s for %s on %s at %s. Please review and confirm.",
        $user['username'],
        $service['name'],
        $formatted_date,
        $formatted_time
    );

    // Insert notification for salon owner
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, message, created_at) 
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$salon['owner_id'], $notification_msg]);

    // Send email to salon owner
    $stmt = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
    $stmt->execute([$salon['owner_id']]);
    $owner = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($owner) {
        $subject = "New Appointment Request - {$salon['name']}";
        $message = "Dear {$owner['username']},\n\n";
        $message .= "You have received a new appointment request:\n\n";
        $message .= "Salon: {$salon['name']}\n";
        $message .= "Service: {$service['name']}\n";
        $message .= "Price: Rs " . number_format($service['price'], 2) . "\n";
        $message .= "Duration: {$service['duration']} minutes\n";
        $message .= "Customer: {$user['username']}\n";
        $message .= "Email: {$user['email']}\n";
        $message .= "Date: {$formatted_date}\n";
        $message .= "Time: {$formatted_time}\n\n";
        $message .= "Please log in to your dashboard to confirm or reject this appointment.\n\n";
        $message .= "Thank you,\nSalonora Team";
        
        $headers = "From: noreply@salonora.com\r\n";
        $headers .= "Reply-To: support@salonora.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        @mail($owner['email'], $subject, $message, $headers);
    }

    // Commit transaction
    $pdo->commit();

    // Log activity
    error_log("Appointment #{$appointment_id} created by user #{$user_id} for salon #{$salon_id}");

    // Success response
    $success_message = "Appointment request sent successfully! The salon owner will review and confirm your booking.";
    $redirect = "salon_details.php?id={$salon_id}";
    
    sendResponse(true, $success_message, [
        'appointment_id' => $appointment_id,
        'salon_name' => $salon['name'],
        'service_name' => $service['name'],
        'date' => $formatted_date,
        'time' => $formatted_time
    ], $isAjax, $redirect);

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error
    error_log("Appointment booking error: " . $e->getMessage());
    
    $redirect = "salon_details.php?id={$salon_id}";
    sendResponse(false, 'An error occurred while booking your appointment. Please try again.', [], $isAjax, $redirect);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error
    error_log("Unexpected error during appointment booking: " . $e->getMessage());
    
    $redirect = "salon_details.php?id={$salon_id}";
    sendResponse(false, 'An unexpected error occurred. Please contact support.', [], $isAjax, $redirect);
}
?>