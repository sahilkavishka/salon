<?php
session_start();
require '../includes/config.php'; // Database connection

header('Content-Type: application/json');

// Get search parameters
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$type     = isset($_GET['type']) ? trim($_GET['type']) : '';
$radius   = isset($_GET['radius']) ? (float)$_GET['radius'] : 5;

// Default response
$response = [];

// Function to calculate distance (Haversine formula)
function distance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}

// Convert location to lat/lng using OpenStreetMap Nominatim
if($location !== '') {
    $location = urlencode($location);
    $url = "https://nominatim.openstreetmap.org/search?q={$location}&format=json&limit=1";
    $json = file_get_contents($url);
    $data = json_decode($json, true);

    if(!empty($data)) {
        $userLat = (float)$data[0]['lat'];
        $userLng = (float)$data[0]['lon'];
    } else {
        // If location not found, return empty array
        echo json_encode([]);
        exit;
    }
} else {
    // Default center if no location provided
    $userLat = 7.2906;  // Anuradhapura lat
    $userLng = 80.6337; // Anuradhapura lng
}

// Build SQL query
$sql = "SELECT id, name, type, latitude, longitude FROM salons WHERE 1=1";
$params = [];

if($type !== '') {
    $sql .= " AND type = :type";
    $params['type'] = $type;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$salons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter salons by radius
foreach($salons as $salon) {
    $d = distance($userLat, $userLng, $salon['latitude'], $salon['longitude']);
    if($d <= $radius) {
        $response[] = [
            'id' => $salon['id'],
            'name' => $salon['name'],
            'type' => $salon['type'],
            'lat' => (float)$salon['latitude'],
            'lng' => (float)$salon['longitude'],
            'distance' => round($d, 2)
        ];
    }
}

echo json_encode($response);
