<?php
// public/user/profile.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';

$id = $_SESSION['id'];

// Fetch user data
$stmt = $pdo->prepare('SELECT id, username AS name, email, phone FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die('User not found.');

$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '') $errors[] = 'Name is required.';
    if ($phone !== '' && !preg_match('/^[0-9+\-\s]+$/', $phone)) $errors[] = 'Phone can only contain numbers, +, - and spaces.';

    if (empty($errors)) {
        $u = $pdo->prepare('UPDATE users SET username=?, phone=? WHERE id=?');
        $u->execute([$name, $phone, $id]);
        $_SESSION['user_name'] = $name;
        $success = 'Profile updated successfully.';
        $user['name'] = $name;
        $user['phone'] = $phone;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Profile - Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <h2>My Profile</h2>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <form method="post" class="card p-3">
    <div class="mb-3">
      <label class="form-label">Name</label>
      <input name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Email</label>
      <input class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
    </div>
    <div class="mb-3">
      <label class="form-label">Phone</label>
      <input name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
      <div class="form-text">Optional. Numbers, +, - allowed.</div>
    </div>
    <button type="submit" class="btn btn-primary">Save Profile</button>
  </form>
</div>
</body>
</html>
