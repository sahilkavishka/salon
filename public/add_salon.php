<?php
session_start();
require '../includes/config.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "owner") {
    die("Access denied.");
}

$owner_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['name'];
    $address = $_POST['address'];
    $type = $_POST['type'];

    // Handle logo upload
    $logo = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
        $logoFile = $_FILES['logo'];
        $ext = pathinfo($logoFile['name'], PATHINFO_EXTENSION);
        $logo = 'logo_' . time() . '.' . $ext;
        move_uploaded_file($logoFile['tmp_name'], "../uploads/" . $logo);
    }

    // Handle multiple images
    $images = [];
    if (isset($_FILES['images'])) {
        foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['images']['error'][$key] === 0) {
                $ext = pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION);
                $newName = 'img_' . time() . '_' . $key . '.' . $ext;
                move_uploaded_file($tmpName, "../uploads/" . $newName);
                $images[] = $newName;
            }
        }
    }
    $imagesJson = json_encode($images);

    // Insert into database
    $stmt = $pdo->prepare("INSERT INTO salons (owner_id, name, address, type, logo, images) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$owner_id, $name, $address, $type, $logo, $imagesJson])) {
        header("Location: manage_salons.php");
        exit;
    } else {
        $error = "Error adding salon.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Salon</title>
<link rel="stylesheet" href="assets/css/add_salon.css">
</head>
<body>

<h2>Add New Salon</h2>

<?php if(!empty($error)) echo "<p class='error'>".htmlspecialchars($error)."</p>"; ?>

<form method="post" enctype="multipart/form-data">
    <label for="name">Salon Name</label>
    <input type="text" name="name" id="name" required>

    <label for="address">Address</label>
    <input type="text" name="address" id="address" required>

    <label for="type">Type</label>
    <select name="type" id="type">
        <option value="beauty">Beauty</option>
        <option value="barber">Barber</option>
        <option value="spa">Spa</option>
    </select>

    <label for="logo">Salon Logo</label>
    <input type="file" name="logo" id="logo">

    <label for="images">Salon Images (multiple allowed)</label>
    <input type="file" name="images[]" id="images" multiple>

    <button type="submit">Add Salon</button>
</form>

</body>
</html>
