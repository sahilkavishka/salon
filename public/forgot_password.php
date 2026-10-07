<?php
// public/forgot_password.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';

$error = '';
$success = '';
$dev_reset_link = null;
$show_reset_form = false;
$valid_token = '';
$user_name = '';

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// -------------------------------------------------------------
// Check if user clicked a reset link (?token=...)
// -------------------------------------------------------------
$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));

if ($token !== '') {
    $stmt = $pdo->prepare("SELECT pr.email, pr.expires_at, u.username, u.id FROM password_resets pr JOIN users u ON pr.email = u.email WHERE pr.token = ? AND pr.expires_at > NOW() ORDER BY pr.id DESC LIMIT 1");
    $stmt->execute([$token]);
    $resetData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resetData) {
        $show_reset_form = true;
        $valid_token = $token;
        $user_name = $resetData['username'];
    } else {
        $error = "This password reset link is invalid or has expired. Please request a new link.";
    }
}

// -------------------------------------------------------------
// Handle Form Submissions
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    $posted_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_csrf)) {
        $error = "Invalid session security token. Please refresh the page.";
    } else {
        $step = $_POST['step'] ?? '';

        // Step 1: User requests reset link by email
        if ($step === 'email') {
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address.";
            } else {
                $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // Generate secure token
                    $newToken = bin2hex(random_bytes(32));
                    
                    // Invalidate old tokens for this email
                    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

                    // Store new token (expires in 1 hour)
                    $insertStmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
                    $insertStmt->execute([$email, $newToken]);

                    // Build reset link
                    $resetUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('forgot_password.php?token=' . $newToken);

                    // Send email
                    $mailSubject = "Reset Your Salonora Password";
                    $mailBody = "<p>Hi " . htmlspecialchars($user['username']) . ",</p>"
                              . "<p>You recently requested to reset your password for your Salonora account. Click the button below to reset it:</p>"
                              . "<p><a href='$resetUrl' style='background:#e91e63;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block;'>Reset Password</a></p>"
                              . "<p>Or copy this link: <br>$resetUrl</p>"
                              . "<p>This link is valid for 1 hour. If you did not request this, please ignore this email.</p>";

                    $mailResult = sendSystemMail($email, $user['username'], $mailSubject, $mailBody);

                    // If SMTP is disabled in dev mode, show the test link directly for convenience
                    if (!SMTP_ENABLED || $mailResult['mode'] === 'simulated') {
                        $dev_reset_link = $resetUrl;
                    }
                }

                // Show safe message (does not reveal if email exists or not)
                $success = "If that email is registered in our system, you will receive password reset instructions shortly.";
            }
        }

        // Step 2: User submits new password using valid token
        if ($step === 'reset') {
            if (!$show_reset_form || empty($valid_token)) {
                $error = "Session expired or invalid reset token. Please request a new link.";
            } else {
                $password = $_POST['password'] ?? '';
                $confirm = $_POST['confirm_password'] ?? '';

                if (empty($password) || empty($confirm)) {
                    $error = "Please fill in all fields.";
                } elseif ($password !== $confirm) {
                    $error = "Passwords do not match.";
                } elseif (strlen($password) < 8) {
                    $error = "Password must be at least 8 characters long.";
                } else {
                    // Update password securely
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                    $updateStmt->execute([$hashed, $resetData['email']]);

                    // Clear used token
                    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$resetData['email']]);

                    // Notify user in-app
                    notifyUser($pdo, $resetData['id'], "Your account password was successfully reset.");

                    $success = "Password updated successfully! You can now <a href='" . url('login.php') . "' style='color:#e91e63;font-weight:600;'>Login</a> with your new password.";
                    $show_reset_form = false;
                }
            }
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
            overflow-x: hidden;
        }

        .forgot-container {
            background: rgba(255, 255, 255, 0.98);
            padding: 50px 40px;
            border-radius: 20px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3),
                        0 0 40px rgba(219, 112, 147, 0.1);
            position: relative;
            z-index: 1;
            backdrop-filter: blur(10px);
        }

        .logo {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo i {
            font-size: 50px;
            background: linear-gradient(135deg, #db7093 0%, #ff69b4 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        h2 {
            text-align: center;
            margin-bottom: 10px;
            color: #1a1a2e;
            font-family: 'Playfair Display', serif;
            font-size: 30px;
            font-weight: 700;
        }

        .subtitle {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 25px;
            font-weight: 300;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            line-height: 1.5;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .alert-dev {
            background: #fff8e1;
            color: #f57f17;
            border: 1px solid #ffe082;
            font-size: 13px;
            word-break: break-all;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #e91e63;
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
            background: #fafafa;
            transition: all 0.3s ease;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #e91e63;
            background: white;
            box-shadow: 0 0 0 4px rgba(233, 30, 99, 0.1);
        }

        button {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(156, 39, 176, 0.3);
        }

        .back-link {
            text-align: center;
            margin-top: 25px;
        }

        .back-link a {
            color: #e91e63;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .back-link a:hover {
            color: #9c27b0;
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="forgot-container">
    <div class="logo">
        <i class="fas fa-spa"></i>
    </div>
    <h2>Reset Password</h2>
    <p class="subtitle">Secure account recovery for Salonora</p>

    <?php if ($error): ?>
        <div class='alert alert-error'>
            <i class="fas fa-exclamation-circle mt-1"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class='alert alert-success'>
            <i class="fas fa-check-circle mt-1"></i>
            <div><?= $success ?></div>
        </div>

        <?php if ($dev_reset_link): ?>
            <div class='alert alert-dev'>
                <div>
                    <strong><i class="fas fa-flask"></i> Development Mode Test Link:</strong><br>
                    <a href="<?= htmlspecialchars($dev_reset_link) ?>" style="color:#d81b60;word-break:break-all;">Click here to simulate opening the email reset link</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="back-link">
            <a href="<?= url('login.php') ?>">
                <i class="fas fa-arrow-left"></i> Return to Login
            </a>
        </div>
    <?php else: ?>

        <?php if ($show_reset_form): ?>
            <p style="font-size:14px;color:#555;margin-bottom:20px;">
                Hello <strong><?= htmlspecialchars($user_name) ?></strong>, enter your new password below (minimum 8 characters).
            </p>
            <form method="POST" action="">
                <input type="hidden" name="step" value="reset">
                <input type="hidden" name="token" value="<?= htmlspecialchars($valid_token) ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="form-group">
                    <label>New Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" required minlength="8" placeholder="At least 8 characters">
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="confirm_password" required minlength="8" placeholder="Repeat your new password">
                    </div>
                </div>

                <button type="submit">
                    <i class="fas fa-key me-1"></i> Update Password
                </button>
            </form>
        <?php else: ?>
            <p style="font-size:14px;color:#555;margin-bottom:20px;">
                Enter your registered email address below, and we will generate a secure reset link.
            </p>
            <form method="POST" action="">
                <input type="hidden" name="step" value="email">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" required placeholder="your@email.com">
                    </div>
                </div>

                <button type="submit">
                    Send Reset Link <i class="fas fa-arrow-right ms-1"></i>
                </button>
            </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="<?= url('login.php') ?>">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>

    <?php endif; ?>
</div>

</body>
</html>