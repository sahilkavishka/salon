<?php
// public/search_api.php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$query = trim($_GET['query'] ?? '');

if ($query === '') {
    echo json_encode([]);
    exit;
}

try {
    // Search salons by name, address, or service
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.id, s.lat, s.lng
        FROM salons s
        LEFT JOIN services srv ON srv.salon_id = s.id
        WHERE s.name LIKE :q 
           OR s.address LIKE :q 
           OR srv.name LIKE :q
    ");
    $stmt->execute([':q' => "%$query%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($results);

} catch (Exception $e) {
    echo json_encode([]);
}
