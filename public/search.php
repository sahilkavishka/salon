<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;

$sql = "SELECT id AS salon_id, name, address, latitude, longitude FROM salons";
$params = [];

$conditions = [];
if ($q !== '') {
    $conditions[] = "(name LIKE :q OR address LIKE :q)";
    $params[':q'] = "%$q%";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// Location-based ordering
if ($lat !== null && $lng !== null) {
    $sql .= empty($conditions) ? "" : " ";
    $sql .= "ORDER BY (POW(latitude-:lat,2)+POW(longitude-:lng,2)) ASC LIMIT 20";
    $params[':lat'] = $lat;
    $params[':lng'] = $lng;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
