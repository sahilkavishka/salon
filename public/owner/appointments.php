<?php
// public/owner/appointments.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

// Only allow owner role
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

// Prepare IN placeholders
$in  = str_repeat('?,', count($salonIds) - 1) . '?';

// Fetch pending requests
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
<head>
    <meta charset="utf-8">
    <title>Owner - Appointments</title>
    <style>
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f4f4f4; }
        form { display: inline; margin: 0; }
        button { padding: 4px 8px; cursor: pointer; }
    </style>
</head>
<body>
<h2>Pending Appointment Requests</h2>
<?php if (count($pending) === 0): ?>
    <p>No pending requests.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Salon</th>
                <th>Service</th>
                <th>User</th>
                <th>Date</th>
                <th>Time</th>
                <th>Requested At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pending as $p): ?>
            <tr>
                <td><?= (int)$p['id'] ?></td>
                <td><?= htmlspecialchars($p['salon_name']) ?></td>
                <td><?= htmlspecialchars($p['service_name']) ?></td>
                <td><?= htmlspecialchars($p['user_name']) ?></td>
                <td><?= htmlspecialchars($p['appointment_date']) ?></td>
                <td><?= date('H:i', strtotime($p['appointment_time'])) ?></td>
                <td><?= htmlspecialchars($p['created_at']) ?></td>
                <td>
                    <form action="owner_confirm.php" method="post">
                        <input type="hidden" name="appointment_id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="action" value="confirm">
                        <button type="submit">Confirm</button>
                    </form>
                    <form action="owner_confirm.php" method="post">
                        <input type="hidden" name="appointment_id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" onclick="return confirm('Reject this request?')">Reject</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2>Confirmed (Booked) Appointments</h2>
<?php if (count($confirmed) === 0): ?>
    <p>No confirmed appointments.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Salon</th>
                <th>Service</th>
                <th>User</th>
                <th>Date</th>
                <th>Time</th>
                <th>Confirmed At</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($confirmed as $c): ?>
            <tr>
                <td><?= (int)$c['id'] ?></td>
                <td><?= htmlspecialchars($c['salon_name']) ?></td>
                <td><?= htmlspecialchars($c['service_name']) ?></td>
                <td><?= htmlspecialchars($c['user_name']) ?></td>
                <td><?= htmlspecialchars($c['appointment_date']) ?></td>
                <td><?= date('H:i', strtotime($c['appointment_time'])) ?></td>
                <td><?= htmlspecialchars($c['updated_at'] ?? $c['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</body>
</html>
