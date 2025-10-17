<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';
checkAuth();

$user_id = $_SESSION['id'];

// Fetch notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC");
$stmt->execute([':uid' => $user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark all as read
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid")->execute([':uid' => $user_id]);
?>
<!doctype html>
<html>
<head>
    <title>Notifications</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; }
        .notif { background: #f5f5f5; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
        .unread { background: #eaf7ff; }
        .time { color: gray; font-size: 0.9em; }
    </style>
</head>
<body>
    <h1>Notifications</h1>

    <?php if (empty($notifications)): ?>
        <p>No notifications yet.</p>
    <?php else: ?>
        <?php foreach ($notifications as $n): ?>
            <div class="notif <?php echo $n['is_read'] ? '' : 'unread'; ?>">
                <p><?php echo htmlspecialchars($n['message']); ?></p>
                <div class="time"><?php echo htmlspecialchars($n['created_at']); ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
