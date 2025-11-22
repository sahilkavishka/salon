<?php
session_start();
require_once __DIR__ . '/../config.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $profilePicturePath = null;

    // Validations
    if ($username === '') $errors[] = "Full Name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if ($location === '') $errors[] = "Location is required.";

    // Duplicate email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) $errors[] = "Email already registered.";

    // Profile picture upload
    if (!empty($_FILES['profile_picture']['name'])) {
        $allowed = ['image/jpeg','image/png'];
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
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register - Salonora</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
/* Animated Background from login.php */
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

/* Registration card styling */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}
body{display:flex;align-items:center;justify-content:center;height:100vh;position:relative;overflow:hidden;}
.card{position:relative;z-index:1;background:#fff;width:400px;padding:2rem;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,0.1);transition:0.3s;}
.card:hover{box-shadow:0 12px 25px rgba(0,0,0,0.15);}
h2{text-align:center;margin-bottom:1.5rem;color:#fff;text-shadow:0 2px 4px rgba(0,0,0,0.2);}
.alert{border-radius:8px;padding:0.8rem;margin-bottom:1rem;font-size:0.9rem;}
.alert-danger{background:#ffe6e6;color:#d9534f;}
.alert-success{background:#e6ffed;color:#28a745;}
input,select{width:100%;padding:12px 15px;margin-top:8px;margin-bottom:15px;border:1.5px solid #ccc;border-radius:8px;transition:0.3s;font-size:0.95rem;}
input:focus,select:focus{border-color:#e91e63;outline:none;box-shadow:0 0 8px rgba(233,30,99,0.2);}
button{width:100%;padding:12px;border:none;border-radius:8px;background:#e91e63;color:#fff;font-weight:600;font-size:1rem;cursor:pointer;transition:0.3s;}
button:hover{background:#d81b60;}
.password-toggle{position:relative;}
.password-toggle i{position:absolute;right:15px;top:50%;transform:translateY(-50%);cursor:pointer;color:#888;}
#profilePreview{width:80px;height:80px;border-radius:50%;object-fit:cover;margin-top:10px;display:none;border:2px solid #e91e63;}
#strengthMessage{font-size:0.85rem;margin-top:-10px;margin-bottom:10px;color:#fff;}
a.login-link{text-decoration:none;color:#fff;font-weight:500;display:block;text-align:center;margin-top:10px;}
</style>
</head>
<body>
<div class="bg-animation"></div>
<div class="card">
<h2>Create Account</h2>

<?php if($errors): ?>
<div class="alert alert-danger"><?php foreach($errors as $e) echo htmlspecialchars($e)."<br>"; ?></div>
<?php endif; ?>

<?php if($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<input type="text" name="username" placeholder="Full Name" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
<input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>

<div class="password-toggle">
<input type="password" name="password" id="password" placeholder="Password" required>
<i onclick="togglePassword()">👁️</i>
<div id="strengthMessage"></div>
</div>

<input type="text" name="phone" placeholder="Phone (Optional)">
<select name="location" required>
<option value="">Select City</option>
<?php
$cities = ["Colombo","Kandy","Galle","Matara","Negombo","Kurunegala","Jaffna","Badulla","Batticaloa","Trincomalee","Anuradhapura","Puttalam","Hambantota","Polonnaruwa","Ratnapura","Gampaha","Kalutara","Mannar","Nuwara Eliya"];
foreach($cities as $c) echo "<option value='$c'".(($_POST['location']??'')==$c?' selected':'').">$c</option>";
?>
</select>

<label>Profile Picture (Optional)</label>
<input type="file" name="profile_picture" accept="image/*" onchange="previewProfile(this)">
<img id="profilePreview" src="#" alt="Preview">

<button type="submit">Register</button>
<a href="login.php" class="login-link">Already have an account? Login</a>
</form>
</div>

<script>
function togglePassword(){
    const pass = document.getElementById('password');
    pass.type = pass.type==='password'?'text':'password';
}

const password = document.getElementById('password');
const strengthMessage = document.getElementById('strengthMessage');
password.addEventListener('input', function(){
    const val = password.value;
    let strength="Weak", color="red";
    if(val.length>=6 && /[A-Z]/.test(val) && /[0-9]/.test(val)) {strength="Strong"; color="green";}
    else if(val.length>=6) {strength="Medium"; color="orange";}
    strengthMessage.textContent = "Strength: "+strength;
    strengthMessage.style.color=color;
});

function previewProfile(input){
    const preview=document.getElementById('profilePreview');
    if(input.files && input.files[0]){
        const reader=new FileReader();
        reader.onload=e=>{preview.src=e.target.result;preview.style.display='block';}
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
