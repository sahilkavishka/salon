<?php
// public/index.php
require_once __DIR__ . '/../config.php';
session_start();

$loggedIn = isset($_SESSION['id']);
$role = $_SESSION['role'] ?? 'guest';
$userName = $_SESSION['user_name'] ?? '';

// Fetch salons with coordinates and services
$stmt = $pdo->query("
    SELECT s.id, s.name, s.address, s.lat, s.lng, sr.id AS service_id, sr.name AS service_name
    FROM salons s
    LEFT JOIN services sr ON s.id = sr.salon_id
    WHERE s.lat IS NOT NULL AND s.lng IS NOT NULL
");
$salons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Notification count
$user_id = $_SESSION['id'] ?? null;
$unreadCount = 0;
if ($user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
    $stmt->execute([':uid' => $user_id]);
    $unreadCount = $stmt->fetchColumn();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Salonora - Find and book the best salons near you with ease">
  <meta name="keywords" content="salon, beauty, haircut, spa, booking">
  <title>Salonora - Find Premium Salons Nearby</title>
  
  <!-- Stylesheets -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="/assets/css/style.css">
  
  <!-- Preconnect for performance -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid px-4">
    <a class="navbar-brand" href="index.php">Salonora</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
        <?php if (!$loggedIn): ?>
          <li class="nav-item">
            <a class="nav-link" href="register.php">
              <span class="nav-icon">👤</span> Register
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="login.php">
              <span class="nav-icon">🔐</span> Login
            </a>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <span class="nav-link user-greeting">
              <span class="greeting-icon">👋</span> Hello, <strong><?= htmlspecialchars($userName) ?></strong>
            </span>
          </li>
          <?php if ($role === 'owner'): ?>
            <li class="nav-item">
              <a class="nav-link" href="owner/dashboard.php">
                <span class="nav-icon">📊</span> Dashboard
              </a>
            </li>
          <?php else: ?>
            <li class="nav-item">
              <a class="nav-link" href="user/profile.php">
                <span class="nav-icon">👤</span> Profile
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="user/my_appointments.php">
                <span class="nav-icon">📅</span> My Appointments
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="user/salon_view.php">
                <span class="nav-icon">💇</span> Salons
              </a>
            </li>
            <li class="nav-item position-relative">
              <a class="nav-link notification-link" href="notifications.php">
                <span class="nav-icon">🔔</span> Notifications
                <?php if ($unreadCount > 0): ?>
                  <span class="notif-badge"><?= $unreadCount ?></span>
                <?php endif; ?>
              </a>
            </li>
          <?php endif; ?>
          <li class="nav-item">
            <a class="nav-link text-danger logout-link" href="logout.php">
              <span class="nav-icon">🚪</span> Logout
            </a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- Hero Section -->
<div class="hero-section">
  <div class="container">
    <div class="hero-content text-center fade-in">
      <h1 class="hero-title">
        Discover Your Perfect Salon
      </h1>
      <p class="hero-subtitle">
        Book appointments at premium salons near you with just a few clicks
      </p>
    </div>
  </div>
</div>

<!-- Main Content -->
<div class="container main-content">
  <div class="text-center mb-4">
    <h2 class="section-title">Find the Best Salons Near You</h2>
    <p class="section-subtitle">Search by name or address to explore top-rated salons in your area</p>
  </div>

  <!-- Search Box -->
  <div class="search-container">
    <div class="input-group mx-auto">
      <input id="searchBox" type="text" class="form-control" placeholder="Search by salon name or address..." aria-label="Search salons">
      <button id="searchBtn" class="btn btn-custom" type="button">Search</button>
    </div>
  </div>

  <!-- Map Statistics -->
  <div class="map-stats-container">
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="stat-card">
          <div class="stat-icon">💈</div>
          <div class="stat-number" id="totalSalons"><?= count(array_unique(array_column($salons, 'id'))) ?></div>
          <div class="stat-label">Available Salons</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-card">
          <div class="stat-icon">⭐</div>
          <div class="stat-number">4.8</div>
          <div class="stat-label">Average Rating</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-card">
          <div class="stat-icon">✨</div>
          <div class="stat-number">5000+</div>
          <div class="stat-label">Happy Customers</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Map Container -->
  <div class="map-wrapper">
    <div id="map" role="application" aria-label="Interactive salon map"></div>
    <div class="map-legend">
      <span class="legend-item">
        <span class="legend-marker">📍</span> Salon Location
      </span>
    </div>
  </div>

  <!-- Features Section -->
  <?php if (!$loggedIn): ?>
  <div class="features-section mt-5">
    <h3 class="text-center mb-4 section-title">Why Choose Salonora?</h3>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon">🎯</div>
          <h4>Easy Booking</h4>
          <p>Book your salon appointments in seconds with our intuitive interface</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon">💎</div>
          <h4>Premium Salons</h4>
          <p>Access to verified, top-rated salons in your neighborhood</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon">📱</div>
          <h4>Real-time Updates</h4>
          <p>Get instant notifications about your appointments and special offers</p>
        </div>
      </div>
    </div>
    <div class="text-center mt-4">
      <a href="register.php" class="btn btn-custom btn-lg cta-button">Get Started Now</a>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" action="user/post_review.php" class="modal-content" id="reviewForm">
      <div class="modal-header">
        <h5 class="modal-title" id="reviewModalLabel">Write a Review</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="salon_id" id="reviewSalonId">
        
        <div class="mb-3">
          <label for="reviewRating" class="form-label">Rating</label>
          <select name="rating" id="reviewRating" class="form-select" required>
            <option value="">Select rating...</option>
            <option value="5">⭐⭐⭐⭐⭐ Excellent</option>
            <option value="4">⭐⭐⭐⭐ Very Good</option>
            <option value="3">⭐⭐⭐ Good</option>
            <option value="2">⭐⭐ Fair</option>
            <option value="1">⭐ Poor</option>
          </select>
        </div>
        
        <div class="mb-3">
          <label for="reviewComment" class="form-label">Your Review</label>
          <textarea name="comment" id="reviewComment" class="form-control" rows="4" placeholder="Share your experience with this salon..." required></textarea>
          <div class="form-text">Minimum 10 characters</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success">
          <span class="btn-icon">✍️</span> Submit Review
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Footer -->
<footer class="footer">
  <div class="container">
    <div class="row">
      <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
        <p class="mb-0">&copy; <?= date('Y') ?> Salonora. All rights reserved.</p>
      </div>
      <div class="col-md-6 text-center text-md-end">
        <a href="#" class="footer-link">Privacy Policy</a>
        <span class="footer-divider">|</span>
        <a href="#" class="footer-link">Terms of Service</a>
        <span class="footer-divider">|</span>
        <a href="#" class="footer-link">Contact Us</a>
      </div>
    </div>
  </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// Initialize map
var map = L.map('map', {
    zoomControl: true,
    scrollWheelZoom: true,
    dragging: true,
    doubleClickZoom: true
}).setView([6.9271, 79.8612], 13);

// Add tile layer with attribution
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
}).addTo(map);

// Custom marker icon
var salonIcon = L.divIcon({
    className: 'custom-marker',
    html: '<div class="marker-pin">📍</div>',
    iconSize: [30, 42],
    iconAnchor: [15, 42],
    popupAnchor: [0, -42]
});

// Parse salon data
var salons = <?= json_encode($salons) ?>;
var salonGroups = {};

// Group salons by ID
salons.forEach(function(salon) {
    if (!salonGroups[salon.id]) {
        salonGroups[salon.id] = {
            id: salon.id,
            name: salon.name,
            address: salon.address,
            lat: salon.lat,
            lng: salon.lng,
            services: []
        };
    }
    if (salon.service_name) {
        salonGroups[salon.id].services.push(salon.service_name);
    }
});

// Add salon markers to map
Object.values(salonGroups).forEach(function(salon) {
    var servicesHtml = '';
    if (salon.services.length > 0) {
        servicesHtml = '<div class="popup-services"><strong>Services:</strong><br>' + 
                      salon.services.slice(0, 3).join(', ') + 
                      (salon.services.length > 3 ? '...' : '') + 
                      '</div>';
    }
    
    var popupContent = `
        <div class="custom-popup">
            <h6 class="popup-title">${salon.name}</h6>
            <p class="popup-address"><span class="popup-icon">📍</span>${salon.address}</p>
            ${servicesHtml}
            <div class="popup-actions">
                <?php if ($loggedIn && $role !== 'owner'): ?>
                <a href="user/salon_details.php?id=${salon.id}" class="btn btn-sm btn-primary popup-btn">
                    <span class="btn-icon">👁️</span> View Details
                </a>
                <button class="btn btn-sm btn-success popup-btn" onclick="openReviewModal(${salon.id})">
                    <span class="btn-icon">⭐</span> Write Review
                </button>
                <?php else: ?>
                <a href="login.php" class="btn btn-sm btn-primary popup-btn">
                    Login to Book
                </a>
                <?php endif; ?>
            </div>
        </div>
    `;
    
    L.marker([salon.lat, salon.lng], {icon: salonIcon})
        .addTo(map)
        .bindPopup(popupContent, {
            maxWidth: 300,
            className: 'custom-leaflet-popup'
        });
});

// Search functionality
document.getElementById('searchBtn').addEventListener('click', performSearch);
document.getElementById('searchBox').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        performSearch();
    }
});

function performSearch() {
    const query = document.getElementById('searchBox').value.toLowerCase().trim();
    
    if (!query) {
        alert('Please enter a search term');
        return;
    }
    
    var found = false;
    for (const [id, salon] of Object.entries(salonGroups)) {
        if (salon.name.toLowerCase().includes(query) || 
            salon.address.toLowerCase().includes(query)) {
            map.setView([salon.lat, salon.lng], 17);
            found = true;
            
            // Open popup for found salon
            map.eachLayer(function(layer) {
                if (layer instanceof L.Marker) {
                    var latLng = layer.getLatLng();
                    if (latLng.lat === salon.lat && latLng.lng === salon.lng) {
                        layer.openPopup();
                    }
                }
            });
            break;
        }
    }
    
    if (!found) {
        alert('No salon found matching "' + query + '"');
    }
}

// Open review modal
function openReviewModal(salonId) {
    document.getElementById('reviewSalonId').value = salonId;
    var reviewModal = new bootstrap.Modal(document.getElementById('reviewModal'));
    reviewModal.show();
}

// Form validation
document.getElementById('reviewForm')?.addEventListener('submit', function(e) {
    const comment = document.getElementById('reviewComment').value.trim();
    if (comment.length < 10) {
        e.preventDefault();
        alert('Review must be at least 10 characters long');
        return false;
    }
});

// Add fade-in animation on load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('.hero-content')?.classList.add('fade-in');
    document.querySelector('.main-content')?.classList.add('fade-in');
});

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});
</script>

</body>
</html>