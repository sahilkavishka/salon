<?php
session_start();
require '../includes/config.php';

// ✅ Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: login.php");
    exit;
}

// Handle delete request
if (isset($_GET['delete'])) {
    $salon_id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM salons WHERE id = ? AND owner_id = ?");
    $stmt->execute([$salon_id, $_SESSION['user_id']]);
    header("Location: manage_salons.php?msg=deleted");
    exit;
}

// Fetch salons owned by this user
$stmt = $pdo->prepare("SELECT * FROM salons WHERE owner_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$salons = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage My Salons</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    a { text-decoration: none; margin: 0 5px; }
    .salon { border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; border-radius: 5px; }
    .actions { margin-top: 10px; }
  </style>
</head>
<body>
  <h1>My Salons</h1>
  <p><a href="index.php">🏠 Back to Home</a> | <a href="add_salon.php">➕ Add New Salon</a></p>

  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <p style="color: green;">Salon deleted successfully.</p>
  <?php endif; ?>

  <?php if (count($salons) > 0): ?>
    <?php foreach ($salons as $salon): ?>
      <div class="salon">
        <strong><?= htmlspecialchars($salon['name']) ?></strong><br>
        <?= htmlspecialchars($salon['address']) ?><br>
        Type: <?= htmlspecialchars($salon['type']) ?><br>
        Rating: <?= htmlspecialchars($salon['rating']) ?><br>

        <div class="actions">
          <a href="edit_salon.php?id=<?= $salon['id'] ?>">✏️ Edit</a>
          <a href="manage_salons.php?delete=<?= $salon['id'] ?>" onclick="return confirm('Are you sure you want to delete this salon?');">🗑 Delete</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p>You haven’t added any salons yet.</p>
  <?php endif; ?>
</body>
</html>
