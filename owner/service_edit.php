<?php
// owner/service_edit.php
session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'owner') {
    header('Location: ../public/login.php');
    exit;
}
$owner_id = $_SESSION['id'];
$service_id = intval($_GET['id'] ?? 0);
if (!$service_id) die('Service id required.');

// fetch service and salon
$stmt = $pdo->prepare("
  SELECT sv.*, s.owner_id
  FROM services sv
  JOIN salons s ON sv.salon_id = s.salon_id
  WHERE sv.service_id = ?
");
$stmt->execute([$service_id]);
$row = $stmt->fetch();
if (!$row) die('Service not found.');
if ($row['owner_id'] != $owner_id) die('Not authorized.');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_name = trim($_POST['service_name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $duration = trim($_POST['duration'] ?? '');

    if (!$service_name) $errors[] = "Service name required.";

    if (empty($errors)) {
        $u = $pdo->prepare("UPDATE services SET service_name=?, price=?, duration=? WHERE service_id=?");
        $u->execute([$service_name, $price, $duration, $service_id]);
        header("Location: services.php?salon_id=" . intval($row['salon_id']));
        exit;
    }
}

?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Edit Service</title></head>
<body>
  <h1>Edit Service</h1>
  <?php if ($errors) foreach ($errors as $e) echo "<p style='color:red;'>$e</p>"; ?>
  <form method="post">
    <label>Service name</label><br>
    <input name="service_name" value="<?=htmlspecialchars($row['service_name'])?>" required><br>
    <label>Price</label><br>
    <input name="price" type="number" step="0.01" value="<?=htmlspecialchars($row['price'])?>"><br>
    <label>Duration</label><br>
    <input name="duration" value="<?=htmlspecialchars($row['duration'])?>"><br><br>
    <button type="submit">Save</button>
  </form>
  <p><a href="services.php?salon_id=<?=$row['salon_id']?>">Back to services</a></p>
</body>
</html>
