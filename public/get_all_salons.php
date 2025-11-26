<?php
// public/get_all_salons.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php'; // adjust path if needed
// session_start(); // only if you need session here

try {
    /**
     * Assumptions:
     * - DB connection variable is $pdo (PDO)
     *   If you use mysqli ($conn), tell me and I’ll convert it.
     * - Table: salons
     *   Columns:
     *   id, owner_id, name, address, phone, email, description,
     *   website, facebook, instagram,
     *   parking_available, wheelchair_accessible, wifi_available, air_conditioned,
     *   image, lat, lng, opening_time, closing_time, slot_duration
     *
     * - Table: reviews
     *   id, user_id, salon_id, rating, comment, created_at
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
        ORDER BY s.name ASC
    ";

    $stmt = $pdo->query($sql);
    $salons = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Build image URL (adjust path if different)
        $imageUrl = null;
        if (!empty($row['image'])) {
            // if you store full URL in DB, just use $row['image']
            // if you store only filename, prepend your uploads path:
            $imageUrl = '/uploads/salons/' . $row['image'];
        }

        $salons[] = [
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
            'opening_time'         => $row['opening_time'],   // "HH:MM:SS"
            'closing_time'         => $row['closing_time'],   // "HH:MM:SS"
            'slot_duration'        => (int)$row['slot_duration'],
            'rating'               => $row['avg_rating'] !== null ? (float)$row['avg_rating'] : null,
            'review_count'         => (int)$row['review_count'],
            // services => [] for now (you can fill later if you add services table)
            'services'             => []
        ];
    }

    echo json_encode($salons);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'details' => $e->getMessage()
    ]);
}
