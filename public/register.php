<?php
// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 1 : 0);
ini_set('session.use_strict_mode', 1);
session_start();

require_once __DIR__ . '/../config.php';

// If already logged in, redirect to appropriate home
if (isset($_SESSION['id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'owner') {
        header('Location: ' . url('owner/dashboard.php'));
    } else {
        header('Location: ' . url('index.php'));
    }
    exit;
}

$errors = [];
$success = '';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = "Security token validation failed. Please refresh the page and try again.";
    }

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $profilePicturePath = null;

    // Role (user / owner)
    $role = $_POST['role'] ?? 'user';
    $allowedRoles = ['user', 'owner'];
    if (!in_array($role, $allowedRoles, true)) {
        $errors[] = "Please select a valid account type (Customer or Salon Owner).";
    }

    // Validations
    if ($username === '' || strlen($username) < 2) {
        $errors[] = "Full Name must be at least 2 characters long.";
    }
    if (strlen($username) > 100) {
        $errors[] = "Full Name is too long (maximum 100 characters).";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address (e.g., name@example.com).";
    }

    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }

    if ($location === '') {
        $errors[] = "Please select your city/location.";
    }

    if (!empty($phone) && !preg_match('/^[0-9\+\-\(\)\s]{7,20}$/', $phone)) {
        $errors[] = "Please enter a valid phone number.";
    }

    // Check duplicate email
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "This email is already registered. Please <a href='" . url('login.php') . "' style='color:#e91e63;font-weight:600;text-decoration:underline;'>Login instead</a> or use a different email.";
        }
    }

    // Profile picture upload (optional)
    if (!empty($_FILES['profile_picture']['name']) && empty($errors)) {
        $file = $_FILES['profile_picture'];

        if ($file['error'] === UPLOAD_ERR_OK) {
            if ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = "Profile picture must be less than 2MB.";
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

                if (!in_array($ext, $allowedExts, true)) {
                    $errors[] = "Only JPG, PNG or WebP images are allowed.";
                } else {
                    $fileName = 'profile_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $uploadDir = __DIR__ . '/uploads/profile/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $uploadPath = $uploadDir . $fileName;
                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        $profilePicturePath = "uploads/profile/" . $fileName;
                        @chmod($uploadPath, 0644);
                    } else {
                        // Warning only, don't fail registration
                        error_log("Failed to move uploaded profile picture: " . $file['name']);
                    }
                }
            }
        }
    }

    // Insert into database and auto-login
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, phone, profile_picture, location, role, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $username,
                $email,
                $hashedPassword,
                $phone,
                $profilePicturePath,
                $location,
                $role
            ]);

            $newUserId = (int)$pdo->lastInsertId();

            // Automatically log in the new user
            session_regenerate_id(true);
            $_SESSION['id'] = $newUserId;
            $_SESSION['user_name'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['email'] = $email;

            // Send in-app welcome notification
            notifyUser($pdo, $newUserId, "Welcome to Salonora, " . htmlspecialchars($username) . "! Your account is ready.");

            // Redirect based on role
            if ($role === 'owner') {
                header('Location: ' . url('owner/dashboard.php'));
            } else {
                header('Location: ' . url('index.php'));
            }
            exit;

        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $errors[] = "Registration could not be completed at this moment. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account - Salonora</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {box-sizing:border-box; margin:0; padding:0;}
body {
  font-family:'Poppins', sans-serif;
  background:linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
  min-height:100vh;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:30px 15px;
  position:relative;
}
.card {
  background:rgba(255,255,255,0.98);
  border-radius:20px;
  padding:40px;
  width:100%;
  max-width:500px;
  box-shadow:0 20px 60px rgba(0,0,0,0.3);
  position:relative;
  z-index:1;
  backdrop-filter:blur(10px);
}
.logo-title {
  text-align:center;
  margin-bottom:25px;
}
.logo-title i {
  font-size:42px;
  background:linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
  -webkit-background-clip:text;
  -webkit-text-fill-color:transparent;
  display:block;
  margin-bottom:8px;
}
.logo-title h2 {
  font-family:'Playfair Display', serif;
  color:#1a1a2e;
  font-size:28px;
  font-weight:700;
}
.logo-title p {
  color:#666;
  font-size:14px;
}
.form-group {margin-bottom:18px;}
label {display:block; margin-bottom:6px; font-weight:500; font-size:14px; color:#333;}
input, select {
  width:100%;
  padding:12px 14px;
  border:2px solid #e0e0e0;
  border-radius:10px;
  font-size:14px;
  font-family:'Poppins', sans-serif;
  transition:all 0.3s ease;
  background:#fafafa;
}
input:focus, select:focus {
  outline:none;
  border-color:#e91e63;
  background:#fff;
  box-shadow:0 0 0 4px rgba(233,30,99,0.1);
}
.password-wrapper {position:relative;}
.password-wrapper input {padding-right:45px;}
.password-toggle {
  position:absolute;
  right:15px;
  top:50%;
  transform:translateY(-50%);
  cursor:pointer;
  font-size:1.1rem;
  color:#888;
}
.password-hint {
  font-size:12px;
  color:#777;
  margin-top:5px;
  display:flex;
  align-items:center;
  gap:5px;
}
#strengthMessage {
  font-size:12px;
  margin-top:4px;
  font-weight:500;
}
.alert {
  padding:12px 16px;
  border-radius:10px;
  margin-bottom:20px;
  font-size:14px;
  line-height:1.5;
}
.alert-danger {
  background:#ffebee;
  color:#c62828;
  border:1px solid #ffcdd2;
}
.alert-success {
  background:#e8f5e9;
  color:#2e7d32;
  border:1px solid #c8e6c9;
}
#profilePreview {
  width:80px;
  height:80px;
  border-radius:50%;
  object-fit:cover;
  margin-top:10px;
  display:none;
  border:3px solid #e91e63;
}
button[type="submit"] {
  width:100%;
  padding:14px;
  border:none;
  border-radius:10px;
  background:linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
  color:#fff;
  font-weight:600;
  font-size:16px;
  cursor:pointer;
  transition:all 0.3s;
  margin-top:10px;
  text-transform:uppercase;
  letter-spacing:1px;
}
button[type="submit"]:hover {
  transform:translateY(-2px);
  box-shadow:0 8px 20px rgba(233,30,99,0.3);
}
.login-link {
  text-decoration:none;
  color:#e91e63;
  font-weight:500;
  display:block;
  text-align:center;
  margin-top:20px;
  font-size:14px;
  transition:0.3s;
}
.login-link:hover {color:#9c27b0; text-decoration:underline;}
.required {color:#e91e63;}
</style>
</head>
<body>

<div class="card">
  <div class="logo-title">
    <i class="fas fa-spa"></i>
    <h2>Create Account</h2>
    <p>Join Salonora to find & book premium salons</p>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <i class="fas fa-exclamation-circle me-1"></i>
      <?php foreach ($errors as $e): ?>
        <div><?= $e ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" id="registerForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div class="form-group">
      <label>Full Name <span class="required">*</span></label>
      <input type="text" name="username" maxlength="100" 
             value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
             placeholder="Your full name" required>
    </div>

    <div class="form-group">
      <label>Email Address <span class="required">*</span></label>
      <input type="email" name="email" maxlength="150"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
             placeholder="name@example.com" required>
    </div>

    <div class="form-group">
      <label>Password <span class="required">*</span></label>
      <div class="password-wrapper">
        <input type="password" name="password" id="password" maxlength="72" 
               placeholder="At least 6 characters" required>
        <span class="password-toggle" onclick="togglePassword()"><i class="fas fa-eye" id="toggleIcon"></i></span>
      </div>
      <div id="strengthMessage"></div>
      <div class="password-hint">
        <i class="fas fa-info-circle"></i> Use at least 6 characters.
      </div>
    </div>

    <div class="form-group">
      <label>Phone Number</label>
      <input type="tel" name="phone" placeholder="07XXXXXXXX"
             value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label>Location / City <span class="required">*</span></label>
      <select name="location" required>
        <option value="">Select your city</option>
        <?php
        $cities = ["Colombo","Kandy","Galle","Matara","Negombo","Kurunegala","Jaffna",
                  "Badulla","Batticaloa","Trincomalee","Anuradhapura","Puttalam",
                  "Hambantota","Polonnaruwa","Ratnapura","Gampaha","Kalutara",
                  "Mannar","Nuwara Eliya"];
        foreach($cities as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>" <?= (($_POST['location'] ?? '') == $c) ? 'selected' : '' ?>>
            <?= htmlspecialchars($c) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label>Account Type <span class="required">*</span></label>
      <select name="role" required>
        <option value="user" <?= (($_POST['role'] ?? '') === 'user') ? 'selected' : '' ?>>Customer (Book salon appointments)</option>
        <option value="owner" <?= (($_POST['role'] ?? '') === 'owner') ? 'selected' : '' ?>>Salon Owner (Register & manage my salon)</option>
      </select>
    </div>

    <div class="form-group">
      <label>Profile Picture <small style="color:#888;">(Optional)</small></label>
      <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp" onchange="previewProfile(this)">
      <img id="profilePreview" src="#" alt="Preview">
    </div>

    <button type="submit">Create Account</button>
    <a href="<?= url('login.php') ?>" class="login-link">Already have an account? Sign In</a>
  </form>
</div>

<script>
function togglePassword() {
  const passField = document.getElementById('password');
  const icon = document.getElementById('toggleIcon');
  if (passField.type === 'password') {
    passField.type = 'text';
    icon.classList.remove('fa-eye');
    icon.classList.add('fa-eye-slash');
  } else {
    passField.type = 'password';
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  }
}

const passwordField = document.getElementById('password');
const strengthMessage = document.getElementById('strengthMessage');

passwordField.addEventListener('input', function() {
  const val = passwordField.value;
  if (!val) {
    strengthMessage.textContent = "";
    return;
  }
  let strength = "Weak";
  let color = "#d9534f";
  let score = 0;

  if (val.length >= 6) score++;
  if (val.length >= 8) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  if (score >= 4) {
    strength = "Strong";
    color = "#28a745";
  } else if (score >= 2) {
    strength = "Medium";
    color = "#ff9800";
  }

  strengthMessage.textContent = "Strength: " + strength;
  strengthMessage.style.color = color;
});

function previewProfile(input) {
  const preview = document.getElementById('profilePreview');
  const file = input.files[0];
  if (file) {
    if (file.size > 2 * 1024 * 1024) {
      alert('File size must be less than 2MB');
      input.value = '';
      preview.style.display = 'none';
      return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
      preview.src = e.target.result;
      preview.style.display = 'block';
    }
    reader.readAsDataURL(file);
  }
}
</script>
</body>
</html>
