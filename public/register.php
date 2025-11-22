<?php
session_start();
require_once __DIR__ . '/../../config.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $profilePicturePath = null;

    // Validate basic fields
    if ($username === '') $errors[] = "Username is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";

    // Validate profile picture upload
    if (!empty($_FILES['profile_picture']['name'])) {

        $allowed = ['image/jpeg', 'image/png'];
        $fileType = $_FILES['profile_picture']['type'];
        $fileSize = $_FILES['profile_picture']['size'];

        if (!in_array($fileType, $allowed)) {
            $errors[] = "Only JPG and PNG images are allowed.";
        }

        if ($fileSize > 2 * 1024 * 1024) {
            $errors[] = "Profile picture must be less than 2MB.";
        }

        if (empty($errors)) {
            $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $fileName = "profile_" . time() . "_" . rand(1000, 9999) . "." . $ext;
            $uploadPath = __DIR__ . '/../../uploads/profile/' . $fileName;

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadPath)) {
                $profilePicturePath = "uploads/profile/" . $fileName;
            } else {
                $errors[] = "Failed to upload profile picture.";
            }
        }
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Email already registered.";
    }

    // If no errors — Insert user
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $query = "INSERT INTO users (username, email, password, phone, profile_picture, location, role, created_at)
                  VALUES (?, ?, ?, ?, ?, ?, 'user', NOW())";

        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $username,
            $email,
            $hashedPassword,
            $phone,
            $profilePicturePath,
            $location
        ]);

        $success = "Registration successful! You can now log in.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - Salonora</title>
    <link rel="stylesheet" href="../assets/css/register.css">

    <!-- Google Maps for Autocomplete -->
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_API_KEY&libraries=places"></script>

    <script>
    function initAutocomplete() {
        const input = document.getElementById("location");
        const autocomplete = new google.maps.places.Autocomplete(input);
    }
    </script>
</head>

<body onload="initAutocomplete()">

<div class="container">

    <h2>Create an Account</h2>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?>
                <?= htmlspecialchars($e) ?><br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <label>Full Name</label>
        <input type="text" name="username" required>

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <label>Phone</label>
        <input type="text" name="phone">

        <label>Location (Google Autocomplete)</label>
        <input type="text" name="location" id="location" placeholder="Colombo, Sri Lanka">

        <label>Profile Picture</label>
        <input type="file" name="profile_picture" accept="image/*">

        <button type="submit" class="btn-submit">Register</button>

    </form>

</div>

</body>
</html>
