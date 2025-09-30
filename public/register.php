<?php
require '../includes/config.php'; // adjust path if needed

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = password_hash($_POST["password"], PASSWORD_BCRYPT);
    $role = $_POST["role"];

    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    if ($stmt->execute([$username, $password, $role])) {
        echo "Registered successfully. <a href='login.php'>Login here</a>";
    } else {
        echo "Error: Could not register user.";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Register</title></head>
<body>
<h2>Register</h2>
<form method="post">
  <input type="text" name="username" placeholder="Username" required><br>
  <input type="password" name="password" placeholder="Password" required><br>
  <select name="role">
    <option value="user">User</option>
    <option value="owner">Owner</option>
  </select><br>
  <button type="submit">Register</button>
</form>
</body>
</html>
