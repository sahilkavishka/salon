<?php
// public/post_review.php
session_start();
require_once __DIR__ . '/../auth_check.php';
checkAuth('customer');
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_SESSION['id'];
    $salon_id = intval($_POST['salon_id']);
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5) $rating = 5; // fallback
    $stmt = $pdo->prepare("INSERT INTO reviews (id, salon_id, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->execute([$id, $salon_id, $rating, $comment]);
    header("Location: salon_view.php?id=$salon_id");
    exit;
}
