<?php
session_start();
require '../config/db.php'; // adjust if needed
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Salon Finder</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    #map { height: 400px; width: 100%; margin-top: 20px; }
    .menu { margin-bottom: 15px; }
    .menu a { margin-right: 10px; }
  </style>
</head>
<body>
  <h1>Salon Finder</h1>

  <!-- ✅ Top Menu (role-based) -->
  <div class="menu">
    <?php if (isset($_SESSION["username"])): ?>
      <p>Welcome, <?= htmlspecialchars($_SESSION["username"]) ?> (<?= $_SESSION["role"] ?>)</p>
      <a href="logout.php">Logout</a>
      <?php if ($_SESSION["role"] === "owner"): ?>
        | <a href="add_salon.php">Add Salon</a>
        | <a href="manage_salon.php">Manage Salons</a>
      <?php endif; ?>
    <?php else: ?>
      <a href="login.php">Login</a> | <a href="register.php">Register</a>
    <?php endif; ?>
  </div>

  <!-- ✅ Search Form -->
  <form method="get" id="searchForm">
    <input type="text" name="query" id="query" placeholder="Enter location or salon name" required>
    <button type="submit">Search</button>
  </form>

  <!-- ✅ Salon Results -->
  <div id="results"></div>

  <!-- ✅ Google Map -->
  <div id="map"></div>

  <!-- ✅ JS Logic -->
  <script>
    let map;
    let markers = [];

    function initMap() {
      // Default center (Sri Lanka)
      map = new google.maps.Map(document.getElementById("map"), {
        center: { lat: 7.8731, lng: 80.7718 },
        zoom: 7,
      });
    }

    // ✅ Handle form submit with AJAX
    document.getElementById("searchForm").addEventListener("submit", function(e) {
      e.preventDefault();
      const query = document.getElementById("query").value;

      fetch("search.php?query=" + encodeURIComponent(query))
        .then(res => res.json())
        .then(data => {
          // Clear old results
          document.getElementById("results").innerHTML = "";
          markers.forEach(m => m.setMap(null));
          markers = [];

          if (data.length === 0) {
            document.getElementById("results").innerHTML = "<p>No salons found.</p>";
            return;
          }

          data.forEach(salon => {
            // Add to results list
            let div = document.createElement("div");
            div.innerHTML = `<strong>${salon.name}</strong><br>${salon.address}<br>Rating: ${salon.rating}<hr>`;
            document.getElementById("results").appendChild(div);

            // Add marker on map
            let marker = new google.maps.Marker({
              position: { lat: parseFloat(salon.latitude), lng: parseFloat(salon.longitude) },
              map: map,
              title: salon.name,
            });
            markers.push(marker);

            let infowindow = new google.maps.InfoWindow({
              content: `<b>${salon.name}</b><br>${salon.address}<br>Rating: ${salon.rating}`
            });
            marker.addListener("click", () => infowindow.open(map, marker));
          });

          // Focus map on first result
          map.setCenter({ lat: parseFloat(data[0].latitude), lng: parseFloat(data[0].longitude) });
          map.setZoom(13);
        });
    });
  </script>

  <!-- ✅ Google Maps API -->
  <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&callback=initMap" async defer></script>
</body>
</html>
