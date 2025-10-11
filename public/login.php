<?php
// public/login.php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id, username, email, password, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // ✅ Store session data consistently
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['username'];
        $_SESSION['role'] = $user['role']; // 'customer' or 'owner'

        // Redirect based on role
        if ($user['role'] === 'owner') {
            header('Location: owner/dashboard.php');
        } else {
            header('Location: user/profile.php');
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
  <title>Login | Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width:400px;">
  <h3 class="text-center mb-4">Login to Salonora</h3>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <div class="mb-3">
      <label>Email</label>
      <input name="email" type="email" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Password</label>
      <input name="password" type="password" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary w-100">Login</button>
    <p class="text-center mt-3">
      Don’t have an account? <a href="register.php">Register</a>
    </p>
  </form>
</div>
</body>
</html>
