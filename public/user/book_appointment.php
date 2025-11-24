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

// Fetch salon details
$stmt = $pdo->prepare("SELECT id, name, address, opening_time, closing_time, slot_duration FROM salons WHERE id=?");
$stmt->execute([$salon_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$salon) {
    $_SESSION['error_message'] = 'Salon not found.';
    header('Location: salon_view.php');
    exit;
}

// Fetch services
$serviceStmt = $pdo->prepare("SELECT id, name, price, duration, description FROM services WHERE salon_id=? ORDER BY price ASC");
$serviceStmt->execute([$salon_id]);
$services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);

// Submit booking
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = intval($_POST['service_id']);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if ($service_id <= 0) $errors[] = "Please select a service.";
    if ($appointment_date == "") $errors[] = "Please select a date.";
    if ($appointment_time == "") $errors[] = "Please select a time slot.";

    if ($appointment_date < date("Y-m-d")) $errors[] = "Cannot book appointments for past dates.";

    // Verify slot is still available
    if (empty($errors)) {
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM appointments 
            WHERE salon_id=? AND appointment_date=? AND appointment_time=? 
            AND status != 'cancelled'
        ");
        $checkStmt->execute([$salon_id, $appointment_date, $appointment_time]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing['count'] > 0) {
            $errors[] = "This time slot is no longer available. Please select another slot.";
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO appointments (salon_id, user_id, service_id, appointment_date, appointment_time, notes, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");

        $stmt->execute([
            $salon_id,
            $user_id,
            $service_id,
            $appointment_date,
            $appointment_time,
            $notes
        ]);

        $_SESSION['success_message'] = "Appointment booked successfully! We'll send you a confirmation soon.";
        header("Location: my_appointments.php");
        exit;
    }
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
    --gradient-primary:  linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
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
    background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
    color: white;
    padding: 4rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 30px 30px;
    box-shadow: var(--shadow-lg);
    margin-top: -50px;
}

.page-header h2 {
    font-weight: 800;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
    
}

.page-header p {
    opacity: 0.95;
    font-size: 1.1rem;
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
}

.card-header-custom {
    background: var(--gradient-primary);
    color: white;
    padding: 1.5rem 2rem;
    border: none;
}

.card-header-custom h5 {
    font-weight: 700;
    margin: 0;
    font-size: 1.3rem;
}

.card-body-custom {
    padding: 2rem;
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

/* Service Selection */
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

.slot-btn.booked .slot-status::before {
    content: '✕ ';
}

.slot-btn.selected .slot-status::before {
    content: '✓ ';
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

.empty-state-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.empty-state-text {
    color: #9ca3af;
}

/* Legend */
.slots-legend {
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
    font-size: 0.9rem;
}

.legend-box {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    border: 2px solid;
    display: flex;
    align-items: center;
    justify-content: center;
}

.legend-box.available {
    background: white;
    border-color: #e5e7eb;
}

.legend-box.selected {
    background: var(--gradient-primary);
    border-color: var(--primary-purple);
    color: white;
}

.legend-box.booked {
    background: #fee2e2;
    border-color: #fecaca;
    color: #991b1b;
}

/* Booking Summary */
.booking-summary {
    background:  linear-gradient(135deg, #ec337170 0%, #9b27b077 100%);;
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

.summary-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    background: var(--gradient-primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.summary-title {
    font-weight: 800;
    color: var(--dark-purple);
    font-size: 1.3rem;
    margin: 0;
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

.summary-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #666;
    font-weight: 500;
}

.summary-label i {
    color: var(--primary-purple);
}

.summary-value {
    font-weight: 700;
    color: var(--dark-purple);
    text-align: right;
}

.summary-total {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    margin-top: 1rem;
    border: 2px solid var(--primary-purple);
}

.summary-total .summary-label {
    font-size: 1.1rem;
    font-weight: 700;
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
    transform: none;
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
    color: var(--dark-purple);
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

.alert-danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
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
    
    .step-progress {
        padding: 0;
    }
    
    .step-circle {
        width: 50px;
        height: 50px;
        font-size: 1rem;
    }
    
    .step-label {
        font-size: 0.75rem;
    }
}

/* Pulse animation for important elements */
@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

.pulse {
    animation: pulse 2s infinite;
}
</style>
</head>
<body>

<div class="page-header">
    <div class="container text-center">
        <h2><i class="fas fa-calendar-check"></i> Book Your Appointment</h2>
        <p><?= htmlspecialchars($salon['name']) ?></p>
    </div>
</div>

<div class="container booking-container">
    <!-- Salon Information -->
    <div class="salon-info-card">
        <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Salon Information</h5>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="info-text">
                    <span class="info-label">Location</span>
                    <span class="info-value"><?= htmlspecialchars($salon['address']) ?></span>
                </div>
            </div>
            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="info-text">
                    <span class="info-label">Operating Hours</span>
                    <span class="info-value">
                        <?= date('g:i A', strtotime($salon['opening_time'])) ?> - 
                        <?= date('g:i A', strtotime($salon['closing_time'])) ?>
                    </span>
                </div>
            </div>
            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-hourglass-split"></i>
                </div>
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
            <div class="step-circle">
                <i class="fas fa-cut"></i>
            </div>
            <div class="step-label">Choose Service</div>
        </div>
        <div class="step-item" id="step2">
            <div class="step-circle">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="step-label">Pick Date</div>
        </div>
        <div class="step-item" id="step3">
            <div class="step-circle">
                <i class="fas fa-clock"></i>
            </div>
            <div class="step-label">Select Time</div>
        </div>
        <div class="step-item" id="step4">
            <div class="step-circle">
                <i class="fas fa-check-circle"></i>
            </div>
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

        <!-- Step 1: Select Service -->
        <div class="booking-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-cut me-2"></i>Step 1: Select Your Service</h5>
            </div>
            <div class="card-body-custom">
                <?php if (empty($services)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <div class="empty-state-title">No Services Available</div>
                        <div class="empty-state-text">This salon doesn't have any services listed at the moment.</div>
                    </div>
                <?php else: ?>
                    <div class="services-grid">
                        <?php foreach ($services as $s): ?>
                            <label for="service_<?= $s['id'] ?>" class="service-card" data-service-id="<?= $s['id'] ?>">
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
                <?php endif; ?>
            </div>
        </div>

        <!-- Step 2: Select Date -->
        <div class="booking-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-calendar-alt me-2"></i>Step 2: Choose Your Preferred Date</h5>
            </div>
            <div class="card-body-custom">
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
                <small class="text-muted d-block mt-2">
                    <i class="fas fa-info-circle"></i> You can book appointments up to 30 days in advance
                </small>
            </div>
        </div>

        <!-- Step 3: Select Time Slot -->
        <div class="booking-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-clock me-2"></i>Step 3: Select Your Time Slot</h5>
            </div>
            <div class="card-body-custom">
                <div class="loading-container" id="loadingSlots">
                    <div class="spinner"></div>
                    <p class="mb-0 text-muted">Loading available time slots...</p>
                </div>

                <div class="slots-container" id="slotsContainer" style="display: none;">
                    <div class="time-period morning">
                        <div class="period-header">
                            <div class="period-icon">
                                <i class="fas fa-sun"></i>
                            </div>
                            <div>
                                <div class="period-title">Morning</div>
                                <div class="period-time">6:00 AM - 12:00 PM</div>
                            </div>
                        </div>
                        <div class="slots-grid" id="morningSlots"></div>
                    </div>

                    <div class="time-period afternoon">
                        <div class="period-header">
                            <div class="period-icon">
                                <i class="fas fa-cloud-sun"></i>
                            </div>
                            <div>
                                <div class="period-title">Afternoon</div>
                                <div class="period-time">12:00 PM - 5:00 PM</div>
                            </div>
                        </div>
                        <div class="slots-grid" id="afternoonSlots"></div>
                    </div>

                    <div class="time-period evening">
                        <div class="period-header">
                            <div class="period-icon">
                                <i class="fas fa-moon"></i>
                            </div>
                            <div>
                                <div class="period-title">Evening</div>
                                <div class="period-time">5:00 PM - 11:00 PM</div>
                            </div>
                        </div>
                        <div class="slots-grid" id="eveningSlots"></div>
                    </div>
                </div>

                <div class="empty-state" id="emptySlots">
                    <div class="empty-state-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="empty-state-title">Select a Date First</div>
                    <div class="empty-state-text">Choose a date above to view available time slots</div>
                </div>

                <div class="slots-legend">
                    <div class="legend-item">
                        <div class="legend-box available">
                            <i class="fas fa-check" style="font-size: 0.75rem; color: #10b981;"></i>
                        </div>
                        <span>Available</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-box selected">
                            <i class="fas fa-check" style="font-size: 0.75rem;"></i>
                        </div>
                        <span>Selected</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-box booked">
                            <i class="fas fa-times" style="font-size: 0.75rem;"></i>
                        </div>
                        <span>Booked</span>
                    </div>
                </div>

                <input type="hidden" name="appointment_time" id="selectedTime" required>
            </div>
        </div>

        <!-- Additional Notes -->
        <div class="booking-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-sticky-note me-2"></i>Additional Notes (Optional)</h5>
            </div>
            <div class="card-body-custom">
                <textarea name="notes" 
                          id="notes" 
                          class="notes-textarea" 
                          placeholder="Any special requests or requirements? (e.g., allergies, preferred stylist, etc.)"
                          maxlength="500"></textarea>
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i> Maximum 500 characters
                </small>
            </div>
        </div>

        <!-- Booking Summary -->
        <div class="booking-summary" id="bookingSummary">
            <div class="summary-header">
                <div class="summary-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <h5 class="summary-title">Booking Summary</h5>
            </div>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-label">
                        <i class="fas fa-cut"></i>
                        <span>Service</span>
                    </div>
                    <div class="summary-value" id="summaryService">-</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Date</span>
                    </div>
                    <div class="summary-value" id="summaryDate">-</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">
                        <i class="fas fa-clock"></i>
                        <span>Time</span>
                    </div>
                    <div class="summary-value" id="summaryTime">-</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">
                        <i class="fas fa-hourglass-split"></i>
                        <span>Duration</span>
                    </div>
                    <div class="summary-value" id="summaryDuration">-</div>
                </div>
            </div>
            <div class="summary-total">
                <div class="summary-item">
                    <div class="summary-label">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Total Amount</span>
                    </div>
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
            <i class="fas fa-arrow-left"></i>
            <span>Back to Salons</span>
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let selectedService = null;
let selectedDate = null;
let selectedTime = null;

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

// Date Selection
document.getElementById('datePicker').addEventListener('change', function() {
    selectedDate = this.value;
    selectedTime = null;
    document.getElementById('selectedTime').value = '';
    
    if (!selectedDate) return;
    
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
            document.getElementById('emptySlots').innerHTML = `
                <div class="empty-state-icon text-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="empty-state-title">Error Loading Slots</div>
                <div class="empty-state-text">Please try again or refresh the page</div>
            `;
            console.error('Error:', err);
        });
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
        document.getElementById('emptySlots').innerHTML = `
            <div class="empty-state-icon text-warning">
                <i class="fas fa-calendar-times"></i>
            </div>
            <div class="empty-state-title">No Slots Available</div>
            <div class="empty-state-text">All time slots are booked for this date. Please try another date.</div>
        `;
        return;
    }
    
    document.getElementById('slotsContainer').style.display = 'block';
    
    let slots = data;
    if (typeof data[0] === 'string') {
        slots = data.map(time => ({ time: time, booked: false }));
    }
    
    let morningCount = 0, afternoonCount = 0, eveningCount = 0;
    
    slots.forEach(slot => {
        const hour = parseInt(slot.time.split(':')[0]);
        const period = slot.time.includes('PM') ? 'PM' : 'AM';
        const hour24 = period === 'PM' && hour !== 12 ? hour + 12 : (period === 'AM' && hour === 12 ? 0 : hour);
        
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'slot-btn';
        
        if (slot.booked) {
            btn.classList.add('booked');
            btn.innerHTML = `
                <span class="slot-time">${slot.time}</span>
                <span class="slot-status">Booked</span>
            `;
            btn.disabled = true;
        } else {
            btn.innerHTML = `
                <span class="slot-time">${slot.time}</span>
                <span class="slot-status">Available</span>
            `;
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
            morningCount++;
        } else if (hour24 < 17) {
            afternoonSlots.appendChild(btn);
            afternoonCount++;
        } else {
            eveningSlots.appendChild(btn);
            eveningCount++;
        }
    });
    
    // Hide empty periods
    document.querySelector('.morning').style.display = morningCount > 0 ? 'block' : 'none';
    document.querySelector('.afternoon').style.display = afternoonCount > 0 ? 'block' : 'none';
    document.querySelector('.evening').style.display = eveningCount > 0 ? 'block' : 'none';
    
    // Show total available count
    const availableCount = slots.filter(s => !s.booked).length;
    if (availableCount === 0) {
        document.getElementById('slotsContainer').style.display = 'none';
        document.getElementById('emptySlots').style.display = 'block';
    }
}

function updateProgress() {
    const steps = ['step1', 'step2', 'step3', 'step4'];
    let activeStep = 0;
    
    // Reset all steps
    steps.forEach((step, index) => {
        const el = document.getElementById(step);
        el.classList.remove('active', 'completed');
        
        if (index === 0) {
            el.classList.add('active');
        }
    });
    
    // Step 1 - Service
    if (selectedService) {
        document.getElementById('step1').classList.remove('active');
        document.getElementById('step1').classList.add('completed');
        document.getElementById('step2').classList.add('active');
        activeStep = 1;
    }
    
    // Step 2 - Date
    if (selectedDate) {
        document.getElementById('step2').classList.remove('active');
        document.getElementById('step2').classList.add('completed');
        document.getElementById('step3').classList.add('active');
        activeStep = 2;
    }
    
    // Step 3 - Time
    if (selectedTime) {
        document.getElementById('step3').classList.remove('active');
        document.getElementById('step3').classList.add('completed');
        document.getElementById('step4').classList.add('active');
        activeStep = 3;
    }
    
    // Update progress line
    const progressLine = document.getElementById('progressLine');
    const progressPercent = (activeStep / 3) * 75; // 75% max (from 15% to 90%)
    progressLine.style.width = progressPercent + '%';
    
    // Enable submit button
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

// Form validation
document.getElementById('bookingForm').addEventListener('submit', function(e) {
    if (!selectedService || !selectedDate || !selectedTime) {
        e.preventDefault();
        alert('Please complete all steps before booking:\n\n✓ Select a service\n✓ Choose a date\n✓ Pick a time slot');
        return false;
    }
});

// Auto-dismiss any alert messages after 5 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.animation = 'slideUp 0.5s ease forwards';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);
</script>

</body>
</html>