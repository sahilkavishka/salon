<?php
// public/user/profile.php

session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';

// Check user session
$id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (!$id) {
    header('Location: ../login.php');
    exit;
}

// Fetch user data
$stmt = $pdo->prepare('SELECT id, username AS name, email, phone, profile_picture, location, created_at 
                       FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) die('User not found.');

// Get stats
$stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE user_id = ?');
$stmt->execute([$id]);
$appointments_count = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM reviews WHERE user_id = ?');
$stmt->execute([$id]);
$reviews_count = $stmt->fetchColumn();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $profilePicturePath = $user['profile_picture'];

    if ($name === '') $errors[] = 'Name is required.';
    if ($phone !== '' && !preg_match('/^[0-9+\-\s]+$/', $phone)) {
        $errors[] = 'Phone number format is invalid.';
    }

    // Handle profile picture upload
    if (!empty($_FILES['profile_picture']['name'])) {

        $allowed = ['image/jpeg', 'image/png'];
        $fileType = $_FILES['profile_picture']['type'];
        $fileSize = $_FILES['profile_picture']['size'];

        if (!in_array($fileType, $allowed)) {
            $errors[] = "Only JPG and PNG images allowed.";
        }

        if ($fileSize > 2 * 1024 * 1024) {
            $errors[] = "Image must be below 2MB.";
        }

        if (empty($errors)) {
            $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $fileName = "profile_" . time() . "_" . rand(1000, 9999) . "." . $ext;

            $uploadPath = __DIR__ . '/../../uploads/profile/' . $fileName;

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadPath)) {
                $profilePicturePath = "uploads/profile/" . $fileName;
            } else {
                $errors[] = "Failed to upload profile image.";
            }
        }
    }

    if (empty($errors)) {

        $query = "UPDATE users SET username=?, phone=?, location=?, profile_picture=? WHERE id=?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$name, $phone, $location, $profilePicturePath, $id]);

        $_SESSION['user_name'] = $name;

        $success = "Profile updated successfully.";

        // Update values on page
        $user['name'] = $name;
        $user['phone'] = $phone;
        $user['location'] = $location;
        $user['profile_picture'] = $profilePicturePath;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile - Salonora</title>
  <?php include __DIR__ . '/../header.php'; ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/profile.css">
</head>

<body>

<div class="page-header">
    <div class="container">
        <h1 class="page-title">My Profile</h1>
    </div>
</div>

<div class="container pb-5 profile-container">

    <!-- Alerts -->
    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?>
            <?= htmlspecialchars($e) ?><br>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="profile-card">

      <!-- Profile Header -->
      <div class="profile-header">

        <div class="profile-avatar">
            <?php if ($user['profile_picture']): ?>
                <img src="../../<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile" class="img-fluid rounded-circle" 
                     style="width:120px;height:120px;object-fit:cover;">
            <?php else: ?>
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            <?php endif; ?>
        </div>

        <h2 class="profile-name"><?= htmlspecialchars($user['name']) ?></h2>

        <p class="profile-email">
            <i class="fas fa-envelope"></i>
            <?= htmlspecialchars($user['email']) ?>
        </p>

        <?php if (!empty($user['location'])): ?>
        <p class="profile-location">
            <i class="fas fa-map-marker-alt"></i>
            <?= htmlspecialchars($user['location']) ?>
        </p>
        <?php endif; ?>

        <div class="member-since">
          <i class="fas fa-calendar-alt"></i>
          Member since <?= date('M Y', strtotime($user['created_at'])) ?>
        </div>
      </div>

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-box">
          <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
          <span class="stat-number"><?= $appointments_count ?></span>
          <span class="stat-label">Total Appointments</span>
        </div>

        <div class="stat-box">
          <div class="stat-icon"><i class="fas fa-star"></i></div>
          <span class="stat-number"><?= $reviews_count ?></span>
          <span class="stat-label">Reviews Written</span>
        </div>

        <div class="stat-box">
          <div class="stat-icon"><i class="fas fa-trophy"></i></div>
          <span class="stat-number">Gold</span>
          <span class="stat-label">Membership Status</span>
        </div>
      </div>

      <!-- Edit Form -->
      <form method="post" enctype="multipart/form-data" class="form-section">
        <h3 class="section-title">
          <i class="fas fa-user-edit"></i> Edit Profile Information
        </h3>

        <label class="form-label"><i class="fas fa-user"></i> Full Name</label>
        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>

        <label class="form-label"><i class="fas fa-phone"></i> Phone Number</label>
        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>">

        <label class="form-label"><i class="fas fa-map-marker-alt"></i> Location</label>
        <select name="location" class="form-control" required>
            <option value="">Select your city</option>

            <?php
            $cities = [
                "Colombo", "Kandy", "Galle", "Matara", "Jaffna", "Negombo", "Anuradhapura",
                "Kurunegala", "Badulla", "Batticaloa", "Trincomalee", "Kegalle",
                "Ratnapura", "Hambantota", "Polonnaruwa", "Puttalam", "Mannar",
                "Nuwara Eliya", "Kalutara", "Gampaha"
            ];

            foreach ($cities as $city):
                $selected = ($user['location'] === $city) ? "selected" : "";
                echo "<option value='$city' $selected>$city</option>";
            endforeach;
            ?>
        </select>

        <label class="form-label"><i class="fas fa-image"></i> Profile Picture</label>
        <input type="file" name="profile_picture" class="form-control">

        <div class="text-center mt-3">
          <button type="submit" class="btn-save">
            <i class="fas fa-save"></i> Save Changes
          </button>
        </div>
      </form>

      <!-- Quick Actions -->
      <div class="quick-actions">
        <h4 class="section-title"><i class="fas fa-bolt"></i> Quick Actions</h4>
        <div class="action-buttons">
          <a href="my_appointments.php" class="btn-action btn-action-primary">
            <i class="fas fa-calendar-alt"></i> View Appointments
          </a>
          <a href="salon_view.php" class="btn-action btn-action-secondary">
            <i class="fas fa-search"></i> Browse Salons
          </a>
        </div>
      </div>

    </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
