<?php
// public/post_review.php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['id'];
    $salon_id = intval($_POST['salon_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($salon_id && $rating) {
        $stmt = $pdo->prepare("INSERT INTO reviews (user_id, salon_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $salon_id, $rating, $comment]);
    }

    header("Location: user/salon_view.php?id=$salon_id");
    exit;
}
?>
