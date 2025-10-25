<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

$salon_id = intval($_GET['id'] ?? 0);
if ($salon_id <= 0) die("Invalid salon ID.");

// Fetch salon info
$stmt = $pdo->prepare("SELECT s.*, u.username AS owner_name FROM salons s LEFT JOIN users u ON s.owner_id = u.id WHERE s.id = ?");
$stmt->execute([$salon_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$salon) die("Salon not found.");

// Fetch services
$stmt = $pdo->prepare("SELECT * FROM services WHERE salon_id = ?");
$stmt->execute([$salon_id]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch reviews
$stmt = $pdo->prepare("SELECT r.*, u.username FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.salon_id = ? ORDER BY r.created_at DESC");
$stmt->execute([$salon_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate average rating
$avg_rating = 0;
if (!empty($reviews)) {
    $total = array_sum(array_column($reviews, 'rating'));
    $avg_rating = round($total / count($reviews), 1);
}

// Can interact?
$user_can_interact = isset($_SESSION['role'], $_SESSION['id']) &&
                     in_array($_SESSION['role'], ['user', 'customer']) &&
                     $_SESSION['id'] != $salon['owner_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($salon['name']) ?> - Salonora</title>
  
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

    .btn-back {
      background: white;
      color: var(--primary);
      border: 2px solid var(--primary);
      padding: 0.75rem 1.5rem;
      border-radius: 50px;
      font-weight: 600;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .btn-back:hover {
      background: var(--primary);
      color: white;
      transform: translateX(-5px);
    }

    /* Hero Section */
    .salon-hero {
      position: relative;
      height: 400px;
      background: var(--gradient-primary);
      overflow: hidden;
      margin-bottom: -80px;
    }

    .salon-hero-bg {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-size: cover;
      background-position: center;
      opacity: 0.3;
    }

    .salon-hero::after {
      content: '';
      position: absolute;
      bottom: -1px;
      left: 0;
      right: 0;
      height: 100px;
      background: var(--light);
      border-radius: 50% 50% 0 0 / 100% 100% 0 0;
    }

    /* Salon Info Card */
    .salon-info-card {
      position: relative;
      z-index: 10;
      background: white;
      border-radius: 24px;
      box-shadow: var(--shadow-xl);
      overflow: hidden;
      margin-bottom: 3rem;
    }

    .salon-header {
      display: flex;
      align-items: center;
      gap: 2rem;
      padding: 2rem;
      background: linear-gradient(135deg, rgba(233, 30, 99, 0.05) 0%, rgba(156, 39, 176, 0.05) 100%);
    }

    .salon-image {
      width: 200px;
      height: 200px;
      border-radius: 20px;
      object-fit: cover;
      box-shadow: var(--shadow-lg);
      border: 5px solid white;
    }

    .salon-details h1 {
      font-size: 2.5rem;
      font-weight: 800;
      color: var(--text-dark);
      margin-bottom: 0.5rem;
    }

    .salon-meta {
      display: flex;
      gap: 2rem;
      margin-top: 1rem;
      flex-wrap: wrap;
    }

    .meta-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--text-light);
    }

    .meta-item i {
      color: var(--primary);
      font-size: 1.1rem;
    }

    .meta-item strong {
      color: var(--text-dark);
    }

    .rating-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: var(--gradient-primary);
      color: white;
      padding: 0.5rem 1.5rem;
      border-radius: 50px;
      font-weight: 700;
      font-size: 1.1rem;
      box-shadow: var(--shadow-md);
    }

    .rating-badge i {
      color: #ffd700;
    }

    /* Section Styling */
    .section {
      margin-bottom: 3rem;
    }

    .section-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 2rem;
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

    .section-badge {
      background: rgba(233, 30, 99, 0.1);
      color: var(--primary);
      padding: 0.5rem 1.25rem;
      border-radius: 50px;
      font-weight: 600;
      font-size: 0.9rem;
    }

    /* Services Grid */
    .services-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.5rem;
    }

    .service-card {
      background: white;
      border-radius: 20px;
      padding: 2rem;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(0, 0, 0, 0.05);
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

    .service-icon {
      width: 60px;
      height: 60px;
      background: linear-gradient(135deg, rgba(233, 30, 99, 0.1) 0%, rgba(156, 39, 176, 0.1) 100%);
      border-radius: 15px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1rem;
    }

    .service-icon i {
      font-size: 1.5rem;
      background: var(--gradient-primary);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
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
    }

    .service-detail {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--text-light);
      font-size: 0.95rem;
    }

    .service-detail i {
      color: var(--primary);
    }

    .service-detail strong {
      color: var(--text-dark);
      font-weight: 600;
    }

    .btn-book {
      width: 100%;
      background: var(--gradient-primary);
      color: white;
      border: none;
      padding: 0.9rem;
      border-radius: 12px;
      font-weight: 600;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .btn-book:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
      color: white;
    }

    /* Reviews Section */
    .reviews-container {
      background: white;
      border-radius: 20px;
      padding: 2rem;
      box-shadow: var(--shadow-sm);
    }

    .review-item {
      padding: 1.5rem 0;
      border-bottom: 1px solid #e9ecef;
    }

    .review-item:last-child {
      border-bottom: none;
    }

    .review-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1rem;
    }

    .review-user {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .user-avatar {
      width: 50px;
      height: 50px;
      background: var(--gradient-primary);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 700;
      font-size: 1.2rem;
    }

    .user-info h6 {
      margin: 0;
      font-weight: 700;
      color: var(--text-dark);
    }

    .review-stars {
      color: #ffa500;
      font-size: 1.1rem;
    }

    .review-content {
      color: var(--text-light);
      line-height: 1.6;
      margin-bottom: 0.5rem;
    }

    .review-date {
      font-size: 0.85rem;
      color: var(--text-light);
    }

    .btn-write-review {
      background: var(--gradient-primary);
      color: white;
      border: none;
      padding: 1rem 2.5rem;
      border-radius: 50px;
      font-weight: 700;
      font-size: 1.1rem;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 0.75rem;
      box-shadow: var(--shadow-md);
    }

    .btn-write-review:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(233, 30, 99, 0.4);
      color: white;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 3rem;
      color: var(--text-light);
    }

    .empty-state i {
      font-size: 4rem;
      color: #dfe6e9;
      margin-bottom: 1rem;
    }

    /* Modal Styling */
    .modal-content {
      border: none;
      border-radius: 24px;
      box-shadow: var(--shadow-xl);
    }

    .modal-header {
      background: var(--gradient-primary);
      color: white;
      border-radius: 24px 24px 0 0;
      padding: 1.5rem 2rem;
      border: none;
    }

    .modal-title {
      font-weight: 700;
      font-size: 1.5rem;
    }

    .btn-close {
      filter: brightness(0) invert(1);
    }

    .modal-body {
      padding: 2rem;
    }

    .form-label {
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 0.5rem;
    }

    .form-control,
    .form-select {
      border: 2px solid #e9ecef;
      border-radius: 12px;
      padding: 0.9rem 1rem;
      transition: var(--transition);
      font-size: 1rem;
    }

    .form-control:focus,
    .form-select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
      outline: none;
    }

    .modal-footer {
      border: none;
      padding: 1.5rem 2rem;
      gap: 1rem;
    }

    .btn-modal-primary {
      background: var(--gradient-primary);
      color: white;
      border: none;
      padding: 0.9rem 2rem;
      border-radius: 50px;
      font-weight: 700;
      transition: var(--transition);
    }

    .btn-modal-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
    }

    .btn-modal-secondary {
      background: #e9ecef;
      color: var(--text-dark);
      border: none;
      padding: 0.9rem 2rem;
      border-radius: 50px;
      font-weight: 700;
      transition: var(--transition);
    }

    .btn-modal-secondary:hover {
      background: #dee2e6;
    }

    .success-msg {
      background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
      color: white;
      padding: 1rem;
      border-radius: 12px;
      margin-top: 1rem;
      font-weight: 600;
      display: none;
    }

    .success-msg.show {
      display: block;
      animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Responsive */
    @media (max-width: 768px) {
      .salon-header {
        flex-direction: column;
        text-align: center;
      }

      .salon-details h1 {
        font-size: 2rem;
      }

      .salon-meta {
        justify-content: center;
      }

      .services-grid {
        grid-template-columns: 1fr;
      }

      .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
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
    </div>
  </nav>

  <!-- Hero Section -->
  <div class="salon-hero">
    <?php if ($salon['image']): ?>
      <div class="salon-hero-bg" style="background-image: url('../../<?= htmlspecialchars($salon['image']) ?>');"></div>
    <?php endif; ?>
  </div>

  <div class="container pb-5">
    <!-- Back Button -->
    <div class="mb-4">
      <a href="salon_view.php" class="btn-back">
        <i class="fas fa-arrow-left"></i> Back to Salons
      </a>
    </div>

    <!-- Salon Info Card -->
    <div class="salon-info-card">
      <div class="salon-header">
        <?php if ($salon['image']): ?>
          <img src="../../<?= htmlspecialchars($salon['image']) ?>" class="salon-image" alt="<?= htmlspecialchars($salon['name']) ?>">
        <?php endif; ?>
        <div class="salon-details flex-grow-1">
          <h1><?= htmlspecialchars($salon['name']) ?></h1>
          <div class="rating-badge">
            <i class="fas fa-star"></i>
            <?= $avg_rating ?> / 5.0
          </div>
          <div class="salon-meta">
            <div class="meta-item">
              <i class="fas fa-map-marker-alt"></i>
              <span><?= htmlspecialchars($salon['address']) ?></span>
            </div>
            <div class="meta-item">
              <i class="fas fa-user-tie"></i>
              <span>Owner: <strong><?= htmlspecialchars($salon['owner_name']) ?></strong></span>
            </div>
            <div class="meta-item">
              <i class="fas fa-concierge-bell"></i>
              <span><strong><?= count($services) ?></strong> Services</span>
            </div>
            <div class="meta-item">
              <i class="fas fa-comments"></i>
              <span><strong><?= count($reviews) ?></strong> Reviews</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Services Section -->
    <div class="section" id="services">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-cut"></i>
          Available Services
        </h2>
        <span class="section-badge"><?= count($services) ?> Services</span>
      </div>

      <?php if (empty($services)): ?>
        <div class="empty-state">
          <i class="fas fa-spa"></i>
          <h4>No Services Available</h4>
          <p>This salon hasn't added any services yet.</p>
        </div>
      <?php else: ?>
        <div class="services-grid">
          <?php foreach ($services as $s): ?>
            <div class="service-card">
              <div class="service-icon">
                <i class="fas fa-scissors"></i>
              </div>
              <h5 class="service-name"><?= htmlspecialchars($s['name']) ?></h5>
              <div class="service-details">
                <div class="service-detail">
                  <i class="fas fa-tag"></i>
                  <span>Rs <strong><?= htmlspecialchars($s['price']) ?></strong></span>
                </div>
                <div class="service-detail">
                  <i class="far fa-clock"></i>
                  <span><strong><?= htmlspecialchars($s['duration']) ?></strong> mins</span>
                </div>
              </div>
              <?php if ($user_can_interact): ?>
                <button class="btn btn-book book-btn" 
                        data-service="<?= $s['id'] ?>" 
                        data-service-name="<?= htmlspecialchars($s['name']) ?>">
                  <i class="fas fa-calendar-check"></i> Book Now
                </button>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Reviews Section -->
    <div class="section">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-star"></i>
          Customer Reviews
        </h2>
        <span class="section-badge"><?= count($reviews) ?> Reviews</span>
      </div>

      <div class="reviews-container">
        <?php if (empty($reviews)): ?>
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
              </div>
              <p class="review-content"><?= nl2br(htmlspecialchars($r['comment'])) ?></p>
              <small class="review-date">
                <i class="far fa-calendar"></i> <?= date('M d, Y', strtotime($r['created_at'])) ?>
              </small>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($user_can_interact): ?>
          <div class="text-center mt-4">
            <button class="btn-write-review" id="writeReviewBtn">
              <i class="fas fa-pen"></i> Write a Review
            </button>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Book Appointment Modal -->
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
          <div class="mb-3">
            <label class="form-label">Service</label>
            <input type="text" id="book_service_name" class="form-control" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Appointment Date</label>
            <input type="date" id="appointment_date" class="form-control" required min="<?= date('Y-m-d') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Appointment Time</label>
            <input type="time" id="appointment_time" class="form-control" required>
          </div>
          <div id="book_msg" class="success-msg"></div>
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

  <!-- Review Modal -->
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
          <div class="mb-3">
            <label class="form-label">Your Rating</label>
            <select id="review_rating" class="form-select" required>
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
            <textarea id="review_comment" class="form-control" rows="5" placeholder="Share your experience with this salon..." required></textarea>
          </div>
          <div id="review_msg" class="success-msg"></div>
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

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Open Book Modal
    document.querySelectorAll('.book-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('book_service_id').value = btn.dataset.service;
        document.getElementById('book_service_name').value = btn.dataset.serviceName;
        new bootstrap.Modal(document.getElementById('bookModal')).show();
      });
    });

    // AJAX Book Appointment
    document.getElementById('bookSubmit').addEventListener('click', () => {
      let salonId = document.getElementById('book_salon_id').value;
      let serviceId = document.getElementById('book_service_id').value;
      let date = document.getElementById('appointment_date').value;
      let time = document.getElementById('appointment_time').value;

      if(!date || !time) { 
        alert('Please select date and time'); 
        return; 
      }

      fetch('../book_appointment.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `salon_id=${salonId}&service_id=${serviceId}&appointment_date=${date}&appointment_time=${time}`
      }).then(res => res.text()).then(data => {
        const msgEl = document.getElementById('book_msg');
        msgEl.textContent = data;
        msgEl.classList.add('show');
        setTimeout(() => {
          location.reload();
        }, 1500);
      }).catch(err => {
        alert('Booking failed. Please try again.');
      });
    });

    // Open Review Modal
    document.getElementById('writeReviewBtn')?.addEventListener('click', () => {
      new bootstrap.Modal(document.getElementById('reviewModal')).show();
    });

    // AJAX Post Review
    document.getElementById('reviewSubmit').addEventListener('click', () => {
      let salonId = document.getElementById('review_salon_id').value;
      let rating = document.getElementById('review_rating').value;
      let comment = document.getElementById('review_comment').value.trim();

      if(!rating) { 
        alert('Please select a rating'); 
        return; 
      }

      if(!comment) { 
        alert('Please write a comment'); 
        return; 
      }

      fetch('../post_review.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `salon_id=${salonId}&rating=${rating}&comment=${encodeURIComponent(comment)}`
      }).then(res => res.text()).then(data => {
        const msgEl = document.getElementById('review_msg');
        msgEl.textContent = data;
        msgEl.classList.add('show');
        setTimeout(() => {
          location.reload();
        }, 1500);
      }).catch(err => {
        alert('Review submission failed. Please try again.');
      });
    });

    // Smooth scroll to services
    if(window.location.hash === '#services') {
      document.getElementById('services').scrollIntoView({ behavior: 'smooth' });
    }
  </script>
</body>
</html>