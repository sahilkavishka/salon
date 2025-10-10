<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$q = $_GET['q'] ?? '';
$lat = $_GET['lat'] ?? '';
$lng = $_GET['lng'] ?? '';

$sql = "SELECT id AS salon_id, name, address, latitude, longitude FROM salons";
$params = [];

if ($q !== '') {
    $sql .= " WHERE name LIKE :q OR address LIKE :q";
    $params[':q'] = "%$q%";
} elseif ($lat !== '' && $lng !== '') {
    $sql .= " ORDER BY (POW(latitude-:lat,2)+POW(longitude-:lng,2)) ASC LIMIT 20";
    $params[':lat'] = $lat;
    $params[':lng'] = $lng;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
