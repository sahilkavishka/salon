<?php
require_once __DIR__ . '/../config.php';

$query = trim($_GET['query'] ?? '');
$results = [];

if ($query !== "") {

    $stmt = $pdo->prepare("
        SELECT id, name, address, lat, lng
        FROM salons
        WHERE name LIKE :q OR address LIKE :q
    ");
    $stmt->execute([':q' => "%$query%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

header("Content-Type: application/json");
echo json_encode($results);
