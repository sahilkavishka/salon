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

// Fetch appointments for this user
$stmt = $pdo->prepare("
    SELECT 
        a.*, 
        s.name AS salon_name,
        srv.name AS service_name
    FROM appointments a
    JOIN salons s ON s.id = a.salon_id
    JOIN services srv ON srv.id = a.service_id
    WHERE a.user_id = :uid
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([':uid' => $user_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html>
<head>
    <title>My Appointments</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; }
        h2 { margin-top: 40px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px; border: 1px solid #ccc; text-align: left; }
        .pending { color: orange; font-weight: bold; }
        .confirmed { color: green; font-weight: bold; }
        .rejected { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>My Appointments</h1>

    <?php if (empty($appointments)): ?>
        <p>You have no appointment requests yet.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Salon</th>
                <th>Service</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
                <th>Updated</th>
            </tr>
            <?php foreach ($appointments as $a): ?>
                <tr>
                    <td><?php echo htmlspecialchars($a['salon_name']); ?></td>
                    <td><?php echo htmlspecialchars($a['service_name']); ?></td>
                    <td><?php echo htmlspecialchars($a['appointment_date']); ?></td>
                    <td><?php echo htmlspecialchars(substr($a['appointment_time'], 0, 5)); ?></td>
                    <td class="<?php echo htmlspecialchars($a['status']); ?>">
                        <?php echo ucfirst($a['status']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($a['updated_at'] ?? $a['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</body>
</html>
