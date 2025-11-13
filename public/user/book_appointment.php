<?php
session_start();
require_once __DIR__ . '/../../config.php';

// Redirect if not logged in
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['id'];
$salon_id = $_GET['id'] ?? '';

if (empty($salon_id)) {
    die("Invalid salon ID.");
}

// Fetch salon details
$stmt = $pdo->prepare("SELECT id, name, address, image FROM salons WHERE id = ?");
$stmt->execute([$salon_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$salon) {
    die("Salon not found.");
}

// Fetch services for this salon
$serviceQuery = $pdo->prepare("SELECT id, name, price FROM services WHERE salon_id = ?");
$serviceQuery->execute([$salon_id]);
$services = $serviceQuery->fetchAll(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = $_POST['service_id'] ?? '';
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';

    if ($service_id && $date && $time) {
        $stmt = $pdo->prepare("
            INSERT INTO appointments (user_id, salon_id, service_id, appointment_date, appointment_time, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ");

        if ($stmt->execute([$user_id, $salon_id, $service_id, $date, $time])) {
            $success = "🎉 Your appointment has been booked successfully!";
        } else {
            $error = "❌ Failed to book appointment. Please try again.";
        }
    } else {
        $error = "⚠️ Please fill in all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Book Appointment - Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

  <style>
    body {
      background-color: #f4f4f9;
      font-family: "Poppins", sans-serif;
    }
    .booking-card {
      max-width: 700px;
      margin: 60px auto;
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    .salon-header img {
      width: 100%;
      height: 250px;
      object-fit: cover;
    }
    .salon-header h4 {
      margin: 15px 0 0;
    }
    .form-control, .form-select {
      border-radius: 8px;
      padding: 10px;
    }
    .btn-book {
      background-color: #6c63ff;
      border: none;
      color: #fff;
      font-weight: 500;
      border-radius: 8px;
      padding: 12px;
      transition: 0.3s;
    }
    .btn-book:hover {
      background-color: #514ad8;
    }
  </style>
</head>
<body>

  <?php include '../header.php'; ?>

  <div class="booking-card">
    <div class="salon-header">
      <img src="../../uploads/salon/<?php echo htmlspecialchars($salon['image']); ?>" alt="Salon Image">
      <div class="p-4">
        <h4><?php echo htmlspecialchars($salon['name']); ?></h4>
        <p class="text-muted mb-3"><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($salon['address']); ?></p>
      </div>
    </div>

    <div class="p-4">
      <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
      <?php elseif ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Select Service <span class="text-danger">*</span></label>
          <select name="service_id" class="form-select" required>
            <option value="">-- Choose a Service --</option>
            <?php foreach ($services as $service): ?>
              <option value="<?php echo $service['id']; ?>">
                <?php echo htmlspecialchars($service['name']); ?> - LKR <?php echo number_format($service['price'], 2); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Select Date <span class="text-danger">*</span></label>
          <input type="date" name="date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Select Time <span class="text-danger">*</span></label>
          <input type="time" name="time" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-book w-100">
          <i class="fas fa-calendar-check me-2"></i>Book Appointment
        </button>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
