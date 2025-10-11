<?php
// public/index.php
require_once __DIR__ . '/../config.php';
session_start();

// Detect login and role
$loggedIn = isset($_SESSION['user_id']);
$role = $_SESSION['role'] ?? 'guest';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Salonora - Find Salons Nearby</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap -->
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
          <?php if(!$loggedIn): ?>
            <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
            <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
          <?php else: ?>
            <?php if($role === 'owner'): ?>
              <li class="nav-item"><a class="nav-link" href="owner/dashboard.php">Dashboard</a></li>
            <?php else: ?>
              <li class="nav-item"><a class="nav-link" href="user/profile.php">Profile</a></li>
              <li class="nav-item"><a class="nav-link" href="user/appointment.php">My Appointments</a></li>
            <?php endif; ?>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php">Logout</a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

  <!-- 🔹 Search Section -->
  <div class="container mt-4 text-center">
    <h2 class="fw-bold mb-3">Find the Best Salons Near You 💇‍♀️</h2>
    <div class="search-bar input-group">
      <input id="searchBox" type="text" class="form-control" placeholder="Search by salon name or address...">
      <button id="searchBtn" class="btn btn-primary">Search</button>
    </div>
    <div id="map"></div>
  </div>

  <!-- 🔹 Footer -->
  <footer>
    <p>&copy; <?=date('Y')?> Salonora. All rights reserved.</p>
  </footer>

  <!-- 🔹 JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    let map, markers = [];

    function initMap() {
      const defaultCenter = { lat: 6.9271, lng: 79.8612 }; // Default to Colombo
      map = new google.maps.Map(document.getElementById('map'), {
        center: defaultCenter,
        zoom: 13
      });

      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
          const p = { lat: pos.coords.latitude, lng: pos.coords.longitude };
          map.setCenter(p);
          loadSalonsByLatLng(p.lat, p.lng);
        }, () => loadSalonsDefault());
      } else {
        loadSalonsDefault();
      }
    }

    function clearMarkers() {
      markers.forEach(m => m.setMap(null));
      markers = [];
    }

    function addMarker(salon) {
      const pos = { lat: parseFloat(salon.latitude), lng: parseFloat(salon.longitude) };
      const marker = new google.maps.Marker({ position: pos, map });
      const info = new google.maps.InfoWindow({
        content: `<div style='text-align:center;'>
                    <strong>${salon.name}</strong><br>
                    ${salon.address}<br>
                    <a href="user/salon_view.php?id=${salon.salon_id}" class="btn btn-sm btn-outline-primary mt-2">View Salon</a>
                  </div>`
      });
      marker.addListener('click', () => info.open(map, marker));
      markers.push(marker);
    }

    function loadSalonsByLatLng(lat, lng){
      fetch(`search.php?lat=${lat}&lng=${lng}`)
        .then(r => r.json())
        .then(data => {
          clearMarkers();
          data.forEach(s => addMarker(s));
        });
    }

    function loadSalonsDefault(){
      fetch('search.php')
        .then(r => r.json())
        .then(data => {
          clearMarkers();
          data.forEach(s => addMarker(s));
        });
    }

    document.getElementById('searchBtn').addEventListener('click', () => {
      const q = document.getElementById('searchBox').value.trim();
      if (!q) return;
      fetch(`search.php?q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(data => {
          clearMarkers();
          data.forEach(s => addMarker(s));
          if (data[0] && data[0].latitude) {
            map.setCenter({lat: parseFloat(data[0].latitude), lng: parseFloat(data[0].longitude)});
          }
        });
    });
  </script>

  <script src="https://maps.googleapis.com/maps/api/js?key=<?=GOOGLE_MAPS_API_KEY?>&callback=initMap" async defer></script>
</body>
</html>
