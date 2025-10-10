<?php
require_once __DIR__ . '/../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = in_array($_POST['role'] ?? 'user', ['user','owner']) ? $_POST['role'] : 'user';

    if (!$name || !$email || !$password) {
        $error = "Name, email and password are required.";
    } else {
        // check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email already registered.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $email, $hash, $role]);

            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_name'] = $name;
            $_SESSION['role'] = $role;

            if ($role === 'owner') {
                header('Location: ../owner/dashboard.php');
            } else {
                header('Location: index.php');
            }
            exit;
        }
    }
}
?>

<!doctype html>
<html>
<head><meta charset="utf-8"><title>Register</title></head>
<body>
<h1>Register</h1>
<?php if (!empty($error)): ?>
    <p style="color:red;"><?=htmlspecialchars($error)?></p>
<?php endif; ?>
<form method="post">
    <input name="name" placeholder="Name" required><br>
    <input name="email" type="email" placeholder="Email" required><br>
    <input name="password" type="password" placeholder="Password" required><br>
    <select name="role">
        <option value="user">Customer</option>
        <option value="owner">Salon Owner</option>
    </select><br><br>
    <button type="submit">Register</button>
</form>
</body>
</html>
