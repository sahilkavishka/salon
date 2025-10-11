<?php
// user/profile.php
require_once __DIR__ . '/../auth_check.php';
checkAuth('customer'); // Only customers can access

require_once __DIR__ . '/../../config.php';

$user_id = $_SESSION['user_id'];

// Fetch user
$stmt = $pdo->prepare("SELECT id, username AS name, email, phone, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die('User not found.');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '') $errors[] = "Name is required.";

    if (!$errors) {
        $update = $pdo->prepare("UPDATE users SET username = ?, phone = ? WHERE id = ?");
        $update->execute([$name, $phone, $user_id]);
        $_SESSION['user_name'] = $name;
        header('Location: profile.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Profile | Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <h2 class="mb-4">👤 My Profile</h2>
  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?></div>
  <?php endif; ?>

  <form method="post" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label class="form-label">Name</label>
      <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Email (read-only)</label>
      <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
    </div>
    <div class="mb-3">
      <label class="form-label">Phone</label>
      <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
    </div>
    <button type="submit" class="btn btn-primary w-100">💾 Save Changes</button>
  </form>
</div>
</body>
</html>
