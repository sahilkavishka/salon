<?php
// public/notifications.php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';
checkAuth();

$user_id = $_SESSION['id'];

// Mark notification as read if requested
if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    $notif_id = intval($_GET['id']);
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $user_id]);
    header('Location: notifications.php');
    exit;
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header('Location: notifications.php');
    exit;
}

// Delete notification
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $notif_id = intval($_GET['id']);
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $user_id]);
    header('Location: notifications.php');
    exit;
}

// Fetch notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate read and unread
$unread = array_filter($notifications, fn($n) => $n['is_read'] == 0);
$read = array_filter($notifications, fn($n) => $n['is_read'] == 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notifications - Salonora</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/notifications.css">
  

  
  
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg">
    <div class="container">
      <a class="navbar-brand" href="index.php">
        <i class="fas fa-spa"></i> Salonora
      </a>
      <div class="ms-auto">
        <a href="index.php" class="btn btn-gradient btn-sm">
          <i class="fas fa-arrow-left me-1"></i> Back
        </a>
      </div>
    </div>
  </nav>

  <!-- Page Header -->
  <div class="page-header">
    <div class="container">
      <div class="page-header-content">
        <h1 class="page-title">Notifications</h1>
        <p class="page-subtitle">Stay updated with your latest activities</p>
      </div>
    </div>
  </div>

  <div class="container pb-5">
    <!-- Notifications Header -->
    <div class="notifications-header">
      <div class="notification-stats">
        <div class="stat-item">
          <div class="stat-icon">
            <i class="fas fa-bell"></i>
          </div>
          <div class="stat-info">
            <h3><?= count($unread) ?></h3>
            <p>Unread</p>
          </div>
        </div>
        <div class="stat-item">
          <div class="stat-icon" style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);">
            <i class="fas fa-check"></i>
          </div>
          <div class="stat-info">
            <h3><?= count($read) ?></h3>
            <p>Read</p>
          </div>
        </div>
      </div>

      <div class="actions-group">
        <?php if (count($unread) > 0): ?>
          <a href="?mark_all_read=1" class="btn-action btn-mark-all">
            <i class="fas fa-check-double"></i> Mark All as Read
          </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if (empty($notifications)): ?>
      <!-- Empty State -->
      <div class="empty-state">
        <i class="far fa-bell-slash"></i>
        <h4>No Notifications Yet</h4>
        <p>You're all caught up! New notifications will appear here.</p>
        <a href="index.php" class="btn btn-gradient">
          <i class="fas fa-home me-2"></i> Go to Home
        </a>
      </div>
    <?php else: ?>
      <!-- Unread Notifications -->
      <?php if (count($unread) > 0): ?>
        <div class="section-divider">
          <h5><i class="fas fa-circle" style="color: var(--primary); font-size: 0.5rem; margin-right: 0.5rem;"></i>New Notifications</h5>
          <hr>
        </div>

        <?php foreach ($unread as $notif): ?>
          <div class="notification-card unread">
            <div style="display: flex; gap: 1rem;">
              <div class="notification-icon">
                <?php if (strpos($notif['message'], 'confirmed') !== false): ?>
                  <i class="fas fa-check-circle"></i>
                <?php elseif (strpos($notif['message'], 'rejected') !== false || strpos($notif['message'], 'cancelled') !== false): ?>
                  <i class="fas fa-times-circle"></i>
                <?php elseif (strpos($notif['message'], 'review') !== false): ?>
                  <i class="fas fa-star"></i>
                <?php else: ?>
                  <i class="fas fa-bell"></i>
                <?php endif; ?>
              </div>
              
              <div class="notification-content">
                <div class="notification-header">
                  <div style="flex: 1;">
                    <div class="notification-message">
                      <?= htmlspecialchars($notif['message']) ?>
                    </div>
                    <div class="notification-time">
                      <i class="far fa-clock"></i>
                      <?php
                        $time_diff = time() - strtotime($notif['created_at']);
                        if ($time_diff < 60) echo 'Just now';
                        elseif ($time_diff < 3600) echo floor($time_diff / 60) . ' minutes ago';
                        elseif ($time_diff < 86400) echo floor($time_diff / 3600) . ' hours ago';
                        else echo date('M d, Y h:i A', strtotime($notif['created_at']));
                      ?>
                    </div>
                  </div>
                  
                  <div class="notification-actions">
                    <a href="?mark_read=1&id=<?= $notif['id'] ?>" class="btn-icon read" title="Mark as read">
                      <i class="fas fa-check"></i>
                    </a>
                    <a href="?delete=1&id=<?= $notif['id'] ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Delete this notification?');">
                      <i class="fas fa-trash"></i>
                    </a>
                  </div>
                </div>
              </div>
            </div>
            <span class="unread-badge">NEW</span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <!-- Read Notifications -->
      <?php if (count($read) > 0): ?>
        <div class="section-divider">
          <h5>Earlier Notifications</h5>
          <hr>
        </div>

        <?php foreach ($read as $notif): ?>
          <div class="notification-card read">
            <div style="display: flex; gap: 1rem;">
              <div class="notification-icon">
                <?php if (strpos($notif['message'], 'confirmed') !== false): ?>
                  <i class="fas fa-check-circle"></i>
                <?php elseif (strpos($notif['message'], 'rejected') !== false || strpos($notif['message'], 'cancelled') !== false): ?>
                  <i class="fas fa-times-circle"></i>
                <?php elseif (strpos($notif['message'], 'review') !== false): ?>
                  <i class="fas fa-star"></i>
                <?php else: ?>
                  <i class="fas fa-bell"></i>
                <?php endif; ?>
              </div>
              
              <div class="notification-content">
                <div class="notification-header">
                  <div style="flex: 1;">
                    <div class="notification-message">
                      <?= htmlspecialchars($notif['message']) ?>
                    </div>
                    <div class="notification-time">
                      <i class="far fa-clock"></i>
                      <?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?>
                    </div>
                  </div>
                  
                  <div class="notification-actions">
                    <a href="?delete=1&id=<?= $notif['id'] ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Delete this notification?');">
                      <i class="fas fa-trash"></i>
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>