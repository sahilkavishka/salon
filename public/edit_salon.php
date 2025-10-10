<?php
session_start();
require '../includes/config.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "owner") {
    die("Access denied.");
}

$owner_id = $_SESSION['user_id'];

if (!isset($_GET['id'])) {
    die("Salon ID missing.");
}

$salon_id = (int)$_GET['id'];

// Fetch salon details
$stmt = $pdo->prepare("SELECT * FROM salons WHERE id = ? AND owner_id = ?");
$stmt->execute([$salon_id, $owner_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$salon) {
    die("Salon not found.");
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['name'];
    $address = $_POST['address'];
    $type = $_POST['type'];

    // Handle logo upload
    $logo = $salon['logo'];
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
        $logoFile = $_FILES['logo'];
        $ext = pathinfo($logoFile['name'], PATHINFO_EXTENSION);
        $newLogoName = 'logo_' . time() . '.' . $ext;
        move_uploaded_file($logoFile['tmp_name'], "../uploads/" . $newLogoName);

        // Delete old logo
        if ($logo && file_exists("../uploads/" . $logo)) unlink("../uploads/" . $logo);
        $logo = $newLogoName;
    }

    // Handle multiple images
    $existingImages = json_decode($salon['images'], true) ?: [];
    $newImages = $existingImages;

    if (isset($_FILES['images'])) {
        foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['images']['error'][$key] === 0) {
                $ext = pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION);
                $newName = 'img_' . time() . '_' . $key . '.' . $ext;
                move_uploaded_file($tmpName, "../uploads/" . $newName);
                $newImages[] = $newName;
            }
        }
    }

    // Handle removing selected images
    if (isset($_POST['remove_images'])) {
        foreach ($_POST['remove_images'] as $rmImg) {
            $rmImg = basename($rmImg);
            if (($key = array_search($rmImg, $newImages)) !== false) {
                unset($newImages[$key]);
                if (file_exists("../uploads/".$rmImg)) unlink("../uploads/".$rmImg);
            }
        }
        $newImages = array_values($newImages); // reindex array
    }

    $imagesJson = json_encode($newImages);

    // Update salon in database
    $stmt = $pdo->prepare("UPDATE salons SET name = ?, address = ?, type = ?, logo = ?, images = ? WHERE id = ? AND owner_id = ?");
    if ($stmt->execute([$name, $address, $type, $logo, $imagesJson, $salon_id, $owner_id])) {
        header("Location: manage_salons.php");
        exit;
    } else {
        $error = "Error updating salon.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Salon</title>
<link rel="stylesheet" href="assets/css/edit_salon.css">
</head>
<body>

<h2>Edit Salon</h2>

<?php if(!empty($error)) echo "<p class='error'>".htmlspecialchars($error)."</p>"; ?>

<form method="post" enctype="multipart/form-data">
    <label for="name">Salon Name</label>
    <input type="text" name="name" id="name" value="<?= htmlspecialchars($salon['name']) ?>" required>

    <label for="address">Address</label>
    <input type="text" name="address" id="address" value="<?= htmlspecialchars($salon['address']) ?>" required>

    <label for="type">Type</label>
    <select name="type" id="type">
        <option value="beauty" <?= $salon['type']=='beauty'?'selected':'' ?>>Beauty</option>
        <option value="barber" <?= $salon['type']=='barber'?'selected':'' ?>>Barber</option>
        <option value="spa" <?= $salon['type']=='spa'?'selected':'' ?>>Spa</option>
    </select>

    <label for="logo">Salon Logo</label><br>
    <?php if($salon['logo'] && file_exists("../uploads/".$salon['logo'])): ?>
        <img src="../uploads/<?= htmlspecialchars($salon['logo']) ?>" alt="Logo"><br>
    <?php endif; ?>
    <input type="file" name="logo" id="logo">

    <label for="images">Salon Images (multiple allowed)</label><br>
    <?php 
    $images = json_decode($salon['images'], true) ?: [];
    foreach($images as $img): 
        if(file_exists("../uploads/".$img)):
    ?>
        <div>
            <input type="checkbox" class="remove-checkbox" name="remove_images[]" value="<?= htmlspecialchars($img) ?>"> Remove
            <img src="../uploads/<?= htmlspecialchars($img) ?>" alt="Salon Image">
        </div>
    <?php 
        endif; 
    endforeach; 
    ?>
    <input type="file" name="images[]" id="images" multiple>

    <button type="submit">Update Salon</button>
</form>

</body>
</html>
