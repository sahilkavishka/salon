<?php
// public/user/salon_view.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

// Handle review submission
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['salon_id'], $_POST['rating'], $_POST['comment'])
) {
    $salon_id = intval($_POST['salon_id']);
    $user_id = $_SESSION['id'] ?? 0;
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    $errors = [];

    if ($rating < 1 || $rating > 5) $errors[] = "Please select a valid rating.";
    if ($comment === '') $errors[] = "Review cannot be empty.";

    // Optional: Prevent duplicate reviews per user per salon
    // $checkStmt = $pdo->prepare("SELECT id FROM reviews WHERE salon_id=? AND user_id=?");
    // $checkStmt->execute([$salon_id, $user_id]);
    // if ($checkStmt->fetch()) $errors[] = "You have already reviewed this salon.";

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO reviews (salon_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$salon_id, $user_id, $rating, $comment]);
        $_SESSION['success_message'] = "Review submitted successfully!";
    } else {
        $_SESSION['error_message'] = implode(" ", $errors);
    }
    header('Location: salon_view.php');
    exit;
}

// Fetch all salons with service count
$stmt = $pdo->query("
    SELECT 
        s.id AS salon_id,
        s.name,
        s.address,
        s.image,
        s.owner_id,
        u.username AS owner_name,
        COUNT(sr.id) AS service_count
    FROM salons s
    LEFT JOIN users u ON s.owner_id = u.id
    LEFT JOIN services sr ON s.id = sr.salon_id
    GROUP BY s.id
    ORDER BY s.name ASC
");
$salons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine logged-in user
$logged_user_id = $_SESSION['id'] ?? 0;
$logged_user_role = $_SESSION['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>All Salons - Salonora</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/salon_view.css">
</head>
<body>
  <nav class="navbar navbar-expand-lg">
    <div class="container">
      <a class="navbar-brand" href="../../index.php">
        <i class="fas fa-spa"></i> Salonora
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav align-items-center">
          <li class="nav-item"><a href="/salonora/public/index.php" class="nav-link">Home</a></li>
          <li class="nav-item"><a class="nav-link active" href="salon_view.php">Salons</a></li>
          <li class="nav-item"><a class="nav-link" href="my_appointments.php">Appointments</a></li>
          <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
          <?php if (isset($_SESSION['id'])): ?>
            <li class="nav-item ms-3">
              <a href="../logout.php" class="btn btn-gradient btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i> Logout
              </a>
            </li>
          <?php else: ?>
            <li class="nav-item ms-3">
              <a href="../login.php" class="btn btn-gradient btn-sm">
                <i class="fas fa-sign-in-alt me-1"></i> Login
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

  <div class="page-header">
    <div class="container">
      <div class="page-header-content">
        <h1 class="page-title">Discover Amazing Salons</h1>
        <p class="page-subtitle">Browse through our curated collection of premium beauty destinations</p>
      </div>
    </div>
  </div>

  <div class="container pb-5">
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

    <div class="stats-bar">
      <div class="stat-item">
        <span class="stat-number"><?= count($salons) ?></span>
        <span class="stat-label">Total Salons</span>
      </div>
      <div class="stat-item">
        <span class="stat-number">4.8</span>
        <span class="stat-label">Average Rating</span>
      </div>
      <div class="stat-item">
        <span class="stat-number">10k+</span>
        <span class="stat-label">Happy Clients</span>
      </div>
    </div>

    <div class="filter-bar">
      <div class="row align-items-center">
        <div class="col-md-8 mb-3 mb-md-0">
          <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search salons by name, location, or owner..." class="form-control">
          </div>
        </div>
        <div class="col-md-4">
          <select id="sortSelect" class="form-select">
            <option value="name">Sort by Name</option>
            <option value="services">Sort by Services</option>
            <option value="location">Sort by Location</option>
          </select>
        </div>
      </div>
    </div>

    <?php if (empty($salons)): ?>
      <div class="empty-state">
        <i class="fas fa-store-slash"></i>
        <h4>No Salons Available</h4>
        <p>Check back later for new salon listings</p>
      </div>
    <?php else: ?>
      <div class="row g-4" id="salonsGrid">
        <?php foreach ($salons as $salon):
          $can_interact = in_array($logged_user_role, ['user', 'customer']) && $logged_user_id != $salon['owner_id'];
          $image_path = '../../' . ($salon['image'] ?: 'assets/img/default_salon.jpg');
        ?>
          <div class="col-lg-4 col-md-6 salon-item"
               data-name="<?= strtolower(htmlspecialchars($salon['name'])) ?>"
               data-location="<?= strtolower(htmlspecialchars($salon['address'])) ?>"
               data-owner="<?= strtolower(htmlspecialchars($salon['owner_name'])) ?>"
               data-services="<?= $salon['service_count'] ?>">
            <div class="salon-card">
              <div class="salon-img-wrapper">
                <img src="<?= htmlspecialchars($image_path) ?>" class="salon-img" alt="<?= htmlspecialchars($salon['name']) ?>">
                <div class="salon-badge">
                  <i class="fas fa-concierge-bell me-1"></i>
                  <?= $salon['service_count'] ?> Services
                </div>
              </div>
              <div class="salon-card-body">
                <h5 class="salon-title"><?= htmlspecialchars($salon['name']) ?></h5>
                <div class="salon-info">
                  <div class="info-row">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?= htmlspecialchars($salon['address']) ?></span>
                  </div>
                  <div class="info-row">
                    <i class="fas fa-user-tie"></i>
                    <span>Owner: <strong><?= htmlspecialchars($salon['owner_name']) ?></strong></span>
                  </div>
                  <div class="info-row">
                    <i class="fas fa-star"></i>
                    <span>Rating: <strong>4.8/5</strong></span>
                  </div>
                </div>
                <div class="salon-actions">
                  <a href="salon_details.php?id=<?= $salon['salon_id'] ?>" class="btn btn-view">
                    <i class="fas fa-eye"></i> View Details
                  </a>
                  <a href="add_appointment.php?salon_id=<?= $salon['salon_id'] ?>" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i> Add Appointment
                  </a>
                  <button class="btn btn-review"
                          data-bs-toggle="modal"
                          data-bs-target="#reviewModal"
                          data-salon="<?= $salon['salon_id'] ?>"
                          data-name="<?= htmlspecialchars($salon['name']) ?>">
                    <i class="fas fa-star"></i> Write Review
                  </button>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Review Modal -->
  <div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <form method="post" action="salon_view.php" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fas fa-star me-2"></i>Write a Review
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="salon_id" id="reviewSalonId">
          <div class="mb-3">
            <label class="form-label">Salon</label>
            <input type="text" id="reviewSalonName" class="form-control" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Rating</label>
            <select name="rating" class="form-select" required>
              <option value="">Select Rating</option>
              <option value="5">★★★★★ Excellent</option>
              <option value="4">★★★★☆ Very Good</option>
              <option value="3">★★★☆☆ Good</option>
              <option value="2">★★☆☆☆ Fair</option>
              <option value="1">★☆☆☆☆ Poor</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Your Review</label>
            <textarea name="comment" class="form-control" rows="4" placeholder="Share your experience..." required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-modal-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-modal-primary">
            <i class="fas fa-paper-plane me-2"></i>Submit Review
          </button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const salonItems = document.querySelectorAll('.salon-item');
    searchInput.addEventListener('input', function() {
      const searchTerm = this.value.toLowerCase();
      salonItems.forEach(item => {
        const name = item.dataset.name;
        const location = item.dataset.location;
        const owner = item.dataset.owner;
        if (name.includes(searchTerm) || location.includes(searchTerm) || owner.includes(searchTerm)) {
          item.style.display = 'block';
        } else {
          item.style.display = 'none';
        }
      });
    });

    // Sort functionality
    const sortSelect = document.getElementById('sortSelect');
    const salonsGrid = document.getElementById('salonsGrid');
    sortSelect.addEventListener('change', function() {
      const sortBy = this.value;
      const items = Array.from(salonItems);
      items.sort((a, b) => {
        if (sortBy === 'name') {
          return a.dataset.name.localeCompare(b.dataset.name);
        } else if (sortBy === 'services') {
          return parseInt(b.dataset.services) - parseInt(a.dataset.services);
        } else if (sortBy === 'location') {
          return a.dataset.location.localeCompare(b.dataset.location);
        }
      });
      items.forEach(item => salonsGrid.appendChild(item));
    });

    // Review Modal
    const reviewModal = document.getElementById('reviewModal');
    reviewModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      const salonId = button.getAttribute('data-salon');
      const salonName = button.getAttribute('data-name');
      document.getElementById('reviewSalonId').value = salonId;
      document.getElementById('reviewSalonName').value = salonName;
    });
  </script>
</body>
</html>
