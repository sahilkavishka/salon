<?php
// public/index.php
require_once __DIR__ . '/../config.php';
session_start();

$loggedIn = isset($_SESSION['id']);
$role = $_SESSION['role'] ?? 'guest';
$userName = $_SESSION['user_name'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Salonora - Find Salons Nearby</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>#map{height:420px;border-radius:8px;margin-top:15px}</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">Salonora</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto">
        <?php if (!$loggedIn): ?>
          <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
          <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
        <?php else: ?>
          <?php if ($role === 'owner'): ?>
            <li class="nav-item"><a class="nav-link" href="owner/dashboard.php">Dashboard</a></li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link" href="user/profile.php">Profile</a></li>
            <li class="nav-item"><a class="nav-link" href="user/appointments.php">My Appointments</a></li>
            <li class="nav-item"><a class="nav-link" href="user/salon_view.php">salons</a></li>
          <?php endif; ?>
          <li class="nav-item"><a class="nav-link text-danger" href="logout.php">Logout</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container text-center mt-4">
  <h2>Find the Best Salons Near You</h2>
  <div class="input-group mb-3" style="max-width:640px;margin:0 auto;">
    <input id="searchBox" class="form-control" placeholder="Search by salon name or address">
    <button id="searchBtn" class="btn btn-primary">Search</button>
  </div>
  <div id="map"></div>
</div>

<footer class="text-center mt-4 mb-4">&copy; <?= date('Y') ?> Salonora</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
