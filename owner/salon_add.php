<?php
// owner/salon_add.php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header('Location: ../public/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $owner_id = $_SESSION['user_id'];
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $contact = $_POST['contact'] ?? '';
    $description = $_POST['description'] ?? '';
    $imagePath = null;

    // handle image upload (optional)
    if (!empty($_FILES['image']['name'])) {
        $targetDir = __DIR__ . '/uploads/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        $filename = time() . '_' . basename($_FILES['image']['name']);
        $targetFile = $targetDir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $imagePath = 'owner/uploads/' . $filename; // relative path
        }
    }

    $stmt = $pdo->prepare("INSERT INTO salons (owner_id, name, address, latitude, longitude, contact, description, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$owner_id, $name, $address, $latitude, $longitude, $contact, $description, $imagePath]);
    header('Location: salon_list.php');
    exit;
}
?>
<!-- HTML form minimal -->
<!doctype html><html><head><meta charset="utf-8"><title>Add Salon</title></head><body>
<form method="post" enctype="multipart/form-data">
  <input name="name" placeholder="Salon name" required><br>
  <input name="address" placeholder="Address"><br>
  <input name="latitude" placeholder="Latitude"><br>
  <input name="longitude" placeholder="Longitude"><br>
  <input name="contact" placeholder="Contact"><br>
  <textarea name="description" placeholder="Description"></textarea><br>
  <input type="file" name="image"><br>
  <button type="submit">Add Salon</button>
</form>
</body></html>
