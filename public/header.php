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