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

// Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
    
    if ($appointment_id) {
        // Verify the appointment belongs to this user and can be cancelled
        $checkStmt = $pdo->prepare("
            SELECT status, appointment_date, appointment_time 
            FROM appointments 
            WHERE id = :id AND user_id = :uid
        ");
        $checkStmt->execute([':id' => $appointment_id, ':uid' => $user_id]);
        $appointment = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($appointment && in_array($appointment['status'], ['pending', 'confirmed'])) {
            // Only allow cancellation if appointment is at least 24 hours away
            $appointmentDateTime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
            $hoursUntilAppointment = ($appointmentDateTime - time()) / 3600;
            
            if ($hoursUntilAppointment >= 24) {
                $cancelStmt = $pdo->prepare("
                    UPDATE appointments 
                    SET status = 'cancelled', updated_at = NOW() 
                    WHERE id = :id AND user_id = :uid
                ");
                $cancelStmt->execute([':id' => $appointment_id, ':uid' => $user_id]);
                $_SESSION['success_message'] = "Appointment cancelled successfully.";
            } else {
                $_SESSION['error_message'] = "Cannot cancel appointment less than 24 hours before scheduled time.";
            }
        } else {
            $_SESSION['error_message'] = "Cannot cancel this appointment.";
        }
    }
    header('Location: my_appointments.php');
    exit;
}

// Fetch appointments for this user
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

// Separate appointments by status
$pending = array_filter($appointments, fn($a) => $a['status'] === 'pending');
$confirmed = array_filter($appointments, fn($a) => $a['status'] === 'confirmed');
$cancelled = array_filter($appointments, fn($a) => $a['status'] === 'cancelled');
$rejected = array_filter($appointments, fn($a) => $a['status'] === 'rejected');
$completed = array_filter($appointments, fn($a) => $a['status'] === 'completed');

// Separate upcoming vs past appointments
$now = time();
$upcoming = array_filter($appointments, function($a) use ($now) {
    $appointmentTime = strtotime($a['appointment_date'] . ' ' . $a['appointment_time']);
    return $appointmentTime > $now && in_array($a['status'], ['pending', 'confirmed']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Appointments - Salonora</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    body { font-family: 'Poppins', sans-serif; background: #faf5f8ff; }
    .navbar-brand { font-weight: 800; background: linear-gradient(135deg,#e91e63,#9c27b0); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
    .page-header { background: linear-gradient(135deg,#e91e63,#9c27b0); padding:4rem 0 3rem; color:white; text-align:center; }
    .appointment-card { background:white; padding:1.5rem; margin-bottom:1.5rem; border-radius:16px; border-left:5px solid; box-shadow:0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s; }
    .appointment-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
    .appointment-card.pending{border-left-color:#f39c12;}
    .appointment-card.confirmed{border-left-color:#27ae60;}
    .appointment-card.cancelled{border-left-color:#95a5a6;}
    .appointment-card.rejected{border-left-color:#e74c3c;}
    .appointment-card.completed{border-left-color:#3498db;}
    .status-badge{padding:.5rem 1rem;border-radius:50px;font-weight:600;font-size:0.85rem;}
    .status-badge.pending{color:#f39c12;background:rgba(243,156,18,0.1);}
    .status-badge.confirmed{color:#27ae60;background:rgba(39,174,96,0.1);}
    .status-badge.cancelled{color:#95a5a6;background:rgba(149,165,166,0.1);}
    .status-badge.rejected{color:#e74c3c;background:rgba(231,76,60,0.1);}
    .status-badge.completed{color:#3498db;background:rgba(52,152,219,0.1);}
    .nav-pills .nav-link { border-radius: 50px; margin: 0 0.25rem; }
    .nav-pills .nav-link.active { background: linear-gradient(135deg,#e91e63,#9c27b0); }
    .btn-cancel { background: #e74c3c; color: white; border: none; }
    .btn-cancel:hover { background: #c0392b; color: white; }
    .alert { border-radius: 12px; }
    .appointment-actions { margin-top: 1rem; }
    .info-row { display: flex; align-items: center; margin-bottom: 0.5rem; }
    .info-row i { width: 20px; margin-right: 8px; }
  </style>
</head>
<body>

  <?php include __DIR__ . '/../header.php'; ?>

<!-- Header -->
<div class="page-header">
  <h1>My Appointments</h1>
  <p>Track and manage your salon bookings easily</p>
</div>

<div class="container pb-5 mt-4">
  <!-- Success/Error Messages -->
  <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
  <?php endif; ?>
  
  <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_SESSION['error_message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
  <?php endif; ?>

  <!-- Tabs -->
  <ul class="nav nav-pills mb-4 justify-content-center" id="appointmentTabs">
    <li class="nav-item"><button class="nav-link active" data-tab="upcoming">Upcoming (<?= count($upcoming) ?>)</button></li>
    <li class="nav-item"><button class="nav-link" data-tab="all">All (<?= count($appointments) ?>)</button></li>
    <li class="nav-item"><button class="nav-link" data-tab="pending">Pending (<?= count($pending) ?>)</button></li>
    <li class="nav-item"><button class="nav-link" data-tab="confirmed">Confirmed (<?= count($confirmed) ?>)</button></li>
    <li class="nav-item"><button class="nav-link" data-tab="completed">Completed (<?= count($completed) ?>)</button></li>
  </ul>

  <!-- Tab: Upcoming -->
  <div class="tab-content active" id="upcoming">
    <?php if (empty($upcoming)): ?>
      <div class="text-center bg-white p-5 rounded shadow-sm">
        <i class="far fa-calendar fa-4x text-muted mb-3"></i>
        <h4>No Upcoming Appointments</h4>
        <p>Book your next salon appointment now.</p>
        <a href="salon_view.php" class="btn btn-primary">Browse Salons</a>
      </div>
    <?php else: ?>
      <?php foreach ($upcoming as $a): 
        $appointmentTime = strtotime($a['appointment_date'] . ' ' . $a['appointment_time']);
        $hoursUntil = ($appointmentTime - time()) / 3600;
        $canCancel = $hoursUntil >= 24 && in_array($a['status'], ['pending', 'confirmed']);
      ?>
        <div class="appointment-card <?= $a['status'] ?>">
          <div class="d-flex justify-content-between align-items-start">
            <h5 class="mb-0"><?= htmlspecialchars($a['salon_name']) ?></h5>
            <span class="status-badge <?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
          </div>
          
          <div class="mt-3">
            <div class="info-row">
              <i class="fas fa-cut text-primary"></i>
              <span><strong><?= htmlspecialchars($a['service_name']) ?></strong></span>
            </div>
            <div class="info-row">
              <i class="fas fa-rupee-sign text-success"></i>
              <span>Rs <?= number_format($a['service_price'],2) ?></span>
            </div>
            <div class="info-row">
              <i class="fas fa-calendar text-danger"></i>
              <span><?= date('l, F j, Y', strtotime($a['appointment_date'])) ?></span>
            </div>
            <div class="info-row">
              <i class="fas fa-clock text-warning"></i>
              <span><?= date('h:i A', strtotime($a['appointment_time'])) ?> (<?= $a['service_duration'] ?> mins)</span>
            </div>
            <div class="info-row">
              <i class="fas fa-map-marker-alt text-info"></i>
              <span><?= htmlspecialchars($a['salon_address']) ?></span>
            </div>
            <?php if (!empty($a['salon_phone'])): ?>
            <div class="info-row">
              <i class="fas fa-phone text-secondary"></i>
              <span><?= htmlspecialchars($a['salon_phone']) ?></span>
            </div>
            <?php endif; ?>
          </div>

          <?php if ($canCancel): ?>
          <div class="appointment-actions">
            <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this appointment?');" style="display:inline;">
              <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
              <button type="submit" name="cancel_appointment" class="btn btn-sm btn-cancel">
                <i class="fas fa-times-circle me-1"></i> Cancel Appointment
              </button>
            </form>
          </div>
          <?php elseif ($hoursUntil < 24 && $hoursUntil > 0): ?>
          <div class="appointment-actions">
            <small class="text-muted"><i class="fas fa-info-circle"></i> Cannot cancel within 24 hours of appointment</small>
          </div>
          <?php endif; ?>
          
          <hr class="my-2">
          <small class="text-muted"><i class="far fa-clock"></i> Booked: <?= date('M d, Y h:i A', strtotime($a['created_at'])) ?></small>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Tab: All -->
  <div class="tab-content" id="all">
    <?php if (empty($appointments)): ?>
      <div class="text-center bg-white p-5 rounded shadow-sm">
        <i class="far fa-calendar-times fa-4x text-muted mb-3"></i>
        <h4>No Appointments Found</h4>
        <p>Start booking your favorite salons now.</p>
        <a href="salon_view.php" class="btn btn-primary">Browse Salons</a>
      </div>
    <?php else: ?>
      <?php foreach ($appointments as $a): ?>
        <div class="appointment-card <?= $a['status'] ?>">
          <div class="d-flex justify-content-between align-items-start">
            <h5 class="mb-0"><?= htmlspecialchars($a['salon_name']) ?></h5>
            <span class="status-badge <?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
          </div>
          
          <div class="mt-3">
            <div class="info-row">
              <i class="fas fa-cut text-primary"></i>
              <span><strong><?= htmlspecialchars($a['service_name']) ?></strong> - Rs <?= number_format($a['service_price'],2) ?></span>
            </div>
            <div class="info-row">
              <i class="fas fa-calendar text-danger"></i>
              <span><?= date('M d, Y', strtotime($a['appointment_date'])) ?> at <?= date('h:i A', strtotime($a['appointment_time'])) ?></span>
            </div>
            <div class="info-row">
              <i class="fas fa-map-marker-alt text-info"></i>
              <span><?= htmlspecialchars($a['salon_address']) ?></span>
            </div>
          </div>
          
          <hr class="my-2">
          <small class="text-muted"><i class="far fa-clock"></i> Updated: <?= date('M d, Y h:i A', strtotime($a['updated_at'] ?? $a['created_at'])) ?></small>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Tab: Pending -->
  <div class="tab-content" id="pending">
    <?php if (empty($pending)): ?>
      <div class="text-center bg-white p-5 rounded shadow-sm">
        <i class="far fa-clock fa-4x text-muted mb-3"></i>
        <h4>No Pending Appointments</h4>
      </div>
    <?php else: ?>
      <?php foreach ($pending as $a): ?>
        <div class="appointment-card pending">
          <div class="d-flex justify-content-between">
            <h5><?= htmlspecialchars($a['salon_name']) ?></h5>
            <span class="status-badge pending">Pending</span>
          </div>
          <p class="mb-1"><strong><?= htmlspecialchars($a['service_name']) ?></strong> - Rs <?= number_format($a['service_price'],2) ?></p>
          <p class="mb-1"><?= date('M d, Y', strtotime($a['appointment_date'])) ?> - <?= date('h:i A', strtotime($a['appointment_time'])) ?></p>
          <small class="text-muted">Waiting for salon confirmation</small>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Tab: Confirmed -->
  <div class="tab-content" id="confirmed">
    <?php if (empty($confirmed)): ?>
      <div class="text-center bg-white p-5 rounded shadow-sm">
        <i class="fas fa-check-circle fa-4x text-muted mb-3"></i>
        <h4>No Confirmed Appointments</h4>
      </div>
    <?php else: ?>
      <?php foreach ($confirmed as $a): ?>
        <div class="appointment-card confirmed">
          <div class="d-flex justify-content-between">
            <h5><?= htmlspecialchars($a['salon_name']) ?></h5>
            <span class="status-badge confirmed">Confirmed</span>
          </div>
          <p class="mb-1"><strong><?= htmlspecialchars($a['service_name']) ?></strong> - Rs <?= number_format($a['service_price'],2) ?></p>
          <p class="mb-1"><?= date('M d, Y', strtotime($a['appointment_date'])) ?> - <?= date('h:i A', strtotime($a['appointment_time'])) ?></p>
          <small class="text-success"><i class="fas fa-check"></i> Confirmed by salon</small>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Tab: Completed -->
  <div class="tab-content" id="completed">
    <?php if (empty($completed)): ?>
      <div class="text-center bg-white p-5 rounded shadow-sm">
        <i class="far fa-calendar-check fa-4x text-muted mb-3"></i>
        <h4>No Completed Appointments</h4>
      </div>
    <?php else: ?>
      <?php foreach ($completed as $a): ?>
        <div class="appointment-card completed">
          <div class="d-flex justify-content-between">
            <h5><?= htmlspecialchars($a['salon_name']) ?></h5>
            <span class="status-badge completed">Completed</span>
          </div>
          <p class="mb-1"><strong><?= htmlspecialchars($a['service_name']) ?></strong> - Rs <?= number_format($a['service_price'],2) ?></p>
          <p class="mb-1"><?= date('M d, Y', strtotime($a['appointment_date'])) ?> - <?= date('h:i A', strtotime($a['appointment_time'])) ?></p>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
  <?php include __DIR__ . '/../footer.php'; ?>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const tabs = document.querySelectorAll('[data-tab]');
  const contents = document.querySelectorAll('.tab-content');
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      contents.forEach(c => c.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(tab.getAttribute('data-tab')).classList.add('active');
    });
  });
</script>
</body>
</html>