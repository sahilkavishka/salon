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
  <link rel="stylesheet" href="/salonora/public/assets/css/style.css">

 

  <style>
    /* Force navbar to always show as scrolled (keeps original colors from style.css) */
    #mainNav.scrolled {
      /* Your style.css will handle the colors */
    }

    /* Add padding to body to account for fixed navbar */
    body {
      padding-top: 80px;
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

  </style>
</head>

<body>
  <header>
    <!-- Navbar - Always Visible with Original Colors -->
    <nav class="navbar navbar-expand-lg fixed-top scrolled" id="mainNav">
      <div class="container">
        <a class="navbar-brand" href="/salonora/public/index.php">
          <i class="fas fa-spa"></i> Salonora
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
          <ul class="navbar-nav align-items-center">
            <li class="nav-item"><a href="/salonora/public/index.php" class="nav-link">Home</a></li>
            <li class="nav-item"><a href="/salonora/public/user/salon_view.php" class="nav-link"><i class="fas fa-cut me-1"></i> Salons</a></li>
            <li class="nav-item"><a href="/salonora/public/user/my_appointments.php" class="nav-link"><i class="far fa-calendar-check me-1"></i> Appointments</a></li>
            <li class="nav-item"><a href="#contact" class="nav-link">Contact</a></li>
            <li class="nav-item"><a href="/salonora/public/notifications.php" class="nav-link"><i class="far fa-bell me-1"></i> Notifications</a></li>
            <li class="nav-item"><a href="/salonora/public/user/profile.php" class="nav-link"><i class="far fa-user me-1"></i> Profile</a></li>

            <?php if (isset($_SESSION['id'])): ?>
              <li class="nav-item ms-3">
                <a href="/salonora/public/logout.php" class="btn btn-outline-light btn-sm">
                  <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
              </li>
            <?php else: ?>
              <li class="nav-item ms-3">
                <a href="/salonora/public/login.php" class="btn btn-gradient">
                  <i class="fas fa-sign-in-alt me-1"></i> Login
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </nav>
  </header>