<?php
session_start();
require '../includes/config.php'; // Database connection
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Salon Finder</title>
  <link rel="stylesheet" href="assets/css/style.css">

  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
</head>
<body>

  <!-- Header -->
  <header>
    <div class="header-left">
      <h1>Salon Finder</h1>
    </div>
    <div class="header-right">
      <?php if (isset($_SESSION['user_id'])): ?>
        <?php
          $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
          $stmt->execute([$_SESSION['user_id']]);
          $user_pic = $stmt->fetchColumn();
        ?>
        <?php if ($user_pic): ?>
          <img src="../uploads/<?= htmlspecialchars($user_pic) ?>" alt="Profile Picture" class="profile-pic">
        <?php endif; ?>
        <span>Welcome, <?= htmlspecialchars($_SESSION['username']) ?> </span>

        <?php if ($_SESSION['role'] === 'owner'): ?>
          <a href="add_salon.php">➕ Add Salon</a>
          <a href="manage_salons.php">⚙ Manage Salons</a>
        <?php endif; ?>

        <a href="logout.php">🚪 Logout</a>
      <?php else: ?>
        <a href="login.php">🔑 Login</a>
        <a href="register.php">📝 Register</a>
      <?php endif; ?>
    </div>
  </header>

  <!-- Search Section -->
  <form id="searchForm">
    <input id="locationInput" name="location" placeholder="Enter address (or use my location)">
    <select id="typeSelect" name="type">
      <option value="">All</option>
      <option value="beauty">Beauty</option>
      <option value="barber">Barber</option>
      <option value="spa">Spa</option>
    </select>
    <input type="number" id="radiusInput" name="radius" value="5" min="1"> km
    <button type="submit">🔍 Search</button>
    <button type="button" id="useLocation">📍 Use my location</button>
  </form>

  <!-- Map -->
  <div id="map" style="width:100%; height:550px;"></div>

  <!-- Leaflet JS -->
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="assets/js/map.js"></script>
</body>
</html>
