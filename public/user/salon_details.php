<?php
session_start();
date_default_timezone_set('Asia/Colombo');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

$salon_id = intval($_GET['id'] ?? 0);
if ($salon_id <= 0) die("Invalid salon ID.");

// Fetch salon info with enhanced query
$stmt = $pdo->prepare("
    SELECT s.*, u.username AS owner_name, u.email AS owner_email 
    FROM salons s 
    LEFT JOIN users u ON s.owner_id = u.id 
    WHERE s.id = ?
");
$stmt->execute([$salon_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$salon) die("Salon not found.");

// Fetch services with category grouping
$stmt = $pdo->prepare("SELECT * FROM services WHERE salon_id = ? ORDER BY name ASC");
$stmt->execute([$salon_id]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch reviews with pagination support
$reviews_per_page = 5;
$page = intval($_GET['page'] ?? 1);
$offset = ($page - 1) * $reviews_per_page;

$stmt = $pdo->prepare("
    SELECT r.*, u.username 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.salon_id = ? 
    ORDER BY r.created_at DESC 
    LIMIT $reviews_per_page OFFSET $offset
");
$stmt->execute([$salon_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total reviews count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE salon_id = ?");
$stmt->execute([$salon_id]);
$total_reviews = $stmt->fetchColumn();
$total_pages = ceil($total_reviews / $reviews_per_page);

// Calculate detailed rating statistics
$avg_rating = 0;
$rating_distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

if ($total_reviews > 0) {
    $stmt = $pdo->prepare("SELECT rating FROM reviews WHERE salon_id = ?");
    $stmt->execute([$salon_id]);
    $all_ratings = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $total = array_sum($all_ratings);
    $avg_rating = round($total / count($all_ratings), 1);
    
    foreach ($all_ratings as $rating) {
        $rating_distribution[$rating]++;
    }
}

// Check if user has already reviewed
$user_has_reviewed = false;
if (isset($_SESSION['id'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE salon_id = ? AND user_id = ?");
    $stmt->execute([$salon_id, $_SESSION['id']]);
    $user_has_reviewed = $stmt->fetchColumn() > 0;
}

// Fetch staff for this salon
$staffStmt = $pdo->prepare("SELECT * FROM salon_staff WHERE salon_id = ? AND is_active = 1 ORDER BY name ASC");
$staffStmt->execute([$salon_id]);
$salon_staff = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch gallery photos for this salon
$galleryStmt = $pdo->prepare("SELECT * FROM salon_gallery WHERE salon_id = ? ORDER BY id DESC LIMIT 12");
$galleryStmt->execute([$salon_id]);
$salon_gallery = $galleryStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active promo codes
$promoStmt = $pdo->prepare("SELECT code, discount_percent, discount_amount, valid_until FROM promo_codes WHERE salon_id = ? AND is_active = 1 AND (valid_until IS NULL OR valid_until >= CURDATE())");
$promoStmt->execute([$salon_id]);
$active_promos = $promoStmt->fetchAll(PDO::FETCH_ASSOC);

// Can interact?
$user_can_interact = isset($_SESSION['role'], $_SESSION['id']) &&
                     in_array($_SESSION['role'], ['user', 'customer']) &&
                     $_SESSION['id'] != $salon['owner_id'];

// Format operating hours from opening_time, closing_time and operating_days
$operating_days_list = !empty($salon['operating_days']) ? $salon['operating_days'] : 'Everyday';
$operating_hours = "$operating_days_list: 9:00 AM - 8:00 PM";
if (!empty($salon['opening_time']) && !empty($salon['closing_time'])) {
    $opening_fmt = date('g:i A', strtotime($salon['opening_time']));
    $closing_fmt = date('g:i A', strtotime($salon['closing_time']));
    $operating_hours = "$operating_days_list: $opening_fmt - $closing_fmt";
}

// Get contact and social info
$phone = $salon['phone'] ?? null;
$email = $salon['email'] ?? null;
$website = $salon['website'] ?? null;
$facebook = $salon['facebook'] ?? null;
$instagram = $salon['instagram'] ?? null;
$description = $salon['description'] ?? null;

// Check if salon is currently open (checking both operating days and time)
$is_open = false;
$current_status = 'Closed';

$todayDay = date('l');
$operatingDays = !empty($salon['operating_days']) 
    ? array_map('trim', explode(',', $salon['operating_days'])) 
    : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

$isOpenDay = in_array($todayDay, $operatingDays);

if (!$isOpenDay) {
    $is_open = false;
    $current_status = 'Closed Today';
} elseif (!empty($salon['opening_time']) && !empty($salon['closing_time'])) {
    $now = new DateTime();
    $opening = DateTime::createFromFormat('H:i:s', $salon['opening_time']) ?: DateTime::createFromFormat('H:i', $salon['opening_time']);
    $closing = DateTime::createFromFormat('H:i:s', $salon['closing_time']) ?: DateTime::createFromFormat('H:i', $salon['closing_time']);

    if ($opening && $closing) {
        $opening->setDate($now->format('Y'), $now->format('m'), $now->format('d'));
        $closing->setDate($now->format('Y'), $now->format('m'), $now->format('d'));

        if ($now >= $opening && $now <= $closing) {
            $is_open = true;
            $current_status = 'Open Now';
        } else {
            $is_open = false;
            $current_status = 'Closed';
        }
    }
}

// Get popular services (most booked)
$stmt = $pdo->prepare("
    SELECT s.id, s.salon_id, s.name, s.description, s.price, s.duration, s.category, COUNT(a.id) as booking_count 
    FROM services s 
    LEFT JOIN appointments a ON s.id = a.service_id 
    WHERE s.salon_id = ? 
    GROUP BY s.id, s.salon_id, s.name, s.description, s.price, s.duration, s.category
    ORDER BY booking_count DESC 
    LIMIT 3
");
$stmt->execute([$salon_id]);
$popular_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($salon['name']) ?> - Salonora</title>
  
  <!-- SEO Meta Tags -->
  <meta name="description" content="Book appointments at <?= htmlspecialchars($salon['name']) ?>. Rating: <?= $avg_rating ?>/5 from <?= $total_reviews ?> reviews.">
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Leaflet Map CSS (Free OpenStreetMap) -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="../assets/css/salon_details.css">
</head>
<body>
  
  <!-- Hero Section with Overlay -->
  <div class="salon-hero">
    <?php if ($salon['image']): ?>
      <div class="salon-hero-bg" style="background-image: url('../../<?= htmlspecialchars($salon['image']) ?>');"></div>
      <div class="hero-overlay"></div>
    <?php endif; ?>
  </div>

  <div class="container pb-5">
    <!-- Enhanced Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <a href="salon_view.php" class="btn-back">
        <i class="fas fa-arrow-left"></i> Back to Salons
      </a>
      <div class="d-none d-md-flex gap-3">
        <a href="#services" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-cut"></i> Services
        </a>
        <a href="#reviews" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-star"></i> Reviews
        </a>
      </div>
    </div>

    <!-- Salon Description Section -->
    <?php if ($description): ?>
    <div class="section">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-info-circle"></i>
          About This Salon
        </h2>
      </div>
      <div class="salon-description">
        <p><?= nl2br(htmlspecialchars($description)) ?></p>
      </div>
    </div>
    <?php endif; ?>

    <!-- Popular Services Section -->
    <?php if (!empty($popular_services) && count($popular_services) > 0): ?>
    <div class="section">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-fire"></i>
          Popular Services
        </h2>
        <span class="section-badge">Most Booked</span>
      </div>
      <div class="row">
        <?php foreach ($popular_services as $ps): ?>
          <div class="col-md-4 mb-3">
            <div class="popular-service-card">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-fire text-danger me-2"></i>
                <h5 class="mb-0"><?= htmlspecialchars($ps['name']) ?></h5>
              </div>
              <div class="d-flex justify-content-between align-items-center">
                <span class="text-primary fw-bold">Rs <?= number_format($ps['price'], 2) ?></span>
                <span class="text-muted"><i class="far fa-clock"></i> <?= $ps['duration'] ?> mins</span>
              </div>
              <?php if ($ps['booking_count'] > 0): ?>
                <small class="text-success">
                  <i class="fas fa-check-circle"></i> <?= $ps['booking_count'] ?> bookings
                </small>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Enhanced Salon Info Card -->
    <div class="salon-info-card">
      <div class="salon-header">
        <?php if ($salon['image']): ?>
          <img src="../../<?= htmlspecialchars($salon['image']) ?>" 
               class="salon-image" 
               alt="<?= htmlspecialchars($salon['name']) ?>"
               loading="lazy">
        <?php endif; ?>
        <div class="salon-details flex-grow-1">
          <h1><?= htmlspecialchars($salon['name']) ?></h1>
          
          <!-- Enhanced Rating Display -->
          <div class="rating-container mb-3">
            <div class="rating-badge">
              <i class="fas fa-star"></i>
              <?= $avg_rating ?> / 5.0
            </div>
            <span class="text-muted ms-2">(<?= $total_reviews ?> <?= $total_reviews == 1 ? 'review' : 'reviews' ?>)</span>
            <span class="badge <?= $is_open ? 'bg-success' : 'bg-danger' ?> ms-2">
              <i class="fas fa-clock"></i> <?= $current_status ?>
            </span>
          </div>

          <!-- Enhanced Meta Information -->
          <div class="salon-meta">
            <div class="meta-item">
              <i class="fas fa-map-marker-alt"></i>
              <span><?= htmlspecialchars($salon['address']) ?></span>
            </div>
            <?php if ($phone): ?>
            <div class="meta-item">
              <i class="fas fa-phone"></i>
              <a href="tel:<?= htmlspecialchars($phone) ?>"><?= htmlspecialchars($phone) ?></a>
            </div>
            <?php
            $rawPhone = preg_replace('/[^0-9]/', '', $phone);
            $waPhone = str_starts_with($rawPhone, '0') ? ('94' . substr($rawPhone, 1)) : $rawPhone;
            $waText = urlencode("Hello " . $salon['name'] . ", I'm interested in your salon services on Salonora!");
            ?>
            <div class="meta-item">
              <i class="fab fa-whatsapp text-success"></i>
              <a href="https://wa.me/<?= htmlspecialchars($waPhone) ?>?text=<?= $waText ?>" target="_blank" rel="noopener" class="text-success fw-bold">
                Chat on WhatsApp
              </a>
            </div>
            <?php endif; ?>
            <?php if ($email): ?>
            <div class="meta-item">
              <i class="fas fa-envelope"></i>
              <a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a>
            </div>
            <?php endif; ?>
            <?php if ($website): ?>
            <div class="meta-item">
              <i class="fas fa-globe"></i>
              <a href="<?= htmlspecialchars($website) ?>" target="_blank" rel="noopener">Visit Website</a>
            </div>
            <?php endif; ?>
            <div class="meta-item">
              <i class="fas fa-clock"></i>
              <span><?= htmlspecialchars($operating_hours) ?></span>
            </div>
            <div class="meta-item">
              <i class="fas fa-user-tie"></i>
              <span>Owner: <strong><?= htmlspecialchars(!empty($salon['owner_name']) ? $salon['owner_name'] : 'Verified Stylist') ?></strong></span>
            </div>
          </div>

          <!-- Social Media Links -->
          <?php if ($facebook || $instagram): ?>
          <div class="social-links mt-3">
            <?php if ($facebook): ?>
            <a href="<?= htmlspecialchars($facebook) ?>" target="_blank" rel="noopener" class="social-link facebook">
              <i class="fab fa-facebook"></i> Facebook
            </a>
            <?php endif; ?>
            <?php if ($instagram): ?>
            <a href="<?= htmlspecialchars($instagram) ?>" target="_blank" rel="noopener" class="social-link instagram">
              <i class="fab fa-instagram"></i> Instagram
            </a>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <!-- Quick Stats -->
          <div class="quick-stats mt-3">
            <div class="stat-item">
              <i class="fas fa-concierge-bell"></i>
              <div>
                <strong><?= count($services) ?></strong>
                <small>Services</small>
              </div>
            </div>
            <div class="stat-item">
              <i class="fas fa-comments"></i>
              <div>
                <strong><?= $total_reviews ?></strong>
                <small>Reviews</small>
              </div>
            </div>
            <?php if ($salon['parking_available']): ?>
            <div class="stat-item">
              <i class="fas fa-parking"></i>
              <div>
                <strong>Available</strong>
                <small>Parking</small>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($salon['wifi_available']): ?>
            <div class="stat-item">
              <i class="fas fa-wifi"></i>
              <div>
                <strong>Free</strong>
                <small>WiFi</small>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($salon['wheelchair_accessible']): ?>
            <div class="stat-item">
              <i class="fas fa-wheelchair"></i>
              <div>
                <strong>Yes</strong>
                <small>Accessible</small>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($salon['air_conditioned']): ?>
            <div class="stat-item">
              <i class="fas fa-snowflake"></i>
              <div>
                <strong>Yes</strong>
                <small>AC</small>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Services Section with Search/Filter -->
    <div class="section" id="services">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-cut"></i>
          Available Services
        </h2>
        <span class="section-badge"><?= count($services) ?> Services</span>
      </div>

      <?php if (!empty($services)): ?>
        <!-- Service Search and Sort -->
        <div class="row mb-4">
          <div class="col-md-8">
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-search"></i></span>
              <input type="text" 
                     id="serviceSearch" 
                     class="form-control" 
                     placeholder="Search services...">
            </div>
          </div>
          <div class="col-md-4">
            <select id="serviceSort" class="form-select">
              <option value="name">Sort by Name</option>
              <option value="price-low">Price: Low to High</option>
              <option value="price-high">Price: High to Low</option>
              <option value="duration">Duration</option>
            </select>
          </div>
        </div>
      <?php endif; ?>

      <?php if (empty($services)): ?>
        <div class="empty-state">
          <i class="fas fa-spa"></i>
          <h4>No Services Available</h4>
          <p>This salon hasn't added any services yet.</p>
        </div>
      <?php else: ?>
        <div class="services-grid" id="servicesGrid">
          <?php foreach ($services as $s): ?>
            <div class="service-card" 
                 data-service-name="<?= strtolower(htmlspecialchars($s['name'])) ?>"
                 data-price="<?= $s['price'] ?>"
                 data-duration="<?= $s['duration'] ?>">
              <div class="service-icon">
                <i class="fas fa-scissors"></i>
              </div>
              <h5 class="service-name"><?= htmlspecialchars($s['name']) ?></h5>
              <?php if (!empty($s['description'])): ?>
                <p class="service-description text-muted small">
                  <?= htmlspecialchars($s['description']) ?>
                </p>
              <?php endif; ?>
              <div class="service-details">
                <div class="service-detail">
                  <i class="fas fa-tag"></i>
                  <span>Rs <strong><?= number_format($s['price'], 2) ?></strong></span>
                </div>
                <div class="service-detail">
                  <i class="far fa-clock"></i>
                  <span><strong><?= htmlspecialchars($s['duration']) ?></strong> mins</span>
                </div>
              </div>
              <?php if ($user_can_interact): ?>
                <button class="btn btn-book book-btn" 
                        data-service="<?= $s['id'] ?>" 
                        data-service-name="<?= htmlspecialchars($s['name']) ?>"
                        data-service-price="<?= htmlspecialchars($s['price']) ?>"
                        data-service-duration="<?= htmlspecialchars($s['duration']) ?>">
                  <i class="fas fa-calendar-check"></i> Book Now
                </button>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($active_promos)): ?>
    <!-- Active Promo Codes -->
    <div class="section mb-4 p-4 rounded-4 shadow-sm" style="background: linear-gradient(135deg, rgba(233,30,99,0.06) 0%, rgba(139,92,246,0.06) 100%); border: 2px dashed rgba(233,30,99,0.3);">
      <div class="d-flex align-items-center mb-3">
        <i class="fas fa-tags text-warning fa-2x me-3"></i>
        <div>
          <h4 class="fw-bold mb-0 text-dark">Special Offers & Promo Codes</h4>
          <small class="text-muted">Use these promo codes when booking your appointment to save!</small>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-3">
        <?php foreach ($active_promos as $promo): ?>
        <div class="bg-white p-3 rounded-3 shadow-sm border d-flex align-items-center gap-3">
          <span class="badge bg-primary fs-6 px-3 py-2 font-monospace tracking-wide">
            <?= htmlspecialchars($promo['code']) ?>
          </span>
          <div>
            <strong class="text-success d-block">
              <?= $promo['discount_percent'] > 0 ? ($promo['discount_percent'] . '% OFF') : ('Rs ' . number_format($promo['discount_amount'], 2) . ' OFF') ?>
            </strong>
            <small class="text-muted">Valid for online bookings</small>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($salon_staff)): ?>
    <!-- Staff & Stylists Section -->
    <div class="section mb-4" id="staff">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-user-friends"></i>
          Our Professional Team
        </h2>
        <span class="section-badge"><?= count($salon_staff) ?> Stylists</span>
      </div>
      <div class="row g-3">
        <?php foreach ($salon_staff as $st): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="card h-100 border-0 shadow-sm rounded-4 text-center p-3">
            <div class="mb-2">
              <?php if (!empty($st['avatar'])): ?>
                <img src="<?= htmlspecialchars($st['avatar']) ?>" alt="<?= htmlspecialchars($st['name']) ?>" class="rounded-circle shadow-sm" style="width: 75px; height: 75px; object-fit: cover;">
              <?php else: ?>
                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto shadow-sm" style="width: 75px; height: 75px;">
                  <i class="fas fa-user-tie fa-2x text-muted"></i>
                </div>
              <?php endif; ?>
            </div>
            <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($st['name']) ?></h6>
            <small class="text-primary"><?= htmlspecialchars($st['specialty'] ?: 'Stylist') ?></small>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($salon_gallery)): ?>
    <!-- Salon Photo Gallery / Portfolio -->
    <div class="section mb-4" id="gallery">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-images"></i>
          Work Portfolio & Gallery
        </h2>
        <span class="section-badge"><?= count($salon_gallery) ?> Photos</span>
      </div>
      <div class="row g-3">
        <?php foreach ($salon_gallery as $photo): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden position-relative group">
            <img src="<?= htmlspecialchars($photo['image_url']) ?>" alt="<?= htmlspecialchars($photo['title']) ?>" class="img-fluid" style="height: 180px; width: 100%; object-fit: cover;">
            <?php if (!empty($photo['title'])): ?>
            <div class="p-2 text-center bg-white">
              <small class="fw-semibold text-truncate d-block text-dark"><?= htmlspecialchars($photo['title']) ?></small>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Enhanced Reviews Section -->
    <div class="section" id="reviews">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-star"></i>
          Customer Reviews
        </h2>
        <span class="section-badge"><?= $total_reviews ?> Reviews</span>
      </div>

      <?php if ($total_reviews > 0): ?>
        <!-- Rating Distribution -->
        <div class="rating-summary mb-4">
          <div class="row align-items-center">
            <div class="col-md-4 text-center">
              <div class="average-rating">
                <h1 class="display-3 mb-0"><?= $avg_rating ?></h1>
                <div class="stars mb-2">
                  <?php for($i = 0; $i < 5; $i++): ?>
                    <i class="<?= $i < round($avg_rating) ? 'fas' : 'far' ?> fa-star text-warning"></i>
                  <?php endfor; ?>
                </div>
                <p class="text-muted mb-0">Based on <?= $total_reviews ?> reviews</p>
              </div>
            </div>
            <div class="col-md-8">
              <?php foreach ([5, 4, 3, 2, 1] as $star): ?>
                <div class="rating-bar-container">
                  <span class="rating-label"><?= $star ?> <i class="fas fa-star"></i></span>
                  <div class="progress rating-progress">
                    <div class="progress-bar bg-warning" 
                         style="width: <?= $total_reviews > 0 ? ($rating_distribution[$star] / $total_reviews * 100) : 0 ?>%">
                    </div>
                  </div>
                  <span class="rating-count"><?= $rating_distribution[$star] ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="reviews-container">
        <?php if (empty($reviews) && $page == 1): ?>
          <div class="empty-state">
            <i class="far fa-comments"></i>
            <h4>No Reviews Yet</h4>
            <p>Be the first to review this salon!</p>
          </div>
        <?php else: ?>
                        <?php foreach ($reviews as $r): ?>
            <div class="review-item">
              <div class="review-header">
                <div class="review-user">
                  <div class="user-avatar">
                    <?= strtoupper(substr($r['username'], 0, 1)) ?>
                  </div>
                  <div class="user-info">
                    <h6><?= htmlspecialchars($r['username']) ?></h6>
                    <div class="review-stars">
                      <?php for($i = 0; $i < 5; $i++): ?>
                        <i class="<?= $i < $r['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                      <?php endfor; ?>
                    </div>
                  </div>
                </div>
                <small class="review-date">
                  <i class="far fa-calendar"></i> <?= date('M d, Y', strtotime($r['created_at'])) ?>
                </small>
              </div>
              <p class="review-content"><?= nl2br(htmlspecialchars($r['comment'])) ?></p>
              
              <!-- Helpful Review Actions -->
              <?php if (isset($_SESSION['id']) && $_SESSION['id'] != $r['user_id']): ?>
              <div class="review-actions mt-2">
                <button class="btn btn-sm btn-outline-secondary helpful-btn" data-review-id="<?= $r['id'] ?>">
                  <i class="far fa-thumbs-up"></i> Helpful
                </button>
              </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>

          <!-- Pagination -->
          <?php if ($total_pages > 1): ?>
            <nav aria-label="Reviews pagination" class="mt-4">
              <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                  <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?id=<?= $salon_id ?>&page=<?= $i ?>#reviews"><?= $i ?></a>
                  </li>
                <?php endfor; ?>
              </ul>
            </nav>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($user_can_interact): ?>
          <div class="text-center mt-4">
            <?php if ($user_has_reviewed): ?>
              <p class="text-muted">
                <i class="fas fa-check-circle text-success"></i> You've already reviewed this salon
              </p>
            <?php else: ?>
              <button class="btn-write-review" id="writeReviewBtn">
                <i class="fas fa-pen"></i> Write a Review
              </button>
            <?php endif; ?>
          </div>
        <?php elseif (!isset($_SESSION['id'])): ?>
          <div class="text-center mt-4">
            <p class="text-muted">
              <a href="../login.php">Login</a> to write a review
            </p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Map Section (if lat/lng available) -->
    <?php if (!empty($salon['lat']) && !empty($salon['lng'])): ?>
    <div class="section">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-map-marked-alt"></i>
          Location
        </h2>
      </div>
      <div class="salon-map">
        <div id="detailMap" style="width: 100%; height: 400px; border-radius: 12px; z-index: 1;"></div>
        <div class="mt-3 text-center d-flex justify-content-center gap-2 flex-wrap">
          <a href="https://www.openstreetmap.org/?mlat=<?= $salon['lat'] ?>&mlon=<?= $salon['lng'] ?>#map=16/<?= $salon['lat'] ?>/<?= $salon['lng'] ?>" 
             target="_blank" 
             class="btn btn-outline-primary btn-sm">
            <i class="fas fa-map-marked-alt"></i> OpenStreetMap
          </a>
          <a href="https://www.google.com/maps/search/?api=1&query=<?= $salon['lat'] ?>,<?= $salon['lng'] ?>" 
             target="_blank" 
             class="btn btn-primary btn-sm">
            <i class="fas fa-directions"></i> Get Directions
          </a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Share & Actions Section -->
    <div class="section">
      <div class="text-center">
        <h5 class="mb-3">Share This Salon</h5>
        <div class="share-buttons">
          <button class="btn btn-outline-primary" onclick="shareOnFacebook()">
            <i class="fab fa-facebook"></i> Facebook
          </button>
          <button class="btn btn-outline-info" onclick="shareOnTwitter()">
            <i class="fab fa-twitter"></i> Twitter
          </button>
          <button class="btn btn-outline-success" onclick="shareOnWhatsApp()">
            <i class="fab fa-whatsapp"></i> WhatsApp
          </button>
          <button class="btn btn-outline-secondary" onclick="copyLink()">
            <i class="fas fa-link"></i> Copy Link
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Enhanced Book Appointment Modal -->
  <div class="modal fade" id="bookModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fas fa-calendar-check me-2"></i>Book Appointment
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="book_salon_id" value="<?= $salon_id ?>">
          <input type="hidden" id="book_service_id">
          
          <!-- Service Summary -->
          <div class="booking-summary mb-4">
            <h6 class="mb-3">Booking Details</h6>
            <div class="mb-2">
              <strong>Service:</strong> <span id="book_service_name_display"></span>
            </div>
            <div class="mb-2">
              <strong>Price:</strong> Rs <span id="book_service_price_display"></span>
            </div>
            <div>
              <strong>Duration:</strong> <span id="book_service_duration_display"></span> mins
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">
              <i class="fas fa-calendar"></i> Appointment Date *
            </label>
            <input type="date" 
                   id="appointment_date" 
                   class="form-control" 
                   required 
                   min="<?= date('Y-m-d') ?>"
                   max="<?= date('Y-m-d', strtotime('+3 months')) ?>">
            <small class="form-text text-muted">Select a date within the next 3 months</small>
          </div>
          
          <div class="mb-3">
            <label class="form-label">
              <i class="fas fa-clock"></i> Appointment Time *
            </label>
            <input type="time" 
                   id="appointment_time" 
                   class="form-control" 
                   required
                   min="09:00"
                   max="20:00">
            <small class="form-text text-muted">Operating hours: <?= htmlspecialchars($operating_hours) ?></small>
          </div>

          <div class="mb-3">
            <label class="form-label">
              <i class="fas fa-comment"></i> Special Requests (Optional)
            </label>
            <textarea id="booking_notes" 
                      class="form-control" 
                      rows="3" 
                      placeholder="Any special requests or notes for the salon..."></textarea>
          </div>

          <div id="book_msg" class="alert" style="display: none;"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-modal-secondary" data-bs-dismiss="modal">Cancel</button>
          <button id="bookSubmit" class="btn btn-modal-primary">
            <i class="fas fa-check me-2"></i>Confirm Booking
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Enhanced Review Modal -->
  <div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fas fa-star me-2"></i>Write a Review
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="review_salon_id" value="<?= $salon_id ?>">
          
          <div class="mb-4">
            <label class="form-label">Your Rating *</label>
            <div class="star-rating-input" id="starRatingInput">
              <i class="far fa-star" data-rating="1"></i>
              <i class="far fa-star" data-rating="2"></i>
              <i class="far fa-star" data-rating="3"></i>
              <i class="far fa-star" data-rating="4"></i>
              <i class="far fa-star" data-rating="5"></i>
            </div>
            <input type="hidden" id="review_rating" required>
            <small class="form-text text-muted">Click to rate</small>
          </div>

          <div class="mb-3">
            <label class="form-label">Your Review *</label>
            <textarea id="review_comment" 
                      class="form-control" 
                      rows="5" 
                      placeholder="Share your experience with this salon..." 
                      required
                      minlength="10"
                      maxlength="500"></textarea>
            <small class="form-text text-muted">
              <span id="charCount">0</span>/500 characters (minimum 10)
            </small>
          </div>

          <div id="review_msg" class="alert" style="display: none;"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-modal-secondary" data-bs-dismiss="modal">Cancel</button>
          <button id="reviewSubmit" class="btn btn-modal-primary">
            <i class="fas fa-paper-plane me-2"></i>Submit Review
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Loading Overlay -->
  <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div class="spinner-border text-light" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Service Search Functionality
    const serviceSearch = document.getElementById('serviceSearch');
    const serviceSort = document.getElementById('serviceSort');
    const servicesGrid = document.getElementById('servicesGrid');
    
    if (serviceSearch) {
      serviceSearch.addEventListener('input', filterAndSortServices);
    }
    
    if (serviceSort) {
      serviceSort.addEventListener('change', filterAndSortServices);
    }
    
    function filterAndSortServices() {
      const searchTerm = serviceSearch ? serviceSearch.value.toLowerCase() : '';
      const sortBy = serviceSort ? serviceSort.value : 'name';
      const serviceCards = Array.from(document.querySelectorAll('.service-card'));
      
      // Filter
      serviceCards.forEach(card => {
        const serviceName = card.dataset.serviceName;
        if (serviceName.includes(searchTerm)) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
      
      // Sort visible cards
      const visibleCards = serviceCards.filter(card => card.style.display !== 'none');
      visibleCards.sort((a, b) => {
        switch(sortBy) {
          case 'price-low':
            return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
          case 'price-high':
            return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
          case 'duration':
            return parseInt(a.dataset.duration) - parseInt(b.dataset.duration);
          case 'name':
          default:
            return a.dataset.serviceName.localeCompare(b.dataset.serviceName);
        }
      });
      
      // Re-append in sorted order
      if (servicesGrid) {
        visibleCards.forEach(card => servicesGrid.appendChild(card));
      }
    }

    // Share Functions
    function shareOnFacebook() {
      const url = encodeURIComponent(window.location.href);
      window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}`, '_blank', 'width=600,height=400');
    }
    
    function shareOnTwitter() {
      const url = encodeURIComponent(window.location.href);
      const text = encodeURIComponent('Check out this salon!');
      window.open(`https://twitter.com/intent/tweet?url=${url}&text=${text}`, '_blank', 'width=600,height=400');
    }
    
    function shareOnWhatsApp() {
      const url = encodeURIComponent(window.location.href);
      const text = encodeURIComponent('Check out this salon!');
      window.open(`https://wa.me/?text=${text} ${url}`, '_blank');
    }
    
    function copyLink() {
      navigator.clipboard.writeText(window.location.href).then(() => {
        alert('Link copied to clipboard!');
      }).catch(err => {
        console.error('Failed to copy:', err);
      });
    }

    // Enhanced Star Rating Input
    const starRatingInput = document.getElementById('starRatingInput');
    if (starRatingInput) {
      const stars = starRatingInput.querySelectorAll('i');
      let selectedRating = 0;

      stars.forEach(star => {
        star.addEventListener('click', () => {
          selectedRating = star.dataset.rating;
          document.getElementById('review_rating').value = selectedRating;
          updateStars(selectedRating);
        });

        star.addEventListener('mouseenter', () => {
          updateStars(star.dataset.rating);
        });
      });

      starRatingInput.addEventListener('mouseleave', () => {
        updateStars(selectedRating);
      });

      function updateStars(rating) {
        stars.forEach((star, index) => {
          if (index < rating) {
            star.classList.remove('far');
            star.classList.add('fas');
          } else {
            star.classList.remove('fas');
            star.classList.add('far');
          }
        });
      }
    }

    // Character Counter for Review
    const reviewComment = document.getElementById('review_comment');
    const charCount = document.getElementById('charCount');
    if (reviewComment && charCount) {
      reviewComment.addEventListener('input', () => {
        charCount.textContent = reviewComment.value.length;
      });
    }

    // Enhanced Book Modal
    document.querySelectorAll('.book-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('book_service_id').value = btn.dataset.service;
        document.getElementById('book_service_name_display').textContent = btn.dataset.serviceName;
        document.getElementById('book_service_price_display').textContent = btn.dataset.servicePrice;
        document.getElementById('book_service_duration_display').textContent = btn.dataset.serviceDuration;
        new bootstrap.Modal(document.getElementById('bookModal')).show();
      });
    });

    // Enhanced AJAX Book Appointment
    document.getElementById('bookSubmit').addEventListener('click', function() {
      const submitBtn = this;
      const salonId = document.getElementById('book_salon_id').value;
      const serviceId = document.getElementById('book_service_id').value;
      const date = document.getElementById('appointment_date').value;
      const time = document.getElementById('appointment_time').value;
      const notes = document.getElementById('booking_notes').value;

      if (!date || !time) { 
        showMessage('book_msg', 'Please select date and time', 'danger');
        return; 
      }

      // Validate date is not in the past
      const selectedDate = new Date(date + 'T' + time);
      const now = new Date();
      if (selectedDate < now) {
        showMessage('book_msg', 'Please select a future date and time', 'danger');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Booking...';

      fetch('../book_appointment.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `salon_id=${salonId}&service_id=${serviceId}&appointment_date=${date}&appointment_time=${time}&notes=${encodeURIComponent(notes)}`
      })
      .then(res => res.text())
      .then(data => {
        showMessage('book_msg', data, 'success');
        setTimeout(() => {
          location.reload();
        }, 1500);
      })
      .catch(err => {
        showMessage('book_msg', 'Booking failed. Please try again.', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-check me-2"></i>Confirm Booking';
      });
    });

    // Open Review Modal
    document.getElementById('writeReviewBtn')?.addEventListener('click', () => {
      new bootstrap.Modal(document.getElementById('reviewModal')).show();
    });

    // Enhanced AJAX Post Review
    document.getElementById('reviewSubmit').addEventListener('click', function() {
      const submitBtn = this;
      const salonId = document.getElementById('review_salon_id').value;
      const rating = document.getElementById('review_rating').value;
      const comment = document.getElementById('review_comment').value.trim();

      if (!rating) { 
        showMessage('review_msg', 'Please select a rating', 'warning');
        return; 
      }

      if (!comment || comment.length < 10) { 
        showMessage('review_msg', 'Please write at least 10 characters', 'warning');
        return; 
      }

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

      fetch('../post_review.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `salon_id=${salonId}&rating=${rating}&comment=${encodeURIComponent(comment)}`
      })
      .then(res => res.text())
      .then(data => {
        showMessage('review_msg', data, 'success');
        setTimeout(() => {
          location.reload();
        }, 1500);
      })
      .catch(err => {
        showMessage('review_msg', 'Review submission failed. Please try again.', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Review';
      });
    });

    // Helper function to show messages
    function showMessage(elementId, message, type) {
      const msgEl = document.getElementById(elementId);
      msgEl.textContent = message;
      msgEl.className = `alert alert-${type}`;
      msgEl.style.display = 'block';
    }

    // Smooth scroll with offset for fixed headers
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });

    // Auto-scroll to section if hash in URL
    if (window.location.hash) {
      setTimeout(() => {
        const target = document.querySelector(window.location.hash);
        if (target) {
          target.scrollIntoView({ behavior: 'smooth' });
        }
      }, 100);
    }

    // Form validation feedback
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
      form.addEventListener('submit', (e) => {
        if (!form.checkValidity()) {
          e.preventDefault();
          e.stopPropagation();
        }
        form.classList.add('was-validated');
      });
    });
  </script>

  <!-- Leaflet Map JS (100% Free OpenStreetMap) -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var mapContainer = document.getElementById('detailMap');
      <?php if (!empty($salon['lat']) && !empty($salon['lng'])): ?>
      if (mapContainer && typeof L !== 'undefined') {
        var lat = <?= (float)$salon['lat'] ?>;
        var lng = <?= (float)$salon['lng'] ?>;
        var map = L.map('detailMap').setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
          maxZoom: 19
        }).addTo(map);

        var marker = L.marker([lat, lng]).addTo(map);
        marker.bindPopup("<strong><?= addslashes(htmlspecialchars($salon['name'])) ?></strong><br><?= addslashes(htmlspecialchars($salon['address'])) ?>").openPopup();
      }
      <?php endif; ?>
    });
  </script>
</body>
</html>