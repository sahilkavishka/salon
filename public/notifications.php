<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';
checkAuth();

$user_id = $_SESSION['id'];

// Fetch notifications (unread first)
$stmt = $pdo->prepare("
    SELECT * 
    FROM notifications 
    WHERE user_id = :uid 
    ORDER BY is_read ASC, created_at DESC
");
$stmt->execute([':uid' => $user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark all as read AFTER fetching
$update = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0");
$update->execute([':uid' => $user_id]);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Notifications - Salonora</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: Arial, sans-serif; padding: 40px; }
        .notif {
            background: #fff;
            border-left: 6px solid #007bff;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .unread { background: #eaf7ff; border-color: #28a745; }
        .time { color: #6c757d; font-size: 0.9em; margin-top: 5px; }
        .container { max-width: 600px; }
    </style>
</head>
<body>
<div class="container">
    <h2 class="mb-4 text-center">🔔 Your Notifications</h2>

    <?php if (empty($notifications)): ?>
        <div class="alert alert-info text-center">No notifications yet.</div>
    <?php else: ?>
        <?php foreach ($notifications as $n): ?>
            <div class="notif <?php echo $n['is_read'] ? '' : 'unread'; ?>">
                <p class="mb-1"><?php echo htmlspecialchars($n['message']); ?></p>
                <div class="time"><?php echo htmlspecialchars($n['created_at']); ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="text-center mt-4">
        <a href="index.php" class="btn btn-primary">← Back to Home</a>
    </div>
</div>
</body>
</html>
