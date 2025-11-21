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
  
  
  <style>
    :root {
      --primary: #e91e63;
      --primary-dark: #c2185b;
      --secondary: #9c27b0;
      --accent: #ff6b9d;
      --dark: #1a1a2e;
      --light: #f5f7fa;
      --text-dark: #2d3436;
      --text-light: #636e72;
      --gradient-primary: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
      --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
      --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.12);
      --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.15);
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: var(--light);
      color: var(--text-dark);
    }

   
    /* Page Header */
    .page-header {
      background: var(--gradient-primary);
      padding: 4rem 0 3rem;
      margin-bottom: 3rem;
      position: relative;
      overflow: hidden;
    }

    .page-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.1)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,144C960,149,1056,139,1152,122.7C1248,107,1344,85,1392,74.7L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
      opacity: 0.5;
    }

    .page-header-content {
      position: relative;
      z-index: 2;
      text-align: center;
    }

    .page-title {
      font-size: 2.5rem;
      font-weight: 800;
      color: white;
      margin-bottom: 0.5rem;
    }

    .page-subtitle {
      font-size: 1.1rem;
      color: rgba(255, 255, 255, 0.9);
    }

    /* Stats & Actions */
    .notifications-header {
      background: white;
      border-radius: 20px;
      padding: 2rem;
      box-shadow: var(--shadow-sm);
      margin-bottom: 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1.5rem;
    }

    .notification-stats {
      display: flex;
      gap: 2rem;
      align-items: center;
    }

    .stat-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .stat-icon {
      width: 50px;
      height: 50px;
      background: var(--gradient-primary);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.25rem;
    }

    .stat-info h3 {
      font-size: 1.75rem;
      font-weight: 800;
      margin: 0;
      color: var(--text-dark);
    }

    .stat-info p {
      margin: 0;
      color: var(--text-light);
      font-size: 0.9rem;
    }

    .actions-group {
      display: flex;
      gap: 1rem;
    }

    .btn-action {
      padding: 0.75rem 1.5rem;
      border-radius: 50px;
      font-weight: 600;
      transition: var(--transition);
      display: flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
      border: 2px solid;
      white-space: nowrap;
    }

    .btn-mark-all {
      background: white;
      color: var(--primary);
      border-color: var(--primary);
    }

    .btn-mark-all:hover {
      background: var(--primary);
      color: white;
    }

    /* Notification Card */
    .notification-card {
      background: white;
      border-radius: 16px;
      padding: 1.5rem;
      margin-bottom: 1rem;
      box-shadow: var(--shadow-sm);
      border-left: 4px solid;
      transition: var(--transition);
      position: relative;
    }

    .notification-card.unread {
      border-left-color: var(--primary);
      background: linear-gradient(135deg, rgba(233, 30, 99, 0.02) 0%, rgba(156, 39, 176, 0.02) 100%);
    }

    .notification-card.read {
      border-left-color: #e9ecef;
      opacity: 0.7;
    }

    .notification-card:hover {
      box-shadow: var(--shadow-md);
      transform: translateX(5px);
    }

    .notification-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 0.75rem;
    }

    .notification-icon {
      width: 40px;
      height: 40px;
      background: linear-gradient(135deg, rgba(233, 30, 99, 0.1) 0%, rgba(156, 39, 176, 0.1) 100%);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary);
      font-size: 1.1rem;
      margin-right: 1rem;
      flex-shrink: 0;
    }

    .notification-content {
      flex: 1;
    }

    .notification-message {
      color: var(--text-dark);
      line-height: 1.6;
      margin-bottom: 0.5rem;
      font-size: 0.95rem;
    }

    .notification-time {
      color: var(--text-light);
      font-size: 0.85rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .notification-time i {
      font-size: 0.75rem;
    }

    .notification-actions {
      display: flex;
      gap: 0.5rem;
      flex-shrink: 0;
    }

    .btn-icon {
      width: 35px;
      height: 35px;
      border-radius: 8px;
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: var(--transition);
      background: transparent;
    }

    .btn-icon:hover {
      background: var(--light);
    }

    .btn-icon.read {
      color: var(--primary);
    }

    .btn-icon.delete {
      color: #e74c3c;
    }

    .unread-badge {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: var(--gradient-primary);
      color: white;
      padding: 0.25rem 0.75rem;
      border-radius: 50px;
      font-size: 0.75rem;
      font-weight: 700;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 4rem 2rem;
      background: white;
      border-radius: 20px;
      box-shadow: var(--shadow-sm);
    }

    .empty-state i {
      font-size: 5rem;
      color: #dfe6e9;
      margin-bottom: 1.5rem;
    }

    .empty-state h4 {
      font-size: 1.5rem;
      color: var(--text-dark);
      margin-bottom: 0.75rem;
    }

    .empty-state p {
      color: var(--text-light);
      margin-bottom: 2rem;
    }

    /* Section Divider */
    .section-divider {
      display: flex;
      align-items: center;
      margin: 2rem 0 1.5rem;
      gap: 1rem;
    }

    .section-divider h5 {
      font-weight: 700;
      color: var(--text-dark);
      margin: 0;
      white-space: nowrap;
    }

    .section-divider hr {
      flex: 1;
      border-color: #e9ecef;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .page-title {
        font-size: 2rem;
      }

      .notifications-header {
        flex-direction: column;
        align-items: flex-start;
      }

      .notification-stats {
        flex-direction: column;
        gap: 1rem;
        width: 100%;
      }

      .actions-group {
        width: 100%;
        flex-direction: column;
      }

      .btn-action {
        justify-content: center;
      }

      .notification-card {
        padding: 1rem;
      }
    }

    /* Animation */
    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .notification-card {
      animation: fadeIn 0.3s ease;
    }
  </style>

</head>

<body>
 
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