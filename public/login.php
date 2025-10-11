<?php
// public/login.php
ob_start();
session_start();
require_once __DIR__ . '/../config.php'; // adjust if your config is in /config.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    // Find user
    $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // ✅ Save session data
        $_SESSION['id'] = $user['id'];
        $_SESSION['user_name'] = $user['username'];
        $_SESSION['role'] = strtolower(trim((string)$user['role']));


        // ✅ Debug line (temporary)
        // echo 'Detected role: ' . $_SESSION['role']; exit;

        // ✅ Redirect based on role
        if ($_SESSION['role'] === 'owner') {
            header('Location: owner/dashboard.php');
        } else {
            header('Location: index.php');
        }
        exit;
    } else {
        $error = "Invalid login credentials.";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Login - Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex justify-content-center align-items-center" style="height:100vh;">
  <div class="card p-4 shadow" style="max-width:400px; width:100%;">
    <h3 class="text-center mb-3">Login</h3>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <div class="mb-3">
        <input type="email" name="email" class="form-control" placeholder="Email address" required>
      </div>
      <div class="mb-3">
        <input type="password" name="password" class="form-control" placeholder="Password" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>

    <div class="text-center mt-3">
      <small>Don't have an account? <a href="register.php">Register here</a></small>
    </div>
  </div>
</body>
</html>
