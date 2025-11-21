<?php
/**
 * Get All Salons API
 * Returns all active salons for map display
 */

session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Cache-Control: public, max-age=300'); // Cache for 5 minutes

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sendError($message, $statusCode = 400) {
    sendResponse(['error' => $message], $statusCode);
}

try {
    // Get all active salons with essential information
    // Adjusted to match your database structure
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.name,
            s.address,
            s.city,
            s.lat,
            s.lng,
            s.rating,
            s.phone,
            s.email,
            s.website,
            s.image,
            s.opening_hours,
            s.created_at,
            COUNT(DISTINCT r.id) as review_count,
            GROUP_CONCAT(DISTINCT srv.name ORDER BY srv.name SEPARATOR ', ') as services
        FROM salons s
        LEFT JOIN reviews r ON r.salon_id = s.id
        LEFT JOIN services srv ON srv.salon_id = s.id
        GROUP BY s.id
        ORDER BY 
            s.rating DESC,
            s.name ASC
        LIMIT 500
    ");
    
    $stmt->execute();
    $salons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format response
    $formattedSalons = array_map(function($salon) {
        return [
            'id' => (int) $salon['id'],
            'name' => htmlspecialchars($salon['name'], ENT_QUOTES, 'UTF-8'),
            'address' => htmlspecialchars($salon['address'], ENT_QUOTES, 'UTF-8'),
            'city' => htmlspecialchars($salon['city'] ?? '', ENT_QUOTES, 'UTF-8'),
            'lat' => (float) $salon['lat'],
            'lng' => (float) $salon['lng'],
            'rating' => $salon['rating'] ? round((float) $salon['rating'], 1) : null,
            'phone' => $salon['phone'] ? htmlspecialchars($salon['phone'], ENT_QUOTES, 'UTF-8') : null,
            'email' => $salon['email'] ? htmlspecialchars($salon['email'], ENT_QUOTES, 'UTF-8') : null,
            'website' => $salon['website'],
            'image_url' => $salon['image_url'] ?: 'assets/images/default-salon.jpg',
            'opening_hours' => $salon['opening_hours'],
            'is_featured' => (bool) $salon['is_featured'],
            'review_count' => (int) $salon['review_count'],
            'services' => $salon['services']
        ];
    }, $salons);
    
    sendResponse($formattedSalons);
    
} catch (PDOException $e) {
    error_log("Get All Salons Error: " . $e->getMessage());
    sendError('Failed to load salons', 500);
    
} catch (Exception $e) {
    error_log("Get All Salons Error: " . $e->getMessage());
    sendError('An unexpected error occurred', 500);
}