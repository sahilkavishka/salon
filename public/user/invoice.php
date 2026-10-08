<?php
// public/user/invoice.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

$appt_id = intval($_GET['id'] ?? 0);
if ($appt_id <= 0) {
    die("Invalid appointment ID.");
}

$user_id = $_SESSION['id'];
$user_role = $_SESSION['role'] ?? 'user';

// Fetch appointment details ensuring authorized access (customer or salon owner)
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        u.username AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone,
        s.name AS salon_name,
        s.address AS salon_address,
        s.phone AS salon_phone,
        s.email AS salon_email,
        s.owner_id AS salon_owner_id,
        srv.name AS service_name,
        srv.price AS service_price,
        srv.duration AS service_duration,
        srv.category AS service_category
    FROM appointments a
    JOIN users u ON u.id = a.user_id
    JOIN salons s ON s.id = a.salon_id
    JOIN services srv ON srv.id = a.service_id
    WHERE a.id = ? AND (a.user_id = ? OR s.owner_id = ?)
");
$stmt->execute([$appt_id, $user_id, $user_id]);
$appt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appt) {
    die("Appointment not found or unauthorized access.");
}

// Generate check-in verification code
$checkin_payload = json_encode([
    'appt_id' => $appt['id'],
    'user_id' => $appt['user_id'],
    'salon_id' => $appt['salon_id'],
    'date' => $appt['appointment_date'],
    'time' => $appt['appointment_time']
]);
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" . urlencode($checkin_payload);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invoice #INV-<?= str_pad($appt['id'], 5, '0', STR_PAD_LEFT) ?> - Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: #f1f5f9;
      color: #1e293b;
      padding: 40px 0;
    }
    .invoice-card {
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
      padding: 40px;
      max-width: 800px;
      margin: 0 auto;
      border: 1px solid #e2e8f0;
    }
    .invoice-header {
      border-bottom: 2px solid #f1f5f9;
      padding-bottom: 24px;
      margin-bottom: 30px;
    }
    .brand-logo {
      font-size: 1.8rem;
      font-weight: 800;
      color: #e91e63;
      text-decoration: none;
    }
    .status-pill {
      display: inline-block;
      padding: 6px 16px;
      border-radius: 50px;
      font-weight: 700;
      font-size: 0.85rem;
      text-transform: uppercase;
    }
    .status-confirmed { background: #dcfce7; color: #15803d; }
    .status-pending { background: #fef9c3; color: #a16207; }
    .status-completed { background: #e0f2fe; color: #0369a1; }
    .status-cancelled, .status-rejected { background: #fee2e2; color: #b91c1c; }
    
    .invoice-table th {
      background: #f8fafc;
      color: #64748b;
      font-weight: 600;
      font-size: 0.85rem;
      border-top: none;
    }
    .qr-box {
      background: #f8fafc;
      border: 1px dashed #cbd5e1;
      border-radius: 12px;
      padding: 15px;
      text-align: center;
    }
    @media print {
      body { background: #ffffff; padding: 0; }
      .invoice-card { box-shadow: none; border: none; padding: 0; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>

<div class="container">
  <!-- Actions Bar -->
  <div class="d-flex justify-content-between align-items-center mb-4 no-print" style="max-width: 800px; margin: 0 auto;">
    <a href="javascript:history.back()" class="btn btn-outline-secondary rounded-pill px-3">
      <i class="fas fa-arrow-left me-1"></i> Back
    </a>
    <div>
      <button onclick="window.print()" class="btn btn-primary rounded-pill px-4" style="background: linear-gradient(135deg, #e91e63, #9c27b0); border:none;">
        <i class="fas fa-print me-1"></i> Print / Save as PDF
      </button>
    </div>
  </div>

  <!-- Invoice Container -->
  <div class="invoice-card">
    <div class="invoice-header d-flex justify-content-between align-items-start">
      <div>
        <div class="brand-logo">
          <i class="fas fa-spa me-1"></i> Salonora
        </div>
        <p class="text-muted mb-0 small">Official Booking Receipt</p>
      </div>
      <div class="text-end">
        <h4 class="mb-1 text-primary" style="color: #e91e63 !important;">#INV-<?= str_pad($appt['id'], 5, '0', STR_PAD_LEFT) ?></h4>
        <div class="status-pill status-<?= htmlspecialchars($appt['status']) ?>">
          <?= ucfirst(htmlspecialchars($appt['status'])) ?>
        </div>
        <div class="text-muted small mt-2">
          Issued: <?= date('M d, Y') ?>
        </div>
      </div>
    </div>

    <!-- Info Columns -->
    <div class="row mb-4">
      <div class="col-sm-6 mb-3">
        <h6 class="text-muted text-uppercase small fw-bold mb-2">Salon Details</h6>
        <h5 class="fw-bold mb-1"><?= htmlspecialchars($appt['salon_name']) ?></h5>
        <p class="text-muted mb-1 small"><i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($appt['salon_address']) ?></p>
        <?php if (!empty($appt['salon_phone'])): ?>
          <p class="text-muted mb-1 small"><i class="fas fa-phone me-1"></i> <?= htmlspecialchars($appt['salon_phone']) ?></p>
        <?php endif; ?>
        <?php if (!empty($appt['salon_email'])): ?>
          <p class="text-muted mb-0 small"><i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($appt['salon_email']) ?></p>
        <?php endif; ?>
      </div>

      <div class="col-sm-6 text-sm-end mb-3">
        <h6 class="text-muted text-uppercase small fw-bold mb-2">Billed To (Customer)</h6>
        <h5 class="fw-bold mb-1"><?= htmlspecialchars($appt['customer_name']) ?></h5>
        <p class="text-muted mb-1 small"><i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($appt['customer_email']) ?></p>
        <?php if (!empty($appt['customer_phone'])): ?>
          <p class="text-muted mb-1 small"><i class="fas fa-phone me-1"></i> <?= htmlspecialchars($appt['customer_phone']) ?></p>
        <?php endif; ?>
        <p class="text-muted mb-0 small">
          <i class="fas fa-calendar-check me-1"></i> Booked on: <?= date('M d, Y h:i A', strtotime($appt['created_at'])) ?>
        </p>
      </div>
    </div>

    <!-- Appointment Schedule Banner -->
    <div class="alert alert-info py-2 px-3 mb-4 rounded-3 d-flex justify-content-between align-items-center" style="background:#f0f9ff;border-color:#bae6fd;">
      <div>
        <i class="fas fa-calendar-alt text-primary me-2"></i>
        <strong>Scheduled Date:</strong> <?= date('l, F j, Y', strtotime($appt['appointment_date'])) ?>
      </div>
      <div>
        <i class="fas fa-clock text-primary me-2"></i>
        <strong>Time:</strong> <?= date('h:i A', strtotime($appt['appointment_time'])) ?> (<?= $appt['service_duration'] ?> mins)
      </div>
    </div>

    <!-- Service Table -->
    <div class="table-responsive mb-4">
      <table class="table invoice-table">
        <thead>
          <tr>
            <th>SERVICE</th>
            <th>CATEGORY</th>
            <th>DURATION</th>
            <th class="text-end">PRICE</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="fw-bold"><?= htmlspecialchars($appt['service_name']) ?></td>
            <td><span class="badge bg-light text-dark"><?= htmlspecialchars($appt['service_category'] ?? 'General') ?></span></td>
            <td><?= htmlspecialchars($appt['service_duration']) ?> mins</td>
            <td class="text-end fw-bold">Rs <?= number_format($appt['service_price'], 2) ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Total and QR Code Row -->
    <div class="row align-items-center pt-3 border-top">
      <div class="col-sm-5 mb-3 mb-sm-0">
        <div class="qr-box">
          <img src="<?= $qr_url ?>" alt="QR Check-in" style="width:110px;height:110px;" class="img-fluid rounded mb-2">
          <div class="fw-bold small text-muted">Scan to Verify & Check-in</div>
          <small class="text-muted" style="font-size: 0.75rem;">Appt ID: #<?= $appt['id'] ?></small>
        </div>
      </div>
      <div class="col-sm-7">
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Subtotal:</span>
          <span class="fw-bold">Rs <?= number_format($appt['service_price'], 2) ?></span>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Booking Fee / Tax:</span>
          <span class="fw-bold">Rs 0.00</span>
        </div>
        <hr>
        <div class="d-flex justify-content-between">
          <h5 class="fw-bold mb-0">Total Due / Paid:</h5>
          <h4 class="fw-bold mb-0" style="color: #e91e63;">Rs <?= number_format($appt['service_price'], 2) ?></h4>
        </div>
        <small class="text-muted d-block text-end mt-1">Payment Method: Pay at Salon / Cash</small>
      </div>
    </div>

    <!-- Footer Note -->
    <div class="text-center text-muted small mt-5 pt-3 border-top">
      <p class="mb-1">Thank you for booking with <strong>Salonora</strong>!</p>
      <p class="mb-0">Please present this receipt or your QR code when you arrive at <?= htmlspecialchars($appt['salon_name']) ?>.</p>
    </div>
  </div>
</div>

</body>
</html>
