<?php
// public/owner/salon_edit.php

session_start();
require_once __DIR__ . '/../../config.php';          // ✅ include DB connection ($pdo)
require_once __DIR__ . '/../auth_check.php'; 
checkAuth('owner');

// ensure owner logged in
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'owner') {
    header('Location: ../login.php');
    exit;
}

$owner_id = $_SESSION['id'];
$salon_id = intval($_GET['id'] ?? 0);

if ($salon_id <= 0) {
    $_SESSION['error'] = 'Salon ID is missing.';
    header('Location: dashboard.php');
    exit;
}

// ✅ fetch salon owned by this owner
$stmt = $pdo->prepare("SELECT * FROM salons WHERE id = ? AND owner_id = ?");
$stmt->execute([$salon_id, $owner_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$salon) {
    $_SESSION['error'] = 'Salon not found or not authorized.';
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $imagePath = $salon['image'] ?? null;

    if ($name === '') {
        $errors[] = "Salon name is required.";
    }

    // ✅ handle image upload
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $errors[] = "Invalid image type.";
        } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            $errors[] = "Image size exceeds 5MB.";
        } else {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $newName = 'salon_' . time() . '_' . uniqid() . '.' . $ext;
            $uploadPath = $uploadDir . $newName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                $imagePath = 'owner/uploads/' . $newName;

                // remove old image
                if (!empty($salon['image']) && file_exists(__DIR__ . '/../../' . $salon['image'])) {
                    @unlink(__DIR__ . '/../../' . $salon['image']);
                }
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE salons SET name=?, address=?, image=? WHERE id=? AND owner_id=?");
        $stmt->execute([$name, $address, $imagePath, $salon_id, $owner_id]);

        $_SESSION['success'] = 'Salon updated successfully.';
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Salon</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <h2>Edit Salon</h2>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e): ?>
        <?= htmlspecialchars($e) ?><br>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="card p-3">
    <div class="mb-3">
      <label class="form-label">Name</label>
      <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($salon['name']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Address</label>
      <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($salon['address']) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Current Image</label><br>
      <?php if ($salon['image']): ?>
        <img src="../../<?= htmlspecialchars($salon['image']) ?>" width="150" class="mb-2">
      <?php else: ?>
        <p>No image uploaded</p>
      <?php endif; ?>
      <input type="file" name="image" class="form-control" accept="image/*">
    </div>
    <button type="submit" class="btn btn-primary">Save Changes</button>
    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
  </form>
</div>
</body>
</html>
