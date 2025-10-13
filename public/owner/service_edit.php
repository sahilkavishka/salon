<?php
// public/owner/salon_edit.php

session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];
$salon_id = intval($_GET['id'] ?? 0);
if (!$salon_id) {
    die('Salon ID missing.');
}

// verify salon belongs to this owner
$stmt = $pdo->prepare("SELECT * FROM salons WHERE id = ? AND owner_id = ?");
$stmt->execute([$salon_id, $owner_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$salon) {
    die('Salon not found or unauthorized.');
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '') $errors[] = 'Salon name is required.';
    if ($address === '') $errors[] = 'Salon address is required.';

    // handle optional image upload
    $newImagePath = $salon['image'] ?? null;
    if (!empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        $allowed = ['image/jpeg','image/png','image/gif'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload error.';
        } elseif (!in_array(mime_content_type($file['tmp_name']), $allowed, true)) {
            $errors[] = 'Only JPG/PNG/GIF allowed.';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safeName = 'uploads/salon_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $destDir = dirname(__DIR__, 2) . '/uploads';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            $destFull = dirname(__DIR__, 2) . '/' . $safeName;
            if (!move_uploaded_file($file['tmp_name'], $destFull)) {
                $errors[] = 'Failed to move uploaded file.';
            } else {
                $newImagePath = $safeName;
                // optional: delete old file if existed
                if (!empty($salon['image']) && file_exists(dirname(__DIR__, 2) . '/' . $salon['image'])) {
                    @unlink(dirname(__DIR__, 2) . '/' . $salon['image']);
                }
            }
        }
    }

    if (empty($errors)) {
        // Update statement (no created_at assumed)
        $stmt = $pdo->prepare("UPDATE salons SET name = ?, address = ?, image = ? WHERE id = ? AND owner_id = ?");
        $stmt->execute([$name, $address, $newImagePath, $salon_id, $owner_id]);

        $success = 'Salon updated successfully.';
        // refresh $salon variable
        $stmt = $pdo->prepare("SELECT * FROM salons WHERE id = ?");
        $stmt->execute([$salon_id]);
        $salon = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Edit Salon - Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Edit Salon</h2>
    <div>
      <a href="dashboard.php" class="btn btn-secondary btn-sm">← Dashboard</a>
      <a href="../logout.php" class="btn btn-danger btn-sm">Logout</a>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="card p-3">
    <div class="mb-3">
      <label class="form-label">Salon Name</label>
      <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($salon['name'] ?? '') ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Address</label>
      <textarea name="address" class="form-control" rows="3" required><?= htmlspecialchars($salon['address'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Current Image</label><br>
      <?php if (!empty($salon['image'])): ?>
        <img src="<?= htmlspecialchars('../../' . $salon['image']) ?>" style="max-width:200px;" class="img-thumbnail mb-2"><br>
      <?php else: ?>
        <div class="text-muted mb-2">No image uploaded.</div>
      <?php endif; ?>
      <label class="form-label">Replace Image (optional)</label>
      <input type="file" name="image" accept="image/*" class="form-control">
      <div class="form-text">JPG/PNG/GIF allowed.</div>
    </div>

    <button class="btn btn-primary">Save Changes</button>
  </form>
</div>
</body>
</html>
