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
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg fixed-top" id="mainNav">
    <div class="container">
      <a class="navbar-brand" href="index.php">
        <i class="fas fa-spa"></i> Salonora
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav align-items-center">
          <li class="nav-item"><a href="index.php" class="nav-link active">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="user/salon_view.php"><i class="fas fa-cut me-1"></i> Salons</a></li>
          <li class="nav-item"><a class="nav-link" href="user/my_appointments.php"><i class="far fa-calendar-check me-1"></i> Appointments</a></li>
          <li class="nav-item"><a class="nav-link" href="user/profile.php"><i class="far fa-user me-1"></i> Profile</a></li>
          <li class="nav-item"><a href="#contact" class="nav-link">Contact</a></li>
          <?php if (isset($_SESSION['id'])): ?>
            <li class="nav-item ms-3">
              <a href="logout.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i> Logout
              </a>
            </li>
          <?php else: ?>
            <li class="nav-item ms-3">
              <a href="login.php" class="btn btn-gradient">
                <i class="fas fa-sign-in-alt me-1"></i> Login
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

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
          <form class="search-container" action="search.php" method="GET">
            <div class="search-wrapper">
              <i class="fas fa-search search-icon"></i>
              <input type="text" name="query" class="search-input" placeholder="Search salon name, service, or location..." required>
              <button class="btn-search" type="submit">
                <span>Search</span>
                <i class="fas fa-arrow-right ms-2"></i>
              </button>
            </div>
          </form>

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

  <!-- Footer -->
  <footer class="footer" id="contact">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-4">
          <h5 class="footer-brand"><i class="fas fa-spa"></i> Salonora</h5>
          <p class="footer-desc">Your trusted platform for discovering and booking premium salon services.</p>
          <div class="social-links">
            <a href="#"><i class="fab fa-facebook-f"></i></a>
            <a href="#"><i class="fab fa-instagram"></i></a>
            <a href="#"><i class="fab fa-twitter"></i></a>
            <a href="#"><i class="fab fa-linkedin-in"></i></a>
          </div>
        </div>
        <div class="col-lg-2 col-md-6">
          <h6 class="footer-heading">Quick Links</h6>
          <ul class="footer-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="user/salon_view.php">Salons</a></li>
            <li><a href="#">Services</a></li>
            <li><a href="#">About Us</a></li>
          </ul>
        </div>
        <div class="col-lg-2 col-md-6">
          <h6 class="footer-heading">Support</h6>
          <ul class="footer-links">
            <li><a href="#">Help Center</a></li>
            <li><a href="#">Privacy Policy</a></li>
            <li><a href="#">Terms of Service</a></li>
            <li><a href="#">Contact</a></li>
          </ul>
        </div>
        <div class="col-lg-4">
          <h6 class="footer-heading">Newsletter</h6>
          <p class="footer-desc">Subscribe for exclusive offers and updates</p>
          <form class="newsletter-form">
            <input type="email" placeholder="Your email address" required>
            <button type="submit"><i class="fas fa-paper-plane"></i></button>
          </form>
        </div>
      </div>
      <hr class="footer-divider">
      <div class="text-center">
        <p class="footer-copyright">© <?php echo date("Y"); ?> Salonora. All rights reserved. Made with <i class="fas fa-heart"></i></p>
      </div>
    </div>
  </footer>

  <!-- JS Libraries -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

  <!-- Custom Scripts -->
  <script>
    // Navbar scroll effect
    window.addEventListener('scroll', function() {
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

    // Initialize map
    var map = L.map('map', {
      zoomControl: false
    }).setView([6.9271, 79.8612], 13);

    L.control.zoom({
      position: 'topright'
    }).addTo(map);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Custom marker icon
    var customIcon = L.divIcon({
      className: 'custom-marker',
      html: '<i class="fas fa-map-marker-alt"></i>',
      iconSize: [40, 40],
      iconAnchor: [20, 40],
      popupAnchor: [0, -40]
    });

    // Sample salon data
    var salons = [
      { name: "Salon Elegance", lat: 6.9271, lng: 79.8612, address: "Colombo 07", rating: 4.8, services: 25 },
      { name: "Beauty Bliss", lat: 6.9350, lng: 79.8560, address: "Colombo 03", rating: 4.9, services: 30 },
      { name: "Hair & Glow", lat: 6.9205, lng: 79.8789, address: "Nugegoda", rating: 4.7, services: 20 },
      { name: "Glam Studio", lat: 6.9365, lng: 79.8471, address: "Bambalapitiya", rating: 4.6, services: 22 },
      { name: "Style Avenue", lat: 6.9180, lng: 79.8650, address: "Dehiwala", rating: 4.8, services: 28 }
    ];

    salons.forEach(salon => {
      var marker = L.marker([salon.lat, salon.lng], { icon: customIcon }).addTo(map);
      marker.bindPopup(`
        <div class="map-popup">
          <h6>${salon.name}</h6>
          <p class="mb-1"><i class="fas fa-map-marker-alt me-1"></i> ${salon.address}</p>
          <p class="mb-1"><i class="fas fa-star me-1"></i> ${salon.rating} Rating</p>
          <p class="mb-2"><i class="fas fa-concierge-bell me-1"></i> ${salon.services} Services</p>
          <a href="user/salon_view.php" class="btn btn-sm btn-primary w-100">View Details</a>
        </div>
      `);
    });

    // Locate me button
    document.getElementById('locateMe').addEventListener('click', function() {
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
          map.setView([position.coords.latitude, position.coords.longitude], 15);
          L.marker([position.coords.latitude, position.coords.longitude]).addTo(map)
            .bindPopup('You are here!').openPopup();
        });
      }
    });

    // Fullscreen toggle
    document.getElementById('fullscreen').addEventListener('click', function() {
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