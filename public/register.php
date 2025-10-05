<?php
require '../includes/config.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = password_hash($_POST["password"], PASSWORD_BCRYPT);
    $role = $_POST["role"];

    // Check if username already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        $error = "Username already exists. Please choose another.";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Email already registered. Please use another.";
        }
    }

    // Handle profile picture upload
    if (!$error) {
        $profile_pic = null;
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            if (in_array($file_ext, $allowed)) {
                $file_name = uniqid() . '.' . $file_ext;
                $upload_dir = '../uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $file_name);
                $profile_pic = $file_name;
            } else {
                $error = "Invalid file type. Only JPG, PNG, GIF allowed.";
            }
        }
    }

    // Insert new user
    if (!$error) {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, profile_pic) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$username, $email, $password, $role, $profile_pic])) {
            $success = "Registered successfully. <a href='login.php'>Login here</a>";
        } else {
            $error = "Error: Could not register user.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - Salon Finder</title>
<link rel="stylesheet" href="assets/css/register.css">
</head>
<body>

<div class="register-container">
    <h2>Register</h2>

    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success-message"><?= $success ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="text" name="username" placeholder="Username" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <select name="role" required>
            <option value="">Select Role</option>
            <option value="user">User</option>
            <option value="owner">Owner</option>
        </select>
        <label for="profile_pic">Profile Picture (optional):</label>
        <input type="file" name="profile_pic" id="profile_pic" accept="image/*">
        <button type="submit">Register</button>
    </form>

    <div class="login-link">
        Already have an account? <a href="login.php">Login here</a>
    </div>
</div>

</body>
</html>
