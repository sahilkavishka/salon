<?php
// public/user/salon_view.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle review submission
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['salon_id'], $_POST['rating'], $_POST['comment'])
) {
    // CSRF verification
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Invalid security token. Please refresh and try again.";
        header('Location: salon_view.php');
        exit;
    }

    $salon_id = intval($_POST['salon_id']);
    $user_id = $_SESSION['id'] ?? 0;
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    $errors = [];

    // Validation
    if ($rating < 1 || $rating > 5) $errors[] = "Please select a valid rating between 1 and 5 stars.";
    if ($comment === '') $errors[] = "Review message cannot be empty.";
    if (strlen($comment) < 10) $errors[] = "Review must be at least 10 characters.";
    if (strlen($comment) > 1000) $errors[] = "Review must not exceed 1000 characters.";

    // Prevent duplicate reviews per user per salon
    $checkStmt = $pdo->prepare("SELECT id FROM reviews WHERE salon_id=? AND user_id=?");
    $checkStmt->execute([$salon_id, $user_id]);
    if ($checkStmt->fetch()) $errors[] = "You have already reviewed this salon.";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO reviews (salon_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$salon_id, $user_id, $rating, $comment]);

            // Recalculate average rating
            $avgStmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE salon_id = ?");
            $avgStmt->execute([$salon_id]);
            $avgData = $avgStmt->fetch(PDO::FETCH_ASSOC);
            $newRating = round($avgData['avg_rating'] ?? 0, 2);
            $newTotal = intval($avgData['total_reviews'] ?? 0);

            $updateSalon = $pdo->prepare("UPDATE salons SET rating = ?, total_reviews = ? WHERE id = ?");
            $updateSalon->execute([$newRating, $newTotal, $salon_id]);

            $_SESSION['success_message'] = "Your review has been published successfully!";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Failed to submit review. Please try again.";
        }
    } else {
        $_SESSION['error_message'] = implode(" ", $errors);
    }
    header('Location: salon_view.php');
    exit;
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 9;
$offset = ($page - 1) * $per_page;

// Fetch total count
$countStmt = $pdo->query("SELECT COUNT(*) FROM salons");
$total_salons = (int)$countStmt->fetchColumn();
$total_pages = ceil($total_salons / $per_page);

// Fetch salons with pagination & aggregated service/review stats
$stmt = $pdo->prepare("
    SELECT 
        s.id AS salon_id,
        s.name,
        s.address,
        s.image,
        s.owner_id,
        s.lat,
        s.lng,
        u.username AS owner_name,
        COUNT(DISTINCT sr.id) AS service_count,
        COALESCE(AVG(r.rating), 0) AS avg_rating,
        COUNT(DISTINCT r.id) AS review_count
    FROM salons s
    LEFT JOIN users u ON s.owner_id = u.id
    LEFT JOIN services sr ON s.id = sr.salon_id
    LEFT JOIN reviews r ON s.id = r.salon_id
    GROUP BY s.id, s.name, s.address, s.image, s.owner_id, s.lat, s.lng, u.username
    ORDER BY s.name ASC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$salons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate overall stats
$statsStmt = $pdo->query("
    SELECT 
        COUNT(DISTINCT s.id) as total_salons,
        COALESCE(AVG(r.rating), 0) as avg_rating,
        COUNT(DISTINCT sr.id) as total_services
    FROM salons s
    LEFT JOIN reviews r ON s.id = r.salon_id
    LEFT JOIN services sr ON s.id = sr.salon_id
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Determine user context
$logged_user_id = $_SESSION['id'] ?? 0;
$logged_user_role = strtolower($_SESSION['role'] ?? '');

$page_title = 'Explore Salons | Salonora';
include __DIR__ . '/../header.php';
?>

<style>
/* ========================================================
   SALONORA EXPLORE SALONS - LUXURY DIRECTORY STYLES
   ======================================================== */

.salons-hero {
  background: linear-gradient(135deg, #180928 0%, #2f083b 45%, #620f54 85%, #881337 100%);
  color: #ffffff;
  border-radius: 28px;
  padding: 3rem 2.25rem;
  margin-bottom: 2rem;
  position: relative;
  overflow: hidden;
  box-shadow: 0 15px 35px -10px rgba(98, 15, 84, 0.45);
}

.salons-hero::before {
  content: '';
  position: absolute;
  top: -50px;
  right: -50px;
  width: 250px;
  height: 250px;
  background: radial-gradient(circle, rgba(236, 72, 153, 0.35) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
}

/* Quick Summary Stat Chips */
.salon-stat-chip {
  background: rgba(255, 255, 255, 0.12);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 16px;
  padding: 0.6rem 1.15rem;
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
  color: #ffffff;
  font-size: 0.9rem;
}

.salon-stat-chip .stat-number {
  font-size: 1.25rem;
  font-weight: 800;
  color: #fbcfe8;
}

/* Filter Studio Bar */
.salon-filter-card {
  background: #ffffff;
  border-radius: 22px;
  border: 1.5px solid #e2e8f0;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
  padding: 1.5rem;
  margin-bottom: 2rem;
}

.search-input-wrap {
  position: relative;
}

.search-input-wrap i {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
}

.search-input-wrap input {
  padding-left: 2.6rem;
  border-radius: 50px;
  border: 1.5px solid #cbd5e1;
  font-size: 0.92rem;
}

/* Quick Filter Chips Bar */
.filter-chips-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px dashed #e2e8f0;
}

.salon-chip {
  background: #f8fafc;
  color: #1e293b;
  border: 1.5px solid #cbd5e1;
  border-radius: 50px;
  padding: 0.45rem 1.1rem;
  font-size: 0.84rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.22s ease;
  user-select: none;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}

.salon-chip:hover {
  background: #fdf2f8;
  color: #db2777;
  border-color: #fbcfe8;
}

.salon-chip.active {
  background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%) !important;
  color: #ffffff !important;
  border-color: transparent !important;
  box-shadow: 0 4px 12px rgba(233, 30, 99, 0.3);
}

/* View Switcher */
.view-toggle-wrap {
  display: flex;
  gap: 0.35rem;
  background: #f1f5f9;
  padding: 0.3rem;
  border-radius: 50px;
  border: 1px solid #e2e8f0;
}

.view-toggle-btn {
  border: none;
  background: transparent;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  color: #64748b;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  transition: all 0.2s ease;
  cursor: pointer;
}

.view-toggle-btn.active {
  background: #ffffff;
  color: #e91e63;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Salon Card */
.salon-card-box {
  background: #ffffff;
  border-radius: 24px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.04);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  height: 100%;
  display: flex;
  flex-direction: column;
}

.salon-card-box:hover {
  transform: translateY(-4px);
  box-shadow: 0 14px 35px rgba(233, 30, 99, 0.12);
  border-color: #fbcfe8;
}

.salon-card-thumb-wrap {
  position: relative;
  width: 100%;
  height: 210px;
  background: #1a1a2e;
  overflow: hidden;
}

.salon-card-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.4s ease;
}

.salon-card-box:hover .salon-card-img {
  transform: scale(1.05);
}

.salon-services-pill {
  position: absolute;
  top: 14px;
  left: 14px;
  background: rgba(18, 8, 38, 0.75);
  backdrop-filter: blur(8px);
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.25);
  padding: 0.35rem 0.85rem;
  border-radius: 50px;
  font-size: 0.78rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.salon-card-body {
  padding: 1.5rem;
  flex-grow: 1;
  display: flex;
  flex-direction: column;
}

.salon-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 0.5rem;
  line-height: 1.3;
}

.salon-info-row {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  font-size: 0.88rem;
  color: #475569;
  margin-bottom: 0.5rem;
}

.salon-info-row i {
  color: #e91e63;
  margin-top: 3px;
  font-size: 0.95rem;
  width: 16px;
  flex-shrink: 0;
}

.rating-stars {
  color: #f59e0b;
  font-size: 0.9rem;
  letter-spacing: 1px;
}

/* Card Button Actions */
.salon-card-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: auto;
  padding-top: 1.25rem;
  border-top: 1px solid #f1f5f9;
}

/* List View Variant */
#salonsGrid.list-view .salon-card-box {
  flex-direction: row;
}

#salonsGrid.list-view .salon-card-thumb-wrap {
  width: 320px;
  height: auto;
  flex-shrink: 0;
}

@media (max-width: 768px) {
  #salonsGrid.list-view .salon-card-box {
    flex-direction: column;
  }
  #salonsGrid.list-view .salon-card-thumb-wrap {
    width: 100%;
    height: 200px;
  }
}

/* ========================================================
   DARK MODE ADAPTATIONS
   ======================================================== */
body.dark-mode .salons-hero {
  background: linear-gradient(135deg, #11051c 0%, #1c0524 45%, #3d0534 85%, #4c051e 100%) !important;
  box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.6) !important;
}

body.dark-mode .salon-filter-card {
  background: #1a1a2e !important;
  border-color: #2d2d48 !important;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4) !important;
}

body.dark-mode .search-input-wrap input {
  background: #1e1e38 !important;
  border-color: #2d2d48 !important;
  color: #f8fafc !important;
}

body.dark-mode .salon-chip {
  background: #1e1e38 !important;
  color: #f8fafc !important;
  border-color: #3b3b5e !important;
}

body.dark-mode .salon-chip:hover {
  background: #2a2a4c !important;
  color: #f472b6 !important;
}

body.dark-mode .salon-chip.active {
  background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%) !important;
  color: #ffffff !important;
  border-color: transparent !important;
}

body.dark-mode .view-toggle-wrap {
  background: #1e1e38 !important;
  border-color: #2d2d48 !important;
}

body.dark-mode .view-toggle-btn.active {
  background: #2a2a4c !important;
  color: #f472b6 !important;
}

body.dark-mode .salon-card-box {
  background: #1a1a2e !important;
  border-color: #2d2d48 !important;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4) !important;
}

body.dark-mode .salon-title {
  color: #f8fafc !important;
}

body.dark-mode .salon-info-row {
  color: #cbd5e1 !important;
}

body.dark-mode .salon-card-actions {
  border-top-color: #2d2d48 !important;
}
</style>

<div class="container py-4 pb-5">

  <!-- ==========================================
       HERO BANNER & PLATFORM STATS
       ========================================== -->
  <div class="salons-hero">
    <div class="row align-items-center">
      <div class="col-lg-8">
        <span class="badge bg-white bg-opacity-20 text-white px-3 py-2 rounded-pill fw-bold mb-3 shadow-sm">
          <i class="fas fa-gem me-1 text-warning"></i> Certified Beauty Directory
        </span>
        <h1 class="display-6 fw-bold mb-2">Explore Luxury & Boutique Salons</h1>
        <p class="lead mb-4 text-white-50" style="font-size: 1.05rem; max-width: 650px;">
          Find verified hair stylists, beauty salons, aesthetic spas, and nail bars across Sri Lanka. Browse menus, inspect verified ratings, and book your appointments online.
        </p>

        <!-- Stats row -->
        <div class="d-flex flex-wrap gap-2">
          <div class="salon-stat-chip">
            <i class="fas fa-store text-info"></i>
            <div>
              <span class="stat-number"><?= $stats['total_salons'] ?></span>
              <span class="d-block small text-white-50">Verified Salons</span>
            </div>
          </div>

          <div class="salon-stat-chip">
            <i class="fas fa-star text-warning"></i>
            <div>
              <span class="stat-number"><?= number_format($stats['avg_rating'], 1) ?> ★</span>
              <span class="d-block small text-white-50">Average Rating</span>
            </div>
          </div>

          <div class="salon-stat-chip">
            <i class="fas fa-cut text-success"></i>
            <div>
              <span class="stat-number"><?= $stats['total_services'] ?>+</span>
              <span class="d-block small text-white-50">Bookable Services</span>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
        <a href="ai_advisor.php" class="btn btn-light btn-lg rounded-pill px-4 py-3 fw-bold text-dark shadow-lg">
          <i class="fas fa-magic text-warning me-2"></i> AI Style Advisor
        </a>
      </div>
    </div>
  </div>

  <!-- Global Flash Messages -->
  <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm d-flex align-items-center mb-4" role="alert">
      <i class="fas fa-check-circle me-3 fs-5"></i>
      <div><?= htmlspecialchars($_SESSION['success_message']) ?></div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm d-flex align-items-center mb-4" role="alert">
      <i class="fas fa-exclamation-triangle me-3 fs-5"></i>
      <div><?= htmlspecialchars($_SESSION['error_message']) ?></div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
  <?php endif; ?>

  <!-- ==========================================
       SEARCH, SORT & FILTERS CARD
       ========================================== -->
  <div class="salon-filter-card">
    <div class="row g-3 align-items-center">
      
      <!-- Live Search -->
      <div class="col-lg-6 col-md-6">
        <div class="search-input-wrap">
          <i class="fas fa-search"></i>
          <input type="text" id="searchInput" placeholder="Search salons by name, address, or owner..." class="form-control" aria-label="Search salons">
        </div>
      </div>

      <!-- Sort Dropdown -->
      <div class="col-lg-4 col-md-4">
        <select id="sortSelect" class="form-select rounded-pill px-3 py-2" aria-label="Sort salons">
          <option value="name">Sort by: Name (A-Z)</option>
          <option value="distance">📍 Distance: Nearest First (GPS)</option>
          <option value="rating">Sort by: Highest Rated</option>
          <option value="services">Sort by: Most Services</option>
          <option value="location">Sort by: Location</option>
        </select>
      </div>

      <!-- Grid / List Switcher -->
      <div class="col-lg-2 col-md-2 text-md-end">
        <div class="view-toggle-wrap d-inline-flex">
          <button type="button" class="view-toggle-btn active" data-view="grid" title="Grid View">
            <i class="fas fa-th-large"></i>
          </button>
          <button type="button" class="view-toggle-btn" data-view="list" title="List View">
            <i class="fas fa-list"></i>
          </button>
        </div>
      </div>

    </div>

    <!-- Quick Filter Chips -->
    <div class="filter-chips-bar">
      <span class="salon-chip active" data-filter="all">
        <i class="fas fa-globe"></i> All Salons
      </span>
      <span class="salon-chip" id="chipNearMe" data-filter="near-me" onclick="activateNearMe()">
        <i class="fas fa-location-arrow text-primary"></i> Near Me (GPS)
      </span>
      <span class="salon-chip" data-filter="top-rated">
        <i class="fas fa-star text-warning"></i> Top Rated (4+ ★)
      </span>
      <span class="salon-chip" data-filter="most-services">
        <i class="fas fa-concierge-bell text-info"></i> Most Services (3+)
      </span>
      <span class="salon-chip" data-filter="most-reviewed">
        <i class="fas fa-comments text-success"></i> Most Reviewed
      </span>
    </div>
  </div>

  <!-- ==========================================
       SALONS GRID
       ========================================== -->
  <?php if (empty($salons)): ?>
    <div class="text-center py-5 bg-white rounded-4 border p-4 shadow-sm">
      <i class="fas fa-store-slash display-4 text-muted mb-3 opacity-50"></i>
      <h4 class="fw-bold">No Salons Listed Yet</h4>
      <p class="text-muted small">Check back soon as new verified beauty studios join Salonora.</p>
    </div>
  <?php else: ?>
    <div class="row g-4" id="salonsGrid">
      <?php foreach ($salons as $salon):
        $is_owner_of_this_salon = ($logged_user_role === 'owner' && $logged_user_id == $salon['owner_id']);
        $can_book = ($logged_user_role !== 'owner');

        // Robust image resolution
        $img = !empty($salon['image']) ? $salon['image'] : 'assets/images/salon_default.jpg';
        $full_img_path = url($img);

        $avg_rating = round($salon['avg_rating'], 1);
        $stars_full = floor($salon['avg_rating']);
        $stars_half = ($salon['avg_rating'] - $stars_full) >= 0.5 ? 1 : 0;
        $stars_empty = max(0, 5 - $stars_full - $stars_half);
      ?>
        <div class="col-lg-4 col-md-6 salon-item"
             data-name="<?= strtolower(htmlspecialchars($salon['name'])) ?>"
             data-location="<?= strtolower(htmlspecialchars($salon['address'])) ?>"
             data-owner="<?= strtolower(htmlspecialchars($salon['owner_name'] ?? '')) ?>"
             data-services="<?= $salon['service_count'] ?>"
             data-rating="<?= $salon['avg_rating'] ?>"
             data-reviews="<?= $salon['review_count'] ?>"
             data-lat="<?= htmlspecialchars($salon['lat'] ?? '') ?>"
             data-lng="<?= htmlspecialchars($salon['lng'] ?? '') ?>">
          
          <div class="salon-card-box">
            
            <!-- Thumbnail Image & Badges -->
            <div class="salon-card-thumb-wrap">
              <img src="<?= htmlspecialchars($full_img_path) ?>" class="salon-card-img" alt="<?= htmlspecialchars($salon['name']) ?>" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=600&q=80'">
              
              <div class="salon-services-pill">
                <i class="fas fa-concierge-bell me-1"></i>
                <?= $salon['service_count'] ?> Services
              </div>

              <div class="distance-badge-container position-absolute top-0 end-0 m-2"></div>
            </div>

            <!-- Card Details -->
            <div class="salon-card-body">
              <h5 class="salon-title"><?= htmlspecialchars($salon['name']) ?></h5>

              <div class="salon-info-row">
                <i class="fas fa-map-marker-alt"></i>
                <span><?= htmlspecialchars($salon['address']) ?></span>
              </div>

              <div class="salon-info-row">
                <i class="fas fa-user-check"></i>
                <span>Owner: <strong><?= htmlspecialchars(!empty($salon['owner_name']) ? $salon['owner_name'] : 'Verified Stylist') ?></strong></span>
              </div>

              <div class="salon-info-row align-items-center">
                <i class="fas fa-star text-warning"></i>
                <div class="d-flex align-items-center gap-2">
                  <span class="rating-stars">
                    <?php for($i = 0; $i < $stars_full; $i++) echo '★'; ?>
                    <?php if($stars_half) echo '⯨'; ?>
                    <?php for($i = 0; $i < $stars_empty; $i++) echo '☆'; ?>
                  </span>
                  <span class="fw-bold text-dark small"><?= number_format($avg_rating, 1) ?></span>
                  <span class="text-muted small">(<?= $salon['review_count'] ?> reviews)</span>
                </div>
              </div>

              <!-- Action Buttons -->
              <div class="salon-card-actions">
                <a href="salon_details.php?id=<?= $salon['salon_id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-2 fw-semibold">
                  <i class="fas fa-eye me-1"></i> Details
                </a>

                <?php if ($can_book): ?>
                  <a href="book_appointment.php?salon_id=<?= $salon['salon_id']; ?>" class="btn btn-sm btn-gradient rounded-pill px-3 py-2 fw-semibold">
                    <i class="fas fa-calendar-check me-1"></i> Book Now
                  </a>
                  <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3 py-2 fw-semibold"
                          data-bs-toggle="modal"
                          data-bs-target="#reviewModal"
                          data-salon="<?= $salon['salon_id'] ?>"
                          data-name="<?= htmlspecialchars($salon['name']) ?>"
                          onclick="openReviewModal(this)">
                    <i class="fas fa-star me-1"></i> Review
                  </button>
                <?php elseif ($is_owner_of_this_salon): ?>
                  <a href="../owner/dashboard.php" class="btn btn-sm btn-primary rounded-pill px-3 py-2 fw-semibold">
                    <i class="fas fa-user-shield me-1"></i> My Dashboard
                  </a>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-2 small align-self-center">
                    Owner Mode
                  </span>
                <?php endif; ?>
              </div>

            </div>

          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
      <div class="d-flex justify-content-center mt-5">
        <ul class="pagination pagination-lg">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link rounded-circle mx-1" href="?page=<?= $page - 1 ?>" aria-label="Previous">
              <i class="fas fa-chevron-left"></i>
            </a>
          </li>
          
          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
              <a class="page-link rounded-circle mx-1" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>

          <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
            <a class="page-link rounded-circle mx-1" href="?page=<?= $page + 1 ?>" aria-label="Next">
              <i class="fas fa-chevron-right"></i>
            </a>
          </li>
        </ul>
      </div>
    <?php endif; ?>

  <?php endif; ?>

</div>

<!-- ==========================================
     WRITE REVIEW MODAL
     ========================================== -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="salon_view.php" class="modal-content rounded-4 border-0 shadow-lg p-3" id="reviewForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="salon_id" id="reviewSalonId">

      <div class="modal-header border-0 pb-0">
        <div>
          <h5 class="modal-title fw-bold text-dark">
            <i class="fas fa-star text-warning me-2"></i>Write a Salon Review
          </h5>
          <p class="text-muted small mb-0" id="reviewSalonName">Salon Name</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body py-3">
        <div class="mb-3">
          <label class="form-label fw-semibold small">Star Rating</label>
          <select name="rating" class="form-select rounded-3 py-2" required>
            <option value="">Select your rating</option>
            <option value="5">★★★★★ 5 Stars (Excellent Experience)</option>
            <option value="4">★★★★☆ 4 Stars (Very Good)</option>
            <option value="3">★★★☆☆ 3 Stars (Good / Average)</option>
            <option value="2">★★☆☆☆ 2 Stars (Fair)</option>
            <option value="1">★☆☆☆☆ 1 Star (Needs Improvement)</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold small">Your Honest Feedback</label>
          <textarea name="comment" id="reviewComment" class="form-control rounded-3" rows="4" placeholder="Share your experience with services, atmosphere, cleanliness, and staff (min. 10 characters)..." required minlength="10" maxlength="1000" oninput="updateCharCount(this)"></textarea>
          <div class="d-flex justify-content-between text-muted small mt-1">
            <span>Minimum 10 characters</span>
            <span id="charCount">0 / 1000</span>
          </div>
        </div>
      </div>

      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-gradient rounded-pill px-4 fw-bold">
          <i class="fas fa-paper-plane me-1"></i> Submit Review
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Search & Filter
const searchInput = document.getElementById('searchInput');
const sortSelect = document.getElementById('sortSelect');
const salonItems = document.querySelectorAll('.salon-item');
const salonsGrid = document.getElementById('salonsGrid');
let activeFilter = 'all';
let userLocation = null;

if (searchInput) {
  searchInput.addEventListener('input', filterAndSortSalons);
}

if (sortSelect) {
  sortSelect.addEventListener('change', function() {
    if (this.value === 'distance' && !userLocation) {
      activateNearMe();
    } else {
      filterAndSortSalons();
    }
  });
}

// Quick filter chips
document.querySelectorAll('.salon-chip').forEach(chip => {
  chip.addEventListener('click', function() {
    if (this.dataset.filter === 'near-me') return; // Handled by activateNearMe
    document.querySelectorAll('.salon-chip').forEach(c => c.classList.remove('active'));
    this.classList.add('active');
    activeFilter = this.dataset.filter;
    filterAndSortSalons();
  });
});

function filterAndSortSalons() {
  const q = searchInput ? searchInput.value.trim().toLowerCase() : '';
  const sortBy = sortSelect ? sortSelect.value : 'name';

  salonItems.forEach(item => {
    const name = item.dataset.name || '';
    const location = item.dataset.location || '';
    const owner = item.dataset.owner || '';
    const rating = parseFloat(item.dataset.rating || 0);
    const services = parseInt(item.dataset.services || 0);
    const reviews = parseInt(item.dataset.reviews || 0);

    let matchesSearch = !q || name.includes(q) || location.includes(q) || owner.includes(q);
    let matchesChip = true;

    if (activeFilter === 'top-rated') matchesChip = (rating >= 4.0);
    if (activeFilter === 'most-services') matchesChip = (services >= 3);
    if (activeFilter === 'most-reviewed') matchesChip = (reviews >= 1);

    if (matchesSearch && matchesChip) {
      item.style.display = '';
    } else {
      item.style.display = 'none';
    }
  });

  // Sort
  const itemsArr = Array.from(salonItems);
  itemsArr.sort((a, b) => {
    if (sortBy === 'distance') {
      return parseFloat(a.dataset.distance || 99999) - parseFloat(b.dataset.distance || 99999);
    } else if (sortBy === 'name') {
      return (a.dataset.name || '').localeCompare(b.dataset.name || '');
    } else if (sortBy === 'rating') {
      return parseFloat(b.dataset.rating || 0) - parseFloat(a.dataset.rating || 0);
    } else if (sortBy === 'services') {
      return parseInt(b.dataset.services || 0) - parseInt(a.dataset.services || 0);
    } else if (sortBy === 'location') {
      return (a.dataset.location || '').localeCompare(b.dataset.location || '');
    }
    return 0;
  });

  if (salonsGrid) {
    itemsArr.forEach(item => salonsGrid.appendChild(item));
  }
}

// Geolocation
function getDistanceKm(lat1, lon1, lat2, lon2) {
  const R = 6371;
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLon/2) * Math.sin(dLon/2);
  return R * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)));
}

function activateNearMe() {
  if (!navigator.geolocation) {
    alert("Geolocation is not supported by your browser.");
    return;
  }
  const chipNearMe = document.getElementById('chipNearMe');
  if (chipNearMe) chipNearMe.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Locating...';

  navigator.geolocation.getCurrentPosition(
    pos => {
      userLocation = { lat: pos.coords.latitude, lng: pos.coords.longitude };
      if (chipNearMe) chipNearMe.innerHTML = '<i class="fas fa-location-arrow text-primary"></i> Near Me (Active)';
      
      salonItems.forEach(item => {
        const sLat = parseFloat(item.dataset.lat);
        const sLng = parseFloat(item.dataset.lng);
        const badgeCont = item.querySelector('.distance-badge-container');
        if (!isNaN(sLat) && !isNaN(sLng) && sLat !== 0) {
          const dist = getDistanceKm(userLocation.lat, userLocation.lng, sLat, sLng);
          item.dataset.distance = dist;
          if (badgeCont) {
            badgeCont.innerHTML = `<span class="badge bg-primary text-white rounded-pill px-2 py-1 small shadow-sm"><i class="fas fa-location-arrow me-1"></i>${dist < 1 ? (dist * 1000).toFixed(0) + ' m' : dist.toFixed(1) + ' km'}</span>`;
          }
        } else {
          item.dataset.distance = 99999;
        }
      });

      if (sortSelect) sortSelect.value = 'distance';
      filterAndSortSalons();
    },
    err => {
      if (chipNearMe) chipNearMe.innerHTML = '<i class="fas fa-location-arrow text-primary"></i> Near Me (GPS)';
      alert("Could not access your location. Please check browser location permissions.");
    },
    { timeout: 10000, enableHighAccuracy: true }
  );
}

// View toggle (Grid / List)
document.querySelectorAll('.view-toggle-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.view-toggle-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    const view = this.dataset.view;

    if (view === 'list') {
      salonsGrid.classList.add('list-view');
      salonItems.forEach(item => {
        item.classList.remove('col-lg-4', 'col-md-6');
        item.classList.add('col-12');
      });
    } else {
      salonsGrid.classList.remove('list-view');
      salonItems.forEach(item => {
        item.classList.remove('col-12');
        item.classList.add('col-lg-4', 'col-md-6');
      });
    }
  });
});

function openReviewModal(btn) {
  const salonId = btn.getAttribute('data-salon');
  const salonName = btn.getAttribute('data-name');
  document.getElementById('reviewSalonId').value = salonId;
  document.getElementById('reviewSalonName').innerText = salonName;
}

function updateCharCount(el) {
  document.getElementById('charCount').innerText = el.value.length + ' / 1000';
}
</script>

<?php include __DIR__ . '/../footer.php'; ?>
