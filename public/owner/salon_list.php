<?php
// owner/salon_list.php

require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'owner') {
    header('Location: ../public/login.php');
    exit;
}
$salon_id = $_SESSION['id'];
$stmt = $pdo->prepare("SELECT * FROM salons WHERE salon_id = ?");
$stmt->execute([$salon_id]);
$salons = $stmt->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title>My Salons</title></head><body>
<h1>Your Salons</h1>
<a href="salon_add.php">Add new salon</a>
<ul>
<?php foreach ($salons as $s): ?>
  <li>
    <?=htmlspecialchars($s['name'])?> - <a href="salon_edit.php?id=<?=$s['salon_id']?>">Edit</a> |
    <a href="services.php?salon_id=<?=$s['salon_id']?>">Services</a> |
    <a href="appointments.php?salon_id=<?=$s['salon_id']?>">Appointments</a>
  </li>
<?php endforeach; ?>
</ul>
</body></html>
