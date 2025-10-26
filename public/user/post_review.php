<?php
// public/user/post_review.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

// Set header for JSON response
header('Content-Type: application/json');

// Response helper function
function sendResponse($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

$user_id = $_SESSION['id'];
$salon_id = intval($_POST['salon_id'] ?? 0);
$rating = intval($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

// Validation
$errors = [];

if ($salon_id <= 0) {
    $errors[] = 'Invalid salon selected';
}

if ($rating < 1 || $rating > 5) {
    $errors[] = 'Rating must be between 1 and 5 stars';
}

if (empty($comment)) {
    $errors[] = 'Review comment is required';
}

if (strlen($comment) < 10) {
    $errors[] = 'Review must be at least 10 characters long';
}

if (strlen($comment) > 500) {
    $errors[] = 'Review cannot exceed 500 characters';
}

if (!empty($errors)) {
    sendResponse(false, implode('. ', $errors));
}

try {
    // Check if salon exists
    $stmt = $pdo->prepare("SELECT id, name, owner_id FROM salons WHERE id = ?");
    $stmt->execute([$salon_id]);
    $salon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$salon) {
        sendResponse(false, 'Salon not found');
    }

    // Prevent salon owners from reviewing their own salons
    if ($salon['owner_id'] == $user_id) {
        sendResponse(false, 'You cannot review your own salon');
    }

    // Check if user has already reviewed this salon
    $stmt = $pdo->prepare("
        SELECT id FROM reviews 
        WHERE user_id = ? AND salon_id = ?
    ");
    $stmt->execute([$user_id, $salon_id]);
    $existingReview = $stmt->fetch();

    if ($existingReview) {
        sendResponse(false, 'You have already reviewed this salon');
    }

    // Optional: Check if user has completed an appointment at this salon
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE user_id = ? AND salon_id = ? AND status = 'completed'
    ");
    $stmt->execute([$user_id, $salon_id]);
    $appointmentCheck = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($appointmentCheck['count'] == 0) {
        sendResponse(false, 'You must have a completed appointment to review this salon');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Sanitize comment (remove harmful content)
    $comment = htmlspecialchars($comment, ENT_QUOTES, 'UTF-8');

    // Insert review
    $stmt = $pdo->prepare("
        INSERT INTO reviews (user_id, salon_id, rating, comment, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $salon_id, $rating, $comment]);
    $review_id = $pdo->lastInsertId();

    // Get user info for notification
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Notify salon owner
    $stars = str_repeat('⭐', $rating);
    $notificationMsg = sprintf(
        "%s left a %s rating for your salon '%s': \"%s\"",
        $user['username'],
        $stars,
        $salon['name'],
        strlen($comment) > 50 ? substr($comment, 0, 47) . '...' : $comment
    );

    $stmtNotify = $pdo->prepare("
        INSERT INTO notifications (user_id, message, created_at) 
        VALUES (?, ?, NOW())
    ");
    $stmtNotify->execute([$salon['owner_id'], $notificationMsg]);

    // Calculate new average rating for the salon
    $stmt = $pdo->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
        FROM reviews 
        WHERE salon_id = ?
    ");
    $stmt->execute([$salon_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $avg_rating = round($stats['avg_rating'], 1);
    $total_reviews = $stats['total_reviews'];

    // Optional: Send email to salon owner
    $stmtOwner = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
    $stmtOwner->execute([$salon['owner_id']]);
    $owner = $stmtOwner->fetch(PDO::FETCH_ASSOC);

    if ($owner) {
        $subject = "New Review for {$salon['name']} - Salonora";
        $message = "Dear {$owner['username']},\n\n";
        $message .= "You have received a new review for your salon '{$salon['name']}':\n\n";
        $message .= "Rating: $stars ($rating/5)\n";
        $message .= "Customer: {$user['username']}\n";
        $message .= "Review: \"$comment\"\n\n";
        $message .= "Current Average Rating: $avg_rating/5 ($total_reviews reviews)\n\n";
        $message .= "Thank you for providing excellent service!\n\n";
        $message .= "Best regards,\nSalonora Team";
        
        $headers = "From: noreply@salonora.com\r\n";
        $headers .= "Reply-To: support@salonora.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        @mail($owner['email'], $subject, $message, $headers);
    }

    // Commit transaction
    $pdo->commit();

    // Log activity
    error_log("Review #{$review_id} posted by user #{$user_id} for salon #{$salon_id}");

    sendResponse(true, 'Thank you for your review! It has been submitted successfully.', [
        'review_id' => $review_id,
        'rating' => $rating,
        'avg_rating' => $avg_rating,
        'total_reviews' => $total_reviews
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error
    error_log("Review submission error: " . $e->getMessage());
    
    sendResponse(false, 'An error occurred while submitting your review. Please try again.');
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error
    error_log("Unexpected error during review submission: " . $e->getMessage());
    
    sendResponse(false, 'An unexpected error occurred. Please contact support.');
}
?>