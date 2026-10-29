<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Only POST is allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$salon_id = intval($input['salon_id'] ?? 0);
$user_query = trim($input['query'] ?? '');
$gender = trim($input['gender'] ?? 'Not specified');
$face_shape = trim($input['face_shape'] ?? 'Not specified');
$hair_type = trim($input['hair_type'] ?? 'Not specified');
$style_vibe = trim($input['style_vibe'] ?? 'Not specified');
$language = trim($input['lang'] ?? 'en'); // 'en' or 'si'

if (empty($user_query) && $gender === 'Not specified' && $face_shape === 'Not specified') {
    echo json_encode(['success' => false, 'message' => 'Please provide a query or select your preferences.']);
    exit;
}

// Fetch available services from salon or general
$salon_name = "Salonora Partner Salon";
$services = [];

if ($salon_id > 0) {
    $salonStmt = $pdo->prepare("SELECT name FROM salons WHERE id = ?");
    $salonStmt->execute([$salon_id]);
    $salon = $salonStmt->fetch(PDO::FETCH_ASSOC);
    if ($salon) {
        $salon_name = $salon['name'];
    }

    $servStmt = $pdo->prepare("SELECT id, name, category, price, duration, description FROM services WHERE salon_id = ? AND is_active = 1");
    $servStmt->execute([$salon_id]);
    $services = $servStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $servStmt = $pdo->query("SELECT id, name, category, price, duration, description FROM services WHERE is_active = 1 LIMIT 15");
    $services = $servStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Build services context for Gemini
$servicesContext = "";
foreach ($services as $s) {
    $servicesContext .= "- [ID: {$s['id']}] {$s['name']} ({$s['category']}): Rs. " . number_format($s['price'], 2) . " (~{$s['duration']} mins). Description: {$s['description']}\n";
}

$systemInstruction = "You are 'Salonora AI', an elite celebrity hair stylist, aesthetician, and personal grooming advisor for the Salonora beauty platform.
Your goals:
1. Provide personalized, highly stylish, and practical hair/beauty recommendations based on the user's face shape, hair type, gender, lifestyle, and occasion.
2. Recommend 1 to 3 specific services from the salon's actual available services list.
3. Keep the tone warm, confident, elegant, and empowering.
4. Format your response cleanly using Markdown with clear headers, bullet points, and highlight matching salon services.
5. If the user language is Sinhala ('si'), provide the advice in fluent, natural, polite Sinhala with English terms for technical styles where helpful. Otherwise, provide it in English.";

$prompt = "Client Profile:\n";
$prompt .= "- Target Salon: {$salon_name}\n";
$prompt .= "- Gender: {$gender}\n";
$prompt .= "- Face Shape: {$face_shape}\n";
$prompt .= "- Hair Type / Texture: {$hair_type}\n";
$prompt .= "- Desired Vibe / Occasion: {$style_vibe}\n";
$prompt .= "- Client's Specific Question / Details: \"{$user_query}\"\n\n";

$prompt .= "Available Services at this Salon:\n";
$prompt .= (!empty($servicesContext)) ? $servicesContext : "General salon services available.\n";

$prompt .= "\nPlease provide:\n";
$prompt .= "1. **Personalized Style Assessment**: Why certain cuts/treatments flatter this face shape and hair texture.\n";
$prompt .= "2. **Top Recommended Styles/Treatments**: 2-3 specific options with styling advice.\n";
$prompt .= "3. **Matching Salonora Services**: Explicitly state which of the listed services above the client should book (mention Service Name and ID if applicable).\n";
$prompt .= "4. **At-Home Maintenance Tip**: 1 simple pro-tip for keeping the style fresh.\n";

if ($language === 'si') {
    $prompt .= "\nPlease reply in friendly, fluent Sinhala (සිංහල භාෂාවෙන් පිළිතුරු දෙන්න).";
}

$aiResponse = callGeminiAI($prompt, $systemInstruction);

if (!$aiResponse) {
    echo json_encode([
        'success' => false,
        'message' => 'AI Advisor is temporarily unavailable. Please try again in a moment.'
    ]);
    exit;
}

// Try to extract mentioned service IDs from response
$matchedServices = [];
foreach ($services as $s) {
    if (stripos($aiResponse, $s['name']) !== false || stripos($aiResponse, "ID: {$s['id']}") !== false) {
        $matchedServices[] = [
            'id' => $s['id'],
            'name' => $s['name'],
            'price' => $s['price'],
            'duration' => $s['duration']
        ];
    }
}

echo json_encode([
    'success' => true,
    'salon_id' => $salon_id,
    'salon_name' => $salon_name,
    'advice' => $aiResponse,
    'matched_services' => $matchedServices
]);
