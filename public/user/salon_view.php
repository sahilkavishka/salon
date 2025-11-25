<?php
// public/user/salon_view.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

// Handle review submission
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['salon_id'], $_POST['rating'], $_POST['comment'])
) {
    $salon_id = intval($_POST['salon_id']);
    $user_id = $_SESSION['id'] ?? 0;
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    $errors = [];

    // Enhanced validation
    if ($rating < 1 || $rating > 5) $errors[] = "Please select a valid rating.";
    if ($comment === '') $errors[] = "Review cannot be empty.";
    if (strlen($comment) < 10) $errors[] = "Review must be at least 10 characters.";
    if (strlen($comment) > 1000) $errors[] = "Review must not exceed 1000 characters.";

    // Prevent duplicate reviews per user per salon
    $checkStmt = $pdo->prepare("SELECT id FROM reviews WHERE salon_id=? AND user_id=?");
    $checkStmt->execute([$salon_id, $user_id]);
    if ($checkStmt->fetch()) $errors[] = "You have already reviewed this salon.";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO reviews (salon_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$salon_id, $user_id, $rating, $comment]);
            $_SESSION['success_message'] = "Review submitted successfully!";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Failed to submit review. Please try again.";
        }
    } else {
        $_SESSION['error_message'] = implode(" ", $errors);
    }
    header('Location: salon_view.php');
    exit;
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 9;
$offset = ($page - 1) * $per_page;

// Fetch total count
$countStmt = $pdo->query("SELECT COUNT(*) FROM salons");
$total_salons = $countStmt->fetchColumn();
$total_pages = ceil($total_salons / $per_page);

// Fetch salons with pagination
$stmt = $pdo->prepare("
    SELECT 
        s.id AS salon_id,
        s.name,
        s.address,
        s.image,
        s.owner_id,
        u.username AS owner_name,
        COUNT(DISTINCT sr.id) AS service_count,
        COALESCE(AVG(r.rating), 0) AS avg_rating,
        COUNT(DISTINCT r.id) AS review_count
    FROM salons s
    LEFT JOIN users u ON s.owner_id = u.id
    LEFT JOIN services sr ON s.id = sr.salon_id
    LEFT JOIN reviews r ON s.id = r.salon_id
    GROUP BY s.id
    ORDER BY s.name ASC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$salons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate overall stats (from all salons)
$statsStmt = $pdo->query("
    SELECT 
        COUNT(DISTINCT s.id) as total_salons,
        COALESCE(AVG(r.rating), 0) as avg_rating,
        COUNT(DISTINCT sr.id) as total_services
    FROM salons s
    LEFT JOIN reviews r ON s.id = r.salon_id
    LEFT JOIN services sr ON s.id = sr.salon_id
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

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
  <meta name="description" content="Discover premium beauty salons and book appointments online">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary-pink: #ff6b9d;
      --primary-purple: #8b5cf6;
      --dark-purple: #6b21a8;
      --light-pink: #fce7f3;
      --gradient-primary: linear-gradient(135deg, #ff6b9d 0%, #8b5cf6 100%);
      --gradient-secondary: linear-gradient(135deg, #fce7f3 0%, #ede9fe 100%);
      --shadow-sm: 0 2px 8px rgba(139, 92, 246, 0.1);
      --shadow-md: 0 4px 16px rgba(139, 92, 246, 0.15);
      --shadow-lg: 0 8px 32px rgba(139, 92, 246, 0.2);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(to bottom, #fef3f8, #ffffff);
      color: #333;
      min-height: 100vh;
    }

    /* Page Header */
    .page-header {
      background: var(--gradient-primary);
      color: white;
      padding: 4rem 0 3rem;
      margin-bottom: 3rem;
      position: relative;
      overflow: hidden;
      margin-top: 50px;
    }

    .page-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.1)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,138.7C960,139,1056,117,1152,106.7C1248,96,1344,96,1392,96L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') bottom center no-repeat;
      background-size: cover;
      opacity: 0.3;
    }

    .page-header-content {
      position: relative;
      z-index: 1;
      text-align: center;
    }

    .page-title {
      font-size: 3rem;
      font-weight: 800;
      margin-bottom: 1rem;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
      animation: fadeInDown 0.8s ease;
    }

    .page-subtitle {
      font-size: 1.25rem;
      font-weight: 300;
      opacity: 0.95;
      animation: fadeInUp 0.8s ease 0.2s both;
    }

    /* Stats Bar */
    .stats-bar {
      display: flex;
      justify-content: space-around;
      align-items: center;
      background: var(--dark-purple);
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: var(--shadow-md);
      animation: fadeInUp 0.8s ease 0.4s both;
    }

    .stat-item {
      text-align: center;
      flex: 1;
      padding: 0 1rem;
      border-right: 2px solid rgba(255, 255, 255, 0.2);
    }

    .stat-item:last-child {
      border-right: none;
    }

    .stat-number {
      display: block;
      font-size: 3.5rem;
      font-weight: 800;
      color: var(--primary-pink);
      margin-bottom: 0.5rem;
    }

    .stat-label {
      display: block;
      font-size: 1rem;
      color: #fce7f3;
      font-weight: 500;
    }

    /* Filter Bar */
    .filter-bar {
      background: white;
      padding: 1.5rem;
      border-radius: 15px;
      margin-bottom: 2rem;
      box-shadow: var(--shadow-sm);
    }

    .search-box {
      position: relative;
    }

    .search-box i {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--primary-purple);
      font-size: 1.1rem;
    }

    .search-box input {
      padding-left: 3rem;
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      transition: all 0.3s ease;
    }

    .search-box input:focus {
      border-color: var(--primary-purple);
      box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
      outline: none;
    }

    .form-select {
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      padding: 0.75rem 1rem;
      transition: all 0.3s ease;
    }

    .form-select:focus {
      border-color: var(--primary-pink);
      box-shadow: 0 0 0 4px rgba(255, 107, 157, 0.1);
      outline: none;
    }

    /* View Toggle */
    .view-toggle {
      display: flex;
      gap: 0.5rem;
      background: #f3f4f6;
      padding: 0.25rem;
      border-radius: 10px;
    }

    .view-btn {
      padding: 0.5rem 1rem;
      border: none;
      background: transparent;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s ease;
      color: #666;
    }

    .view-btn.active {
      background: white;
      color: var(--primary-purple);
      box-shadow: var(--shadow-sm);
    }

    /* Salon Card */
    .salon-card {
      background: white;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: var(--shadow-sm);
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    .salon-card:hover {
      transform: translateY(-8px);
      box-shadow: var(--shadow-lg);
    }

    .salon-img-wrapper {
      position: relative;
      overflow: hidden;
      padding-top: 66.67%;
      background: var(--gradient-secondary);
    }

    .salon-img {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.6s ease;
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
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0%, 100% {
        transform: scale(1);
      }
      50% {
        transform: scale(1.05);
      }
    }

    .salon-card-body {
      padding: 1.5rem;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }

    .salon-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--dark-purple);
      margin-bottom: 1rem;
    }

    .salon-info {
      margin-bottom: 1.5rem;
      flex-grow: 1;
    }

    .info-row {
      display: flex;
      align-items: center;
      margin-bottom: 0.75rem;
      color: #666;
      font-size: 0.9rem;
    }

    .info-row i {
      width: 20px;
      color: var(--primary-pink);
      margin-right: 0.75rem;
    }

    .rating-display {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .stars {
      color: #fbbf24;
      font-size: 1rem;
    }

    .rating-text {
      color: #666;
      font-size: 0.85rem;
    }

    /* List View */
    .list-view .salon-card {
      flex-direction: row;
      height: auto;
    }

    .list-view .salon-img-wrapper {
      width: 250px;
      padding-top: 0;
      height: auto;
      min-height: 200px;
    }

    .list-view .salon-card-body {
      flex: 1;
    }

    .list-view .salon-actions {
      flex-direction: row;
    }

    /* Buttons */
    .salon-actions {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .btn {
      border-radius: 12px;
      padding: 0.75rem 1.25rem;
      font-weight: 600;
      transition: all 0.3s ease;
      border: none;
      font-size: 0.9rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      text-decoration: none;
      flex: 1;
      min-width: fit-content;
    }

    .btn-view {
      background: var(--gradient-secondary);
      color: var(--dark-purple);
      border: 2px solid var(--primary-purple);
    }

    .btn-view:hover {
      background: var(--gradient-primary);
      color: white;
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .btn-primary {
      background: var(--gradient-primary);
      color: white;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
      opacity: 0.9;
    }

    .btn-review {
      background: white;
      color: var(--primary-pink);
      border: 2px solid var(--primary-pink);
    }

    .btn-review:hover {
      background: var(--primary-pink);
      color: white;
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    /* Pagination */
    .pagination-wrapper {
      display: flex;
      justify-content: center;
      margin-top: 3rem;
    }

    .pagination {
      display: flex;
      gap: 0.5rem;
      list-style: none;
    }

    .page-link {
      padding: 0.75rem 1.25rem;
      border-radius: 10px;
      background: white;
      color: var(--primary-purple);
      border: 2px solid #e5e7eb;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .page-link:hover {
      background: var(--gradient-primary);
      color: white;
      border-color: transparent;
      transform: translateY(-2px);
    }

    .page-item.active .page-link {
      background: var(--gradient-primary);
      color: white;
      border-color: transparent;
    }

    .page-item.disabled .page-link {
      opacity: 0.5;
      cursor: not-allowed;
      pointer-events: none;
    }

    /* Modal */
    .modal-content {
      border-radius: 20px;
      border: none;
      overflow: hidden;
    }

    .modal-header {
      background: var(--gradient-primary);
      color: white;
      border: none;
      padding: 1.5rem;
    }

    .modal-title {
      font-weight: 700;
      font-size: 1.5rem;
    }

    .modal-body {
      padding: 2rem;
    }

    .form-label {
      font-weight: 600;
      color: var(--dark-purple);
      margin-bottom: 0.5rem;
    }

    .form-control, .form-select {
      border-radius: 12px;
      border: 2px solid #e5e7eb;
      padding: 0.75rem 1rem;
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--primary-purple);
      box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
    }

    .modal-footer {
      border: none;
      padding: 1.5rem 2rem;
      background: var(--gradient-secondary);
    }

    .btn-modal-secondary {
      background: white;
      color: #666;
      border: 2px solid #e5e7eb;
    }

    .btn-modal-primary {
      background: var(--gradient-primary);
      color: white;
    }

    /* Alerts */
    .alert {
      border-radius: 12px;
      border: none;
      padding: 1rem 1.5rem;
      margin-bottom: 1.5rem;
      animation: slideInDown 0.5s ease;
    }

    .alert-success {
      background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
      color: #065f46;
    }

    .alert-danger {
      background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
      color: #991b1b;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 4rem 2rem;
      animation: fadeIn 0.8s ease;
    }

    .empty-state i {
      font-size: 5rem;
      color: var(--primary-purple);
      opacity: 0.3;
      margin-bottom: 1.5rem;
    }

    .empty-state h4 {
      color: var(--dark-purple);
      font-weight: 700;
      margin-bottom: 0.5rem;
    }

    .empty-state p {
      color: #666;
    }

    /* Loading Skeleton */
    .skeleton {
      animation: skeleton-loading 1s linear infinite alternate;
    }

    @keyframes skeleton-loading {
      0% {
        background-color: hsl(200, 20%, 80%);
      }
      100% {
        background-color: hsl(200, 20%, 95%);
      }
    }

    .skeleton-card {
      height: 400px;
      border-radius: 20px;
    }

    /* Animations */
    @keyframes fadeInDown {
      from {
        opacity: 0;
        transform: translateY(-30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }
      to {
        opacity: 1;
      }
    }

    @keyframes slideInDown {
      from {
        transform: translateY(-100%);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    /* Responsive */
    @media (max-width: 768px) {
      .page-title {
        font-size: 2rem;
      }

      .page-subtitle {
        font-size: 1rem;
      }

      .stats-bar {
        flex-direction: column;
        gap: 1.5rem;
      }

      .stat-item {
        border-right: none;
        border-bottom: 2px solid rgba(255, 255, 255, 0.2);
        padding-bottom: 1rem;
      }

      .stat-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
      }

      .salon-actions {
        flex-direction: column;
      }

      .btn {
        width: 100%;
      }

      .list-view .salon-card {
        flex-direction: column;
      }

      .list-view .salon-img-wrapper {
        width: 100%;
        padding-top: 66.67%;
      }
    }

    /* Character counter */
    .char-counter {
      font-size: 0.875rem;
      color: #666;
      text-align: right;
      margin-top: 0.25rem;
    }

    .char-counter.warning {
      color: #f59e0b;
    }

    .char-counter.danger {
      color: #ef4444;
    }

    /* Filter chips */
    .filter-chips {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
      margin-top: 1rem;
    }

    .filter-chip {
      padding: 0.5rem 1rem;
      background: var(--gradient-secondary);
      border-radius: 50px;
      font-size: 0.875rem;
      color: var(--dark-purple);
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      cursor: pointer;
      transition: all 0.3s ease;
      border: 2px solid transparent;
    }

    .filter-chip:hover {
      border-color: var(--primary-purple);
      transform: translateY(-2px);
    }

    .filter-chip.active {
      background: var(--gradient-primary);
      color: white;
    }
  </style>
  <?php include __DIR__ . '/../header.php'; ?>
</head>
<body>
  <div class="page-header">
    <div class="container">
      <div class="page-header-content">
        <h1 class="page-title">✨ Discover Amazing Salons</h1>
        <p class="page-subtitle">Browse through our curated collection of premium beauty destinations</p>
      </div>
    </div>
  </div>

  <div class="container pb-5">
    <?php if (isset($_SESSION['success_message'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_SESSION['error_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="stats-bar">
      <div class="stat-item">
        <span class="stat-number"><?= $stats['total_salons'] ?></span>
        <span class="stat-label">Total Salons</span>
      </div>
      <div class="stat-item">
        <span class="stat-number"><?= number_format($stats['avg_rating'], 1) ?></span>
        <span class="stat-label">Average Rating</span>
      </div>
      <div class="stat-item">
        <span class="stat-number"><?= $stats['total_services'] ?>+</span>
        <span class="stat-label">Total Services</span>
      </div>
    </div>

    <div class="filter-bar">
      <div class="row align-items-center">
        <div class="col-md-6 mb-3 mb-md-0">
          <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search salons by name, location, or owner..." class="form-control" aria-label="Search salons">
          </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
          <select id="sortSelect" class="form-select" aria-label="Sort salons">
            <option value="name">Sort by Name</option>
            <option value="rating">Sort by Rating</option>
            <option value="services">Sort by Services</option>
            <option value="location">Sort by Location</option>
          </select>
        </div>
        <div class="col-md-3">
          <div class="view-toggle">
            <button class="view-btn active" data-view="grid" aria-label="Grid view">
              <i class="fas fa-th"></i>
            </button>
            <button class="view-btn" data-view="list" aria-label="List view">
              <i class="fas fa-list"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Quick Filter Chips -->
      <div class="filter-chips">
        <span class="filter-chip" data-filter="all">
          <i class="fas fa-globe"></i> All Salons
        </span>
        <span class="filter-chip" data-filter="top-rated">
          <i class="fas fa-star"></i> Top Rated (4+)
        </span>
        <span class="filter-chip" data-filter="most-services">
          <i class="fas fa-concierge-bell"></i> Most Services (5+)
        </span>
        <span class="filter-chip" data-filter="most-reviewed">
          <i class="fas fa-comments"></i> Most Reviewed
        </span>
      </div>
    </div>

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
          $avg_rating = number_format($salon['avg_rating'], 1);
          $stars_full = floor($salon['avg_rating']);
          $stars_half = ($salon['avg_rating'] - $stars_full) >= 0.5 ? 1 : 0;
          $stars_empty = 5 - $stars_full - $stars_half;
        ?>
          <div class="col-lg-4 col-md-6 salon-item"
               data-name="<?= strtolower(htmlspecialchars($salon['name'])) ?>"
               data-location="<?= strtolower(htmlspecialchars($salon['address'])) ?>"
               data-owner="<?= strtolower(htmlspecialchars($salon['owner_name'])) ?>"
               data-services="<?= $salon['service_count'] ?>"
               data-rating="<?= $salon['avg_rating'] ?>"
               data-reviews="<?= $salon['review_count'] ?>">
            <div class="salon-card">
              <div class="salon-img-wrapper">
                <img src="<?= htmlspecialchars($image_path) ?>" class="salon-img" alt="<?= htmlspecialchars($salon['name']) ?>" loading="lazy">
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
                    <div class="rating-display">
                      <span class="stars">
                        <?php for($i = 0; $i < $stars_full; $i++) echo '★'; ?>
                        <?php if($stars_half) echo '⯨'; ?>
                        <?php for($i = 0; $i < $stars_empty; $i++) echo '☆'; ?>
                      </span>
                      <span class="rating-text"><?= $avg_rating ?> (<?= $salon['review_count'] ?> reviews)</span>
                    </div>
                  </div>
                </div>
                <div class="salon-actions">
                  <a href="salon_details.php?id=<?= $salon['salon_id'] ?>" class="btn btn-view">
                    <i class="fas fa-eye"></i> View Details
                  </a>
                  <a href="book_appointment.php?salon_id=<?= $salon['salon_id']; ?>" class="btn btn-primary">
                    <i class="fas fa-calendar-check"></i> Book Now
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

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
        <div class="pagination-wrapper">
          <ul class="pagination">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                <i class="fas fa-chevron-left"></i>
              </a>
            </li>
            
            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            
            if ($start > 1): ?>
              <li class="page-item">
                <a class="page-link" href="?page=1">1</a>
              </li>
              <?php if ($start > 2): ?>
                <li class="page-item disabled">
                  <span class="page-link">...</span>
                </li>
              <?php endif; ?>
            <?php endif; ?>
            
            <?php for ($i = $start; $i <= $end; $i++): ?>
              <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
            
            <?php if ($end < $total_pages): ?>
              <?php if ($end < $total_pages - 1): ?>
                <li class="page-item disabled">
                  <span class="page-link">...</span>
                </li>
              <?php endif; ?>
              <li class="page-item">
                <a class="page-link" href="?page=<?= $total_pages ?>"><?= $total_pages ?></a>
              </li>
            <?php endif; ?>
            
            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
              <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                <i class="fas fa-chevron-right"></i>
              </a>
            </li>
          </ul>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Review Modal -->
  <div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form method="post" action="salon_view.php" class="modal-content" id="reviewForm">
        <div class="modal-header">
          <h5 class="modal-title" id="reviewModalLabel">
            <i class="fas fa-star me-2"></i>Write a Review
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="salon_id" id="reviewSalonId">
          <div class="mb-3">
            <label class="form-label"><i class="fas fa-store me-2"></i>Salon</label>
            <input type="text" id="reviewSalonName" class="form-control" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label"><i class="fas fa-star me-2"></i>Rating</label>
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
            <label class="form-label"><i class="fas fa-comment me-2"></i>Your Review</label>
            <textarea name="comment" id="reviewComment" class="form-control" rows="4" placeholder="Share your experience... (minimum 10 characters)" required minlength="10" maxlength="1000"></textarea>
            <div class="char-counter">
              <span id="charCount">0</span> / 1000 characters
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-modal-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-2"></i>Cancel
          </button>
          <button type="submit" class="btn btn-modal-primary">
            <i class="fas fa-paper-plane me-2"></i>Submit Review
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php include __DIR__ . '/../footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Search functionality with debouncing
    const searchInput = document.getElementById('searchInput');
    const salonItems = document.querySelectorAll('.salon-item');
    const salonsGrid = document.getElementById('salonsGrid');
    let searchTimeout;

    searchInput.addEventListener('input', function() {
      clearTimeout(searchTimeout);
      const searchTerm = this.value.toLowerCase();
      
      searchTimeout = setTimeout(() => {
        filterSalons();
      }, 300);
    });

    // Sort functionality
    const sortSelect = document.getElementById('sortSelect');
    
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
        } else if (sortBy === 'rating') {
          return parseFloat(b.dataset.rating) - parseFloat(a.dataset.rating);
        }
      });
      
      items.forEach(item => salonsGrid.appendChild(item));
    });

    // View toggle
    const viewBtns = document.querySelectorAll('.view-btn');
    const gridContainer = document.getElementById('salonsGrid');

    viewBtns.forEach(btn => {
      btn.addEventListener('click', function() {
        viewBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        const view = this.dataset.view;
        if (view === 'list') {
          gridContainer.classList.add('list-view');
          salonItems.forEach(item => {
            item.classList.remove('col-lg-4', 'col-md-6');
            item.classList.add('col-12');
          });
        } else {
          gridContainer.classList.remove('list-view');
          salonItems.forEach(item => {
            item.classList.remove('col-12');
            item.classList.add('col-lg-4', 'col-md-6');
          });
        }
      });
    });

    // Filter chips
    const filterChips = document.querySelectorAll('.filter-chip');
    let activeFilter = 'all';

    filterChips.forEach(chip => {
      chip.addEventListener('click', function() {
        filterChips.forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        activeFilter = this.dataset.filter;
        filterSalons();
      });
    });

    function filterSalons() {
      const searchTerm = searchInput.value.toLowerCase();
      let visibleCount = 0;

      salonItems.forEach(item => {
        const name = item.dataset.name;
        const location = item.dataset.location;
        const owner = item.dataset.owner;
        const rating = parseFloat(item.dataset.rating);
        const services = parseInt(item.dataset.services);
        const reviews = parseInt(item.dataset.reviews);

        // Search filter
        const matchesSearch = searchTerm === '' || 
          name.includes(searchTerm) || 
          location.includes(searchTerm) || 
          owner.includes(searchTerm);

        // Quick filter
        let matchesFilter = true;
        if (activeFilter === 'top-rated') {
          matchesFilter = rating >= 4;
        } else if (activeFilter === 'most-services') {
          matchesFilter = services >= 5;
        } else if (activeFilter === 'most-reviewed') {
          matchesFilter = reviews >= 5;
        }

        if (matchesSearch && matchesFilter) {
          item.style.display = 'block';
          visibleCount++;
        } else {
          item.style.display = 'none';
        }
      });

      // Show no results message
      const existingMsg = document.querySelector('.no-results-msg');
      if (existingMsg) existingMsg.remove();
      
      if (visibleCount === 0) {
        const noResults = document.createElement('div');
        noResults.className = 'col-12 no-results-msg';
        noResults.innerHTML = `
          <div class="empty-state">
            <i class="fas fa-search"></i>
            <h4>No Salons Found</h4>
            <p>Try adjusting your search terms or filters</p>
          </div>
        `;
        salonsGrid.appendChild(noResults);
      }
    }

    // Review Modal
    const reviewModal = document.getElementById('reviewModal');
    const reviewComment = document.getElementById('reviewComment');
    const charCount = document.getElementById('charCount');

    reviewModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      const salonId = button.getAttribute('data-salon');
      const salonName = button.getAttribute('data-name');
      document.getElementById('reviewSalonId').value = salonId;
      document.getElementById('reviewSalonName').value = salonName;
      reviewComment.value = '';
      charCount.textContent = '0';
      charCount.parentElement.classList.remove('warning', 'danger');
    });

    // Character counter
    reviewComment.addEventListener('input', function() {
      const length = this.value.length;
      charCount.textContent = length;
      
      const counter = charCount.parentElement;
      counter.classList.remove('warning', 'danger');
      
      if (length > 900) {
        counter.classList.add('danger');
      } else if (length > 750) {
        counter.classList.add('warning');
      }
    });

    // Form validation
    const reviewForm = document.getElementById('reviewForm');
    reviewForm.addEventListener('submit', function(e) {
      const comment = reviewComment.value;
      const rating = this.querySelector('select[name="rating"]').value;
      
      if (!rating) {
        e.preventDefault();
        alert('Please select a rating');
        return;
      }
      
      if (comment.length < 10) {
        e.preventDefault();
        alert('Review must be at least 10 characters long');
        return;
      }
      
      if (comment.length > 1000) {
        e.preventDefault();
        alert('Review must not exceed 1000 characters');
        return;
      }
    });

    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
      setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
      }, 5000);
    });

    // Animate cards on scroll
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry, index) => {
        if (entry.isIntersecting) {
          setTimeout(() => {
            entry.target.style.animation = 'fadeInUp 0.6s ease forwards';
          }, index * 100);
          observer.unobserve(entry.target);
        }
      });
    }, observerOptions);

    salonItems.forEach(item => {
      item.style.opacity = '0';
      observer.observe(item);
    });

    // Preserve scroll position on page reload
    if ('scrollRestoration' in history) {
      history.scrollRestoration = 'manual';
    }
    
    const scrollPos = sessionStorage.getItem('scrollPos');
    if (scrollPos) {
      window.scrollTo(0, parseInt(scrollPos));
      sessionStorage.removeItem('scrollPos');
    }

    window.addEventListener('beforeunload', () => {
      sessionStorage.setItem('scrollPos', window.scrollY);
    });
  </script>
</body>
</html>