<?php
// owner/salon_edit.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'owner') {
    header('Location: /salonora/public/login.php');
    exit;
}

$owner_id = $_SESSION['user_id'];
$salon_id = intval($_GET['id'] ?? 0);

if (!$salon_id) {
    $_SESSION['error'] = 'Salon ID is required.';
    header('Location: salon_list.php');
    exit;
}

// Verify owner owns salon
$stmt = $pdo->prepare("SELECT * FROM salons WHERE salon_id = ? AND owner_id = ?");
$stmt->execute([$salon_id, $owner_id]);
$salon = $stmt->fetch();

if (!$salon) {
    $_SESSION['error'] = 'Salon not found or access denied.';
    header('Location: salon_list.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;

    // Validation
    if (empty($name)) {
        $errors[] = "Salon name is required.";
    }
    if (strlen($name) > 255) {
        $errors[] = "Salon name must be less than 255 characters.";
    }

    // Handle image upload
    $imagePath = $salon['image'];
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $targetDir = __DIR__ . '/uploads/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        $filename = time() . '_' . uniqid() . '_' . basename($_FILES['image']['name']);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $errors[] = "Only JPG, JPEG, PNG, GIF, and WebP files are allowed.";
        } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) { // 5MB limit
            $errors[] = "Image size must be less than 5MB.";
        } else {
            $tmp = $_FILES['image']['tmp_name'];
            if (move_uploaded_file($tmp, $targetDir . $filename)) {
                // Delete old image if it exists and is not the default
                if ($imagePath && !str_contains($imagePath, 'default_')) {
                    $oldImagePath = __DIR__ . '/../' . $imagePath;
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
                $imagePath = 'owner/uploads/' . $filename;
            } else {
                $errors[] = "Image upload failed. Please try again.";
            }
        }
    } elseif ($_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = "File upload error: " . $_FILES['image']['error'];
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE salons SET name=?, address=?, contact=?, description=?, latitude=?, longitude=?, image=?, updated_at=NOW() WHERE salon_id=? AND owner_id=?");
            $stmt->execute([$name, $address, $contact, $description, $latitude, $longitude, $imagePath, $salon_id, $owner_id]);
            
            $_SESSION['success'] = 'Salon updated successfully!';
            header("Location: salon_list.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Display session messages
if (isset($_SESSION['error'])) {
    $errors[] = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Salon - <?= htmlspecialchars($salon['name']) ?></title>
    <style>
        .error { color: red; margin: 10px 0; }
        .success { color: green; margin: 10px 0; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        textarea { height: 100px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .current-image { margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Edit Salon: <?= htmlspecialchars($salon['name']) ?></h1>
    
    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="success">
            <p><?= htmlspecialchars($success) ?></p>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="name">Salon Name *</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($salon['name']) ?>" required maxlength="255">
        </div>

        <div class="form-group">
            <label for="address">Address</label>
            <input type="text" id="address" name="address" value="<?= htmlspecialchars($salon['address'] ?? '') ?>" maxlength="500">
        </div>

        <div class="form-group">
            <label for="latitude">Latitude</label>
            <input type="number" id="latitude" name="latitude" step="any" value="<?= htmlspecialchars($salon['latitude'] ?? '') ?>" placeholder="e.g., 40.7128">
        </div>

        <div class="form-group">
            <label for="longitude">Longitude</label>
            <input type="number" id="longitude" name="longitude" step="any" value="<?= htmlspecialchars($salon['longitude'] ?? '') ?>" placeholder="e.g., -74.0060">
        </div>

        <div class="form-group">
            <label for="contact">Contact Information</label>
            <input type="text" id="contact" name="contact" value="<?= htmlspecialchars($salon['contact'] ?? '') ?>" maxlength="255">
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" maxlength="1000"><?= htmlspecialchars($salon['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="image">Salon Image</label>
            <?php if ($salon['image']): ?>
                <div class="current-image">
                    <p>Current Image:</p>
                    <img src="../<?= htmlspecialchars($salon['image']) ?>" alt="Current salon image" style="max-width: 200px; height: auto;">
                </div>
            <?php endif; ?>
            <input type="file" id="image" name="image" accept="image/*">
            <small>Supported formats: JPG, PNG, GIF, WebP (Max 5MB)</small>
        </div>

        <div class="form-group">
            <button type="submit">Save Changes</button>
            <a href="salon_list.php" style="margin-left: 10px;">Cancel</a>
        </div>
    </form>
</body>
</html>