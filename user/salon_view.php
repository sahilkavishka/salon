<?php
// user/salon_view.php
session_start();
require_once __DIR__ . '/../includes/config.php';

$salon_id = intval($_GET['id'] ?? 0);
if (!$salon_id) { die('Salon id required.'); }

// fetch salon + services + reviews
$stmt = $pdo->prepare("SELECT * FROM salons WHERE salon_id = ?");
$stmt->execute([$salon_id]);
$salon = $stmt->fetch();
if (!$salon) die('Salon not found.');

$stmt = $pdo->prepare("SELECT * FROM services WHERE salon_id = ?");
$stmt->execute([$salon_id]);
$services = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT r.*, u.name as user_name FROM reviews r JOIN users u ON r.id = u.id WHERE r.salon_id = ? ORDER BY r.created_at DESC");
$stmt->execute([$salon_id]);
$reviews = $stmt->fetchAll();

?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title><?=htmlspecialchars($salon['name'])?></title></head>
<body>
  <a href="../public/index.php">Back to search</a>
  <h1><?=htmlspecialchars($salon['name'])?></h1>
  <p><?=htmlspecialchars($salon['address'])?></p>
  <?php if ($salon['image']): ?>
    <img src="../<?=htmlspecialchars($salon['image'])?>" style="max-width:300px;"><br>
  <?php endif; ?>

  <h2>Services</h2>
  <?php if (!$services): ?>
    <p>No services listed.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($services as $s): ?>
        <li>
          <?=htmlspecialchars($s['service_name'])?> — <?=htmlspecialchars($s['duration'])?> — <?=htmlspecialchars($s['price'])?>
          <?php if (isset($_SESSION['id'])): ?>
            <form method="post" action="../public/book_appointment.php" style="display:inline;">
              <input type="hidden" name="salon_id" value="<?=$salon['salon_id']?>">
              <input type="hidden" name="service_id" value="<?=$s['service_id']?>">
              <input type="date" name="appointment_date" required>
              <input type="time" name="appointment_time" required>
              <button type="submit">Book</button>
            </form>
          <?php else: ?>
            <a href="../public/login.php">Login to book</a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <h2>Reviews</h2>
  <?php if (!$reviews): ?>
    <p>No reviews yet.</p>
  <?php else: ?>
    <?php foreach ($reviews as $r): ?>
      <div style="border-bottom:1px solid #ccc;padding:6px 0;">
        <strong><?=htmlspecialchars($r['user_name'])?></strong> — <?=$r['rating']?>/5<br>
        <p><?=nl2br(htmlspecialchars($r['comment']))?></p>
        <small><?=htmlspecialchars($r['created_at'])?></small>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['id'])): ?>
    <h3>Post a review</h3>
    <form method="post" action="../public/post_review.php">
      <input type="hidden" name="salon_id" value="<?=$salon['salon_id']?>">
      <label>Rating</label>
      <select name="rating">
        <option>5</option><option>4</option><option>3</option><option>2</option><option>1</option>
      </select><br>
      <label>Comment</label><br>
      <textarea name="comment" rows="4"></textarea><br>
      <button type="submit">Submit Review</button>
    </form>
  <?php else: ?>
    <p><a href="../public/login.php">Login</a> to leave a review.</p>
  <?php endif; ?>
</body>
</html>
