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
  <link rel="stylesheet" href="../assets/css/salon_details.css">
  
  
</head>
<body>
  
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