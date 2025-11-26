<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

if (!isset($_GET['salon_id']) || !is_numeric($_GET['salon_id'])) {
    $_SESSION['error_message'] = 'Salon not specified.';
    header('Location: salon_view.php');
    exit;
}

$salon_id = intval($_GET['salon_id']);
$user_id = $_SESSION['id'];

// Fetch salon details with additional info
$stmt = $pdo->prepare("
    SELECT s.id, s.name, s.address, s.opening_time, s.closing_time, s.slot_duration, s.phone, s.email,
           COUNT(DISTINCT a.id) as total_bookings,
           AVG(r.rating) as avg_rating
    FROM salons s
    LEFT JOIN appointments a ON s.id = a.salon_id AND a.status = 'completed'
    LEFT JOIN reviews r ON s.id = r.salon_id
    WHERE s.id = ?
    GROUP BY s.id
");
$stmt->execute([$salon_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$salon) {
    $_SESSION['error_message'] = 'Salon not found.';
    header('Location: salon_view.php');
    exit;
}

// Check if salon is open today
$current_day = date('l');
$is_open_today = true; // You might want to check against salon's operating days

// Fetch services with categories
$serviceStmt = $pdo->prepare("
    SELECT id, name, price, duration, description, category 
    FROM services 
    WHERE salon_id = ? AND is_active = 1
    ORDER BY category ASC, price ASC
");
$serviceStmt->execute([$salon_id]);
$services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);

// Group services by category
$grouped_services = [];
foreach ($services as $service) {
    $category = $service['category'] ?? 'General';
    $grouped_services[$category][] = $service;
}

// Check for existing pending appointments
$existingStmt = $pdo->prepare("
    SELECT COUNT(*) as pending_count 
    FROM appointments 
    WHERE user_id = ? AND salon_id = ? AND status = 'pending'
");
$existingStmt->execute([$user_id, $salon_id]);
$existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
$has_pending = $existing['pending_count'] > 0;

// Submit booking
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid security token. Please refresh and try again.";
    }

    $service_id = intval($_POST['service_id'] ?? 0);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    // Validation
    if ($service_id <= 0) $errors[] = "Please select a service.";
    if ($appointment_date == "") $errors[] = "Please select a date.";
    if ($appointment_time == "") $errors[] = "Please select a time slot.";

    // Date validation
    $today = date("Y-m-d");
    $max_date = date("Y-m-d", strtotime("+30 days"));
    
    if ($appointment_date < $today) {
        $errors[] = "Cannot book appointments for past dates.";
    }
    
    if ($appointment_date > $max_date) {
        $errors[] = "Cannot book appointments more than 30 days in advance.";
    }

    // Check if date is weekend (if salon is closed on weekends)
    $day_of_week = date('N', strtotime($appointment_date));
    // Add your weekend check logic here if needed

    // Verify service belongs to salon
    if (empty($errors)) {
        $serviceCheck = $pdo->prepare("SELECT id FROM services WHERE id = ? AND salon_id = ? AND is_active = 1");
        $serviceCheck->execute([$service_id, $salon_id]);
        if (!$serviceCheck->fetch()) {
            $errors[] = "Invalid service selected.";
        }
    }

    // Verify slot is still available
    if (empty($errors)) {
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM appointments 
            WHERE salon_id = ? AND appointment_date = ? AND appointment_time = ? 
            AND status NOT IN ('cancelled', 'rejected')
        ");
        $checkStmt->execute([$salon_id, $appointment_date, $appointment_time]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing['count'] > 0) {
            $errors[] = "This time slot is no longer available. Please select another slot.";
        }
    }

    // Check if time is within salon operating hours
    if (empty($errors)) {
        $time_24h = date('H:i:s', strtotime($appointment_time));
        if ($time_24h < $salon['opening_time'] || $time_24h >= $salon['closing_time']) {
            $errors[] = "Selected time is outside salon operating hours.";
        }
    }

    // Prevent double booking by same user
    if (empty($errors)) {
        $doubleBookCheck = $pdo->prepare("
            SELECT COUNT(*) as count FROM appointments 
            WHERE user_id = ? AND appointment_date = ? AND appointment_time = ?
            AND status NOT IN ('cancelled', 'rejected')
        ");
        $doubleBookCheck->execute([$user_id, $appointment_date, $appointment_time]);
        $doubleBook = $doubleBookCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($doubleBook['count'] > 0) {
            $errors[] = "You already have an appointment at this time.";
        }
    }

    // Sanitize notes
    $notes = htmlspecialchars($notes, ENT_QUOTES, 'UTF-8');
    if (strlen($notes) > 500) {
        $notes = substr($notes, 0, 500);
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert appointment
            $insertStmt = $pdo->prepare("
                INSERT INTO appointments 
                (salon_id, user_id, service_id, appointment_date, appointment_time, notes, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
            ");

            $insertStmt->execute([
                $salon_id,
                $user_id,
                $service_id,
                $appointment_date,
                $appointment_time,
                $notes
            ]);

            $appointment_id = $pdo->lastInsertId();

            // Log the appointment creation
            $logStmt = $pdo->prepare("
                INSERT INTO appointment_log 
                (appointment_id, old_status, new_status, changed_by, action_type, changed_at)
                VALUES (?, NULL, 'pending', ?, 'created', NOW())
            ");
            $logStmt->execute([$appointment_id, $user_id]);

            // Optional: Send notification email/SMS
            // sendBookingConfirmation($user_id, $appointment_id);

            $pdo->commit();

            $_SESSION['success_message'] = "🎉 Appointment booked successfully! Booking ID: #" . $appointment_id . ". We'll send you a confirmation soon.";
            header("Location: my_appointments.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Booking error: " . $e->getMessage());
            $errors[] = "An error occurred while processing your booking. Please try again.";
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - <?= htmlspecialchars($salon['name']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary-pink: #ff6b9d;
    --primary-purple: #8b5cf6;
    --dark-purple: #6b21a8;
    --light-pink: #fce7f3;
    --gradient-primary: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
    --gradient-secondary: linear-gradient(135deg, #fce7f3 0%, #ede9fe 100%);
    --shadow-sm: 0 2px 8px rgba(139, 92, 246, 0.1);
    --shadow-md: 0 4px 16px rgba(139, 92, 246, 0.15);
    --shadow-lg: 0 8px 32px rgba(139, 92, 246, 0.2);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(to bottom, #eba0c27c, #f8f0fc9d);
    min-height: 100vh;
    padding: 20px 0;
}

.page-header {
    background: var(--gradient-primary);
    color: white;
    padding: 3rem 0 4rem;
    margin-bottom: 2rem;
    border-radius: 0 0 30px 30px;
    box-shadow: var(--shadow-lg);
    margin-top: -20px;
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="2" fill="white" opacity="0.1"/></svg>');
    opacity: 0.3;
}

.page-header h2 {
    font-weight: 800;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
    position: relative;
    z-index: 1;
}

.page-header p {
    opacity: 0.95;
    font-size: 1.1rem;
    position: relative;
    z-index: 1;
}

.salon-rating {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255,255,255,0.2);
    padding: 0.5rem 1rem;
    border-radius: 50px;
    margin-top: 0.5rem;
}

.booking-container {
    max-width: 1200px;
    margin: 0 auto;
}

.booking-card {
    background: white;
    border-radius: 25px;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    margin-bottom: 2rem;
    transition: transform 0.3s ease;
}

.booking-card:hover {
    transform: translateY(-5px);
}

.card-header-custom {
    background: var(--gradient-primary);
    color: white;
    padding: 1.5rem 2rem;
    border: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.card-header-custom h5 {
    font-weight: 700;
    margin: 0;
    font-size: 1.3rem;
}

.step-badge {
    background: rgba(255,255,255,0.3);
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
}

.card-body-custom {
    padding: 2rem;
}

/* Alert Banner */
.alert-banner {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-left: 5px solid #f59e0b;
    border-radius: 15px;
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.alert-banner i {
    font-size: 1.5rem;
    color: #92400e;
}

/* Salon Info Card */
.salon-info-card {
    background: var(--gradient-secondary);
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border-left: 5px solid var(--primary-purple);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.info-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-purple);
    font-size: 1.2rem;
}

.info-text {
    flex: 1;
}

.info-label {
    font-size: 0.85rem;
    color: #666;
    display: block;
}

.info-value {
    font-weight: 600;
    color: var(--dark-purple);
    font-size: 1rem;
}

/* Step Progress */
.step-progress {
    display: flex;
    justify-content: space-between;
    margin: 2rem 0 3rem;
    position: relative;
    padding: 0 1rem;
}

.step-progress::before {
    content: '';
    position: absolute;
    top: 30px;
    left: 15%;
    right: 15%;
    height: 3px;
    background: #e5e7eb;
    z-index: 0;
}

.progress-line {
    position: absolute;
    top: 30px;
    left: 15%;
    height: 3px;
    background: var(--gradient-primary);
    z-index: 1;
    transition: width 0.5s ease;
    width: 0%;
}

.step-item {
    flex: 1;
    text-align: center;
    position: relative;
    z-index: 2;
}

.step-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: white;
    border: 3px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.75rem;
    font-size: 1.2rem;
    font-weight: 700;
    color: #9ca3af;
    transition: all 0.3s ease;
    position: relative;
}

.step-item.active .step-circle {
    border-color: var(--primary-purple);
    background: var(--gradient-primary);
    color: white;
    box-shadow: 0 0 0 8px rgba(139, 92, 246, 0.1);
    transform: scale(1.1);
    animation: pulse 2s infinite;
}

.step-item.completed .step-circle {
    border-color: #10b981;
    background: #10b981;
    color: white;
}

.step-circle i {
    font-size: 1.5rem;
}

.step-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #9ca3af;
    transition: color 0.3s ease;
}

.step-item.active .step-label {
    color: var(--primary-purple);
}

.step-item.completed .step-label {
    color: #10b981;
}

@keyframes pulse {
    0%, 100% {
        box-shadow: 0 0 0 8px rgba(139, 92, 246, 0.1);
    }
    50% {
        box-shadow: 0 0 0 12px rgba(139, 92, 246, 0.2);
    }
}

/* Service Categories */
.category-section {
    margin-bottom: 2rem;
}

.category-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #e5e7eb;
}

.category-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--gradient-primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
}

.category-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--dark-purple);
    margin: 0;
}

/* Service Cards */
.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.service-card {
    border: 3px solid #e5e7eb;
    border-radius: 20px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: white;
    position: relative;
    overflow: hidden;
}

.service-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: var(--gradient-primary);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.service-card:hover {
    border-color: var(--primary-purple);
    transform: translateY(-5px);
    box-shadow: var(--shadow-md);
}

.service-card:hover::before {
    transform: scaleX(1);
}

.service-card.selected {
    border-color: var(--primary-purple);
    background: var(--gradient-secondary);
    box-shadow: var(--shadow-lg);
    transform: translateY(-5px);
}

.service-card.selected::before {
    transform: scaleX(1);
}

.service-radio {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 25px;
    height: 25px;
    accent-color: var(--primary-purple);
    cursor: pointer;
}

.service-name {
    font-weight: 700;
    color: var(--dark-purple);
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    padding-right: 2rem;
}

.service-description {
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 1rem;
    line-height: 1.5;
}

.service-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 2px dashed #e5e7eb;
}

.service-duration {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #666;
    font-size: 0.9rem;
}

.service-price {
    font-weight: 800;
    color: var(--primary-pink);
    font-size: 1.3rem;
}

/* Quick Select Chips */
.quick-select {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.quick-chip {
    padding: 0.5rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 50px;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    font-weight: 600;
}

.quick-chip:hover {
    border-color: var(--primary-purple);
    background: var(--gradient-secondary);
}

.quick-chip.active {
    border-color: var(--primary-purple);
    background: var(--gradient-primary);
    color: white;
}

/* Date Picker */
.date-picker-wrapper {
    position: relative;
    margin-top: 1rem;
}

.date-input {
    width: 100%;
    padding: 1rem 1rem 1rem 3.5rem;
    border: 3px solid #e5e7eb;
    border-radius: 15px;
    font-size: 1rem;
    font-weight: 600;
    color: var(--dark-purple);
    transition: all 0.3s ease;
    cursor: pointer;
}

.date-input:focus {
    outline: none;
    border-color: var(--primary-purple);
    box-shadow: 0 0 0 5px rgba(139, 92, 246, 0.1);
}

.date-icon {
    position: absolute;
    left: 1.25rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--primary-purple);
    font-size: 1.3rem;
    pointer-events: none;
}

/* Calendar shortcuts */
.calendar-shortcuts {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.shortcut-btn {
    flex: 1;
    padding: 0.75rem;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.85rem;
    font-weight: 600;
}

.shortcut-btn:hover {
    border-color: var(--primary-purple);
    background: var(--gradient-secondary);
}

/* Time Slots */
.slots-container {
    margin-top: 1.5rem;
}

.time-period {
    margin-bottom: 2rem;
}

.period-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #e5e7eb;
}

.period-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
}

.morning .period-icon {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
}

.afternoon .period-icon {
    background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%);
    color: #9a3412;
}

.evening .period-icon {
    background: linear-gradient(135deg, #ddd6fe 0%, #c4b5fd 100%);
    color: #5b21b6;
}

.period-title {
    font-weight: 700;
    color: var(--dark-purple);
    font-size: 1.1rem;
    margin: 0;
}

.period-time {
    font-size: 0.85rem;
    color: #666;
}

.slots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 0.75rem;
}

.slot-btn {
    padding: 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    background: white;
    font-weight: 600;
    color: #374151;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    position: relative;
}

.slot-btn:hover:not(.booked):not(.disabled) {
    border-color: var(--primary-purple);
    background: var(--gradient-secondary);
    transform: translateY(-3px);
    box-shadow: var(--shadow-sm);
}

.slot-btn.selected {
    border-color: var(--primary-purple);
    background: var(--gradient-primary);
    color: white;
    box-shadow: var(--shadow-md);
    transform: translateY(-3px);
}

.slot-btn.booked {
    background: #fee2e2;
    border-color: #fecaca;
    color: #991b1b;
    cursor: not-allowed;
    opacity: 0.7;
}

.slot-btn.disabled {
    background: #f3f4f6;
    color: #9ca3af;
    cursor: not-allowed;
    opacity: 0.5;
}

.slot-time {
    font-size: 1rem;
    font-weight: 700;
}

.slot-status {
    font-size: 0.75rem;
    font-weight: 500;
}

/* Loading State */
.loading-container {
    text-align: center;
    padding: 3rem;
    display: none;
}

.loading-container.show {
    display: block;
}

.spinner {
    width: 60px;
    height: 60px;
    border: 5px solid #f3f4f6;
    border-top-color: var(--primary-purple);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #9ca3af;
}

.empty-state-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* Booking Summary */
.booking-summary {
    background: linear-gradient(135deg, #ec337170 0%, #9b27b077 100%);
    border-radius: 20px;
    padding: 2rem;
    margin-top: 2rem;
    border: 3px solid var(--primary-purple);
    display: none;
    animation: slideIn 0.5s ease-out;
}

.booking-summary.show {
    display: block;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.summary-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px dashed var(--primary-purple);
}

.summary-grid {
    display: grid;
    gap: 1rem;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: white;
    border-radius: 12px;
}

.summary-total {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    margin-top: 1rem;
    border: 2px solid var(--primary-purple);
}

.summary-total .summary-value {
    font-size: 1.8rem;
    color: var(--primary-pink);
}

/* Notes */
.notes-textarea {
    width: 100%;
    padding: 1rem;
    border: 3px solid #e5e7eb;
    border-radius: 15px;
    resize: vertical;
    min-height: 100px;
    font-size: 1rem;
    transition: all 0.3s ease;
    font-family: 'Poppins', sans-serif;
}

.notes-textarea:focus {
    outline: none;
    border-color: var(--primary-purple);
    box-shadow: 0 0 0 5px rgba(139, 92, 246, 0.1);
}

.char-counter {
    text-align: right;
    font-size: 0.85rem;
    color: #9ca3af;
    margin-top: 0.5rem;
}

/* View Toggle */
.view-toggle {
    display: flex;
    gap: 0.5rem;
    background: #f3f4f6;
    padding: 0.5rem;
    border-radius: 12px;
    width: fit-content;
}

.toggle-btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    background: transparent;
    color: #6b7280;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.toggle-btn:hover {
    color: var(--primary-purple);
}

.toggle-btn.active {
    background: white;
    color: var(--primary-purple);
    box-shadow: var(--shadow-sm);
}

/* Calendar View */
.calendar-view {
    margin-top: 1.5rem;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: var(--gradient-secondary);
    border-radius: 15px;
}

.calendar-month {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--dark-purple);
    margin: 0;
}

.calendar-nav-btn {
    width: 40px;
    height: 40px;
    border: none;
    border-radius: 10px;
    background: white;
    color: var(--primary-purple);
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--shadow-sm);
}

.calendar-nav-btn:hover {
    background: var(--primary-purple);
    color: white;
    transform: scale(1.1);
}

.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.weekday {
    text-align: center;
    font-weight: 600;
    color: #6b7280;
    padding: 0.5rem;
    font-size: 0.9rem;
}

.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.5rem;
}

.calendar-day {
    aspect-ratio: 1;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
    position: relative;
    padding: 0.5rem;
}

.calendar-day:hover:not(.disabled):not(.other-month) {
    border-color: var(--primary-purple);
    transform: translateY(-3px);
    box-shadow: var(--shadow-sm);
}

.calendar-day.disabled {
    background: #f9fafb;
    color: #d1d5db;
    cursor: not-allowed;
    opacity: 0.5;
}

.calendar-day.other-month {
    color: #d1d5db;
    background: #fafafa;
}

.calendar-day.today {
    border-color: var(--primary-purple);
    font-weight: 700;
}

.calendar-day.selected {
    background: var(--gradient-primary);
    color: white;
    border-color: var(--primary-purple);
    transform: scale(1.05);
    box-shadow: var(--shadow-md);
}

.calendar-day.has-booking {
    background: linear-gradient(135deg, #c084fc 0%, #a855f7 100%);
    color: white;
    border-color: var(--primary-purple);
}

.day-number {
    font-size: 1.1rem;
    font-weight: 600;
}

.day-indicator {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    margin-top: 0.25rem;
}

.day-indicator.available {
    background: #10b981;
}

.day-indicator.limited {
    background: #fbbf24;
}

.day-indicator.full {
    background: #ef4444;
}

.calendar-day .slot-info {
    font-size: 0.7rem;
    margin-top: 0.25rem;
    opacity: 0.8;
}

.calendar-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    justify-content: center;
    margin-top: 1.5rem;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 12px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #6b7280;
}

.legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

/* Picker View */
.picker-view {
    margin-top: 1.5rem;
}

.date-picker-wrapper {
    position: relative;
    margin-top: 1rem;
}

.date-input {
    width: 100%;
    padding: 1rem 1rem 1rem 3.5rem;
    border: 3px solid #e5e7eb;
    border-radius: 15px;
    font-size: 1rem;
    font-weight: 600;
    color: var(--dark-purple);
    transition: all 0.3s ease;
    cursor: pointer;
}

.date-input:focus {
    outline: none;
    border-color: var(--primary-purple);
    box-shadow: 0 0 0 5px rgba(139, 92, 246, 0.1);
}

.date-icon {
    position: absolute;
    left: 1.25rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--primary-purple);
    font-size: 1.3rem;
    pointer-events: none;
}

/* Calendar shortcuts */
.calendar-shortcuts {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.shortcut-btn {
    flex: 1;
    padding: 0.75rem;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.85rem;
    font-weight: 600;
}

.shortcut-btn:hover {
    border-color: var(--primary-purple);
    background: var(--gradient-secondary);
}

.char-counter {
    text-align: right;
    font-size: 0.85rem;
    color: #9ca3af;
    margin-top: 0.5rem;
}

/* Buttons */
.btn-book {
    width: 100%;
    padding: 1.25rem;
    background: var(--gradient-primary);
    border: none;
    border-radius: 15px;
    color: white;
    font-size: 1.2rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    transition: all 0.3s ease;
    box-shadow: var(--shadow-md);
}

.btn-book:hover:not(:disabled) {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}

.btn-book:disabled {
    background: #9ca3af;
    cursor: not-allowed;
    opacity: 0.6;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--primary-purple);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.back-link:hover {
    gap: 0.75rem;
}

/* Alerts */
.alert {
    border-radius: 15px;
    border: none;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    animation: slideDown 0.5s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .services-grid {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .slots-grid {
        grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    }
}
</style>
</head>
<body>

<div class="page-header">
    <div class="container text-center">
        <h2><i class="fas fa-calendar-check"></i> Book Your Appointment</h2>
        <p class="mb-2"><?= htmlspecialchars($salon['name']) ?></p>
        <?php if ($salon['avg_rating']): ?>
        <div class="salon-rating">
            <i class="fas fa-star"></i>
            <span><?= number_format($salon['avg_rating'], 1) ?></span>
            <span>(<?= $salon['total_bookings'] ?> bookings)</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="container booking-container">
    
    <?php if ($has_pending): ?>
    <div class="alert-banner">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>Notice:</strong> You already have a pending appointment with this salon. 
            <a href="my_appointments.php" style="color: #92400e; text-decoration: underline;">View Appointments</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Salon Information -->
    <div class="salon-info-card">
        <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Salon Information</h5>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div class="info-text">
                    <span class="info-label">Location</span>
                    <span class="info-value"><?= htmlspecialchars($salon['address']) ?></span>
                </div>
            </div>
            <div class="info-item">
                <div class="info-icon"><i class="fas fa-clock"></i></div>
                <div class="info-text">
                    <span class="info-label">Operating Hours</span>
                    <span class="info-value">
                        <?= date('g:i A', strtotime($salon['opening_time'])) ?> - 
                        <?= date('g:i A', strtotime($salon['closing_time'])) ?>
                    </span>
                </div>
            </div>
            <div class="info-item">
                <div class="info-icon"><i class="fas fa-hourglass-split"></i></div>
                <div class="info-text">
                    <span class="info-label">Slot Duration</span>
                    <span class="info-value"><?= $salon['slot_duration'] ?> minutes</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Step Progress -->
    <div class="step-progress">
        <div class="progress-line" id="progressLine"></div>
        <div class="step-item active" id="step1">
            <div class="step-circle"><i class="fas fa-cut"></i></div>
            <div class="step-label">Choose Service</div>
        </div>
        <div class="step-item" id="step2">
            <div class="step-circle"><i class="fas fa-calendar-alt"></i></div>
            <div class="step-label">Pick Date</div>
        </div>
        <div class="step-item" id="step3">
            <div class="step-circle"><i class="fas fa-clock"></i></div>
            <div class="step-label">Select Time</div>
        </div>
        <div class="step-item" id="step4">
            <div class="step-circle"><i class="fas fa-check-circle"></i></div>
            <div class="step-label">Confirm</div>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <div class="d-flex align-items-start">
                <i class="fas fa-exclamation-triangle me-3 mt-1" style="font-size: 1.5rem;"></i>
                <div>
                    <strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" id="bookingForm">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <!-- Step 1: Select Service -->
        <div class="booking-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-cut me-2"></i>Step 1: Select Your Service</h5>
                <span class="step-badge">1 of 4</span>
            </div>
            <div class="card-body-custom">
                <?php if (empty($services)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fas fa-inbox"></i></div>
                        <div class="empty-state-title">No Services Available</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($grouped_services as $category => $category_services): ?>
                    <div class="category-section">
                        <div class="category-header">
                            <div class="category-icon">
                                <i class="fas fa-scissors"></i>
                            </div>
                            <h6 class="category-title"><?= htmlspecialchars($category) ?></h6>
                        </div>
                        <div class="services-grid">
                            <?php foreach ($category_services as $s): ?>
                                <label for="service_<?= $s['id'] ?>" class="service-card">
                                    <input type="radio" 
                                           name="service_id" 
                                           value="<?= $s['id'] ?>" 
                                           id="service_<?= $s['id'] ?>"
                                           class="service-radio"
                                           data-price="<?= $s['price'] ?>"
                                           data-duration="<?= $s['duration'] ?>"
                                           data-name="<?= htmlspecialchars($s['name']) ?>"
                                           required>
                                    <div class="service-name"><?= htmlspecialchars($s['name']) ?></div>
                                    <?php if (!empty($s['description'])): ?>
                                        <div class="service-description"><?= htmlspecialchars($s['description']) ?></div>
                                    <?php endif; ?>
                                    <div class="service-details">
                                        <div class="service-duration">
                                            <i class="fas fa-clock"></i>
                                            <span><?= $s['duration'] ?> min</span>
                                        </div>
                                        <div class="service-price">Rs <?= number_format($s['price'], 2) ?></div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Step 2: Select Date -->
        <div class="booking-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-calendar-alt me-2"></i>Step 2: Choose Your Preferred Date</h5>
                <span class="step-badge">2 of 4</span>
            </div>
            <div class="card-body-custom">
                
                <!-- View Toggle -->
                <div class="view-toggle mb-3">
                    <button type="button" class="toggle-btn active" onclick="toggleView('calendar')" id="calendarViewBtn">
                        <i class="fas fa-calendar"></i> Calendar View
                    </button>
                    <button type="button" class="toggle-btn" onclick="toggleView('picker')" id="pickerViewBtn">
                        <i class="fas fa-list"></i> Date Picker
                    </button>
                </div>

                <!-- Calendar View -->
                <div id="calendarView" class="calendar-view">
                    <div class="calendar-header">
                        <button type="button" class="calendar-nav-btn" onclick="changeMonth(-1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <h5 class="calendar-month" id="calendarMonth"></h5>
                        <button type="button" class="calendar-nav-btn" onclick="changeMonth(1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    
                    <div class="calendar-weekdays">
                        <div class="weekday">Sun</div>
                        <div class="weekday">Mon</div>
                        <div class="weekday">Tue</div>
                        <div class="weekday">Wed</div>
                        <div class="weekday">Thu</div>
                        <div class="weekday">Fri</div>
                        <div class="weekday">Sat</div>
                    </div>
                    
                    <div class="calendar-days" id="calendarDays"></div>
                    
                    <div class="calendar-legend">
                        <div class="legend-item">
                            <span class="legend-dot" style="background: #10b981;"></span>
                            <span>Available</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot" style="background: #fbbf24;"></span>
                            <span>Limited Slots</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot" style="background: #ef4444;"></span>
                            <span>Fully Booked</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot" style="background: var(--primary-purple);"></span>
                            <span>Your Bookings</span>
                        </div>
                    </div>
                </div>

                <!-- Date Picker View (Hidden by default) -->
                <div id="pickerView" class="picker-view" style="display: none;">
                    <div class="calendar-shortcuts">
                        <button type="button" class="shortcut-btn" onclick="setDate(0)">
                            <i class="fas fa-calendar-day"></i> Today
                        </button>
                        <button type="button" class="shortcut-btn" onclick="setDate(1)">
                            <i class="fas fa-calendar-plus"></i> Tomorrow
                        </button>
                        <button type="button" class="shortcut-btn" onclick="setDate(7)">
                            <i class="fas fa-calendar-week"></i> Next Week
                        </button>
                    </div>
                    <div class="date-picker-wrapper">
                        <i class="fas fa-calendar-event date-icon"></i>
                        <input type="date" 
                               name="appointment_date" 
                               id="datePicker"
                               class="date-input" 
                               min="<?= date("Y-m-d") ?>" 
                               max="<?= date("Y-m-d", strtotime("+30 days")) ?>"
                               required>
                    </div>
                </div>
                
                <small class="text-muted d-block mt-3">
                    <i class="fas fa-info-circle"></i> You can book appointments up to 30 days in advance
                </small>
            </div>
        </div>

        <!-- Step 3: Select Time -->
        <div class="booking-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-clock me-2"></i>Step 3: Select Your Time Slot</h5>
                <span class="step-badge">3 of 4</span>
            </div>
            <div class="card-body-custom">
                <div class="loading-container" id="loadingSlots">
                    <div class="spinner"></div>
                    <p class="mb-0 text-muted">Loading available time slots...</p>
                </div>

                <div class="slots-container" id="slotsContainer" style="display: none;">
                    <div class="time-period morning">
                        <div class="period-header">
                            <div class="period-icon"><i class="fas fa-sun"></i></div>
                            <div>
                                <div class="period-title">Morning</div>
                                <div class="period-time">6:00 AM - 12:00 PM</div>
                            </div>
                        </div>
                        <div class="slots-grid" id="morningSlots"></div>
                    </div>
                    <div class="time-period afternoon">
                        <div class="period-header">
                            <div class="period-icon"><i class="fas fa-cloud-sun"></i></div>
                            <div>
                                <div class="period-title">Afternoon</div>
                                <div class="period-time">12:00 PM - 5:00 PM</div>
                            </div>
                        </div>
                        <div class="slots-grid" id="afternoonSlots"></div>
                    </div>
                    <div class="time-period evening">
                        <div class="period-header">
                            <div class="period-icon"><i class="fas fa-moon"></i></div>
                            <div>
                                <div class="period-title">Evening</div>
                                <div class="period-time">5:00 PM - 11:00 PM</div>
                            </div>
                        </div>
                        <div class="slots-grid" id="eveningSlots"></div>
                    </div>
                </div>

                <div class="empty-state" id="emptySlots">
                    <div class="empty-state-icon"><i class="fas fa-clock"></i></div>
                    <div class="empty-state-title">Select a Date First</div>
                </div>

                <input type="hidden" name="appointment_time" id="selectedTime" required>
            </div>
        </div>

        <!-- Notes -->
        <div class="booking-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-sticky-note me-2"></i>Additional Notes (Optional)</h5>
                <span class="step-badge">4 of 4</span>
            </div>
            <div class="card-body-custom">
                <textarea name="notes" 
                          id="notes" 
                          class="notes-textarea" 
                          placeholder="Any special requests? (e.g., allergies, preferred stylist, etc.)"
                          maxlength="500"></textarea>
                <div class="char-counter">
                    <span id="charCount">0</span> / 500 characters
                </div>
            </div>
        </div>

        <!-- Booking Summary -->
        <div class="booking-summary" id="bookingSummary">
            <div class="summary-header">
                <div class="summary-icon"><i class="fas fa-receipt"></i></div>
                <h5 class="summary-title">Booking Summary</h5>
            </div>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-label"><i class="fas fa-cut"></i> Service</div>
                    <div class="summary-value" id="summaryService">-</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label"><i class="fas fa-calendar-alt"></i> Date</div>
                    <div class="summary-value" id="summaryDate">-</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label"><i class="fas fa-clock"></i> Time</div>
                    <div class="summary-value" id="summaryTime">-</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label"><i class="fas fa-hourglass-split"></i> Duration</div>
                    <div class="summary-value" id="summaryDuration">-</div>
                </div>
            </div>
            <div class="summary-total">
                <div class="summary-item">
                    <div class="summary-label"><i class="fas fa-money-bill-wave"></i> Total Amount</div>
                    <div class="summary-value" id="summaryPrice">-</div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-book" id="submitBtn" disabled>
            <i class="fas fa-check-circle"></i>
            <span>Confirm Booking</span>
        </button>

    </form>

    <div class="text-center mt-4 mb-5">
        <a href="salon_view.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Salons
        </a>
    </div>
</div>

<script>
let selectedService = null;
let selectedDate = null;
let selectedTime = null;
let currentMonth = new Date();
let userBookings = []; // Will store user's existing bookings
let salonAvailability = {}; // Will store availability data for each date

// Initialize calendar on page load
document.addEventListener('DOMContentLoaded', function() {
    loadUserBookings();
    renderCalendar();
});

// Load user's existing bookings
function loadUserBookings() {
    fetch(`get_user_bookings.php?salon_id=<?= $salon_id ?>`)
        .then(res => res.json())
        .then(data => {
            userBookings = data.bookings || [];
            renderCalendar();
        })
        .catch(err => console.error('Error loading bookings:', err));
}

// Load availability for visible month
function loadMonthAvailability(year, month) {
    const startDate = new Date(year, month, 1).toISOString().split('T')[0];
    const endDate = new Date(year, month + 1, 0).toISOString().split('T')[0];
    
    fetch(`get_availability.php?salon_id=<?= $salon_id ?>&start=${startDate}&end=${endDate}`)
        .then(res => res.json())
        .then(data => {
            salonAvailability = data.availability || {};
            renderCalendar();
        })
        .catch(err => console.error('Error loading availability:', err));
}

// Render calendar
function renderCalendar() {
    const year = currentMonth.getFullYear();
    const month = currentMonth.getMonth();
    
    // Update header
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];
    document.getElementById('calendarMonth').textContent = `${monthNames[month]} ${year}`;
    
    // Load availability data
    loadMonthAvailability(year, month);
    
    // Get first day of month and number of days
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();
    
    const calendarDays = document.getElementById('calendarDays');
    calendarDays.innerHTML = '';
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const maxDate = new Date();
    maxDate.setDate(maxDate.getDate() + 30);
    
    // Previous month days
    for (let i = firstDay - 1; i >= 0; i--) {
        const day = daysInPrevMonth - i;
        const dayEl = createDayElement(day, true, false);
        calendarDays.appendChild(dayEl);
    }
    
    // Current month days
    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month, day);
        date.setHours(0, 0, 0, 0);
        const dateString = date.toISOString().split('T')[0];
        
        const isDisabled = date < today || date > maxDate;
        const isToday = date.getTime() === today.getTime();
        const isSelected = selectedDate === dateString;
        const hasBooking = userBookings.some(b => b.appointment_date === dateString);
        
        const availability = salonAvailability[dateString];
        
        const dayEl = createDayElement(day, false, isDisabled, isToday, isSelected, hasBooking, availability, dateString);
        calendarDays.appendChild(dayEl);
    }
    
    // Next month days to fill grid
    const totalCells = calendarDays.children.length;
    const remainingCells = (totalCells % 7 === 0) ? 0 : 7 - (totalCells % 7);
    for (let day = 1; day <= remainingCells; day++) {
        const dayEl = createDayElement(day, true, false);
        calendarDays.appendChild(dayEl);
    }
}

function createDayElement(day, isOtherMonth, isDisabled, isToday = false, isSelected = false, hasBooking = false, availability = null, dateString = '') {
    const dayEl = document.createElement('div');
    dayEl.className = 'calendar-day';
    
    if (isOtherMonth) {
        dayEl.classList.add('other-month');
    }
    if (isDisabled) {
        dayEl.classList.add('disabled');
    }
    if (isToday) {
        dayEl.classList.add('today');
    }
    if (isSelected) {
        dayEl.classList.add('selected');
    }
    if (hasBooking) {
        dayEl.classList.add('has-booking');
    }
    
    const dayNumber = document.createElement('div');
    dayNumber.className = 'day-number';
    dayNumber.textContent = day;
    dayEl.appendChild(dayNumber);
    
    // Add availability indicator
    if (!isOtherMonth && !isDisabled && availability) {
        const indicator = document.createElement('div');
        indicator.className = 'day-indicator';
        
        if (availability.available > 10) {
            indicator.classList.add('available');
        } else if (availability.available > 0) {
            indicator.classList.add('limited');
        } else {
            indicator.classList.add('full');
        }
        
        dayEl.appendChild(indicator);
        
        // Add slot info
        if (availability.available > 0) {
            const slotInfo = document.createElement('div');
            slotInfo.className = 'slot-info';
            slotInfo.textContent = `${availability.available} slots`;
            dayEl.appendChild(slotInfo);
        }
    }
    
    // Add click handler
    if (!isOtherMonth && !isDisabled && dateString) {
        dayEl.style.cursor = 'pointer';
        dayEl.onclick = () => selectDate(dateString);
    }
    
    return dayEl;
}

function selectDate(dateString) {
    selectedDate = dateString;
    selectedTime = null;
    document.getElementById('selectedTime').value = '';
    document.getElementById('datePicker').value = dateString;
    
    // Update calendar display
    renderCalendar();
    
    // Load time slots
    document.getElementById('emptySlots').style.display = 'none';
    document.getElementById('slotsContainer').style.display = 'none';
    document.getElementById('loadingSlots').classList.add('show');
    
    fetch(`fetch_slots.php?salon_id=<?= $salon_id ?>&date=${selectedDate}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('loadingSlots').classList.remove('show');
            displaySlots(data);
            updateProgress();
            updateSummary();
        })
        .catch(err => {
            document.getElementById('loadingSlots').classList.remove('show');
            document.getElementById('emptySlots').style.display = 'block';
        });
}

function changeMonth(delta) {
    currentMonth.setMonth(currentMonth.getMonth() + delta);
    
    // Prevent going back before current month
    const today = new Date();
    if (currentMonth < new Date(today.getFullYear(), today.getMonth(), 1)) {
        currentMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        return;
    }
    
    // Prevent going forward more than 2 months
    const maxMonth = new Date();
    maxMonth.setMonth(maxMonth.getMonth() + 2);
    if (currentMonth > maxMonth) {
        currentMonth = maxMonth;
        return;
    }
    
    renderCalendar();
}

function toggleView(view) {
    const calendarView = document.getElementById('calendarView');
    const pickerView = document.getElementById('pickerView');
    const calendarBtn = document.getElementById('calendarViewBtn');
    const pickerBtn = document.getElementById('pickerViewBtn');
    
    if (view === 'calendar') {
        calendarView.style.display = 'block';
        pickerView.style.display = 'none';
        calendarBtn.classList.add('active');
        pickerBtn.classList.remove('active');
    } else {
        calendarView.style.display = 'none';
        pickerView.style.display = 'block';
        calendarBtn.classList.remove('active');
        pickerBtn.classList.add('active');
    }
}

// Service Selection
document.querySelectorAll('.service-card').forEach(card => {
    card.addEventListener('click', function(e) {
        if (e.target.type === 'radio') return;
        const radio = this.querySelector('input[type="radio"]');
        radio.checked = true;
        document.querySelectorAll('.service-card').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
        selectedService = {
            id: radio.value,
            name: radio.dataset.name,
            price: radio.dataset.price,
            duration: radio.dataset.duration
        };
        updateProgress();
        updateSummary();
    });
});

// Date shortcuts
function setDate(days) {
    const date = new Date();
    date.setDate(date.getDate() + days);
    const dateString = date.toISOString().split('T')[0];
    selectDate(dateString);
}

// Date Picker (for picker view)
document.getElementById('datePicker').addEventListener('change', function() {
    selectDate(this.value);
});

function displaySlots(data) {
    const morningSlots = document.getElementById('morningSlots');
    const afternoonSlots = document.getElementById('afternoonSlots');
    const eveningSlots = document.getElementById('eveningSlots');
    morningSlots.innerHTML = '';
    afternoonSlots.innerHTML = '';
    eveningSlots.innerHTML = '';
    if (!data || data.length === 0) {
        document.getElementById('emptySlots').style.display = 'block';
        return;
    }
    document.getElementById('slotsContainer').style.display = 'block';
    let slots = data;
    if (typeof data[0] === 'string') {
        slots = data.map(time => ({ time: time, booked: false }));
    }
    slots.forEach(slot => {
        const hour = parseInt(slot.time.split(':')[0]);
        const period = slot.time.includes('PM') ? 'PM' : 'AM';
        const hour24 = period === 'PM' && hour !== 12 ? hour + 12 : (period === 'AM' && hour === 12 ? 0 : hour);
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'slot-btn';
        if (slot.booked) {
            btn.classList.add('booked');
            btn.innerHTML = `<span class="slot-time">${slot.time}</span><span class="slot-status">Booked</span>`;
            btn.disabled = true;
        } else {
            btn.innerHTML = `<span class="slot-time">${slot.time}</span><span class="slot-status">Available</span>`;
            btn.addEventListener('click', function() {
                document.querySelectorAll('.slot-btn:not(.booked)').forEach(b => {
                    b.classList.remove('selected');
                    b.querySelector('.slot-status').textContent = 'Available';
                });
                this.classList.add('selected');
                this.querySelector('.slot-status').textContent = 'Selected';
                document.getElementById('selectedTime').value = slot.time;
                selectedTime = slot.time;
                updateProgress();
                updateSummary();
            });
        }
        if (hour24 < 12) {
            morningSlots.appendChild(btn);
        } else if (hour24 < 17) {
            afternoonSlots.appendChild(btn);
        } else {
            eveningSlots.appendChild(btn);
        }
    });
}

function updateProgress() {
    const steps = ['step1', 'step2', 'step3', 'step4'];
    let activeStep = 0;
    steps.forEach((step, index) => {
        const el = document.getElementById(step);
        el.classList.remove('active', 'completed');
        if (index === 0) el.classList.add('active');
    });
    if (selectedService) {
        document.getElementById('step1').classList.remove('active');
        document.getElementById('step1').classList.add('completed');
        document.getElementById('step2').classList.add('active');
        activeStep = 1;
    }
    if (selectedDate) {
        document.getElementById('step2').classList.remove('active');
        document.getElementById('step2').classList.add('completed');
        document.getElementById('step3').classList.add('active');
        activeStep = 2;
    }
    if (selectedTime) {
        document.getElementById('step3').classList.remove('active');
        document.getElementById('step3').classList.add('completed');
        document.getElementById('step4').classList.add('active');
        activeStep = 3;
    }
    const progressLine = document.getElementById('progressLine');
    const progressPercent = (activeStep / 3) * 75;
    progressLine.style.width = progressPercent + '%';
    document.getElementById('submitBtn').disabled = !(selectedService && selectedDate && selectedTime);
}

function updateSummary() {
    const summary = document.getElementById('bookingSummary');
    if (selectedService && selectedDate && selectedTime) {
        summary.classList.add('show');
        document.getElementById('summaryService').textContent = selectedService.name;
        const dateObj = new Date(selectedDate + 'T00:00:00');
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('summaryDate').textContent = dateObj.toLocaleDateString('en-US', options);
        document.getElementById('summaryTime').textContent = selectedTime;
        document.getElementById('summaryDuration').textContent = selectedService.duration + ' minutes';
        document.getElementById('summaryPrice').textContent = 'Rs ' + parseFloat(selectedService.price).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    } else {
        summary.classList.remove('show');
    }
}

// Character counter
document.getElementById('notes').addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
});

// Form validation
document.getElementById('bookingForm').addEventListener('submit', function(e) {
    if (!selectedService || !selectedDate || !selectedTime) {
        e.preventDefault();
        alert('Please complete all steps before booking');
        return false;
    }
});
</script>

</body>
</html>