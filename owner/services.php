<?php
// owner/service_add.php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'owner') {
    header('Location: ../public/login.php');
    exit;
}
$salon_id = $_GET['salon_id'] ?? null;
if (!$salon_id) { die('Salon id required'); }
// Validate owner owns the salon (important)
$stmt = $pdo->prepare("SELECT * FROM salons WHERE salon_id = ? AND owner_id = ?");
$stmt->execute([$salon_id, $_SESSION['id']]);
if (!$stmt->fetch()) { die('Not authorized'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['service_name'] ?? '';
    $price = $_POST['price'] ?? 0;
    $duration = $_POST['duration'] ?? '';
    $stmt = $pdo->prepare("INSERT INTO services (salon_id, service_name, price, duration) VALUES (?, ?, ?, ?)");
    $stmt->execute([$salon_id, $name, $price, $duration]);
    header("Location: services.php?salon_id=$salon_id");
    exit;
}
?>
<form method="post">
  <input name="service_name" placeholder="Service name" required><br>
  <input name="price" placeholder="Price"><br>
  <input name="duration" placeholder="Duration"><br>
  <button type="submit">Add Service</button>
</form>
