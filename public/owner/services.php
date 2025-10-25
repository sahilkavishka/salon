<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];
$salon_id = intval($_GET['salon_id'] ?? 0);

if (!$salon_id) {
    $_SESSION['flash_error'] = 'Salon ID missing.';
    header('Location: dashboard.php');
    exit;
}

// Verify salon belongs to the owner
$stmt = $pdo->prepare("SELECT id, name, address FROM salons WHERE id=? AND owner_id=?");
$stmt->execute([$salon_id, $owner_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$salon) {
    $_SESSION['flash_error'] = 'Unauthorized access or salon not found.';
    header('Location: dashboard.php');
    exit;
}

// Handle delete action
if (isset($_GET['delete'])) {
    $sid = intval($_GET['delete']);
    if ($sid > 0) {
        $del = $pdo->prepare("DELETE FROM services WHERE id=? AND salon_id=?");
        $del->execute([$sid, $salon_id]);
        $_SESSION['flash_success'] = 'Service deleted successfully.';
        header("Location: services.php?salon_id=$salon_id");
        exit;
    }
}

// Fetch services for this salon
$stmt = $pdo->prepare("SELECT * FROM services WHERE salon_id=? ORDER BY id DESC");
$stmt->execute([$salon_id]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_services = count($services);
$avg_price = $total_services > 0 ? array_sum(array_column($services, 'price')) / $total_services : 0;
$total_duration = array_sum(array_column($services, 'duration'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Services - <?= htmlspecialchars($salon['name']) ?> | Salonora</title>
  
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

    /* Page Header */
    .page-header {
      background: var(--gradient-primary);
      padding: 3rem 0;
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
    }

    .page-title {
      font-size: 2rem;
      font-weight: 800;
      color: white;
      margin-bottom: 0.5rem;
    }

    .page-subtitle {
      font-size: 1rem;
      color: rgba(255, 255, 255, 0.9);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .page-actions {
      display: flex;
      gap: 0.75rem;
      margin-top: 1.5rem;
      flex-wrap: wrap;
    }

    .btn-action {
      background: white;
      color: var(--primary);
      border: none;
      padding: 0.75rem 1.5rem;
      border-radius: 50px;
      font-weight: 600;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
    }

    .btn-action:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
      color: var(--primary);
    }

    .btn-action.primary {
      background: rgba(255, 255, 255, 0.2);
      color: white;
      border: 2px solid white;
    }

    .btn-action.primary:hover {
      background: white;
      color: var(--primary);
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
      border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-md);
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

    .stat-icon.services {
      background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
    }

    .stat-icon.price {
      background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    }

    .stat-icon.duration {
      background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    }

    .stat-info h3 {
      font-size: 1.8rem;
      font-weight: 800;
      margin: 0;
      color: var(--text-dark);
    }

    .stat-info p {
      margin: 0;
      color: var(--text-light);
      font-size: 0.9rem;
    }

    /* Alert Styling */
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

    .alert-info {
      background: linear-gradient(135deg, #3498db 0%, #74b9ff 100%);
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

    /* Services Section */
    .services-section {
      background: white;
      border-radius: 20px;
      padding: 2rem;
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

    .btn-add-service {
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

    .btn-add-service:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
      color: white;
    }

    /* Services Grid */
    .services-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.5rem;
    }

    .service-card {
      background: white;
      border: 2px solid #e9ecef;
      border-radius: 20px;
      padding: 2rem;
      transition: var(--transition);
      position: relative;
      overflow: hidden;
    }

    .service-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--gradient-primary);
      transform: scaleX(0);
      transition: var(--transition);
    }

    .service-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-lg);
      border-color: var(--primary);
    }

    .service-card:hover::before {
      transform: scaleX(1);
    }

    .service-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      margin-bottom: 1.5rem;
    }

    .service-icon {
      width: 60px;
      height: 60px;
      background: linear-gradient(135deg, rgba(233, 30, 99, 0.1) 0%, rgba(156, 39, 176, 0.1) 100%);
      border-radius: 15px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: var(--primary);
    }

    .service-id {
      background: rgba(233, 30, 99, 0.1);
      color: var(--primary);
      padding: 0.25rem 0.75rem;
      border-radius: 50px;
      font-size: 0.8rem;
      font-weight: 700;
    }

    .service-name {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 1rem;
    }

    .service-details {
      display: flex;
      gap: 1.5rem;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }

    .service-detail {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .service-detail i {
      color: var(--primary);
      font-size: 1rem;
    }

    .service-detail strong {
      color: var(--text-dark);
      font-weight: 600;
    }

    .service-actions {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.75rem;
      padding-top: 1rem;
      border-top: 1px solid #e9ecef;
    }

    .btn-service-action {
      padding: 0.75rem;
      border-radius: 12px;
      font-weight: 600;
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

    .btn-delete {
      background: white;
      color: #e74c3c;
      border-color: #e74c3c;
    }

    .btn-delete:hover {
      background: #e74c3c;
      color: white;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 4rem 2rem;
    }

    .empty-state i {
      font-size: 5rem;
      color: #dfe6e9;
      margin-bottom: 1.5rem;
    }

    .empty-state h4 {
      font-size: 1.5rem;
      color: var(--text-dark);
      margin-bottom: 0.75rem;
    }

    .empty-state p {
      color: var(--text-light);
      margin-bottom: 2rem;
    }

    /* Delete Modal */
    .modal-content {
      border: none;
      border-radius: 24px;
      box-shadow: var(--shadow-xl);
    }

    .modal-header {
      background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
      color: white;
      border-radius: 24px 24px 0 0;
      padding: 1.5rem 2rem;
      border: none;
    }

    .modal-title {
      font-weight: 700;
    }

    .btn-close {
      filter: brightness(0) invert(1);
    }

    .modal-body {
      padding: 2rem;
    }

    .modal-footer {
      border: none;
      padding: 1.5rem 2rem;
      gap: 1rem;
    }

    .btn-modal {
      padding: 0.9rem 2rem;
      border-radius: 50px;
      font-weight: 700;
      transition: var(--transition);
      border: none;
    }

    .btn-modal-cancel {
      background: #e9ecef;
      color: var(--text-dark);
    }

    .btn-modal-delete {
      background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
      color: white;
    }

    .btn-modal-delete:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
    }

    /* Responsive */
    @media (max-width: 768px) {
      .page-title {
        font-size: 1.5rem;
      }

      .stats-bar {
        grid-template-columns: 1fr;
      }

      .services-grid {
        grid-template-columns: 1fr;
      }

      .page-actions {
        width: 100%;
      }

      .btn-action {
        flex: 1;
        justify-content: center;
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

    .service-card {
      animation: fadeIn 0.5s ease forwards;
      opacity: 0;
    }

    .service-card:nth-child(1) { animation-delay: 0.1s; }
    .service-card:nth-child(2) { animation-delay: 0.2s; }
    .service-card:nth-child(3) { animation-delay: 0.3s; }
    .service-card:nth-child(4) { animation-delay: 0.4s; }
    .service-card:nth-child(5) { animation-delay: 0.5s; }
    .service-card:nth-child(6) { animation-delay: 0.6s; }
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

  <!-- Page Header -->
  <div class="page-header">
    <div class="container">
      <div class="page-header-content">
        <h1 class="page-title">Services for <?= htmlspecialchars($salon['name']) ?></h1>
        <p class="page-subtitle">
          <i class="fas fa-map-marker-alt"></i>
          <?= htmlspecialchars($salon['address']) ?>
        </p>
        <div class="page-actions">
          <a href="dashboard.php" class="btn-action">
            <i class="fas fa-arrow-left"></i> Dashboard
          </a>
          <a href="service_add.php?salon_id=<?= $salon_id ?>" class="btn-action primary">
            <i class="fas fa-plus"></i> Add Service
          </a>
          <a href="../logout.php" class="btn-action">
            <i class="fas fa-sign-out-alt"></i> Logout
          </a>
        </div>
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
        <div class="stat-icon price">
          <i class="fas fa-tag"></i>
        </div>
        <div class="stat-info">
          <h3>Rs <?= number_format($avg_price, 0) ?></h3>
          <p>Average Price</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon duration">
          <i class="far fa-clock"></i>
        </div>
        <div class="stat-info">
          <h3><?= $total_duration ?> min</h3>
          <p>Total Duration</p>
        </div>
      </div>
    </div>

    <!-- Services Section -->
    <div class="services-section">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-list"></i>
          All Services
        </h2>
        <a href="service_add.php?salon_id=<?= $salon_id ?>" class="btn-add-service">
          <i class="fas fa-plus"></i> Add New Service
        </a>
      </div>

      <?php if (empty($services)): ?>
        <div class="empty-state">
          <i class="fas fa-spa"></i>
          <h4>No Services Yet</h4>
          <p>Start by adding your first service to attract customers</p>
          <a href="service_add.php?salon_id=<?= $salon_id ?>" class="btn-add-service">
            <i class="fas fa-plus"></i> Add Your First Service
          </a>
        </div>
      <?php else: ?>
        <div class="services-grid">
          <?php foreach ($services as $s): ?>
            <div class="service-card">
              <div class="service-header">
                <div class="service-icon">
                  <i class="fas fa-scissors"></i>
                </div>
                <span class="service-id">#<?= $s['id'] ?></span>
              </div>
              <h3 class="service-name"><?= htmlspecialchars($s['name']) ?></h3>
              <div class="service-details">
                <div class="service-detail">
                  <i class="fas fa-tag"></i>
                  <span>Rs <strong><?= number_format($s['price'], 2) ?></strong></span>
                </div>
                <div class="service-detail">
                  <i class="far fa-clock"></i>
                  <span><strong><?= $s['duration'] ?></strong> mins</span>
                </div>
              </div>
              <div class="service-actions">
                <a href="service_edit.php?id=<?= $s['id'] ?>&salon_id=<?= $salon_id ?>" class="btn-service-action btn-edit">
                  <i class="fas fa-edit"></i> Edit
                </a>
                <button class="btn-service-action btn-delete" 
                        onclick="confirmDelete(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>')">
                  <i class="fas fa-trash"></i> Delete
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">Are you sure you want to delete <strong id="serviceName"></strong>? This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-modal btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
          <a href="#" id="confirmDeleteBtn" class="btn-modal btn-modal-delete">
            <i class="fas fa-trash me-2"></i>Delete Service
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function confirmDelete(serviceId, serviceName) {
      document.getElementById('serviceName').textContent = serviceName;
      document.getElementById('confirmDeleteBtn').href = 
        `services.php?salon_id=<?= $salon_id ?>&delete=${serviceId}`;
      
      const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
      modal.show();
    }

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
      });
    }, 5000);
  </script>
</body>
</html>