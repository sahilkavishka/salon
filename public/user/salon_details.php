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

// Can interact?
$user_can_interact = isset($_SESSION['role'], $_SESSION['id']) &&
                     in_array($_SESSION['role'], ['user', 'customer']) &&
                     $_SESSION['id'] != $salon['owner_id'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($salon['name']) ?> - Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; }
    .service-card { border-radius: 12px; transition: transform 0.2s, box-shadow 0.2s; }
    .service-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
  </style>
</head>
<body>
<div class="container py-4">

  <a href="salon_view.php" class="btn btn-secondary mb-3">&larr; Back</a>

  <h2><?= htmlspecialchars($salon['name']) ?></h2>
  <p><strong>Owner:</strong> <?= htmlspecialchars($salon['owner_name']) ?></p>
  <p><strong>Address:</strong> <?= htmlspecialchars($salon['address']) ?></p>
  <?php if ($salon['image']): ?>
    <img src="../../<?= htmlspecialchars($salon['image']) ?>" class="img-fluid rounded mb-3" style="max-width:400px;">
  <?php endif; ?>

  <h4 id="services">Services</h4>
  <?php if (empty($services)): ?>
    <div class="alert alert-info">No services available.</div>
  <?php else: ?>
    <div class="row">
      <?php foreach ($services as $s): ?>
        <div class="col-md-4 mb-3">
          <div class="card p-3 service-card shadow-sm">
            <h5><?= htmlspecialchars($s['name']) ?></h5>
            <p>Price: Rs <?= htmlspecialchars($s['price']) ?> | <?= htmlspecialchars($s['duration']) ?> mins</p>
            <?php if ($user_can_interact): ?>
              <button class="btn btn-primary btn-sm w-100 mb-1 book-btn" 
                      data-service="<?= $s['id'] ?>" 
                      data-service-name="<?= htmlspecialchars($s['name']) ?>">
                Book Now
              </button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h4 class="mt-4">Customer Reviews</h4>
  <div id="reviews">
    <?php if (empty($reviews)): ?>
      <div class="alert alert-secondary">No reviews yet.</div>
    <?php else: ?>
      <?php foreach ($reviews as $r): ?>
        <div class="border-bottom py-2">
          <strong><?= htmlspecialchars($r['username']) ?></strong> ⭐ <?= htmlspecialchars($r['rating']) ?>/5
          <p><?= nl2br(htmlspecialchars($r['comment'])) ?></p>
          <small class="text-muted"><?= htmlspecialchars($r['created_at']) ?></small>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php if ($user_can_interact): ?>
    <button class="btn btn-success mt-3" id="writeReviewBtn">Write a Review</button>
  <?php endif; ?>

</div>

<!-- Book Appointment Modal -->
<div class="modal fade" id="bookModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Book Appointment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="book_salon_id" value="<?= $salon_id ?>">
        <input type="hidden" id="book_service_id">
        <p id="book_service_name" class="fw-bold"></p>
        <div class="mb-2">
          <label>Date</label>
          <input type="date" id="appointment_date" class="form-control" required>
        </div>
        <div class="mb-2">
          <label>Time</label>
          <input type="time" id="appointment_time" class="form-control" required>
        </div>
        <div id="book_msg" class="text-success"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="bookSubmit" class="btn btn-primary">Book Now</button>
      </div>
    </div>
  </div>
</div>
<?php
// salon_details.php (excerpt) - make sure session started earlier and config included
// Example variables assumed: $salon['id'], $services = array of services with id & name/price
?>

<!-- Booking form (simple, posts to book_appointment.php) -->
<h3>Request Appointment</h3>
<form action="/public/user/book_appointment.php" method="post" id="bookForm">
    <input type="hidden" name="salon_id" value="<?php echo htmlspecialchars($salon['id']); ?>">

    <label for="service_id">Select Service</label>
    <select name="service_id" id="service_id" required>
        <option value="">-- Choose service --</option>
        <?php foreach ($services as $s): ?>
            <option value="<?php echo (int)$s['id']; ?>">
                <?php echo htmlspecialchars($s['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="appointment_date">Date</label>
    <input type="date" id="appointment_date" name="appointment_date" required min="<?php echo date('Y-m-d'); ?>">

    <label for="appointment_time">Time</label>
    <input type="time" id="appointment_time" name="appointment_time" required>

    <button type="submit">Request Appointment</button>
</form>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Write a Review</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="review_salon_id" value="<?= $salon_id ?>">
        <div class="mb-2">
          <label>Rating</label>
          <select id="review_rating" class="form-select" required>
            <option value="5">★★★★★</option>
            <option value="4">★★★★</option>
            <option value="3">★★★</option>
            <option value="2">★★</option>
            <option value="1">★</option>
          </select>
        </div>
        <div class="mb-2">
          <label>Comment</label>
          <textarea id="review_comment" class="form-control" rows="3" placeholder="Your review..." required></textarea>
        </div>
        <div id="review_msg" class="text-success"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="reviewSubmit" class="btn btn-success">Submit Review</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Open Book Modal
  document.querySelectorAll('.book-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('book_service_id').value = btn.dataset.service;
      document.getElementById('book_service_name').textContent = btn.dataset.serviceName;
      new bootstrap.Modal(document.getElementById('bookModal')).show();
    });
  });

  // AJAX Book Appointment
  document.getElementById('bookSubmit').addEventListener('click', () => {
    let salonId = document.getElementById('book_salon_id').value;
    let serviceId = document.getElementById('book_service_id').value;
    let date = document.getElementById('appointment_date').value;
    let time = document.getElementById('appointment_time').value;

    if(!date || !time) { alert('Select date and time'); return; }

    fetch('../book_appointment.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: `salon_id=${salonId}&service_id=${serviceId}&appointment_date=${date}&appointment_time=${time}`
    }).then(res => res.text()).then(data => {
      document.getElementById('book_msg').textContent = data;
      setTimeout(()=>location.reload(), 1500); // reload after success
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

    if(!comment) { alert('Write a comment'); return; }

    fetch('../post_review.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: `salon_id=${salonId}&rating=${rating}&comment=${encodeURIComponent(comment)}`
    }).then(res => res.text()).then(data => {
      document.getElementById('review_msg').textContent = data;
      setTimeout(()=>location.reload(), 1500); // reload after success
    });
  });
</script>
</body>
</html>
