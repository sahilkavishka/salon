<?php
// owner/service_add.php
session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'owner') {
    header('Location: ../public/login.php');
    exit;
}

$owner_id = $_SESSION['id'];
$salon_id = intval($_GET['salon_id'] ?? 0);
if (!$salon_id) die('Salon ID required.');

// verify ownership
$stmt = $pdo->prepare("SELECT * FROM salons WHERE salon_id = ? AND owner_id = ?");
$stmt->execute([$salon_id, $owner_id]);
if (!$stmt->fetch()) die('Not authorized to add service for this salon.');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_name = trim($_POST['service_name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $duration = trim($_POST['duration'] ?? '');

    if (!$service_name) $errors[] = "Service name required.";

    if (empty($errors)) {
        $i = $pdo->prepare("INSERT INTO services (salon_id, service_name, price, duration) VALUES (?, ?, ?, ?)");
        $i->execute([$salon_id, $service_name, $price, $duration]);
        header("Location: services.php?salon_id=$salon_id");
        exit;
    }
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Add Service</title></head>
<body>
  <h1>Add Service for Salon #<?=htmlspecialchars($salon_id)?></h1>
  <?php if ($errors) foreach ($errors as $e) echo "<p style='color:red;'>$e</p>"; ?>
  <form method="post">
    <label>Service name</label><br>
    <input name="service_name" required><br>
    <label>Price</label><br>
    <input name="price" type="number" step="0.01"><br>
    <label>Duration (e.g., 30 mins)</label><br>
    <input name="duration"><br><br>
    <button type="submit">Add Service</button>
  </form>
  <p><a href="services.php?salon_id=<?=$salon_id?>">Back to services</a></p>
</body>
</html>
