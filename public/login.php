<?php
session_start();
require '../includes/config.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user["password"])) {
        session_regenerate_id(true); // Prevent session fixation
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["role"] = $user["role"];
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Salon Finder</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

<div class="login-container">
    <h2>Login</h2><br>

    <?php if (!empty($error)): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" class="login-form">

        <!-- Username -->
        <div class="form-group username">
            <label for="username" class="visually-hidden">Username</label>
            <input 
                type="text" 
                id="username" 
                name="username" 
                placeholder="Username" 
                required 
                autofocus
                aria-label="Username"
            >
        </div>

        <!-- Password -->
        <div class="form-group password">
            <label for="password" class="visually-hidden">Password</label>
            <input 
                type="password" 
                id="password" 
                name="password" 
                placeholder="Password" 
                required
                aria-label="Password"
            >
        </div>

        <button type="submit" class="submit-btn">Login</button>
    </form>

    <div class="register-link">
        Don't have an account? <a href="register.php">Register here</a>
    </div>
</div>

</body>
</html>
