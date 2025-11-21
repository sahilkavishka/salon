<?php
// public/user/my_appointments.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

if (!isset($_SESSION['id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['id'];

// Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);

    if ($appointment_id) {
        $checkStmt = $pdo->prepare("
            SELECT status, appointment_date, appointment_time 
            FROM appointments 
            WHERE id = :id AND user_id = :uid
        ");
        $checkStmt->execute([':id' => $appointment_id, ':uid' => $user_id]);
        $appointment = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($appointment && in_array($appointment['status'], ['pending', 'confirmed'])) {

            $appointmentDateTime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
            $hoursUntil = ($appointmentDateTime - time()) / 3600;

            if ($hoursUntil >= 24) {
                $pdo->prepare("
                    UPDATE appointments 
                    SET status = 'cancelled', updated_at = NOW() 
                    WHERE id = :id AND user_id = :uid
                ")->execute([':id' => $appointment_id, ':uid' => $user_id]);

                $_SESSION['success_message'] = "Appointment cancelled successfully.";
            } else {
                $_SESSION['error_message'] = "Cannot cancel appointment less than 24 hours before scheduled time.";
            }

        } else {
            $_SESSION['error_message'] = "Cannot cancel this appointment.";
        }
    }

    header('Location: my_appointments.php');
    exit;
}

// Fetch appointments
$stmt = $pdo->prepare("
    SELECT 
        a.*, 
        s.name AS salon_name,
        s.address AS salon_address,
        s.phone AS salon_phone,
        srv.name AS service_name,
        srv.price AS service_price,
        srv.duration AS service_duration
    FROM appointments a
    JOIN salons s ON s.id = a.salon_id
    JOIN services srv ON srv.id = a.service_id
    WHERE a.user_id = :uid
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([':uid' => $user_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group
$now = time();
$pending = array_filter($appointments, fn($x) => $x['status'] === 'pending');
$confirmed = array_filter($appointments, fn($x) => $x['status'] === 'confirmed');
$completed = array_filter($appointments, fn($x) => $x['status'] === 'completed');

$upcoming = array_filter($appointments, function($a) use ($now) {
    $t = strtotime($a['appointment_date'] . ' ' . $a['appointment_time']);
    return $t > $now && in_array($a['status'], ['pending','confirmed']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Appointments - Salonora</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
body{font-family:'Poppins',sans-serif;background:#faf5f8ff;}
.page-header{
    background:linear-gradient(135deg,#e91e63,#9c27b0);
    padding:4rem 0 3rem;
    color:white;text-align:center;
    margin-top: 50px;
}
.appointment-card{
    background:white;border-radius:18px;padding:1.6rem;margin-bottom:1.5rem;
    border-left:6px solid;box-shadow:0 3px 10px rgba(0,0,0,0.09);
    transition:0.25s ease;
}
.appointment-card:hover{transform:translateY(-3px);box-shadow:0 6px 18px rgba(0,0,0,0.12);}
.appointment-card .info-row{display:flex;align-items:center;margin-bottom:8px;}
.appointment-card .info-row i{width:22px;margin-right:10px;font-size:1rem;}
.status-badge{padding:0.45rem 1.05rem;font-weight:600;border-radius:50px;font-size:0.8rem;}
.pending{border-left-color:#f39c12;}
.pending .status-badge{color:#f39c12;background:rgba(243,156,18,0.12);}
.confirmed{border-left-color:#27ae60;}
.confirmed .status-badge{color:#27ae60;background:rgba(39,174,96,0.12);}
.cancelled{border-left-color:#95a5a6;}
.rejected{border-left-color:#e74c3c;}
.completed{border-left-color:#3498db;}
.btn-cancel{
    background:#e74c3c;color:white;border:none;padding:8px 15px;
    border-radius:50px;font-size:0.85rem;font-weight:600;
}
.btn-cancel:hover{background:#c0392b;color:white;}

.nav-pills .nav-link{
    background:linear-gradient(135deg,#e91e63,#9c27b0);
    color:white!important;
}
</style>
</head>

<body>

<?php include __DIR__.'/../header.php'; ?>

<div class="page-header">
  <h1>My Appointments</h1>
  <p>Track and manage your salon bookings</p>
</div>

<div class="container pb-5 mt-4">

<?php if(isset($_SESSION['success_message'])): ?>
<div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
<?php endif; ?>

<?php if(isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-pills justify-content-center mb-4">
  <li class="nav-item"><button data-tab="upcoming" class="nav-link active">Upcoming (<?= count($upcoming) ?>)</button></li>
  <li class="nav-item"><button data-tab="all" class="nav-link">All (<?= count($appointments) ?>)</button></li>
  <li class="nav-item"><button data-tab="pending" class="nav-link">Pending (<?= count($pending) ?>)</button></li>
  <li class="nav-item"><button data-tab="confirmed" class="nav-link">Confirmed (<?= count($confirmed) ?>)</button></li>
  <li class="nav-item"><button data-tab="completed" class="nav-link">Completed (<?= count($completed) ?>)</button></li>
</ul>

<!-- TAB: UPCOMING -->
<div class="tab-content active" id="upcoming">
<?php if(empty($upcoming)): ?>
  <div class="text-center bg-white p-5 shadow-sm rounded">
    <i class="far fa-calendar fa-4x text-muted mb-3"></i>
    <h4>No Upcoming Appointments</h4>
    <p>Book your next salon appointment now.</p>
    <a href="salon_view.php" class="btn btn-primary">Browse Salons</a>
  </div>
<?php else: ?>
<?php foreach($upcoming as $a):
    $t = strtotime($a['appointment_date'].' '.$a['appointment_time']);
    $hours = ($t - time())/3600;
    $canCancel = $hours >= 24 && in_array($a['status'],['pending','confirmed']);
?>
<div class="appointment-card <?= $a['status'] ?>">
  <div class="d-flex justify-content-between align-items-start">
    <h5 class="mb-0"><?= htmlspecialchars($a['salon_name']) ?></h5>
    <span class="status-badge <?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
  </div>

  <div class="mt-3">
    <div class="info-row"><i class="fas fa-cut text-primary"></i>
      <strong><?= htmlspecialchars($a['service_name']) ?></strong>
    </div>
    <div class="info-row"><i class="fas fa-rupee-sign text-success"></i>Rs <?= number_format($a['service_price'],2) ?></div>
    <div class="info-row"><i class="fas fa-calendar text-danger"></i><?= date('l, F j, Y', strtotime($a['appointment_date'])) ?></div>
    <div class="info-row"><i class="fas fa-clock text-warning"></i><?= date('h:i A', strtotime($a['appointment_time'])) ?> (<?= $a['service_duration'] ?> mins)</div>
    <div class="info-row"><i class="fas fa-map-marker-alt text-info"></i><?= htmlspecialchars($a['salon_address']) ?></div>
    <?php if(!empty($a['salon_phone'])): ?>
    <div class="info-row"><i class="fas fa-phone text-secondary"></i><?= htmlspecialchars($a['salon_phone']) ?></div>
    <?php endif; ?>
  </div>

  <?php if($canCancel): ?>
  <form method="POST" class="mt-3" onsubmit="return confirm('Cancel this appointment?');">
    <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
    <button class="btn-cancel" name="cancel_appointment"><i class="fas fa-times-circle me-1"></i>Cancel</button>
  </form>
  <?php endif; ?>

  <hr>
  <small class="text-muted"><i class="far fa-clock"></i> Booked: <?= date('M d, Y h:i A', strtotime($a['created_at'])) ?></small>
</div>

<?php endforeach; ?>
<?php endif; ?>

</div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>

<!-- Simple tabs logic -->
<script>
const tabs=document.querySelectorAll('[data-tab]');
const contents=document.querySelectorAll('.tab-content');
tabs.forEach(b=>b.onclick=()=>{
  tabs.forEach(x=>x.classList.remove('active'));
  contents.forEach(x=>x.classList.remove('active'));
  b.classList.add('active');
  document.getElementById(b.dataset.tab).classList.add('active');
});
</script>


</body>

</html>
