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
  <title>Salonora - Find Salons Nearby</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <style>
    body { background: #f8f9fa; }
    #map { height: 450px; border-radius: 10px; margin-top: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    .btn-custom { border-radius: 8px; transition: 0.2s; }
    .btn-custom:hover { transform: translateY(-2px); }
    .navbar-brand { font-weight: bold; }
    .notif-badge {
        position: absolute;
        top: -5px; right: -10px;
        background: red; color: white;
        padding: 2px 6px; border-radius: 50%;
        font-size: 12px;
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand" href="index.php">Salonora</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <?php if (!$loggedIn): ?>
          <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
          <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
        <?php else: ?>
          <?php if ($role === 'owner'): ?>
            <li class="nav-item"><a class="nav-link" href="owner/dashboard.php">Dashboard</a></li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link" href="user/profile.php">Profile</a></li>
            <li class="nav-item"><a class="nav-link" href="user/my_appointments.php">My Appointments</a></li>
            <li class="nav-item"><a class="nav-link" href="user/salon_view.php">Salons</a></li>
            <li class="nav-item position-relative">
              <a class="nav-link" href="notifications.php">
                🔔 Notifications
                <?php if ($unreadCount > 0): ?>
                  <span class="notif-badge"><?= $unreadCount ?></span>
                <?php endif; ?>
              </a>
            </li>
          <?php endif; ?>
          <li class="nav-item"><a class="nav-link text-danger" href="logout.php">Logout</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- Map Container -->
<div class="container text-center mt-4">
  <h2 class="mb-4">Find the Best Salons Near You</h2>
  <div class="input-group mb-4 mx-auto" style="max-width:640px;">
    <input id="searchBox" type="text" class="form-control" placeholder="Search by salon name or address">
    <button id="searchBtn" class="btn btn-light btn-custom">Search</button>
  </div>
  <div id="map"></div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="user/post_review.php" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Write a Review</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="salon_id" id="reviewSalonId">
        <div class="mb-2">
          <label>Rating</label>
          <select name="rating" class="form-select" required>
            <option value="5">★★★★★</option>
            <option value="4">★★★★</option>
            <option value="3">★★★</option>
            <option value="2">★★</option>
            <option value="1">★</option>
          </select>
        </div>
        <div class="mb-2">
          <label>Comment</label>
          <textarea name="comment" class="form-control" rows="3" placeholder="Your review..." required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success">Submit Review</button>
      </div>
    </form>
  </div>
</div>

<!-- Footer -->
<footer class="text-center mt-5 mb-4 text-muted">&copy; <?= date('Y') ?> Salonora</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
var map = L.map('map').setView([6.9271, 79.8612], 15);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

var salons = <?= json_encode($salons) ?>;

// Add salon markers
salons.forEach(function(salon){
    const popup = `
        <b>${salon.name}</b><br>${salon.address}<br>
        <?php if ($loggedIn && $role !== 'owner'): ?>
        <a href="user/salon_details.php?id=${salon.id}" class="btn btn-sm btn-primary btn-custom mt-1">View</a>
        <button class="btn btn-sm btn-success btn-custom mt-1" onclick="openReviewModal(${salon.id})">Write Review</button>
        <?php endif; ?>
    `;
    L.marker([salon.lat, salon.lng]).addTo(map).bindPopup(popup);
});

// Search functionality
document.getElementById('searchBtn').addEventListener('click', function(){
    const query = document.getElementById('searchBox').value.toLowerCase();
    for (const salon of salons) {
        if (salon.name.toLowerCase().includes(query) || salon.address.toLowerCase().includes(query)) {
            map.setView([salon.lat, salon.lng], 17);
            break;
        }
    }
});

function openReviewModal(salonId){
    document.getElementById('reviewSalonId').value = salonId;
    new bootstrap.Modal(document.getElementById('reviewModal')).show();
}
</script>
</body>
</html>
