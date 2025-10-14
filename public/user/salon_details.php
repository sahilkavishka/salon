<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

$salon_id = intval($_GET['id'] ?? 0);
if ($salon_id <= 0) die("Invalid salon ID.");

// Fetch salon info
$stmt = $pdo->prepare("
    SELECT s.*, u.username AS owner_name
    FROM salons s
    LEFT JOIN users u ON s.owner_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$salon_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$salon) die("Salon not found.");

// Fetch services
$stmt = $pdo->prepare("SELECT * FROM services WHERE salon_id = ?");
$stmt->execute([$salon_id]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch reviews
$stmt = $pdo->prepare("
    SELECT r.*, u.username 
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.salon_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$salon_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine if logged-in user can interact
$logged_user_id = $_SESSION['id'] ?? 0;
$logged_user_role = $_SESSION['role'] ?? '';
$can_interact = in_array($logged_user_role, ['user', 'customer']) && $logged_user_id != $salon['owner_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($salon['name']) ?> - Details</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <a href="salon_view.php" class="btn btn-secondary mb-3">&larr; Back</a>

  <h2><?= htmlspecialchars($salon['name']) ?></h2>
  <p><strong>Owner:</strong> <?= htmlspecialchars($salon['owner_name']) ?></p>
  <p><strong>Address:</strong> <?= htmlspecialchars($salon['address']) ?></p>

  <img src="<?= htmlspecialchars('../../' . ($salon['image'] ?: 'assets/img/default_salon.jpg')) ?>" 
       class="rounded mb-3" style="max-width:400px;">

  <h4 id="services">Services</h4>
  <?php if (empty($services)): ?>
    <div class="alert alert-info">No services available.</div>
  <?php else: ?>
    <div class="row">
      <?php foreach ($services as $s): ?>
        <div class="col-md-4 mb-3">
          <div class="card p-3">
            <h5><?= htmlspecialchars($s['name']) ?></h5>
            <p>Price: Rs <?= htmlspecialchars($s['price']) ?> | <?= htmlspecialchars($s['duration']) ?> mins</p>

            <?php if ($can_interact): ?>
              <form method="post" action="../../book_appointment.php">
                <input type="hidden" name="salon_id" value="<?= $salon_id ?>">
                <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
                <input type="date" name="appointment_date" class="form-control mb-2" required>
                <input type="time" name="appointment_time" class="form-control mb-2" required>
                <button class="btn btn-primary btn-sm w-100">Book</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h4 class="mt-4">Customer Reviews</h4>
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

  <?php if ($can_interact): ?>
    <h5 class="mt-4">Write a Review</h5>
    <form method="post" action="../../post_review.php" class="card p-3">
      <input type="hidden" name="salon_id" value="<?= $salon_id ?>">
      <select name="rating" class="form-select mb-2" required>
        <option value="5">★★★★★</option>
        <option value="4">★★★★</option>
        <option value="3">★★★</option>
        <option value="2">★★</option>
        <option value="1">★</option>
      </select>
      <textarea name="comment" class="form-control mb-2" placeholder="Your review..." required></textarea>
      <button class="btn btn-success btn-sm">Submit Review</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
