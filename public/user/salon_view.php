<?php
// public/user/salon_view.php
session_start();
require_once __DIR__ . '/../../config.php';

$salon_id = intval($_GET['id'] ?? 0);
if (!$salon_id) {
    die('Salon ID required.');
}

// 🔹 Fetch salon details
$stmt = $pdo->prepare("SELECT * FROM salons WHERE id = ?");
$stmt->execute([$salon_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$salon) die('Salon not found.');

// 🔹 Fetch salon services
$stmt = $pdo->prepare("SELECT * FROM services WHERE salon_id = ?");
$stmt->execute([$salon_id]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 🔹 Fetch reviews
$stmt = $pdo->prepare("
    SELECT r.*, u.username AS user_name 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.salon_id = ? 
    ORDER BY r.created_at DESC
");
$stmt->execute([$salon_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($salon['name']) ?> - Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <a href="../index.php" class="btn btn-secondary btn-sm mb-3">← Back to Home</a>

  <h2><?= htmlspecialchars($salon['name']) ?></h2>
  <p><?= htmlspecialchars($salon['address']) ?></p>

  <?php if (!empty($salon['image'])): ?>
    <img src="../../<?= htmlspecialchars($salon['image']) ?>" class="img-fluid mb-3" style="max-width:400px;">
  <?php endif; ?>

  <h4>Services</h4>
  <?php if (empty($services)): ?>
    <div class="alert alert-info">No services listed yet.</div>
  <?php else: ?>
    <ul class="list-group mb-4">
      <?php foreach ($services as $s): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <div>
            <?= htmlspecialchars($s['name']) ?> — Rs.<?= htmlspecialchars($s['price']) ?> / <?= htmlspecialchars($s['duration']) ?> mins
          </div>
          <?php if (isset($_SESSION['id'])): ?>
            <form method="post" action="../book_appointment.php" class="d-flex">
              <input type="hidden" name="salon_id" value="<?= $salon_id ?>">
              <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
              <input type="date" name="appointment_date" class="form-control form-control-sm me-2" required>
              <input type="time" name="appointment_time" class="form-control form-control-sm me-2" required>
              <button type="submit" class="btn btn-primary btn-sm">Book</button>
            </form>
          <?php else: ?>
            <a href="../login.php" class="btn btn-outline-primary btn-sm">Login to Book</a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <h4>Reviews</h4>
  <?php if (empty($reviews)): ?>
    <div class="alert alert-secondary">No reviews yet.</div>
  <?php else: ?>
    <?php foreach ($reviews as $r): ?>
      <div class="card mb-2">
        <div class="card-body">
          <strong><?= htmlspecialchars($r['user_name']) ?></strong> — ⭐ <?= htmlspecialchars($r['rating']) ?>/5
          <p class="mt-2 mb-1"><?= nl2br(htmlspecialchars($r['comment'])) ?></p>
          <small class="text-muted"><?= htmlspecialchars($r['created_at']) ?></small>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['id'])): ?>
    <h5 class="mt-4">Leave a Review</h5>
    <form method="post" action="../post_review.php" class="card p-3">
      <input type="hidden" name="salon_id" value="<?= $salon_id ?>">
      <div class="mb-2">
        <label class="form-label">Rating</label>
        <select name="rating" class="form-select form-select-sm w-25">
          <?php for ($i=5; $i>=1; $i--): ?>
            <option><?= $i ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="mb-2">
        <label class="form-label">Comment</label>
        <textarea name="comment" class="form-control" rows="3"></textarea>
      </div>
      <button class="btn btn-success btn-sm">Submit Review</button>
    </form>
  <?php else: ?>
    <p><a href="../login.php">Login</a> to leave a review.</p>
  <?php endif; ?>
</div>
</body>
</html>
