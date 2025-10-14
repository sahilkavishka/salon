<?php
// public/register.php
session_start();
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = in_array($_POST['role'] ?? 'user', ['user','owner']) ? $_POST['role'] : 'user';

    $errors = [];

    if (!$name || !$username || !$email || !$password || !$confirm_password) {
        $errors[] = "All fields are required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = "Email is already registered.";
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, username, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$name, $username, $email, $hashed, $role]);

        $_SESSION['id'] = $pdo->lastInsertId();
        $_SESSION['user_name'] = $username;
        $_SESSION['role'] = $role;

        // Redirect based on role
        if ($role === 'owner') {
            header('Location: /salonora/public/owner/dashboard.php');
        } else {
            header('Location: index.php');
        }
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Register - Salonora</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f4f6f8; }
    .register-card { max-width: 450px; width: 100%; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
    .toggle-password { cursor: pointer; position: absolute; right: 15px; top: 10px; color: #6c757d; }
  </style>
</head>
<body class="d-flex justify-content-center align-items-center vh-100">
  <div class="register-card bg-white position-relative">
    <h3 class="text-center mb-4">Create Your Account</h3>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" action="">
      <div class="mb-3">
        <label class="form-label">Full Name</label>
        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($name ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($username ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Email address</label>
        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($email ?? '') ?>">
      </div>
      <div class="mb-3 position-relative">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" id="password" required>
        <span class="toggle-password" onclick="togglePassword()">Show</span>
      </div>
      <div class="mb-3 position-relative">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="confirm_password" class="form-control" id="confirm_password" required>
        <span class="toggle-password" onclick="toggleConfirm()">Show</span>
      </div>
      <div class="mb-3">
        <label class="form-label">Role</label>
        <select name="role" class="form-select" required>
          <option value="user" <?= (isset($role) && $role==='user')?'selected':'' ?>>User / Customer</option>
          <option value="owner" <?= (isset($role) && $role==='owner')?'selected':'' ?>>Salon Owner</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary w-100">Register</button>
    </form>

    <div class="text-center mt-3">
      <small>Already have an account? <a href="login.php">Login here</a></small>
    </div>
  </div>

  <script>
    function togglePassword() {
      const pwd = document.getElementById('password');
      const toggle = event.target;
      pwd.type = (pwd.type === 'password') ? 'text' : 'password';
      toggle.textContent = (pwd.type === 'password') ? 'Show' : 'Hide';
    }
    function toggleConfirm() {
      const pwd = document.getElementById('confirm_password');
      const toggle = event.target;
      pwd.type = (pwd.type === 'password') ? 'text' : 'password';
      toggle.textContent = (pwd.type === 'password') ? 'Show' : 'Hide';
    }
  </script>
</body>
</html>
