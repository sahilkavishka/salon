<?php
// public/search.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';


header('Content-Type: application/json; charset=utf-8');


$radius = isset($_GET['radius']) ? (float)$_GET['radius'] : 5; // km
$type = isset($_GET['type']) && $_GET['type'] !== '' ? $_GET['type'] : null;


if (isset($_GET['lat']) && isset($_GET['lng'])) {
$lat = (float)$_GET['lat'];
$lng = (float)$_GET['lng'];
} elseif (!empty($_GET['location'])) {
$coords = geocodeAddress($_GET['location']);
if (!$coords) { echo json_encode(['error' => 'Could not geocode address']); exit; }
$lat = $coords['lat'];
$lng = $coords['lng'];
} else {
http_response_code(400);
echo json_encode(['error' => 'No location provided']);
exit;
}


$sql = "SELECT id, name, address, latitude, longitude, type, rating,
(6371 * acos(
cos(radians(:lat)) * cos(radians(latitude)) * cos(radians(longitude) - radians(:lng))
+ sin(radians(:lat)) * sin(radians(latitude))
)) AS distance
FROM salons";


$params = [':lat' => $lat, ':lng' => $lng];


if ($type) {
$sql .= ' WHERE type = :type';
$params[':type'] = $type;
}


$sql .= ' HAVING distance <= :radius ORDER BY distance ASC LIMIT 50';
$params[':radius'] = $radius;


$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();


echo json_encode($rows);