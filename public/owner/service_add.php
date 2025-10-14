<?php
session_start();
require_once '../../config.php';
require_once __DIR__ . '/../auth_check.php';

// Only allow owners
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'owner') {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = trim($_POST['price']);

    if ($name !== '' && $price !== '') {
        $stmt = $pdo->prepare("INSERT INTO services (name, description, price) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $price]);

        header('Location: services.php?msg=added');
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
    <title>Add Service</title>
    <link rel="stylesheet" href="../../assets/style.css">
</head>
<body>
    <div class="main-content">
        <h2>Add Service</h2>
        <?php if (!empty($error)): ?>
            <p style="color:red;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST">
            <label for="name">Service Name:</label>
            <input type="text" name="name" id="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>

            <label for="description">Description:</label>
            <textarea name="description" id="description"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

            <label for="price">Price:</label>
            <input type="number" step="0.01" name="price" id="price" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" required>

            <button type="submit">Add Service</button>
            <a href="services.php">Cancel</a>
        </form>
    </div>
</body>
</html>
