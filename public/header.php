<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= isset($page_title) ? $page_title : 'Salonora' ?></title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Main Style CSS (from index.php) -->
  <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">

  <!-- PWA Manifest & Meta -->
  <meta name="theme-color" content="#e91e63">
  <link rel="manifest" href="<?= url('manifest.json') ?>">
  <link rel="icon" type="image/svg+xml" href="<?= url('assets/images/logo.svg') ?>">
  <link rel="apple-touch-icon" href="<?= url('assets/images/logo.svg') ?>">

  <script>
    // Apply saved theme immediately before render
    if (localStorage.getItem('salonora_theme') === 'dark') {
      document.documentElement.classList.add('dark-mode');
    }
  </script>

  <style>
  /* ================================
     LIGHT MODE THEME (Base Variables)
     ================================ */
  :root {
    --primary: #e91e63;
    --primary-dark: #c2185b;
    --primary-purple: #7b1fa2;
    --dark-purple: #4a148c;
    --primary-pink: #c2185b;
    --light-purple: #f3e5f5;
    --light-pink: #fce4ec;
    --gradient-primary: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
    --gradient-secondary: linear-gradient(135deg, #fce4ec 0%, #f3e5f5 100%);
    --gradient-light: linear-gradient(135deg, #fce4ec 0%, #f3e5f5 100%);
    --gradient-dark: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    --text-dark: #1e293b;
    --text-muted: #64748b;
  }

  /* ================================
     DARK MODE THEME
     ================================ */
  html.dark-mode, body.dark-mode {
    background-color: #111122 !important;
    color: #e2e8f0 !important;
    --dark-purple: #f8fafc !important;
    --primary-purple: #c084fc !important;
    --primary-pink: #f472b6 !important;
    --light-purple: #3b2d54 !important;
    --light-pink: #252542 !important;
    --gradient-light: linear-gradient(135deg, rgba(233, 30, 99, 0.2) 0%, rgba(156, 39, 176, 0.25) 100%) !important;
    --gradient-secondary: linear-gradient(135deg, rgba(233, 30, 99, 0.15) 0%, rgba(156, 39, 176, 0.25) 100%) !important;
    --text-dark: #f8fafc !important;
    --text-muted: #94a3b8 !important;
  }
  body.dark-mode .card,
  body.dark-mode .appointment-card,
  body.dark-mode .profile-card,
  body.dark-mode .empty-state,
  body.dark-mode .form-container,
  body.dark-mode .notifications-header,
  body.dark-mode .notification-card,
  body.dark-mode .modal-content,
  body.dark-mode .nav-tabs,
  body.dark-mode .tab-content,
  body.dark-mode .stat-box,
  body.dark-mode .salon-card,
  body.dark-mode .salon-card-body,
  body.dark-mode .salon-info-card,
  body.dark-mode .salon-header,
  body.dark-mode .booking-card,
  body.dark-mode .card-body-custom,
  body.dark-mode .service-card,
  body.dark-mode .stylist-card,
  body.dark-mode .summary-item,
  body.dark-mode .summary-total,
  body.dark-mode .review-item,
  body.dark-mode .popular-service-card,
  body.dark-mode .upcoming-card,
  body.dark-mode .quick-stat-card,
  body.dark-mode .stat-card,
  body.dark-mode .filter-bar,
  body.dark-mode .search-box,
  body.dark-mode .search-box input,
  body.dark-mode .scanner-card,
  body.dark-mode .stats-card,
  body.dark-mode .stats-bar,
  body.dark-mode .filter-chip,
  body.dark-mode .page-link,
  body.dark-mode .bg-white,
  body.dark-mode .bg-light,
  body.dark-mode .dark-bg-card {
    background-color: #1a1a2e !important;
    color: #e2e8f0 !important;
    border-color: #2d2d48 !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4) !important;
  }
  body.dark-mode .text-dark,
  body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, 
  body.dark-mode h4, body.dark-mode h5, body.dark-mode h6,
  body.dark-mode .form-label, body.dark-mode strong,
  body.dark-mode .salon-title, body.dark-mode .service-name,
  body.dark-mode .profile-name, body.dark-mode .filter-title,
  body.dark-mode .stat-label, body.dark-mode .stat-number,
  body.dark-mode .section-title, body.dark-mode .card-title {
    color: #f8fafc !important;
  }
  body.dark-mode [style*="color: var(--dark-purple)"],
  body.dark-mode [style*="color:var(--dark-purple)"],
  body.dark-mode [style*="color: #6a1b9a"],
  body.dark-mode [style*="color:#6a1b9a"] {
    color: #f8fafc !important;
  }
  body.dark-mode [style*="color: var(--primary-purple)"],
  body.dark-mode [style*="color:var(--primary-purple)"] {
    color: #c084fc !important;
  }
  body.dark-mode [style*="color: var(--primary-pink)"],
  body.dark-mode [style*="color:var(--primary-pink)"] {
    color: #f472b6 !important;
  }
  body.dark-mode .info-row,
  body.dark-mode .info-row span,
  body.dark-mode p,
  body.dark-mode span:not(.badge):not(.status-badge) {
    color: #cbd5e1 !important;
  }
  body.dark-mode .text-muted, body.dark-mode .form-hint {
    color: #94a3b8 !important;
  }
  body.dark-mode .table {
    color: #cbd5e1 !important;
    border-color: #2d2d48 !important;
  }
  body.dark-mode .table th,
  body.dark-mode .table-light,
  body.dark-mode .table-light th {
    background-color: #1e1e38 !important;
    color: #f8fafc !important;
    border-color: #2d2d48 !important;
  }
  body.dark-mode .table td {
    background-color: transparent !important;
    color: #cbd5e1 !important;
    border-color: #2d2d48 !important;
  }
  body.dark-mode .table-hover tbody tr:hover td {
    background-color: #252542 !important;
  }
  body.dark-mode .dropdown-menu {
    background-color: #1a1a2e !important;
    border-color: #2d2d48 !important;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5) !important;
  }
  body.dark-mode .dropdown-item {
    color: #cbd5e1 !important;
  }
  body.dark-mode .dropdown-item:hover,
  body.dark-mode .dropdown-item:focus,
  body.dark-mode .dropdown-item.active {
    background-color: #2d2d48 !important;
    color: #ffffff !important;
  }
  body.dark-mode .input-group-text {
    background-color: #1e1e38 !important;
    color: #cbd5e1 !important;
    border-color: #3b3b5e !important;
  }
  body.dark-mode .form-control,
  body.dark-mode .form-select,
  body.dark-mode .form-textarea,
  body.dark-mode textarea {
    background-color: #1e1e38 !important;
    color: #ffffff !important;
    border-color: #3b3b5e !important;
  }
  body.dark-mode .form-control:focus,
  body.dark-mode .form-select:focus {
    border-color: #e91e63 !important;
    box-shadow: 0 0 0 0.25rem rgba(233, 30, 99, 0.25) !important;
  }
  body.dark-mode .page-header {
    background: linear-gradient(135deg, #ad1457, #6a1b9a) !important;
  }

  /* Button Contrast & Dark Mode */
  body.dark-mode .btn-outline-dark {
    color: #f1f5f9 !important;
    border-color: #64748b !important;
    background-color: rgba(255, 255, 255, 0.08) !important;
  }
  body.dark-mode .btn-outline-dark:hover {
    background-color: #ffffff !important;
    color: #0f172a !important;
    border-color: #ffffff !important;
  }
  body.dark-mode .btn-outline-secondary {
    color: #cbd5e1 !important;
    border-color: #475569 !important;
    background-color: rgba(255, 255, 255, 0.05) !important;
  }
  body.dark-mode .btn-outline-secondary:hover {
    background-color: #475569 !important;
    color: #ffffff !important;
  }
  /* Crisp Light Mode Button Visibility */
  .btn-outline-info {
    color: #0284c7 !important;
    border-color: #0284c7 !important;
    font-weight: 600 !important;
  }
  .btn-outline-info:hover {
    background-color: #0284c7 !important;
    color: #ffffff !important;
  }
  .btn-outline-success {
    color: #16a34a !important;
    border-color: #16a34a !important;
    font-weight: 600 !important;
  }
  .btn-outline-success:hover {
    background-color: #16a34a !important;
    color: #ffffff !important;
  }
  .btn-outline-warning {
    color: #b45309 !important;
    border-color: #d97706 !important;
    font-weight: 600 !important;
  }
  .btn-outline-warning:hover {
    background-color: #d97706 !important;
    color: #ffffff !important;
  }
  .btn-outline-primary {
    color: #7b1fa2 !important;
    border-color: #9c27b0 !important;
    font-weight: 600 !important;
  }
  .btn-outline-primary:hover {
    background-color: #9c27b0 !important;
    color: #ffffff !important;
  }

  /* Universal High-Contrast Buttons */
  .btn-qr-pass {
    background: #ffffff !important;
    color: #4338ca !important;
    border: 2px solid #6366f1 !important;
    font-weight: 700 !important;
    transition: all 0.25s ease !important;
  }
  .btn-qr-pass:hover {
    background: #4f46e5 !important;
    color: #ffffff !important;
    border-color: #4f46e5 !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
  }
  body.dark-mode .btn-qr-pass {
    background: rgba(99, 102, 241, 0.2) !important;
    color: #a5b4fc !important;
    border-color: #6366f1 !important;
  }
  body.dark-mode .btn-qr-pass:hover {
    background: #6366f1 !important;
    color: #ffffff !important;
  }

  .btn-rate-review {
    background: #ffffff !important;
    color: #b45309 !important;
    border: 2px solid #f59e0b !important;
    font-weight: 700 !important;
    transition: all 0.25s ease !important;
  }
  .btn-rate-review:hover {
    background: #f59e0b !important;
    color: #ffffff !important;
    border-color: #f59e0b !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);
  }
  body.dark-mode .btn-rate-review {
    background: rgba(245, 158, 11, 0.2) !important;
    color: #fbbf24 !important;
    border-color: #f59e0b !important;
  }
  body.dark-mode .btn-rate-review:hover {
    background: #f59e0b !important;
    color: #1e1b4b !important;
  }

  .btn-review {
    background: #ffffff !important;
    color: #c2185b !important;
    border: 2px solid #e91e63 !important;
    font-weight: 700 !important;
    transition: all 0.25s ease !important;
  }
  .btn-review:hover {
    background: var(--gradient-primary) !important;
    color: #ffffff !important;
    border-color: transparent !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(233, 30, 99, 0.35);
  }
  body.dark-mode .btn-review {
    background: rgba(233, 30, 99, 0.2) !important;
    color: #f472b6 !important;
    border-color: #ec4899 !important;
  }
  body.dark-mode .btn-review:hover {
    background: var(--gradient-primary) !important;
    color: #ffffff !important;
  }

  .btn-view {
    background: #ffffff !important;
    color: #6a1b9a !important;
    border: 2px solid #9c27b0 !important;
    font-weight: 700 !important;
    transition: all 0.25s ease !important;
  }
  .btn-view:hover {
    background: var(--gradient-primary) !important;
    color: #ffffff !important;
    border-color: transparent !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(156, 39, 176, 0.35);
  }
  body.dark-mode .btn-view {
    background: rgba(156, 39, 176, 0.2) !important;
    color: #c084fc !important;
    border-color: #a855f7 !important;
  }
  body.dark-mode .btn-view:hover {
    background: var(--gradient-primary) !important;
    color: #ffffff !important;
  }

  body.dark-mode .btn-outline-warning {
    color: #fbbf24 !important;
    border-color: #fbbf24 !important;
    background-color: rgba(251, 191, 36, 0.1) !important;
  }
  body.dark-mode .btn-outline-warning:hover {
    background-color: #fbbf24 !important;
    color: #1e1b4b !important;
  }
  body.dark-mode .btn-outline-primary {
    color: #c4b5fd !important;
    border-color: #8b5cf6 !important;
    background-color: rgba(139, 92, 246, 0.1) !important;
  }
  body.dark-mode .btn-outline-primary:hover {
    background-color: #8b5cf6 !important;
    color: #ffffff !important;
  }
  body.dark-mode .btn-outline-info {
    color: #38bdf8 !important;
    border-color: #0284c7 !important;
    background-color: rgba(56, 189, 248, 0.1) !important;
  }
  body.dark-mode .btn-outline-info:hover {
    background-color: #0284c7 !important;
    color: #ffffff !important;
  }
  body.dark-mode .btn-outline-success {
    color: #4ade80 !important;
    border-color: #16a34a !important;
    background-color: rgba(74, 222, 128, 0.1) !important;
  }
  body.dark-mode .btn-outline-success:hover {
    background-color: #16a34a !important;
    color: #ffffff !important;
  }
  body.dark-mode .btn-modal-secondary {
    background-color: #2d2d48 !important;
    color: #e2e8f0 !important;
    border-color: #3b3b5e !important;
  }
  body.dark-mode .btn-modal-secondary:hover {
    background-color: #3b3b5e !important;
    color: #ffffff !important;
  }
  body.dark-mode .modal-title,
  body.dark-mode .char-counter {
    color: #f8fafc !important;
  }
   
/* ================================
   NAVBAR
   ================================ */
.navbar {
  padding: 1rem 0;
  background: transparent !important;
  transition: var(--transition);
  z-index: 1000;
  background: rgba(26, 26, 46, 0.95) ;
  
  
}

.navbar.scrolled {
  background: rgba(26, 26, 46, 0.95) !important;
  backdrop-filter: blur(10px);
  box-shadow: var(--shadow-md);
}

.navbar-brand {
  font-size: 1.5rem;
  font-weight: 800;
  background: var(--gradient-primary);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  transition: var(--transition);
}

.navbar-brand i {
  background: var(--gradient-primary);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-right: 0.5rem;
}

.navbar.scrolled .navbar-brand,
.navbar.scrolled .nav-link {
  color: white !important;
}

.navbar.scrolled .navbar-brand {
  -webkit-text-fill-color: white;
}

.nav-link {
  font-weight: 500;
  padding: 0.5rem 1rem !important;
  margin: 0 0.25rem;
  border-radius: 8px;
  transition: var(--transition);
  color: white !important;
}

.nav-link:hover,
.nav-link.active {
  background: rgba(255, 255, 255, 0.1);
  transform: translateY(-2px);
}

.btn-gradient {
  background: var(--gradient-primary);
  color: white;
  border: none;
  padding: 0.5rem 1.5rem;
  border-radius: 50px;
  font-weight: 600;
  box-shadow: 0 4px 15px rgba(233, 30, 99, 0.4);
  transition: var(--transition);
}

.btn-gradient:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(233, 30, 99, 0.5);
  color: white;
}

.btn-outline-light {
  border: 2px solid rgba(255, 255, 255, 0.3);
  color: white;
  border-radius: 50px;
  transition: var(--transition);
}

.btn-outline-light:hover {
  background: white;
  color: var(--primary);
  border-color: white;
  transform: translateY(-2px);
}

.navbar-toggler {
  border: none;
  padding: 0.5rem;
}

.navbar-toggler:focus {
  box-shadow: none;
}

body {
  padding-top: 80px;
}

.btn-nav-back {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.25);
  color: #ffffff !important;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  transition: all 0.2s ease;
  flex-shrink: 0;
  text-decoration: none;
  cursor: pointer;
}

.btn-nav-back:hover {
  background: var(--gradient-primary);
  border-color: transparent;
  transform: translateX(-2px);
  box-shadow: 0 4px 12px rgba(233, 30, 99, 0.4);
  color: #ffffff !important;
}

body.dark-mode .btn-nav-back {
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.18);
}

.navbar-nav {
  flex-wrap: nowrap !important;
}

.navbar-nav .nav-link {
  font-size: 0.88rem !important;
  padding: 0.45rem 0.65rem !important;
  margin: 0 0.1rem !important;
  white-space: nowrap !important;
  display: inline-flex !important;
  align-items: center !important;
}

.navbar-nav .btn-outline-light {
  padding: 0.35rem 0.85rem !important;
  font-size: 0.85rem !important;
  white-space: nowrap !important;
}

  </style>
</head>

<body>
  <header>
    <!-- Navbar - Always Visible with Original Colors -->
    <nav class="navbar navbar-expand-lg fixed-top scrolled" id="mainNav">
      <div class="container">
        <div class="d-flex align-items-center">
          <?php 
          $currentScript = basename($_SERVER['PHP_SELF'] ?? '');
          $isHome = ($currentScript === 'index.php' || empty($currentScript));
          ?>
          <?php if (!$isHome): ?>
          <button type="button" onclick="handleGlobalBack()" class="btn btn-nav-back me-2 shadow-sm" title="Go Back">
            <i class="fas fa-arrow-left"></i>
          </button>
          <?php endif; ?>

          <a class="navbar-brand" href="<?= url('index.php') ?>">
            <i class="fas fa-spa"></i> Salonora
          </a>
        </div>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
          <ul class="navbar-nav align-items-center">
            <?php if (isset($_SESSION['id'])): ?>
              <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'owner'): ?>
                <li class="nav-item"><a href="<?= url('owner/dashboard.php') ?>" class="nav-link"><i class="fas fa-chart-line me-1"></i> Dashboard</a></li>
                <li class="nav-item"><a href="<?= url('owner/appointments.php') ?>" class="nav-link"><i class="far fa-calendar-alt me-1"></i> Bookings</a></li>
                <li class="nav-item"><a href="<?= url('owner/checkin.php') ?>" class="nav-link"><i class="fas fa-qrcode me-1"></i> QR Check-in</a></li>
                <li class="nav-item dropdown">
                  <a class="nav-link dropdown-toggle" href="#" id="ownerToolsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-tools me-1"></i> Manage
                  </a>
                  <ul class="dropdown-menu dropdown-menu-dark shadow" aria-labelledby="ownerToolsDropdown">
                    <li><a class="dropdown-item" href="<?= url('owner/services.php') ?>"><i class="fas fa-cut me-2"></i> Services</a></li>
                    <li><a class="dropdown-item" href="<?= url('owner/staff.php') ?>"><i class="fas fa-user-friends me-2"></i> Staff & Stylists</a></li>
                    <li><a class="dropdown-item" href="<?= url('owner/promos.php') ?>"><i class="fas fa-tags me-2"></i> Promo Codes</a></li>
                    <li><a class="dropdown-item" href="<?= url('owner/gallery.php') ?>"><i class="fas fa-images me-2"></i> Gallery Portfolio</a></li>
                  </ul>
                </li>
                <li class="nav-item"><a href="<?= url('user/salon_view.php') ?>" class="nav-link"><i class="fas fa-store me-1"></i> Salons</a></li>
              <?php else: ?>
                <li class="nav-item"><a href="<?= url('index.php') ?>" class="nav-link"><i class="fas fa-home me-1"></i> Home</a></li>
                <li class="nav-item"><a href="<?= url('user/salon_view.php') ?>" class="nav-link"><i class="fas fa-cut me-1"></i> Salons</a></li>
                <li class="nav-item"><a href="<?= url('user/ai_advisor.php') ?>" class="nav-link"><i class="fas fa-magic text-warning me-1"></i> AI Advisor</a></li>
                <li class="nav-item"><a href="<?= url('user/my_appointments.php') ?>" class="nav-link"><i class="far fa-calendar-check me-1"></i> Appointments</a></li>
              <?php endif; ?>
              
              <li class="nav-item"><a href="<?= url('notifications.php') ?>" class="nav-link"><i class="far fa-bell me-1"></i> Notifications</a></li>
              <li class="nav-item"><a href="<?= url('user/profile.php') ?>" class="nav-link"><i class="far fa-user me-1"></i> Profile</a></li>
              <li class="nav-item ms-2">
                <button type="button" id="themeToggleBtn" class="btn btn-outline-light btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width:34px;height:34px;" title="Toggle Theme">
                  <i class="fas fa-moon" id="themeToggleIcon"></i>
                </button>
              </li>
              <li class="nav-item ms-2">
                <a href="<?= url('logout.php') ?>" class="btn btn-outline-light btn-sm rounded-pill">
                  <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
              </li>
            <?php else: ?>
              <li class="nav-item"><a href="<?= url('index.php') ?>" class="nav-link"><i class="fas fa-home me-1"></i> Home</a></li>
              <li class="nav-item"><a href="<?= url('user/salon_view.php') ?>" class="nav-link"><i class="fas fa-cut me-1"></i> Salons</a></li>
              <li class="nav-item"><a href="<?= url('user/ai_advisor.php') ?>" class="nav-link"><i class="fas fa-magic text-warning me-1"></i> AI Advisor</a></li>
              <li class="nav-item"><a href="<?= url('index.php#contact') ?>" class="nav-link"><i class="far fa-envelope me-1"></i> Contact</a></li>
              <li class="nav-item ms-2">
                <button type="button" id="themeToggleBtn" class="btn btn-outline-light btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width:34px;height:34px;" title="Toggle Theme">
                  <i class="fas fa-moon" id="themeToggleIcon"></i>
                </button>
              </li>
              <li class="nav-item ms-3">
                <a href="<?= url('login.php') ?>" class="btn btn-gradient rounded-pill">
                  <i class="fas fa-sign-in-alt me-1"></i> Login
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </nav>
  </header>

  <script>
    // Dark/Light Mode Toggle Logic
    document.addEventListener('DOMContentLoaded', function() {
      const toggleBtn = document.getElementById('themeToggleBtn');
      const toggleIcon = document.getElementById('themeToggleIcon');
      
      function updateIcon(isDark) {
        if (!toggleIcon) return;
        if (isDark) {
          toggleIcon.classList.remove('fa-moon');
          toggleIcon.classList.add('fa-sun');
          toggleIcon.style.color = '#f1c40f';
        } else {
          toggleIcon.classList.remove('fa-sun');
          toggleIcon.classList.add('fa-moon');
          toggleIcon.style.color = '#ffffff';
        }
      }

      const isCurrentDark = document.documentElement.classList.contains('dark-mode') || localStorage.getItem('salonora_theme') === 'dark';
      if (isCurrentDark) {
        document.documentElement.classList.add('dark-mode');
        document.body.classList.add('dark-mode');
        updateIcon(true);
      }

      if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
          const isDark = !document.documentElement.classList.contains('dark-mode');
          document.documentElement.classList.toggle('dark-mode', isDark);
          document.body.classList.toggle('dark-mode', isDark);
          localStorage.setItem('salonora_theme', isDark ? 'dark' : 'light');
          updateIcon(isDark);
        });
      }

      // Register PWA Service Worker
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('<?= url("sw.js") ?>').catch(() => {});
      }
    });

    // Global intelligent back navigation function
    function handleGlobalBack() {
      if (window.history.length > 1 && document.referrer && document.referrer.includes(window.location.host)) {
        window.history.back();
      } else {
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'owner'): ?>
        window.location.href = '<?= url("owner/dashboard.php") ?>';
        <?php else: ?>
        window.location.href = '<?= url("index.php") ?>';
        <?php endif; ?>
      }
    }
  </script>