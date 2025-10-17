<?php
session_start();
require_once '../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];
$service_id = intval($_GET['id'] ?? 0);

if ($service_id <= 0) {
    $_SESSION['flash_error'] = 'Service ID is missing.';
    header('Location: dashboard.php');
    exit;
}

// Fetch the service and ensure it belongs to a salon owned by this owner
$stmt = $pdo->prepare("
    SELECT svc.*, s.name AS salon_name
    FROM services svc
    JOIN salons s ON svc.salon_id = s.id
    WHERE svc.id = ? AND s.owner_id = ?
");
$stmt->execute([$service_id, $owner_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    $_SESSION['flash_error'] = 'Service not found or unauthorized.';
    header('Location: dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');

    if ($name === '') $errors[] = "Service name is required.";
    if ($price === '' || !is_numeric($price) || $price <= 0) $errors[] = "Valid price is required.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE services SET name=?, description=?, price=? WHERE id=?");
        $stmt->execute([$name, $description, $price, $service_id]);

        $_SESSION['flash_success'] = "Service updated successfully.";
        header("Location: services.php?salon_id={$service['salon_id']}");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Service - <?= htmlspecialchars($service['salon_name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
    <h2>Edit Service for <?= htmlspecialchars($service['salon_name']) ?></h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="card p-3">
        <div class="mb-3">
            <label class="form-label">Service Name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($service['name']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control"><?= htmlspecialchars($service['description']) ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Price</label>
            <input type="number" step="0.01" name="price" class="form-control" value="<?= htmlspecialchars($service['price']) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Update Service</button>
        <a href="services.php?salon_id=<?= $service['salon_id'] ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>
</body>
</html>
