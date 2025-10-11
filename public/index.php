<?php
// public/index.php
require_once __DIR__ . '/../config.php';
session_start();

$loggedIn = isset($_SESSION['id']);
$role = $_SESSION['role'] ?? 'guest';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Salonora - Find Salons Nearby</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    #map { height: 500px; width: 100%; border-radius: 10px; margin-top: 15px; }
    .search-bar { max-width: 600px; margin: 20px auto; }
    body { background-color: #f9f9f9; }
    footer { margin-top: 40px; text-align: center; color: #777; padding: 10px 0; }
  </style>
</head>
<body>

<!-- 🔹 Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="index.php">Salonora</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <?php if (!$loggedIn): ?>
          <!-- Not logged in -->
          <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
          <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>

        <?php else: ?>
          <!-- Logged in -->
          <?php if ($role === 'owner'): ?>
            <li class="nav-item"><a class="nav-link" href="../owner/dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="../owner/salon_add.php">Add Salon</a></li>
            <li class="nav-item"><a class="nav-link" href="../owner/services.php">Services</a></li>

          <?php elseif ($role === 'customer'): ?>
            <li class="nav-item"><a class="nav-link" href="../user/profile.php">Profile</a></li>
            <li class="nav-item"><a class="nav-link" href="../user/appointment.php">My Appointments</a></li>
            <li class="nav-item"><a class="nav-link" href="../user/salon_view.php">Salons</a></li>
          <?php endif; ?>

          <li class="nav-item"><a class="nav-link text-danger" href="logout.php">Logout</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- 🔹 Rest of your content (search + map etc.) -->
<div class="container mt-4 text-center">
  <h2 class="fw-bold mb-3">Find the Best Salons Near You 💇‍♀️</h2>
  <div class="search-bar input-group">
    <input id="searchBox" type="text" class="form-control" placeholder="Search by salon name or address...">
    <button id="searchBtn" class="btn btn-primary">Search</button>
  </div>
  <div id="map"></div>
</div>

<footer>
  <p>&copy; <?= date('Y') ?> Salonora. All rights reserved.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>&callback=initMap" async defer></script>
</body>
</html>
