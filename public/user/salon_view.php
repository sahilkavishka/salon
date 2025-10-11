<?php
// user/salon_view.php
require_once __DIR__ . '/../../config.php';
session_start();

$salon_id = (int)($_GET['id'] ?? 0);
if (!$salon_id) die('Salon ID required.');

// ✅ Fetch salon info
$stmt = $pdo->prepare("SELECT * FROM salons WHERE salon_id = ?");
$stmt->execute([$salon_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$salon) die('Salon not found.');

// ✅ Fetch services
$stmt = $pdo->prepare("SELECT * FROM services WHERE salon_id = ?");
$stmt->execute([$salon_id]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Fetch reviews
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
  <title><?= htmlspecialchars($salon['name']) ?> | Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <a href="../index.php" class="btn btn-secondary btn-sm mb-3">⬅ Back to Search</a>
  <h2><?= htmlspecialchars($salon['name']) ?></h2>
  <p><?= htmlspecialchars($salon['address'] ?? '') ?></p>

  <?php if ($salon['image']): ?>
    <img src="../<?= htmlspecialchars($salon['image']) ?>" class="img-thumbnail mb-3" style="max-width:300px;">
  <?php endif; ?>

  <h4>💇 Services</h4>
  <?php if (!$services): ?>
    <p>No services listed yet.</p>
  <?php else: ?>
    <ul class="list-group mb-4">
      <?php foreach ($services as $s): ?>
        <li class="list-group-item">
          <strong><?= htmlspecialchars($s['service_name']) ?></strong> — <?= htmlspecialchars($s['duration']) ?> — Rs. <?= htmlspecialchars($s['price']) ?>
          <?php if (isset($_SESSION['user_id'])): ?>
            <form method="post" action="../book_appointment.php" class="d-inline ms-3">
              <input type="hidden" name="salon_id" value="<?= $salon['salon_id'] ?>">
              <input type="hidden" name="service_id" value="<?= $s['service_id'] ?>">
              <input type="date" name="appointment_date" required>
              <input type="time" name="appointment_time" required>
              <button type="submit" class="btn btn-sm btn-primary">Book</button>
            </form>
          <?php else: ?>
            <a href="../login.php" class="btn btn-sm btn-outline-primary ms-3">Login to Book</a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <h4>⭐ Reviews</h4>
  <?php if (!$reviews): ?>
    <p>No reviews yet.</p>
  <?php else: ?>
    <?php foreach ($reviews as $r): ?>
      <div class="border-bottom py-2">
        <strong><?= htmlspecialchars($r['user_name']) ?></strong> — <?= htmlspecialchars($r['rating']) ?>/5<br>
        <p class="mb-1"><?= nl2br(htmlspecialchars($r['comment'])) ?></p>
        <small class="text-muted"><?= htmlspecialchars($r['created_at']) ?></small>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['user_id'])): ?>
    <h5 class="mt-4">✍ Leave a Review</h5>
    <form method="post" action="../post_review.php">
      <input type="hidden" name="salon_id" value="<?= $salon['salon_id'] ?>">
      <div class="mb-2">
        <label>Rating</label>
        <select name="rating" class="form-select w-auto d-inline">
          <option>5</option><option>4</option><option>3</option><option>2</option><option>1</option>
        </select>
      </div>
      <div class="mb-3">
        <label>Comment</label>
        <textarea name="comment" class="form-control" rows="3"></textarea>
      </div>
      <button type="submit" class="btn btn-success">Submit Review</button>
    </form>
  <?php else: ?>
    <p class="mt-3"><a href="../login.php">Login</a> to leave a review.</p>
  <?php endif; ?>
</div>
</body>
</html>
