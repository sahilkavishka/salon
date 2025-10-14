<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('customer');

$user_id = $_SESSION['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $salon_id = intval($_POST['salon_id']);
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);

    if ($salon_id <= 0 || $rating < 1 || $rating > 5 || $comment === '') {
        die("Invalid review data.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO reviews (user_id, salon_id, rating, comment, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $salon_id, $rating, $comment]);

    header("Location: user/salon_details.php?id=$salon_id");
    exit;
}
?>
