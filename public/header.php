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
     DARK MODE THEME
     ================================ */
  html.dark-mode, body.dark-mode {
    background-color: #111122 !important;
    color: #e2e8f0 !important;
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
  body.dark-mode .stat-box {
    background-color: #1a1a2e !important;
    color: #e2e8f0 !important;
    border-color: #2d2d48 !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4) !important;
  }
  body.dark-mode .text-dark,
  body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, 
  body.dark-mode h4, body.dark-mode h5, body.dark-mode h6,
  body.dark-mode .form-label, body.dark-mode strong {
    color: #f8fafc !important;
  }
  body.dark-mode .text-muted, body.dark-mode .form-hint {
    color: #94a3b8 !important;
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

.btn-back {
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.3);
  color: #ffffff !important;
  border-radius: 50px;
  padding: 0.35rem 0.85rem;
  font-size: 0.85rem;
  font-weight: 600;
  transition: all 0.25s ease;
  backdrop-filter: blur(5px);
  text-decoration: none;
  cursor: pointer;
  line-height: 1.2;
}

.btn-back:hover {
  background: var(--gradient-primary);
  border-color: transparent;
  color: #ffffff !important;
  transform: translateX(-3px);
  box-shadow: 0 4px 12px rgba(233, 30, 99, 0.4);
}

.btn-back i {
  transition: transform 0.2s ease;
}

.btn-back:hover i {
  transform: translateX(-2px);
}

body.dark-mode .btn-back {
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.18);
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
          <button type="button" onclick="handleGlobalBack()" class="btn btn-back me-3 d-inline-flex align-items-center justify-content-center shadow-sm" title="Go Back">
            <i class="fas fa-arrow-left me-1"></i>
            <span class="d-none d-sm-inline">Back</span>
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
            <li class="nav-item"><a href="<?= url('index.php') ?>" class="nav-link">Home</a></li>
            <li class="nav-item"><a href="<?= url('user/salon_view.php') ?>" class="nav-link"><i class="fas fa-cut me-1"></i> Salons</a></li>
            
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
              <?php else: ?>
                <li class="nav-item"><a href="<?= url('user/my_appointments.php') ?>" class="nav-link"><i class="far fa-calendar-check me-1"></i> Appointments</a></li>
              <?php endif; ?>
              
              <li class="nav-item"><a href="<?= url('notifications.php') ?>" class="nav-link"><i class="far fa-bell me-1"></i> Notifications</a></li>
              <li class="nav-item"><a href="<?= url('user/profile.php') ?>" class="nav-link"><i class="far fa-user me-1"></i> Profile</a></li>
              <li class="nav-item ms-2">
                <button type="button" id="themeToggleBtn" class="btn btn-outline-light btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;" title="Toggle Theme">
                  <i class="fas fa-moon" id="themeToggleIcon"></i>
                </button>
              </li>
              <li class="nav-item ms-3">
                <a href="<?= url('logout.php') ?>" class="btn btn-outline-light btn-sm">
                  <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
              </li>
            <?php else: ?>
              <li class="nav-item"><a href="<?= url('index.php#contact') ?>" class="nav-link">Contact</a></li>
              <li class="nav-item ms-2">
                <button type="button" id="themeToggleBtn" class="btn btn-outline-light btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;" title="Toggle Theme">
                  <i class="fas fa-moon" id="themeToggleIcon"></i>
                </button>
              </li>
              <li class="nav-item ms-3">
                <a href="<?= url('login.php') ?>" class="btn btn-gradient">
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
          const isDark = document.body.classList.toggle('dark-mode');
          document.documentElement.classList.toggle('dark-mode', isDark);
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