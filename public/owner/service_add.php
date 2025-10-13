<?php
// public/owner/service_add.php
session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];
$salon_id = intval($_GET['salon_id'] ?? 0);

if (!$salon_id) die('Salon ID missing.');

// Ensure the salon belongs to the logged-in owner
$stmt = $pdo->prepare("SELECT * FROM salons WHERE id = ? AND owner_id = ?");
$stmt->execute([$salon_id, $owner_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$salon) die('Unauthorized or salon not found.');

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $duration = intval($_POST['duration'] ?? 0);

    if ($name === '') $errors[] = 'Service name is required.';
    if ($price === '' || !is_numeric($price)) $errors[] = 'Valid price is required.';
    if ($duration <= 0) $errors[] = 'Duration must be greater than 0.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO services (salon_id, name, price, duration) VALUES (?, ?, ?, ?)");
        $stmt->execute([$salon_id, $name, $price, $duration]);
        $success = 'Service added successfully.';
        $name = $price = $duration = '';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add Service - <?= htmlspecialchars($salon['name']) ?> | Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Add Service to: <?= htmlspecialchars($salon['name']) ?></h2>
    <div>
      <a href="services.php?salon_id=<?= $salon_id ?>" class="btn btn-secondary btn-sm">← Back to Services</a>
      <a href="../logout.php" class="btn btn-danger btn-sm">Logout</a>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <form method="post" class="card p-3">
    <div class="mb-3">
      <label class="form-label">Service Name</label>
      <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($name ?? '') ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Price (Rs.)</label>
      <input type="number" step="0.01" name="price" class="form-control" value="<?= htmlspecialchars($price ?? '') ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Duration (minutes)</label>
      <input type="number" name="duration" class="form-control" value="<?= htmlspecialchars($duration ?? '') ?>" required>
    </div>
    <button class="btn btn-primary">Add Service</button>
  </form>
</div>
</body>
</html>
