<?php
// public/login.php
session_start();
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(strtolower(trim($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email) {
        $error = "Please enter a valid email address.";
    } else {
        // Find user
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Save session data
            $_SESSION['id'] = $user['id'];
            $_SESSION['user_name'] = $user['username'];
            $_SESSION['role'] = strtolower(trim((string)$user['role']));

            // Redirect based on role
            if ($_SESSION['role'] === 'owner') {
                header('Location: owner/dashboard.php');
            } else {
                header('Location: user/index.php');
            }
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Login - Salonora</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f4f6f8; }
    .login-card { max-width: 400px; width: 100%; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
    .toggle-password { cursor: pointer; position: absolute; right: 15px; top: 10px; color: #6c757d; }
  </style>
</head>
<body class="d-flex justify-content-center align-items-center vh-100">
  <div class="login-card bg-white position-relative">
    <h3 class="text-center mb-4">Login to Salonora</h3>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <div class="mb-3">
        <label class="form-label">Email address</label>
        <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
      </div>

      <div class="mb-3 position-relative">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" placeholder="Enter your password" id="password" required>
        <span class="toggle-password" onclick="togglePassword()">Show</span>
      </div>

      <!-- Optional feature: Remember Me -->
      <!--
      <div class="mb-3 form-check">
        <input type="checkbox" class="form-check-input" id="rememberMe" name="remember">
        <label class="form-check-label" for="rememberMe">Remember me</label>
      </div>
      -->

      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>

    <div class="text-center mt-3">
      <small>Don't have an account? <a href="register.php">Register here</a></small><br>
      <small><a href="forgot_password.php">Forgot password?</a></small>
    </div>
  </div>

  <script>
    function togglePassword() {
      const pwd = document.getElementById('password');
      const toggle = document.querySelector('.toggle-password');
      if (pwd.type === 'password') {
        pwd.type = 'text';
        toggle.textContent = 'Hide';
      } else {
        pwd.type = 'password';
        toggle.textContent = 'Show';
      }
    }
  </script>
</body>
</html>
