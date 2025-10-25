<?php
// public/user/profile.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';

$id = $_SESSION['id'];

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
  
  <style>
    :root {
      --primary: #e91e63;
      --primary-dark: #c2185b;
      --secondary: #9c27b0;
      --accent: #ff6b9d;
      --dark: #1a1a2e;
      --light: #f5f7fa;
      --text-dark: #2d3436;
      --text-light: #636e72;
      --gradient-primary: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
      --gradient-secondary: linear-gradient(135deg, #ff6b9d 0%, #c471ed 100%);
      --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
      --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.12);
      --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.15);
      --shadow-xl: 0 20px 60px rgba(0, 0, 0, 0.2);
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: var(--light);
      color: var(--text-dark);
    }

    /* Navbar */
    .navbar {
      background: white !important;
      box-shadow: var(--shadow-sm);
      padding: 1rem 0;
    }

    .navbar-brand {
      font-size: 1.5rem;
      font-weight: 800;
      background: var(--gradient-primary);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .navbar-brand i {
      background: var(--gradient-primary);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .nav-link {
      color: var(--text-dark);
      font-weight: 500;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      transition: var(--transition);
    }

    .nav-link:hover, .nav-link.active {
      background: rgba(233, 30, 99, 0.1);
      color: var(--primary);
    }

    .btn-gradient {
      background: var(--gradient-primary);
      color: white;
      border: none;
      padding: 0.5rem 1.5rem;
      border-radius: 50px;
      font-weight: 600;
      transition: var(--transition);
    }

    .btn-gradient:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
      color: white;
    }

    /* Page Header */
    .page-header {
      background: var(--gradient-primary);
      padding: 3rem 0 5rem;
      margin-bottom: -60px;
      position: relative;
      overflow: hidden;
    }

    .page-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.1)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,144C960,149,1056,139,1152,122.7C1248,107,1344,85,1392,74.7L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
      opacity: 0.5;
    }

    .page-title {
      font-size: 2rem;
      font-weight: 800;
      color: white;
      text-align: center;
      position: relative;
      z-index: 2;
    }

    /* Profile Container */
    .profile-container {
      position: relative;
      z-index: 10;
    }

    /* Profile Card */
    .profile-card {
      background: white;
      border-radius: 24px;
      box-shadow: var(--shadow-xl);
      overflow: hidden;
      margin-bottom: 2rem;
    }

    .profile-header {
      background: linear-gradient(135deg, rgba(233, 30, 99, 0.05) 0%, rgba(156, 39, 176, 0.05) 100%);
      padding: 3rem 2rem 2rem;
      text-align: center;
      position: relative;
    }

    .profile-avatar {
      width: 120px;
      height: 120px;
      background: var(--gradient-primary);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      font-size: 3rem;
      font-weight: 800;
      color: white;
      box-shadow: var(--shadow-lg);
      border: 5px solid white;
    }

    .profile-name {
      font-size: 2rem;
      font-weight: 800;
      color: var(--text-dark);
      margin-bottom: 0.5rem;
    }

    .profile-email {
      color: var(--text-light);
      font-size: 1rem;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .profile-email i {
      color: var(--primary);
    }

    .member-since {
      margin-top: 1rem;
      padding: 0.75rem 1.5rem;
      background: white;
      border-radius: 50px;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--text-light);
      font-size: 0.9rem;
      box-shadow: var(--shadow-sm);
    }

    .member-since i {
      color: var(--primary);
    }

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      padding: 2rem;
    }

    .stat-box {
      text-align: center;
      padding: 1.5rem;
      background: linear-gradient(135deg, rgba(233, 30, 99, 0.05) 0%, rgba(156, 39, 176, 0.05) 100%);
      border-radius: 16px;
      transition: var(--transition);
    }

    .stat-box:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-md);
    }

    .stat-icon {
      width: 60px;
      height: 60px;
      background: var(--gradient-primary);
      border-radius: 15px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1rem;
    }

    .stat-icon i {
      font-size: 1.5rem;
      color: white;
    }

    .stat-number {
      font-size: 2rem;
      font-weight: 800;
      background: var(--gradient-primary);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      display: block;
      margin-bottom: 0.25rem;
    }

    .stat-label {
      color: var(--text-light);
      font-size: 0.95rem;
    }

    /* Form Section */
    .form-section {
      padding: 2rem;
    }

    .section-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .section-title i {
      width: 40px;
      height: 40px;
      background: var(--gradient-primary);
      color: white;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-label {
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 0.75rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .form-label i {
      color: var(--primary);
      font-size: 0.9rem;
    }

    .form-control {
      border: 2px solid #e9ecef;
      border-radius: 12px;
      padding: 1rem 1.25rem;
      font-size: 1rem;
      transition: var(--transition);
    }

    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
      outline: none;
    }

    .form-control:read-only {
      background: #f8f9fa;
      cursor: not-allowed;
    }

    .form-text {
      color: var(--text-light);
      font-size: 0.85rem;
      margin-top: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .form-text i {
      font-size: 0.75rem;
    }

    .btn-save {
      background: var(--gradient-primary);
      color: white;
      border: none;
      padding: 1rem 3rem;
      border-radius: 50px;
      font-weight: 700;
      font-size: 1.1rem;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 0.75rem;
      box-shadow: var(--shadow-md);
    }

    .btn-save:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(233, 30, 99, 0.4);
      color: white;
    }

    /* Alert Styling */
    .alert {
      border: none;
      border-radius: 16px;
      padding: 1.25rem 1.5rem;
      margin-bottom: 2rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      animation: slideIn 0.3s ease;
    }

    .alert i {
      font-size: 1.5rem;
    }

    .alert-success {
      background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
      color: white;
    }

    .alert-danger {
      background: linear-gradient(135deg, #d63031 0%, #e17055 100%);
      color: white;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Quick Actions */
    .quick-actions {
      padding: 2rem;
      background: linear-gradient(135deg, rgba(233, 30, 99, 0.05) 0%, rgba(156, 39, 176, 0.05) 100%);
      border-top: 1px solid #e9ecef;
    }

    .action-buttons {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .btn-action {
      flex: 1;
      min-width: 200px;
      padding: 1rem 1.5rem;
      border-radius: 12px;
      font-weight: 600;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      text-decoration: none;
    }

    .btn-action-primary {
      background: white;
      color: var(--primary);
      border: 2px solid var(--primary);
    }

    .btn-action-primary:hover {
      background: var(--primary);
      color: white;
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .btn-action-secondary {
      background: white;
      color: var(--secondary);
      border: 2px solid var(--secondary);
    }

    .btn-action-secondary:hover {
      background: var(--secondary);
      color: white;
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    /* Responsive */
    @media (max-width: 768px) {
      .page-title {
        font-size: 1.75rem;
      }

      .profile-name {
        font-size: 1.5rem;
      }

      .profile-avatar {
        width: 100px;
        height: 100px;
        font-size: 2.5rem;
      }

      .stats-grid {
        grid-template-columns: 1fr;
      }

      .form-section {
        padding: 1.5rem;
      }

      .btn-save {
        width: 100%;
        justify-content: center;
      }

      .action-buttons {
        flex-direction: column;
      }

      .btn-action {
        width: 100%;
      }
    }
  </style>
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