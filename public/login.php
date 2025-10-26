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

  
  <style>
    :root {
      --primary: #e91e63;
      --primary-dark: #c2185b;
      --secondary: #9c27b0;
      --accent: #ff6b9d;
      --dark: #1a1a2e;
      --light: #f5f7fa;
      --text-dark: #2d3436;
      --text-light: #636e72;
      --gradient-primary: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
      --gradient-secondary: linear-gradient(135deg, #ff6b9d 0%, #c471ed 100%);
      --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
      --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.12);
      --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.15);
      --shadow-xl: 0 20px 60px rgba(0, 0, 0, 0.2);
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: var(--light);
      color: var(--text-dark);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow-x: hidden;
    }

    /* Animated Background */
    .bg-animation {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: 0;
      background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
    }

    .bg-animation::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.1)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,144C960,149,1056,139,1152,122.7C1248,107,1344,85,1392,74.7L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') repeat;
      animation: wave 20s linear infinite;
    }

    @keyframes wave {
      0% { transform: translate(0, 0); }
      100% { transform: translate(-50%, -50%); }
    }

    .floating-shapes {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: 1;
      pointer-events: none;
    }

    .shape {
      position: absolute;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
      animation: float 15s infinite;
    }

    .shape:nth-child(1) {
      width: 80px;
      height: 80px;
      top: 10%;
      left: 10%;
      animation-delay: 0s;
    }

    .shape:nth-child(2) {
      width: 60px;
      height: 60px;
      top: 70%;
      left: 80%;
      animation-delay: 4s;
    }

    .shape:nth-child(3) {
      width: 100px;
      height: 100px;
      top: 40%;
      left: 70%;
      animation-delay: 2s;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0) rotate(0deg); }
      50% { transform: translateY(-30px) rotate(180deg); }
    }

    /* Login Container */
    .login-container {
      position: relative;
      z-index: 10;
      width: 100%;
      max-width: 450px;
      padding: 0 1.5rem;
    }

    .login-card {
      background: white;
      border-radius: 24px;
      padding: 3rem 2.5rem;
      box-shadow: var(--shadow-xl);
      position: relative;
      overflow: hidden;
    }

    .login-card::before {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      width: 150px;
      height: 150px;
      background: var(--gradient-primary);
      opacity: 0.05;
      border-radius: 50%;
      transform: translate(50%, -50%);
    }

    /* Header */
    .login-header {
      text-align: center;
      margin-bottom: 2.5rem;
      position: relative;
    }

    .brand-logo {
      width: 70px;
      height: 70px;
      background: var(--gradient-primary);
      border-radius: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      font-size: 2rem;
      color: white;
      box-shadow: var(--shadow-md);
    }

    .login-title {
      font-size: 1.8rem;
      font-weight: 800;
      color: var(--text-dark);
      margin-bottom: 0.5rem;
    }

    .login-subtitle {
      color: var(--text-light);
      font-size: 0.95rem;
    }

    /* Alert */
    .alert {
      border: none;
      border-radius: 12px;
      padding: 1rem 1.25rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      animation: slideIn 0.3s ease;
    }

    .alert-danger {
      background: linear-gradient(135deg, rgba(214, 48, 49, 0.1) 0%, rgba(225, 112, 85, 0.1) 100%);
      color: #d63031;
      border-left: 4px solid #d63031;
    }

    .alert i {
      font-size: 1.25rem;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Form */
    .form-group {
      margin-bottom: 1.5rem;
      position: relative;
    }

    .form-label {
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 0.5rem;
      display: block;
      font-size: 0.9rem;
    }

    .input-group {
      position: relative;
    }

    .input-icon {
      position: absolute;
      left: 1.25rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-light);
      font-size: 1rem;
      z-index: 2;
    }

    .form-control {
      width: 100%;
      padding: 1rem 1rem 1rem 3rem;
      border: 2px solid #e9ecef;
      border-radius: 12px;
      font-size: 0.95rem;
      transition: var(--transition);
      background: white;
    }

    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
      outline: none;
    }

    .password-toggle {
      position: absolute;
      right: 1.25rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-light);
      cursor: pointer;
      z-index: 2;
      font-size: 1rem;
      transition: var(--transition);
    }

    .password-toggle:hover {
      color: var(--primary);
    }

    /* Remember & Forgot */
    .form-options {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      font-size: 0.9rem;
    }

    .form-check {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .form-check input[type="checkbox"] {
      width: 18px;
      height: 18px;
      cursor: pointer;
      accent-color: var(--primary);
    }

    .form-check label {
      color: var(--text-dark);
      cursor: pointer;
      margin: 0;
    }

    .forgot-link {
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
      transition: var(--transition);
    }

    .forgot-link:hover {
      color: var(--primary-dark);
    }

    /* Submit Button */
    .btn-submit {
      width: 100%;
      background: var(--gradient-primary);
      color: white;
      border: none;
      padding: 1rem;
      border-radius: 12px;
      font-weight: 700;
      font-size: 1rem;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
      margin-bottom: 1.5rem;
      cursor: pointer;
    }

    .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(233, 30, 99, 0.4);
    }

    .btn-submit:active {
      transform: translateY(0);
    }

    /* Divider */
    .divider {
      display: flex;
      align-items: center;
      text-align: center;
      margin: 1.5rem 0;
      color: var(--text-light);
      font-size: 0.9rem;
    }

    .divider::before,
    .divider::after {
      content: '';
      flex: 1;
      border-bottom: 1px solid #e9ecef;
    }

    .divider span {
      padding: 0 1rem;
    }

    /* Register Link */
    .register-link {
      text-align: center;
      color: var(--text-light);
      font-size: 0.95rem;
    }

    .register-link a {
      color: var(--primary);
      text-decoration: none;
      font-weight: 700;
      transition: var(--transition);
    }

    .register-link a:hover {
      color: var(--primary-dark);
    }

    /* Back Home Link */
    .back-home {
      text-align: center;
      margin-top: 1.5rem;
    }

    .back-home a {
      color: white;
      text-decoration: none;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: rgba(255, 255, 255, 0.2);
      padding: 0.75rem 1.5rem;
      border-radius: 50px;
      transition: var(--transition);
      backdrop-filter: blur(10px);
    }

    .back-home a:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: translateY(-2px);
    }

    /* Responsive */
    @media (max-width: 576px) {
      .login-card {
        padding: 2rem 1.5rem;
      }

      .login-title {
        font-size: 1.5rem;
      }

      .form-options {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
      }
    }

    /* Loading State */
    .btn-submit:disabled {
      opacity: 0.7;
      cursor: not-allowed;
    }

    .spinner {
      display: none;
      width: 20px;
      height: 20px;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-top-color: white;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }

    .btn-submit.loading .spinner {
      display: block;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }
  </style>
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