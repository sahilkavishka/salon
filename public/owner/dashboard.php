<?php
// owner/dashboard.php

require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

require_once __DIR__ . '/../../config.php';

$owner_id = $_SESSION['id']; // consistent with login.php

// ✅ Fetch owner's salons
$stmt = $pdo->prepare("SELECT * FROM salons WHERE owner_id = ?");
$stmt->execute([$owner_id]);
$salons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Fetch recent appointments for owner's salons
$stmt = $pdo->prepare("
  SELECT a.*, s.name AS service_name, u.username AS customer_name, sal.name AS salon_name
  FROM appointments a
  JOIN services s ON a.service_id = s.id
  JOIN users u ON a.user_id = u.id
  JOIN salons sal ON a.salon_id = sal.id
  WHERE sal.owner_id = ?
  ORDER BY a.created_at DESC
  LIMIT 10
");
$stmt->execute([$owner_id]);
$recentAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Owner Dashboard - Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <h1 class="mb-3">Welcome, <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?> 👋</h1>

  <div class="mb-3">
    <a href="../logout.php" class="btn btn-danger btn-sm">Logout</a>
    <a href="salon_add.php" class="btn btn-success btn-sm">Add Salon</a>
  </div>

  <h3>Your Salons</h3>
  <?php if (empty($salons)): ?>
    <div class="alert alert-info">No salons found. <a href="salon_add.php" class="alert-link">Add your first salon</a>.</div>
  <?php else: ?>
    <ul class="list-group mb-4">
      <?php foreach ($salons as $salon): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <strong><?= htmlspecialchars($salon['name']) ?></strong>
          <div>
            <a href="salon_edit.php?id=<?= $salon['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
            <a href="services.php?salon_id=<?= $salon['id'] ?>" class="btn btn-sm btn-outline-success">Services</a>
            <a href="appointment_list.php?salon_id=<?= $salon['id'] ?>" class="btn btn-sm btn-outline-secondary">Appointments</a>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <h3>Recent Appointments</h3>
  <?php if (empty($recentAppointments)): ?>
    <div class="alert alert-secondary">No recent appointments found.</div>
  <?php else: ?>
    <table class="table table-striped">
      <thead>
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
        <?php foreach ($recentAppointments as $a): ?>
          <tr>
            <td><?= $a['appointment_id'] ?></td>
            <td><?= htmlspecialchars($a['salon_name']) ?></td>
            <td><?= htmlspecialchars($a['service_name']) ?></td>
            <td><?= htmlspecialchars($a['customer_name']) ?></td>
            <td><?= htmlspecialchars($a['appointment_date']) ?></td>
            <td><?= htmlspecialchars($a['appointment_time']) ?></td>
            <td><?= htmlspecialchars($a['status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
</body>
</html>
