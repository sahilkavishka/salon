<?php
// public/owner/salon_add.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '') $errors[] = 'Salon name is required.';
    if ($address === '') $errors[] = 'Salon address is required.';

    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        $allowed = ['image/jpeg','image/png','image/gif'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload error.';
        } elseif (!in_array(mime_content_type($file['tmp_name']), $allowed, true)) {
            $errors[] = 'Only JPG, PNG or GIF images allowed.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $safe = 'uploads/salon_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;

            $destDir = __DIR__ . '/../../uploads';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);

            $destFull = __DIR__ . '/../../' . $safe;
            if (!move_uploaded_file($file['tmp_name'], $destFull)) {
                $errors[] = 'Failed to save uploaded image.';
            } else {
                $imagePath = $safe;
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO salons (owner_id, name, address, image) VALUES (?, ?, ?, ?)");
        $stmt->execute([$owner_id, $name, $address, $imagePath]);
        $_SESSION['flash_success'] = 'Salon added successfully.';
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add Salon - Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Add New Salon</h2>
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

  <form method="post" enctype="multipart/form-data" class="card p-3">
    <div class="mb-3">
      <label class="form-label">Salon Name</label>
      <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($name ?? '') ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Address</label>
      <textarea name="address" class="form-control" rows="3" required><?= htmlspecialchars($address ?? '') ?></textarea>
    </div>
    <div class="mb-3">
      <label class="form-label">Image (optional)</label>
      <input type="file" name="image" accept="image/*" class="form-control">
      <div class="form-text">JPG, PNG or GIF. Max size depends on PHP settings.</div>
    </div>
    <button class="btn btn-primary">Add Salon</button>
  </form>
</div>
</body>
</html>
