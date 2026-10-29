<?php
session_start();
date_default_timezone_set('Asia/Colombo');
require_once __DIR__ . '/../../config.php';

// Initialize CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$salon_id = intval($_GET['id'] ?? 0);
if ($salon_id <= 0) {
    header('Location: salon_view.php');
    exit;
}

// Fetch salon info with owner details
$stmt = $pdo->prepare("
    SELECT s.*, u.username AS owner_name, u.email AS owner_email 
    FROM salons s 
    LEFT JOIN users u ON s.owner_id = u.id 
    WHERE s.id = ?
");
$stmt->execute([$salon_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$salon) {
    header('Location: salon_view.php');
    exit;
}

// Fetch services
$stmt = $pdo->prepare("SELECT * FROM services WHERE salon_id = ? AND is_active = 1 ORDER BY name ASC");
$stmt->execute([$salon_id]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch reviews with pagination support
$reviews_per_page = 5;
$page = max(1, intval($_GET['page'] ?? 1));
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
$total_reviews = intval($stmt->fetchColumn());
$total_pages = max(1, ceil($total_reviews / $reviews_per_page));

// Calculate rating statistics
$avg_rating = 0;
$rating_distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

if ($total_reviews > 0) {
    $stmt = $pdo->prepare("SELECT rating FROM reviews WHERE salon_id = ?");
    $stmt->execute([$salon_id]);
    $all_ratings = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $total = array_sum($all_ratings);
    $avg_rating = round($total / count($all_ratings), 1);
    
    foreach ($all_ratings as $rVal) {
        $rInt = intval($rVal);
        if (isset($rating_distribution[$rInt])) {
            $rating_distribution[$rInt]++;
        }
    }
}

// User context
$is_logged_in = isset($_SESSION['id']);
$user_id = $_SESSION['id'] ?? 0;
$user_role = strtolower($_SESSION['role'] ?? 'guest');
$is_owner = $is_logged_in && ($user_role === 'owner' || $user_id == $salon['owner_id']);
$user_can_interact = $is_logged_in && in_array($user_role, ['user', 'customer']) && ($user_id != $salon['owner_id']);

// Check if user has already reviewed
$user_has_reviewed = false;
if ($is_logged_in) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE salon_id = ? AND user_id = ?");
    $stmt->execute([$salon_id, $user_id]);
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

// Format operating hours
$operating_days_list = !empty($salon['operating_days']) ? $salon['operating_days'] : 'Everyday';
$operating_hours = "$operating_days_list: 9:00 AM - 8:00 PM";
if (!empty($salon['opening_time']) && !empty($salon['closing_time'])) {
    $opening_fmt = date('g:i A', strtotime($salon['opening_time']));
    $closing_fmt = date('g:i A', strtotime($salon['closing_time']));
    $operating_hours = "$operating_days_list: $opening_fmt - $closing_fmt";
}

// Contact and social info
$phone = $salon['phone'] ?? null;
$email = $salon['email'] ?? null;
$website = $salon['website'] ?? null;
$facebook = $salon['facebook'] ?? null;
$instagram = $salon['instagram'] ?? null;
$description = $salon['description'] ?? null;

// Determine if currently open
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

// Popular services (most booked)
$stmt = $pdo->prepare("
    SELECT s.id, s.salon_id, s.name, s.description, s.price, s.duration, s.category, COUNT(a.id) as booking_count 
    FROM services s 
    LEFT JOIN appointments a ON s.id = a.service_id 
    WHERE s.salon_id = ? AND s.is_active = 1
    GROUP BY s.id, s.salon_id, s.name, s.description, s.price, s.duration, s.category
    ORDER BY booking_count DESC, s.name ASC
    LIMIT 3
");
$stmt->execute([$salon_id]);
$popular_services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Resolved salon image
$salon_img_src = '';
if (!empty($salon['image']) && file_exists(__DIR__ . '/../../' . $salon['image'])) {
    $salon_img_src = '../../' . htmlspecialchars($salon['image']);
} else {
    // Check fallback uploads
    $sample_img = 'uploads/salon_1761629900_7dcb0ead26c8.jpg';
    if (file_exists(__DIR__ . '/../../' . $sample_img)) {
        $salon_img_src = '../../' . $sample_img;
    }
}
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
  
  <!-- Leaflet Map CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  
  <!-- Custom CSS -->
  <link rel="stylesheet" href="../assets/css/salon_details.css">

  <script>
    if (localStorage.getItem('salonora_theme') === 'dark') {
      document.documentElement.classList.add('dark-mode');
    }
  </script>
</head>
<body>
  
  <!-- Hero Banner -->
  <div class="salon-hero">
    <?php if ($salon_img_src): ?>
      <div class="salon-hero-bg" style="background-image: url('<?= $salon_img_src ?>');"></div>
    <?php endif; ?>
    <div class="hero-overlay"></div>
  </div>

  <div class="container pb-5" style="position: relative; z-index: 20; margin-top: -60px;">
    
    <!-- Top Navigation Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <a href="salon_view.php" class="btn-back">
        <i class="fas fa-arrow-left"></i> Back to Salons
      </a>
      
      <div class="d-flex gap-2 align-items-center flex-wrap">
        <button type="button" id="themeToggleBtn" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm bg-white" title="Toggle Theme">
          <i class="fas fa-moon me-1" id="themeToggleIcon"></i> Theme
        </button>
        <div class="d-none d-md-flex gap-2">
          <a href="#services" class="btn btn-outline-primary btn-sm rounded-pill px-3">
            <i class="fas fa-cut me-1"></i> Services
          </a>
          <?php if (!empty($salon_staff)): ?>
          <a href="#staff" class="btn btn-outline-primary btn-sm rounded-pill px-3">
            <i class="fas fa-user-friends me-1"></i> Team
          </a>
          <?php endif; ?>
          <a href="#reviews" class="btn btn-outline-primary btn-sm rounded-pill px-3">
            <i class="fas fa-star me-1"></i> Reviews
          </a>
          <?php if (!empty($salon['lat']) && !empty($salon['lng'])): ?>
          <a href="#location" class="btn btn-outline-primary btn-sm rounded-pill px-3">
            <i class="fas fa-map-marker-alt me-1"></i> Location
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div><!-- End Navigation Bar -->

    <!-- Main Salon Profile Card (At the Top!) -->
    <div class="salon-info-card">
      <div class="salon-header">
        <?php if ($salon_img_src): ?>
          <img src="<?= $salon_img_src ?>" 
               class="salon-image" 
               alt="<?= htmlspecialchars($salon['name']) ?>"
               loading="lazy">
        <?php else: ?>
          <div class="salon-avatar-fallback">
            <i class="fas fa-spa"></i>
            <span class="fw-bold fs-6">Salonora</span>
          </div>
        <?php endif; ?>

        <div class="salon-details flex-grow-1">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <h1 class="mb-0 fw-bold"><?= htmlspecialchars($salon['name']) ?></h1>
            <span class="badge <?= $is_open ? 'bg-success' : 'bg-danger' ?> fs-6 px-3 py-2 rounded-pill">
              <i class="fas fa-clock me-1"></i> <?= $current_status ?>
            </span>
          </div>
          
          <!-- Rating Display -->
          <div class="rating-container mb-3 d-flex align-items-center flex-wrap gap-2">
            <div class="rating-badge">
              <i class="fas fa-star"></i>
              <?= $avg_rating ?> / 5.0
            </div>
            <span class="text-muted fw-semibold ms-1">(<?= $total_reviews ?> <?= $total_reviews == 1 ? 'review' : 'reviews' ?>)</span>
          </div>

          <!-- Meta Information -->
          <div class="salon-meta">
            <div class="meta-item">
              <i class="fas fa-map-marker-alt"></i>
              <span><?= htmlspecialchars($salon['address']) ?></span>
            </div>
            
            <?php if ($phone): ?>
              <?php
              $rawPhone = preg_replace('/[^0-9]/', '', $phone);
              $waPhone = str_starts_with($rawPhone, '0') ? ('94' . substr($rawPhone, 1)) : $rawPhone;
              $waText = urlencode("Hello " . $salon['name'] . ", I found your salon on Salonora!");
              ?>
              <div class="meta-item">
                <i class="fas fa-phone"></i>
                <a href="tel:<?= htmlspecialchars($phone) ?>" class="text-decoration-none fw-semibold"><?= htmlspecialchars($phone) ?></a>
              </div>
              <div class="meta-item">
                <i class="fab fa-whatsapp text-success"></i>
                <a href="https://wa.me/<?= htmlspecialchars($waPhone) ?>?text=<?= $waText ?>" target="_blank" rel="noopener" class="text-success fw-bold text-decoration-none">
                  Chat on WhatsApp
                </a>
              </div>
            <?php endif; ?>

            <?php if ($email): ?>
              <div class="meta-item">
                <i class="fas fa-envelope"></i>
                <a href="mailto:<?= htmlspecialchars($email) ?>" class="text-decoration-none"><?= htmlspecialchars($email) ?></a>
              </div>
            <?php endif; ?>

            <?php if ($website): ?>
              <div class="meta-item">
                <i class="fas fa-globe"></i>
                <a href="<?= htmlspecialchars($website) ?>" target="_blank" rel="noopener" class="text-decoration-none">Visit Website</a>
              </div>
            <?php endif; ?>

            <div class="meta-item">
              <i class="fas fa-calendar-alt"></i>
              <span><?= htmlspecialchars($operating_hours) ?></span>
            </div>

            <div class="meta-item">
              <i class="fas fa-user-tie"></i>
              <span>Stylist: <strong><?= htmlspecialchars(!empty($salon['owner_name']) ? $salon['owner_name'] : 'Verified Stylist') ?></strong></span>
            </div>
          </div>

          <!-- Amenities & Stats Pills -->
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
            <?php if (!empty($salon['parking_available'])): ?>
            <div class="stat-item">
              <i class="fas fa-parking"></i>
              <div>
                <strong>Parking</strong>
                <small>Free</small>
              </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($salon['wifi_available'])): ?>
            <div class="stat-item">
              <i class="fas fa-wifi"></i>
              <div>
                <strong>WiFi</strong>
                <small>Free</small>
              </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($salon['air_conditioned'])): ?>
            <div class="stat-item">
              <i class="fas fa-snowflake"></i>
              <div>
                <strong>AC</strong>
                <small>Yes</small>
              </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($salon['wheelchair_accessible'])): ?>
            <div class="stat-item">
              <i class="fas fa-wheelchair"></i>
              <div>
                <strong>Wheelchair</strong>
                <small>Accessible</small>
              </div>
            </div>
            <?php endif; ?>
          </div>

          <!-- Primary Booking Call To Action -->
          <div class="mt-4 pt-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
              <?php if ($user_can_interact || !$is_logged_in): ?>
                <a href="book_appointment.php?salon_id=<?= $salon_id ?>" class="btn-book-hero">
                  <i class="fas fa-calendar-check"></i> Book an Appointment
                </a>
              <?php elseif ($is_owner): ?>
                <div class="alert alert-info py-2 px-3 mb-0 d-inline-flex align-items-center gap-2 rounded-3">
                  <i class="fas fa-user-shield"></i>
                  <span>You are viewing your own salon profile.</span>
                  <a href="../owner/dashboard.php" class="btn btn-sm btn-primary ms-2 rounded-pill">Owner Dashboard</a>
                </div>
              <?php endif; ?>
            </div>

            <div class="d-flex gap-2 flex-wrap">
              <?php if ($user_can_interact && !$user_has_reviewed): ?>
                <button type="button" class="btn btn-outline-primary rounded-pill px-3 py-2 fw-semibold" id="topWriteReviewBtn">
                  <i class="fas fa-pen me-1"></i> Write Review
                </button>
              <?php endif; ?>
              
              <?php if ($facebook || $instagram): ?>
                <?php if ($facebook): ?>
                  <a href="<?= htmlspecialchars($facebook) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary rounded-pill px-3 py-2 fw-semibold">
                    <i class="fab fa-facebook me-1"></i> Facebook
                  </a>
                <?php endif; ?>
                <?php if ($instagram): ?>
                  <a href="<?= htmlspecialchars($instagram) ?>" target="_blank" rel="noopener" class="btn btn-outline-danger rounded-pill px-3 py-2 fw-semibold">
                    <i class="fab fa-instagram me-1"></i> Instagram
                  </a>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>

        </div><!-- End salon-details -->
      </div><!-- End salon-header -->
    </div><!-- End Main Salon Profile Card -->

    <!-- About This Salon -->
    <?php if ($description): ?>
    <div class="section">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-info-circle"></i>
          About This Salon
        </h2>
      </div>
      <div class="salon-description p-4 rounded-4 shadow-sm bg-white border">
        <p class="mb-0 fs-6 leading-relaxed"><?= nl2br(htmlspecialchars($description)) ?></p>
      </div>
    </div>
    <?php endif; ?>

    <!-- Active Promo Codes Section -->
    <?php if (!empty($active_promos)): ?>
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

    <!-- Popular Services (Most Booked) -->
    <?php if (!empty($popular_services) && count($popular_services) > 0): ?>
    <div class="section">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-fire"></i>
          Popular Services
        </h2>
        <span class="section-badge">Most Booked</span>
      </div>
      <div class="row g-3">
        <?php foreach ($popular_services as $ps): ?>
          <div class="col-md-4">
            <div class="popular-service-card">
              <div>
                <div class="d-flex align-items-center mb-2">
                  <i class="fas fa-fire text-danger me-2"></i>
                  <h5 class="mb-0 fw-bold"><?= htmlspecialchars($ps['name']) ?></h5>
                </div>
                <?php if (!empty($ps['description'])): ?>
                  <p class="text-muted small mb-2"><?= htmlspecialchars($ps['description']) ?></p>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <span class="text-primary fw-bold fs-6">Rs <?= number_format($ps['price'], 2) ?></span>
                  <span class="text-muted small"><i class="far fa-clock"></i> <?= $ps['duration'] ?> mins</span>
                </div>
              </div>
              <div>
                <a href="book_appointment.php?salon_id=<?= $salon_id ?>&service_id=<?= $ps['id'] ?>" class="btn btn-sm btn-outline-primary w-100 rounded-pill py-2 fw-semibold">
                  <i class="fas fa-calendar-check me-1"></i> Book This Service
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- AI Advisor Banner -->
    <div class="card border-0 rounded-4 shadow-sm mb-4" style="background: linear-gradient(135deg, #1e1b4b 0%, #4c1d95 100%); color: #fff;">
      <div class="card-body p-3 p-md-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width:50px;height:50px;font-size:1.3rem;">
            <i class="fas fa-magic"></i>
          </div>
          <div>
            <h5 class="fw-bold mb-1">Unsure which hairstyle or treatment suits you best?</h5>
            <div class="text-white-50 small">Consult our Gemini AI Beauty Advisor for tailored recommendations from this salon's menu!</div>
          </div>
        </div>
        <a href="<?= url('user/ai_advisor.php') ?>?salon_id=<?= $salon_id ?>" class="btn btn-warning text-dark fw-bold rounded-pill px-4 shadow-sm">
          <i class="fas fa-sparkles me-1"></i> Consult AI Stylist
        </a>
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
        <div class="row mb-4 g-2">
          <div class="col-md-8">
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-search"></i></span>
              <input type="text" 
                     id="serviceSearch" 
                     class="form-control" 
                     placeholder="Search services by name...">
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
          <p>This salon hasn't listed any services yet.</p>
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

              <div class="service-actions mt-3">
                <?php if ($user_can_interact || !$is_logged_in): ?>
                  <a href="book_appointment.php?salon_id=<?= $salon_id ?>&service_id=<?= $s['id'] ?>" class="btn btn-book">
                    <i class="fas fa-calendar-check me-1"></i> Book Now
                  </a>
                <?php elseif ($is_owner): ?>
                  <span class="badge bg-secondary w-100 py-2">Owner View</span>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Staff & Stylists Section -->
    <?php if (!empty($salon_staff)): ?>
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
              <?php if (!empty($st['avatar']) && file_exists(__DIR__ . '/../../' . $st['avatar'])): ?>
                <img src="../../<?= htmlspecialchars($st['avatar']) ?>" alt="<?= htmlspecialchars($st['name']) ?>" class="rounded-circle shadow-sm" style="width: 75px; height: 75px; object-fit: cover;">
              <?php else: ?>
                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto shadow-sm" style="width: 75px; height: 75px;">
                  <i class="fas fa-user-tie fa-2x text-primary"></i>
                </div>
              <?php endif; ?>
            </div>
            <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($st['name']) ?></h6>
            <small class="text-primary fw-semibold"><?= htmlspecialchars($st['specialty'] ?: 'Professional Stylist') ?></small>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Salon Photo Gallery / Portfolio -->
    <?php if (!empty($salon_gallery)): ?>
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

    <!-- Interactive Map Section -->
    <?php if (!empty($salon['lat']) && !empty($salon['lng'])): ?>
    <div class="section" id="location">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-map-marked-alt"></i>
          Salon Location & Directions
        </h2>
      </div>
      <div class="salon-map p-3 rounded-4 shadow-sm bg-white border">
        <div id="detailMap" style="width: 100%; height: 380px; border-radius: 14px; z-index: 1;"></div>
        <div class="mt-3 text-center d-flex justify-content-center gap-2 flex-wrap">
          <a href="https://www.google.com/maps/search/?api=1&query=<?= $salon['lat'] ?>,<?= $salon['lng'] ?>" 
             target="_blank" 
             rel="noopener"
             class="btn btn-primary btn-sm rounded-pill px-4 py-2">
            <i class="fas fa-directions me-1"></i> Get Directions in Google Maps
          </a>
          <a href="https://www.openstreetmap.org/?mlat=<?= $salon['lat'] ?>&mlon=<?= $salon['lng'] ?>#map=16/<?= $salon['lat'] ?>/<?= $salon['lng'] ?>" 
             target="_blank" 
             rel="noopener"
             class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2">
            <i class="fas fa-external-link-alt me-1"></i> OpenStreetMap
          </a>
        </div>
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
        <div class="rating-summary mb-4 p-4 rounded-4 shadow-sm bg-white border">
          <div class="row align-items-center">
            <div class="col-md-4 text-center border-end">
              <div class="average-rating">
                <h1 class="display-3 mb-0 fw-bold text-primary"><?= $avg_rating ?></h1>
                <div class="stars mb-2">
                  <?php for($i = 0; $i < 5; $i++): ?>
                    <i class="<?= $i < round($avg_rating) ? 'fas' : 'far' ?> fa-star text-warning"></i>
                  <?php endfor; ?>
                </div>
                <p class="text-muted mb-0">Based on <?= $total_reviews ?> verified <?= $total_reviews == 1 ? 'review' : 'reviews' ?></p>
              </div>
            </div>
            <div class="col-md-8 px-md-4">
              <?php foreach ([5, 4, 3, 2, 1] as $star): ?>
                <div class="rating-bar-container d-flex align-items-center gap-2 mb-2">
                  <span class="rating-label fw-semibold" style="width: 50px;"><?= $star ?> <i class="fas fa-star text-warning"></i></span>
                  <div class="progress flex-grow-1" style="height: 10px; border-radius: 5px;">
                    <div class="progress-bar bg-warning" 
                         style="width: <?= $total_reviews > 0 ? ($rating_distribution[$star] / $total_reviews * 100) : 0 ?>%">
                    </div>
                  </div>
                  <span class="rating-count text-muted small" style="width: 30px; text-align: right;"><?= $rating_distribution[$star] ?></span>
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
            <p>Be the first customer to review this salon!</p>
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

        <div class="text-center mt-4">
          <?php if ($user_can_interact): ?>
            <?php if ($user_has_reviewed): ?>
              <p class="text-success fw-semibold">
                <i class="fas fa-check-circle"></i> You have already reviewed this salon. Thank you!
              </p>
            <?php else: ?>
              <button class="btn-write-review" id="writeReviewBtn">
                <i class="fas fa-pen"></i> Write a Review
              </button>
            <?php endif; ?>
          <?php elseif (!$is_logged_in): ?>
            <p class="text-muted">
              <a href="../login.php" class="btn btn-outline-primary rounded-pill px-4">Login to Write a Review</a>
            </p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Share Salon Section -->
    <div class="section">
      <div class="p-4 rounded-4 shadow-sm bg-white border text-center">
        <h5 class="mb-3 fw-bold">Share This Salon with Friends</h5>
        <div class="share-buttons d-flex justify-content-center gap-2 flex-wrap">
          <button class="btn btn-outline-primary rounded-pill px-3 py-2" onclick="shareOnFacebook()">
            <i class="fab fa-facebook me-1"></i> Facebook
          </button>
          <button class="btn btn-outline-info rounded-pill px-3 py-2" onclick="shareOnTwitter()">
            <i class="fab fa-twitter me-1"></i> Twitter
          </button>
          <button class="btn btn-outline-success rounded-pill px-3 py-2" onclick="shareOnWhatsApp()">
            <i class="fab fa-whatsapp me-1"></i> WhatsApp
          </button>
          <button class="btn btn-outline-secondary rounded-pill px-3 py-2" onclick="copyLink()">
            <i class="fas fa-link me-1"></i> Copy Link
          </button>
        </div>
      </div>
    </div>

  </div><!-- End .container -->

  <!-- Write Review Modal -->
  <?php if ($user_can_interact && !$user_has_reviewed): ?>
  <div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fas fa-star me-2"></i>Write a Review for <?= htmlspecialchars($salon['name']) ?>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="review_salon_id" value="<?= $salon_id ?>">
          <input type="hidden" id="review_csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          
          <div class="mb-4 text-center">
            <label class="form-label d-block fw-bold mb-2">Select Your Rating *</label>
            <div class="star-rating-input fs-3 text-warning" id="starRatingInput" style="cursor: pointer;">
              <i class="far fa-star" data-rating="1"></i>
              <i class="far fa-star" data-rating="2"></i>
              <i class="far fa-star" data-rating="3"></i>
              <i class="far fa-star" data-rating="4"></i>
              <i class="far fa-star" data-rating="5"></i>
            </div>
            <input type="hidden" id="review_rating" value="">
            <small class="form-text text-muted" id="ratingText">Click on stars to rate</small>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Your Review Feedback *</label>
            <textarea id="review_comment" 
                      class="form-control" 
                      rows="4" 
                      placeholder="Share your experience (staff, service quality, cleanliness)..." 
                      required
                      minlength="10"
                      maxlength="500"></textarea>
            <div class="d-flex justify-content-between mt-1">
              <small class="text-muted">Minimum 10 characters</small>
              <small class="text-muted"><span id="charCount">0</span>/500</small>
            </div>
          </div>

          <div id="review_msg" class="alert" style="display: none;"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-modal-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" id="reviewSubmit" class="btn btn-modal-primary">
            <i class="fas fa-paper-plane me-2"></i>Submit Review
          </button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Service Search and Sort
    const serviceSearch = document.getElementById('serviceSearch');
    const serviceSort = document.getElementById('serviceSort');
    const servicesGrid = document.getElementById('servicesGrid');
    
    function filterAndSortServices() {
      const searchTerm = serviceSearch ? serviceSearch.value.toLowerCase().trim() : '';
      const sortBy = serviceSort ? serviceSort.value : 'name';
      const serviceCards = Array.from(document.querySelectorAll('#servicesGrid .service-card'));
      
      serviceCards.forEach(card => {
        const serviceName = card.dataset.serviceName || '';
        if (serviceName.includes(searchTerm)) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
      
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
            return (a.dataset.serviceName || '').localeCompare(b.dataset.serviceName || '');
        }
      });
      
      if (servicesGrid) {
        visibleCards.forEach(card => servicesGrid.appendChild(card));
      }
    }

    if (serviceSearch) serviceSearch.addEventListener('input', filterAndSortServices);
    if (serviceSort) serviceSort.addEventListener('change', filterAndSortServices);

    // Share Functions
    function shareOnFacebook() {
      const url = encodeURIComponent(window.location.href);
      window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}`, '_blank', 'width=600,height=400');
    }
    
    function shareOnTwitter() {
      const url = encodeURIComponent(window.location.href);
      const text = encodeURIComponent('Check out <?= addslashes($salon['name']) ?> on Salonora!');
      window.open(`https://twitter.com/intent/tweet?url=${url}&text=${text}`, '_blank', 'width=600,height=400');
    }
    
    function shareOnWhatsApp() {
      const url = encodeURIComponent(window.location.href);
      const text = encodeURIComponent('Check out <?= addslashes($salon['name']) ?> on Salonora:');
      window.open(`https://wa.me/?text=${text}%20${url}`, '_blank');
    }
    
    function copyLink() {
      navigator.clipboard.writeText(window.location.href).then(() => {
        alert('Salon link copied to clipboard!');
      }).catch(err => {
        console.error('Failed to copy:', err);
      });
    }

    // Interactive Star Rating for Review Modal
    const starRatingInput = document.getElementById('starRatingInput');
    if (starRatingInput) {
      const stars = starRatingInput.querySelectorAll('i');
      const ratingText = document.getElementById('ratingText');
      const ratingLabels = ['', 'Poor (1 star)', 'Fair (2 stars)', 'Good (3 stars)', 'Very Good (4 stars)', 'Excellent (5 stars)'];
      let selectedRating = 0;

      stars.forEach(star => {
        star.addEventListener('click', () => {
          selectedRating = parseInt(star.dataset.rating, 10);
          document.getElementById('review_rating').value = selectedRating;
          updateStars(selectedRating);
          if (ratingText) ratingText.textContent = ratingLabels[selectedRating];
        });

        star.addEventListener('mouseenter', () => {
          const hoverRating = parseInt(star.dataset.rating, 10);
          updateStars(hoverRating);
          if (ratingText) ratingText.textContent = ratingLabels[hoverRating];
        });
      });

      starRatingInput.addEventListener('mouseleave', () => {
        updateStars(selectedRating);
        if (ratingText) ratingText.textContent = selectedRating > 0 ? ratingLabels[selectedRating] : 'Click on stars to rate';
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

    // Character counter for reviews
    const reviewComment = document.getElementById('review_comment');
    const charCount = document.getElementById('charCount');
    if (reviewComment && charCount) {
      reviewComment.addEventListener('input', () => {
        charCount.textContent = reviewComment.value.length;
      });
    }

    // Modal Trigger Buttons
    const reviewModalEl = document.getElementById('reviewModal');
    let reviewModal = null;
    if (reviewModalEl) {
      reviewModal = new bootstrap.Modal(reviewModalEl);
      document.getElementById('writeReviewBtn')?.addEventListener('click', () => reviewModal.show());
      document.getElementById('topWriteReviewBtn')?.addEventListener('click', () => reviewModal.show());
    }

    // AJAX Post Review Submission
    const reviewSubmit = document.getElementById('reviewSubmit');
    if (reviewSubmit) {
      reviewSubmit.addEventListener('click', function() {
        const submitBtn = this;
        const salonId = document.getElementById('review_salon_id').value;
        const csrfToken = document.getElementById('review_csrf_token').value;
        const rating = document.getElementById('review_rating').value;
        const comment = (document.getElementById('review_comment').value || '').trim();
        const msgEl = document.getElementById('review_msg');

        if (!rating || parseInt(rating, 10) < 1) {
          msgEl.className = 'alert alert-warning';
          msgEl.textContent = 'Please select a star rating (1 to 5).';
          msgEl.style.display = 'block';
          return;
        }

        if (comment.length < 10) {
          msgEl.className = 'alert alert-warning';
          msgEl.textContent = 'Please write at least 10 characters in your review.';
          msgEl.style.display = 'block';
          return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

        fetch('post_review.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: `salon_id=${encodeURIComponent(salonId)}&rating=${encodeURIComponent(rating)}&comment=${encodeURIComponent(comment)}&csrf_token=${encodeURIComponent(csrfToken)}`
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            msgEl.className = 'alert alert-success';
            msgEl.textContent = data.message || 'Review submitted successfully!';
            msgEl.style.display = 'block';
            setTimeout(() => {
              location.reload();
            }, 1200);
          } else {
            msgEl.className = 'alert alert-danger';
            msgEl.textContent = data.message || 'Failed to submit review.';
            msgEl.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Review';
          }
        })
        .catch(err => {
          msgEl.className = 'alert alert-danger';
          msgEl.textContent = 'An error occurred while submitting your review. Please try again.';
          msgEl.style.display = 'block';
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Review';
        });
      });
    }

    // Smooth Scrolling for section anchors
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        const targetId = this.getAttribute('href');
        if (targetId && targetId !== '#') {
          const targetEl = document.querySelector(targetId);
          if (targetEl) {
            e.preventDefault();
            targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }
      });
    });

    // Theme Toggle Handler
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const themeToggleIcon = document.getElementById('themeToggleIcon');

    function updateThemeUI() {
      const isDark = document.body.classList.contains('dark-mode') || document.documentElement.classList.contains('dark-mode');
      if (themeToggleIcon) {
        themeToggleIcon.className = isDark ? 'fas fa-sun me-1 text-warning' : 'fas fa-moon me-1';
      }
    }
    updateThemeUI();

    if (themeToggleBtn) {
      themeToggleBtn.addEventListener('click', () => {
        const isDark = !document.documentElement.classList.contains('dark-mode');
        document.documentElement.classList.toggle('dark-mode', isDark);
        document.body.classList.toggle('dark-mode', isDark);
        localStorage.setItem('salonora_theme', isDark ? 'dark' : 'light');
        updateThemeUI();
      });
    }
  </script>

  <!-- Leaflet Map JS -->
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
          attribution: '&copy; OpenStreetMap contributors',
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
