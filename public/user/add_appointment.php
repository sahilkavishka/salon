<?php
// public/user/add_appointment.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

if (!isset($_GET['salon_id']) || !is_numeric($_GET['salon_id'])) {
    $_SESSION['error_message'] = 'Salon not specified.';
    header('Location: salon_view.php');
    exit;
}
$salon_id = intval($_GET['salon_id']);
$user_id = $_SESSION['id'];

// Fetch salon details
$stmt = $pdo->prepare("SELECT id, name FROM salons WHERE id=?");
$stmt->execute([$salon_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$salon) {
    $_SESSION['error_message'] = 'Salon not found.';
    header('Location: salon_view.php');
    exit;
}

// Fetch available services for this salon
$serviceStmt = $pdo->prepare("SELECT id, name, price, duration FROM services WHERE salon_id=? ORDER BY name ASC");
$serviceStmt->execute([$salon_id]);
$services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);

// Appointment submit logic
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = intval($_POST['service_id']);
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $appointment_time = trim($_POST['appointment_time'] ?? '');

    // Basic validation
    if ($service_id <= 0) $errors[] = 'Please select a service.';
    if ($appointment_date === '') $errors[] = 'Please select date.';
    if ($appointment_time === '') $errors[] = 'Please select time.';

    // Optional: No past dates
    if ($appointment_date < date('Y-m-d')) $errors[] = 'Cannot choose past date.';

    // Service actually exists in this salon
    $serviceExists = false;
    foreach ($services as $srv) if ($srv['id'] == $service_id) $serviceExists = true;
    if (!$serviceExists) $errors[] = 'Invalid service selection.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO appointments 
            (salon_id, user_id, service_id, appointment_date, appointment_time, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$salon_id, $user_id, $service_id, $appointment_date, $appointment_time]);
        $_SESSION['success_message'] = 'Appointment requested successfully!';
        header("Location: my_appointments.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Book Appointment - <?= htmlspecialchars($salon['name']) ?> | Salonora</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/css/appointment.css">
</head>
<body>
<nav class="navbar navbar-expand-lg bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="../../index.php">
            <i class="fas fa-spa"></i> Salonora
        </a>
    </div>
</nav>
<div class="container mt-5 pb-5">
    <div class="card shadow" style="max-width: 500px; margin: auto;">
        <div class="card-header bg-gradient text-white">
            <h3 class="mb-0">Book Appointment</h3>
            <p class="mb-0">Salon: <strong><?= htmlspecialchars($salon['name']) ?></strong></p>
        </div>
        <div class="card-body">
          <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?></div>
          <?php endif; ?>
          <form method="POST">
            <div class="mb-3">
              <label class="form-label">Service <span class="text-danger">*</span></label>
              <select name="service_id" class="form-select" required>
                <option value="">Select a Service</option>
                <?php foreach ($services as $service): ?>
                  <option value="<?= $service['id'] ?>"
                    <?= (isset($_POST['service_id']) && $_POST['service_id'] == $service['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($service['name']) ?> (Rs <?= number_format($service['price'],2) ?>, <?= $service['duration'] ?> mins)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Appointment Date <span class="text-danger">*</span></label>
              <input type="date" name="appointment_date" class="form-control"
                value="<?= htmlspecialchars($_POST['appointment_date'] ?? '') ?>"
                min="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Appointment Time <span class="text-danger">*</span></label>
              <input type="time" name="appointment_time" class="form-control"
                value="<?= htmlspecialchars($_POST['appointment_time'] ?? '') ?>" required>
            </div>
            <div class="mt-4 d-flex justify-content-between">
              <a href="salon_view.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancel
              </a>
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-calendar-plus"></i> Book Appointment
              </button>
            </div>
          </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
