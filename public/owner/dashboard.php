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

$page_title = "Owner Dashboard - Salonora";
?>
 <!-- Dashboard CSS -->
  <link rel="stylesheet" href="/salonora/public/assets/css/dashboard.css">
   <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <!--footer-->
  <link rel="stylesheet" href="/salonora/public/assets/css/footer.css">
   

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

<!-- Page-specific Scripts -->
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

<?php include __DIR__ . '/../footer.php'; ?>