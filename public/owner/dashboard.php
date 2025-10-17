<?php
// public/owner/dashboard.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];

// Fetch salons
$stmt = $pdo->prepare("SELECT id AS salon_id, name, address FROM salons WHERE owner_id = ?");
$stmt->execute([$owner_id]);
$salons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent appointments
$stmt = $pdo->prepare("
    SELECT 
        a.id AS appointment_id,
        s.name AS service_name,
        u.username AS customer_name,
        sa.name AS salon_name,
        a.appointment_date,
        a.appointment_time,
        a.status
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN users u ON a.user_id = u.id
    JOIN salons sa ON a.salon_id = sa.id
    WHERE sa.owner_id = ?
    ORDER BY a.created_at DESC
    LIMIT 10
");
$stmt->execute([$owner_id]);
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Owner Dashboard - Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Welcome, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Owner') ?> 👋</h2>
    <a href="../logout.php" class="btn btn-danger btn-sm">Logout</a>
  </div>

  <h4>Your Salons</h4>
  <?php if (empty($salons)): ?>
    <div class="alert alert-info">No salons yet. <a href="salon_add.php">Add one</a>.</div>
  <?php else: ?>
    <ul class="list-group mb-4">
      <?php foreach ($salons as $salon): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <div>
            <strong><?= htmlspecialchars($salon['name']) ?></strong><br>
            <small><?= htmlspecialchars($salon['address']) ?></small>
          </div>
          <div>
            <a class="btn btn-sm btn-outline-primary" href="salon_edit.php?id=<?= $salon['salon_id'] ?>">Edit</a>
            <a class="btn btn-sm btn-outline-success" href="services.php?salon_id=<?= $salon['salon_id'] ?>">Services</a>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <h4>Recent Appointments</h4>
  <?php if (empty($recent)): ?>
    <div class="alert alert-secondary">No recent appointments found.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead class="table-dark">
          <tr>
            <th>ID</th>
            <th>Salon</th>
            <th>Service</th>
            <th>Customer</th>
            <th>Date</th>
            <th>Time</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $a): 
              $badge = match($a['status']) {
                  'pending' => 'bg-warning text-dark',
                  'confirmed' => 'bg-success',
                  'rejected' => 'bg-danger',
                  default => 'bg-secondary'
              };
          ?>
            <tr>
              <td><?= (int)$a['appointment_id'] ?></td>
              <td><?= htmlspecialchars($a['salon_name']) ?></td>
              <td><?= htmlspecialchars($a['service_name']) ?></td>
              <td><?= htmlspecialchars($a['customer_name']) ?></td>
              <td><?= htmlspecialchars($a['appointment_date']) ?></td>
              <td><?= date('H:i', strtotime($a['appointment_time'])) ?></td>
              <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($a['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

</div>
</body>
</html>
