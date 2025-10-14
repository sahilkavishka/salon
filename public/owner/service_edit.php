<?php
session_start();
require_once '../../config.php';
require_once __DIR__ . '/../auth_check.php';


if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'owner') {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: services.php');
    exit;
}

$id = $_GET['id'];

// Fetch existing service details
$stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
$stmt->execute([$id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    header('Location: services.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = trim($_POST['price']);

    if ($name !== '' && $price !== '') {
        $stmt = $pdo->prepare("UPDATE services SET name = ?, description = ?, price = ? WHERE id = ?");
        $stmt->execute([$name, $description, $price, $id]);

        header('Location: services.php?msg=updated');
        exit;
    } else {
        $error = "Name and price are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Service</title>
    <link rel="stylesheet" href="../../assets/style.css">
</head>
<body>
    

    <div class="main-content">
        <h2>Edit Service</h2>
        <?php if (!empty($error)): ?>
            <p style="color:red;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST">
            <label for="name">Service Name:</label>
            <input type="text" name="name" id="name" value="<?= htmlspecialchars($service['name']) ?>" required>

            <label for="description">Description:</label>
            <textarea name="description" id="description"><?= htmlspecialchars($service['description']) ?></textarea>

            <label for="price">Price:</label>
            <input type="number" step="0.01" name="price" id="price" value="<?= htmlspecialchars($service['price']) ?>" required>

            <button type="submit">Update</button>
            <a href="services.php">Cancel</a>
        </form>
    </div>
</body>
</html>
