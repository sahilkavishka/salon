<?php
// public/user/my_appointments.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('user');

if (!isset($_SESSION['id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = (int)$_SESSION['id'];

// Set page title for header
$page_title = 'My Appointments | Salonora';

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Invalid security token. Please refresh and try again.";
        header('Location: my_appointments.php');
        exit;
    }

    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
    $cancel_reason = trim($_POST['cancellation_reason'] ?? 'Cancelled by customer');

    if ($appointment_id) {
        try {
            $pdo->beginTransaction();

            $checkStmt = $pdo->prepare("
                SELECT salon_id, status, appointment_date, appointment_time 
                FROM appointments 
                WHERE id = :id AND user_id = :uid
                FOR UPDATE
            ");
            $checkStmt->execute([':id' => $appointment_id, ':uid' => $user_id]);
            $appointment = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($appointment && in_array($appointment['status'], ['pending', 'confirmed'])) {
                $appointmentDateTime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
                $hoursUntil = ($appointmentDateTime - time()) / 3600;

                // Pending appointments can be cancelled anytime before appointment time; confirmed requires 24h
                $canCancel = false;
                if ($appointment['status'] === 'pending') {
                    $canCancel = ($appointmentDateTime > time());
                } elseif ($appointment['status'] === 'confirmed') {
                    $canCancel = ($hoursUntil >= 24);
                }

                if ($canCancel) {
                    $oldStatus = $appointment['status'];
                    $pdo->prepare("
                        UPDATE appointments 
                        SET status = 'cancelled', cancellation_reason = :reason, cancelled_at = NOW(), updated_at = NOW() 
                        WHERE id = :id AND user_id = :uid
                    ")->execute([
                        ':reason' => $cancel_reason ?: 'Cancelled by customer',
                        ':id' => $appointment_id, 
                        ':uid' => $user_id
                    ]);

                    // Add log to appointment_logs
                    $logStmt = $pdo->prepare("
                        INSERT INTO appointment_logs (appointment_id, user_id, changed_by, old_status, new_status, action, action_type, changed_at) 
                        VALUES (?, ?, ?, ?, 'cancelled', 'cancelled', 'cancel', NOW())
                    ");
                    $logStmt->execute([$appointment_id, $user_id, $user_id, $oldStatus]);

                    // Notify salon owner
                    $ownerStmt = $pdo->prepare("SELECT owner_id FROM salons WHERE id = ?");
                    $ownerStmt->execute([$appointment['salon_id']]);
                    $ownerId = $ownerStmt->fetchColumn();
                    if ($ownerId) {
                        notifyUser($pdo, (int)$ownerId, "An appointment for " . date('M d, Y', strtotime($appointment['appointment_date'])) . " was cancelled by the customer.");
                    }

                    $pdo->commit();
                    $_SESSION['success_message'] = "Appointment #{$appointment_id} has been cancelled successfully.";
                } else {
                    $pdo->rollBack();
                    if ($appointment['status'] === 'confirmed') {
                        $_SESSION['error_message'] = "Confirmed appointments cannot be cancelled less than 24 hours before the scheduled time. Please call the salon directly.";
                    } else {
                        $_SESSION['error_message'] = "Cannot cancel appointments after the scheduled time has passed.";
                    }
                }
            } else {
                $pdo->rollBack();
                $_SESSION['error_message'] = "Cannot cancel this appointment or it has already been processed.";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Appointment cancellation error: " . $e->getMessage());
            $_SESSION['error_message'] = "An error occurred while cancelling your appointment. Please try again.";
        }
    }

    header('Location: my_appointments.php');
    exit;
}

// Fetch all appointments with resilient LEFT JOINs so no booking is ever hidden
$stmt = $pdo->prepare("
    SELECT 
        a.*, 
        COALESCE(s.name, 'Salonora Partner Salon') AS salon_name,
        COALESCE(s.address, 'Salon Location') AS salon_address,
        COALESCE(s.phone, '') AS salon_phone,
        COALESCE(srv.name, 'Beauty Service') AS service_name,
        COALESCE(srv.price, 0) AS service_price,
        COALESCE(srv.duration, 30) AS service_duration,
        COALESCE(srv.category, 'General') AS service_category,
        st.name AS staff_name
    FROM appointments a
    LEFT JOIN salons s ON s.id = a.salon_id
    LEFT JOIN services srv ON srv.id = a.service_id
    LEFT JOIN salon_staff st ON a.staff_id = st.id
    WHERE a.user_id = :uid
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([':uid' => $user_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group appointments
$now = time();
$pending = array_filter($appointments, fn($x) => $x['status'] === 'pending');
$confirmed = array_filter($appointments, fn($x) => $x['status'] === 'confirmed');
$completed = array_filter($appointments, fn($x) => $x['status'] === 'completed');
$cancelled_or_rejected = array_filter($appointments, fn($x) => in_array($x['status'], ['cancelled', 'rejected']));

$upcoming = array_filter($appointments, function($a) use ($now) {
    $t = strtotime($a['appointment_date'] . ' ' . $a['appointment_time']);
    return $t > $now && in_array($a['status'], ['pending', 'confirmed']);
});

// Determine active default tab: if no upcoming, show 'all'
$defaultTab = (!empty($upcoming)) ? 'upcoming' : 'all';

include __DIR__ . '/../header.php';
?>

<style>
/* ========================================================
   SALONORA MY APPOINTMENTS - LUXURY DASHBOARD STYLES
   ======================================================== */

.appts-hero {
  background: linear-gradient(135deg, #180928 0%, #2e083a 45%, #660f58 85%, #831843 100%);
  color: #ffffff;
  border-radius: 24px;
  padding: 2.5rem 2rem;
  margin-bottom: 2rem;
  position: relative;
  overflow: hidden;
  box-shadow: 0 15px 35px -10px rgba(102, 15, 88, 0.4);
}

.appts-hero::before {
  content: '';
  position: absolute;
  top: -40px;
  right: -40px;
  width: 200px;
  height: 200px;
  background: radial-gradient(circle, rgba(236, 72, 153, 0.3) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
}

.appts-portal-badge {
  background: rgba(255, 255, 255, 0.16);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.28);
  color: #ffffff !important;
  font-weight: 700;
  font-size: 0.84rem;
  padding: 0.45rem 1.1rem;
  border-radius: 50px;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Quick Summary Stat Badges */
.summary-stat-chip {
  background: rgba(255, 255, 255, 0.12);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 16px;
  padding: 0.6rem 1.1rem;
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
  color: #ffffff;
  font-size: 0.9rem;
}

.summary-stat-chip .stat-number {
  font-size: 1.25rem;
  font-weight: 700;
  color: #fbcfe8;
}

/* Tab Navigation Pills */
.nav-pills-appts {
  display: flex;
  flex-wrap: nowrap;
  overflow-x: auto;
  gap: 0.5rem;
  padding-bottom: 0.5rem;
  scrollbar-width: thin;
  justify-content: flex-start;
}

.nav-pills-appts::-webkit-scrollbar {
  height: 4px;
}
.nav-pills-appts::-webkit-scrollbar-thumb {
  background: rgba(233, 30, 99, 0.3);
  border-radius: 4px;
}

.nav-pills-appts .nav-link {
  background: #ffffff !important;
  color: #1e293b !important;
  border: 1.5px solid #cbd5e1 !important;
  border-radius: 50px;
  padding: 0.55rem 1.25rem;
  font-weight: 600;
  font-size: 0.9rem;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  transition: all 0.25s ease;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}

.nav-pills-appts .nav-link:hover {
  background: #fdf2f8 !important;
  color: #db2777 !important;
  border-color: #fbcfe8 !important;
  transform: translateY(-1px);
}

.nav-pills-appts .nav-link.active {
  background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%) !important;
  color: #ffffff !important;
  border-color: transparent !important;
  box-shadow: 0 6px 18px rgba(233, 30, 99, 0.35);
}

.nav-pills-appts .nav-link .badge-count {
  background: #e2e8f0;
  color: #1e293b;
  font-size: 0.78rem;
  padding: 0.2rem 0.65rem;
  border-radius: 50px;
  font-weight: 700;
  border: 1px solid #cbd5e1;
}

.nav-pills-appts .nav-link.active .badge-count {
  background: rgba(255, 255, 255, 0.28);
  color: #ffffff;
  border-color: rgba(255, 255, 255, 0.35);
}

/* Appointment Cards */
.appt-card {
  background: #ffffff;
  border-radius: 20px;
  padding: 1.6rem;
  margin-bottom: 1.5rem;
  border: 1px solid #e2e8f0;
  border-left: 6px solid #cbd5e1;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
  transition: all 0.25s ease;
  position: relative;
}

.appt-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
}

/* Status Border Colors */
.appt-card.confirmed { border-left-color: #10b981; }
.appt-card.pending { border-left-color: #f59e0b; }
.appt-card.completed { border-left-color: #3b82f6; }
.appt-card.cancelled { border-left-color: #94a3b8; }
.appt-card.rejected { border-left-color: #ef4444; }

/* Status Badges */
.badge-status-pill {
  padding: 0.45rem 1rem;
  font-weight: 700;
  border-radius: 50px;
  font-size: 0.8rem;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  letter-spacing: 0.3px;
}

.badge-status-confirmed {
  background: rgba(16, 185, 129, 0.12);
  color: #059669;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.badge-status-pending {
  background: rgba(245, 158, 11, 0.12);
  color: #d97706;
  border: 1px solid rgba(245, 158, 11, 0.3);
}

.badge-status-completed {
  background: rgba(59, 130, 246, 0.12);
  color: #2563eb;
  border: 1px solid rgba(59, 130, 246, 0.3);
}

.badge-status-cancelled {
  background: rgba(100, 116, 139, 0.12);
  color: #475569;
  border: 1px solid rgba(100, 116, 139, 0.3);
}

.badge-status-rejected {
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

/* Detail Info Rows */
.appt-detail-row {
  display: flex;
  align-items: flex-start;
  margin-bottom: 0.65rem;
  font-size: 0.92rem;
  color: #334155;
}

.appt-detail-row i {
  width: 22px;
  margin-right: 0.75rem;
  margin-top: 3px;
  font-size: 1rem;
  flex-shrink: 0;
}

.appt-detail-row a {
  color: #e91e63;
  text-decoration: none;
  font-weight: 500;
}

.appt-detail-row a:hover {
  text-decoration: underline;
}

/* Notes callout inside card */
.appt-notes-box {
  background: #f8fafc;
  border-left: 3px solid #cbd5e1;
  padding: 0.65rem 1rem;
  border-radius: 0 10px 10px 0;
  font-size: 0.86rem;
  color: #475569;
  margin-top: 0.75rem;
  margin-bottom: 0.75rem;
}

.appt-reason-box {
  background: #fef2f2;
  border-left: 3px solid #ef4444;
  padding: 0.65rem 1rem;
  border-radius: 0 10px 10px 0;
  font-size: 0.86rem;
  color: #991b1b;
  margin-top: 0.75rem;
  margin-bottom: 0.75rem;
}

/* Tab panes */
.tab-pane-content {
  display: none;
}

.tab-pane-content.active {
  display: block;
  animation: fadeIn 0.25s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Empty State */
.empty-appts-box {
  background: #ffffff;
  border-radius: 24px;
  padding: 4rem 2rem;
  text-align: center;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}

.empty-appts-icon {
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

/* Live Search Filter Input */
.search-filter-box {
  position: relative;
  max-width: 320px;
}

.search-filter-box input {
  padding-left: 2.5rem;
  border-radius: 50px;
  border: 1.5px solid #e2e8f0;
  font-size: 0.88rem;
}

.search-filter-box i {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
}

/* ========================================================
   DARK MODE ADAPTATIONS
   ======================================================== */
body.dark-mode .appts-hero {
  background: linear-gradient(135deg, #11051c 0%, #1c0524 45%, #3d0534 85%, #4c051e 100%) !important;
  box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.6) !important;
}

body.dark-mode .nav-pills-appts .nav-link {
  background: #1e1e38 !important;
  color: #f8fafc !important;
  border-color: #3b3b5e !important;
}

body.dark-mode .nav-pills-appts .nav-link:hover {
  background: #2a2a4c !important;
  color: #f472b6 !important;
  border-color: #ec4899 !important;
}

body.dark-mode .nav-pills-appts .nav-link.active {
  background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%) !important;
  color: #ffffff !important;
  border-color: transparent !important;
}

body.dark-mode .nav-pills-appts .nav-link .badge-count {
  background: rgba(255, 255, 255, 0.12) !important;
  color: #f1f5f9 !important;
  border-color: rgba(255, 255, 255, 0.15) !important;
}

body.dark-mode .nav-pills-appts .nav-link.active .badge-count {
  background: rgba(255, 255, 255, 0.28) !important;
  color: #ffffff !important;
}

body.dark-mode .appt-card {
  background: #1a1a2e !important;
  border-color: #2d2d48 !important;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4) !important;
}

body.dark-mode .appt-card h5 {
  color: #f8fafc !important;
}

body.dark-mode .appt-detail-row {
  color: #cbd5e1 !important;
}

body.dark-mode .appt-detail-row strong {
  color: #f8fafc !important;
}

body.dark-mode .appt-notes-box {
  background: #151528 !important;
  border-left-color: #475569 !important;
  color: #94a3b8 !important;
}

body.dark-mode .appt-reason-box {
  background: rgba(239, 68, 68, 0.15) !important;
  border-left-color: #ef4444 !important;
  color: #fca5a5 !important;
}

body.dark-mode .empty-appts-box {
  background: #1a1a2e !important;
  border-color: #2d2d48 !important;
  color: #e2e8f0 !important;
}

body.dark-mode .empty-appts-icon {
  background: rgba(233, 30, 99, 0.15) !important;
  color: #f472b6 !important;
}

body.dark-mode .search-filter-box input {
  background: #1e1e38 !important;
  border-color: #2d2d48 !important;
  color: #f8fafc !important;
}

body.dark-mode .badge-status-confirmed {
  background: rgba(16, 185, 129, 0.2) !important;
  color: #34d399 !important;
  border-color: rgba(16, 185, 129, 0.4) !important;
}

body.dark-mode .badge-status-pending {
  background: rgba(245, 158, 11, 0.2) !important;
  color: #fbbf24 !important;
  border-color: rgba(245, 158, 11, 0.4) !important;
}

body.dark-mode .badge-status-completed {
  background: rgba(59, 130, 246, 0.2) !important;
  color: #60a5fa !important;
  border-color: rgba(59, 130, 246, 0.4) !important;
}

body.dark-mode .badge-status-cancelled {
  background: rgba(148, 163, 184, 0.2) !important;
  color: #94a3b8 !important;
  border-color: rgba(148, 163, 184, 0.4) !important;
}

body.dark-mode .badge-status-rejected {
  background: rgba(239, 68, 68, 0.2) !important;
  color: #f87171 !important;
  border-color: rgba(239, 68, 68, 0.4) !important;
}
</style>

<div class="container py-4 pb-5">

  <!-- ==========================================
       HERO BANNER & STATS BAR
       ========================================== -->
  <div class="appts-hero">
    <div class="row align-items-center">
      <div class="col-lg-7">
        <div class="mb-3">
          <span class="appts-portal-badge">
            <i class="fas fa-calendar-check me-1 text-warning"></i> Customer Booking Portal
          </span>
        </div>
        <h1 class="display-6 fw-bold mb-2">My Salon Appointments</h1>
        <p class="lead mb-3 text-white-50" style="font-size: 1.05rem;">
          Track your upcoming salon visits, access digital QR passes, review invoices, and manage your beauty schedule with ease.
        </p>

        <!-- Summary Stat Chips -->
        <div class="d-flex flex-wrap gap-2">
          <div class="summary-stat-chip">
            <i class="fas fa-calendar text-info"></i>
            <div>
              <span class="stat-number"><?= count($upcoming) ?></span>
              <span class="d-block small text-white-50">Upcoming</span>
            </div>
          </div>
          <div class="summary-stat-chip">
            <i class="fas fa-hourglass-half text-warning"></i>
            <div>
              <span class="stat-number"><?= count($pending) ?></span>
              <span class="d-block small text-white-50">Pending</span>
            </div>
          </div>
          <div class="summary-stat-chip">
            <i class="fas fa-check-circle text-success"></i>
            <div>
              <span class="stat-number"><?= count($completed) ?></span>
              <span class="d-block small text-white-50">Completed</span>
            </div>
          </div>
          <div class="summary-stat-chip">
            <i class="fas fa-history text-secondary"></i>
            <div>
              <span class="stat-number"><?= count($appointments) ?></span>
              <span class="d-block small text-white-50">Total Bookings</span>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-5 text-lg-end mt-4 mt-lg-0">
        <a href="<?= url('user/salon_view.php') ?>" class="btn btn-light btn-lg rounded-pill px-4 py-3 fw-bold text-dark shadow-lg">
          <i class="fas fa-plus-circle text-primary me-2"></i> Book New Service
        </a>
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
       TABS NAVIGATION & SEARCH FILTER BAR
       ========================================== -->
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    
    <!-- Tab Navigation Pills -->
    <div class="nav-pills-appts">
      <button type="button" data-tab="upcoming" class="nav-link <?= ($defaultTab === 'upcoming') ? 'active' : '' ?>">
        <i class="fas fa-clock"></i> Upcoming 
        <span class="badge-count"><?= count($upcoming) ?></span>
      </button>

      <button type="button" data-tab="all" class="nav-link <?= ($defaultTab === 'all') ? 'active' : '' ?>">
        <i class="fas fa-list-ul"></i> All 
        <span class="badge-count"><?= count($appointments) ?></span>
      </button>

      <button type="button" data-tab="pending" class="nav-link">
        <i class="fas fa-hourglass-half"></i> Pending 
        <span class="badge-count"><?= count($pending) ?></span>
      </button>

      <button type="button" data-tab="confirmed" class="nav-link">
        <i class="fas fa-check-circle"></i> Confirmed 
        <span class="badge-count"><?= count($confirmed) ?></span>
      </button>

      <button type="button" data-tab="completed" class="nav-link">
        <i class="fas fa-calendar-check"></i> Completed 
        <span class="badge-count"><?= count($completed) ?></span>
      </button>

      <button type="button" data-tab="cancelled" class="nav-link">
        <i class="fas fa-ban"></i> Cancelled / Declined 
        <span class="badge-count"><?= count($cancelled_or_rejected) ?></span>
      </button>
    </div>

    <!-- Live Search Filter -->
    <div class="search-filter-box">
      <i class="fas fa-search"></i>
      <input type="text" id="apptSearchInput" class="form-control" placeholder="Search salon or service...">
    </div>

  </div>

  <!-- ==========================================
       APPOINTMENT RENDER FUNCTION
       ========================================== -->
  <?php
  function renderApptCardHTML($a, $now, $csrf_token) {
      $apptDateTime = strtotime($a['appointment_date'] . ' ' . $a['appointment_time']);
      $hoursUntil = ($apptDateTime - $now) / 3600;
      $isPast = ($apptDateTime <= $now);

      // Cancellation eligibility rule
      $canCancel = false;
      if ($a['status'] === 'pending' && !$isPast) {
          $canCancel = true;
      } elseif ($a['status'] === 'confirmed' && $hoursUntil >= 24) {
          $canCancel = true;
      }

      // Format WhatsApp Phone
      $rawPhone = preg_replace('/[^0-9]/', '', $a['salon_phone'] ?? '');
      $waPhone = '';
      if (!empty($rawPhone)) {
          $waPhone = str_starts_with($rawPhone, '0') ? ('94' . substr($rawPhone, 1)) : $rawPhone;
      }
      $waText = urlencode("Hello {$a['salon_name']}, I have an appointment (#{$a['id']}) for {$a['service_name']} on " . date('M d, Y', strtotime($a['appointment_date'])) . " at " . date('h:i A', strtotime($a['appointment_time'])) . ".");

      // Status labels & badges
      $statusConfig = [
          'confirmed' => ['label' => 'Confirmed', 'icon' => 'fa-check-circle', 'class' => 'badge-status-confirmed'],
          'pending'   => ['label' => 'Pending Approval', 'icon' => 'fa-hourglass-half', 'class' => 'badge-status-pending'],
          'completed' => ['label' => 'Completed', 'icon' => 'fa-flag-checkered', 'class' => 'badge-status-completed'],
          'cancelled' => ['label' => 'Cancelled', 'icon' => 'fa-ban', 'class' => 'badge-status-cancelled'],
          'rejected'  => ['label' => 'Declined by Salon', 'icon' => 'fa-times-circle', 'class' => 'badge-status-rejected']
      ];
      $st = $statusConfig[$a['status']] ?? ['label' => ucfirst($a['status']), 'icon' => 'fa-circle', 'class' => 'badge-status-cancelled'];

      ob_start();
      ?>
      <div class="appt-card <?= htmlspecialchars($a['status']) ?>" data-search="<?= strtolower(htmlspecialchars($a['salon_name'] . ' ' . $a['service_name'] . ' ' . $a['salon_address'])) ?>">
        
        <!-- Card Top Bar: Salon & Status -->
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
          <div>
            <div class="d-flex align-items-center gap-2 mb-1">
              <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-1 small fw-bold">
                #APT-<?= str_pad($a['id'], 5, '0', STR_PAD_LEFT) ?>
              </span>
              <?php if (!empty($a['service_category'])): ?>
                <span class="badge bg-light text-muted border rounded-pill px-2 py-1 small">
                  <?= htmlspecialchars($a['service_category']) ?>
                </span>
              <?php endif; ?>
            </div>
            <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($a['salon_name']) ?></h5>
          </div>

          <span class="badge-status-pill <?= $st['class'] ?>">
            <i class="fas <?= $st['icon'] ?>"></i>
            <?= $st['label'] ?>
          </span>
        </div>

        <!-- Appointment Details Grid -->
        <div class="row g-2 mb-2">
          
          <div class="col-md-6">
            <div class="appt-detail-row">
              <i class="fas fa-cut text-primary"></i>
              <div>
                <span class="text-muted small d-block">Service</span>
                <strong><?= htmlspecialchars($a['service_name']) ?></strong>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="appt-detail-row">
              <i class="fas fa-tag text-success"></i>
              <div>
                <span class="text-muted small d-block">Total Price</span>
                <strong class="text-success">Rs. <?= number_format($a['service_price'], 2) ?></strong>
                <?php if (!empty($a['discount_amount']) && $a['discount_amount'] > 0): ?>
                  <span class="badge bg-danger-subtle text-danger rounded-pill ms-1 small">
                    -Rs. <?= number_format($a['discount_amount'], 2) ?> Promo
                  </span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="appt-detail-row">
              <i class="fas fa-calendar-alt text-danger"></i>
              <div>
                <span class="text-muted small d-block">Appointment Date</span>
                <strong><?= date('l, M d, Y', strtotime($a['appointment_date'])) ?></strong>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="appt-detail-row">
              <i class="fas fa-clock text-warning"></i>
              <div>
                <span class="text-muted small d-block">Scheduled Time</span>
                <strong><?= date('h:i A', strtotime($a['appointment_time'])) ?></strong>
                <span class="text-muted small">(~<?= htmlspecialchars($a['service_duration']) ?> mins)</span>
              </div>
            </div>
          </div>

          <?php if (!empty($a['staff_name'])): ?>
          <div class="col-md-6">
            <div class="appt-detail-row">
              <i class="fas fa-user-friends text-info"></i>
              <div>
                <span class="text-muted small d-block">Assigned Stylist</span>
                <strong><?= htmlspecialchars($a['staff_name']) ?></strong>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <div class="col-md-6">
            <div class="appt-detail-row">
              <i class="fas fa-map-marker-alt text-secondary"></i>
              <div>
                <span class="text-muted small d-block">Salon Location</span>
                <span><?= htmlspecialchars($a['salon_address']) ?></span>
              </div>
            </div>
          </div>

        </div>

        <!-- Client Notes Callout (if any) -->
        <?php if (!empty($a['notes'])): ?>
          <div class="appt-notes-box">
            <i class="fas fa-sticky-note me-1 text-primary"></i>
            <strong>My Notes:</strong> <?= htmlspecialchars($a['notes']) ?>
          </div>
        <?php endif; ?>

        <!-- Cancellation/Decline Reason (if any) -->
        <?php if (!empty($a['cancellation_reason'])): ?>
          <div class="appt-reason-box">
            <i class="fas fa-info-circle me-1"></i>
            <strong>Reason:</strong> <?= htmlspecialchars($a['cancellation_reason']) ?>
          </div>
        <?php endif; ?>

        <!-- Policy Alerts & Context -->
        <?php if ($isPast && in_array($a['status'], ['pending', 'confirmed'])): ?>
          <div class="alert alert-secondary py-2 px-3 mb-3 small d-flex align-items-center rounded-3">
            <i class="fas fa-history me-2"></i>
            This appointment date and time has passed.
          </div>
        <?php elseif (!$canCancel && $a['status'] === 'confirmed'): ?>
          <div class="text-muted small mb-3">
            <i class="fas fa-info-circle me-1"></i> Confirmed bookings within 24 hours cannot be cancelled online. Please call the salon directly.
          </div>
        <?php endif; ?>

        <!-- Action Buttons Bar -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3 border-top mt-2">
          
          <!-- Left Actions -->
          <div class="d-flex flex-wrap gap-2 align-items-center">
            
            <!-- WhatsApp Button -->
            <?php if (!empty($waPhone)): ?>
              <a href="https://wa.me/<?= htmlspecialchars($waPhone) ?>?text=<?= $waText ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success rounded-pill px-3">
                <i class="fab fa-whatsapp me-1"></i> WhatsApp
              </a>
            <?php endif; ?>

            <!-- Phone Call Button -->
            <?php if (!empty($a['salon_phone'])): ?>
              <a href="tel:<?= htmlspecialchars($a['salon_phone']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="fas fa-phone-alt me-1"></i> Call Salon
              </a>
            <?php endif; ?>

            <!-- Receipt / Invoice Button -->
            <a href="invoice.php?id=<?= $a['id'] ?>" target="_blank" class="btn btn-sm btn-outline-info rounded-pill px-3">
              <i class="fas fa-file-invoice me-1"></i> Receipt
            </a>

            <!-- QR Pass Button -->
            <?php if (in_array($a['status'], ['pending', 'confirmed'])): ?>
              <button type="button" class="btn btn-sm btn-qr-pass rounded-pill px-3" 
                data-id="<?= $a['id'] ?>"
                data-salon="<?= htmlspecialchars($a['salon_name']) ?>"
                data-service="<?= htmlspecialchars($a['service_name']) ?>"
                data-datetime="<?= date('l, M d, Y - h:i A', strtotime($a['appointment_date'] . ' ' . $a['appointment_time'])) ?>"
                onclick="openQRModalFromBtn(this)">
                <i class="fas fa-qrcode me-1"></i> QR Pass
              </button>
            <?php endif; ?>

            <!-- Review Button (for completed appointments) -->
            <?php if ($a['status'] === 'completed'): ?>
              <a href="salon_details.php?id=<?= $a['salon_id'] ?>#reviews" class="btn btn-sm btn-rate-review rounded-pill px-3">
                <i class="fas fa-star me-1"></i> Rate & Review
              </a>
            <?php endif; ?>

            <!-- Re-book Button (for completed or cancelled) -->
            <?php if (in_array($a['status'], ['completed', 'cancelled', 'rejected'])): ?>
              <a href="book_appointment.php?salon_id=<?= $a['salon_id'] ?>&service_id=<?= $a['service_id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                <i class="fas fa-redo-alt me-1"></i> Book Again
              </a>
            <?php endif; ?>

          </div>

          <!-- Right Action: Cancel Button (if eligible) -->
          <?php if ($canCancel): ?>
            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" 
              data-id="<?= $a['id'] ?>"
              data-salon="<?= htmlspecialchars($a['salon_name']) ?>"
              data-service="<?= htmlspecialchars($a['service_name']) ?>"
              data-datetime="<?= date('M d, Y h:i A', strtotime($a['appointment_date'] . ' ' . $a['appointment_time'])) ?>"
              onclick="openCancelModalFromBtn(this)">
              <i class="fas fa-times-circle me-1"></i> Cancel Booking
            </button>
          <?php endif; ?>

        </div>

        <!-- Footer Meta -->
        <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center text-muted small">
          <span><i class="far fa-clock me-1"></i> Booked on: <?= date('M d, Y h:i A', strtotime($a['created_at'])) ?></span>
          <a href="salon_details.php?id=<?= $a['salon_id'] ?>" class="text-muted text-decoration-none hover-primary">
            View Salon Profile <i class="fas fa-external-link-alt ms-1 small"></i>
          </a>
        </div>

      </div>
      <?php
      return ob_get_clean();
  }
  ?>

  <!-- ==========================================
       TAB CONTENT PANES
       ========================================== -->

  <!-- TAB: UPCOMING -->
  <div class="tab-pane-content <?= ($defaultTab === 'upcoming') ? 'active' : '' ?>" id="tab-upcoming">
    <?php if (empty($upcoming)): ?>
      <div class="empty-appts-box">
        <div class="empty-appts-icon"><i class="far fa-calendar-check"></i></div>
        <h4 class="fw-bold mb-2">No Upcoming Appointments</h4>
        <p class="text-muted mx-auto mb-4" style="max-width: 440px;">
          You don't have any pending or confirmed bookings coming up. Browse our certified salons and schedule your next pampering session today!
        </p>
        <a href="<?= url('user/salon_view.php') ?>" class="btn btn-gradient rounded-pill px-4 py-2 fw-semibold">
          <i class="fas fa-search me-1"></i> Explore Salons
        </a>
      </div>
    <?php else: ?>
      <?php foreach ($upcoming as $a): ?>
        <?= renderApptCardHTML($a, $now, $_SESSION['csrf_token']) ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- TAB: ALL -->
  <div class="tab-pane-content <?= ($defaultTab === 'all') ? 'active' : '' ?>" id="tab-all">
    <?php if (empty($appointments)): ?>
      <div class="empty-appts-box">
        <div class="empty-appts-icon"><i class="far fa-calendar-alt"></i></div>
        <h4 class="fw-bold mb-2">No Appointments Found</h4>
        <p class="text-muted mx-auto mb-4" style="max-width: 440px;">
          You haven't made any salon appointments yet. Start your beauty journey now.
        </p>
        <a href="<?= url('user/salon_view.php') ?>" class="btn btn-gradient rounded-pill px-4 py-2 fw-semibold">
          <i class="fas fa-search me-1"></i> Explore Salons
        </a>
      </div>
    <?php else: ?>
      <?php foreach ($appointments as $a): ?>
        <?= renderApptCardHTML($a, $now, $_SESSION['csrf_token']) ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- TAB: PENDING -->
  <div class="tab-pane-content" id="tab-pending">
    <?php if (empty($pending)): ?>
      <div class="empty-appts-box">
        <div class="empty-appts-icon"><i class="far fa-hourglass"></i></div>
        <h4 class="fw-bold mb-2">No Pending Appointments</h4>
        <p class="text-muted mx-auto mb-3" style="max-width: 420px;">
          All your booking requests have been reviewed by salons.
        </p>
      </div>
    <?php else: ?>
      <?php foreach ($pending as $a): ?>
        <?= renderApptCardHTML($a, $now, $_SESSION['csrf_token']) ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- TAB: CONFIRMED -->
  <div class="tab-pane-content" id="tab-confirmed">
    <?php if (empty($confirmed)): ?>
      <div class="empty-appts-box">
        <div class="empty-appts-icon"><i class="far fa-check-circle"></i></div>
        <h4 class="fw-bold mb-2">No Confirmed Appointments</h4>
        <p class="text-muted mx-auto mb-3" style="max-width: 420px;">
          Your confirmed appointments will appear here as soon as the salon accepts your booking.
        </p>
      </div>
    <?php else: ?>
      <?php foreach ($confirmed as $a): ?>
        <?= renderApptCardHTML($a, $now, $_SESSION['csrf_token']) ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- TAB: COMPLETED -->
  <div class="tab-pane-content" id="tab-completed">
    <?php if (empty($completed)): ?>
      <div class="empty-appts-box">
        <div class="empty-appts-icon"><i class="fas fa-flag-checkered"></i></div>
        <h4 class="fw-bold mb-2">No Completed Appointments Yet</h4>
        <p class="text-muted mx-auto mb-3" style="max-width: 420px;">
          After visiting the salon and checking in, your past history and invoices will show up here.
        </p>
      </div>
    <?php else: ?>
      <?php foreach ($completed as $a): ?>
        <?= renderApptCardHTML($a, $now, $_SESSION['csrf_token']) ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- TAB: CANCELLED / DECLINED -->
  <div class="tab-pane-content" id="tab-cancelled">
    <?php if (empty($cancelled_or_rejected)): ?>
      <div class="empty-appts-box">
        <div class="empty-appts-icon"><i class="far fa-smile"></i></div>
        <h4 class="fw-bold mb-2">No Cancelled Bookings</h4>
        <p class="text-muted mx-auto mb-3" style="max-width: 420px;">
          You don't have any cancelled or declined appointments. Everything is on schedule!
        </p>
      </div>
    <?php else: ?>
      <?php foreach ($cancelled_or_rejected as $a): ?>
        <?= renderApptCardHTML($a, $now, $_SESSION['csrf_token']) ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<!-- ==========================================
     MODAL: QR CHECK-IN PASS
     ========================================== -->
<div class="modal fade" id="appointmentQrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4 rounded-4 shadow border-0">
      <div class="modal-header border-0 pb-0">
        <div>
          <h5 class="modal-title fw-bold text-dark mb-0" id="qrModalSalonName">Salon Name</h5>
          <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1 small fw-bold mt-1" id="qrModalAptId">Appointment #</span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body py-4">
        <p class="text-muted small mb-3">Present this QR pass to the salon receptionist upon arrival for instant contact-free check-in.</p>
        
        <div class="p-3 bg-white rounded-4 shadow-sm d-inline-block border">
          <img id="qrModalImage" src="" alt="Appointment QR Code" style="width:210px; height:210px; object-fit:contain;" class="rounded-3">
        </div>

        <div class="mt-3">
          <h6 class="fw-bold text-dark mb-1" id="qrModalService">Service Name</h6>
          <p class="text-muted small mb-0" id="qrModalDateTime">Date & Time</p>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0 justify-content-center">
        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ==========================================
     MODAL: CANCELLATION CONFIRMATION
     ========================================== -->
<div class="modal fade" id="cancelAppointmentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 rounded-4 shadow border-0">
      <form method="POST" action="my_appointments.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="appointment_id" id="cancelModalApptId" value="">
        <input type="hidden" name="cancel_appointment" value="1">

        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold text-danger">
            <i class="fas fa-exclamation-triangle me-2"></i> Cancel Appointment?
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body py-3">
          <p class="text-muted small mb-2">
            Are you sure you want to cancel your scheduled appointment at <strong id="cancelModalSalon"></strong>?
          </p>

          <div class="bg-light p-3 rounded-3 mb-3 small">
            <div><strong>Service:</strong> <span id="cancelModalService"></span></div>
            <div><strong>Date & Time:</strong> <span id="cancelModalDateTime"></span></div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Reason for Cancellation (Optional):</label>
            <select class="form-select rounded-3 small" name="cancellation_reason">
              <option value="Schedule Conflict / Plan Changed">Schedule Conflict / Plan Changed</option>
              <option value="Booked by Mistake">Booked by Mistake</option>
              <option value="Emergency or Illness">Emergency or Illness</option>
              <option value="Decided to change salon or service">Decided to change salon or service</option>
              <option value="Other">Other</option>
            </select>
          </div>

          <div class="alert alert-warning py-2 px-3 small mb-0 rounded-3">
            <i class="fas fa-info-circle me-1"></i> This cancellation cannot be undone. You will need to book a new appointment if you change your mind.
          </div>
        </div>

        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Keep Booking</button>
          <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">Confirm Cancellation</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// ==========================================
// TAB SWITCHING LOGIC
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
  const tabButtons = document.querySelectorAll('.nav-pills-appts .nav-link');
  const tabPanes = document.querySelectorAll('.tab-pane-content');

  tabButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      const target = this.getAttribute('data-tab');

      tabButtons.forEach(b => b.classList.remove('active'));
      tabPanes.forEach(p => p.classList.remove('active'));

      this.classList.add('active');
      const pane = document.getElementById('tab-' + target);
      if (pane) {
        pane.classList.add('active');
      }
    });
  });

  // Real-time search filter across cards in active pane
  const searchInput = document.getElementById('apptSearchInput');
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const q = this.value.trim().toLowerCase();
      document.querySelectorAll('.appt-card').forEach(card => {
        const text = card.getAttribute('data-search') || '';
        if (!q || text.includes(q)) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    });
  }
});

// ==========================================
// QR PASS MODAL
// ==========================================
function openQRModalFromBtn(btn) {
  const aptId = btn.getAttribute('data-id');
  const salonName = btn.getAttribute('data-salon');
  const serviceName = btn.getAttribute('data-service');
  const dateTime = btn.getAttribute('data-datetime');

  document.getElementById('qrModalSalonName').innerText = salonName;
  document.getElementById('qrModalService').innerText = serviceName;
  document.getElementById('qrModalDateTime').innerText = dateTime;
  document.getElementById('qrModalAptId').innerText = 'Appointment #APT-' + String(aptId).padStart(5, '0');

  const qrData = 'SALONORA_APT:' + aptId;
  document.getElementById('qrModalImage').src = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(qrData);

  const modal = new bootstrap.Modal(document.getElementById('appointmentQrModal'));
  modal.show();
}

// ==========================================
// CANCELLATION MODAL
// ==========================================
function openCancelModalFromBtn(btn) {
  const aptId = btn.getAttribute('data-id');
  const salonName = btn.getAttribute('data-salon');
  const serviceName = btn.getAttribute('data-service');
  const dateTime = btn.getAttribute('data-datetime');

  document.getElementById('cancelModalApptId').value = aptId;
  document.getElementById('cancelModalSalon').innerText = salonName;
  document.getElementById('cancelModalService').innerText = serviceName;
  document.getElementById('cancelModalDateTime').innerText = dateTime;

  const modal = new bootstrap.Modal(document.getElementById('cancelAppointmentModal'));
  modal.show();
}
</script>

<?php include __DIR__ . '/../footer.php'; ?>
