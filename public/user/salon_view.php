<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

// Fetch all salons with service count
$stmt = $pdo->query("
    SELECT 
        s.id AS salon_id,
        s.name,
        s.address,
        s.image,
        s.owner_id,
        u.username AS owner_name,
        COUNT(sr.id) AS service_count
    FROM salons s
    LEFT JOIN users u ON s.owner_id = u.id
    LEFT JOIN services sr ON s.id = sr.salon_id
    GROUP BY s.id
    ORDER BY s.name ASC
");
$salons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine logged-in user
$logged_user_id = $_SESSION['id'] ?? 0;
$logged_user_role = $_SESSION['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>All Salons - Salonora</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    :root {
      --primary: #e91e63;
      --primary-dark: #c2185b;
      --secondary: #9c27b0;
      --accent: #ff6b9d;
      --dark: #1a1a2e;
      --light: #f5f7fa;
      --text-dark: #2d3436;
      --text-light: #636e72;
      --gradient-primary: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
      --gradient-secondary: linear-gradient(135deg, #ff6b9d 0%, #c471ed 100%);
      --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
      --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.12);
      --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.15);
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: var(--light);
      color: var(--text-dark);
    }

    /* Navbar */
    .navbar {
      background: white !important;
      box-shadow: var(--shadow-sm);
      padding: 1rem 0;
    }

    .navbar-brand {
      font-size: 1.5rem;
      font-weight: 800;
      background: var(--gradient-primary);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .navbar-brand i {
      background: var(--gradient-primary);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .nav-link {
      color: var(--text-dark);
      font-weight: 500;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      transition: var(--transition);
    }

    .nav-link:hover {
      background: rgba(233, 30, 99, 0.1);
      color: var(--primary);
    }

    .btn-gradient {
      background: var(--gradient-primary);
      color: white;
      border: none;
      padding: 0.5rem 1.5rem;
      border-radius: 50px;
      font-weight: 600;
      transition: var(--transition);
    }

    .btn-gradient:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
      color: white;
    }

    /* Page Header */
    .page-header {
      background: var(--gradient-primary);
      padding: 4rem 0 3rem;
      margin-bottom: 3rem;
      position: relative;
      overflow: hidden;
    }

    .page-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.1)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,144C960,149,1056,139,1152,122.7C1248,107,1344,85,1392,74.7L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
      opacity: 0.5;
    }

    .page-header-content {
      position: relative;
      z-index: 2;
      text-align: center;
    }

    .page-title {
      font-size: 2.5rem;
      font-weight: 800;
      color: white;
      margin-bottom: 0.5rem;
    }

    .page-subtitle {
      font-size: 1.1rem;
      color: rgba(255, 255, 255, 0.9);
      margin-bottom: 2rem;
    }

    /* Search & Filter Bar */
    .filter-bar {
      background: white;
      padding: 2rem;
      border-radius: 20px;
      box-shadow: var(--shadow-md);
      margin-bottom: 3rem;
    }

    .search-box {
      position: relative;
    }

    .search-box i {
      position: absolute;
      left: 1.5rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-light);
      font-size: 1.1rem;
    }

    .search-box input {
      width: 100%;
      padding: 1rem 1rem 1rem 3.5rem;
      border: 2px solid #e9ecef;
      border-radius: 50px;
      font-size: 1rem;
      transition: var(--transition);
    }

    .search-box input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
    }

    /* Stats Bar */
    .stats-bar {
      display: flex;
      justify-content: space-around;
      background: white;
      padding: 2rem;
      border-radius: 20px;
      box-shadow: var(--shadow-md);
      margin-bottom: 3rem;
    }

    .stat-item {
      text-align: center;
    }

    .stat-number {
      font-size: 2rem;
      font-weight: 800;
      background: var(--gradient-primary);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      display: block;
    }

    .stat-label {
      color: var(--text-light);
      font-size: 0.9rem;
      margin-top: 0.25rem;
    }

    /* Salon Cards */
    .salon-card {
      background: white;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: var(--shadow-sm);
      transition: var(--transition);
      height: 100%;
      display: flex;
      flex-direction: column;
      border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .salon-card:hover {
      transform: translateY(-10px);
      box-shadow: var(--shadow-lg);
      border-color: var(--primary);
    }

    .salon-img-wrapper {
      position: relative;
      overflow: hidden;
      height: 240px;
    }

    .salon-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.5s ease;
    }

    .salon-card:hover .salon-img {
      transform: scale(1.1);
    }

    .salon-badge {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: var(--gradient-primary);
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 50px;
      font-size: 0.85rem;
      font-weight: 600;
      box-shadow: var(--shadow-md);
    }

    .salon-card-body {
      padding: 1.5rem;
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .salon-title {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 1rem;
    }

    .salon-info {
      flex: 1;
      margin-bottom: 1rem;
    }

    .info-row {
      display: flex;
      align-items: center;
      margin-bottom: 0.75rem;
      color: var(--text-light);
      font-size: 0.95rem;
    }

    .info-row i {
      width: 20px;
      color: var(--primary);
      margin-right: 0.75rem;
    }

    .info-row strong {
      color: var(--text-dark);
      margin-left: 0.25rem;
    }

    .salon-actions {
      display: flex;
      gap: 0.5rem;
      flex-direction: column;
    }

    .btn-view {
      background: var(--gradient-primary);
      color: white;
      border: none;
      padding: 0.75rem;
      border-radius: 12px;
      font-weight: 600;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .btn-view:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
      color: white;
    }

    .btn-review {
      background: white;
      color: var(--primary);
      border: 2px solid var(--primary);
      padding: 0.75rem;
      border-radius: 12px;
      font-weight: 600;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .btn-review:hover {
      background: var(--primary);
      color: white;
      transform: translateY(-2px);
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 4rem 2rem;
      background: white;
      border-radius: 20px;
      box-shadow: var(--shadow-md);
    }

    .empty-state i {
      font-size: 4rem;
      color: var(--text-light);
      margin-bottom: 1rem;
    }

    .empty-state h4 {
      color: var(--text-dark);
      margin-bottom: 0.5rem;
    }

    .empty-state p {
      color: var(--text-light);
    }

    /* Modal Styling */
    .modal-content {
      border: none;
      border-radius: 20px;
      box-shadow: var(--shadow-xl);
    }

    .modal-header {
      background: var(--gradient-primary);
      color: white;
      border-radius: 20px 20px 0 0;
      padding: 1.5rem;
      border: none;
    }

    .modal-title {
      font-weight: 700;
    }

    .btn-close {
      filter: brightness(0) invert(1);
    }

    .modal-body {
      padding: 2rem;
    }

    .form-label {
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 0.5rem;
    }

    .form-control,
    .form-select {
      border: 2px solid #e9ecef;
      border-radius: 12px;
      padding: 0.75rem 1rem;
      transition: var(--transition);
    }

    .form-control:focus,
    .form-select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
    }

    .modal-footer {
      border: none;
      padding: 1.5rem 2rem;
    }

    .btn-modal-primary {
      background: var(--gradient-primary);
      color: white;
      border: none;
      padding: 0.75rem 2rem;
      border-radius: 50px;
      font-weight: 600;
      transition: var(--transition);
    }

    .btn-modal-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
    }

    .btn-modal-secondary {
      background: #e9ecef;
      color: var(--text-dark);
      border: none;
      padding: 0.75rem 2rem;
      border-radius: 50px;
      font-weight: 600;
      transition: var(--transition);
    }

    .btn-modal-secondary:hover {
      background: #dee2e6;
    }

    /* Star Rating */
    .star-rating {
      display: flex;
      gap: 0.5rem;
      font-size: 1.5rem;
    }

    .star-rating option {
      font-size: 1rem;
    }

    /* Loading Animation */
    .loading {
      text-align: center;
      padding: 3rem;
    }

    .spinner {
      width: 50px;
      height: 50px;
      border: 4px solid rgba(233, 30, 99, 0.2);
      border-top-color: var(--primary);
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin: 0 auto;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Responsive */
    @media (max-width: 768px) {
      .page-title {
        font-size: 2rem;
      }

      .stats-bar {
        flex-direction: column;
        gap: 1.5rem;
      }

      .salon-actions {
        flex-direction: column;
      }

      .filter-bar {
        padding: 1.5rem;
      }
    }

    /* Animations */
    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .salon-card {
      animation: fadeIn 0.6s ease forwards;
    }

    .salon-card:nth-child(1) { animation-delay: 0.1s; }
    .salon-card:nth-child(2) { animation-delay: 0.2s; }
    .salon-card:nth-child(3) { animation-delay: 0.3s; }
    .salon-card:nth-child(4) { animation-delay: 0.4s; }
    .salon-card:nth-child(5) { animation-delay: 0.5s; }
    .salon-card:nth-child(6) { animation-delay: 0.6s; }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg">
    <div class="container">
      <a class="navbar-brand" href="../../index.php">
        <i class="fas fa-spa"></i> Salonora
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav align-items-center">
          <li class="nav-item"><a href="../../index.php" class="nav-link">Home</a></li>
          <li class="nav-item"><a class="nav-link active" href="salon_view.php">Salons</a></li>
          <li class="nav-item"><a class="nav-link" href="my_appointments.php">Appointments</a></li>
          <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
          <?php if (isset($_SESSION['id'])): ?>
            <li class="nav-item ms-3">
              <a href="../logout.php" class="btn btn-gradient btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i> Logout
              </a>
            </li>
          <?php else: ?>
            <li class="nav-item ms-3">
              <a href="../login.php" class="btn btn-gradient btn-sm">
                <i class="fas fa-sign-in-alt me-1"></i> Login
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Page Header -->
  <div class="page-header">
    <div class="container">
      <div class="page-header-content">
        <h1 class="page-title">Discover Amazing Salons</h1>
        <p class="page-subtitle">Browse through our curated collection of premium beauty destinations</p>
      </div>
    </div>
  </div>

  <div class="container pb-5">
    <!-- Stats Bar -->
    <div class="stats-bar">
      <div class="stat-item">
        <span class="stat-number"><?= count($salons) ?></span>
        <span class="stat-label">Total Salons</span>
      </div>
      <div class="stat-item">
        <span class="stat-number">4.8</span>
        <span class="stat-label">Average Rating</span>
      </div>
      <div class="stat-item">
        <span class="stat-number">10k+</span>
        <span class="stat-label">Happy Clients</span>
      </div>
    </div>

    <!-- Search & Filter -->
    <div class="filter-bar">
      <div class="row align-items-center">
        <div class="col-md-8 mb-3 mb-md-0">
          <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search salons by name, location, or owner..." class="form-control">
          </div>
        </div>
        <div class="col-md-4">
          <select id="sortSelect" class="form-select">
            <option value="name">Sort by Name</option>
            <option value="services">Sort by Services</option>
            <option value="location">Sort by Location</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Salons Grid -->
    <?php if (empty($salons)): ?>
      <div class="empty-state">
        <i class="fas fa-store-slash"></i>
        <h4>No Salons Available</h4>
        <p>Check back later for new salon listings</p>
      </div>
    <?php else: ?>
      <div class="row g-4" id="salonsGrid">
        <?php foreach ($salons as $salon): 
          $can_interact = in_array($logged_user_role, ['user', 'customer']) && $logged_user_id != $salon['owner_id'];
          $image_path = '../../' . ($salon['image'] ?: 'assets/img/default_salon.jpg');
        ?>
          <div class="col-lg-4 col-md-6 salon-item" 
               data-name="<?= strtolower(htmlspecialchars($salon['name'])) ?>"
               data-location="<?= strtolower(htmlspecialchars($salon['address'])) ?>"
               data-owner="<?= strtolower(htmlspecialchars($salon['owner_name'])) ?>"
               data-services="<?= $salon['service_count'] ?>">
            <div class="salon-card">
              <div class="salon-img-wrapper">
                <img src="<?= htmlspecialchars($image_path) ?>" class="salon-img" alt="<?= htmlspecialchars($salon['name']) ?>">
                <div class="salon-badge">
                  <i class="fas fa-concierge-bell me-1"></i>
                  <?= $salon['service_count'] ?> Services
                </div>
              </div>
              <div class="salon-card-body">
                <h5 class="salon-title"><?= htmlspecialchars($salon['name']) ?></h5>
                <div class="salon-info">
                  <div class="info-row">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?= htmlspecialchars($salon['address']) ?></span>
                  </div>
                  <div class="info-row">
                    <i class="fas fa-user-tie"></i>
                    <span>Owner: <strong><?= htmlspecialchars($salon['owner_name']) ?></strong></span>
                  </div>
                  <div class="info-row">
                    <i class="fas fa-star"></i>
                    <span>Rating: <strong>4.8/5</strong></span>
                  </div>
                </div>
                <div class="salon-actions">
                  <a href="salon_details.php?id=<?= $salon['salon_id'] ?>" class="btn btn-view">
                    <i class="fas fa-eye"></i> View Details
                  </a>
                  <button class="btn btn-review" 
                          data-bs-toggle="modal" 
                          data-bs-target="#reviewModal" 
                          data-salon="<?= $salon['salon_id'] ?>"
                          data-name="<?= htmlspecialchars($salon['name']) ?>">
                    <i class="fas fa-star"></i> Write Review
                  </button>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Review Modal -->
  <div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <form method="post" action="../post_review.php" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fas fa-star me-2"></i>Write a Review
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="salon_id" id="reviewSalonId">
          <div class="mb-3">
            <label class="form-label">Salon</label>
            <input type="text" id="reviewSalonName" class="form-control" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Rating</label>
            <select name="rating" class="form-select" required>
              <option value="">Select Rating</option>
              <option value="5">★★★★★ Excellent</option>
              <option value="4">★★★★☆ Very Good</option>
              <option value="3">★★★☆☆ Good</option>
              <option value="2">★★☆☆☆ Fair</option>
              <option value="1">★☆☆☆☆ Poor</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Your Review</label>
            <textarea name="comment" class="form-control" rows="4" placeholder="Share your experience..." required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-modal-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-modal-primary">
            <i class="fas fa-paper-plane me-2"></i>Submit Review
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const salonItems = document.querySelectorAll('.salon-item');

    searchInput.addEventListener('input', function() {
      const searchTerm = this.value.toLowerCase();
      
      salonItems.forEach(item => {
        const name = item.dataset.name;
        const location = item.dataset.location;
        const owner = item.dataset.owner;
        
        if (name.includes(searchTerm) || location.includes(searchTerm) || owner.includes(searchTerm)) {
          item.style.display = 'block';
        } else {
          item.style.display = 'none';
        }
      });
    });

    // Sort functionality
    const sortSelect = document.getElementById('sortSelect');
    const salonsGrid = document.getElementById('salonsGrid');

    sortSelect.addEventListener('change', function() {
      const sortBy = this.value;
      const items = Array.from(salonItems);
      
      items.sort((a, b) => {
        if (sortBy === 'name') {
          return a.dataset.name.localeCompare(b.dataset.name);
        } else if (sortBy === 'services') {
          return parseInt(b.dataset.services) - parseInt(a.dataset.services);
        } else if (sortBy === 'location') {
          return a.dataset.location.localeCompare(b.dataset.location);
        }
      });
      
      items.forEach(item => salonsGrid.appendChild(item));
    });

    // Review Modal
    const reviewModal = document.getElementById('reviewModal');
    reviewModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      const salonId = button.getAttribute('data-salon');
      const salonName = button.getAttribute('data-name');
      
      document.getElementById('reviewSalonId').value = salonId;
      document.getElementById('reviewSalonName').value = salonName;
    });
  </script>
</body>
</html>