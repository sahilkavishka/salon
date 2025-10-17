<?php
// public/owner/appointments.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

// only allow owner role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header('Location: ../login.php');
    exit;
}
$owner_id = $_SESSION['id'];

// Fetch salons owned by this owner
$stmt = $pdo->prepare("SELECT id, name FROM salons WHERE owner_id = :owner_id");
$stmt->execute([':owner_id' => $owner_id]);
$salons = $stmt->fetchAll(PDO::FETCH_ASSOC);
$salonIds = array_column($salons, 'id');

if (empty($salonIds)) {
    echo "<p>You don't own any salons yet.</p>";
    exit;
}

// Fetch pending requests for these salons
$in  = str_repeat('?,', count($salonIds) - 1) . '?';
$stmt = $pdo->prepare("
    SELECT a.*, u.username AS user_name, s.name AS service_name, sal.name AS salon_name
    FROM appointments a
    JOIN users u ON u.id = a.user_id
    JOIN services s ON s.id = a.service_id
    JOIN salons sal ON sal.id = a.salon_id
    WHERE a.salon_id IN ($in) AND a.status = 'pending'
    ORDER BY a.created_at DESC
");
$stmt->execute($salonIds);
$pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch confirmed bookings
$stmt = $pdo->prepare("
    SELECT a.*, u.username AS user_name, s.name AS service_name, sal.name AS salon_name
    FROM appointments a
    JOIN users u ON u.id = a.user_id
    JOIN services s ON s.id = a.service_id
    JOIN salons sal ON sal.id = a.salon_id
    WHERE a.salon_id IN ($in) AND a.status = 'confirmed'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$stmt->execute($salonIds);
$confirmed = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html>
<head><title>Owner - Appointments</title></head>
<body>
<h2>Pending Appointment Requests</h2>
<?php if (count($pending) === 0): ?>
    <p>No pending requests.</p>
<?php else: ?>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr>
            <th>ID</th><th>Salon</th><th>Service</th><th>User</th><th>Date</th><th>Time</th><th>Requested At</th><th>Actions</th>
        </tr>
        <?php foreach ($pending as $p): ?>
            <tr>
                <td><?php echo (int)$p['id']; ?></td>
                <td><?php echo htmlspecialchars($p['salon_name']); ?></td>
                <td><?php echo htmlspecialchars($p['service_name']); ?></td>
                <td><?php echo htmlspecialchars($p['user_name']); ?></td>
                <td><?php echo htmlspecialchars($p['appointment_date']); ?></td>
                <td><?php echo htmlspecialchars(substr($p['appointment_time'],0,5)); ?></td>
                <td><?php echo htmlspecialchars($p['created_at']); ?></td>
                <td>
                    <!-- confirm and reject actions -->
                    <form style="display:inline" action="owner_confirm.php" method="post">
                        <input type="hidden" name="appointment_id" value="<?php echo (int)$p['id']; ?>">
                        <input type="hidden" name="action" value="confirm">
                        <button type="submit">Confirm</button>
                    </form>

                    <form style="display:inline" action="owner_confirm.php" method="post">
                        <input type="hidden" name="appointment_id" value="<?php echo (int)$p['id']; ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" onclick="return confirm('Reject this request?')">Reject</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<h2>Confirmed (Booked) Appointments</h2>
<?php if (count($confirmed) === 0): ?>
    <p>No confirmed appointments.</p>
<?php else: ?>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr><th>ID</th><th>Salon</th><th>Service</th><th>User</th><th>Date</th><th>Time</th><th>Confirmed At</th></tr>
        <?php foreach ($confirmed as $c): ?>
            <tr>
                <td><?php echo (int)$c['id']; ?></td>
                <td><?php echo htmlspecialchars($c['salon_name']); ?></td>
                <td><?php echo htmlspecialchars($c['service_name']); ?></td>
                <td><?php echo htmlspecialchars($c['user_name']); ?></td>
                <td><?php echo htmlspecialchars($c['appointment_date']); ?></td>
                <td><?php echo htmlspecialchars(substr($c['appointment_time'],0,5)); ?></td>
                <td><?php echo htmlspecialchars($c['updated_at'] ?? $c['created_at']); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

</body>
</html>
