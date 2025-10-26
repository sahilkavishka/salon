<?php
// public/login.php
session_start();
require_once __DIR__ . '/../config.php';

// Redirect if already logged in
if (isset($_SESSION['id'])) {
    $role = $_SESSION['role'] ?? 'user';
    if ($role === 'owner') {
        header('Location: owner/dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '') $errors[] = 'Email is required';
    if ($password === '') $errors[] = 'Password is required';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['id'] = $user['id'];
            $_SESSION['user_name'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];

            // Redirect based on role
            if ($user['role'] === 'owner') {
                header('Location: owner/dashboard.php');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            $errors[] = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - Salonora</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="../assets/css/login.css">

 
</head>
<body>
  <!-- Animated Background -->
  <div class="bg-animation"></div>
  <div class="floating-shapes">
    <div class="shape"></div>
    <div class="shape"></div>
    <div class="shape"></div>
  </div>

  <!-- Login Container -->
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <div class="brand-logo">
          <i class="fas fa-spa"></i>
        </div>
        <h1 class="login-title">Welcome Back!</h1>
        <p class="login-subtitle">Sign in to continue to Salonora</p>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <i class="fas fa-exclamation-circle"></i>
          <div>
            <?php foreach ($errors as $error): ?>
              <?= htmlspecialchars($error) ?><br>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <form method="POST" id="loginForm">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <div class="input-group">
            <i class="input-icon fas fa-envelope"></i>
            <input 
              type="email" 
              name="email" 
              class="form-control" 
              placeholder="your@email.com"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-group">
            <i class="input-icon fas fa-lock"></i>
            <input 
              type="password" 
              name="password" 
              id="password"
              class="form-control" 
              placeholder="Enter your password"
              required>
            <i class="password-toggle fas fa-eye" onclick="togglePassword()"></i>
          </div>
        </div>

        <div class="form-options">
          <div class="form-check">
            <input type="checkbox" id="remember" name="remember">
            <label for="remember">Remember me</label>
          </div>
          <a href="#" class="forgot-link">Forgot Password?</a>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <span class="spinner"></span>
          <i class="fas fa-sign-in-alt"></i>
          <span>Sign In</span>
        </button>
      </form>

      <div class="divider">
        <span>New to Salonora?</span>
      </div>

      <div class="register-link">
        Don't have an account? <a href="register.php">Create Account</a>
      </div>
    </div>

    <div class="back-home">
      <a href="index.php">
        <i class="fas fa-arrow-left"></i>
        Back to Home
      </a>
    </div>
  </div>

  <!-- Logout Success Message (if redirected from logout) -->
  <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
  <script>
    // Show temporary success message
    const alert = document.createElement('div');
    alert.className = 'alert alert-success';
    alert.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; background: linear-gradient(135deg, #00b894 0%, #00cec9 100%); color: white; padding: 1rem 1.5rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); animation: slideInRight 0.3s ease;';
    alert.innerHTML = '<i class="fas fa-check-circle" style="margin-right: 0.5rem;"></i>You have been successfully logged out';
    document.body.appendChild(alert);
    
    setTimeout(() => {
      alert.style.animation = 'slideOutRight 0.3s ease';
      setTimeout(() => alert.remove(), 300);
    }, 3000);

    // Add animations
    const style = document.createElement('style');
    style.textContent = `
      @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
      }
      @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
      }
    `;
    document.head.appendChild(style);
  </script>
  <?php endif; ?>

  <script>
    // Password toggle
    function togglePassword() {
      const input = document.getElementById('password');
      const icon = document.querySelector('.password-toggle');
      
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    }

    // Form submission loading state
    document.getElementById('loginForm').addEventListener('submit', function() {
      const btn = document.getElementById('submitBtn');
      btn.classList.add('loading');
      btn.disabled = true;
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
      });
    }, 5000);
  </script>
</body>
</html>