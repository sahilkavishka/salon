<?php
// config.php
// EDIT: set these values to match your environment
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'salonora');
define('DB_USER', 'root');
define('DB_PASS', ''); // set your db password
define('BASE_URL', '/salonora/public/'); // path to public folder from web root
define('UPLOAD_DIR', __DIR__ . '/owner/uploads/'); // store salon images
define('GOOGLE_MAPS_API_KEY', 'YOUR_GOOGLE_MAPS_API_KEY'); // put your key here


// db.php
require_once __DIR__ . '/config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    // In production, log error and show friendly message
    die("Database connection failed: " . $e->getMessage());
}
?>
