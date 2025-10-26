<?php
// public/owner/appointments.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];

// Fetch all appointments for owner's salons
$stmt = $pdo->prepare("
    SELECT 
        a.id AS appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.created_at,
        a.updated_at,
        u.username AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone,
        srv.name AS service_name,
        srv.price AS service_price,
        srv.duration AS service_duration,
        sal.name AS salon_name,
        sal.id AS salon_id
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    JOIN services srv ON a.service_id = srv.id
    JOIN salons sal ON a.salon_id = sal.id
    WHERE sal.owner_id = ?
    ORDER BY 
        CASE a.status 
            WHEN 'pending' THEN 1
            WHEN 'confirmed' THEN 2
            WHEN 'completed' THEN 3
            WHEN 'rejected' THEN 4
        END,
        a.appointment_date DESC,
        a.appointment_time DESC
");
$stmt->execute([$owner_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate by status
$pending = array_filter($appointments, fn($a) => $a['status'] === 'pending');
$confirmed = array_filter($appointments, fn($a) => $a['status'] === 'confirmed');
$completed = array_filter($appointments, fn($a) => $a['status'] === 'completed');
$rejected = array_filter($appointments, fn($a) => $a['status'] === 'rejected');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Appointments - Salonora</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/owner_confirm.css">

 
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg">
    <div class="container">
      <a class="navbar-brand" href="../../index.php">
        <i class="fas fa-spa"></i> Salonora
      </a>
      <div class="ms-auto">
        <a href="dashboard.php" class="btn btn-gradient btn-sm">
          <i class="fas fa-arrow-left me-1"></i> Dashboard
        </a>
      </div>
    </div>
  </nav>

  <!-- Page Header -->
  <div class="page-header">
    <div class="container">
      <div class="page-header-content">
        <h1 class="page-title">Appointment Management</h1>
        <p class="page-subtitle">Review and manage customer bookings</p>
      </div>
    </div>
  </div>

  <div class="container pb-5">
    <!-- Alerts -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></span>
      </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></span>
      </div>
    <?php endif; ?>

    <!-- Stats Bar -->
    <div class="stats-bar">
      <div class="stat-card" onclick="filterAppointments('pending')">
        <div class="stat-icon pending">
          <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
          <h3><?= count($pending) ?></h3>
          <p>Pending</p>
        </div>
      </div>
      <div class="stat-card" onclick="filterAppointments('confirmed')">
        <div class="stat-icon confirmed">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
          <h3><?= count($confirmed) ?></h3>
          <p>Confirmed</p>
        </div>
      </div>
      <div class="stat-card" onclick="filterAppointments('completed')">
        <div class="stat-icon completed">
          <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-info">
          <h3><?= count($completed) ?></h3>
          <p>Completed</p>
        </div>
      </div>
      <div class="stat-card" onclick="filterAppointments('rejected')">
        <div class="stat-icon rejected">
          <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-info">
          <h3><?= count($rejected) ?></h3>
          <p>Rejected</p>
        </div>
      </div>
    </div>

    <!-- Appointments Section -->
    <div class="appointments-section">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-calendar-alt"></i>
          All Appointments
        </h2>
      </div>

      <?php if (empty($appointments)): ?>
        <div class="empty-state">
          <i class="far fa-calendar-times"></i>
          <h4>No Appointments Yet</h4>
          <p>Customer bookings will appear here</p>
        </div>
      <?php else: ?>
        <div id="appointmentsContainer">
          <?php foreach ($appointments as $a): ?>
            <div class="appointment-card <?= $a['status'] ?>" data-status="<?= $a['status'] ?>">
              <div class="appointment-header">
                <div class="appointment-id">#<?= $a['appointment_id'] ?></div>
                <span class="status-badge <?= $a['status'] ?>">
                  <?php if ($a['status'] === 'pending'): ?>
                    <i class="fas fa-clock"></i>
                  <?php elseif ($a['status'] === 'confirmed'): ?>
                    <i class="fas fa-check-circle"></i>
                  <?php elseif ($a['status'] === 'rejected'): ?>
                    <i class="fas fa-times-circle"></i>
                  <?php else: ?>
                    <i class="fas fa-calendar-check"></i>
                  <?php endif; ?>
                  <?= ucfirst($a['status']) ?>
                </span>
              </div>

              <div class="appointment-body">
                <div class="appointment-detail">
                  <div class="detail-icon">
                    <i class="fas fa-store"></i>
                  </div>
                  <div class="detail-info">
                    <h6>Salon</h6>
                    <p><?= htmlspecialchars($a['salon_name']) ?></p>
                  </div>
                </div>
                <div class="appointment-detail">
                  <div class="detail-icon">
                    <i class="fas fa-user"></i>
                  </div>
                  <div class="detail-info">
                    <h6>Customer</h6>
                    <p><?= htmlspecialchars($a['customer_name']) ?></p>
                  </div>
                </div>
                <div class="appointment-detail">
                  <div class="detail-icon">
                    <i class="fas fa-concierge-bell"></i>
                  </div>
                  <div class="detail-info">
                    <h6>Service</h6>
                    <p><?= htmlspecialchars($a['service_name']) ?></p>
                  </div>
                </div>
                <div class="appointment-detail">
                  <div class="detail-icon">
                    <i class="far fa-calendar"></i>
                  </div>
                  <div class="detail-info">
                    <h6>Date</h6>
                    <p><?= date('M d, Y', strtotime($a['appointment_date'])) ?></p>
                  </div>
                </div>
                <div class="appointment-detail">
                  <div class="detail-icon">
                    <i class="far fa-clock"></i>
                  </div>
                  <div class="detail-info">
                    <h6>Time</h6>
                    <p><?= date('h:i A', strtotime($a['appointment_time'])) ?></p>
                  </div>
                </div>
                <div class="appointment-detail">
                  <div class="detail-icon">
                    <i class="fas fa-tag"></i>
                  </div>
                  <div class="detail-info">
                    <h6>Price</h6>
                    <p>Rs <?= number_format($a['service_price'], 2) ?></p>
                  </div>
                </div>
              </div>

              <?php if ($a['status'] === 'pending'): ?>
                <div class="appointment-actions">
                  <form method="POST" action="owner_confirm.php" style="flex:1;">
                    <input type="hidden" name="appointment_id" value="<?= $a['appointment_id'] ?>">
                    <input type="hidden" name="action" value="confirm">
                    <button type="submit" class="btn-action btn-confirm">
                      <i class="fas fa-check"></i> Confirm
                    </button>
                  </form>
                  <form method="POST" action="owner_confirm.php" style="flex:1;">
                    <input type="hidden" name="appointment_id" value="<?= $a['appointment_id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="btn-action btn-reject" onclick="return confirm('Are you sure you want to reject this appointment?');">
                      <i class="fas fa-times"></i> Reject
                    </button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Filter appointments by status
    function filterAppointments(status) {
      const cards = document.querySelectorAll('.appointment-card');
      const statCards = document.querySelectorAll('.stat-card');
      
      // Remove active class from all stat cards
      statCards.forEach(card => card.classList.remove('active'));
      
      if (status === 'all') {
        cards.forEach(card => card.style.display = 'block');
      } else {
        cards.forEach(card => {
          if (card.dataset.status === status) {
            card.style.display = 'block';
            // Add active class to clicked stat card
            event.currentTarget.classList.add('active');
          } else {
            card.style.display = 'none';
          }
        });
      }
    }

    // Auto-hide alerts
    setTimeout(() => {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
      });
    }, 5000);

    // Auto-refresh for pending appointments
    const pendingCount = <?= count($pending) ?>;
    if (pendingCount > 0) {
      setInterval(() => {
        location.reload();
      }, 60000); // Refresh every minute
    }
  </script>
</body>
</html>