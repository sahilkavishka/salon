<?php
session_start();
require_once __DIR__ . '/../config.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $otp = trim($_POST['otp'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $profilePicturePath = null;

    // Basic validations
    if ($username === '') $errors[] = "Full Name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if ($location === '') $errors[] = "Location is required.";

    // Duplicate email check
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) $errors[] = "Email already registered.";

    // OTP handling
    if (empty($_SESSION['reg_otp']) || empty($_SESSION['otp_time'])) {
        $_SESSION['reg_otp'] = rand(100000, 999999);
        $_SESSION['otp_time'] = time();
        require_once __DIR__.'/otp_mailer.php';
        sendOTP($email, $_SESSION['reg_otp']);
        $errors[] = "OTP sent to your email. Please enter it to continue.";
    } elseif ($otp != $_SESSION['reg_otp'] || time() - $_SESSION['otp_time'] > 300) {
        $errors[] = "Invalid or expired OTP.";
    }

    // Profile picture upload
    if (!empty($_FILES['profile_picture']['name'])) {
        $allowed = ['image/jpeg', 'image/png'];
        $fileType = $_FILES['profile_picture']['type'];
        $fileSize = $_FILES['profile_picture']['size'];
        if (!in_array($fileType, $allowed)) $errors[] = "Only JPG and PNG allowed.";
        if ($fileSize > 2*1024*1024) $errors[] = "Max size 2MB.";

        if (empty($errors)) {
            $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $fileName = "profile_".time()."_".rand(1000,9999).".".$ext;
            $uploadPath = __DIR__.'/../../uploads/profile/'.$fileName;
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'],$uploadPath))
                $profilePicturePath = "uploads/profile/".$fileName;
            else $errors[] = "Failed to upload profile picture.";
        }
    }

    // Insert into DB
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username,email,password,phone,profile_picture,location,role,created_at) VALUES (?,?,?,?,?,?,'user',NOW())");
        $stmt->execute([$username,$email,$hashedPassword,$phone,$profilePicturePath,$location]);
        $success = "Registration successful! You can now log in.";
        session_unset();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register - Salonora</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
body{font-family:'Poppins',sans-serif;background:#f5f7fa;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;transition:0.5s;}
.container{width:100%;max-width:500px;padding:1.5rem;}
.card{background:white;border-radius:24px;padding:2.5rem 2rem;box-shadow:0 20px 60px rgba(0,0,0,0.2);transition:0.3s;}
h2{text-align:center;margin-bottom:1.5rem;color:#2d3436;}
.alert{border:none;border-radius:12px;padding:1rem;margin-bottom:1rem;}
.alert-danger{background:rgba(255,0,0,0.05);color:#d63031;border-left:4px solid #d63031;}
.alert-success{background:linear-gradient(135deg,#00b894 0%,#00cec9 100%);color:white;border-left:4px solid #00b894;}
input,select{width:100%;padding:12px;border-radius:10px;border:2px solid #e9ecef;margin-top:5px;transition:0.3s;}
input:focus,select:focus{border-color:#e91e63;outline:none;}
button{padding:12px;border:none;border-radius:12px;cursor:pointer;font-weight:700;margin-top:15px;background:#e91e63;color:white;width:100%;transition:0.3s;}
button:hover{opacity:0.9;}
.password-toggle{position:relative;}
.password-toggle i{position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:#888;}
#profilePreview{width:100px;height:100px;border-radius:50%;object-fit:cover;margin-top:10px;display:none;border:2px solid #e91e63;}
#strengthMessage{font-size:0.85rem;margin-top:5px;}
</style>
</head>
<body>
<div class="container">
<div class="card">
<h2>Create Account</h2>

<?php if($errors): ?>
<div class="alert alert-danger"><?php foreach($errors as $e) echo htmlspecialchars($e)."<br>"; ?></div>
<?php endif; ?>

<?php if($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="registerForm">
<label>Full Name</label>
<input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>

<label>Email</label>
<input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>

<label>OTP</label>
<input type="text" name="otp" placeholder="Enter OTP sent to your email" required>

<label>Password</label>
<div class="password-toggle">
<input type="password" name="password" id="password" required>
<i onclick="togglePassword()">👁️</i>
<div id="strengthMessage"></div>
</div>

<label>Phone</label>
<input type="text" name="phone">

<label>Location</label>
<select name="location" required>
<option value="">Select City</option>
<?php
$cities = ["Colombo","Kandy","Galle","Matara","Negombo","Kurunegala","Jaffna","Badulla","Batticaloa","Trincomalee","Anuradhapura","Puttalam","Hambantota","Polonnaruwa","Ratnapura","Gampaha","Kalutara","Mannar","Nuwara Eliya"];
foreach($cities as $c) echo "<option value='$c'".(($_POST['location']??'')==$c?' selected':'').">$c</option>";
?>
</select>

<label>Profile Picture</label>
<input type="file" name="profile_picture" accept="image/*" onchange="previewProfile(this)">
<img id="profilePreview" src="#" alt="Preview">

<button type="submit">Register</button>
</form>
</div>
</div>

<script>
function togglePassword(){
    const pass = document.getElementById('password');
    pass.type = pass.type === 'password' ? 'text' : 'password';
}

const password = document.getElementById('password');
const strengthMessage = document.getElementById('strengthMessage');
password.addEventListener('input', function(){
    const val = password.value;
    let strength = "Weak";
    let color = "red";
    if(val.length >= 6 && /[A-Z]/.test(val) && /[0-9]/.test(val)) {strength="Strong"; color="green";}
    else if(val.length >= 6) {strength="Medium"; color="orange";}
    strengthMessage.textContent = "Strength: " + strength;
    strengthMessage.style.color = color;
});

function previewProfile(input){
    const preview = document.getElementById('profilePreview');
    if(input.files && input.files[0]){
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
