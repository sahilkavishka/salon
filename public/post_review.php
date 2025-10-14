<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';
checkAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['id'];
    $salon_id = intval($_POST['salon_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if (!$salon_id || !$rating || !$comment) {
        echo "All fields are required.";
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO reviews (user_id, salon_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $salon_id, $rating, $comment]);
        echo "Review submitted successfully!";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
