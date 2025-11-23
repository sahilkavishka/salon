<?php
// public/user/my_appointments.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

if (!isset($_SESSION['id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['id'];

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Invalid security token.";
        header('Location: my_appointments.php');
        exit;
    }

    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);

    if ($appointment_id) {
        try {
            $pdo->beginTransaction();

            $checkStmt = $pdo->prepare("
                SELECT status, appointment_date, appointment_time 
                FROM appointments 
                WHERE id = :id AND user_id = :uid
                FOR UPDATE
            ");
            $checkStmt->execute([':id' => $appointment_id, ':uid' => $user_id]);
            $appointment = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($appointment && in_array($appointment['status'], ['pending', 'confirmed'])) {
                $appointmentDateTime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
                $hoursUntil = ($appointmentDateTime - time()) / 3600;

                if ($hoursUntil >= 24) {
                    $pdo->prepare("
                        UPDATE appointments 
                        SET status = 'cancelled', updated_at = NOW() 
                        WHERE id = :id AND user_id = :uid
                    ")->execute([':id' => $appointment_id, ':uid' => $user_id]);

                    $pdo->commit();
                    $_SESSION['success_message'] = "Appointment cancelled successfully.";
                } else {
                    $pdo->rollBack();
                    $_SESSION['error_message'] = "Cannot cancel appointment less than 24 hours before scheduled time.";
                }
            } else {
                $pdo->rollBack();
                $_SESSION['error_message'] = "Cannot cancel this appointment.";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Appointment cancellation error: " . $e->getMessage());
            $_SESSION['error_message'] = "An error occurred. Please try again.";
        }
    }

    header('Location: my_appointments.php');
    exit;
}

// Fetch appointments
$stmt = $pdo->prepare("
    SELECT 
        a.*, 
        s.name AS salon_name,
        s.address AS salon_address,
        s.phone AS salon_phone,
        srv.name AS service_name,
        srv.price AS service_price,
        srv.duration AS service_duration
    FROM appointments a
    JOIN salons s ON s.id = a.salon_id
    JOIN services srv ON srv.id = a.service_id
    WHERE a.user_id = :uid
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([':uid' => $user_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group appointments
$now = time();
$pending = array_filter($appointments, fn($x) => $x['status'] === 'pending');
$confirmed = array_filter($appointments, fn($x) => $x['status'] === 'confirmed');
$completed = array_filter($appointments, fn($x) => $x['status'] === 'completed');
$cancelled = array_filter($appointments, fn($x) => $x['status'] === 'cancelled');

$upcoming = array_filter($appointments, function($a) use ($now) {
    $t = strtotime($a['appointment_date'] . ' ' . $a['appointment_time']);
    return $t > $now && in_array($a['status'], ['pending','confirmed']);
});

// Function to render appointment card
function renderAppointmentCard($a, $now, $csrf_token) {
    $t = strtotime($a['appointment_date'] . ' ' . $a['appointment_time']);
    $hours = ($t - $now) / 3600;
    $canCancel = $hours >= 24 && in_array($a['status'], ['pending', 'confirmed']);
    $isPast = $t <= $now;
    
    ob_start();
    ?>
    <div class="appointment-card <?= htmlspecialchars($a['status']) ?>">
        <div class="d-flex justify-content-between align-items-start">
            <h5 class="mb-0"><?= htmlspecialchars($a['salon_name']) ?></h5>
            <span class="status-badge <?= htmlspecialchars($a['status']) ?>">
                <?= ucfirst(htmlspecialchars($a['status'])) ?>
            </span>
        </div>

        <div class="mt-3">
            <div class="info-row">
                <i class="fas fa-cut text-primary"></i>
                <strong><?= htmlspecialchars($a['service_name']) ?></strong>
            </div>
            <div class="info-row">
                <i class="fas fa-rupee-sign text-success"></i>
                Rs <?= number_format($a['service_price'], 2) ?>
            </div>
            <div class="info-row">
                <i class="fas fa-calendar text-danger"></i>
                <?= date('l, F j, Y', strtotime($a['appointment_date'])) ?>
            </div>
            <div class="info-row">
                <i class="fas fa-clock text-warning"></i>
                <?= date('h:i A', strtotime($a['appointment_time'])) ?> 
                (<?= htmlspecialchars($a['service_duration']) ?> mins)
            </div>
            <div class="info-row">
                <i class="fas fa-map-marker-alt text-info"></i>
                <?= htmlspecialchars($a['salon_address']) ?>
            </div>
            <?php if (!empty($a['salon_phone'])): ?>
            <div class="info-row">
                <i class="fas fa-phone text-secondary"></i>
                <a href="tel:<?= htmlspecialchars($a['salon_phone']) ?>">
                    <?= htmlspecialchars($a['salon_phone']) ?>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($canCancel): ?>
        <form method="POST" class="mt-3" onsubmit="return confirm('Are you sure you want to cancel this appointment? This action cannot be undone.');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
            <button type="submit" class="btn-cancel" name="cancel_appointment">
                <i class="fas fa-times-circle me-1"></i>Cancel Appointment
            </button>
        </form>
        <?php elseif ($isPast && in_array($a['status'], ['pending', 'confirmed'])): ?>
        <div class="mt-3">
            <small class="text-muted"><i class="fas fa-info-circle"></i> This appointment time has passed</small>
        </div>
        <?php elseif (!$canCancel && in_array($a['status'], ['pending', 'confirmed'])): ?>
        <div class="mt-3">
            <small class="text-muted"><i class="fas fa-info-circle"></i> Cannot cancel within 24 hours of appointment</small>
        </div>
        <?php endif; ?>

        <hr>
        <small class="text-muted">
            <i class="far fa-clock"></i> Booked: <?= date('M d, Y h:i A', strtotime($a['created_at'])) ?>
        </small>
    </div>
    <?php
    return ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Appointments - Salonora</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
body {
    font-family: 'Poppins', sans-serif;
    background: #faf5f8ff;
}
.page-header {
    background: linear-gradient(135deg, #e91e63, #9c27b0);
    padding: 4rem 0 3rem;
    color: white;
    text-align: center;
    margin-top: 50px;
}
.appointment-card {
    background: white;
    border-radius: 18px;
    padding: 1.6rem;
    margin-bottom: 1.5rem;
    border-left: 6px solid;
    box-shadow: 0 3px 10px rgba(0,0,0,0.09);
    transition: 0.25s ease;
}
.appointment-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.12);
}
.appointment-card .info-row {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}
.appointment-card .info-row i {
    width: 22px;
    margin-right: 10px;
    font-size: 1rem;
}
.status-badge {
    padding: 0.45rem 1.05rem;
    font-weight: 600;
    border-radius: 50px;
    font-size: 0.8rem;
}
.pending {
    border-left-color: #f39c12;
}
.pending .status-badge {
    color: #f39c12;
    background: rgba(243,156,18,0.12);
}
.confirmed {
    border-left-color: #27ae60;
}
.confirmed .status-badge {
    color: #27ae60;
    background: rgba(39,174,96,0.12);
}
.cancelled {
    border-left-color: #95a5a6;
}
.cancelled .status-badge {
    color: #95a5a6;
    background: rgba(149,165,166,0.12);
}
.rejected {
    border-left-color: #e74c3c;
}
.rejected .status-badge {
    color: #e74c3c;
    background: rgba(231,76,60,0.12);
}
.completed {
    border-left-color: #3498db;
}
.completed .status-badge {
    color: #3498db;
    background: rgba(52,152,219,0.12);
}
.btn-cancel {
    background: #e74c3c;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}
.btn-cancel:hover {
    background: #c0392b;
    color: white;
    transform: scale(1.05);
}
.nav-pills .nav-link {
    color: #666;
    background: #e91e63;
    margin: 0 0.25rem;
    border-radius: 50px;
    transition: 0.3s;
}
.nav-pills .nav-link:hover {
    background: #bb1591ff;
}
.nav-pills .nav-link.active {
    background: linear-gradient(135deg, #e91e63, #9c27b0);
    color: white !important;
}
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}
.empty-state {
    text-align: center;
    background: white;
    padding: 3rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border-radius: 18px;
}
.empty-state i {
    font-size: 4rem;
    color: #c20784ff;
    margin-bottom: 1rem;
}


</style>
</head>

<body>

<?php include __DIR__.'/../header.php'; ?>

<div class="page-header">
    <h1><i class="fas fa-calendar-alt me-2"></i>My Appointments</h1>
    <p>Track and manage your salon bookings</p>
</div>

<div class="container pb-5 mt-4">

<?php if(isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['success_message']); endif; ?>

<?php if(isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['error_message']); endif; ?>

<!-- Tabs -->
<ul class="nav nav-pills justify-content-center mb-4">
    <li class="nav-item">
        <button data-tab="upcoming" class="nav-link active">
            Upcoming <span class="badge bg-light text-dark"><?= count($upcoming) ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button data-tab="all" class="nav-link">
            All <span class="badge bg-light text-dark"><?= count($appointments) ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button data-tab="pending" class="nav-link">
            Pending <span class="badge bg-light text-dark"><?= count($pending) ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button data-tab="confirmed" class="nav-link">
            Confirmed <span class="badge bg-light text-dark"><?= count($confirmed) ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button data-tab="completed" class="nav-link">
            Completed <span class="badge bg-light text-dark"><?= count($completed) ?></span>
        </button>
    </li>
</ul>

<!-- TAB: UPCOMING -->
<div class="tab-content active" id="upcoming">
    <?php if(empty($upcoming)): ?>
        <div class="empty-state">
            <i class="far fa-calendar"></i>
            <h4>No Upcoming Appointments</h4>
            <p class="text-muted">Book your next salon appointment now.</p>
            <a href="salon_view.php" class="btn btn-primary mt-3">
                <i class="fas fa-search me-2"></i>Browse Salons
            </a>
        </div>
    <?php else: ?>
        <?php foreach($upcoming as $a): ?>
            <?= renderAppointmentCard($a, $now, $_SESSION['csrf_token']) ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- TAB: ALL -->
<div class="tab-content" id="all">
    <?php if(empty($appointments)): ?>
        <div class="empty-state">
            <i class="far fa-calendar"></i>
            <h4>No Appointments Yet</h4>
            <p class="text-muted">Start booking your salon services.</p>
            <a href="salon_view.php" class="btn btn-primary mt-3">
                <i class="fas fa-search me-2"></i>Browse Salons
            </a>
        </div>
    <?php else: ?>
        <?php foreach($appointments as $a): ?>
            <?= renderAppointmentCard($a, $now, $_SESSION['csrf_token']) ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- TAB: PENDING -->
<div class="tab-content" id="pending">
    <?php if(empty($pending)): ?>
        <div class="empty-state">
            <i class="far fa-hourglass"></i>
            <h4>No Pending Appointments</h4>
            <p class="text-muted">All your appointments have been processed.</p>
        </div>
    <?php else: ?>
        <?php foreach($pending as $a): ?>
            <?= renderAppointmentCard($a, $now, $_SESSION['csrf_token']) ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- TAB: CONFIRMED -->
<div class="tab-content" id="confirmed">
    <?php if(empty($confirmed)): ?>
        <div class="empty-state">
            <i class="far fa-check-circle"></i>
            <h4>No Confirmed Appointments</h4>
            <p class="text-muted">Your confirmed appointments will appear here.</p>
        </div>
    <?php else: ?>
        <?php foreach($confirmed as $a): ?>
            <?= renderAppointmentCard($a, $now, $_SESSION['csrf_token']) ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- TAB: COMPLETED -->
<div class="tab-content" id="completed">
    <?php if(empty($completed)): ?>
        <div class="empty-state">
            <i class="far fa-check-square"></i>
            <h4>No Completed Appointments</h4>
            <p class="text-muted">Your appointment history will appear here.</p>
        </div>
    <?php else: ?>
        <?php foreach($completed as $a): ?>
            <?= renderAppointmentCard($a, $now, $_SESSION['csrf_token']) ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</div>

<?php include __DIR__ . '/../footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Tab switching logic -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('[data-tab]');
    const contents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active class from all tabs and contents
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding content
            this.classList.add('active');
            const targetContent = document.getElementById(targetTab);
            if (targetContent) {
                targetContent.classList.add('active');
            }
        });
    });
});
</script>

</body>
</html>