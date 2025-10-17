<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];
$salon_id = intval($_GET['salon_id'] ?? 0);

if (!$salon_id) {
    $_SESSION['flash_error'] = 'Salon ID missing.';
    header('Location: dashboard.php');
    exit;
}

// Verify salon belongs to the owner
$stmt = $pdo->prepare("SELECT id, name FROM salons WHERE id=? AND owner_id=?");
$stmt->execute([$salon_id, $owner_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$salon) {
    $_SESSION['flash_error'] = 'Unauthorized access or salon not found.';
    header('Location: dashboard.php');
    exit;
}

// Handle delete action
if (isset($_GET['delete'])) {
    $sid = intval($_GET['delete']);
    if ($sid > 0) {
        $del = $pdo->prepare("DELETE FROM services WHERE id=? AND salon_id=?");
        $del->execute([$sid, $salon_id]);
        $_SESSION['flash_success'] = 'Service deleted successfully.';
        header("Location: services.php?salon_id=$salon_id");
        exit;
    }
}

// Fetch services for this salon
$stmt = $pdo->prepare("SELECT * FROM services WHERE salon_id=? ORDER BY id DESC");
$stmt->execute([$salon_id]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Services - <?= htmlspecialchars($salon['name']) ?> | Salonora</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Services for: <?= htmlspecialchars($salon['name']) ?></h2>
        <div>
            <a href="dashboard.php" class="btn btn-secondary btn-sm">← Dashboard</a>
            <a href="service_add.php?salon_id=<?= $salon_id ?>" class="btn btn-primary btn-sm">+ Add Service</a>
            <a href="../logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <?php if (empty($services)): ?>
        <div class="alert alert-info">No services found. Click “Add Service” to create one.</div>
    <?php else: ?>
        <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Price (Rs.)</th>
                    <th>Duration (mins)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $s): ?>
                    <tr>
                        <td><?= (int)$s['id'] ?></td>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td><?= htmlspecialchars($s['price']) ?></td>
                        <td><?= htmlspecialchars($s['duration'] ?? '-') ?></td>
                        <td>
                            <a href="service_edit.php?id=<?= $s['id'] ?>&salon_id=<?= $salon_id ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                            <a href="services.php?salon_id=<?= $salon_id ?>&delete=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this service?');">Delete</a>
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
