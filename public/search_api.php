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
    /**
     * Search by:
     *  - salon name
     *  - address
     *  - description
     *  - website/facebook/instagram
     *  - (optional) services table later
     */

    $sql = "
        SELECT 
            s.id,
            s.name,
            s.address,
            s.phone,
            s.email,
            s.description,
            s.website,
            s.facebook,
            s.instagram,
            s.parking_available,
            s.wheelchair_accessible,
            s.wifi_available,
            s.air_conditioned,
            s.image,
            s.lat,
            s.lng,
            s.opening_time,
            s.closing_time,
            s.slot_duration,
            COALESCE(AVG(r.rating), 0) AS avg_rating,
            COUNT(r.id) AS review_count
        FROM salons s
        LEFT JOIN reviews r ON r.salon_id = s.id
        WHERE 
            s.name LIKE :q
            OR s.address LIKE :q
            OR s.description LIKE :q
            OR s.website LIKE :q
            OR s.facebook LIKE :q
            OR s.instagram LIKE :q
        GROUP BY 
            s.id,
            s.name,
            s.address,
            s.phone,
            s.email,
            s.description,
            s.website,
            s.facebook,
            s.instagram,
            s.parking_available,
            s.wheelchair_accessible,
            s.wifi_available,
            s.air_conditioned,
            s.image,
            s.lat,
            s.lng,
            s.opening_time,
            s.closing_time,
            s.slot_duration
        ORDER BY avg_rating DESC, s.name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':q' => '%' . $query . '%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];

    foreach ($rows as $row) {
        $imageUrl = null;
        if (!empty($row['image'])) {
            // adjust if your upload path is different
            $imageUrl = '/uploads/salons/' . $row['image'];
        }

        $results[] = [
            'id'                   => (int)$row['id'],
            'name'                 => $row['name'],
            'address'              => $row['address'],
            'phone'                => $row['phone'],
            'email'                => $row['email'],
            'description'          => $row['description'],
            'website'              => $row['website'],
            'facebook'             => $row['facebook'],
            'instagram'            => $row['instagram'],
            'parking_available'    => (int)$row['parking_available'],
            'wheelchair_accessible'=> (int)$row['wheelchair_accessible'],
            'wifi_available'       => (int)$row['wifi_available'],
            'air_conditioned'      => (int)$row['air_conditioned'],
            'image_url'            => $imageUrl,
            'lat'                  => $row['lat'] !== null ? (float)$row['lat'] : null,
            'lng'                  => $row['lng'] !== null ? (float)$row['lng'] : null,
            'opening_time'         => $row['opening_time'],
            'closing_time'         => $row['closing_time'],
            'slot_duration'        => (int)$row['slot_duration'],
            'rating'               => $row['avg_rating'] !== null ? (float)$row['avg_rating'] : null,
            'review_count'         => (int)$row['review_count'],
            // services array reserved for later
            'services'             => []
        ];
    }

    echo json_encode($results);

} catch (Exception $e) {
    echo json_encode([
        'error' => 'Search failed. Please try again.'
        // 'debug' => $e->getMessage() // enable only for debugging
    ]);
}
