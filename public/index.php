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

    <style>
        /* Map must have height */
        #map {
            width: 100%;
            height: 500px;
        }
    </style>
</head>

<body>
    <?php include __DIR__ . "/header.php"; ?>

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

                    <!-- Search Bar -->
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

                    <!-- Quick Stats -->
                    <div class="quick-stats mt-4">
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

    <!-- Map Section -->
    <section class="map-section">
        <div class="container">
            <div class="section-header text-center mb-5">
                <span class="section-badge">Explore</span>
                <h2 class="section-title">Salons Near You</h2>
                <p class="section-subtitle">Find the perfect salon on our interactive map</p>
            </div>

            <div class="map-container position-relative">
                <div id="map"></div>
                <div class="map-controls position-absolute top-0 end-0 p-2">
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

    <?php include __DIR__ . "/footer.php"; ?>

    <!-- JS Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

    <!-- Map Script -->
    <script src="assets/js/map.js"></script>

    <!-- Optional: Particle Animation -->
    <script>
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
    </script>
</body>
</html>
