<?php
// public/owner/dashboard.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];

// Handle salon deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_salon'])) {
    $salon_id = $_POST['salon_id'] ?? 0;
    
    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM salons WHERE id = ? AND owner_id = ?");
    $stmt->execute([$salon_id, $owner_id]);
    
    if ($stmt->fetch()) {
        try {
            // Delete related records first (appointments, services, reviews)
            $pdo->prepare("DELETE FROM appointments WHERE salon_id = ?")->execute([$salon_id]);
            $pdo->prepare("DELETE FROM services WHERE salon_id = ?")->execute([$salon_id]);
            $pdo->prepare("DELETE FROM reviews WHERE salon_id = ?")->execute([$salon_id]);
            
            // Delete salon
            $pdo->prepare("DELETE FROM salons WHERE id = ?")->execute([$salon_id]);
            
            $_SESSION['flash_success'] = 'Salon deleted successfully.';
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Error deleting salon: ' . $e->getMessage();
        }
    } else {
        $_SESSION['flash_error'] = 'Unauthorized action.';
    }
    
    header('Location: dashboard.php');
    exit;
}

// OPTIMIZED: Single query to get all statistics at once
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT s.id) as total_salons,
        COUNT(DISTINCT a.id) as total_appointments,
        COUNT(DISTINCT CASE WHEN a.status = 'pending' THEN a.id END) as pending_appointments,
        COUNT(DISTINCT CASE WHEN a.appointment_date = CURDATE() THEN a.id END) as today_appointments,
        COUNT(DISTINCT CASE WHEN a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) THEN a.id END) as monthly_appointments,
        COUNT(DISTINCT sr.id) as total_services,
        COUNT(DISTINCT r.id) as total_reviews,
        AVG(r.rating) as avg_rating,
        SUM(CASE WHEN a.status = 'completed' THEN sv.price ELSE 0 END) as total_revenue
    FROM salons s
    LEFT JOIN appointments a ON s.id = a.salon_id
    LEFT JOIN services sr ON s.id = sr.salon_id
    LEFT JOIN reviews r ON s.id = r.salon_id
    LEFT JOIN services sv ON a.service_id = sv.id
    WHERE s.owner_id = ?
");
$stmt->execute([$owner_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Extract statistics
$total_appointments = $stats['total_appointments'];
$pending_appointments = $stats['pending_appointments'];
$today_appointments = $stats['today_appointments'];
$monthly_appointments = $stats['monthly_appointments'];
$total_services = $stats['total_services'];
$total_reviews = $stats['total_reviews'];
$avg_rating = $stats['avg_rating'] ?? 0;
$total_revenue = $stats['total_revenue'] ?? 0;

// Fetch salons with additional stats
$stmt = $pdo->prepare("
    SELECT 
        s.id AS salon_id, 
        s.name, 
        s.address, 
        s.image,
        COUNT(DISTINCT sv.id) as services_count,
        COUNT(DISTINCT a.id) as appointments_count,
        AVG(r.rating) as avg_rating
    FROM salons s
    LEFT JOIN services sv ON s.id = sv.salon_id
    LEFT JOIN appointments a ON s.id = a.salon_id
    LEFT JOIN reviews r ON s.id = r.salon_id
    WHERE s.owner_id = ?
    GROUP BY s.id
");
$stmt->execute([$owner_id]);
$salons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent appointments (last 10)
$stmt = $pdo->prepare("
    SELECT 
        a.id AS appointment_id,
        s.name AS service_name,
        s.price,
        u.username AS customer_name,
        u.email AS customer_email,
        sa.name AS salon_name,
        a.appointment_date,
        a.appointment_time,
        a.status,
        DATEDIFF(a.appointment_date, CURDATE()) as days_until
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN users u ON a.user_id = u.id
    JOIN salons sa ON a.salon_id = sa.id
    WHERE sa.owner_id = ?
    ORDER BY a.created_at DESC
    LIMIT 10
");
$stmt->execute([$owner_id]);
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Upcoming appointments (next 7 days)
$stmt = $pdo->prepare("
    SELECT 
        a.id,
        s.name AS service_name,
        u.username AS customer_name,
        sa.name AS salon_name,
        a.appointment_date,
        a.appointment_time
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN users u ON a.user_id = u.id
    JOIN salons sa ON a.salon_id = sa.id
    WHERE sa.owner_id = ?
    AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND a.status != 'cancelled'
    ORDER BY a.appointment_date, a.appointment_time
    LIMIT 5
");
$stmt->execute([$owner_id]);
$upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Analytics: Appointment Trend (Last 7 Days)
$stmt = $pdo->prepare("
    SELECT DATE(a.appointment_date) as date, COUNT(*) as count
    FROM appointments a
    JOIN salons s ON a.salon_id = s.id
    WHERE s.owner_id = ?
    AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(a.appointment_date)
    ORDER BY date ASC
");
$stmt->execute([$owner_id]);
$chartAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Analytics: Status Breakdown
$stmt = $pdo->prepare("
    SELECT a.status, COUNT(*) as count
    FROM appointments a
    JOIN salons s ON a.salon_id = s.id
    WHERE s.owner_id = ?
    GROUP BY a.status
");
$stmt->execute([$owner_id]);
$statusData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top Services
$stmt = $pdo->prepare("
    SELECT 
        s.name,
        COUNT(a.id) as booking_count,
        sa.name as salon_name,
        s.price
    FROM services s
    JOIN salons sa ON s.salon_id = sa.id
    LEFT JOIN appointments a ON s.id = a.service_id
    WHERE sa.owner_id = ?
    GROUP BY s.id
    ORDER BY booking_count DESC
    LIMIT 5
");
$stmt->execute([$owner_id]);
$topServices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Owner Dashboard - Salonora";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-pink: #e91e63;
            --primary-purple: #9c27b0;
            --light-pink: #f8bbd0;
            --light-purple: #e1bee7;
            --dark-purple: #6a1b9a;
            --gradient-primary: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
            --gradient-light: linear-gradient(135deg, #f8bbd0 0%, #e1bee7 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #fce4ec 0%, #f3e5f5 100%);
            min-height: 100vh;
        }

        /* Welcome Header */
        .welcome-header {
            background: var(--gradient-primary);
            color: white;
            padding: 2.5rem 0;
            box-shadow: 0 8px 30px rgba(156, 39, 176, 0.3);
            margin-bottom: 2rem;
        }

        .welcome-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .welcome-text h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .welcome-text p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .welcome-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-action {
            background: white;
            color: var(--primary-purple);
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid white;
        }

        .btn-action:hover {
            background: transparent;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255,255,255,0.3);
        }

        .btn-logout {
            background: transparent;
            color: white;
            border-color: white;
        }

        .btn-logout:hover {
            background: rgba(255,255,255,0.2);
        }

        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .quick-stat-card {
            background: var(--gradient-primary);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(156, 39, 176, 0.25);
            transition: transform 0.3s;
            position: relative;
            overflow: hidden;
        }

        .quick-stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .quick-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(156, 39, 176, 0.35);
        }

        .quick-stat-card.variant-1 { background: linear-gradient(135deg, #c2185b 0%, #e91e63 100%); }
        .quick-stat-card.variant-2 { background: linear-gradient(135deg, #7b1fa2 0%, #9c27b0 100%); }
        .quick-stat-card.variant-3 { background: linear-gradient(135deg, #8e24aa 0%, #ba68c8 100%); }
        .quick-stat-card.variant-4 { background: linear-gradient(135deg, #ad1457 0%, #ec407a 100%); }

        .quick-stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .quick-stat-label {
            font-size: 1rem;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }

        /* Section Styles */
        .section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid var(--light-purple);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-purple);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--primary-pink);
        }

        .btn-add {
            background: var(--gradient-primary);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
        }

        .btn-add:hover {
            background: linear-gradient(135deg, #9c27b0 0%, #e91e63 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(156, 39, 176, 0.4);
            color: white;
        }

        /* Salons Grid */
        .salons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .salon-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .salon-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 30px rgba(156, 39, 176, 0.2);
            border-color: var(--light-purple);
        }

        .salon-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: var(--gradient-light);
        }

        .salon-body {
            padding: 1.5rem;
        }

        .salon-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark-purple);
            margin-bottom: 0.75rem;
        }

        .salon-address {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .salon-address i {
            color: var(--primary-pink);
        }

        .salon-stats {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--gradient-light);
            border-radius: 10px;
        }

        .salon-stat {
            text-align: center;
            flex: 1;
        }

        .salon-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-purple);
        }

        .salon-stat-label {
            font-size: 0.75rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .salon-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .btn-salon-action {
            padding: 0.65rem 1rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            text-align: center;
            transition: all 0.3s;
            border: 2px solid;
        }

        .btn-edit {
            background: var(--gradient-primary);
            color: white;
            border-color: transparent;
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #9c27b0 0%, #e91e63 100%);
            transform: translateY(-2px);
            color: white;
        }

        .btn-services {
            background: white;
            color: var(--primary-purple);
            border-color: var(--primary-purple);
        }

        .btn-services:hover {
            background: var(--primary-purple);
            color: white;
        }

        .btn-appointments {
            background: white;
            color: var(--primary-pink);
            border-color: var(--primary-pink);
        }

        .btn-appointments:hover {
            background: var(--primary-pink);
            color: white;
        }

        .btn-delete {
            background: white;
            color: #e74c3c;
            border-color: #e74c3c;
        }

        .btn-delete:hover {
            background: #e74c3c;
            color: white;
        }

        /* Upcoming Cards */
        .upcoming-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            border-left: 5px solid var(--primary-pink);
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }

        .upcoming-card:hover {
            transform: translateX(8px);
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.2);
        }

        /* Top Service Item */
        .top-service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--gradient-light);
            border-radius: 12px;
            margin-bottom: 0.75rem;
            transition: all 0.3s;
        }

        .top-service-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(156, 39, 176, 0.15);
        }

        /* Table Styles */
        .appointments-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 0.5rem;
        }

        .appointments-table thead th {
            background: var(--gradient-primary);
            color: white;
            padding: 1rem;
            font-weight: 600;
            text-align: left;
            border: none;
        }

        .appointments-table thead th:first-child {
            border-radius: 10px 0 0 10px;
        }

        .appointments-table thead th:last-child {
            border-radius: 0 10px 10px 0;
        }

        .appointments-table tbody tr {
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .appointments-table tbody tr:hover {
            transform: scale(1.01);
            box-shadow: 0 4px 15px rgba(156, 39, 176, 0.15);
        }

        .appointments-table tbody td {
            padding: 1rem;
            border: none;
        }

        .appointments-table tbody td:first-child {
            border-radius: 10px 0 0 10px;
        }

        .appointments-table tbody td:last-child {
            border-radius: 0 10px 10px 0;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.pending {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
        }

        .status-badge.confirmed {
            background: linear-gradient(135deg, #c2185b 0%, #e91e63 100%);
            color: white;
        }

        .status-badge.completed {
            background: linear-gradient(135deg, #7b1fa2 0%, #9c27b0 100%);
            color: white;
        }

        .status-badge.cancelled {
            background: linear-gradient(135deg, #9575cd 0%, #b39ddb 100%);
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #999;
        }

        .empty-state i {
            font-size: 4rem;
            background: var(--gradient-light);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }

        /* Charts */
        .chart-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .chart-title {
            color: var(--dark-purple);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        /* Flash Messages */
        .flash-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            background: var(--gradient-primary);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        @media (max-width: 768px) {
            .welcome-content {
                flex-direction: column;
                text-align: center;
            }

            .quick-stats {
                grid-template-columns: 1fr;
            }

            .salons-grid {
                grid-template-columns: 1fr;
            }

            .salon-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<!-- Flash Messages -->
<?php if (isset($_SESSION['flash_success'])): ?>
    <div class="flash-message">
        <div class="alert alert-success alert-dismissible fade show" role="alert" style="background: linear-gradient(135deg, #c2185b 0%, #e91e63 100%); border: none; color: white;">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($_SESSION['flash_success']) ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
    <div class="flash-message">
        <div class="alert alert-danger alert-dismissible fade show" role="alert" style="background: linear-gradient(135deg, #9575cd 0%, #b39ddb 100%); border: none; color: white;">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($_SESSION['flash_error']) ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<!-- Welcome Header -->
<div class="welcome-header">
    <div class="container">
        <div class="welcome-content">
            <div class="welcome-text">
                <h1>Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>! 👋</h1>
                <p>Here's what's happening with your business today</p>
            </div>
            <div class="welcome-actions">
                <a href="salon_add.php" class="btn-action">
                    <i class="fas fa-plus-circle me-2"></i>Add Salon
                </a>
                <a href="appointments.php" class="btn-action">
                    <i class="fas fa-calendar-check me-2"></i>Manage Bookings
                </a>
                <a href="../logout.php" class="btn-action btn-logout">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">

    <!-- Quick Stats -->
    <div class="quick-stats mt-4">
        <div class="quick-stat-card">
            <div class="quick-stat-value">
                <i class="fas fa-calendar-day me-2"></i><?= $today_appointments ?>
            </div>
            <div class="quick-stat-label">Today's Appointments</div>
        </div>
        <div class="quick-stat-card variant-1">
            <div class="quick-stat-value">
                <i class="fas fa-clock me-2"></i><?= $pending_appointments ?>
            </div>
            <div class="quick-stat-label">Pending Approvals</div>
        </div>
        <div class="quick-stat-card variant-2">
            <div class="quick-stat-value">
                <?= number_format($avg_rating, 1) ?> <i class="fas fa-star"></i>
            </div>
            <div class="quick-stat-label">Average Rating</div>
        </div>
        <div class="quick-stat-card variant-3">
            <div class="quick-stat-value">
                <i class="fas fa-dollar-sign"></i><?= number_format($total_revenue, 0) ?>
            </div>
            <div class="quick-stat-label">Total Revenue</div>
        </div>
    </div>

    <!-- Upcoming Appointments & Top Services -->
    <div class="row mt-4">
        <div class="col-lg-6 mb-4">
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-calendar-check"></i>Upcoming Appointments
                </h2>
                <?php if (empty($upcoming)): ?>
                    <div class="empty-state">
                        <i class="far fa-calendar"></i>
                        <p>No upcoming appointments</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($upcoming as $apt): ?>
                        <div class="upcoming-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1" style="color: var(--dark-purple); font-weight: 600;">
                                        <i class="fas fa-user-circle me-2"></i><?= htmlspecialchars($apt['customer_name']) ?>
                                    </h6>
                                    <p class="mb-1 text-muted small">
                                        <i class="fas fa-cut"></i> <?= htmlspecialchars($apt['service_name']) ?>
                                    </p>
                                    <p class="mb-0 text-muted small">
                                        <i class="fas fa-store"></i> <?= htmlspecialchars($apt['salon_name']) ?>
                                    </p>
                                </div>
                                <div class="text-end">
                                    <div class="badge" style="background: var(--gradient-primary);">
                                        <?= date('M d', strtotime($apt['appointment_date'])) ?>
                                    </div>
                                    <div class="small mt-1" style="color: var(--primary-purple); font-weight: 600;">
                                        <?= date('h:i A', strtotime($apt['appointment_time'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-fire"></i>Top Services
                </h2>
                <?php if (empty($topServices)): ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-line"></i>
                        <p>No service data yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($topServices as $idx => $service): ?>
                        <div class="top-service-item">
                            <div>
                                <span class="badge me-2" style="background: var(--gradient-primary);">#<?= $idx + 1 ?></span>
                                <strong style="color: var(--dark-purple);"><?= htmlspecialchars($service['name']) ?></strong>
                                <div class="small text-muted"><?= htmlspecialchars($service['salon_name']) ?></div>
                            </div>
                            <div class="text-end">
                                <div class="badge" style="background: linear-gradient(135deg, #7b1fa2 0%, #9c27b0 100%);">
                                    <?= $service['booking_count'] ?> bookings
                                </div>
                                <div class="small text-muted mt-1">Rs <?= number_format($service['price'], 2) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ANALYTICS SECTION -->
    <div class="section mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="section-title mb-0">
                <i class="fa-solid fa-chart-line"></i>Analytics Overview
            </h2>
            <button class="btn btn-sm" style="background: var(--gradient-primary); color: white; border: none;" type="button" data-bs-toggle="collapse" data-bs-target="#analyticsSection">
                <i class="fas fa-chart-bar"></i> Toggle Charts
            </button>
        </div>

        <div class="collapse show" id="analyticsSection">
            <div class="row mt-3">
                <div class="col-lg-8 mb-4">
                    <div class="chart-card">
                        <h5 class="chart-title">Appointment Trends - Last 7 Days</h5>
                        <canvas id="appointmentsChart" height="80"></canvas>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="chart-card">
                        <h5 class="chart-title">Status Breakdown</h5>
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Salons Section -->
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-store"></i>Your Salons (<?= count($salons) ?>)
            </h2>
            <a href="salon_add.php" class="btn-add">
                <i class="fas fa-plus"></i> Add New Salon
            </a>
        </div>

        <?php if (empty($salons)): ?>
            <div class="empty-state">
                <i class="fas fa-store-slash"></i>
                <h4 style="color: var(--dark-purple);">No Salons Yet</h4>
                <p>Add your first salon to get started with bookings.</p>
                <a href="salon_add.php" class="btn-add mt-3">
                    <i class="fas fa-plus"></i> Add Salon
                </a>
            </div>
        <?php else: ?>
            <div class="salons-grid">
                <?php foreach ($salons as $salon): ?>
                    <div class="salon-card">
                        <?php if ($salon['image']): ?>
                            <img src="../../<?= htmlspecialchars($salon['image']) ?>" class="salon-image" alt="<?= htmlspecialchars($salon['name']) ?>">
                        <?php else: ?>
                            <div class="salon-image"></div>
                        <?php endif; ?>
                        
                        <div class="salon-body">
                            <h3 class="salon-name"><?= htmlspecialchars($salon['name']) ?></h3>
                            <p class="salon-address">
                                <i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($salon['address']) ?>
                            </p>

                            <!-- Salon Stats -->
                            <div class="salon-stats">
                                <div class="salon-stat">
                                    <div class="salon-stat-value"><?= $salon['services_count'] ?></div>
                                    <div class="salon-stat-label">Services</div>
                                </div>
                                <div class="salon-stat">
                                    <div class="salon-stat-value"><?= $salon['appointments_count'] ?></div>
                                    <div class="salon-stat-label">Bookings</div>
                                </div>
                                <div class="salon-stat">
                                    <div class="salon-stat-value">
                                        <?= $salon['avg_rating'] ? number_format($salon['avg_rating'], 1) : 'N/A' ?>
                                        <?php if ($salon['avg_rating']): ?>
                                            <i class="fas fa-star" style="font-size: 0.8rem; color: #fbbf24;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="salon-stat-label">Rating</div>
                                </div>
                            </div>

                            <div class="salon-actions">
                                <a href="salon_edit.php?id=<?= $salon['salon_id'] ?>" class="btn-salon-action btn-edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="services.php?salon_id=<?= $salon['salon_id'] ?>" class="btn-salon-action btn-services">
                                    <i class="fas fa-list"></i> Services
                                </a>
                                <a href="appointments.php?salon_id=<?= $salon['salon_id'] ?>" class="btn-salon-action btn-appointments">
                                    <i class="fas fa-calendar"></i> Bookings
                                </a>
                                <button type="button" class="btn-salon-action btn-delete" 
                                        onclick="confirmDelete(<?= $salon['salon_id'] ?>, '<?= htmlspecialchars($salon['name'], ENT_QUOTES) ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Appointments -->
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-clock"></i>Recent Appointments
            </h2>
            <a href="appointments.php" class="btn-add">
                <i class="fas fa-eye"></i> View All
            </a>
        </div>

        <?php if (empty($recent)): ?>
            <div class="empty-state">
                <i class="far fa-calendar-times"></i>
                <h4 style="color: var(--dark-purple);">No Appointments Yet</h4>
                <p>Your appointment history will appear here.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Salon</th>
                            <th>Service</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $a): ?>
                            <tr>
                                <td><strong style="color: var(--primary-purple);">#<?= $a['appointment_id'] ?></strong></td>
                                <td><?= htmlspecialchars($a['salon_name']) ?></td>
                                <td><strong><?= htmlspecialchars($a['service_name']) ?></strong></td>
                                <td>
                                    <i class="fas fa-user-circle me-1" style="color: var(--primary-pink);"></i>
                                    <?= htmlspecialchars($a['customer_name']) ?>
                                    <div class="small text-muted"><?= htmlspecialchars($a['customer_email']) ?></div>
                                </td>
                                <td>
                                    <?= date('M d, Y', strtotime($a['appointment_date'])) ?>
                                    <?php if ($a['days_until'] >= 0 && $a['days_until'] <= 3): ?>
                                        <div class="badge" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                            In <?= $a['days_until'] ?> days
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= date('h:i A', strtotime($a['appointment_time'])) ?></strong></td>
                                <td><strong style="color: var(--primary-pink);">Rs <?= number_format($a['price'], 2) ?></strong></td>
                                <td><span class="status-badge <?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
                                <td>
                                    <a href="appointments.php?id=<?= $a['appointment_id'] ?>" class="btn btn-sm" style="background: var(--gradient-primary); color: white; border: none;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="salonNameToDelete"></strong>?</p>
                <div class="alert" style="background: var(--gradient-light); border: none;">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Warning:</strong> This will also delete all associated services, appointments, and reviews. This action cannot be undone.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" id="deleteForm" style="display: inline;">
                    <input type="hidden" name="delete_salon" value="1">
                    <input type="hidden" name="salon_id" id="salonIdToDelete">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Yes, Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Delete confirmation
function confirmDelete(salonId, salonName) {
    document.getElementById('salonIdToDelete').value = salonId;
    document.getElementById('salonNameToDelete').textContent = salonName;
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

// Initialize charts
let chartsInitialized = false;

function initCharts() {
    if (chartsInitialized) return;
    
    // Appointments Chart
    const appointmentLabels = <?= json_encode(array_column($chartAppointments, 'date')) ?>;
    const appointmentCounts = <?= json_encode(array_column($chartAppointments, 'count')) ?>;

    const appointmentsCanvas = document.getElementById('appointmentsChart');
    if (appointmentsCanvas) {
        new Chart(appointmentsCanvas, {
            type: 'line',
            data: {
                labels: appointmentLabels,
                datasets: [{
                    label: 'Appointments',
                    data: appointmentCounts,
                    borderColor: '#e91e63',
                    backgroundColor: 'rgba(233, 30, 99, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#e91e63',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    // Status Chart
    const statusLabels = <?= json_encode(array_column($statusData, 'status')) ?>;
    const statusCounts = <?= json_encode(array_column($statusData, 'count')) ?>;

    const statusColors = {
        'pending': '#ff6b9d',
        'confirmed': '#c2185b',
        'completed': '#7b1fa2',
        'cancelled': '#9575cd'
    };

    const backgroundColors = statusLabels.map(status => statusColors[status] || '#6b7280');

    const statusCanvas = document.getElementById('statusChart');
    if (statusCanvas) {
        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: statusLabels.map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                datasets: [{
                    data: statusCounts,
                    backgroundColor: backgroundColors,
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12,
                                family: 'Poppins'
                            }
                        }
                    }
                }
            }
        });
    }
    
    chartsInitialized = true;
}

// Initialize charts on page load
document.addEventListener('DOMContentLoaded', function() {
    initCharts();
});

// Listen for collapse show event
const analyticsSection = document.getElementById('analyticsSection');
if (analyticsSection) {
    analyticsSection.addEventListener('shown.bs.collapse', function () {
        initCharts();
    });
}

// Auto-dismiss flash messages
setTimeout(function() {
    const alerts = document.querySelectorAll('.flash-message .alert');
    alerts.forEach(alert => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
        bsAlert.close();
    });
}, 5000);

// Notification for pending appointments
<?php if ($pending_appointments > 0): ?>
setTimeout(() => {
    console.log('You have <?= $pending_appointments ?> pending appointments waiting for approval');
}, 2000);
<?php endif; ?>
</script>

</body>
</html>