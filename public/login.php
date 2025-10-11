<?php
// public/login.php
session_start();
require_once __DIR__ . '/../config.php'; // ✅ adjust this if your config path differs

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    // find user by email
    $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // set session data
        $_SESSION['id'] = $user['id'];
        $_SESSION['user_name'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // redirect by role
        if ($user['role'] === 'owner') {
            header('Location: ../owner/dashboard.php');
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
<html>
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
        <input name="email" class="form-control" placeholder="Email" type="email" required>
      </div>
      <div class="mb-3">
        <input name="password" class="form-control" placeholder="Password" type="password" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>

    <p class="text-center mt-3">
      Don't have an account? <a href="register.php">Register</a>
    </p>
  </div>
</body>
</html>
