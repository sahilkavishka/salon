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
</head>
<body>

  <!-- Header -->
  <header>
    <h1>Salon Finder</h1>
    <nav>
      <?php if (isset($_SESSION['user_id'])): ?>
        <?php
          // Fetch user profile picture
          $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
          $stmt->execute([$_SESSION['user_id']]);
          $user_pic = $stmt->fetchColumn();
        ?>
        <?php if ($user_pic): ?>
          <img src="../uploads/<?= htmlspecialchars($user_pic) ?>" 
               alt="Profile Picture" 
               class="profile-pic">
        <?php endif; ?>
        <span>Welcome, <?= htmlspecialchars($_SESSION['username']) ?> (<?= htmlspecialchars($_SESSION['role']) ?>)</span>

        <?php if ($_SESSION['role'] === 'owner'): ?>
          <a href="add_salon.php">➕ Add Salon</a>
          <a href="manage_salons.php">⚙ Manage Salons</a>
        <?php endif; ?>

        <a href="logout.php">🚪 Logout</a>
      <?php else: ?>
        <a href="login.php">🔑 Login</a>
        <a href="register.php">📝 Register</a>
      <?php endif; ?>
    </nav>
  </header>

  <!-- Search Section -->
  <form id="searchForm">
    <input id="locationInput" name="location" 
           placeholder="Enter address (or use my location)">
    
    <select id="typeSelect" name="type">
      <option value="">All</option>
      <option value="beauty">Beauty</option>
      <option value="barber">Barber</option>
      <option value="spa">Spa</option>
    </select>
    
    <input type="number" id="radiusInput" name="radius" 
           value="5" min="1"> km
    
    <button type="submit">🔍 Search</button>
    <button type="button" id="useLocation">📍 Use my location</button>
  </form>

  <!-- Map and Results -->
  <div id="map"></div>
  <div id="results"></div>

  <!-- Scripts -->
  <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_API_KEY&callback=initMap" async defer></script>
  <script src="assets/js/map.js"></script>
</body>
</html>
