<?php
session_start();
require_once __DIR__ . '/../config.php';

$error = '';
$success = '';
$show_reset_form = false;

// Step 1: User enters email
if (isset($_POST['step']) && $_POST['step'] === 'email') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid email address.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email=?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_username'] = $user['username'];
            $show_reset_form = true;
        } else {
            $error = "No account found with that email.";
        }
    }
}

// Step 2: User submits new password
if (isset($_POST['step']) && $_POST['step'] === 'reset') {
    if (!isset($_SESSION['reset_user_id'])) {
        $error = "Session expired. Please try again.";
    } else {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($password) || empty($confirm)) {
            $error = "Please fill in all fields.";
            $show_reset_form = true;
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match.";
            $show_reset_form = true;
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
            $show_reset_form = true;
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->execute([$hashed, $_SESSION['reset_user_id']]);

            unset($_SESSION['reset_user_id'], $_SESSION['reset_username']);
            $success = "Password updated successfully. You can now <a href='login.php'>login</a>.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Salonora</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(219, 112, 147, 0.1) 0%, transparent 70%);
            animation: float 20s infinite ease-in-out;
        }

        body::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255, 182, 193, 0.08) 0%, transparent 70%);
            animation: float 25s infinite ease-in-out reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(5deg); }
            66% { transform: translate(-20px, 20px) rotate(-5deg); }
        }

        .forgot-container {
            background: rgba(255, 255, 255, 0.98);
            padding: 50px 40px;
            border-radius: 20px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3),
                        0 0 40px rgba(219, 112, 147, 0.1);
            position: relative;
            z-index: 1;
            backdrop-filter: blur(10px);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo i {
            font-size: 50px;
            background: linear-gradient(135deg, #db7093 0%, #ff69b4 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        h2 {
            text-align: center;
            margin-bottom: 10px;
            color: #1a1a2e;
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 700;
        }

        .subtitle {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
            font-weight: 300;
        }

        form {
            display: flex;
            flex-direction: column;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #e91e63  ;
            font-size: 16px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #e91e63  ;
            background: white;
            box-shadow: 0 0 0 4px rgba(218, 165, 32, 0.1);
        }

        button {
            padding: 15px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #e91e63 0%, #9c27b0 );
            color: #1a1a2e;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
            position: relative;
            overflow: hidden;
        }

        button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        button:hover::before {
            left: 100%;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px  #9c27b0 ;
        }

        button:active {
            transform: translateY(0);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.4s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert i {
            font-size: 18px;
        }

        .alert-error {
            background: #fff5f5;
            color: #c53030;
            border-left: 4px solid #c53030;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-left: 4px solid #22c55e;
        }

        .alert a {
            color: inherit;
            font-weight: 600;
            text-decoration: underline;
        }

        .info-text {
            margin-bottom: 25px;
            font-size: 14px;
            color: #666;
            line-height: 1.6;
        }

        .welcome-text {
            margin-bottom: 25px;
            font-size: 15px;
            color: #333;
            padding: 15px;
            background: linear-gradient(135deg,#e91e63 0%, #9c27b0 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 600;
            text-align: center;
            border-radius: 8px;
        }

        .back-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e0e0e0;
        }

        .back-link a {
            color: #e91e63  ;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .back-link a:hover {
            gap: 8px;
            color: #e91e63  ;
        }

        @media (max-width: 480px) {
            .forgot-container {
                padding: 40px 30px;
            }

            h2 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>

<div class="forgot-container">
    <div class="logo">
        <i class="fas fa-spa"></i>
    </div>
    <h2>Forgot Password</h2>
    <p class="subtitle">Don't worry, we'll help you reset it</p>

    <?php if ($error): ?>
        <div class='alert alert-error'>
            <i class="fas fa-exclamation-circle"></i>
            <span><?= $error ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class='alert alert-success'>
            <i class="fas fa-check-circle"></i>
            <span><?= $success ?></span>
        </div>
    <?php else: ?>

        <?php if ($show_reset_form && isset($_SESSION['reset_username'])): ?>
            <div class="welcome-text">
                Hi <?= htmlspecialchars($_SESSION['reset_username']) ?>! 👋
            </div>
            <p class="info-text">Please enter your new password below. Make sure it's secure and at least 6 characters long.</p>
            <form method="POST">
                <input type="hidden" name="step" value="reset">
                
                <div class="form-group">
                    <label>New Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" required placeholder="Enter new password">
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="confirm_password" required placeholder="Confirm new password">
                    </div>
                </div>

                <button type="submit">
                    <i class="fas fa-key"></i> Reset Password
                </button>
            </form>
        <?php else: ?>
            <p class="info-text">Enter your email address and we'll help you reset your password securely.</p>
            <form method="POST">
                <input type="hidden" name="step" value="email">
                
                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" required placeholder="your@email.com">
                    </div>
                </div>

                <button type="submit">
                    Continue <i class="fas fa-arrow-right"></i>
                </button>
            </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>

    <?php endif; ?>
</div>

</body>
</html>