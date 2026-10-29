<?php

// Maps Configuration:
// The platform uses Leaflet.js with OpenStreetMap & Nominatim which is 100% FREE & Open-Source.
// NO Google Maps API key, credit card, or paid subscription is required.
define('MAP_PROVIDER', 'OpenStreetMap');

// Mail / SMTP Settings
define('SMTP_ENABLED', false); // Set true and configure credentials to send real SMTP emails
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_FROM', 'no-reply@salonora.com');
define('SMTP_FROM_NAME', 'Salonora Platform');

// Gemini AI Settings
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
}
define('GEMINI_MODEL', 'gemini-3.5-flash-lite');

// Database connection
$host = '127.0.0.1';
$dbname = 'salonora';
$user = 'root';
$pass = '';

// Try port 3307 first (XAMPP / MariaDB setup), fallback to standard 3306
$ports = [3307, 3306];
$pdo = null;
$lastError = null;

foreach ($ports as $port) {
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        break;
    } catch (PDOException $e) {
        $lastError = $e;
    }
}

if (!$pdo) {
    die("Database connection failed: " . ($lastError ? $lastError->getMessage() : "Could not connect to database."));
}

/**
 * Generate a dynamic URL relative to the application's public root.
 * Works seamlessly across XAMPP htdocs, virtual hosts, and PHP built-in server.
 */
if (!function_exists('url')) {
    function url(string $path = ''): string {
        static $base = null;
        if ($base === null) {
            $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
            $pos = strpos($script, '/public');
            if ($pos !== false) {
                $base = substr($script, 0, $pos + 7);
            } else {
                $base = '/public';
            }
            $base = rtrim($base, '/');
        }
        $path = ltrim($path, '/');
        return ($path === '') ? $base : $base . '/' . $path;
    }
}

/**
 * Send an in-app notification to a user.
 */
if (!function_exists('notifyUser')) {
    function notifyUser(PDO $pdo, int $userId, string $message): bool {
        try {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
            return $stmt->execute([$userId, $message]);
        } catch (Exception $e) {
            error_log("Failed to insert notification: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Send system email (using PHPMailer if SMTP is enabled, or system mail).
 */
if (!function_exists('sendSystemMail')) {
    function sendSystemMail(string $toEmail, string $toName, string $subject, string $htmlContent): array {
        if (!SMTP_ENABLED) {
            // In local/dev mode without SMTP, log the email content
            error_log("[Dev Mail Log] To: $toEmail | Subject: $subject");
            return ['success' => true, 'mode' => 'simulated', 'message' => 'Email simulated in development mode.'];
        }

        try {
            $autoload = __DIR__ . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USER;
                $mail->Password   = SMTP_PASS;
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = SMTP_PORT;

                $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
                $mail->addAddress($toEmail, $toName);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $htmlContent;

                $mail->send();
                return ['success' => true, 'mode' => 'sent', 'message' => 'Email sent successfully.'];
            }
        } catch (Exception $e) {
            error_log("Mail error: " . $e->getMessage());
            return ['success' => false, 'mode' => 'error', 'message' => $e->getMessage()];
        }

        return ['success' => false, 'mode' => 'error', 'message' => 'Mailer not configured'];
    }
}

/**
 * Call Google Gemini Generative AI API.
 */
if (!function_exists('callGeminiAI')) {
    function callGeminiAI(string $prompt, ?string $systemInstruction = null): ?string {
        if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY)) {
            error_log("Gemini API key is not configured.");
            return null;
        }

        $model = defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini-3.5-flash-lite';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . GEMINI_API_KEY;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1200
            ]
        ];

        if (!empty($systemInstruction)) {
            $payload['system_instruction'] = [
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            error_log("Gemini API Error (HTTP $httpCode): " . ($curlError ?: $response));
            return null;
        }

        $data = json_decode($response, true);
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }

        return null;
    }
}
?>
