<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

if (!isset($_SESSION['id']) || !in_array($_SESSION['role'], ['user', 'customer'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['id'];

// Fetch appointments
$stmt = $pdo->prepare("
    SELECT a.*, s.name AS salon_name, sr.name AS service_name, sr.price
    FROM appointments a
    JOIN salons s ON a.salon_id = s.id
    JOIN services sr ON a.service_id = sr.id
    WHERE a.user_id = ?
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$stmt->execute([$user_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Appointments - Salonora</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <h2>My Appointments</h2>
  <a href="../index.php" class="btn btn-secondary mb-3">&larr; Back to Home</a>

  <?php if (empty($appointments)): ?>
    <div class="alert alert-info">You have no appointments booked yet.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-striped table-bordered" id="appointmentsTable">
        <thead>
          <tr>
            <th>Salon</th>
            <th>Service</th>
            <th>Price</th>
            <th>Date</th>
            <th>Time</th>
            <th>Booked On</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($appointments as $a): ?>
            <tr id="appt-<?= $a['id'] ?>">
              <td><?= htmlspecialchars($a['salon_name']) ?></td>
              <td><?= htmlspecialchars($a['service_name']) ?></td>
              <td>Rs <?= htmlspecialchars($a['price']) ?></td>
              <td><?= htmlspecialchars($a['appointment_date']) ?></td>
              <td><?= htmlspecialchars($a['appointment_time']) ?></td>
              <td><?= htmlspecialchars($a['created_at']) ?></td>
              <td>
                <button class="btn btn-danger btn-sm cancel-btn" data-id="<?= $a['id'] ?>">Cancel</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// AJAX Cancel Appointment
document.querySelectorAll('.cancel-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        if (!confirm('Are you sure you want to cancel this appointment?')) return;
        const apptId = this.dataset.id;

        fetch('../cancel_appointment.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'id=' + encodeURIComponent(apptId)
        })
        .then(res => res.text())
        .then(msg => {
            alert(msg);
            // Remove the row if success
            if (msg.includes('cancelled')) {
                document.getElementById('appt-' + apptId).remove();
            }
        })
        .catch(err => alert('Error: ' + err));
    });
});
</script>
</body>
</html>
