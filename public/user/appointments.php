<?php
// public/user/appointments.php

session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('customer'); // only customers can view their appointments

$user_id = $_SESSION['id'];

// ✅ fetch user appointments
$stmt = $pdo->prepare("
    SELECT 
        a.id AS appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        s.name AS service_name,
        sal.name AS salon_name,
        sal.address AS salon_address
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN salons sal ON a.salon_id = sal.id
    WHERE a.user_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([$user_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Appointments - Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>My Appointments</h2>
    <a href="../logout.php" class="btn btn-danger btn-sm">Logout</a>
  </div>

  <?php if (empty($appointments)): ?>
    <div class="alert alert-info">You have no appointments yet. <a href="../index.php">Book one</a>!</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-bordered table-striped">
        <thead class="table-dark">
          <tr>
            <th>ID</th>
            <th>Salon</th>
            <th>Service</th>
            <th>Date</th>
            <th>Time</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($appointments as $a): ?>
          <tr>
            <td><?= $a['appointment_id'] ?></td>
            <td>
              <strong><?= htmlspecialchars($a['salon_name']) ?></strong><br>
              <small><?= htmlspecialchars($a['salon_address']) ?></small>
            </td>
            <td><?= htmlspecialchars($a['service_name']) ?></td>
            <td><?= htmlspecialchars($a['appointment_date']) ?></td>
            <td><?= htmlspecialchars($a['appointment_time']) ?></td>
            <td>
              <?php
                $statusClass = match(strtolower($a['status'])) {
                  'confirmed' => 'bg-success',
                  'cancelled' => 'bg-danger',
                  'completed' => 'bg-secondary',
                  default => 'bg-warning text-dark'
                };
              ?>
              <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($a['status']) ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
