<?php
// owner/dashboard.php

require_once __DIR__ . '/../config.php'; // provides $pdo and constants

// Security: only owners
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'owner') {
    header('Location: ../public/login.php');
    exit;
}

$owner_id = $_SESSION['id'];

// Fetch owner's salons
$stmt = $pdo->prepare("SELECT * FROM salons WHERE owner_id = ?");
$stmt->execute([$owner_id]);
$salons = $stmt->fetchAll();

// Fetch recent appointments for owner's salons
$stmt = $pdo->prepare("
  SELECT a.*, s.service_name, u.name AS customer_name, sal.name AS salon_name
  FROM appointments a
  JOIN services s ON a.service_id = s.service_id
  JOIN users u ON a.id = u.id
  JOIN salons sal ON a.salon_id = sal.salon_id
  WHERE sal.owner_id = ?
  ORDER BY a.created_at DESC
  LIMIT 10
");
$stmt->execute([$owner_id]);
$recentAppointments = $stmt->fetchAll();

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Owner Dashboard - Salonora</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <h1>Dashboard — Welcome <?=htmlspecialchars($_SESSION['user_name'] ?? '')?></h1>
  <p><a href="../public/logout.php">Logout</a> | <a href="salon_add.php">Add Salon</a></p>

  <h2>Your Salons</h2>
  <?php if (count($salons) === 0): ?>
    <p>No salons found. <a href="salon_add.php">Add your first salon</a>.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($salons as $salon): ?>
        <li>
          <strong><?=htmlspecialchars($salon['name'])?></strong>
          — <a href="salon_edit.php?id=<?=$salon['salon_id']?>">Edit</a>
          | <a href="services.php?salon_id=<?=$salon['salon_id']?>">Services</a>
          | <a href="appointment_list.php?salon_id=<?=$salon['salon_id']?>">Appointments</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <h2>Recent Appointments</h2>
  <?php if (!$recentAppointments): ?>
    <p>No recent appointments.</p>
  <?php else: ?>
    <table border="1" cellpadding="6">
      <tr><th>ID</th><th>Salon</th><th>Service</th><th>Customer</th><th>Date</th><th>Time</th><th>Status</th></tr>
      <?php foreach ($recentAppointments as $a): ?>
        <tr>
          <td><?=$a['appointment_id']?></td>
          <td><?=htmlspecialchars($a['salon_name'])?></td>
          <td><?=htmlspecialchars($a['service_name'])?></td>
          <td><?=htmlspecialchars($a['customer_name'])?></td>
          <td><?=htmlspecialchars($a['appointment_date'])?></td>
          <td><?=htmlspecialchars($a['appointment_time'])?></td>
          <td><?=htmlspecialchars($a['status'])?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</body>
</html>
