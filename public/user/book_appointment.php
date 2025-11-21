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
$stmt = $pdo->prepare("SELECT id, name, opening_time, closing_time, slot_duration FROM salons WHERE id=?");
$stmt->execute([$salon_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$salon) {
    $_SESSION['error_message'] = 'Salon not found.';
    header('Location: salon_view.php');
    exit;
}

// Fetch services
$serviceStmt = $pdo->prepare("SELECT id, name, price, duration FROM services WHERE salon_id=? ORDER BY name ASC");
$serviceStmt->execute([$salon_id]);
$services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);

// Submit booking
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = intval($_POST['service_id']);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';

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
            INSERT INTO appointments (salon_id, user_id, service_id, appointment_date, appointment_time, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");

        $stmt->execute([
            $salon_id,
            $user_id,
            $service_id,
            $appointment_date,
            $appointment_time
        ]);

        $_SESSION['success_message'] = "Appointment booked successfully!";
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 20px 0;
}

.booking-card {
    border-radius: 15px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.card-header {
    border-radius: 15px 15px 0 0 !important;
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
}

.salon-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #667eea;
}

.salon-info i {
    color: #667eea;
    margin-right: 8px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.info-row:last-child {
    margin-bottom: 0;
}

.service-card {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.3s;
    background: white;
}

.service-card:hover {
    border-color: #667eea;
    background: #f8f9ff;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.service-card.selected {
    border-color: #667eea;
    background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
}

.service-card input[type="radio"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: #667eea;
}

.date-selector {
    position: relative;
}

.date-selector input {
    padding-right: 45px;
}

.calendar-icon {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #667eea;
    pointer-events: none;
    font-size: 1.2rem;
}

.slot-box {
    padding: 12px 20px;
    border-radius: 10px;
    border: 2px solid #667eea;
    cursor: pointer;
    margin: 8px;
    display: inline-block;
    transition: all 0.3s;
    font-weight: 500;
    background: white;
    min-width: 120px;
    text-align: center;
    position: relative;
}

.slot-box i {
    font-size: 0.9rem;
}

.slot-box:hover:not(.booked) {
    background: #667eea;
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

.slot-box.selected {
    background: #667eea;
    color: #fff;
    border-color: #5568d3;
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    transform: translateY(-3px);
}

.slot-box.booked {
    background: #f8d7da;
    border-color: #f5c2c7;
    color: #842029;
    cursor: not-allowed;
    opacity: 0.7;
}

.slot-box.booked:hover {
    transform: none;
    box-shadow: none;
}

#slotsArea {
    display: flex;
    flex-wrap: wrap;
    min-height: 100px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 2px dashed #dee2e6;
}

#loadingSlots {
    display: none;
    text-align: center;
    padding: 30px;
}

.spinner-border {
    color: #667eea;
}

.step-indicator {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    position: relative;
}

.step-indicator::before {
    content: '';
    position: absolute;
    top: 17px;
    left: 12.5%;
    right: 12.5%;
    height: 2px;
    background: #e9ecef;
    z-index: 0;
}

.step {
    flex: 1;
    text-align: center;
    position: relative;
    z-index: 1;
}

.step-number {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: #e9ecef;
    color: #6c757d;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-bottom: 8px;
    transition: all 0.3s;
    border: 3px solid white;
}

.step.active .step-number {
    background: #667eea;
    color: white;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
}

.step.completed .step-number {
    background: #28a745;
    color: white;
}

.step.completed .step-number i {
    display: block;
}

.step-label {
    font-size: 0.85rem;
    color: #6c757d;
    font-weight: 500;
}

.step.active .step-label {
    color: #667eea;
    font-weight: 600;
}

.booking-summary {
    background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
    padding: 20px;
    border-radius: 10px;
    margin-top: 20px;
    border: 2px solid #667eea;
    display: none;
}

.booking-summary.show {
    display: block;
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.summary-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #dee2e6;
}

.summary-item:last-child {
    border-bottom: none;
    font-weight: bold;
    font-size: 1.1rem;
    color: #667eea;
    margin-top: 5px;
}

.btn-book {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 15px;
    font-size: 1.1rem;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-book:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.btn-book:disabled {
    background: #6c757d;
    cursor: not-allowed;
    opacity: 0.6;
}

.alert {
    border-radius: 10px;
    border: none;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

.slots-legend {
    display: flex;
    gap: 20px;
    justify-content: center;
    margin-top: 10px;
    font-size: 0.85rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.legend-box {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 2px solid;
}

.legend-box.available {
    background: white;
    border-color: #667eea;
}

.legend-box.booked {
    background: #f8d7da;
    border-color: #f5c2c7;
}

.legend-box.selected {
    background: #667eea;
    border-color: #5568d3;
}

.back-link {
    color: white;
    text-decoration: none;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.back-link:hover {
    color: #fff;
    gap: 12px;
}
</style>
</head>
<body>

<div class="container mt-4" style="max-width:750px;">
    <div class="card booking-card shadow-lg">
        <div class="card-header text-white p-4">
            <h4 class="mb-1"><i class="bi bi-calendar-check"></i> Book Appointment</h4>
            <small><i class="bi bi-shop"></i> <?= htmlspecialchars($salon['name']) ?></small>
        </div>

        <div class="card-body p-4">

            <!-- Salon Information -->
            <div class="salon-info">
                <h6 class="fw-bold mb-3"><i class="bi bi-info-circle"></i> Salon Information</h6>
                <div class="info-row">
                    <span><i class="bi bi-clock"></i> <strong>Operating Hours:</strong></span>
                    <span>
                        <?= date('g:i A', strtotime($salon['opening_time'])) ?> - 
                        <?= date('g:i A', strtotime($salon['closing_time'])) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span><i class="bi bi-hourglass-split"></i> <strong>Time Slot Duration:</strong></span>
                    <span><?= $salon['slot_duration'] ?> minutes</span>
                </div>
                <div class="info-row">
                    <span><i class="bi bi-calendar-range"></i> <strong>Booking Period:</strong></span>
                    <span>Up to 30 days in advance</span>
                </div>
            </div>

            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step active" id="step1">
                    <div class="step-number">1</div>
                    <div class="step-label">Service</div>
                </div>
                <div class="step" id="step2">
                    <div class="step-number">2</div>
                    <div class="step-label">Date</div>
                </div>
                <div class="step" id="step3">
                    <div class="step-number">3</div>
                    <div class="step-label">Time</div>
                </div>
                <div class="step" id="step4">
                    <div class="step-number">4</div>
                    <div class="step-label">Confirm</div>
                </div>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-danger d-flex align-items-start">
                    <i class="bi bi-exclamation-triangle me-2 mt-1"></i>
                    <div>
                        <strong>Please fix the following errors:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" id="bookingForm">

                <!-- Step 1: Select Service -->
                <div class="mb-4">
                    <label class="form-label fw-bold">
                        <i class="bi bi-scissors"></i> Step 1: Select Service
                        <span class="text-danger">*</span>
                    </label>
                    
                    <?php if (empty($services)): ?>
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <p>No services are currently available at this salon.</p>
                        </div>
                    <?php else: ?>
                        <div id="serviceCards">
                            <?php foreach ($services as $s): ?>
                                <div class="service-card" data-service-id="<?= $s['id'] ?>">
                                    <div class="d-flex align-items-center">
                                        <input type="radio" 
                                               name="service_id" 
                                               value="<?= $s['id'] ?>" 
                                               id="service_<?= $s['id'] ?>" 
                                               data-price="<?= $s['price'] ?>"
                                               data-duration="<?= $s['duration'] ?>"
                                               data-name="<?= htmlspecialchars($s['name']) ?>"
                                               required>
                                        <label class="ms-3 mb-0 flex-grow-1 w-100" for="service_<?= $s['id'] ?>" style="cursor: pointer;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong class="d-block"><?= htmlspecialchars($s['name']) ?></strong>
                                                    <small class="text-muted">
                                                        <i class="bi bi-clock"></i> <?= $s['duration'] ?> minutes
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <strong class="text-primary fs-5">Rs <?= number_format($s['price'], 2) ?></strong>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Step 2: Select Date -->
                <div class="mb-4">
                    <label class="form-label fw-bold">
                        <i class="bi bi-calendar3"></i> Step 2: Select Appointment Date
                        <span class="text-danger">*</span>
                    </label>
                    <div class="date-selector">
                        <input type="date" 
                               name="appointment_date" 
                               id="datePicker"
                               class="form-control form-control-lg" 
                               min="<?= date("Y-m-d") ?>" 
                               max="<?= date("Y-m-d", strtotime("+30 days")) ?>"
                               required>
                        <i class="bi bi-calendar-event calendar-icon"></i>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> Select a date to view available time slots
                    </small>
                </div>

                <!-- Step 3: Select Time Slot -->
                <div class="mb-4">
                    <label class="form-label fw-bold">
                        <i class="bi bi-clock-history"></i> Step 3: Choose Available Time Slot
                        <span class="text-danger">*</span>
                    </label>

                    <div id="loadingSlots">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 mb-0">Loading available time slots...</p>
                    </div>

                    <div id="slotsArea">
                        <div class="empty-state">
                            <i class="bi bi-clock"></i>
                            <p class="mb-0">Please select a date above to view available time slots</p>
                        </div>
                    </div>

                    <div class="slots-legend">
                        <div class="legend-item">
                            <div class="legend-box available"></div>
                            <span>Available</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-box booked"></div>
                            <span>Booked</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-box selected"></div>
                            <span>Selected</span>
                        </div>
                    </div>

                    <input type="hidden" name="appointment_time" id="selectedTime" required>
                </div>

                <!-- Booking Summary -->
                <div class="booking-summary" id="bookingSummary">
                    <h6 class="fw-bold mb-3">
                        <i class="bi bi-receipt"></i> Step 4: Booking Summary
                    </h6>
                    <div class="summary-item">
                        <span><i class="bi bi-scissors"></i> Service:</span>
                        <span id="summaryService" class="fw-bold">-</span>
                    </div>
                    <div class="summary-item">
                        <span><i class="bi bi-calendar3"></i> Date:</span>
                        <span id="summaryDate" class="fw-bold">-</span>
                    </div>
                    <div class="summary-item">
                        <span><i class="bi bi-clock"></i> Time:</span>
                        <span id="summaryTime" class="fw-bold">-</span>
                    </div>
                    <div class="summary-item">
                        <span><i class="bi bi-hourglass-split"></i> Duration:</span>
                        <span id="summaryDuration" class="fw-bold">-</span>
                    </div>
                    <div class="summary-item">
                        <span><i class="bi bi-currency-rupee"></i> Total Amount:</span>
                        <span id="summaryPrice" class="text-primary">-</span>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-book w-100 mt-4" id="submitBtn" disabled>
                    <i class="bi bi-check-circle"></i> Confirm Booking
                </button>

            </form>

        </div>
    </div>

    <div class="text-center mt-3">
        <a href="salon_view.php" class="back-link">
            <i class="bi bi-arrow-left"></i> Back to Salons
        </a>
    </div>
</div>


<script>
let selectedService = null;
let selectedDate = null;
let selectedTime = null;

// Handle service selection
document.querySelectorAll('.service-card').forEach(card => {
    card.addEventListener('click', function() {
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
        
        updateSteps();
        updateSummary();
    });
});

// Handle date selection
document.getElementById('datePicker').addEventListener('change', function() {
    selectedDate = this.value;
    selectedTime = null;
    
    let slotArea = document.getElementById('slotsArea');
    let loading = document.getElementById('loadingSlots');
    let hiddenInput = document.getElementById('selectedTime');

    slotArea.innerHTML = "";
    hiddenInput.value = "";

    if (!selectedDate) return;

    loading.style.display = "block";

    fetch(`fetch_slots.php?salon_id=<?= $salon_id ?>&date=${selectedDate}`)
        .then(res => res.json())
        .then(data => {
            loading.style.display = "none";
            slotArea.innerHTML = "";

            if (!data || data.length === 0) {
                slotArea.innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-calendar-x"></i>
                        <p class="text-danger mb-1">No available time slots for this date</p>
                        <small class="text-muted">Please select another date or contact the salon</small>
                    </div>
                `;
                return;
            }

            // Check if data is array of objects or array of strings
            let slots = data;
            if (typeof data[0] === 'string') {
                // Old format: array of time strings
                slots = data.map(time => ({ time: time, booked: false }));
            }

            slots.forEach(slot => {
                let div = document.createElement("div");
                div.className = "slot-box";
                
                if (slot.booked) {
                    div.classList.add("booked");
                    div.innerHTML = `
                        <i class="bi bi-x-circle"></i> ${slot.time}
                        <br><small>Booked</small>
                    `;
                } else {
                    div.innerHTML = `<i class="bi bi-clock"></i> ${slot.time}`;
                    div.addEventListener("click", function() {
                        document.querySelectorAll(".slot-box:not(.booked)").forEach(x => x.classList.remove("selected"));
                        this.classList.add("selected");
                        hiddenInput.value = slot.time;
                        selectedTime = slot.time;
                        updateSteps();
                        updateSummary();
                    });
                }

                slotArea.appendChild(div);
            });

            // Show available count
            const availableCount = slots.filter(s => !s.booked).length;
            const totalCount = slots.length;
            
            if (availableCount > 0) {
                const countInfo = document.createElement("div");
                countInfo.className = "w-100 text-center mt-2";
                countInfo.innerHTML = `<small class="text-muted"><i class="bi bi-info-circle"></i> ${availableCount} of ${totalCount} slots available</small>`;
                slotArea.appendChild(countInfo);
            }
        })
        .catch(err => {
            loading.style.display = "none";
            slotArea.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-exclamation-triangle"></i>
                    <p class="text-danger mb-1">Error loading time slots</p>
                    <small class="text-muted">Please try again or refresh the page</small>
                </div>
            `;
            console.error('Error fetching slots:', err);
        });
    
    updateSteps();
    updateSummary();
});

function updateSteps() {
    // Reset all steps
    document.querySelectorAll('.step').forEach(step => {
        step.classList.remove('active', 'completed');
    });
    
    // Step 1
    if (selectedService) {
        document.getElementById('step1').classList.add('completed');
        document.getElementById('step2').classList.add('active');
    } else {
        document.getElementById('step1').classList.add('active');
    }
    
    // Step 2
    if (selectedDate) {
        document.getElementById('step2').classList.add('completed');
        document.getElementById('step3').classList.add('active');
    }
    
    // Step 3
    if (selectedTime) {
        document.getElementById('step3').classList.add('completed');
        document.getElementById('step4').classList.add('active');
    }
    
    // Enable submit button only when all steps are complete
    document.getElementById('submitBtn').disabled = !(selectedService && selectedDate && selectedTime);
}

function updateSummary() {
    const summary = document.getElementById('bookingSummary');
    
    if (selectedService && selectedDate && selectedTime) {
        summary.classList.add('show');
        
        document.getElementById('summaryService').textContent = selectedService.name;
        
        const dateObj = new Date(selectedDate + 'T00:00:00');
        document.getElementById('summaryDate').textContent = dateObj.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        document.getElementById('summaryTime').textContent = selectedTime;
        document.getElementById('summaryDuration').textContent = selectedService.duration + ' minutes';
        document.getElementById('summaryPrice').textContent = 'Rs ' + parseFloat(selectedService.price).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    } else {
        summary.classList.remove('show');
    }
}

// Form validation before submission
document.getElementById('bookingForm').addEventListener('submit', function(e) {
    if (!selectedService || !selectedDate || !selectedTime) {
        e.preventDefault();
        alert('Please complete all booking steps:\n1. Select a service\n2. Choose a date\n3. Pick a time slot');
        return false;
    }
});

// Initialize: if services exist, trigger click on first service card
window.addEventListener('DOMContentLoaded', function() {
    const firstService = document.querySelector('.service-card');
    if (firstService) {
        // Auto-select first service for better UX
        // firstService.click();
    }
});
</script>

</body>
</html>