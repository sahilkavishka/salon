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
            <link rel="stylesheet" href="../assets/css/register.css">

  
  
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