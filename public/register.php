<?php
// public/register.php
session_start();
require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = in_array($_POST['role'] ?? 'user', ['user','owner']) ? $_POST['role'] : 'user';
    $phone = $_POST['phone'] ?? '';

    if (!$name || !$email || !$password) {
        $error = "Name, email and password are required.";
    } else {
        // check existing
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email already registered.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hash, $phone, $role]);
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_name'] = $name;
            $_SESSION['role'] = $role;
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!-- Simple HTML form -->
<!doctype html><html><head><meta charset="utf-8"><title>Register</title></head><body>
<?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
<form method="post" action="">
  <input name="name" placeholder="Name" required><br>
  <input name="email" placeholder="Email" type="email" required><br>
  <input name="password" placeholder="Password" type="password" required><br>
  <input name="phone" placeholder="Phone"><br>
  <select name="role">
    <option value="user">Customer</option>
    <option value="owner">Salon Owner</option>
  </select><br>
  <button type="submit">Register</button>
</form>
</body></html>
