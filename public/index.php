<?php
session_start();
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Salonora - Find Salons Nearby</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- ✅ Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- ✅ Leaflet Map CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

  <!-- ✅ Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- ✅ Custom CSS -->
  <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
  <!-- ✅ Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm fixed-top">
    <div class="container">
      <a class="navbar-brand" href="#">💇‍♀️ Salonora</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav align-items-center">
          <li class="nav-item"><a href="index.php" class="nav-link active">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="user/salon_view.php">💈 Salons</a></li>
          <li class="nav-item"><a class="nav-link" href="user/my_appointments.php">📅 Appointments</a></li>
          <li class="nav-item"><a class="nav-link" href="user/profile.php">👤 Profile</a></li>
          <li class="nav-item"><a href="#" class="nav-link">Contact</a></li>
          <?php if (isset($_SESSION['id'])): ?>
            <li class="nav-item ms-3">
              <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
            </li>
          <?php else: ?>
            <li class="nav-item ms-3">
              <a href="login.php" class="btn btn-custom">Login</a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

  <!-- ✅ Hero Section -->
  <section class="hero d-flex align-items-center justify-content-center text-center">
    <div class="container">
      <h1 class="display-5 fw-bold text-white fade-in">Find the Perfect Salon Near You</h1>
      <p class="lead text-light mb-4 fade-in-delay">Search salons, view their services, and book appointments easily.</p>
      <form class="search-bar d-flex justify-content-center" action="search.php" method="GET">
        <input type="text" name="query" class="form-control w-50" placeholder="Search by salon name or location" required>
        <button class="btn btn-primary ms-2 px-4">Search</button>
      </form>
    </div>
  </section>

  <!-- ✅ Map Section -->
  <div class="container map-wrapper mb-5">
    <h3 class="text-center mt-5 mb-3 fw-bold">Explore Salons on the Map</h3>
    <div id="map"></div>
  </div>

  <!-- ✅ Footer -->
  <footer class="text-center text-light py-4">
    <p class="mb-0">© <?php echo date("Y"); ?> Salonora. All rights reserved.</p>
  </footer>

  <!-- ✅ JS Libraries -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

  <!-- ✅ Leaflet Map Script -->
  <script>
    // Initialize map centered on Colombo
    var map = L.map('map').setView([6.9271, 79.8612], 12);

    // Base layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Example salon markers (replace with DB data)
    var salons = [
      { name: "Salon Elegance", lat: 6.9271, lng: 79.8612, address: "Colombo 07" },
      { name: "Beauty Bliss", lat: 6.9350, lng: 79.8560, address: "Colombo 03" },
      { name: "Hair & Glow", lat: 6.9205, lng: 79.8789, address: "Nugegoda" },
      { name: "Glam Studio", lat: 6.9365, lng: 79.8471, address: "Bambalapitiya" }
    ];

    salons.forEach(salon => {
      var marker = L.marker([salon.lat, salon.lng]).addTo(map);
      marker.bindPopup(`<b>${salon.name}</b><br>${salon.address}`);
    });
  </script>
</body>
</html>
