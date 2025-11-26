<?php
// public/get_all_salons.php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Get all salons with rating + review count
    // If you DON'T have a `services` table, remove the second query below.
    $stmt = $pdo->query("
        SELECT 
            s.id,
            s.owner_id,
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
            ROUND(AVG(r.rating), 1) AS rating,
            COUNT(r.id) AS review_count
        FROM salons s
        LEFT JOIN reviews r ON r.salon_id = s.id
        GROUP BY 
            s.id,
            s.owner_id,
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
        ORDER BY s.id DESC
    ");

    $salons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build base URL for images
    // Adjust this if your image path is different
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $baseUrl .= "://".$_SERVER['HTTP_HOST'];
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

    // Optional: if you have a services table like:
    // services(id, salon_id, name, price, duration, ...)
    // we can fetch services per salon and send as array
    $servicesBySalon = [];

    try {
        $srvStmt = $pdo->query("
            SELECT salon_id, name
            FROM services
            ORDER BY salon_id, name
        ");

        while ($row = $srvStmt->fetch(PDO::FETCH_ASSOC)) {
            $sid = (int)$row['salon_id'];
            if (!isset($servicesBySalon[$sid])) {
                $servicesBySalon[$sid] = [];
            }
            $servicesBySalon[$sid][] = $row['name'];
        }
    } catch (Exception $e) {
        // If table doesn't exist or error, just skip services
        $servicesBySalon = [];
    }

    $output = [];

    foreach ($salons as $row) {
        $id = (int)$row['id'];

        // Build full image URL
        $imagePath = $row['image'] ?? '';
        if (!empty($imagePath)) {
            // If image already looks like URL, keep as is
            if (preg_match('#^https?://#', $imagePath)) {
                $imageUrl = $imagePath;
            } else {
                // adjust folder name if your salon images are in another directory
                $imageUrl = $baseUrl . $basePath . '/uploads/profile/' . ltrim($imagePath, '/');
            }
        } else {
            $imageUrl = null;
        }

        $output[] = [
            'id'                   => $id,
            'owner_id'             => (int)$row['owner_id'],
            'name'                 => $row['name'],
            'address'              => $row['address'],
            'phone'                => $row['phone'],
            'email'                => $row['email'],
            'description'          => $row['description'],
            'website'              => $row['website'],
            'facebook'             => $row['facebook'],
            'instagram'            => $row['instagram'],
            'parking_available'    => (int)$row['parking_available'] === 1,
            'wheelchair_available' => (int)$row['wheelchair_accessible'] === 1,
            'wifi_available'       => (int)$row['wifi_available'] === 1,
            'air_conditioned'      => (int)$row['air_conditioned'] === 1,
            'image_url'            => $imageUrl,
            'lat'                  => $row['lat'] !== null ? (float)$row['lat'] : null,
            'lng'                  => $row['lng'] !== null ? (float)$row['lng'] : null,
            'opening_time'         => $row['opening_time'],
            'closing_time'         => $row['closing_time'],
            'slot_duration'        => $row['slot_duration'] !== null ? (int)$row['slot_duration'] : null,
            'rating'               => $row['rating'] !== null ? (float)$row['rating'] : null,
            'review_count'         => (int)$row['review_count'],
            // if services table is available, attach simple names array
            'services'             => $servicesBySalon[$id] ?? [],
        ];
    }

    echo json_encode($output);

} catch (Exception $e) {
    // On error, send empty list with error message (only in dev)
    echo json_encode([
        'error' => true,
        'message' => 'Failed to load salons',
    ]);
}
