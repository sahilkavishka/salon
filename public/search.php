<?php
// public/search.php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

// Accept filters: q (text), lat, lng, radius (km)
$q = trim($_GET['q'] ?? '');
$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
$radius_km = isset($_GET['radius']) ? floatval($_GET['radius']) : 10; // default 10km

if ($lat && $lng) {
    // Haversine formula in SQL to compute distance
    $sql = "SELECT salon_id, name, address, latitude, longitude, contact, description,
        ( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance_km
        FROM salons
        HAVING distance_km <= ?
        ORDER BY distance_km ASC
        LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$lat, $lng, $lat, $radius_km]);
    $rows = $stmt->fetchAll();
} else if ($q !== '') {
    $stmt = $pdo->prepare("SELECT salon_id, name, address, latitude, longitude, contact, description FROM salons WHERE name LIKE ? OR address LIKE ? LIMIT 100");
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like]);
    $rows = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT salon_id, name, address, latitude, longitude, contact, description FROM salons LIMIT 100");
    $stmt->execute();
    $rows = $stmt->fetchAll();
}

echo json_encode($rows);
