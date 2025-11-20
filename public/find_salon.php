<?php
session_start();
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <title>Salonora - Find Premium Salons Nearby</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: #f8f9fa;
    }
    #map {
      width: 100%;
      height: 500px;
      border-radius: 12px;
    }
    .hero {
      background: linear-gradient(135deg, #6a11cb, #2575fc);
      color: white;
      text-align: center;
      padding: 60px 20px;
    }
    .map-btn {
      background: #fff;
      border: none;
      border-radius: 50%;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      margin: 5px;
    }
    .map-controls {
      position: absolute;
      top: 10px;
      right: 10px;
      display: flex;
      flex-direction: column;
      z-index: 1000;
    }
  </style>
</head>

<body>
<?php include __DIR__ . '/../header.php'; ?>

<section class="hero">
  <h1>Find Premium Salons Near You</h1>
  <p>Search and explore top-rated salons instantly on the map.</p>
  <form id="searchForm" class="d-flex justify-content-center mt-4">
    <input type="text" id="searchInput" class="form-control w-50 me-2" placeholder="Search salon name or area...">
    <button class="btn btn-light">Search</button>
  </form>
</section>

<section class="container py-5">
  <div class="position-relative">
    <div id="map"></div>
    <div class="map-controls">
      <button class="map-btn" id="locateMe" title="Find my location"><i class="fas fa-location-crosshairs"></i></button>
    </div>
  </div>
</section>

<?php include __DIR__ . '/../footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
const map = L.map('map').setView([6.9271, 79.8612], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

const icon = L.divIcon({
  className: 'custom-marker',
  html: '<i class="fas fa-map-marker-alt" style="color:#e63946;font-size:24px;"></i>',
  iconSize: [30, 30],
  iconAnchor: [15, 30]
});

let markers = [];

// Function to display markers on map
function displaySalons(salons) {
  // Clear previous markers
  markers.forEach(m => map.removeLayer(m));
  markers = [];

  if (salons.length === 0) {
    alert('No salons found for your search.');
    return;
  }

  salons.forEach(salon => {
    if (salon.latitude && salon.longitude) {
      const marker = L.marker([salon.latitude, salon.longitude], { icon }).addTo(map);
      marker.bindPopup(`
        <div>
          <h6>${salon.name}</h6>
          <p><i class="fas fa-map-marker-alt me-1"></i>${salon.address}</p>
          <p><i class="fas fa-star me-1"></i>${salon.rating ?? 'N/A'} Rating</p>
          <a href="user/salon_view.php?id=${salon.id}" class="btn btn-sm btn-primary w-100">View Details</a>
        </div>
      `);
      markers.push(marker);
    }
  });

  // Center map around first salon
  map.setView([salons[0].latitude, salons[0].longitude], 13);
}

// Load all salons initially
fetch('search.php?all=1')
  .then(res => res.json())
  .then(data => displaySalons(data))
  .catch(() => alert('Error loading salons.'));


// Handle search form
document.getElementById('searchForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const query = document.getElementById('searchInput').value.trim();

  fetch('search.php?query=' + encodeURIComponent(query))
    .then(res => res.json())
    .then(data => displaySalons(data))
    .catch(() => alert('Search failed.'));
});

// Locate user
document.getElementById('locateMe').addEventListener('click', () => {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      const coords = [pos.coords.latitude, pos.coords.longitude];
      map.setView(coords, 15);
      L.marker(coords).addTo(map).bindPopup('You are here!').openPopup();
    });
  }
});
</script>
</body>
</html>
