<?php
session_start();
require '../includes/config.php';
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Salon Finder</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
  body { font-family: Arial, sans-serif; margin: 20px; }
  header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  nav a { margin-left: 15px; }
  form { margin-bottom: 20px; }
  #map { height: 450px; margin-bottom: 20px; }
  #results .salon { padding: 10px; border: 1px solid #ddd; margin-bottom: 10px; border-radius: 5px; }
</style>
</head>
<body>
<header>
  <h1>Salon Finder</h1>
  <nav>
    <?php if (isset($_SESSION['user_id'])): ?>
      <span>Welcome, <?= htmlspecialchars($_SESSION['username']) ?> (<?= $_SESSION['role'] ?>)</span>
      <?php if ($_SESSION['role'] === 'owner'): ?>
        <a href="add_salon.php">Add Salon</a>
        <a href="manage_salons.php">Manage Salons</a>
      <?php endif; ?>
      <a href="logout.php">Logout</a>
    <?php else: ?>
      <a href="login.php">Login</a>
      <a href="register.php">Register</a>
    <?php endif; ?>
  </nav>
</header>

<!-- 🔎 Search Form -->
<form id="searchForm">
  <input id="locationInput" name="location" placeholder="Enter address (or use my location)">
  <select id="typeSelect" name="type">
    <option value="">All</option>
    <option value="beauty">Beauty</option>
    <option value="barber">Barber</option>
    <option value="spa">Spa</option>
  </select>
  <input type="number" id="radiusInput" name="radius" value="5" min="1"> km
  <button type="submit">Search</button>
  <button type="button" id="useLocation">Use my location</button>
</form>

<!-- Map + Results -->
<div id="map"></div>
<div id="results"></div>

<script src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_API_KEY&callback=initMap" async defer></script>
<script src="assets/js/map.js"></script>
</body>
</html>
