<?php
// public/post_review.php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $salon_id = intval($_POST['salon_id']);
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5) $rating = 5; // fallback
    $stmt = $pdo->prepare("INSERT INTO reviews (user_id, salon_id, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $salon_id, $rating, $comment]);
    header("Location: salon_view.php?id=$salon_id");
    exit;
}
