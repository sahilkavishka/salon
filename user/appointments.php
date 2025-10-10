<?php
// owner/appointments.php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'owner') {
    header('Location: ../public/login.php');
    exit;
}
$owner_id = $_SESSION['id'];
$salon_id = $_GET['salon_id'] ?? null;

// list appointments for salons owned by this owner
$stmt = $pdo->prepare("
  SELECT a.*, s.name as service_name, u.name as customer_name, sal.name as salon_name
  FROM appointments a
  JOIN services s ON a.service_id = s.service_id
  JOIN users u ON a.id = u.id
  JOIN salons sal ON a.salon_id = sal.salon_id
  WHERE sal.owner_id = ?
  ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([$owner_id]);
$appointments = $stmt->fetchAll();

// Accept / Cancel action handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $aid = intval($_POST['appointment_id']);
    $action = $_POST['action']; // Confirm / Cancel
    if (in_array($action, ['Confirmed','Cancelled','Completed'])) {
        $u = $pdo->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
        $u->execute([$action, $aid]);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Appointments</title></head><body>
<h1>Appointments</h1>
<table border="1">
<tr><th>ID</th><th>Salon</th><th>Service</th><th>Customer</th><th>Date</th><th>Time</th><th>Status</th><th>Action</th></tr>
<?php foreach ($appointments as $a): ?>
<tr>
  <td><?=$a['appointment_id']?></td>
  <td><?=htmlspecialchars($a['salon_name'])?></td>
  <td><?=htmlspecialchars($a['service_name'])?></td>
  <td><?=htmlspecialchars($a['customer_name'])?></td>
  <td><?=$a['appointment_date']?></td>
  <td><?=$a['appointment_time']?></td>
  <td><?=$a['status']?></td>
  <td>
    <form method="post" style="display:inline;">
      <input type="hidden" name="appointment_id" value="<?=$a['appointment_id']?>">
      <button name="action" value="Confirmed">Confirm</button>
      <button name="action" value="Cancelled">Cancel</button>
      <button name="action" value="Completed">Complete</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</table>
</body></html>
