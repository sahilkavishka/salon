<?php
// public/owner/checkin.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle AJAX Verification / Completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
        exit;
    }

    $action = $_POST['action'];
    $appt_id = intval($_POST['appointment_id'] ?? 0);

    if ($appt_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Appointment ID.']);
        exit;
    }

    // Verify appointment belongs to this owner's salon
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            u.username AS customer_name,
            u.phone AS customer_phone,
            u.email AS customer_email,
            s.name AS salon_name,
            srv.name AS service_name,
            srv.price AS service_price
        FROM appointments a
        JOIN salons s ON s.id = a.salon_id
        JOIN users u ON u.id = a.user_id
        JOIN services srv ON srv.id = a.service_id
        WHERE a.id = ? AND s.owner_id = ?
    ");
    $stmt->execute([$appt_id, $owner_id]);
    $appt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appt) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found or does not belong to your salons.']);
        exit;
    }

    if ($action === 'verify') {
        echo json_encode([
            'success' => true,
            'appointment' => [
                'id' => $appt['id'],
                'customer_name' => $appt['customer_name'],
                'customer_phone' => $appt['customer_phone'] ?: 'N/A',
                'salon_name' => $appt['salon_name'],
                'service_name' => $appt['service_name'],
                'service_price' => number_format($appt['service_price'], 2),
                'date' => date('M d, Y', strtotime($appt['appointment_date'])),
                'time' => date('h:i A', strtotime($appt['appointment_time'])),
                'status' => $appt['status']
            ]
        ]);
        exit;
    }

    if ($action === 'complete_checkin') {
        try {
            $pdo->beginTransaction();
            $oldStatus = $appt['status'];
            
            $update = $pdo->prepare("UPDATE appointments SET status = 'completed', updated_at = NOW() WHERE id = ?");
            $update->execute([$appt_id]);

            // Log
            $log = $pdo->prepare("INSERT INTO appointment_logs (appointment_id, user_id, changed_by, old_status, new_status, action, action_type, changed_at) VALUES (?, ?, ?, ?, 'completed', 'checkin', 'complete', NOW())");
            $log->execute([$appt_id, $appt['user_id'], $owner_id, $oldStatus]);

            // Notify user
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())");
            $notif->execute([$appt['user_id'], "You have successfully checked in for your appointment at {$appt['salon_name']}. We hope you enjoyed your service!"]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Appointment #{$appt_id} successfully marked as Completed!"]);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to update appointment.']);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>QR Check-in & Scanner - Salonora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://unpkg.com/html5-qrcode"></script>
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: #f8fafc;
      color: #1e293b;
    }
    .checkin-container {
      max-width: 650px;
      margin: 40px auto;
    }
    .scanner-card {
      background: #ffffff;
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.06);
      border: 1px solid #e2e8f0;
    }
    #reader {
      width: 100%;
      border-radius: 12px;
      overflow: hidden;
      border: 2px dashed #e2e8f0;
    }

    /* Dark Mode Overrides */
    body.dark-mode {
      background: #111122 !important;
      color: #e2e8f0 !important;
    }
    body.dark-mode .scanner-card {
      background: #1a1a2e !important;
      border-color: #2d2d48 !important;
      box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important;
    }
    body.dark-mode .scanner-card h3 {
      color: #f8fafc !important;
    }
    body.dark-mode #resultCard {
      background: #252542 !important;
      border-color: #3b3b5e !important;
      color: #e2e8f0 !important;
    }
    body.dark-mode #resultCard table td {
      color: #cbd5e1 !important;
    }
    body.dark-mode #resultCard table .fw-bold {
      color: #f8fafc !important;
    }
    body.dark-mode .input-group-text {
      background: #1e1e38 !important;
      border-color: #3b3b5e !important;
      color: #cbd5e1 !important;
    }
    body.dark-mode .form-control {
      background: #1e1e38 !important;
      border-color: #3b3b5e !important;
      color: #ffffff !important;
    }
    body.dark-mode .badge.bg-light.text-muted {
      background: #252542 !important;
      color: #94a3b8 !important;
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../header.php'; ?>

<div class="container checkin-container mt-5 pt-4">
  <div class="scanner-card">
    <div class="text-center mb-4">
      <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width:70px;height:70px;background:linear-gradient(135deg,#e91e63,#9c27b0);color:white;font-size:1.8rem;">
        <i class="fas fa-qrcode"></i>
      </div>
      <h3 class="fw-bold">Client QR Check-in</h3>
      <p class="text-muted">Scan a customer's check-in QR code or enter Appointment ID to verify and complete.</p>
    </div>

    <!-- Alert placeholder -->
    <div id="alertBox"></div>

    <!-- Manual ID Input -->
    <div class="input-group mb-4">
      <span class="input-group-text bg-white"><i class="fas fa-hashtag text-primary"></i></span>
      <input type="number" id="manualApptId" class="form-control form-control-lg" placeholder="Enter Appointment ID (e.g. 12)">
      <button class="btn btn-primary px-4" id="searchBtn" style="background:linear-gradient(135deg,#e91e63,#9c27b0);border:none;">
        <i class="fas fa-search me-1"></i> Verify
      </button>
    </div>

    <div class="text-center my-3">
      <span class="badge bg-light text-muted px-3 py-2">OR USE CAMERA SCANNER</span>
    </div>

    <!-- Camera Scanner Viewport -->
    <div id="reader" class="mb-4"></div>
    <div class="d-flex justify-content-center gap-2 mb-4">
      <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" id="startCamBtn">
        <i class="fas fa-camera me-1"></i> Start Camera
      </button>
      <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="stopCamBtn" style="display:none;">
        <i class="fas fa-stop me-1"></i> Stop Camera
      </button>
    </div>

    <!-- Appointment Verification Result Modal / Card -->
    <div id="resultCard" style="display:none;" class="p-3 rounded-3 border bg-light mt-3">
      <h5 class="fw-bold text-success mb-3"><i class="fas fa-check-circle me-2"></i>Appointment Found</h5>
      <table class="table table-sm table-borderless mb-3">
        <tr>
          <td class="text-muted" style="width:130px;">Customer:</td>
          <td class="fw-bold" id="resCustName"></td>
        </tr>
        <tr>
          <td class="text-muted">Phone:</td>
          <td id="resCustPhone"></td>
        </tr>
        <tr>
          <td class="text-muted">Service:</td>
          <td class="fw-bold" id="resService"></td>
        </tr>
        <tr>
          <td class="text-muted">Price:</td>
          <td class="text-success fw-bold" id="resPrice"></td>
        </tr>
        <tr>
          <td class="text-muted">Date & Time:</td>
          <td id="resDateTime"></td>
        </tr>
        <tr>
          <td class="text-muted">Current Status:</td>
          <td><span class="badge bg-secondary" id="resStatus"></span></td>
        </tr>
      </table>
      <div class="d-grid gap-2">
        <button type="button" class="btn btn-success btn-lg rounded-pill" id="confirmCheckinBtn">
          <i class="fas fa-user-check me-2"></i> Confirm Check-in & Mark Completed
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
let currentApptId = null;
let html5QrCode = null;

function showAlert(msg, type = 'danger') {
  const alertBox = document.getElementById('alertBox');
  alertBox.innerHTML = `
    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
      ${msg}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  `;
}

async function verifyAppointment(id) {
  if (!id) return;
  currentApptId = id;
  const formData = new FormData();
  formData.append('action', 'verify');
  formData.append('appointment_id', id);
  formData.append('csrf_token', csrfToken);

  try {
    const res = await fetch('checkin.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
      const a = data.appointment;
      document.getElementById('resCustName').textContent = a.customer_name;
      document.getElementById('resCustPhone').textContent = a.customer_phone;
      document.getElementById('resService').textContent = a.service_name;
      document.getElementById('resPrice').textContent = 'Rs ' + a.service_price;
      document.getElementById('resDateTime').textContent = a.date + ' at ' + a.time;
      document.getElementById('resStatus').textContent = a.status.toUpperCase();
      document.getElementById('resultCard').style.display = 'block';
      document.getElementById('alertBox').innerHTML = '';
    } else {
      showAlert(data.message, 'danger');
      document.getElementById('resultCard').style.display = 'none';
    }
  } catch (err) {
    showAlert('Network error verifying appointment.', 'danger');
  }
}

document.getElementById('searchBtn').addEventListener('click', () => {
  const id = document.getElementById('manualApptId').value.trim();
  if (id) verifyAppointment(id);
});

document.getElementById('confirmCheckinBtn').addEventListener('click', async () => {
  if (!currentApptId) return;
  const formData = new FormData();
  formData.append('action', 'complete_checkin');
  formData.append('appointment_id', currentApptId);
  formData.append('csrf_token', csrfToken);

  try {
    const res = await fetch('checkin.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
      showAlert(data.message, 'success');
      document.getElementById('resultCard').style.display = 'none';
      document.getElementById('manualApptId').value = '';
    } else {
      showAlert(data.message, 'danger');
    }
  } catch (err) {
    showAlert('Error updating check-in.', 'danger');
  }
});

// Camera Scanner
document.getElementById('startCamBtn').addEventListener('click', () => {
  html5QrCode = new Html5Qrcode("reader");
  html5QrCode.start(
    { facingMode: "environment" },
    { fps: 10, qrbox: { width: 250, height: 250 } },
    (decodedText) => {
      try {
        const parsed = JSON.parse(decodedText);
        if (parsed.appt_id) {
          verifyAppointment(parsed.appt_id);
          stopCamera();
        }
      } catch(e) {
        if (!isNaN(decodedText)) {
          verifyAppointment(decodedText);
          stopCamera();
        }
      }
    },
    (error) => {}
  ).then(() => {
    document.getElementById('startCamBtn').style.display = 'none';
    document.getElementById('stopCamBtn').style.display = 'inline-block';
  }).catch(() => {
    showAlert('Camera permission denied or camera not found.', 'warning');
  });
});

function stopCamera() {
  if (html5QrCode) {
    html5QrCode.stop().then(() => {
      document.getElementById('startCamBtn').style.display = 'inline-block';
      document.getElementById('stopCamBtn').style.display = 'none';
    }).catch(() => {});
  }
}
document.getElementById('stopCamBtn').addEventListener('click', stopCamera);
</script>

<?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>
