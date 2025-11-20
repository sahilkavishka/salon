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
  <link rel="stylesheet" href="../assets/css/services.css">
  
 
</head>
<body>
  

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