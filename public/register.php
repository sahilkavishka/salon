<?php
// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Use only over HTTPS
ini_set('session.use_strict_mode', 1);
session_start();

require_once __DIR__ . '/../config.php';

$errors = [];
$success = '';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $profilePicturePath = null;

    // Role (user / owner)
    $role = $_POST['role'] ?? 'user';
    $allowedRoles = ['user', 'owner'];
    if (!in_array($role, $allowedRoles, true)) {
        $errors[] = "Invalid role selected.";
    }

    // Validations
    if ($username === '' || strlen($username) < 2) {
        $errors[] = "Full Name must be at least 2 characters.";
    }
    if (strlen($username) > 100) {
        $errors[] = "Full Name is too long.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    if (strlen($password) > 72) {
        $errors[] = "Password must be less than 72 characters.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }

    if ($location === '') {
        $errors[] = "Location is required.";
    }

    if (!empty($phone) && !preg_match('/^[0-9\+\-\(\)\s]{7,20}$/', $phone)) {
        $errors[] = "Invalid phone number format.";
    }

    // Check duplicate email
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Registration failed. Please check your information.";
        }
    }

    // Profile picture upload
    if (!empty($_FILES['profile_picture']['name']) && empty($errors)) {
        $file = $_FILES['profile_picture'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload failed.";
        } else {
            if ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = "Profile picture must be less than 2MB.";
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!in_array($mimeType, $allowedMimes)) {
                $errors[] = "Only JPG and PNG images are allowed.";
            }

            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                $errors[] = "Invalid image file.";
            }

            if (empty($errors)) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $fileName = bin2hex(random_bytes(16)) . '.' . $ext;

                // IMPORTANT: uploads/profile inside public
                $uploadDir = __DIR__ . '/uploads/profile/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $htaccessPath = $uploadDir . '.htaccess';
                if (!file_exists($htaccessPath)) {
                    file_put_contents($htaccessPath, "php_flag engine off\nOptions -Indexes");
                }

                $uploadPath = $uploadDir . $fileName;

                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    // This is the web path (relative to public)
                    $profilePicturePath = "uploads/profile/" . $fileName;
                    chmod($uploadPath, 0644);
                } else {
                    $errors[] = "Failed to upload profile picture.";
                }
            }
        }
    }

    // Insert into database
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

            $success = "Registration successful! You can now log in.";

            $_POST = [];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $errors[] = "Registration failed. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - Salonora</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
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
@keyframes wave { 0% { transform: translate(0, 0); } 100% { transform: translate(-50%, -50%); } }

* {margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif;}
body {display:flex; align-items:center; justify-content:center; min-height:100vh; position:relative; overflow-x:hidden; padding:20px;}
.card {position:relative; z-index:1; background:#fff; width:100%; max-width:420px; padding:2rem; border-radius:12px; box-shadow:0 8px 20px rgba(0,0,0,0.1); transition:0.3s;}
.card:hover {box-shadow:0 12px 25px rgba(0,0,0,0.15);}
h2 {text-align:center; margin-bottom:1.5rem; color:black; text-shadow:0 2px 4px rgba(0,0,0,0.2);}
.alert {border-radius:8px; padding:0.8rem; margin-bottom:1rem; font-size:0.9rem;}
.alert-danger {background:#ffe6e6; color:#d9534f; border-left:4px solid #d9534f;}
.alert-success {background:#e6ffed; color:#28a745; border-left:4px solid #28a745;}
.form-group {margin-bottom:15px;}
label {display:block; margin-bottom:5px; font-weight:500; color:#333; font-size:0.9rem;}
input, select {width:100%; padding:12px 15px; border:1.5px solid #ccc; border-radius:8px; transition:0.3s; font-size:0.95rem;}
input:focus, select:focus {border-color:#e91e63; outline:none; box-shadow:0 0 8px rgba(233,30,99,0.2);}
.password-wrapper {position:relative;}
.password-wrapper input {padding-right:40px;}
.password-toggle {position:absolute; right:15px; top:50%; transform:translateY(-50%); cursor:pointer; font-size:1.2rem; user-select:none;}
#strengthMessage {font-size:0.85rem; margin-top:5px; font-weight:500;}
#profilePreview {width:80px; height:80px; border-radius:50%; object-fit:cover; margin-top:10px; display:none; border:3px solid #e91e63;}
button {width:100%; padding:12px; border:none; border-radius:8px; background:#e91e63; color:#fff; font-weight:600; font-size:1rem; cursor:pointer; transition:0.3s; margin-top:10px;}
button:hover {background:#d81b60; transform:translateY(-2px); box-shadow:0 4px 12px rgba(233,30,99,0.3);}
button:active {transform:translateY(0);}
.login-link {text-decoration:none; color:#e91e63; font-weight:500; display:block; text-align:center; margin-top:15px; transition:0.3s;}
.login-link:hover {color:#d81b60;}
.required {color:#e91e63;}
</style>
</head>
<body>
<div class="bg-animation"></div>
<div class="card">
<h2>Create Account</h2>

<?php if($errors): ?>
<div class="alert alert-danger">
    <?php foreach($errors as $e): ?>
        <div><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if($success): ?>
<div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="registerForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div class="form-group">
        <label>Full Name <span class="required">*</span></label>
        <input type="text" name="username" maxlength="100" 
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
    </div>

    <div class="form-group">
        <label>Email <span class="required">*</span></label>
        <input type="email" name="email" maxlength="150"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
    </div>

    <div class="form-group">
        <label>Password <span class="required">*</span></label>
        <div class="password-wrapper">
            <input type="password" name="password" id="password" maxlength="72" required>
            <span class="password-toggle" onclick="togglePassword()">👁️</span>
        </div>
        <div id="strengthMessage"></div>
    </div>

    <div class="form-group">
        <label>Phone</label>
        <input type="tel" name="phone" placeholder="+94 XX XXX XXXX"
               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label>Location <span class="required">*</span></label>
        <select name="location" required>
            <option value="">Select City</option>
            <?php
            $cities = ["Colombo","Kandy","Galle","Matara","Negombo","Kurunegala","Jaffna",
                      "Badulla","Batticaloa","Trincomalee","Anuradhapura","Puttalam",
                      "Hambantota","Polonnaruwa","Ratnapura","Gampaha","Kalutara",
                      "Mannar","Nuwara Eliya"];
            foreach($cities as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" 
                    <?= (($_POST['location'] ?? '') == $c) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Role <span class="required">*</span></label>
        <select name="role" required>
            <option value="user"  <?= (($_POST['role'] ?? '') === 'user')  ? 'selected' : '' ?>>User</option>
            <option value="owner" <?= (($_POST['role'] ?? '') === 'owner') ? 'selected' : '' ?>>Salon Owner</option>
        </select>
    </div>

    <div class="form-group">
        <label>Profile Picture</label>
        <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/jpg" 
               onchange="previewProfile(this)">
        <img id="profilePreview" src="#" alt="Preview">
    </div>

    <button type="submit">Register</button>
    <a href="login.php" class="login-link">Already have an account? Login</a>
</form>
</div>

<script>
function togglePassword() {
    const passField = document.getElementById('password');
    const toggle = document.querySelector('.password-toggle');
    if (passField.type === 'password') {
        passField.type = 'text';
        toggle.textContent = '🙈';
    } else {
        passField.type = 'password';
        toggle.textContent = '👁️';
    }
}

const passwordField = document.getElementById('password');
const strengthMessage = document.getElementById('strengthMessage');

passwordField.addEventListener('input', function() {
    const val = passwordField.value;
    let strength = "Weak";
    let color = "#d9534f";
    let score = 0;

    if (val.length >= 8) score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    if (score >= 5) {
        strength = "Strong";
        color = "#28a745";
    } else if (score >= 3) {
        strength = "Medium";
        color = "#ff9800";
    }

    strengthMessage.textContent = val.length > 0 ? "Strength: " + strength : "";
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

        const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!allowedTypes.includes(file.type)) {
            alert('Only JPG and PNG images are allowed');
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

document.getElementById('registerForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;

    if (password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long');
        return false;
    }

    if (!/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
        e.preventDefault();
        alert('Password must contain uppercase, lowercase, and numbers');
        return false;
    }
});
</script>
</body>
</html>
