<?php
// public/register.php
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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $phone = trim($_POST['phone'] ?? '');

    // Validation
    if ($username === '') $errors[] = 'Username is required';
    if (strlen($username) < 3) $errors[] = 'Username must be at least 3 characters';
    if ($email === '') $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    if ($password === '') $errors[] = 'Password is required';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match';
    if (!in_array($role, ['user', 'owner'])) $errors[] = 'Invalid role selected';

    // Check if email already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already registered';
        }
    }

    // Check if username already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = 'Username already taken';
        }
    }

    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, role, phone, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$username, $email, $hashed_password, $role, $phone]);

            // Auto-login after registration
            $_SESSION['id'] = $pdo->lastInsertId();
            $_SESSION['user_name'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['email'] = $email;

            // Send welcome email
            $subject = "Welcome to Salonora!";
            $message = "Dear $username,\n\n";
            $message .= "Welcome to Salonora! Your account has been created successfully.\n\n";
            if ($role === 'owner') {
                $message .= "As a salon owner, you can now:\n";
                $message .= "- Add your salons\n";
                $message .= "- Manage services\n";
                $message .= "- Handle appointments\n\n";
            } else {
                $message .= "You can now:\n";
                $message .= "- Browse salons\n";
                $message .= "- Book appointments\n";
                $message .= "- Write reviews\n\n";
            }
            $message .= "Thank you for joining Salonora!\n\n";
            $message .= "Best regards,\nSalonora Team";
            
            $headers = "From: noreply@salonora.com\r\n";
            @mail($email, $subject, $message, $headers);

            // Redirect based on role
            if ($role === 'owner') {
                header('Location: owner/dashboard.php');
            } else {
                header('Location: index.php');
            }
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Registration failed. Please try again.';
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register - Salonora</title>
  
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
      padding: 2rem 0;
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

    /* Register Container */
    .register-container {
      position: relative;
      z-index: 10;
      width: 100%;
      max-width: 500px;
      padding: 0 1.5rem;
    }

    .register-card {
      background: white;
      border-radius: 24px;
      padding: 2.5rem;
      box-shadow: var(--shadow-xl);
      position: relative;
      overflow: hidden;
    }

    .register-card::before {
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
    .register-header {
      text-align: center;
      margin-bottom: 2rem;
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
      margin: 0 auto 1rem;
      font-size: 2rem;
      color: white;
      box-shadow: var(--shadow-md);
    }

    .register-title {
      font-size: 1.8rem;
      font-weight: 800;
      color: var(--text-dark);
      margin-bottom: 0.5rem;
    }

    .register-subtitle {
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
      margin-bottom: 1.25rem;
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
      padding: 0.9rem 0.9rem 0.9rem 3rem;
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

    /* Role Selection */
    .role-selection {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .role-option {
      position: relative;
    }

    .role-option input[type="radio"] {
      position: absolute;
      opacity: 0;
    }

    .role-label {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 1.25rem;
      border: 2px solid #e9ecef;
      border-radius: 12px;
      cursor: pointer;
      transition: var(--transition);
      text-align: center;
    }

    .role-option input[type="radio"]:checked + .role-label {
      border-color: var(--primary);
      background: linear-gradient(135deg, rgba(233, 30, 99, 0.05) 0%, rgba(156, 39, 176, 0.05) 100%);
    }

    .role-icon {
      font-size: 2rem;
      margin-bottom: 0.5rem;
      color: var(--primary);
    }

    .role-name {
      font-weight: 600;
      color: var(--text-dark);
      font-size: 0.95rem;
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

    /* Login Link */
    .login-link {
      text-align: center;
      color: var(--text-light);
      font-size: 0.95rem;
    }

    .login-link a {
      color: var(--primary);
      text-decoration: none;
      font-weight: 700;
      transition: var(--transition);
    }

    .login-link a:hover {
      color: var(--primary-dark);
    }

    /* Back Home */
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
      .register-card {
        padding: 2rem 1.5rem;
      }

      .register-title {
        font-size: 1.5rem;
      }

      .role-selection {
        grid-template-columns: 1fr;
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

    /* Password Strength Indicator */
    .password-strength {
      height: 4px;
      background: #e9ecef;
      border-radius: 2px;
      margin-top: 0.5rem;
      overflow: hidden;
    }

    .password-strength-bar {
      height: 100%;
      width: 0%;
      transition: var(--transition);
    }

    .password-strength-bar.weak {
      width: 33%;
      background: #e74c3c;
    }

    .password-strength-bar.medium {
      width: 66%;
      background: #f39c12;
    }

    .password-strength-bar.strong {
      width: 100%;
      background: #27ae60;
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

  <!-- Register Container -->
  <div class="register-container">
    <div class="register-card">
      <div class="register-header">
        <div class="brand-logo">
          <i class="fas fa-spa"></i>
        </div>
        <h1 class="register-title">Join Salonora</h1>
        <p class="register-subtitle">Create your account to get started</p>
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

      <form method="POST" id="registerForm">
        <div class="form-group">
          <label class="form-label">Username</label>
          <div class="input-group">
            <i class="input-icon fas fa-user"></i>
            <input 
              type="text" 
              name="username" 
              class="form-control" 
              placeholder="Choose a username"
              value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
              required>
          </div>
        </div>

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
          <label class="form-label">Phone (Optional)</label>
          <div class="input-group">
            <i class="input-icon fas fa-phone"></i>
            <input 
              type="tel" 
              name="phone" 
              class="form-control" 
              placeholder="+94 71 234 5678"
              value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
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
              placeholder="Create a strong password"
              required>
            <i class="password-toggle fas fa-eye" onclick="togglePassword('password')"></i>
          </div>
          <div class="password-strength">
            <div class="password-strength-bar" id="strengthBar"></div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <div class="input-group">
            <i class="input-icon fas fa-lock"></i>
            <input 
              type="password" 
              name="confirm_password" 
              id="confirm_password"
              class="form-control" 
              placeholder="Re-enter your password"
              required>
            <i class="password-toggle fas fa-eye" onclick="togglePassword('confirm_password')"></i>
          </div>
        </div>

        <label class="form-label">I am a...</label>
        <div class="role-selection">
          <div class="role-option">
            <input type="radio" name="role" id="role_user" value="user" checked>
            <label for="role_user" class="role-label">
              <i class="role-icon fas fa-user"></i>
              <span class="role-name">Customer</span>
            </label>
          </div>
          <div class="role-option">
            <input type="radio" name="role" id="role_owner" value="owner">
            <label for="role_owner" class="role-label">
              <i class="role-icon fas fa-store"></i>
              <span class="role-name">Salon Owner</span>
            </label>
          </div>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <span class="spinner"></span>
          <i class="fas fa-user-plus"></i>
          <span>Create Account</span>
        </button>
      </form>

      <div class="divider">
        <span>Already have an account?</span>
      </div>

      <div class="login-link">
        <a href="login.php">Sign In Instead</a>
      </div>
    </div>

    <div class="back-home">
      <a href="index.php">
        <i class="fas fa-arrow-left"></i>
        Back to Home
      </a>
    </div>
  </div>

  <script>
    // Password toggle
    function togglePassword(fieldId) {
      const input = document.getElementById(fieldId);
      const icon = input.nextElementSibling;
      
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

    // Password strength indicator
    const passwordInput = document.getElementById('password');
    const strengthBar = document.getElementById('strengthBar');

    passwordInput.addEventListener('input', function() {
      const password = this.value;
      let strength = 0;

      if (password.length >= 6) strength++;
      if (password.length >= 10) strength++;
      if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
      if (/\d/.test(password)) strength++;
      if (/[^a-zA-Z\d]/.test(password)) strength++;

      strengthBar.className = 'password-strength-bar';
      if (strength <= 2) {
        strengthBar.classList.add('weak');
      } else if (strength <= 4) {
        strengthBar.classList.add('medium');
      } else {
        strengthBar.classList.add('strong');
      }
    });

    // Form submission loading state
    document.getElementById('registerForm').addEventListener('submit', function(e) {
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;

      if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return;
      }

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

    // Phone number formatting
    const phoneInput = document.querySelector('input[name="phone"]');
    if (phoneInput) {
      phoneInput.addEventListener('input', function(e) {
        let value = e.target.value;
        value = value.replace(/[^0-9+\-\s]/g, '');
        e.target.value = value;
      });
    }
  </script>
</body>
</html>