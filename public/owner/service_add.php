<?php
session_start();
require_once '../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];
$salon_id = intval($_GET['salon_id'] ?? 0);

if ($salon_id <= 0) {
    $_SESSION['flash_error'] = 'Salon not specified.';
    header('Location: dashboard.php');
    exit;
}

// Verify the salon belongs to the owner
$stmt = $pdo->prepare("SELECT id, name FROM salons WHERE id=? AND owner_id=?");
$stmt->execute([$salon_id, $owner_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$salon) {
    $_SESSION['flash_error'] = 'Salon not found or not authorized.';
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');

    if ($name === '') $errors[] = "Service name is required.";
    if ($price === '' || !is_numeric($price) || $price <= 0) $errors[] = "Valid price is required.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO services (salon_id, name, description, price) VALUES (?, ?, ?, ?)");
        $stmt->execute([$salon_id, $name, $description, $price]);

        $_SESSION['flash_success'] = "Service added successfully.";
        header("Location: services.php?salon_id=$salon_id");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Service - <?= htmlspecialchars($salon['name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
    <h2>Add Service for <?= htmlspecialchars($salon['name']) ?></h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="card p-3">
        <div class="mb-3">
            <label class="form-label">Service Name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Price</label>
            <input type="number" step="0.01" name="price" class="form-control" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Add Service</button>
        <a href="services.php?salon_id=<?= $salon_id ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>
</body>
</html>
