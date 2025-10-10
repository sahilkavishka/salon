<?php
// user/profile.php
session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
// fetch user
$stmt = $pdo->prepare("SELECT user_id, name, email, phone, role FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) die('User not found.');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if (!$name) $errors[] = "Name required.";

    if (empty($errors)) {
        $u = $pdo->prepare("UPDATE users SET name=?, phone=? WHERE user_id=?");
        $u->execute([$name, $phone, $user_id]);
        $_SESSION['user_name'] = $name;
        header('Location: profile.php');
        exit;
    }
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Profile</title></head>
<body>
  <h1>Profile</h1>
  <?php if ($errors) foreach ($errors as $e) echo "<p style='color:red;'>$e</p>"; ?>
  <form method="post">
    <label>Name</label><br>
    <input name="name" value="<?=htmlspecialchars($user['name'])?>" required><br>
    <label>Email (cannot change)</label><br>
    <input value="<?=htmlspecialchars($user['email'])?>" disabled><br>
    <label>Phone</label><br>
    <input name="phone" value="<?=htmlspecialchars($user['phone'])?>"><br><br>
    <button type="submit">Save Profile</button>
  </form>
</body>
</html>
