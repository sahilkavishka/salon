<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/config.php';

// Initialize variables
$userPic = null;
$username = null;
$role = null;

// If user is logged in, fetch profile info
if (isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'])) {
    $username = $_SESSION['username'];
    $role = $_SESSION['role'];

    $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $userPic = $stmt->fetchColumn() ?: null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salon Finder</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header role="banner">
    <h1>Salon Finder</h1>
    <nav role="navigation">
        <?php if ($username && $role): ?>
            <?php if ($userPic): ?>
                <img 
                    src="../uploads/<?= htmlspecialchars($userPic) ?>" 
                    alt="<?= htmlspecialchars($username) ?>'s Profile Picture" 
                    class="profile-pic"
                >
            <?php endif; ?>
            <span>
                Welcome, <?= htmlspecialchars($username) ?> (<?= htmlspecialchars($role) ?>)
            </span>
            <?php if ($role === 'owner'): ?>
                <a href="add_salon.php">Add Salon</a>
                <a href="manage_salons.php">Manage Salons</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </nav>
</header>

<main role="main">
    <!-- Search Form -->
    <section id="search-section">
        <form id="searchForm">
            <label for="locationInput" class="visually-hidden">Location</label>
            <input 
                id="locationInput" 
                name="location" 
                placeholder="Enter address (or use my location)" 
                type="text"
            >

            <label for="typeSelect" class="visually-hidden">Salon Type</label>
            <select id="typeSelect" name="type">
                <option value="">All</option>
                <option value="beauty">Beauty</option>
                <option value="barber">Barber</option>
                <option value="spa">Spa</option>
            </select>

            <label for="radiusInput" class="visually-hidden">Radius</label>
            <input 
                type="number" 
                id="radiusInput" 
                name="radius" 
                value="5" 
                min="1"
            > km

            <button type="submit">Search</button>
            <button type="button" id="useLocation">Use my location</button>
        </form>
    </section>

    <!-- Map + Results -->
    <section id="map-section">
        <div id="map" aria-label="Map of nearby salons"></div>
        <div id="results" aria-live="polite"></div>
    </section>
</main>

<script src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_API_KEY&callback=initMap" async defer></script>
<script src="assets/js/map.js"></script>

</body>
</html>
