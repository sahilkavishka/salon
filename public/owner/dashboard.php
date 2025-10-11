<?php
// owner/dashboard.php

session_start();
require_once __DIR__ . '/../../config.php';

// ✅ Check if logged in and owner
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'owner') {
    header('Location: ../login.php');
    exit;
}

$owner_id = $_SESSION['id'];

// ✅ Fetch salons owned by this owner
$stmt = $pdo->prepare("SELECT id AS salon_id, name, address FROM salons WHERE owner_id = ?");
$stmt->execute([$owner_id]);
$salons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Fetch recent appointments (no time column)
$stmt = $pdo->prepare("
    SELECT 
        a.id AS appointment_id,
        s.name AS service_name,
        u.username AS customer_name,
        sa.name AS salon_name,
        a.appointment_date,
        a.status
    FROM appointments a
    INNER JOIN services s ON a.service_id = s.id
    INNER JOIN users u ON a.user_id = u.id
    INNER JOIN salons sa ON a.salon_id = sa.id
    WHERE sa.owner_id = ?
    ORDER BY a.created_at DESC
    LIMIT 10
");
$stmt->execute([$owner_id]);
$recentAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

  <h1 class="mb-3">Welcome, <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?> 👋</h1>

  <div class="mb-3">
    <a href="../logout.php" class="btn btn-danger btn-sm">Logout</a>
    <a href="salon_add.php" class="btn btn-success btn-sm">Add Salon</a>
  </div>

  <!-- ✅ Your Salons -->
  <h3>Your Salons</h3>
  <?php if (empty($salons)): ?>
    <div class="alert alert-info">No salons found. <a href="salon_add.php" class="alert-link">Add your first salon</a>.</div>
  <?php else: ?>
    <ul class="list-group mb-4">
      <?php foreach ($salons as $salon): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <div>
            <strong><?= htmlspecialchars($salon['name']) ?></strong><br>
            <small class="text-muted"><?= htmlspecialchars($salon['address']) ?></small>
          </div>
          <div>
            <a href="salon_edit.php?id=<?= $salon['salon_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
            <a href="services.php?salon_id=<?= $salon['salon_id'] ?>" class="btn btn-sm btn-outline-success">Services</a>
            <a href="appointment_list.php?salon_id=<?= $salon['salon_id'] ?>" class="btn btn-sm btn-outline-secondary">Appointments</a>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <!-- ✅ Recent Appointments -->
  <h3>Recent Appointments</h3>
  <?php if (empty($recentAppointments)): ?>
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
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentAppointments as $a): ?>
            <tr>
              <td><?= htmlspecialchars($a['appointment_id']) ?></td>
              <td><?= htmlspecialchars($a['salon_name']) ?></td>
              <td><?= htmlspecialchars($a['service_name']) ?></td>
              <td><?= htmlspecialchars($a['customer_name']) ?></td>
              <td><?= htmlspecialchars($a['appointment_date']) ?></td>
              <td><span class="badge bg-info text-dark"><?= htmlspecialchars($a['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

</div>
</body>
</html>
