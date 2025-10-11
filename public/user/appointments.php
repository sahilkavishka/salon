<?php
// owner/appointments.php
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

require_once __DIR__ . '/../../config.php';

$salon_id = $_SESSION['user_id'];

// ✅ Handle appointment updates first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['appointment_id'])) {
    $aid = (int)$_POST['appointment_id'];
    $action = $_POST['action'];
    if (in_array($action, ['Confirmed', 'Cancelled', 'Completed'], true)) {
        $u = $pdo->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
        $u->execute([$action, $aid]);
        header('Location: appointments.php');
        exit;
    }
}

// ✅ List appointments for this owner’s salons
$stmt = $pdo->prepare("
  SELECT a.*, s.service_name, u.username AS customer_name, sal.name AS salon_name
  FROM appointments a
  JOIN services s ON a.service_id = s.service_id
  JOIN users u ON a.user_id = u.id
  JOIN salons sal ON a.salon_id = sal.salon_id
  WHERE sal.salon_id = ?
  ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([$salon_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Appointments | Salonora Owner</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <h2>📅 Manage Appointments</h2>
  <a href="dashboard.php" class="btn btn-secondary btn-sm mb-3">⬅ Back to Dashboard</a>

  <?php if (empty($appointments)): ?>
    <div class="alert alert-info">No appointments found.</div>
  <?php else: ?>
    <table class="table table-striped table-bordered">
      <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>Salon</th>
          <th>Service</th>
          <th>Customer</th>
          <th>Date</th>
          <th>Time</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($appointments as $a): ?>
        <tr>
          <td><?= $a['appointment_id'] ?></td>
          <td><?= htmlspecialchars($a['salon_name']) ?></td>
          <td><?= htmlspecialchars($a['service_name']) ?></td>
          <td><?= htmlspecialchars($a['customer_name']) ?></td>
          <td><?= htmlspecialchars($a['appointment_date']) ?></td>
          <td><?= htmlspecialchars($a['appointment_time']) ?></td>
          <td><?= htmlspecialchars($a['status']) ?></td>
          <td>
            <form method="post" class="d-inline">
              <input type="hidden" name="appointment_id" value="<?= $a['appointment_id'] ?>">
              <button name="action" value="Confirmed" class="btn btn-success btn-sm">Confirm</button>
              <button name="action" value="Cancelled" class="btn btn-danger btn-sm">Cancel</button>
              <button name="action" value="Completed" class="btn btn-primary btn-sm">Complete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
</body>
</html>
