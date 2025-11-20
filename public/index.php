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

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Leaflet Map CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <?php include __DIR__. "../header.php"; ?>


  <!-- Hero Section -->
  <section class="hero-section">
    <div class="hero-overlay"></div>
    <div class="hero-particles" id="particles"></div>
    <div class="container hero-content">
      <div class="row align-items-center">
        <div class="col-lg-7">
          <span class="badge-premium mb-3">Premium Salon Experience</span>
          <h1 class="hero-title">
            Discover Your Perfect
            <span class="gradient-text">Beauty Destination</span>
          </h1>
          <p class="hero-subtitle">
            Connect with top-rated salons, explore exclusive services, and book your transformation today.
          </p>

          <!-- Enhanced Search Bar -->
<form id="searchForm" class="search-container">
  <div class="search-wrapper">
    <i class="fas fa-search search-icon"></i>
    <input type="text" id="searchInput" class="search-input" placeholder="Search salon name, service, or location..." required>

    <button class="btn-search" type="submit">
      <span>Search</span>
      <i class="fas fa-arrow-right ms-2"></i>
    </button>
  </div>
</form>



<!-- Salon List -->
<ul id="salonList" class="list-group mt-3"></ul>

<script src="map.js"></script>


          <!-- Quick Stats -->
          <div class="quick-stats">
            <div class="stat-item">
              <i class="fas fa-store"></i>
              <div>
                <strong>500+</strong>
                <span>Salons</span>
              </div>
            </div>
            <div class="stat-item">
              <i class="fas fa-star"></i>
              <div>
                <strong>4.8/5</strong>
                <span>Rating</span>
              </div>
            </div>
            <div class="stat-item">
              <i class="fas fa-users"></i>
              <div>
                <strong>10k+</strong>
                <span>Happy Clients</span>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-5 d-none d-lg-block">
          <div class="hero-image-container">
            <div class="floating-card card-1">
              <i class="fas fa-cut"></i>
              <span>Haircut & Styling</span>
            </div>
            <div class="floating-card card-2">
              <i class="fas fa-spa"></i>
              <span>Spa & Wellness</span>
            </div>
            <div class="floating-card card-3">
              <i class="fas fa-paint-brush"></i>
              <span>Makeup & Beauty</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Features Section -->
  <section class="features-section">
    <div class="container">
      <div class="row g-4">
        <div class="col-md-4">
          <div class="feature-card">
            <div class="feature-icon">
              <i class="fas fa-map-marked-alt"></i>
            </div>
            <h3>Find Nearby</h3>
            <p>Discover premium salons in your area with our interactive map</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="feature-card">
            <div class="feature-icon">
              <i class="fas fa-calendar-check"></i>
            </div>
            <h3>Easy Booking</h3>
            <p>Book appointments instantly with real-time availability</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="feature-card">
            <div class="feature-icon">
              <i class="fas fa-award"></i>
            </div>
            <h3>Top Rated</h3>
            <p>Access verified reviews and ratings from real customers</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Map Section -->
  <section class="map-section">
    <div class="container">
      <div class="section-header text-center mb-5">
        <span class="section-badge">Explore</span>
        <h2 class="section-title">Salons Near You</h2>
        <p class="section-subtitle">Find the perfect salon on our interactive map</p>
      </div>

      <div class="map-container">
        <div id="map"></div>
        <div class="map-controls">
          <button class="map-btn" id="locateMe" title="Find my location">
            <i class="fas fa-location-crosshairs"></i>
          </button>
          <button class="map-btn" id="fullscreen" title="Fullscreen">
            <i class="fas fa-expand"></i>
          </button>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA Section -->
  <section class="cta-section">
    <div class="container text-center">
      <div class="cta-content">
        <h2 class="cta-title">Ready to Transform Your Look?</h2>
        <p class="cta-subtitle">Join thousands of satisfied customers who found their perfect salon</p>
        <a href="user/salon_view.php" class="btn btn-cta">
          Browse Salons <i class="fas fa-arrow-right ms-2"></i>
        </a>
      </div> 
    </div>
  </section>
  <?php include __DIR__. "../footer.php"; ?>

  <!-- JS Libraries -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

  <!-- Custom Scripts -->
  <script>
// Navbar scroll effect
window.addEventListener('scroll', function () {
    const navbar = document.getElementById('mainNav');
    if (window.scrollY > 100) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

// Particles animation
function createParticles() {
    const container = document.getElementById('particles');
    for (let i = 0; i < 50; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.top = Math.random() * 100 + '%';
        particle.style.animationDelay = Math.random() * 15 + 's';
        particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
        container.appendChild(particle);
    }
}
createParticles();

// MAP
var map = L.map('map', { zoomControl: false }).setView([6.9271, 79.8612], 13);

L.control.zoom({ position: 'topright' }).addTo(map);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

// custom icon
var customIcon = L.divIcon({
    className: 'custom-marker',
    html: '<i class="fas fa-map-marker-alt"></i>',
    iconSize: [40, 40],
    iconAnchor: [20, 40],
    popupAnchor: [0, -40]
});

let markers = [];

// Remove all markers
function clearMarkers() {
    markers.forEach(m => map.removeLayer(m));
    markers = [];
}

// Add new marker
function addMarker(s) {
    var marker = L.marker([s.lat, s.lng], { icon: customIcon }).addTo(map);
    marker.bindPopup(`
        <div class="map-popup">
          <h6>${s.name}</h6>
          <p class="mb-1"><i class="fas fa-map-marker-alt me-1"></i> ${s.address}</p>
          <a href="user/salon_view.php?id=${s.id}" class="btn btn-sm btn-primary w-100">View Details</a>
        </div>
    `);
    markers.push(marker);
}

// ------- SEARCH FUNCTION -------
document.getElementById("searchForm").addEventListener("submit", function (e) {
    e.preventDefault();

    let q = document.getElementById("searchInput").value;
    let list = document.getElementById("salonList");

    fetch("search_api.php?query=" + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {

            clearMarkers();
            list.innerHTML = "";

            if (data.length === 0) {
                list.innerHTML = `
                    <li class="list-group-item text-danger">No salons found</li>
                `;
                return;
            }

            data.forEach(s => {
                addMarker(s);
                list.innerHTML += `
                    <li class="list-group-item">
                        <b>${s.name}</b><br>
                        <small>${s.address}</small>
                    </li>
                `;
            });

            map.setView([data[0].lat, data[0].lng], 15);
        });
});

// Locate Me
document.getElementById('locateMe').addEventListener('click', function () {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function (pos) {
            map.setView([pos.coords.latitude, pos.coords.longitude], 15);
            L.marker([pos.coords.latitude, pos.coords.longitude]).addTo(map)
                .bindPopup('You are here!').openPopup();
        });
    }
});

// Fullscreen
document.getElementById('fullscreen').addEventListener('click', function () {
    const mapContainer = document.querySelector('.map-container');
    if (!document.fullscreenElement) {
        mapContainer.requestFullscreen();
        this.innerHTML = '<i class="fas fa-compress"></i>';
    } else {
        document.exitFullscreen();
        this.innerHTML = '<i class="fas fa-expand"></i>';
    }
});
</script>

</body>

</html>