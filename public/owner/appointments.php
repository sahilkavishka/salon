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
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Appointments - Salonora Owner</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { font-family: 'Poppins', sans-serif; background: #f5f7fa; }
            .navbar-brand { font-weight: 800; background: linear-gradient(135deg,#e91e63,#9c27b0); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        </style>
    </head>
    <body>
        <nav class="navbar navbar-expand-lg bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="../../index.php"><i class="fas fa-spa"></i> Salonora</a>
            </div>
        </nav>
        <div class="container mt-5 text-center">
            <div class="alert alert-info">
                <i class="fas fa-info-circle fa-3x mb-3"></i>
                <h4>No Salons Yet</h4>
                <p>You haven't registered any salons yet. Create your first salon to start managing appointments.</p>
                <a href="add_salon.php" class="btn btn-primary mt-3">Add Your First Salon</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Filter by salon
$selectedSalon = isset($_GET['salon']) && is_numeric($_GET['salon']) ? (int)$_GET['salon'] : null;
$filterSalons = $selectedSalon ? [$selectedSalon] : $salonIds;

// Prepare IN placeholders
$in = str_repeat('?,', count($filterSalons) - 1) . '?';

// Fetch pending requests
$stmt = $pdo->prepare("
    SELECT 
        a.*, 
        u.username AS user_name,
        u.email AS user_email,
        u.phone AS user_phone,
        s.name AS service_name,
        s.price AS service_price,
        s.duration AS service_duration,
        sal.name AS salon_name
    FROM appointments a
    JOIN users u ON u.id = a.user_id
    JOIN services s ON s.id = a.service_id
    JOIN salons sal ON sal.id = a.salon_id
    WHERE a.salon_id IN ($in) AND a.status = 'pending'
    ORDER BY a.created_at DESC
");
$stmt->execute($filterSalons);
$pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch confirmed bookings (upcoming only)
$stmt = $pdo->prepare("
    SELECT 
        a.*, 
        u.username AS user_name,
        u.email AS user_email,
        u.phone AS user_phone,
        s.name AS service_name,
        s.price AS service_price,
        s.duration AS service_duration,
        sal.name AS salon_name
    FROM appointments a
    JOIN users u ON u.id = a.user_id
    JOIN services s ON s.id = a.service_id
    JOIN salons sal ON sal.id = a.salon_id
    WHERE a.salon_id IN ($in) 
    AND a.status = 'confirmed'
    AND CONCAT(a.appointment_date, ' ', a.appointment_time) >= NOW()
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$stmt->execute($filterSalons);
$confirmed = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch today's appointments
$stmt = $pdo->prepare("
    SELECT 
        a.*, 
        u.username AS user_name,
        u.email AS user_email,
        u.phone AS user_phone,
        s.name AS service_name,
        s.price AS service_price,
        s.duration AS service_duration,
        sal.name AS salon_name
    FROM appointments a
    JOIN users u ON u.id = a.user_id
    JOIN services s ON s.id = a.service_id
    JOIN salons sal ON sal.id = a.salon_id
    WHERE a.salon_id IN ($in) 
    AND a.status IN ('confirmed', 'pending')
    AND DATE(a.appointment_date) = CURDATE()
    ORDER BY a.appointment_time ASC
");
$stmt->execute($filterSalons);
$today = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch completed appointments
$stmt = $pdo->prepare("
    SELECT 
        a.*, 
        u.username AS user_name,
        s.name AS service_name,
        s.price AS service_price,
        sal.name AS salon_name
    FROM appointments a
    JOIN users u ON u.id = a.user_id
    JOIN services s ON s.id = a.service_id
    JOIN salons sal ON sal.id = a.salon_id
    WHERE a.salon_id IN ($in) AND a.status = 'completed'
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 20
");
$stmt->execute($filterSalons);
$completed = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch cancelled appointments
$stmt = $pdo->prepare("
    SELECT 
        a.*, 
        u.username AS user_name,
        s.name AS service_name,
        sal.name AS salon_name
    FROM appointments a
    JOIN users u ON u.id = a.user_id
    JOIN services s ON s.id = a.service_id
    JOIN salons sal ON sal.id = a.salon_id
    WHERE a.salon_id IN ($in) AND a.status = 'cancelled'
    ORDER BY a.updated_at DESC
    LIMIT 20
");
$stmt->execute($filterSalons);
$cancelled = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$totalRevenue = array_sum(array_column($completed, 'service_price'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appointments Management - Salonora Owner</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f5f7fa; }
        .navbar-brand { font-weight: 800; background: linear-gradient(135deg,#e91e63,#9c27b0); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .page-header { background: linear-gradient(135deg,#e91e63,#9c27b0); padding:3rem 0 2rem; color:white; }
        
        .stat-card { 
            background: white; 
            padding: 1.5rem; 
            border-radius: 16px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 4px 16px rgba(0,0,0,0.12); }
        .stat-card.pending { border-left-color: #f39c12; }
        .stat-card.confirmed { border-left-color: #27ae60; }
        .stat-card.today { border-left-color: #3498db; }
        .stat-card.revenue { border-left-color: #9b59b6; }
        .stat-value { font-size: 2rem; font-weight: 700; margin: 0; }
        .stat-label { color: #7f8c8d; font-size: 0.9rem; }
        
        .appointment-card {
            background: white;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 12px;
            border-left: 4px solid;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.2s;
        }
        .appointment-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .appointment-card.pending { border-left-color: #f39c12; }
        .appointment-card.confirmed { border-left-color: #27ae60; }
        .appointment-card.completed { border-left-color: #3498db; }
        .appointment-card.cancelled { border-left-color: #95a5a6; }
        
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-badge.pending { color: #f39c12; background: rgba(243,156,18,0.1); }
        .status-badge.confirmed { color: #27ae60; background: rgba(39,174,96,0.1); }
        .status-badge.completed { color: #3498db; background: rgba(52,152,219,0.1); }
        .status-badge.cancelled { color: #95a5a6; background: rgba(149,165,166,0.1); }
        
        .btn-confirm { background: #27ae60; color: white; border: none; }
        .btn-confirm:hover { background: #229954; color: white; }
        .btn-reject { background: #e74c3c; color: white; border: none; }
        .btn-reject:hover { background: #c0392b; color: white; }
        .btn-complete { background: #3498db; color: white; border: none; }
        .btn-complete:hover { background: #2980b9; color: white; }
        
        .nav-pills .nav-link { border-radius: 50px; margin: 0 0.25rem; }
        .nav-pills .nav-link.active { background: linear-gradient(135deg,#e91e63,#9c27b0); }
        
        .info-item { display: flex; align-items: center; margin-bottom: 0.5rem; }
        .info-item i { width: 24px; margin-right: 8px; }
        
        .empty-state { 
            text-align: center; 
            padding: 3rem; 
            background: white; 
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .empty-state i { font-size: 4rem; color: #bdc3c7; margin-bottom: 1rem; }
        
        .filter-section { 
            background: white; 
            padding: 1rem; 
            border-radius: 12px; 
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="../../index.php"><i class="fas fa-spa"></i> Salonora</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav align-items-center">
                <li class="nav-item"><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li class="nav-item"><a href="my_salons.php" class="nav-link">My Salons</a></li>
                <li class="nav-item"><a href="appointments.php" class="nav-link active">Appointments</a></li>
                <li class="nav-item ms-3">
                    <a href="../logout.php" class="btn btn-sm btn-danger">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-calendar-alt me-2"></i>Appointments Management</h1>
        <p>Manage bookings and customer appointments</p>
    </div>
</div>

<div class="container py-4">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card pending">
                <div class="stat-value"><?= count($pending) ?></div>
                <div class="stat-label"><i class="fas fa-clock me-1"></i>Pending Requests</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card confirmed">
                <div class="stat-value"><?= count($confirmed) ?></div>
                <div class="stat-label"><i class="fas fa-check-circle me-1"></i>Confirmed</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card today">
                <div class="stat-value"><?= count($today) ?></div>
                <div class="stat-label"><i class="fas fa-calendar-day me-1"></i>Today's Appointments</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card revenue">
                <div class="stat-value">Rs <?= number_format($totalRevenue) ?></div>
                <div class="stat-label"><i class="fas fa-rupee-sign me-1"></i>Completed Revenue</div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <?php if (count($salons) > 1): ?>
    <div class="filter-section">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-auto">
                <label class="form-label mb-0"><i class="fas fa-filter me-2"></i>Filter by Salon:</label>
            </div>
            <div class="col-auto">
                <select name="salon" class="form-select" onchange="this.form.submit()">
                    <option value="">All Salons</option>
                    <?php foreach ($salons as $salon): ?>
                        <option value="<?= $salon['id'] ?>" <?= $selectedSalon == $salon['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($salon['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($selectedSalon): ?>
            <div class="col-auto">
                <a href="appointments.php" class="btn btn-sm btn-outline-secondary">Clear Filter</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>

    <!-- Tabs Navigation -->
    <ul class="nav nav-pills mb-4 justify-content-center" id="appointmentTabs">
        <li class="nav-item">
            <button class="nav-link active" data-tab="today">
                Today (<?= count($today) ?>)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-tab="pending">
                Pending (<?= count($pending) ?>)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-tab="confirmed">
                Confirmed (<?= count($confirmed) ?>)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-tab="completed">
                Completed (<?= count($completed) ?>)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-tab="cancelled">
                Cancelled (<?= count($cancelled) ?>)
            </button>
        </li>
    </ul>

    <!-- Today's Appointments Tab -->
    <div class="tab-content active" id="today">
        <h4 class="mb-3"><i class="fas fa-calendar-day text-primary me-2"></i>Today's Schedule</h4>
        <?php if (empty($today)): ?>
            <div class="empty-state">
                <i class="far fa-calendar-check"></i>
                <h5>No Appointments Today</h5>
                <p class="text-muted">Your schedule is clear for today.</p>
            </div>
        <?php else: ?>
            <?php foreach ($today as $apt): ?>
                <div class="appointment-card <?= $apt['status'] ?>">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="mb-0"><?= htmlspecialchars($apt['user_name']) ?></h5>
                                <span class="status-badge <?= $apt['status'] ?>"><?= ucfirst($apt['status']) ?></span>
                            </div>
                            
                            <div class="info-item">
                                <i class="fas fa-store text-primary"></i>
                                <span><strong><?= htmlspecialchars($apt['salon_name']) ?></strong></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-cut text-success"></i>
                                <span><?= htmlspecialchars($apt['service_name']) ?> (<?= $apt['service_duration'] ?> mins)</span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-clock text-warning"></i>
                                <span><?= date('h:i A', strtotime($apt['appointment_time'])) ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-rupee-sign text-danger"></i>
                                <span>Rs <?= number_format($apt['service_price'], 2) ?></span>
                            </div>
                            <?php if (!empty($apt['user_phone'])): ?>
                            <div class="info-item">
                                <i class="fas fa-phone text-info"></i>
                                <span><?= htmlspecialchars($apt['user_phone']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <?php if ($apt['status'] === 'pending'): ?>
                                <form action="owner_confirm.php" method="post" style="display:inline;">
                                    <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                    <input type="hidden" name="action" value="confirm">
                                    <button type="submit" class="btn btn-sm btn-confirm mb-1">
                                        <i class="fas fa-check me-1"></i>Confirm
                                    </button>
                                </form>
                                <form action="owner_confirm.php" method="post" style="display:inline;">
                                    <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-sm btn-reject mb-1" onclick="return confirm('Reject this request?')">
                                        <i class="fas fa-times me-1"></i>Reject
                                    </button>
                                </form>
                            <?php elseif ($apt['status'] === 'confirmed'): ?>
                                <form action="owner_confirm.php" method="post" style="display:inline;">
                                    <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <button type="submit" class="btn btn-sm btn-complete mb-1">
                                        <i class="fas fa-check-double me-1"></i>Mark Complete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pending Requests Tab -->
    <div class="tab-content" id="pending">
        <h4 class="mb-3"><i class="fas fa-clock text-warning me-2"></i>Pending Requests</h4>
        <?php if (empty($pending)): ?>
            <div class="empty-state">
                <i class="far fa-clock"></i>
                <h5>No Pending Requests</h5>
                <p class="text-muted">All appointment requests have been processed.</p>
            </div>
        <?php else: ?>
            <?php foreach ($pending as $apt): ?>
                <div class="appointment-card pending">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="mb-0"><?= htmlspecialchars($apt['user_name']) ?></h5>
                                <span class="status-badge pending">Pending</span>
                            </div>
                            
                            <div class="info-item">
                                <i class="fas fa-store text-primary"></i>
                                <span><strong><?= htmlspecialchars($apt['salon_name']) ?></strong></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-cut text-success"></i>
                                <span><?= htmlspecialchars($apt['service_name']) ?> - Rs <?= number_format($apt['service_price'], 2) ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-calendar text-danger"></i>
                                <span><?= date('M d, Y', strtotime($apt['appointment_date'])) ?> at <?= date('h:i A', strtotime($apt['appointment_time'])) ?></span>
                            </div>
                            <?php if (!empty($apt['user_email'])): ?>
                            <div class="info-item">
                                <i class="fas fa-envelope text-info"></i>
                                <span><?= htmlspecialchars($apt['user_email']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($apt['user_phone'])): ?>
                            <div class="info-item">
                                <i class="fas fa-phone text-info"></i>
                                <span><?= htmlspecialchars($apt['user_phone']) ?></span>
                            </div>
                            <?php endif; ?>
                            <small class="text-muted d-block mt-2">
                                <i class="far fa-clock"></i> Requested: <?= date('M d, Y h:i A', strtotime($apt['created_at'])) ?>
                            </small>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <form action="owner_confirm.php" method="post" style="display:inline;">
                                <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                <input type="hidden" name="action" value="confirm">
                                <button type="submit" class="btn btn-confirm w-100 mb-2">
                                    <i class="fas fa-check me-1"></i>Confirm Booking
                                </button>
                            </form>
                            <form action="owner_confirm.php" method="post" style="display:inline;">
                                <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-reject w-100" onclick="return confirm('Are you sure you want to reject this request?')">
                                    <i class="fas fa-times me-1"></i>Reject Request
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Confirmed Appointments Tab -->
    <div class="tab-content" id="confirmed">
        <h4 class="mb-3"><i class="fas fa-check-circle text-success me-2"></i>Confirmed Appointments</h4>
        <?php if (empty($confirmed)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h5>No Confirmed Appointments</h5>
                <p class="text-muted">No upcoming confirmed appointments at the moment.</p>
            </div>
        <?php else: ?>
            <?php foreach ($confirmed as $apt): ?>
                <div class="appointment-card confirmed">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="mb-0"><?= htmlspecialchars($apt['user_name']) ?></h5>
                                <span class="status-badge confirmed">Confirmed</span>
                            </div>
                            
                            <div class="info-item">
                                <i class="fas fa-store text-primary"></i>
                                <span><strong><?= htmlspecialchars($apt['salon_name']) ?></strong></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-cut text-success"></i>
                                <span><?= htmlspecialchars($apt['service_name']) ?> - Rs <?= number_format($apt['service_price'], 2) ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-calendar text-danger"></i>
                                <span><?= date('l, F j, Y', strtotime($apt['appointment_date'])) ?> at <?= date('h:i A', strtotime($apt['appointment_time'])) ?></span>
                            </div>
                            <?php if (!empty($apt['user_phone'])): ?>
                            <div class="info-item">
                                <i class="fas fa-phone text-info"></i>
                                <span><?= htmlspecialchars($apt['user_phone']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <form action="owner_confirm.php" method="post" style="display:inline;">
                                <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                <input type="hidden" name="action" value="complete">
                                <button type="submit" class="btn btn-complete w-100">
                                    <i class="fas fa-check-double me-1"></i>Mark as Completed
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Completed Appointments Tab -->
    <div class="tab-content" id="completed">
        <h4 class="mb-3"><i class="fas fa-check-double text-primary me-2"></i>Completed Appointments</h4>
        <?php if (empty($completed)): ?>
            <div class="empty-state">
                <i class="far fa-calendar-check"></i>
                <h5>No Completed Appointments</h5>
                <p class="text-muted">Completed appointments will appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($completed as $apt): ?>
                <div class="appointment-card completed">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5><?= htmlspecialchars($apt['user_name']) ?></h5>
                            <p class="mb-1"><strong><?= htmlspecialchars($apt['salon_name']) ?></strong></p>
                            <p class="mb-1"><?= htmlspecialchars($apt['service_name']) ?> - Rs <?= number_format($apt['service_price'], 2) ?></p>
                            <p class="mb-1 text-muted"><?= date('M d, Y', strtotime($apt['appointment_date'])) ?> at <?= date('h:i A', strtotime($apt['appointment_time'])) ?></p>
                        </div>
                        <span class="status-badge completed">Completed</span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Cancelled Appointments Tab -->
    <div class="tab-content" id="cancelled">
        <h4 class="mb-3"><i class="fas fa-times-circle text-secondary me-2"></i>Cancelled Appointments</h4>
        <?php if (empty($cancelled)): ?>
            <div class="empty-state">
                <i class="fas fa-ban"></i>
                <h5>No Cancelled Appointments</h5>
                <p class="text-muted">Cancelled appointments will appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($cancelled as $apt): ?>
                <div class="appointment-card cancelled">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5><?= htmlspecialchars($apt['user_name']) ?></h5>
                            <p class="mb-1"><strong><?= htmlspecialchars($apt['salon_name']) ?></strong></p>
                            <p class="mb-1"><?= htmlspecialchars($apt['service_name']) ?></p>
                            <p class="mb-1 text-muted"><?= date('M d, Y', strtotime($apt['appointment_date'])) ?> at <?= date('h:i A', strtotime($apt['appointment_time'])) ?></p>
                            <?php if (!empty($apt['cancellation_reason'])): ?>
                            <small class="text-muted"><i class="fas fa-info-circle"></i> Reason: <?= htmlspecialchars($apt['cancellation_reason']) ?></small>
                            <?php endif; ?>
                        </div>
                        <span class="status-badge cancelled">Cancelled</span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Footer -->
<footer class="bg-white mt-5 py-4 border-top">
    <div class="container text-center">
        <p class="mb-0 text-muted">&copy; 2025 Salonora. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Tab switching functionality
    const tabs = document.querySelectorAll('[data-tab]');
    const contents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            // Remove active class from all tabs and contents
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding content
            tab.classList.add('active');
            const targetId = tab.getAttribute('data-tab');
            document.getElementById(targetId).classList.add('active');
        });
    });

    // Auto-refresh every 2 minutes to check for new appointments
    let autoRefreshEnabled = true;
    
    // Show notification if there are pending requests
    <?php if (count($pending) > 0): ?>
        if (Notification.permission === "granted") {
            // Show notification
        } else if (Notification.permission !== "denied") {
            Notification.requestPermission();
        }
    <?php endif; ?>

    // Confirmation dialogs for better UX
    document.querySelectorAll('form').forEach(form => {
        const action = form.querySelector('input[name="action"]')?.value;
        
        if (action === 'complete') {
            form.addEventListener('submit', function(e) {
                if (!confirm('Mark this appointment as completed?')) {
                    e.preventDefault();
                }
            });
        }
    });

    // Add visual feedback on button clicks
    document.querySelectorAll('button[type="submit"]').forEach(btn => {
        btn.addEventListener('click', function() {
            this.disabled = true;
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
            
            // Re-enable after 3 seconds as fallback
            setTimeout(() => {
                this.disabled = false;
                this.innerHTML = originalText;
            }, 3000);
        });
    });
</script>

</body>
</html>