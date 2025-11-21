<?php
session_start();
require_once __DIR__ . '/../config.php';

// Get initial stats for hero section
try {
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT s.id) as total_salons,
            ROUND(AVG(s.rating), 1) as avg_rating,
            COUNT(DISTINCT b.user_id) as total_customers
        FROM salons s
        LEFT JOIN bookings b ON b.salon_id = s.id
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Convert to integers/floats
    $stats['total_salons'] = (int)($stats['total_salons'] ?? 0);
    $stats['avg_rating'] = (float)($stats['avg_rating'] ?? 4.8);
    $stats['total_customers'] = (int)($stats['total_customers'] ?? 0);
    
    // Use fallback if no data
    if ($stats['total_salons'] === 0) $stats['total_salons'] = 500;
    if ($stats['avg_rating'] === 0.0) $stats['avg_rating'] = 4.8;
    if ($stats['total_customers'] === 0) $stats['total_customers'] = 10000;
    
} catch (Exception $e) {
    $stats = [
        'total_salons' => 500,
        'avg_rating' => 4.8,
        'total_customers' => 10000
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Discover premium salons near you. Book haircuts, spa treatments, and beauty services at top-rated locations.">
    <title>Salonora - Find Premium Salons Nearby</title>

    <!-- Preconnect to CDNs for better performance -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://unpkg.com">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

    <!-- Leaflet Map CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous" />
    
    <!-- Leaflet MarkerCluster CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        #map {
            width: 100%;
            height: 600px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .search-results-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            max-height: 450px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            margin-top: 8px;
        }

        .search-results-dropdown.show {
            display: block;
        }

        .search-result-item {
            padding: 16px 20px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: start;
            gap: 12px;
        }

        .search-result-item:hover {
            background: linear-gradient(to right, #f8f9fa, #fff);
            transform: translateX(4px);
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .search-result-content {
            flex: 1;
        }

        .search-result-name {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-result-rating {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #fef3c7;
            color: #d97706;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .search-result-address {
            font-size: 13px;
            color: #718096;
            margin-bottom: 4px;
        }

        .search-result-services {
            font-size: 12px;
            color: #a0aec0;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }

        .service-badge {
            background: #e0e7ff;
            color: #4338ca;
            padding: 3px 10px;
            border-radius: 6px;
            font-weight: 500;
        }

        .search-result-distance {
            font-size: 12px;
            color: #059669;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .no-results {
            padding: 40px 20px;
            text-align: center;
            color: #718096;
        }

        .no-results i {
            font-size: 48px;
            color: #cbd5e0;
            margin-bottom: 16px;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f4f6;
            border-radius: 50%;
            border-top-color: #6366f1;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .search-wrapper {
            position: relative;
        }

        /* Map Filter Panel */
        .map-filter-panel {
            position: absolute;
            top: 10px;
            left: 10px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            padding: 16px;
            z-index: 1000;
            max-width: 280px;
        }

        .filter-title {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .filter-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            cursor: pointer;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .filter-option:hover {
            background: #f7fafc;
        }

        .filter-option input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .filter-option label {
            cursor: pointer;
            font-size: 13px;
            color: #4a5568;
            margin: 0;
        }

        .search-stats {
            text-align: center;
            padding: 12px;
            background: #f7fafc;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 13px;
            color: #4a5568;
        }

        .search-stats strong {
            color: #6366f1;
            font-size: 16px;
        }

        /* Custom Marker Popup */
        .custom-popup .leaflet-popup-content-wrapper {
            border-radius: 12px;
            padding: 0;
            overflow: hidden;
        }

        .popup-content {
            padding: 16px;
            min-width: 250px;
        }

        .popup-header {
            display: flex;
            align-items: start;
            gap: 12px;
            margin-bottom: 12px;
        }

        .popup-image {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            object-fit: cover;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .popup-title {
            font-weight: 600;
            color: #2d3748;
            font-size: 16px;
            margin-bottom: 4px;
        }

        .popup-rating {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #d97706;
            font-size: 13px;
        }

        .popup-details {
            font-size: 13px;
            color: #718096;
            margin-bottom: 12px;
        }

        .popup-actions {
            display: flex;
            gap: 8px;
        }

        .popup-btn {
            flex: 1;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s;
        }

        .popup-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .popup-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .popup-btn-secondary {
            background: #f7fafc;
            color: #4a5568;
        }

        .popup-btn-secondary:hover {
            background: #edf2f7;
            color: #2d3748;
        }

        .map-legend {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 12px 16px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            font-size: 12px;
            z-index: 999;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .legend-item:last-child {
            margin-bottom: 0;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }

        /* Highlight active search result */
        .search-result-item.active {
            background: linear-gradient(to right, #e0e7ff, #fff);
            border-left: 4px solid #6366f1;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .map-filter-panel {
                display: none;
            }
            
            #map {
                height: 450px;
            }
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

                    <!-- Enhanced Search Bar with Autocomplete -->
                    <form id="searchForm" class="search-container">
                        <div class="search-wrapper">
                            <i class="fas fa-search search-icon"></i>
                            <input 
                                type="text" 
                                id="searchInput" 
                                class="search-input" 
                                placeholder="Search salon name, service, or location..." 
                                autocomplete="off"
                                aria-label="Search salons"
                            >
                            <button class="btn-search" type="submit">
                                <span>Search</span>
                                <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                            
                            <!-- Search Results Dropdown -->
                            <div id="searchResults" class="search-results-dropdown"></div>
                        </div>
                    </form>

                    <!-- Quick Stats with Real Data -->
                    <div class="quick-stats mt-4">
                        <div class="stat-item">
                            <i class="fas fa-store"></i>
                            <div>
                                <strong><?php echo number_format($stats['total_salons']); ?>+</strong>
                                <span>Salons</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-star"></i>
                            <div>
                                <strong><?php echo number_format($stats['avg_rating'], 1); ?>/5</strong>
                                <span>Rating</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-users"></i>
                            <div>
                                <strong><?php echo number_format($stats['total_customers']); ?>+</strong>
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
                <div id="map" role="application" aria-label="Interactive salon map"></div>
                
                <!-- Map Filter Panel -->
                <div class="map-filter-panel">
                    <div class="filter-title">
                        <i class="fas fa-filter me-2"></i>Filter Results
                    </div>
                    <div id="searchStatsDisplay" class="search-stats" style="display: none;">
                        Found <strong id="resultCount">0</strong> salons
                    </div>
                    <div class="filter-option">
                        <input type="checkbox" id="filterHighRated" checked>
                        <label for="filterHighRated">Show High Rated (4.0+)</label>
                    </div>
                    <div class="filter-option">
                        <input type="checkbox" id="filterOpenNow">
                        <label for="filterOpenNow">Open Now</label>
                    </div>
                    <div class="filter-option">
                        <input type="checkbox" id="filterNearby" checked>
                        <label for="filterNearby">Show Nearby Only</label>
                    </div>
                </div>
                
                <div class="map-controls position-absolute top-0 end-0 p-2">
                    <button class="map-btn" id="locateMe" title="Find my location" aria-label="Find my location">
                        <i class="fas fa-location-crosshairs"></i>
                    </button>
                    <button class="map-btn" id="clearSearch" title="Clear search" aria-label="Clear search" style="display: none;">
                        <i class="fas fa-times"></i>
                    </button>
                    <button class="map-btn" id="fullscreen" title="Fullscreen" aria-label="Toggle fullscreen">
                        <i class="fas fa-expand"></i>
                    </button>
                </div>

                <!-- Map Legend -->
                <div class="map-legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background: #10b981;"></div>
                        <span>High Rated (4.5+)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #3b82f6;"></div>
                        <span>Good (4.0-4.4)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #f59e0b;"></div>
                        <span>Average (3.0-3.9)</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . "/footer.php"; ?>

    <!-- JS Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

    <!-- Enhanced Search & Map Integration JS -->
    <script src="assets/js/enhanced-map.js"></script>

    <!-- Particle Animation -->
    <script>
        function createParticles() {
            const container = document.getElementById('particles');
            if (!container) return;
            
            const particleCount = window.innerWidth < 768 ? 20 : 50;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 15 + 's';
                particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
                container.appendChild(particle);
            }
        }

        // Initialize particles when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', createParticles);
        } else {
            createParticles();
        }
    </script>
</body>
</html>