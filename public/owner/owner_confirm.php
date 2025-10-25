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
  
  <style>
    :root {
      --primary: #e91e63;
      --primary-dark: #c2185b;
      --secondary: #9c27b0;
      --accent: #ff6b9d;
      --dark: #1a1a2e;
      --light: #f5f7fa;
      --text-dark: #2d3436;
      --text-light: #636e72;
      --gradient-primary: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
      --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
      --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.12);
      --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.15);
      --shadow-xl: 0 20px 60px rgba(0, 0, 0, 0.2);
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: var(--light);
      color: var(--text-dark);
    }

    /* Navbar */
    .navbar {
      background: white !important;
      box-shadow: var(--shadow-sm);
      padding: 1rem 0;
    }

    .navbar-brand {
      font-size: 1.5rem;
      font-weight: 800;
      background: var(--gradient-primary);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .btn-gradient {
      background: var(--gradient-primary);
      color: white;
      border: none;
      padding: 0.5rem 1.5rem;
      border-radius: 50px;
      font-weight: 600;
      transition: var(--transition);
    }

    .btn-gradient:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
      color: white;
    }

    /* Page Header */
    .page-header {
      background: var(--gradient-primary);
      padding: 4rem 0 3rem;
      margin-bottom: 3rem;
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
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.1)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,144C960,149,1056,139,1152,122.7C1248,107,1344,85,1392,74.7L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
      opacity: 0.5;
    }

    .page-header-content {
      position: relative;
      z-index: 2;
      text-align: center;
    }

    .page-title {
      font-size: 2.5rem;
      font-weight: 800;
      color: white;
      margin-bottom: 0.5rem;
    }

    .page-subtitle {
      font-size: 1.1rem;
      color: rgba(255, 255, 255, 0.9);
    }

    /* Alerts */
    .alert {
      border: none;
      border-radius: 16px;
      padding: 1.25rem 1.5rem;
      margin-bottom: 2rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      animation: slideIn 0.3s ease;
    }

    .alert i {
      font-size: 1.5rem;
    }

    .alert-success {
      background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
      color: white;
    }

    .alert-danger {
      background: linear-gradient(135deg, #d63031 0%, #e17055 100%);
      color: white;
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

    /* Stats Bar */
    .stats-bar {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 3rem;
    }

    .stat-card {
      background: white;
      padding: 1.5rem;
      border-radius: 16px;
      box-shadow: var(--shadow-sm);
      display: flex;
      align-items: center;
      gap: 1rem;
      transition: var(--transition);
      cursor: pointer;
    }

    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-md);
    }

    .stat-card.active {
      border: 2px solid var(--primary);
    }

    .stat-icon {
      width: 60px;
      height: 60px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: white;
    }

    .stat-icon.pending {
      background: linear-gradient(135deg, #f39c12 0%, #f1c40f 100%);
    }

    .stat-icon.confirmed {
      background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    }

    .stat-icon.completed {
      background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    }

    .stat-icon.rejected {
      background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    }

    .stat-info h3 {
      font-size: 2rem;
      font-weight: 800;
      margin: 0;
      color: var(--text-dark);
    }

    .stat-info p {
      margin: 0;
      color: var(--text-light);
      font-size: 0.9rem;
    }

    /* Appointment Cards */
    .appointments-section {
      background: white;
      border-radius: 20px;
      padding: 2rem;
      box-shadow: var(--shadow-sm);
      margin-bottom: 2rem;
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
    }

    .section-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--text-dark);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .section-title i {
      width: 40px;
      height: 40px;
      background: var(--gradient-primary);
      color: white;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
    }

    .appointment-card {
      background: white;
      border: 2px solid #e9ecef;
      border-radius: 16px;
      padding: 1.5rem;
      margin-bottom: 1rem;
      transition: var(--transition);
      border-left: 5px solid;
    }

    .appointment-card.pending {
      border-left-color: #f39c12;
    }

    .appointment-card.confirmed {
      border-left-color: #27ae60;
    }

    .appointment-card.completed {
      border-left-color: #3498db;
    }

    .appointment-card.rejected {
      border-left-color: #e74c3c;
    }

    .appointment-card:hover {
      box-shadow: var(--shadow-md);
      transform: translateY(-2px);
    }

    .appointment-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 1rem;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .appointment-id {
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--primary);
    }

    .status-badge {
      padding: 0.4rem 1rem;
      border-radius: 50px;
      font-weight: 600;
      font-size: 0.85rem;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .status-badge.pending {
      background: rgba(243, 156, 18, 0.1);
      color: #f39c12;
    }

    .status-badge.confirmed {
      background: rgba(39, 174, 96, 0.1);
      color: #27ae60;
    }

    .status-badge.completed {
      background: rgba(52, 152, 219, 0.1);
      color: #3498db;
    }

    .status-badge.rejected {
      background: rgba(231, 76, 60, 0.1);
      color: #e74c3c;
    }

    .appointment-body {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .appointment-detail {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .detail-icon {
      width: 40px;
      height: 40px;
      background: linear-gradient(135deg, rgba(233, 30, 99, 0.1) 0%, rgba(156, 39, 176, 0.1) 100%);
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary);
      font-size: 1rem;
    }

    .detail-info h6 {
      margin: 0;
      font-size: 0.8rem;
      color: var(--text-light);
      font-weight: 500;
    }

    .detail-info p {
      margin: 0;
      font-size: 0.95rem;
      color: var(--text-dark);
      font-weight: 600;
    }

    .appointment-actions {
      display: flex;
      gap: 0.75rem;
      padding-top: 1rem;
      border-top: 1px solid #e9ecef;
    }

    .btn-action {
      flex: 1;
      padding: 0.75rem;
      border-radius: 10px;
      font-weight: 600;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      border: none;
      cursor: pointer;
    }

    .btn-confirm {
      background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
      color: white;
    }

    .btn-confirm:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(39, 174, 96, 0.4);
    }

    .btn-reject {
      background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
      color: white;
    }

    .btn-reject:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4);
    }

    .btn-view {
      background: white;
      color: var(--primary);
      border: 2px solid var(--primary);
    }

    .btn-view:hover {
      background: var(--primary);
      color: white;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 3rem 2rem;
    }

    .empty-state i {
      font-size: 4rem;
      color: #dfe6e9;
      margin-bottom: 1rem;
    }

    .empty-state h4 {
      color: var(--text-dark);
      margin-bottom: 0.5rem;
    }

    .empty-state p {
      color: var(--text-light);
    }

    /* Responsive */
    @media (max-width: 768px) {
      .page-title {
        font-size: 2rem;
      }

      .stats-bar {
        grid-template-columns: 1fr;
      }

      .appointment-body {
        grid-template-columns: 1fr;
      }

      .appointment-actions {
        flex-direction: column;
      }
    }
  </style>
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