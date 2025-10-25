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

// Fetch appointments for this user
$stmt = $pdo->prepare("
    SELECT 
        a.*, 
        s.name AS salon_name,
        s.address AS salon_address,
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
$rejected = array_filter($appointments, fn($a) => $a['status'] === 'rejected');
$completed = array_filter($appointments, fn($a) => $a['status'] === 'completed');
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
    /* Styles shortened for brevity (same as your version, unchanged) */
    body { font-family: 'Poppins', sans-serif; background: #f5f7fa; }
    .navbar-brand { font-weight: 800; background: linear-gradient(135deg,#e91e63,#9c27b0); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
    .page-header { background: linear-gradient(135deg,#e91e63,#9c27b0); padding:4rem 0 3rem; color:white; text-align:center; }
    .appointment-card { background:white; padding:1.5rem; margin-bottom:1.5rem; border-radius:16px; border-left:5px solid; box-shadow:0 2px 8px rgba(0,0,0,0.08); }
    .appointment-card.pending{border-left-color:#f39c12;}
    .appointment-card.confirmed{border-left-color:#27ae60;}
    .appointment-card.rejected{border-left-color:#e74c3c;}
    .appointment-card.completed{border-left-color:#3498db;}
    .status-badge{padding:.5rem 1rem;border-radius:50px;font-weight:600;}
    .status-badge.pending{color:#f39c12;background:rgba(243,156,18,0.1);}
    .status-badge.confirmed{color:#27ae60;background:rgba(39,174,96,0.1);}
    .status-badge.rejected{color:#e74c3c;background:rgba(231,76,60,0.1);}
    .status-badge.completed{color:#3498db;background:rgba(52,152,219,0.1);}
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="../../index.php"><i class="fas fa-spa"></i> Salonora</a>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav align-items-center">
        <li class="nav-item"><a href="index.php" class="nav-link">Home</a></li>
        <li class="nav-item"><a href="salon_view.php" class="nav-link">Salons</a></li>
        <li class="nav-item"><a href="my_appointments.php" class="nav-link active">Appointments</a></li>
        <li class="nav-item"><a href="profile.php" class="nav-link">Profile</a></li>
        <li class="nav-item ms-3">
          <a href="../logout.php" class="btn btn-sm btn-danger"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Header -->
<div class="page-header">
  <h1>My Appointments</h1>
  <p>Track and manage your salon bookings easily</p>
</div>

<div class="container pb-5">
  <!-- Tabs -->
  <ul class="nav nav-pills mb-4 justify-content-center" id="appointmentTabs">
    <li class="nav-item"><button class="nav-link active" data-tab="all">All (<?= count($appointments) ?>)</button></li>
    <li class="nav-item"><button class="nav-link" data-tab="pending">Pending (<?= count($pending) ?>)</button></li>
    <li class="nav-item"><button class="nav-link" data-tab="confirmed">Confirmed (<?= count($confirmed) ?>)</button></li>
    <li class="nav-item"><button class="nav-link" data-tab="completed">Completed (<?= count($completed) ?>)</button></li>
  </ul>

  <!-- Tab: All -->
  <div class="tab-content active" id="all">
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
          <div class="d-flex justify-content-between">
            <h5><?= htmlspecialchars($a['salon_name']) ?></h5>
            <span class="status-badge <?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
          </div>
          <p><i class="fas fa-map-marker-alt text-danger"></i> <?= htmlspecialchars($a['salon_address']) ?></p>
          <p><strong>Service:</strong> <?= htmlspecialchars($a['service_name']) ?> | Rs <?= number_format($a['service_price'],2) ?></p>
          <p><strong>Date:</strong> <?= date('M d, Y', strtotime($a['appointment_date'])) ?> | <strong>Time:</strong> <?= date('h:i A', strtotime($a['appointment_time'])) ?></p>
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
          <p><?= htmlspecialchars($a['service_name']) ?> - Rs <?= number_format($a['service_price'],2) ?></p>
          <p><?= date('M d, Y', strtotime($a['appointment_date'])) ?> - <?= date('h:i A', strtotime($a['appointment_time'])) ?></p>
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
          <p><?= htmlspecialchars($a['service_name']) ?> - Rs <?= number_format($a['service_price'],2) ?></p>
          <p><?= date('M d, Y', strtotime($a['appointment_date'])) ?> - <?= date('h:i A', strtotime($a['appointment_time'])) ?></p>
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
          <p><?= htmlspecialchars($a['service_name']) ?> - Rs <?= number_format($a['service_price'],2) ?></p>
          <p><?= date('M d, Y', strtotime($a['appointment_date'])) ?> - <?= date('h:i A', strtotime($a['appointment_time'])) ?></p>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

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
