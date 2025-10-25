<?php
// public/owner/dashboard.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];

// Fetch all salons owned by this owner
$stmt = $pdo->prepare("SELECT id AS salon_id, name, address, image FROM salons WHERE owner_id = ?");
$stmt->execute([$owner_id]);
$salons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM appointments a 
    JOIN salons s ON a.salon_id = s.id 
    WHERE s.owner_id = ?
");
$stmt->execute([$owner_id]);
$total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM appointments a 
    JOIN salons s ON a.salon_id = s.id 
    WHERE s.owner_id = ? AND a.status = 'pending'
");
$stmt->execute([$owner_id]);
$pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM services sr
    JOIN salons s ON sr.salon_id = s.id 
    WHERE s.owner_id = ?
");
$stmt->execute([$owner_id]);
$total_services = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM reviews r
    JOIN salons s ON r.salon_id = s.id 
    WHERE s.owner_id = ?
");
$stmt->execute([$owner_id]);
$total_reviews = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Fetch recent appointments (last 10) for all salons
$stmt = $pdo->prepare("
    SELECT 
        a.id AS appointment_id,
        s.name AS service_name,
        u.username AS customer_name,
        sa.name AS salon_name,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.created_at
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN users u ON a.user_id = u.id
    JOIN salons sa ON a.salon_id = sa.id
    WHERE sa.owner_id = ?
    ORDER BY a.created_at DESC
    LIMIT 10
");
$stmt->execute([$owner_id]);
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Owner Dashboard - Salonora</title>
  
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
      --gradient-secondary: linear-gradient(135deg, #ff6b9d 0%, #c471ed 100%);
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

    .navbar-brand i {
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

    /* Welcome Header */
    .welcome-header {
      background: var(--gradient-primary);
      padding: 3rem 0;
      margin-bottom: 3rem;
      position: relative;
      overflow: hidden;
    }

    .welcome-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.1)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,144C960,149,1056,139,1152,122.7C1248,107,1344,85,1392,74.7L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
      opacity: 0.5;
    }

    .welcome-content {
      position: relative;
      z-index: 2;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .welcome-text h1 {
      color: white;
      font-size: 2.5rem;
      font-weight: 800;
      margin-bottom: 0.5rem;
    }

    .welcome-text p {
      color: rgba(255, 255, 255, 0.9);
      font-size: 1.1rem;
    }

    .welcome-actions {
      display: flex;
      gap: 1rem;
    }

    .btn-action {
      background: white;
      color: var(--primary);
      border: none;
      padding: 1rem 2rem;
      border-radius: 50px;
      font-weight: 700;
      transition: var(--transition);
      display: flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
    }

    .btn-action:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
      color: var(--primary);
    }

    .btn-logout {
      background: rgba(255, 255, 255, 0.2);
      color: white;
      border: 2px solid white;
    }

    .btn-logout:hover {
      background: white;
      color: var(--primary);
    }

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
      margin-bottom: 3rem;
    }

    .stat-card {
      background: white;
      padding: 2rem;
      border-radius: 20px;
      box-shadow: var(--shadow-sm);
      display: flex;
      align-items: center;
      gap: 1.5rem;
      transition: var(--transition);
      border: 1px solid rgba(0, 0, 0, 0.05);
      position: relative;
      overflow: hidden;
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      width: 100px;
      height: 100px;
      background: var(--gradient-primary);
      opacity: 0.05;
      border-radius: 50%;
      transform: translate(30%, -30%);
    }

    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-lg);
    }

    .stat-icon {
      width: 70px;
      height: 70px;
      border-radius: 15px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      color: white;
      flex-shrink: 0;
    }

    .stat-icon.salons {
      background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
    }

    .stat-icon.appointments {
      background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    }

    .stat-icon.services {
      background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    }

    .stat-icon.reviews {
      background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    }

    .stat-info {
      flex: 1;
    }

    .stat-info h3 {
      font-size: 2.5rem;
      font-weight: 800;
      margin: 0;
      color: var(--text-dark);
    }

    .stat-info p {
      margin: 0;
      color: var(--text-light);
      font-size: 0.95rem;
    }

    .stat-badge {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: rgba(233, 30, 99, 0.1);
      color: var(--primary);
      padding: 0.25rem 0.75rem;
      border-radius: 50px;
      font-size: 0.8rem;
      font-weight: 600;
    }

    /* Section */
    .section {
      background: white;
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: var(--shadow-sm);
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .section-title {
      font-size: 1.8rem;
      font-weight: 700;
      color: var(--text-dark);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .section-title i {
      width: 45px;
      height: 45px;
      background: var(--gradient-primary);
      color: white;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .btn-add {
      background: var(--gradient-primary);
      color: white;
      border: none;
      padding: 0.75rem 2rem;
      border-radius: 50px;
      font-weight: 700;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
    }

    .btn-add:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
      color: white;
    }

    /* Salon Cards */
    .salons-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 1.5rem;
    }

    .salon-card {
      background: white;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: var(--shadow-sm);
      transition: var(--transition);
      border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .salon-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-lg);
    }

    .salon-image {
      width: 100%;
      height: 200px;
      object-fit: cover;
      background: linear-gradient(135deg, rgba(233, 30, 99, 0.1) 0%, rgba(156, 39, 176, 0.1) 100%);
    }

    .salon-body {
      padding: 1.5rem;
    }

    .salon-name {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 0.5rem;
    }

    .salon-address {
      color: var(--text-light);
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 1.5rem;
    }

    .salon-address i {
      color: var(--primary);
    }

    .salon-actions {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 0.5rem;
    }

    .btn-salon-action {
      padding: 0.75rem;
      border-radius: 12px;
      font-weight: 600;
      font-size: 0.9rem;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      text-decoration: none;
      border: 2px solid;
    }

    .btn-edit {
      background: white;
      color: #3498db;
      border-color: #3498db;
    }

    .btn-edit:hover {
      background: #3498db;
      color: white;
    }

    .btn-services {
      background: white;
      color: #27ae60;
      border-color: #27ae60;
    }

    .btn-services:hover {
      background: #27ae60;
      color: white;
    }

    .btn-appointments {
      background: white;
      color: var(--primary);
      border-color: var(--primary);
    }

    .btn-appointments:hover {
      background: var(--primary);
      color: white;
    }

    /* Recent Appointments Table */
    .appointments-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
    }

    .appointments-table thead th {
      background: linear-gradient(135deg, rgba(233, 30, 99, 0.1) 0%, rgba(156, 39, 176, 0.1) 100%);
      color: var(--text-dark);
      font-weight: 700;
      padding: 1rem;
      text-align: left;
      border: none;
      font-size: 0.9rem;
    }

    .appointments-table thead th:first-child {
      border-radius: 12px 0 0 0;
    }

    .appointments-table thead th:last-child {
      border-radius: 0 12px 0 0;
    }

    .appointments-table tbody td {
      padding: 1rem;
      border-bottom: 1px solid #e9ecef;
      color: var(--text-dark);
    }

    .appointments-table tbody tr:last-child td {
      border-bottom: none;
    }

    .appointments-table tbody tr:hover {
      background: rgba(233, 30, 99, 0.02);
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

    .status-badge.rejected {
      background: rgba(231, 76, 60, 0.1);
      color: #e74c3c;
    }

    .status-badge.completed {
      background: rgba(52, 152, 219, 0.1);
      color: #3498db;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 3rem 2rem;
      color: var(--text-light);
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

    /* Responsive */
    @media (max-width: 768px) {
      .welcome-text h1 {
        font-size: 2rem;
      }

      .welcome-actions {
        width: 100%;
      }

      .btn-action {
        flex: 1;
        justify-content: center;
      }

      .stats-grid {
        grid-template-columns: 1fr;
      }

      .salons-grid {
        grid-template-columns: 1fr;
      }

      .salon-actions {
        grid-template-columns: 1fr;
      }

      .appointments-table {
        font-size: 0.85rem;
      }

      .appointments-table thead th,
      .appointments-table tbody td {
        padding: 0.75rem 0.5rem;
      }
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .stat-card {
      animation: fadeIn 0.5s ease forwards;
    }

    .stat-card:nth-child(1) { animation-delay: 0.1s; }
    .stat-card:nth-child(2) { animation-delay: 0.2s; }
    .stat-card:nth-child(3) { animation-delay: 0.3s; }
    .stat-card:nth-child(4) { animation-delay: 0.4s; }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg">
    <div class="container">
      <a class="navbar-brand" href="../../index.php">
        <i class="fas fa-spa"></i> Salonora
      </a>
    </div>
  </nav>

  <!-- Welcome Header -->
  <div class="welcome-header">
    <div class="container">
      <div class="welcome-content">
        <div class="welcome-text">
          <h1>Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>! 👋</h1>
          <p>Manage your salons and appointments from your dashboard</p>
        </div>
        <div class="welcome-actions">
          <a href="salon_add.php" class="btn-action">
            <i class="fas fa-plus-circle"></i> Add Salon
          </a>
          <a href="../logout.php" class="btn-action btn-logout">
            <i class="fas fa-sign-out-alt"></i> Logout
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="container pb-5">
    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon salons">
          <i class="fas fa-store"></i>
        </div>
        <div class="stat-info">
          <h3><?= count($salons) ?></h3>
          <p>Total Salons</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon appointments">
          <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="stat-info">
          <h3><?= $total_appointments ?></h3>
          <p>Total Appointments</p>
        </div>
        <?php if ($pending_appointments > 0): ?>
          <span class="stat-badge"><?= $pending_appointments ?> Pending</span>
        <?php endif; ?>
      </div>
      <div class="stat-card">
        <div class="stat-icon services">
          <i class="fas fa-concierge-bell"></i>
        </div>
        <div class="stat-info">
          <h3><?= $total_services ?></h3>
          <p>Total Services</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon reviews">
          <i class="fas fa-star"></i>
        </div>
        <div class="stat-info">
          <h3><?= $total_reviews ?></h3>
          <p>Total Reviews</p>
        </div>
      </div>
    </div>

    <!-- Salons Section -->
    <div class="section">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-store"></i>
          Your Salons
        </h2>
        <a href="salon_add.php" class="btn-add">
          <i class="fas fa-plus"></i> Add New Salon
        </a>
      </div>

      <?php if (empty($salons)): ?>
        <div class="empty-state">
          <i class="fas fa-store-slash"></i>
          <h4>No Salons Yet</h4>
          <p>Start by adding your first salon to the platform</p>
          <a href="salon_add.php" class="btn-add mt-3">
            <i class="fas fa-plus"></i> Add Your First Salon
          </a>
        </div>
      <?php else: ?>
        <div class="salons-grid">
          <?php foreach ($salons as $salon): ?>
            <div class="salon-card">
              <?php if ($salon['image']): ?>
                <img src="../../<?= htmlspecialchars($salon['image']) ?>" class="salon-image" alt="<?= htmlspecialchars($salon['name']) ?>">
              <?php else: ?>
                <div class="salon-image"></div>
              <?php endif; ?>
              <div class="salon-body">
                <h3 class="salon-name"><?= htmlspecialchars($salon['name']) ?></h3>
                <p class="salon-address">
                  <i class="fas fa-map-marker-alt"></i>
                  <?= htmlspecialchars($salon['address']) ?>
                </p>
                <div class="salon-actions">
                  <a href="salon_edit.php?id=<?= $salon['salon_id'] ?>" class="btn-salon-action btn-edit">
                    <i class="fas fa-edit"></i> Edit
                  </a>
                  <a href="services.php?salon_id=<?= $salon['salon_id'] ?>" class="btn-salon-action btn-services">
                    <i class="fas fa-list"></i> Services
                  </a>
                  <a href="appointments.php" class="btn-salon-action btn-appointments">
                    <i class="fas fa-calendar"></i> Bookings
                  </a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Recent Appointments Section -->
    <div class="section">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-clock"></i>
          Recent Appointments
        </h2>
        <a href="appointments.php" class="btn-add">
          <i class="fas fa-eye"></i> View All
        </a>
      </div>

      <?php if (empty($recent)): ?>
        <div class="empty-state">
          <i class="far fa-calendar-times"></i>
          <h4>No Appointments Yet</h4>
          <p>Appointments from customers will appear here</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="appointments-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Salon</th>
                <th>Service</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent as $a): ?>
                <tr>
                  <td><strong>#<?= $a['appointment_id'] ?></strong></td>
                  <td><?= htmlspecialchars($a['salon_name']) ?></td>
                  <td><?= htmlspecialchars($a['service_name']) ?></td>
                  <td>
                    <i class="fas fa-user-circle me-1" style="color: var(--primary);"></i>
                    <?= htmlspecialchars($a['customer_name']) ?>
                  </td>
                  <td><?= date('M d, Y', strtotime($a['appointment_date'])) ?></td>
                  <td><?= date('h:i A', strtotime($a['appointment_time'])) ?></td>
                  <td>
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
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Auto-refresh for pending appointments
    const pendingCount = <?= $pending_appointments ?>;
    if (pendingCount > 0) {
      setInterval(() => {
        location.reload();
      }, 60000); // Refresh every minute if there are pending appointments
    }

    // Scroll animations
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity = '0';
          entry.target.style.transform = 'translateY(20px)';
          
          setTimeout(() => {
            entry.target.style.transition = 'all 0.5s ease';
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
          }, 100);
          
          observer.unobserve(entry.target);
        }
      });
    }, observerOptions);

    document.querySelectorAll('.salon-card').forEach(card => {
      observer.observe(card);
    });
  </script>
</body>
</html>