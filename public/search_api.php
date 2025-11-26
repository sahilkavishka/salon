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
    // + return full data for frontend
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.name,
            s.address,
            s.phone,
            s.email,
            s.lat,
            s.lng,
            s.opening_time,
            s.closing_time,
            s.image AS image_url,
            s.rating,
            s.review_count,
            GROUP_CONCAT(DISTINCT srv.name ORDER BY srv.name SEPARATOR '||') AS services
        FROM salons s
        LEFT JOIN services srv ON srv.salon_id = s.id
        WHERE s.name LIKE :q 
           OR s.address LIKE :q 
           OR srv.name LIKE :q
        GROUP BY 
            s.id, s.name, s.address, s.phone, s.email,
            s.lat, s.lng, s.opening_time, s.closing_time,
            s.image, s.rating, s.review_count
        ORDER BY s.rating DESC, s.name ASC
        LIMIT 100
    ");
    $stmt->execute([':q' => "%{$query}%"]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($rows as $row) {
        $services = [];
        if (!empty($row['services'])) {
            $services = explode('||', $row['services']);
        }

        $results[] = [
            'id'             => (int)$row['id'],
            'name'           => $row['name'],
            'address'        => $row['address'],
            'phone'          => $row['phone'],
            'email'          => $row['email'],
            'lat'            => (float)$row['lat'],
            'lng'            => (float)$row['lng'],
            'opening_time'   => $row['opening_time'],
            'closing_time'   => $row['closing_time'],
            'image_url'      => $row['image_url'],
            'rating'         => $row['rating'] !== null ? (float)$row['rating'] : null,
            'review_count'   => $row['review_count'] !== null ? (int)$row['review_count'] : 0,
            'services'       => $services,
        ];
    }

    echo json_encode($results);

} catch (Exception $e) {
    // For security, don't show internal error message to user
    echo json_encode([
        'error' => 'An error occurred while searching. Please try again.'
    ]);
}
