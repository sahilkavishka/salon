<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth(); // anyone logged in

// ✅ Fetch all salons
$stmt = $pdo->query("
    SELECT 
        s.id AS salon_id,
        s.name,
        s.address,
        s.image,
        u.username AS owner_name,
        COUNT(sr.id) AS service_count
    FROM salons s
    LEFT JOIN users u ON s.owner_id = u.id
    LEFT JOIN services sr ON s.id = sr.salon_id
    GROUP BY s.id
    ORDER BY s.name ASC
");
$salons = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>All Salons - Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; }
    .salon-card { border-radius: 12px; overflow: hidden; transition: transform 0.2s; }
    .salon-card:hover { transform: translateY(-5px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    .salon-img { width: 100%; height: 180px; object-fit: cover; }
  </style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
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
    <div class="alert alert-info">No salons available at the moment.</div>
  <?php else: ?>
    <div class="row">
      <?php foreach ($salons as $salon): ?>
        <div class="col-md-4 mb-4">
          <div class="card salon-card">
            <img src="../../<?= htmlspecialchars($salon['image'] ?: 'assets/img/default_salon.jpg') ?>" class="salon-img" alt="<?= htmlspecialchars($salon['name']) ?>">
            <div class="card-body">
              <h5 class="card-title"><?= htmlspecialchars($salon['name']) ?></h5>
              <p class="card-text mb-1">
                <strong>Address:</strong> <?= htmlspecialchars($salon['address']) ?><br>
                <strong>Owner:</strong> <?= htmlspecialchars($salon['owner_name']) ?><br>
                <strong>Services:</strong> <?= htmlspecialchars($salon['service_count']) ?>
              </p>
              <a href="salon_details.php?id=<?= $salon['salon_id'] ?>" class="btn btn-outline-primary btn-sm">View Details</a>
              <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'customer'): ?>
                <a href="salon_details.php?id=<?= $salon['salon_id'] ?>#services" class="btn btn-primary btn-sm">Book Now</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
