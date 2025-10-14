<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

if (!isset($_SESSION['id'], $_POST['salon_id'], $_POST['rating'], $_POST['comment'])) {
    die("Invalid request.");
}

// Fetch salon owner
$salon_id = intval($_POST['salon_id']);
$stmt = $pdo->prepare("SELECT owner_id FROM salons WHERE id = ?");
$stmt->execute([$salon_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$salon) die("Salon not found.");

// Prevent owner from reviewing their own salon
if ($_SESSION['id'] == $salon['owner_id']) {
    die("You cannot review your own salon.");
}

// Insert review
$stmt = $pdo->prepare("
    INSERT INTO reviews (salon_id, user_id, rating, comment, created_at)
    VALUES (?, ?, ?, ?, NOW())
");
$stmt->execute([
    $salon_id,
    $_SESSION['id'],
    intval($_POST['rating']),
    trim($_POST['comment'])
]);

header("Location: salon_details.php?id=$salon_id&msg=review_posted");
exit;
