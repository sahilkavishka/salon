<?php
// public/reset_password.php
session_start();
require_once __DIR__ . '/../config.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

// Fast token validation
$validToken = false;
$userId = null;

if ($token) {
    $stmt = $pdo->prepare("
        SELECT pr.user_id 
        FROM password_resets pr
        WHERE pr.token = ? AND pr.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $validToken = true;
        $userId = $row['user_id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Password Rules
    if (empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/\d/', $password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[^a-zA-Z\d]/', $password)) {
        $error = 'Password must contain at least one special character.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashedPassword, $userId]);
        $pdo->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reset Password - Salonora</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
body{
    background:linear-gradient(135deg,#6a11cb,#2575fc);
    min-height:100vh;display:flex;align-items:center;justify-content:center;
    font-family:'Poppins',sans-serif;padding:20px;
}
.reset-box{
    background:rgba(255,255,255,0.15);backdrop-filter:blur(18px);
    width:420px;border-radius:22px;border:1px solid rgba(255,255,255,0.25);
    padding:35px 40px;color:white;animation:fade .6s;
}
@keyframes fade{from{opacity:0;transform:translateY(25px);}to{opacity:1;transform:translateY(0);}}

.input-field{position:relative;margin-top:18px;}
.input-field input{
    width:100%;padding:14px 45px;border-radius:14px;font-size:1rem;
    background:rgba(255,255,255,0.18);border:1px solid rgba(255,255,255,0.3);
    color:white;outline:none;transition:.3s;
}
.input-field input:focus{background:rgba(255,255,255,0.28);border-color:white;}
.input-field i{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:#eee;}
.toggle-eye{left:auto;right:15px;cursor:pointer;}

.strength-bar{height:6px;background:rgba(255,255,255,0.25);border-radius:5px;margin-top:6px;overflow:hidden;}
.strength-fill{height:100%;width:0%;transition:.4s;}

.btn-submit{
    width:100%;margin-top:25px;padding:14px;border-radius:14px;border:none;
    background:linear-gradient(135deg,#ff7eb3,#ff758c);color:white;font-weight:600;
    box-shadow:0 5px 18px rgba(255,120,170,0.35);cursor:pointer;transition:.3s;
}
.btn-submit:hover{transform:translateY(-2px);}

.success-box{text-align:center;}
.success-box i{font-size:70px;color:#10b981;margin-bottom:10px;}
.success-box h3{font-weight:600;}
.success-box p{opacity:.9;}

</style>
</head>

<body>

<div class="reset-box">

<?php if(!$validToken): ?>
    <h3 class="text-center"><i class="fas fa-exclamation-triangle"></i> Invalid or Expired Link</h3>
    <p class="text-center">This reset link is no longer valid.</p>
    <a href="forgot_password.php" class="btn btn-light w-100 mt-3">Request New Link</a>

<?php elseif($success): ?>
    <div class="success-box">
        <i class="fas fa-check-circle"></i>
        <h3>Password Reset Successful</h3>
        <p>Redirecting to Login...</p>
    </div>
    <script>setTimeout(()=>location.href='login.php',2500);</script>

<?php else: ?>
    <h3 class="text-center">Reset Your Password</h3>

    <?php if($error): ?>
        <div class="alert alert-danger mt-3"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">

        <div class="input-field">
            <i class="fas fa-lock"></i>
            <input type="password" id="password" name="password" placeholder="New Password" required>
            <i class="fas fa-eye toggle-eye" onclick="toggle('password')"></i>
        </div>
        <div class="strength-bar"><div class="strength-fill" id="strength"></div></div>

        <div class="input-field">
            <i class="fas fa-lock"></i>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
            <i class="fas fa-eye toggle-eye" onclick="toggle('confirm_password')"></i>
        </div>

        <button class="btn-submit">Reset Password</button>
    </form>

    <center><a href="login.php" class="text-white mt-3 d-block">Back to Login</a></center>

<?php endif; ?>
</div>

<script>
function toggle(id){
    let f=document.getElementById(id);
    f.type=f.type==="password"?"text":"password";
}
document.getElementById('password').addEventListener('input',e=>{
    let v=e.target.value,s=0;
    if(v.length>=8)s++;if(/[A-Z]/.test(v))s++;if(/[a-z]/.test(v))s++;if(/\d/.test(v))s++;if(/[^a-zA-Z\d]/.test(v))s++;
    let bar=document.getElementById('strength');
    bar.style.width=[0,"25%","45%","65%","85%","100%"][s];
    bar.style.background=s<=2?"#ef4444":s<=3?"#fbbf24":"#10b981";
});
</script>

</body>
</html>
