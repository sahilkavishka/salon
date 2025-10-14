<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

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
  <title>All Salons - Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; }
    .salon-card { border-radius: 12px; overflow: hidden; transition: transform 0.3s, box-shadow 0.3s; }
    .salon-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
    .salon-img { width: 100%; height: 200px; object-fit: cover; }
    .card-body { min-height: 180px; display: flex; flex-direction: column; justify-content: space-between; }
  </style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>All Salons</h2>
    <div>
      <?php if (isset($_SESSION['id'])): ?>
        <a href="../logout.php" class="btn btn-danger btn-sm">Logout</a>
      <?php else: ?>
        <a href="../login.php" class="btn btn-primary btn-sm">Login</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (empty($salons)): ?>
    <div class="alert alert-info">No salons available.</div>
  <?php else: ?>
    <div class="row">
      <?php foreach ($salons as $salon): 
        $can_interact = in_array($logged_user_role, ['user', 'customer']) && $logged_user_id != $salon['owner_id'];
        $image_path = '../../' . ($salon['image'] ?: 'assets/img/default_salon.jpg');
      ?>
        <div class="col-md-4 mb-4">
          <div class="card salon-card shadow-sm">
            <img src="<?= htmlspecialchars($image_path) ?>" class="salon-img" alt="<?= htmlspecialchars($salon['name']) ?>">
            <div class="card-body">
              <div>
                <h5 class="card-title"><?= htmlspecialchars($salon['name']) ?></h5>
                <p class="card-text mb-2">
                  <strong>Address:</strong> <?= htmlspecialchars($salon['address']) ?><br>
                  <strong>Owner:</strong> <?= htmlspecialchars($salon['owner_name']) ?><br>
                  <strong>Services:</strong> <?= htmlspecialchars($salon['service_count']) ?>
                </p>
              </div>
              <div class="mt-2 d-flex flex-column gap-1">
                <a href="salon_details.php?id=<?= $salon['salon_id'] ?>" class="btn btn-outline-primary btn-sm">View Details</a>
                <?php if ($can_interact): ?>
                  <button class="btn btn-primary btn-sm" 
                          data-bs-toggle="modal" 
                          data-bs-target="#bookModal" 
                          data-salon="<?= $salon['salon_id'] ?>" 
                          data-name="<?= htmlspecialchars($salon['name']) ?>">
                    Book Appointment
                  </button>
                  <button class="btn btn-success btn-sm" 
                          data-bs-toggle="modal" 
                          data-bs-target="#reviewModal" 
                          data-salon="<?= $salon['salon_id'] ?>">
                    Write Review
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Book Appointment Modal -->
<div class="modal fade" id="bookModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="../book_appointment.php" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Book Appointment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="salon_id" id="modalSalonId">
        <p id="modalSalonName" class="fw-bold"></p>
        <div class="mb-2">
          <label>Date</label>
          <input type="date" name="appointment_date" class="form-control" required>
        </div>
        <div class="mb-2">
          <label>Time</label>
          <input type="time" name="appointment_time" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Book Now</button>
      </div>
    </form>
  </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="../post_review.php" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Write a Review</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="salon_id" id="reviewSalonId">
        <div class="mb-2">
          <label>Rating</label>
          <select name="rating" class="form-select" required>
            <option value="5">★★★★★</option>
            <option value="4">★★★★</option>
            <option value="3">★★★</option>
            <option value="2">★★</option>
            <option value="1">★</option>
          </select>
        </div>
        <div class="mb-2">
          <label>Comment</label>
          <textarea name="comment" class="form-control" rows="3" placeholder="Your review..." required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success">Submit Review</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Pass salon info to Book Modal
  var bookModalEl = document.getElementById('bookModal');
  bookModalEl.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var salonId = button.getAttribute('data-salon');
    var salonName = button.getAttribute('data-name');
    document.getElementById('modalSalonId').value = salonId;
    document.getElementById('modalSalonName').textContent = salonName;
  });

  // Pass salon ID to Review Modal
  var reviewModalEl = document.getElementById('reviewModal');
  reviewModalEl.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var salonId = button.getAttribute('data-salon');
    document.getElementById('reviewSalonId').value = salonId;
  });
</script>
</body>
</html>
