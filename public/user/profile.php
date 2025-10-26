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
$stmt = $pdo->prepare('SELECT id, username AS name, email, phone, created_at FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die('User not found.');


// Get user statistics
$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM appointments WHERE user_id = ?');
$stmt->execute([$id]);
$appointments_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM reviews WHERE user_id = ?');
$stmt->execute([$id]);
$reviews_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '') $errors[] = 'Name is required.';
    if ($phone !== '' && !preg_match('/^[0-9+\-\s]+$/', $phone)) $errors[] = 'Phone can only contain numbers, +, - and spaces.';

    if (empty($errors)) {
        $u = $pdo->prepare('UPDATE users SET username=?, phone=? WHERE id=?');
        $u->execute([$name, $phone, $id]);
        $_SESSION['user_name'] = $name;
        $success = 'Profile updated successfully.';
        $user['name'] = $name;
        $user['phone'] = $phone;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile - Salonora</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/profile.css">
  
  
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg">
    <div class="container">
      <a class="navbar-brand" href="../../index.php">
        <i class="fas fa-spa"></i> Salonora
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav align-items-center">
          <li class="nav-item"><a href="../../index.php" class="nav-link">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="salon_view.php">Salons</a></li>
          <li class="nav-item"><a class="nav-link" href="my_appointments.php">Appointments</a></li>
          <li class="nav-item"><a class="nav-link active" href="profile.php">Profile</a></li>
          <li class="nav-item ms-3">
            <a href="../logout.php" class="btn btn-gradient btn-sm">
              <i class="fas fa-sign-out-alt me-1"></i> Logout
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Page Header -->
  <div class="page-header">
    <div class="container">
      <h1 class="page-title">My Profile</h1>
    </div>
  </div>

  <div class="container pb-5 profile-container">
    <!-- Alerts -->
    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <div>
          <?php foreach ($errors as $e): ?>
            <?= htmlspecialchars($e) ?><br>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?= htmlspecialchars($success) ?></span>
      </div>
    <?php endif; ?>

    <!-- Profile Card -->
    <div class="profile-card">
      <!-- Profile Header -->
      <div class="profile-header">
        <div class="profile-avatar">
          <?= strtoupper(substr($user['name'], 0, 1)) ?>
        </div>
        <h2 class="profile-name"><?= htmlspecialchars($user['name']) ?></h2>
        <p class="profile-email">
          <i class="fas fa-envelope"></i>
          <?= htmlspecialchars($user['email']) ?>
        </p>
        <div class="member-since">
          <i class="fas fa-calendar-alt"></i>
          Member since <?= date('M Y', strtotime($user['created_at'])) ?>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="stats-grid">
        <div class="stat-box">
          <div class="stat-icon">
            <i class="fas fa-calendar-check"></i>
          </div>
          <span class="stat-number"><?= $appointments_count ?></span>
          <span class="stat-label">Total Appointments</span>
        </div>
        <div class="stat-box">
          <div class="stat-icon">
            <i class="fas fa-star"></i>
          </div>
          <span class="stat-number"><?= $reviews_count ?></span>
          <span class="stat-label">Reviews Written</span>
        </div>
        <div class="stat-box">
          <div class="stat-icon">
            <i class="fas fa-trophy"></i>
          </div>
          <span class="stat-number">Gold</span>
          <span class="stat-label">Membership Status</span>
        </div>
      </div>

      <!-- Form Section -->
      <form method="post" class="form-section">
        <h3 class="section-title">
          <i class="fas fa-user-edit"></i>
          Edit Profile Information
        </h3>

        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-user"></i> Full Name
          </label>
          <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required placeholder="Enter your full name">
        </div>

        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-envelope"></i> Email Address
          </label>
          <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
          <div class="form-text">
            <i class="fas fa-info-circle"></i> Email address cannot be changed
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-phone"></i> Phone Number
          </label>
          <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+94 71 234 5678">
          <div class="form-text">
            <i class="fas fa-info-circle"></i> Optional. Numbers, +, - and spaces allowed
          </div>
        </div>

        <div class="text-center">
          <button type="submit" class="btn-save">
            <i class="fas fa-save"></i> Save Changes
          </button>
        </div>
      </form>

      <!-- Quick Actions -->
      <div class="quick-actions">
        <h4 class="section-title">
          <i class="fas fa-bolt"></i>
          Quick Actions
        </h4>
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

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
      });
    }, 5000);

    // Phone number formatting
    const phoneInput = document.querySelector('input[name="phone"]');
    if (phoneInput) {
      phoneInput.addEventListener('input', function(e) {
        let value = e.target.value;
        // Remove any characters that aren't numbers, +, -, or spaces
        value = value.replace(/[^0-9+\-\s]/g, '');
        e.target.value = value;
      });
    }
  </script>
</body>
</html>