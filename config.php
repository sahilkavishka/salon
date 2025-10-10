<?php


// Google Maps API Key
define('GOOGLE_MAPS_API_KEY', 'YOUR_API_KEY_HERE'); // Replace with your key

// Database connection
$host = 'localhost';
$dbname = 'salonora';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
