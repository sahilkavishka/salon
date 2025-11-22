<?php
// public/forgot_password.php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // HTTPS only
ini_set('session.use_strict_mode', 1);
session_start();

require_once __DIR__ . '/../config.php';

$error = '';
$success = '';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if required tables exist and create them if they don't
function ensureTablesExist($pdo) {
    try {
        // Check if password_reset_attempts table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_attempts'");
        if ($stmt->rowCount() === 0) {
            // Create the table
            $pdo->exec("
                CREATE TABLE password_reset_attempts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    created_at DATETIME NOT NULL,
                    INDEX idx_ip_time (ip_address, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        
        // Check if password_resets table has the correct structure
        $stmt = $pdo->query("SHOW TABLES LIKE 'password_resets'");
        if ($stmt->rowCount() === 0) {
            // Create the table
            $pdo->exec("
                CREATE TABLE password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token_hash VARCHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME NOT NULL,
                    used_at DATETIME NULL DEFAULT NULL,
                    UNIQUE KEY unique_user (user_id),
                    INDEX idx_token (token_hash),
                    INDEX idx_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Database setup error: " . $e->getMessage());
        return false;
    }
}

// Rate limiting: max 3 attempts per 15 minutes per IP
function checkRateLimit($pdo, $ip) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempt_count 
            FROM password_reset_attempts 
            WHERE ip_address = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$ip]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['attempt_count'] < 3;
    } catch (PDOException $e) {
        error_log("Rate limit check error: " . $e->getMessage());
        return true; // Allow if error (fail open, but log it)
    }
}

function logResetAttempt($pdo, $ip, $email) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO password_reset_attempts (ip_address, email, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$ip, $email]);
    } catch (PDOException $e) {
        error_log("Failed to log reset attempt: " . $e->getMessage());
    }
}

// Clean up expired tokens and old attempts
function cleanupExpiredData($pdo) {
    try {
        $pdo->exec("DELETE FROM password_resets WHERE expires_at < NOW()");
        $pdo->exec("DELETE FROM password_reset_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    } catch (PDOException $e) {
        error_log("Cleanup error: " . $e->getMessage());
    }
}

// Ensure tables exist before processing
if (!ensureTablesExist($pdo)) {
    $error = 'System configuration error. Please contact support.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }
    
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // Validate email
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($email) > 255) {
        $error = 'Email address is too long.';
    } else {
        // Check rate limit
        if (!checkRateLimit($pdo, $ip)) {
            $error = 'Too many reset attempts. Please try again in 15 minutes.';
        } else {
            try {
                // Log the attempt
                logResetAttempt($pdo, $ip, $email);
                
                // Clean up expired data occasionally (1% chance)
                if (rand(1, 100) === 1) {
                    cleanupExpiredData($pdo);
                }
                
                // ALWAYS show success message to prevent email enumeration
                $userExists = false;
                $user = null;
                
                $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $userExists = true;
                    
                    // Generate cryptographically secure token
                    $token = bin2hex(random_bytes(32));
                    
                    // Hash token before storing
                    $hashedToken = hash('sha256', $token);
                    
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Delete any existing tokens for this user
                    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
                    $stmt->execute([$user['id']]);
                    
                    // Store hashed token
                    $stmt = $pdo->prepare("
                        INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) 
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->execute([$user['id'], $hashedToken, $expires]);
                    
                    // Create reset link
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $resetLink = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/salonora/public/reset_password.php?token=" . urlencode($token);
                    
                    // Send email
                    $emailSent = sendPasswordResetEmail($user['email'], $user['username'], $resetLink);
                    
                    if (!$emailSent) {
                        error_log("Failed to send password reset email to: " . $user['email']);
                    }
                }
                
                // ALWAYS show the same success message
                usleep(rand(100000, 300000)); // 0.1-0.3 seconds delay
                
                $success = 'If an account exists with that email, a password reset link has been sent. Please check your email.';
                
                // Clear form
                $_POST['email'] = '';
                
                // Regenerate CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
            } catch (PDOException $e) {
                error_log("Password reset error: " . $e->getMessage());
                $error = 'An error occurred. Please try again later.';
            }
        }
    }
}

// Function to send password reset email
function sendPasswordResetEmail($to, $username, $resetLink) {
    $subject = "Password Reset Request - Salonora";
    
    $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f4f4f4; padding: 20px;'>
                <tr>
                    <td align='center'>
                        <table width='600' cellpadding='0' cellspacing='0' style='background-color: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                            <tr>
                                <td style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px; text-align: center;'>
                                    <h1 style='color: white; margin: 0; font-size: 28px;'>Password Reset</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 40px;'>
                                    <p style='font-size: 16px; color: #333; margin-bottom: 20px;'>Hi " . htmlspecialchars($username) . ",</p>
                                    <p style='font-size: 14px; color: #666; line-height: 1.6; margin-bottom: 30px;'>
                                        We received a request to reset your password for your Salonora account. 
                                        Click the button below to reset it:
                                    </p>
                                    <table width='100%' cellpadding='0' cellspacing='0'>
                                        <tr>
                                            <td align='center' style='padding: 20px 0;'>
                                                <a href='" . htmlspecialchars($resetLink) . "' 
                                                   style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                                          color: white; 
                                                          padding: 15px 40px; 
                                                          text-decoration: none; 
                                                          border-radius: 5px; 
                                                          display: inline-block;
                                                          font-weight: bold;'>
                                                    Reset Password
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style='font-size: 12px; color: #999; margin-top: 30px; padding-top: 30px; border-top: 1px solid #eee;'>
                                        Or copy and paste this link into your browser:<br>
                                        <span style='color: #667eea; word-break: break-all;'>" . htmlspecialchars($resetLink) . "</span>
                                    </p>
                                    <p style='font-size: 12px; color: #999; margin-top: 20px;'>
                                        This link will expire in <strong>1 hour</strong>.
                                    </p>
                                    <p style='font-size: 12px; color: #999; margin-top: 20px;'>
                                        If you didn't request this password reset, please ignore this email.
                                    </p>
                                    <p style='font-size: 14px; color: #333; margin-top: 30px;'>
                                        Thanks,<br>
                                        <strong>Salonora Team</strong>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style='background-color: #f8f8f8; padding: 20px; text-align: center;'>
                                    <p style='font-size: 12px; color: #999; margin: 0;'>
                                        © 2024 Salonora. All rights reserved.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: Salonora <noreply@salonora.com>" . "\r\n";
    $headers .= "Reply-To: support@salonora.com" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    
    return @mail($to, $subject, $message, $headers);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Forgot Password - Salonora</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.1)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,144C960,149,1056,139,1152,122.7C1248,107,1344,85,1392,74.7L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') repeat;
            animation: wave 20s linear infinite;
            opacity: 0.5;
        }

        @keyframes wave {
            0% { transform: translate(0, 0); }
            100% { transform: translate(-50%, 0); }
        }

        .forgot-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
            animation: slideUp 0.5s ease;
            position: relative;
            z-index: 1;
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

        .forgot-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .forgot-header i.fa-lock {
            font-size: 3rem;
            margin-bottom: 1rem;
            animation: shake 2s ease-in-out infinite;
        }

        @keyframes shake {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-5deg); }
            75% { transform: rotate(5deg); }
        }

        .forgot-header h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .forgot-header p {
            opacity: 0.95;
            font-size: 0.95rem;
        }

        .forgot-body {
            padding: 2.5rem 2rem 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transition: left 0.5s;
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert i {
            font-size: 1.2rem;
        }

        .alert-danger {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }

        .back-to-login {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e0e0e0;
        }

        .back-to-login a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .back-to-login a:hover {
            color: #764ba2;
            gap: 8px;
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            pointer-events: none;
        }

        .input-icon input {
            padding-left: 45px;
        }

        .security-note {
            background: #f5f5f5;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            font-size: 0.85rem;
            color: #666;
            display: flex;
            align-items: start;
            gap: 10px;
        }

        .security-note i {
            color: #667eea;
            margin-top: 2px;
        }
    </style>
</head>
<body>

<div class="forgot-container">
    <div class="forgot-header">
        <i class="fas fa-lock"></i>
        <h2>Forgot Password?</h2>
        <p>No worries, we'll send you reset instructions</p>
    </div>

    <div class="forgot-body">
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php else: ?>
            <form method="POST" action="" id="forgotForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-icon">
                        <i class="fas fa-envelope"></i>
                        <input 
                            type="email" 
                            class="form-control" 
                            id="email" 
                            name="email" 
                            placeholder="Enter your email"
                            required
                            maxlength="255"
                            value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                        >
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>

                <div class="security-note">
                    <i class="fas fa-shield-alt"></i>
                    <span>For security reasons, we'll send the reset link only if an account exists with this email.</span>
                </div>
            </form>
        <?php endif; ?>

        <div class="back-to-login">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Login</span>
            </a>
        </div>
    </div>
</div>

<script>
// Prevent multiple submissions
document.getElementById('forgotForm')?.addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    
    setTimeout(function() {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Reset Link';
    }, 3000);
});

// Auto-hide success message after 10 seconds
const successAlert = document.querySelector('.alert-success');
if (successAlert) {
    setTimeout(function() {
        successAlert.style.animation = 'fadeOut 0.5s ease';
        setTimeout(function() {
            successAlert.style.display = 'none';
        }, 500);
    }, 10000);
}
</script>

</body>
</html>