<?php
// public/notifications.php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';
checkAuth();

$user_id = $_SESSION['id'];
$user_role = strtolower($_SESSION['role'] ?? 'user');

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle actions with CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrf = $_POST['csrf_token'] ?? '';
    if (!$csrf || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $_SESSION['error_message'] = 'Invalid security token.';
        header('Location: notifications.php');
        exit;
    }

    // Mark notification as read
    if (isset($_POST['mark_read']) && isset($_POST['id'])) {
        $notif_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($notif_id) {
            try {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$notif_id, $user_id]);
                $_SESSION['success_message'] = 'Notification marked as read.';
            } catch (PDOException $e) {
                error_log("Mark read error: " . $e->getMessage());
                $_SESSION['error_message'] = 'Failed to update notification.';
            }
        }
        header('Location: notifications.php');
        exit;
    }

    // Mark all as read
    if (isset($_POST['mark_all_read'])) {
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            $_SESSION['success_message'] = 'All notifications marked as read.';
        } catch (PDOException $e) {
            error_log("Mark all read error: " . $e->getMessage());
            $_SESSION['error_message'] = 'Failed to update notifications.';
        }
        header('Location: notifications.php');
        exit;
    }

    // Delete notification
    if (isset($_POST['delete']) && isset($_POST['id'])) {
        $notif_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($notif_id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                $stmt->execute([$notif_id, $user_id]);
                $_SESSION['success_message'] = 'Notification deleted.';
            } catch (PDOException $e) {
                error_log("Delete notification error: " . $e->getMessage());
                $_SESSION['error_message'] = 'Failed to delete notification.';
            }
        }
        header('Location: notifications.php');
        exit;
    }

    // Delete all read notifications
    if (isset($_POST['delete_all_read'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1");
            $stmt->execute([$user_id]);
            $_SESSION['success_message'] = 'All read notifications have been cleared.';
        } catch (PDOException $e) {
            error_log("Delete all read error: " . $e->getMessage());
            $_SESSION['error_message'] = 'Failed to clear notifications.';
        }
        header('Location: notifications.php');
        exit;
    }
}

// Fetch notifications with error handling
try {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 100
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch notifications error: " . $e->getMessage());
    $notifications = [];
}

// Separate read and unread
$unread = array_filter($notifications, fn($n) => $n['is_read'] == 0);
$read = array_filter($notifications, fn($n) => $n['is_read'] == 1);

function getNotificationVisual($message) {
    $m = strtolower($message);
    if (strpos($m, 'confirmed') !== false) {
        return [
            'icon' => 'fa-check-circle',
            'bg' => 'rgba(16, 185, 129, 0.15)',
            'color' => '#10b981',
            'border' => '#10b981'
        ];
    } elseif (strpos($m, 'rejected') !== false || strpos($m, 'cancelled') !== false) {
        return [
            'icon' => 'fa-times-circle',
            'bg' => 'rgba(239, 68, 68, 0.15)',
            'color' => '#ef4444',
            'border' => '#ef4444'
        ];
    } elseif (strpos($m, 'completed') !== false) {
        return [
            'icon' => 'fa-calendar-check',
            'bg' => 'rgba(59, 130, 246, 0.15)',
            'color' => '#3b82f6',
            'border' => '#3b82f6'
        ];
    } elseif (strpos($m, 'review') !== false || strpos($m, 'rating') !== false) {
        return [
            'icon' => 'fa-star',
            'bg' => 'rgba(245, 158, 11, 0.15)',
            'color' => '#f59e0b',
            'border' => '#f59e0b'
        ];
    } else {
        return [
            'icon' => 'fa-bell',
            'bg' => 'rgba(233, 30, 99, 0.15)',
            'color' => '#e91e63',
            'border' => '#e91e63'
        ];
    }
}

function timeAgoFormatted($timestamp) {
    $diff = time() - strtotime($timestamp);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y - h:i A', strtotime($timestamp));
}

$page_title = 'Notification Center | Salonora';
include __DIR__ . '/header.php';
?>

<style>
/* ========================================================
   SALONORA NOTIFICATIONS - LUXURY INBOX STYLES
   ======================================================== */

.notifs-hero {
  background: linear-gradient(135deg, #180928 0%, #2f083b 45%, #620f54 85%, #881337 100%);
  color: #ffffff;
  border-radius: 28px;
  padding: 3rem 2.25rem;
  margin-bottom: 2rem;
  position: relative;
  overflow: hidden;
  box-shadow: 0 15px 35px -10px rgba(98, 15, 84, 0.45);
}

.notifs-hero::before {
  content: '';
  position: absolute;
  top: -50px;
  right: -50px;
  width: 240px;
  height: 240px;
  background: radial-gradient(circle, rgba(236, 72, 153, 0.35) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
}

/* Stat Chips */
.notif-stat-chip {
  background: rgba(255, 255, 255, 0.12);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 16px;
  padding: 0.6rem 1.15rem;
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
  color: #ffffff;
  font-size: 0.9rem;
}

.notif-stat-chip .stat-number {
  font-size: 1.25rem;
  font-weight: 800;
  color: #fbcfe8;
}

/* Filter Bar */
.notifs-filter-bar {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 2rem;
}

.notifs-pills {
  display: flex;
  gap: 0.5rem;
  overflow-x: auto;
}

.notifs-pill-btn {
  background: #ffffff !important;
  color: #1e293b !important;
  border: 1.5px solid #cbd5e1 !important;
  border-radius: 50px;
  padding: 0.5rem 1.25rem;
  font-weight: 600;
  font-size: 0.88rem;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.22s ease;
  cursor: pointer;
}

.notifs-pill-btn:hover {
  background: #fdf2f8 !important;
  color: #db2777 !important;
  border-color: #fbcfe8 !important;
}

.notifs-pill-btn.active {
  background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%) !important;
  color: #ffffff !important;
  border-color: transparent !important;
  box-shadow: 0 4px 12px rgba(233, 30, 99, 0.3);
}

.notifs-pill-btn .count-badge {
  background: #e2e8f0;
  color: #1e293b;
  font-size: 0.75rem;
  padding: 0.15rem 0.55rem;
  border-radius: 50px;
  font-weight: 700;
}

.notifs-pill-btn.active .count-badge {
  background: rgba(255, 255, 255, 0.28);
  color: #ffffff;
}

/* Notification Card */
.notif-item-card {
  background: #ffffff;
  border-radius: 20px;
  border: 1.5px solid #e2e8f0;
  padding: 1.25rem 1.5rem;
  margin-bottom: 1rem;
  transition: all 0.25s ease;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.25rem;
  position: relative;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
}

.notif-item-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.07);
  border-color: #fbcfe8;
}

.notif-item-card.unread {
  background: #fdf2f8;
  border-left: 5px solid #ec4899;
}

.notif-icon-box {
  width: 50px;
  height: 50px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  flex-shrink: 0;
}

.notif-msg-text {
  font-size: 0.95rem;
  font-weight: 500;
  color: #1e293b;
  margin-bottom: 0.3rem;
  line-height: 1.5;
}

.notif-time-text {
  font-size: 0.8rem;
  color: #64748b;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

/* Action button icons */
.btn-notif-action {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1.5px solid #e2e8f0;
  background: #ffffff;
  color: #64748b;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.88rem;
  transition: all 0.2s ease;
  cursor: pointer;
}

.btn-notif-action:hover {
  background: #f8fafc;
  color: #e91e63;
  border-color: #ec4899;
  transform: scale(1.08);
}

.btn-notif-delete:hover {
  background: #fef2f2 !important;
  color: #ef4444 !important;
  border-color: #ef4444 !important;
}

/* Empty state */
.notifs-empty-box {
  background: #ffffff;
  border-radius: 24px;
  border: 1px solid #e2e8f0;
  padding: 4rem 2rem;
  text-align: center;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.notifs-empty-icon {
  width: 90px;
  height: 90px;
  border-radius: 50%;
  background: #fdf2f8;
  color: #e91e63;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 2.5rem;
  margin-bottom: 1.5rem;
}

/* ========================================================
   DARK MODE
   ======================================================== */
body.dark-mode .notifs-hero {
  background: linear-gradient(135deg, #11051c 0%, #1c0524 45%, #3d0534 85%, #4c051e 100%) !important;
  box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.6) !important;
}

body.dark-mode .notifs-pill-btn {
  background: #1e1e38 !important;
  color: #f8fafc !important;
  border-color: #3b3b5e !important;
}

body.dark-mode .notifs-pill-btn:hover {
  background: #2a2a4c !important;
  color: #f472b6 !important;
}

body.dark-mode .notifs-pill-btn.active {
  background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%) !important;
  color: #ffffff !important;
  border-color: transparent !important;
}

body.dark-mode .notifs-pill-btn .count-badge {
  background: rgba(255, 255, 255, 0.12) !important;
  color: #e2e8f0 !important;
}

body.dark-mode .notif-item-card {
  background: #1a1a2e !important;
  border-color: #2d2d48 !important;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4) !important;
}

body.dark-mode .notif-item-card.unread {
  background: rgba(236, 72, 153, 0.08) !important;
  border-left-color: #ec4899 !important;
}

body.dark-mode .notif-msg-text {
  color: #f8fafc !important;
}

body.dark-mode .notif-time-text {
  color: #94a3b8 !important;
}

body.dark-mode .btn-notif-action {
  background: #1e1e38 !important;
  border-color: #3b3b5e !important;
  color: #cbd5e1 !important;
}

body.dark-mode .notifs-empty-box {
  background: #1a1a2e !important;
  border-color: #2d2d48 !important;
  color: #e2e8f0 !important;
}
</style>

<div class="container py-4 pb-5">

  <!-- ==========================================
       HERO BANNER
       ========================================== -->
  <div class="notifs-hero">
    <div class="row align-items-center">
      <div class="col-lg-8">
        <span class="badge bg-white bg-opacity-20 text-white px-3 py-2 rounded-pill fw-bold mb-3 shadow-sm">
          <i class="fas fa-bell me-1 text-warning"></i> Activity & Updates Hub
        </span>
        <h1 class="display-6 fw-bold mb-2">Notification Center</h1>
        <p class="lead mb-4 text-white-50" style="font-size: 1.05rem;">
          Stay informed with real-time updates regarding your salon bookings, appointment approvals, invoices, and special announcements.
        </p>

        <!-- Stats Chips -->
        <div class="d-flex flex-wrap gap-2">
          <div class="notif-stat-chip">
            <i class="fas fa-bell text-warning"></i>
            <div>
              <span class="stat-number"><?= count($unread) ?></span>
              <span class="d-block small text-white-50">Unread</span>
            </div>
          </div>

          <div class="notif-stat-chip">
            <i class="fas fa-check-double text-success"></i>
            <div>
              <span class="stat-number"><?= count($read) ?></span>
              <span class="d-block small text-white-50">Read</span>
            </div>
          </div>

          <div class="notif-stat-chip">
            <i class="fas fa-layer-group text-info"></i>
            <div>
              <span class="stat-number"><?= count($notifications) ?></span>
              <span class="d-block small text-white-50">Total Logged</span>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
        <?php if ($user_role === 'owner'): ?>
          <a href="<?= url('owner/appointments.php') ?>" class="btn btn-light btn-lg rounded-pill px-4 py-3 fw-bold text-dark shadow-lg">
            <i class="fas fa-calendar-alt text-primary me-2"></i> Owner Bookings
          </a>
        <?php else: ?>
          <a href="<?= url('user/my_appointments.php') ?>" class="btn btn-light btn-lg rounded-pill px-4 py-3 fw-bold text-dark shadow-lg">
            <i class="fas fa-calendar-check text-primary me-2"></i> My Appointments
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Global Action Messages -->
  <?php if (!empty($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-4 d-flex align-items-center mb-4" role="alert">
      <i class="fas fa-check-circle me-3 fs-5"></i>
      <div><?= htmlspecialchars($_SESSION['success_message']); ?></div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php unset($_SESSION['success_message']); endif; ?>

  <?php if (!empty($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 rounded-4 d-flex align-items-center mb-4" role="alert">
      <i class="fas fa-exclamation-triangle me-3 fs-5"></i>
      <div><?= htmlspecialchars($_SESSION['error_message']); ?></div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php unset($_SESSION['error_message']); endif; ?>

  <!-- ==========================================
       ACTION TOOLBAR & FILTER TABS
       ========================================== -->
  <div class="notifs-filter-bar">
    
    <!-- Filter Pills -->
    <div class="notifs-pills">
      <button type="button" class="notifs-pill-btn active" data-filter="all" onclick="filterNotifs('all', this)">
        <i class="fas fa-inbox"></i> All 
        <span class="count-badge"><?= count($notifications) ?></span>
      </button>

      <button type="button" class="notifs-pill-btn" data-filter="unread" onclick="filterNotifs('unread', this)">
        <i class="fas fa-envelope"></i> Unread 
        <span class="count-badge"><?= count($unread) ?></span>
      </button>

      <button type="button" class="notifs-pill-btn" data-filter="read" onclick="filterNotifs('read', this)">
        <i class="fas fa-envelope-open"></i> Read 
        <span class="count-badge"><?= count($read) ?></span>
      </button>
    </div>

    <!-- Bulk Actions -->
    <div class="d-flex align-items-center gap-2">
      <?php if (count($unread) > 0): ?>
        <form method="POST" class="d-inline">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
          <button type="submit" name="mark_all_read" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-2 fw-semibold">
            <i class="fas fa-check-double me-1"></i> Mark All as Read
          </button>
        </form>
      <?php endif; ?>

      <?php if (count($read) > 0): ?>
        <form method="POST" class="d-inline" onsubmit="return confirm('Clear all read notifications?');">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
          <button type="submit" name="delete_all_read" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2 fw-semibold">
            <i class="fas fa-trash-alt me-1"></i> Clear Read
          </button>
        </form>
      <?php endif; ?>
    </div>

  </div>

  <!-- ==========================================
       NOTIFICATIONS LIST
       ========================================== -->
  <?php if (empty($notifications)): ?>
    <div class="notifs-empty-box">
      <div class="notifs-empty-icon">
        <i class="far fa-bell-slash"></i>
      </div>
      <h4 class="fw-bold mb-2">You're All Caught Up!</h4>
      <p class="text-muted mx-auto mb-4" style="max-width: 440px;">
        There are no notifications right now. When salons update your appointments or confirm bookings, they will appear right here.
      </p>
      <a href="<?= url('index.php') ?>" class="btn btn-gradient rounded-pill px-4 py-2 fw-semibold">
        <i class="fas fa-home me-1"></i> Return Home
      </a>
    </div>
  <?php else: ?>
    <div id="notifsContainer">
      <?php foreach ($notifications as $n):
        $vis = getNotificationVisual($n['message']);
        $is_unread = ($n['is_read'] == 0);
        $filter_tag = $is_unread ? 'unread' : 'read';

        // Check if message is related to appointments
        $has_appt_keyword = (stripos($n['message'], 'appointment') !== false || stripos($n['message'], 'booking') !== false);
        $action_url = ($user_role === 'owner') ? url('owner/appointments.php') : url('user/my_appointments.php');
      ?>
        <div class="notif-item-card <?= $is_unread ? 'unread' : '' ?>" data-status="<?= $filter_tag ?>">
          
          <div class="d-flex align-items-center gap-3 flex-grow-1">
            <div class="notif-icon-box" style="background: <?= $vis['bg'] ?>; color: <?= $vis['color'] ?>;">
              <i class="fas <?= $vis['icon'] ?>"></i>
            </div>

            <div>
              <div class="notif-msg-text">
                <?= htmlspecialchars($n['message']) ?>
                <?php if ($is_unread): ?>
                  <span class="badge bg-danger-subtle text-danger rounded-pill ms-2 px-2 py-1 small fw-bold">NEW</span>
                <?php endif; ?>
              </div>

              <div class="notif-time-text">
                <i class="far fa-clock"></i> <?= timeAgoFormatted($n['created_at']) ?>
                <?php if ($has_appt_keyword): ?>
                  <span class="mx-1">•</span>
                  <a href="<?= $action_url ?>" class="text-primary text-decoration-none fw-semibold">
                    <i class="fas fa-external-link-alt me-1"></i> View Booking
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="d-flex align-items-center gap-2 flex-shrink-0">
            <?php if ($is_unread): ?>
              <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="id" value="<?= $n['id'] ?>">
                <button type="submit" name="mark_read" class="btn-notif-action" title="Mark as read">
                  <i class="fas fa-check text-success"></i>
                </button>
              </form>
            <?php endif; ?>

            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this notification?');">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="id" value="<?= $n['id'] ?>">
              <button type="submit" name="delete" class="btn-notif-action btn-notif-delete" title="Delete notification">
                <i class="fas fa-trash-alt text-danger"></i>
              </button>
            </form>
          </div>

        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<script>
function filterNotifs(type, btn) {
  document.querySelectorAll('.notifs-pill-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  document.querySelectorAll('.notif-item-card').forEach(card => {
    const status = card.getAttribute('data-status');
    if (type === 'all' || status === type) {
      card.style.display = 'flex';
    } else {
      card.style.display = 'none';
    }
  });
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
