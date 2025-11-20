<?php
// public/search_api.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$query = trim($_GET['query'] ?? '');

if ($query === '') {
    echo json_encode([]);
    exit;
}

try {
    // Search by salon name or address
    $stmt = $pdo->prepare("
        SELECT id, name, address, lat, lng
        FROM salons
        WHERE name LIKE :q OR address LIKE :q
    ");
    $stmt->execute([':q' => "%$query%"]);
    $salons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Only include lat & lng (for marker-only map)
    $results = array_map(function($s) {
        return [
            'id' => (int)$s['id'],
            'lat' => (float)$s['lat'],
            'lng' => (float)$s['lng']
        ];
    }, $salons);

    echo json_encode($results);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
