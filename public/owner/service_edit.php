<?php
// public/owner/service_edit.php
session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];
$service_id = intval($_GET['id'] ?? 0);
$salon_id = intval($_GET['salon_id'] ?? 0);

if (!$service_id || !$salon_id) die('Missing parameters.');

// Verify service belongs to salon and salon belongs to owner
$stmt = $pdo->prepare("
  SELECT s.*, sal.name AS salon_name 
  FROM services s
  JOIN salons sal ON s.salon_id = sal.id
  WHERE s.id = ? AND sal.owner_id = ?
");
$stmt->execute([$service_id, $owner_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$service) die('Unauthorized access or service not found.');

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $duration = intval($_POST['duration'] ?? 0);

    if ($name === '') $errors[] = 'Service name is required.';
    if ($price === '' || !is_numeric($price)) $errors[] = 'Valid price is required.';
    if ($duration <= 0) $errors[] = 'Duration must be greater than 0.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE services SET name=?, price=?, duration=? WHERE id=?");
        $stmt->execute([$name, $price, $duration, $service_id]);
        $success = 'Service updated successfully.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Edit Service - <?= htmlspecialchars($service['salon_name']) ?> | Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Edit Service (<?= htmlspecialchars($service['salon_name']) ?>)</h2>
    <div>
      <a href="services.php?s
