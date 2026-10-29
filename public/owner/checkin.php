<?php
// public/owner/checkin.php
session_start();
date_default_timezone_set('Asia/Colombo');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle AJAX Verification / Completion
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
        exit;
    }

    $action = $_POST['action'];
    $appt_id = intval($_POST['appointment_id'] ?? 0);

    if ($appt_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Appointment ID provided.']);
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
            srv.price AS service_price,
            srv.duration AS service_duration
        FROM appointments a
        JOIN salons s ON s.id = a.salon_id
        JOIN users u ON u.id = a.user_id
        JOIN services srv ON srv.id = a.service_id
        WHERE a.id = ? AND s.owner_id = ?
    ");
    $stmt->execute([$appt_id, $owner_id]);
    $appt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appt) {
        echo json_encode(['success' => false, 'message' => 'Appointment #' . $appt_id . ' not found or does not belong to your salons.']);
        exit;
    }

    // Optional staff name
    $staff_name = 'Any Available Stylist';
    if (!empty($appt['staff_id'])) {
        $stf = $pdo->prepare("SELECT name, specialty FROM salon_staff WHERE id = ?");
        $stf->execute([$appt['staff_id']]);
        $stfData = $stf->fetch(PDO::FETCH_ASSOC);
        if ($stfData) {
            $staff_name = $stfData['name'] . (!empty($stfData['specialty']) ? ' (' . $stfData['specialty'] . ')' : '');
        }
    }

    if ($action === 'verify') {
        $discount = floatval($appt['discount_amount'] ?? 0);
        $final_price = max(0, floatval($appt['service_price']) - $discount);

        echo json_encode([
            'success' => true,
            'appointment' => [
                'id' => $appt['id'],
                'customer_name' => $appt['customer_name'],
                'customer_phone' => !empty($appt['customer_phone']) ? $appt['customer_phone'] : 'N/A',
                'customer_email' => !empty($appt['customer_email']) ? $appt['customer_email'] : 'N/A',
                'salon_name' => $appt['salon_name'],
                'service_name' => $appt['service_name'],
                'service_duration' => $appt['service_duration'] . ' mins',
                'service_price' => number_format($appt['service_price'], 2),
                'discount_amount' => $discount > 0 ? number_format($discount, 2) : null,
                'final_price' => number_format($final_price, 2),
                'promo_code' => !empty($appt['promo_code']) ? $appt['promo_code'] : null,
                'staff_name' => $staff_name,
                'date' => date('M d, Y', strtotime($appt['appointment_date'])),
                'time' => date('h:i A', strtotime($appt['appointment_time'])),
                'status' => strtolower($appt['status']),
                'notes' => !empty($appt['notes']) ? $appt['notes'] : null,
                'created_at' => date('M d, Y h:i A', strtotime($appt['created_at']))
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

            // Log activity
            $log = $pdo->prepare("INSERT INTO appointment_logs (appointment_id, user_id, changed_by, old_status, new_status, action, action_type, changed_at) VALUES (?, ?, ?, ?, 'completed', 'checkin', 'complete', NOW())");
            $log->execute([$appt_id, $appt['user_id'], $owner_id, $oldStatus]);

            // In-app notification for client
            notifyUser($pdo, (int)$appt['user_id'], "You have successfully checked in for Appointment #{$appt_id} ({$appt['service_name']}) at {$appt['salon_name']}. We hope you enjoy your service!");

            $pdo->commit();
            echo json_encode([
                'success' => true, 
                'message' => "Client successfully checked in! Appointment #{$appt_id} is marked as Completed."
            ]);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to complete check-in. Error: ' . $e->getMessage()]);
            exit;
        }
    }
}

// Fetch Today's Queue & Metrics for this owner
$today = date('Y-m-d');
$queueStmt = $pdo->prepare("
    SELECT 
        a.id, a.appointment_date, a.appointment_time, a.status, a.notes,
        u.username AS customer_name, u.phone AS customer_phone,
        s.name AS salon_name,
        srv.name AS service_name, srv.price AS service_price
    FROM appointments a
    JOIN salons s ON s.id = a.salon_id
    JOIN users u ON u.id = a.user_id
    JOIN services srv ON srv.id = a.service_id
    WHERE s.owner_id = ? AND a.appointment_date = ?
    ORDER BY a.appointment_time ASC
");
$queueStmt->execute([$owner_id, $today]);
$today_queue = $queueStmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics count
$total_today = count($today_queue);
$completed_today = 0;
$pending_today = 0;
foreach ($today_queue as $q) {
    if ($q['status'] === 'completed') $completed_today++;
    else $pending_today++;
}

// Fallback recent appointments if today's queue is empty
$recent_list = [];
if ($total_today === 0) {
    $recStmt = $pdo->prepare("
        SELECT 
            a.id, a.appointment_date, a.appointment_time, a.status,
            u.username AS customer_name, u.phone AS customer_phone,
            s.name AS salon_name,
            srv.name AS service_name, srv.price AS service_price
        FROM appointments a
        JOIN salons s ON s.id = a.salon_id
        JOIN users u ON u.id = a.user_id
        JOIN services srv ON srv.id = a.service_id
        WHERE s.owner_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 5
    ");
    $recStmt->execute([$owner_id]);
    $recent_list = $recStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client QR Check-in & Pass Verification - Salonora</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Fonts & Bootstrap -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- HTML5 QR Code Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode"></script>

    <script>
        if (localStorage.getItem('salonora_theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <style>
        :root {
            --primary: #e91e63;
            --primary-dark: #c2185b;
            --secondary: #9c27b0;
            --accent: #ff6b9d;
            --gradient-primary: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
            --gradient-secondary: linear-gradient(135deg, #ff6b9d 0%, #c471ed 100%);
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 14px 35px rgba(233, 30, 99, 0.15);
            --border-radius: 20px;
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
        }

        /* Top Header Nav */
        .partner-nav {
            background: #ffffff;
            box-shadow: var(--shadow-sm);
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .partner-logo {
            font-size: 1.4rem;
            font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        /* Hero Banner */
        .page-hero {
            background: linear-gradient(135deg, rgba(233, 30, 99, 0.08) 0%, rgba(156, 39, 176, 0.08) 100%);
            border-radius: var(--border-radius);
            padding: 2.2rem 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(233, 30, 99, 0.15);
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: white;
            color: var(--primary);
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            box-shadow: var(--shadow-sm);
            margin-bottom: 0.8rem;
        }

        /* Metrics Pills */
        .metric-pill {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 0.75rem 1.25rem;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            box-shadow: var(--shadow-sm);
        }

        .metric-pill i {
            font-size: 1.3rem;
        }

        .metric-pill strong {
            font-size: 1.15rem;
            display: block;
            line-height: 1.1;
        }

        .metric-pill small {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
        }

        /* Card Panels */
        .checkin-panel-card {
            background: #ffffff;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid #e2e8f0;
            height: 100%;
            transition: var(--transition);
        }

        .checkin-panel-card:hover {
            box-shadow: var(--shadow-md);
            border-color: #cbd5e1;
        }

        /* Nav Pills for Method Toggle */
        .method-tabs {
            background: #f1f5f9;
            padding: 0.35rem;
            border-radius: 50px;
            display: inline-flex;
            width: 100%;
            margin-bottom: 1.5rem;
        }

        .method-tab-btn {
            flex: 1;
            border: none;
            background: transparent;
            padding: 0.65rem 1rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.88rem;
            color: #64748b;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .method-tab-btn.active {
            background: var(--gradient-primary);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(233, 30, 99, 0.3);
        }

        /* QR Scanner Viewport */
        .scanner-frame-wrap {
            position: relative;
            border-radius: 18px;
            overflow: hidden;
            background: #0f172a;
            border: 2px dashed rgba(233, 30, 99, 0.4);
            min-height: 280px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #reader {
            width: 100%;
            border: none !important;
        }

        #reader video {
            border-radius: 16px;
            width: 100% !important;
            height: auto !important;
        }

        /* Boarding Pass / Ticket Result Card */
        .ticket-result-card {
            background: #ffffff;
            border-radius: 22px;
            border: 1px solid #e2e8f0;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            position: relative;
        }

        .ticket-header {
            background: var(--gradient-primary);
            color: #ffffff;
            padding: 1.5rem;
            position: relative;
        }

        .ticket-header::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            right: 0;
            height: 20px;
            background: radial-gradient(circle, transparent, transparent 50%, #ffffff 50%, #ffffff 100%);
            background-size: 20px 20px;
        }

        .ticket-body {
            padding: 1.8rem 1.5rem 1.5rem;
        }

        .ticket-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 0.6rem 0;
            border-bottom: 1px dashed #e2e8f0;
        }

        .ticket-row:last-child {
            border-bottom: none;
        }

        .ticket-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
        }

        .ticket-val {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
            text-align: right;
        }

        /* Customer Avatar */
        .user-avatar-circle {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #fdf2f8;
            color: var(--primary);
            font-weight: 800;
            font-size: 1.2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #fbcfe8;
        }

        /* Status Badges */
        .status-badge-pending {
            background: #fef3c7;
            color: #d97706;
            font-weight: 700;
            padding: 0.35rem 0.85rem;
            border-radius: 50px;
            font-size: 0.8rem;
        }

        .status-badge-confirmed {
            background: #e0e7ff;
            color: #4338ca;
            font-weight: 700;
            padding: 0.35rem 0.85rem;
            border-radius: 50px;
            font-size: 0.8rem;
        }

        .status-badge-completed {
            background: #dcfce7;
            color: #15803d;
            font-weight: 700;
            padding: 0.35rem 0.85rem;
            border-radius: 50px;
            font-size: 0.8rem;
        }

        /* Buttons */
        .btn-confirm-checkin {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white !important;
            font-weight: 700;
            font-size: 1.05rem;
            padding: 0.9rem;
            border-radius: 50px;
            border: none;
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
            transition: var(--transition);
            width: 100%;
        }

        .btn-confirm-checkin:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.5);
        }

        /* Queue Table Card */
        .queue-table-card {
            background: #ffffff;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-top: 2rem;
        }

        /* Empty State */
        .empty-scanner-state {
            text-align: center;
            padding: 3.5rem 1.5rem;
            color: #64748b;
        }

        .empty-scanner-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #fdf2f8;
            color: var(--primary);
            font-size: 2.2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.2rem;
            box-shadow: var(--shadow-sm);
            animation: pulse-ring 2s infinite ease-in-out;
        }

        @keyframes pulse-ring {
            0% { transform: scale(0.96); box-shadow: 0 0 0 0 rgba(233, 30, 99, 0.4); }
            70% { transform: scale(1); box-shadow: 0 0 0 15px rgba(233, 30, 99, 0); }
            100% { transform: scale(0.96); box-shadow: 0 0 0 0 rgba(233, 30, 99, 0); }
        }

        /* ========================================================
           DARK MODE SUPPORT
           ======================================================== */
        html.dark-mode, body.dark-mode {
            background-color: #0b0b14 !important;
            color: #f1f5f9 !important;
        }

        body.dark-mode .partner-nav {
            background: #141426 !important;
            border-color: #262640 !important;
        }

        body.dark-mode .page-hero {
            background: linear-gradient(135deg, rgba(233, 30, 99, 0.15) 0%, rgba(156, 39, 176, 0.15) 100%) !important;
            border-color: rgba(233, 30, 99, 0.25) !important;
        }

        body.dark-mode .hero-badge {
            background: #1e1e38 !important;
            color: #f472b6 !important;
        }

        body.dark-mode .metric-pill,
        body.dark-mode .checkin-panel-card,
        body.dark-mode .ticket-result-card,
        body.dark-mode .queue-table-card {
            background: #141426 !important;
            border-color: #262640 !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4) !important;
        }

        body.dark-mode .method-tabs {
            background: #1a1a32 !important;
        }

        body.dark-mode .method-tab-btn {
            color: #94a3b8 !important;
        }

        body.dark-mode .method-tab-btn.active {
            color: #ffffff !important;
        }

        body.dark-mode .form-control {
            background-color: #1a1a32 !important;
            color: #ffffff !important;
            border-color: #313154 !important;
        }

        body.dark-mode .input-group-text {
            background: #1a1a32 !important;
            border-color: #313154 !important;
            color: #cbd5e1 !important;
        }

        body.dark-mode .ticket-header::after {
            background: radial-gradient(circle, transparent, transparent 50%, #141426 50%, #141426 100%);
            background-size: 20px 20px;
        }

        body.dark-mode .ticket-row {
            border-color: #262640 !important;
        }

        body.dark-mode .ticket-label {
            color: #94a3b8 !important;
        }

        body.dark-mode .ticket-val {
            color: #f8fafc !important;
        }

        body.dark-mode .table td,
        body.dark-mode .table th {
            color: #cbd5e1 !important;
            border-color: #262640 !important;
            background: transparent !important;
        }

        body.dark-mode .empty-scanner-icon {
            background: #252547 !important;
            color: #f472b6 !important;
        }

        body.dark-mode .btn-outline-secondary {
            color: #cbd5e1 !important;
            border-color: #3b3b5e !important;
            background: #1a1a32 !important;
        }
    </style>
</head>
<body>

    <!-- Sticky Navigation Bar -->
    <header class="partner-nav">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="dashboard.php" class="partner-logo">
                <i class="fas fa-spa"></i> Salonora <span class="badge bg-primary fs-6 fw-normal px-2 py-1 ms-1 rounded-pill">Owner Hub</span>
            </a>
            
            <div class="d-flex align-items-center gap-2">
                <button type="button" id="themeToggleBtn" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm" title="Toggle Dark/Light Mode">
                    <i class="fas fa-moon me-1" id="themeToggleIcon"></i> Theme
                </button>
                <a href="appointments.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
                    <i class="fas fa-calendar-alt me-1"></i> Appointments
                </a>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
                    <i class="fas fa-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </header>

    <div class="container py-4">
        
        <!-- Hero Banner with Today's Live Metrics -->
        <div class="page-hero">
            <div class="row align-items-center g-3">
                <div class="col-lg-7">
                    <span class="hero-badge">
                        <i class="fas fa-qrcode"></i> Instant Attendance Verification
                    </span>
                    <h1 class="display-6 fw-bold mb-2">Client QR Pass & Check-in Hub</h1>
                    <p class="text-muted fs-6 mb-0">
                        Scan customer mobile passes or search by Appointment ID to quickly verify booking details, apply discounts, and complete service check-ins.
                    </p>
                </div>
                <div class="col-lg-5 text-lg-end">
                    <div class="d-flex gap-2 justify-content-lg-end flex-wrap">
                        <div class="metric-pill">
                            <i class="fas fa-calendar-day text-primary"></i>
                            <div>
                                <strong><?= $total_today ?></strong>
                                <small>Today's Bookings</small>
                            </div>
                        </div>
                        <div class="metric-pill">
                            <i class="fas fa-check-double text-success"></i>
                            <div>
                                <strong><?= $completed_today ?></strong>
                                <small>Checked-in</small>
                            </div>
                        </div>
                        <div class="metric-pill">
                            <i class="fas fa-hourglass-half text-warning"></i>
                            <div>
                                <strong><?= $pending_today ?></strong>
                                <small>Pending</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Notification Box -->
        <div id="alertBox"></div>

        <!-- Main 2-Column Interface -->
        <div class="row g-4">
            
            <!-- Left Column: Verification Controls (Scanner & Manual Search) -->
            <div class="col-lg-6">
                <div class="checkin-panel-card">
                    
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-barcode text-primary me-2"></i>Verification Method
                        </h5>
                        <span class="text-muted small">Choose how to verify</span>
                    </div>

                    <!-- Method Tabs -->
                    <div class="method-tabs">
                        <button type="button" class="method-tab-btn active" id="tabCameraBtn" onclick="switchTab('camera')">
                            <i class="fas fa-camera"></i> Live Camera Scan
                        </button>
                        <button type="button" class="method-tab-btn" id="tabManualBtn" onclick="switchTab('manual')">
                            <i class="fas fa-keyboard"></i> Manual ID Input
                        </button>
                    </div>

                    <!-- View A: Camera Scanner Viewport -->
                    <div id="cameraScanSection">
                        <div class="scanner-frame-wrap mb-3" id="scannerFrame">
                            <div id="reader"></div>
                            <div id="cameraStandbyText" class="text-center p-4">
                                <i class="fas fa-video fa-2x text-primary mb-2 opacity-75"></i>
                                <p class="text-light small mb-0">Camera is currently paused.<br>Click "Start Camera Scanner" below to activate.</p>
                            </div>
                        </div>

                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold shadow-sm" id="startCamBtn">
                                <i class="fas fa-camera me-1"></i> Start Camera Scanner
                            </button>
                            <button type="button" class="btn btn-outline-danger rounded-pill px-4 py-2 fw-semibold" id="stopCamBtn" style="display:none;">
                                <i class="fas fa-stop me-1"></i> Stop Camera
                            </button>
                        </div>
                        
                        <p class="text-muted small text-center mt-3 mb-0">
                            <i class="fas fa-info-circle me-1"></i> Position the client's QR Pass inside the camera frame to scan automatically.
                        </p>
                    </div>

                    <!-- View B: Manual Appointment ID Search -->
                    <div id="manualSearchSection" style="display:none;">
                        <label class="form-label fw-bold mb-2">Enter Appointment ID #</label>
                        <div class="input-group input-group-lg mb-3">
                            <span class="input-group-text"><i class="fas fa-hashtag text-primary"></i></span>
                            <input type="number" id="manualApptId" class="form-control" placeholder="e.g. 1, 12, 105..." autofocus>
                            <button class="btn btn-primary px-4 fw-bold" id="searchBtn" style="background:var(--gradient-primary); border:none;">
                                <i class="fas fa-search me-1"></i> Verify Pass
                            </button>
                        </div>
                        <small class="text-muted d-block mb-3">
                            You can find the Appointment ID on the client's confirmation email, SMS, or QR pass card.
                        </small>

                        <div class="p-3 rounded-3 bg-light border">
                            <h6 class="fw-bold mb-2 text-dark small"><i class="fas fa-bolt text-warning me-1"></i>Quick Tip</h6>
                            <p class="text-muted small mb-0">
                                If you know the client's name or scheduled time today, you can also click the <strong>"Verify"</strong> button directly from Today's Queue below!
                            </p>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Right Column: Live Ticket / Verification Result Card -->
            <div class="col-lg-6">
                
                <!-- State A: Empty Placeholder (Awaiting Scan) -->
                <div class="checkin-panel-card" id="awaitingScanCard">
                    <div class="empty-scanner-state">
                        <div class="empty-scanner-icon">
                            <i class="fas fa-qrcode"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-2">Awaiting Client Pass</h4>
                        <p class="text-muted mb-4" style="max-width: 380px; margin: 0 auto;">
                            Scan a customer's digital QR pass with your camera or enter their Appointment ID on the left to verify details.
                        </p>
                        <div class="d-inline-flex align-items-center gap-2 p-2 px-3 rounded-pill bg-light border text-muted small">
                            <i class="fas fa-shield-alt text-success"></i> Instant verification & attendance sync
                        </div>
                    </div>
                </div>

                <!-- State B: Ticket Result Card (When Verified) -->
                <div class="ticket-result-card" id="ticketResultCard" style="display:none;">
                    
                    <div class="ticket-header">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-white text-dark px-3 py-1 rounded-pill fw-bold" id="resApptId">
                                Appointment #--
                            </span>
                            <span id="resStatusBadge" class="status-badge-pending">PENDING</span>
                        </div>
                        <h4 class="fw-bold mb-0 text-white" id="resSalonName">Salon Name</h4>
                        <small class="text-white opacity-75" id="resCreatedAt">Booked on --</small>
                    </div>

                    <div class="ticket-body">
                        
                        <!-- Customer Bio Line -->
                        <div class="d-flex align-items-center gap-3 pb-3 mb-2 border-bottom">
                            <div class="user-avatar-circle" id="resAvatar">
                                U
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="fw-bold mb-0 text-dark" id="resCustName">Client Name</h5>
                                <div class="d-flex align-items-center gap-3 text-muted small mt-1">
                                    <span><i class="fas fa-phone text-primary me-1"></i> <a href="#" id="resCustPhoneLink" class="text-decoration-none"></a></span>
                                    <span id="resCustEmailLine"><i class="fas fa-envelope text-primary me-1"></i> <span id="resCustEmail"></span></span>
                                </div>
                            </div>
                        </div>

                        <!-- Ticket Detail Lines -->
                        <div class="ticket-row">
                            <span class="ticket-label"><i class="fas fa-cut me-1 text-primary"></i> Service</span>
                            <span class="ticket-val" id="resService">--</span>
                        </div>

                        <div class="ticket-row">
                            <span class="ticket-label"><i class="fas fa-user-tie me-1 text-primary"></i> Assigned Stylist</span>
                            <span class="ticket-val" id="resStaff">--</span>
                        </div>

                        <div class="ticket-row">
                            <span class="ticket-label"><i class="far fa-calendar-alt me-1 text-primary"></i> Scheduled Slot</span>
                            <span class="ticket-val text-primary" id="resDateTime">--</span>
                        </div>

                        <div class="ticket-row">
                            <span class="ticket-label"><i class="far fa-clock me-1 text-primary"></i> Duration</span>
                            <span class="ticket-val" id="resDuration">--</span>
                        </div>

                        <div class="ticket-row" id="resPromoRow" style="display:none;">
                            <span class="ticket-label"><i class="fas fa-tag me-1 text-warning"></i> Promo Applied</span>
                            <span class="ticket-val text-success" id="resPromo">--</span>
                        </div>

                        <div class="ticket-row">
                            <span class="ticket-label"><i class="fas fa-receipt me-1 text-primary"></i> Payable Amount</span>
                            <span class="ticket-val text-success fs-5" id="resPrice">Rs 0.00</span>
                        </div>

                        <div class="p-2 rounded-3 bg-light border mt-3" id="resNotesBox" style="display:none;">
                            <small class="text-muted d-block fw-semibold"><i class="fas fa-comment-alt me-1"></i> Client Special Requests:</small>
                            <span class="small text-dark" id="resNotes"></span>
                        </div>

                        <!-- CTA Completion Action -->
                        <div class="mt-4 pt-2">
                            <div id="checkinActionContainer">
                                <button type="button" class="btn-confirm-checkin" id="confirmCheckinBtn">
                                    <i class="fas fa-check-circle me-2"></i> Confirm Check-in & Mark Completed
                                </button>
                            </div>
                            <div id="alreadyCompletedNotice" style="display:none;" class="alert alert-success d-flex align-items-center gap-2 mb-0 rounded-4">
                                <i class="fas fa-check-double fa-lg"></i>
                                <div>
                                    <strong>Already Completed</strong>
                                    <small class="d-block">This client has already checked in and completed their service.</small>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>

        <!-- Today's Queue / Quick Check-in Table -->
        <div class="queue-table-card p-4 mt-5">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-list-check text-primary me-2"></i>
                        <?= $total_today > 0 ? "Today's Appointment Queue (" . date('M d, Y') . ")" : "Recent Salon Bookings" ?>
                    </h5>
                    <small class="text-muted">
                        <?= $total_today > 0 ? "Fast 1-click check-in for arriving clients today" : "No appointments scheduled for today yet. Showing latest salon bookings:" ?>
                    </small>
                </div>
                <a href="appointments.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                    View Full Calendar <i class="fas fa-chevron-right ms-1"></i>
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#ID</th>
                            <th>Time</th>
                            <th>Client</th>
                            <th>Salon & Service</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th class="text-end">Quick Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $queue_items = $total_today > 0 ? $today_queue : $recent_list;
                        if (!empty($queue_items)): 
                            foreach ($queue_items as $item):
                                $stClass = 'status-badge-pending';
                                if ($item['status'] === 'completed') $stClass = 'status-badge-completed';
                                elseif ($item['status'] === 'confirmed') $stClass = 'status-badge-confirmed';
                        ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= $item['id'] ?></td>
                                <td>
                                    <strong><?= date('h:i A', strtotime($item['appointment_time'])) ?></strong>
                                    <?php if ($total_today === 0): ?>
                                        <small class="d-block text-muted"><?= date('M d', strtotime($item['appointment_date'])) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="text-dark d-block"><?= htmlspecialchars($item['customer_name']) ?></strong>
                                    <small class="text-muted"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($item['customer_phone'] ?: 'N/A') ?></small>
                                </td>
                                <td>
                                    <strong class="text-primary d-block"><?= htmlspecialchars($item['service_name']) ?></strong>
                                    <small class="text-muted"><?= htmlspecialchars($item['salon_name']) ?></small>
                                </td>
                                <td class="fw-bold text-success">
                                    Rs <?= number_format($item['service_price'], 2) ?>
                                </td>
                                <td>
                                    <span class="<?= $stClass ?>"><?= strtoupper($item['status']) ?></span>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="verifyAppointment(<?= $item['id'] ?>)">
                                        <i class="fas fa-search me-1"></i> Verify Pass
                                    </button>
                                </td>
                            </tr>
                        <?php 
                            endforeach; 
                        else: 
                        ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-calendar-times fa-2x mb-2 d-block opacity-50"></i>
                                    No appointment records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- End Container -->

    <?php include __DIR__ . '/../footer.php'; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
        let currentApptId = null;
        let html5QrCode = null;

        // Audio Beep on Success
        function playSuccessBeep() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(800, ctx.currentTime);
                gain.gain.setValueAtTime(0.1, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.25);
            } catch (e) {}
        }

        function showAlert(msg, type = 'danger') {
            const alertBox = document.getElementById('alertBox');
            alertBox.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas ${type === 'success' ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-danger'} fa-lg"></i>
                        <span class="fw-semibold">${msg}</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Switch between Camera & Manual tabs
        function switchTab(mode) {
            const camSection = document.getElementById('cameraScanSection');
            const manualSection = document.getElementById('manualSearchSection');
            const tabCam = document.getElementById('tabCameraBtn');
            const tabManual = document.getElementById('tabManualBtn');

            if (mode === 'camera') {
                camSection.style.display = 'block';
                manualSection.style.display = 'none';
                tabCam.classList.add('active');
                tabManual.classList.remove('active');
            } else {
                camSection.style.display = 'none';
                manualSection.style.display = 'block';
                tabCam.classList.remove('active');
                tabManual.classList.add('active');
                document.getElementById('manualApptId').focus();
            }
        }

        // Verify Appointment details via AJAX
        async function verifyAppointment(id) {
            if (!id) return;
            currentApptId = id;

            const searchBtn = document.getElementById('searchBtn');
            if (searchBtn) {
                searchBtn.disabled = true;
                searchBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Verifying...';
            }

            const formData = new FormData();
            formData.append('action', 'verify');
            formData.append('appointment_id', id);
            formData.append('csrf_token', csrfToken);

            try {
                const res = await fetch('checkin.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (searchBtn) {
                    searchBtn.disabled = false;
                    searchBtn.innerHTML = '<i class="fas fa-search me-1"></i> Verify Pass';
                }

                if (data.success) {
                    playSuccessBeep();
                    const a = data.appointment;

                    document.getElementById('resApptId').textContent = 'Appointment #' + a.id;
                    document.getElementById('resSalonName').textContent = a.salon_name;
                    document.getElementById('resCreatedAt').textContent = 'Booked: ' + a.created_at;

                    document.getElementById('resCustName').textContent = a.customer_name;
                    document.getElementById('resAvatar').textContent = a.customer_name.charAt(0).toUpperCase();

                    const phoneLink = document.getElementById('resCustPhoneLink');
                    phoneLink.textContent = a.customer_phone;
                    phoneLink.href = 'tel:' + a.customer_phone;

                    if (a.customer_email && a.customer_email !== 'N/A') {
                        document.getElementById('resCustEmail').textContent = a.customer_email;
                        document.getElementById('resCustEmailLine').style.display = 'inline-block';
                    } else {
                        document.getElementById('resCustEmailLine').style.display = 'none';
                    }

                    document.getElementById('resService').textContent = a.service_name;
                    document.getElementById('resStaff').textContent = a.staff_name;
                    document.getElementById('resDateTime').textContent = a.date + ' at ' + a.time;
                    document.getElementById('resDuration').textContent = a.service_duration;
                    document.getElementById('resPrice').textContent = 'Rs ' + a.final_price;

                    if (a.promo_code) {
                        document.getElementById('resPromoRow').style.display = 'flex';
                        document.getElementById('resPromo').textContent = a.promo_code + (a.discount_amount ? ' (-Rs ' + a.discount_amount + ')' : '');
                    } else {
                        document.getElementById('resPromoRow').style.display = 'none';
                    }

                    if (a.notes) {
                        document.getElementById('resNotesBox').style.display = 'block';
                        document.getElementById('resNotes').textContent = a.notes;
                    } else {
                        document.getElementById('resNotesBox').style.display = 'none';
                    }

                    // Status handling
                    const statusBadge = document.getElementById('resStatusBadge');
                    statusBadge.textContent = a.status.toUpperCase();
                    statusBadge.className = 'status-badge-' + a.status;

                    const actionContainer = document.getElementById('checkinActionContainer');
                    const completedNotice = document.getElementById('alreadyCompletedNotice');

                    if (a.status === 'completed') {
                        actionContainer.style.display = 'none';
                        completedNotice.style.display = 'flex';
                    } else {
                        actionContainer.style.display = 'block';
                        completedNotice.style.display = 'none';
                    }

                    // Show ticket card, hide empty state
                    document.getElementById('ticketResultCard').style.display = 'block';
                    document.getElementById('awaitingScanCard').style.display = 'none';
                    document.getElementById('alertBox').innerHTML = '';

                    // Scroll to ticket card on mobile
                    if (window.innerWidth < 992) {
                        document.getElementById('ticketResultCard').scrollIntoView({ behavior: 'smooth' });
                    }
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (err) {
                if (searchBtn) {
                    searchBtn.disabled = false;
                    searchBtn.innerHTML = '<i class="fas fa-search me-1"></i> Verify Pass';
                }
                showAlert('Network error while verifying appointment pass.', 'danger');
            }
        }

        // Manual search button
        document.getElementById('searchBtn').addEventListener('click', () => {
            const id = document.getElementById('manualApptId').value.trim();
            if (id) verifyAppointment(id);
            else showAlert('Please enter an Appointment ID number.', 'warning');
        });

        // Enter key in manual input
        document.getElementById('manualApptId').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('searchBtn').click();
            }
        });

        // Confirm & Complete Check-in
        document.getElementById('confirmCheckinBtn').addEventListener('click', async function() {
            if (!currentApptId) return;

            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Completing Check-in...';

            const formData = new FormData();
            formData.append('action', 'complete_checkin');
            formData.append('appointment_id', currentApptId);
            formData.append('csrf_token', csrfToken);

            try {
                const res = await fetch('checkin.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Confirm Check-in & Mark Completed';

                if (data.success) {
                    playSuccessBeep();
                    showAlert(data.message, 'success');
                    
                    // Mark as completed in the UI ticket
                    document.getElementById('resStatusBadge').textContent = 'COMPLETED';
                    document.getElementById('resStatusBadge').className = 'status-badge-completed';
                    document.getElementById('checkinActionContainer').style.display = 'none';
                    document.getElementById('alreadyCompletedNotice').style.display = 'flex';

                    // Clear manual search box
                    document.getElementById('manualApptId').value = '';

                    // Reload page after 1.8s to refresh queue table & metrics
                    setTimeout(() => {
                        location.reload();
                    }, 1800);
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (err) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Confirm Check-in & Mark Completed';
                showAlert('An error occurred while confirming check-in.', 'danger');
            }
        });

        // Camera QR Scanner integration with SALONORA_APT format support
        const startCamBtn = document.getElementById('startCamBtn');
        const stopCamBtn = document.getElementById('stopCamBtn');
        const standbyText = document.getElementById('cameraStandbyText');

        startCamBtn.addEventListener('click', () => {
            standbyText.style.display = 'none';
            html5QrCode = new Html5Qrcode("reader");

            html5QrCode.start(
                { facingMode: "environment" },
                { 
                    fps: 15, 
                    qrbox: { width: 250, height: 250 } 
                },
                (decodedText) => {
                    let extractedId = null;

                    // Support format: SALONORA_APT:12
                    if (decodedText.startsWith('SALONORA_APT:')) {
                        extractedId = decodedText.replace('SALONORA_APT:', '').trim();
                    } else if (!isNaN(decodedText.trim())) {
                        extractedId = decodedText.trim();
                    } else {
                        try {
                            const parsed = JSON.parse(decodedText);
                            if (parsed.appt_id) extractedId = parsed.appt_id;
                        } catch(e) {}
                    }

                    if (extractedId) {
                        verifyAppointment(extractedId);
                        stopCamera();
                    }
                },
                (error) => {}
            ).then(() => {
                startCamBtn.style.display = 'none';
                stopCamBtn.style.display = 'inline-block';
            }).catch(err => {
                standbyText.style.display = 'block';
                showAlert('Camera permission was denied or camera hardware was not found.', 'warning');
            });
        });

        function stopCamera() {
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    startCamBtn.style.display = 'inline-block';
                    stopCamBtn.style.display = 'none';
                    standbyText.style.display = 'block';
                }).catch(() => {});
            }
        }
        stopCamBtn.addEventListener('click', stopCamera);

        // Theme Toggle Handler
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeToggleIcon = document.getElementById('themeToggleIcon');

        function updateThemeUI() {
            const isDark = document.body.classList.contains('dark-mode') || document.documentElement.classList.contains('dark-mode');
            if (themeToggleIcon) {
                themeToggleIcon.className = isDark ? 'fas fa-sun me-1 text-warning' : 'fas fa-moon me-1';
            }
        }
        updateThemeUI();

        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                const isDark = !document.documentElement.classList.contains('dark-mode');
                document.documentElement.classList.toggle('dark-mode', isDark);
                document.body.classList.toggle('dark-mode', isDark);
                localStorage.setItem('salonora_theme', isDark ? 'dark' : 'light');
                updateThemeUI();
            });
        }
    </script>
</body>
</html>
