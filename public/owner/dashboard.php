<?php
// public/owner/dashboard.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];

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

// Fetch salons (simplified query)
$stmt = $pdo->prepare("SELECT id AS salon_id, name, address, image FROM salons WHERE owner_id = ?");
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

// Top Services (simplified)
$stmt = $pdo->prepare("
    SELECT 
        s.name,
        COUNT(a.id) as booking_count,
        sa.name as salon_name
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
    <link rel="stylesheet" href="/salonora/public/assets/css/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/salonora/public/assets/css/footer.css">
    <style>
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .quick-stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .quick-stat-card.success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .quick-stat-card.warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .quick-stat-card.info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        
        .quick-stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .quick-stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .upcoming-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-left: 4px solid #667eea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        
        .upcoming-card:hover {
            transform: translateX(5px);
        }
        
        .top-service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        
        .stats-comparison {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        
        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }
    </style>
</head>
<body>

<!-- Welcome Header -->
<div class="welcome-header">
    <div class="container">
        <div class="welcome-content">
            <div class="welcome-text">
                <h1>Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>! 👋</h1>
                <p>Here's what's happening with your business today</p>
            </div>
            <div class="welcome-actions">
                <a href="salon_add.php" class="btn-action"><i class="fas fa-plus-circle"></i> Add Salon</a>
                <a href="appointments.php" class="btn-action"><i class="fas fa-calendar-check"></i> Manage Bookings</a>
                <a href="../logout.php" class="btn-action btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">

    <!-- Quick Stats -->
    <div class="quick-stats mt-4">
        <div class="quick-stat-card">
            <div class="quick-stat-value"><?= $today_appointments ?></div>
            <div class="quick-stat-label"><i class="fas fa-calendar-day"></i> Today's Appointments</div>
        </div>
        <div class="quick-stat-card success">
            <div class="quick-stat-value"><?= $pending_appointments ?></div>
            <div class="quick-stat-label"><i class="fas fa-clock"></i> Pending Approvals</div>
        </div>
        <div class="quick-stat-card warning">
            <div class="quick-stat-value"><?= number_format($avg_rating, 1) ?> <i class="fas fa-star" style="font-size: 1.2rem;"></i></div>
            <div class="quick-stat-label">Average Rating</div>
        </div>
        <div class="quick-stat-card info">
            <div class="quick-stat-value">$<?= number_format($total_revenue, 0) ?></div>
            <div class="quick-stat-label"><i class="fas fa-dollar-sign"></i> Total Revenue</div>
        </div>
    </div>

    <!-- Main Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon salons"><i class="fas fa-store"></i></div>
            <div class="stat-info">
                <h3><?= count($salons) ?></h3>
                <p>Total Salons</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon appointments"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-info">
                <h3><?= $total_appointments ?></h3>
                <p>Total Appointments</p>
                <div class="stats-comparison">
                    <span class="trend-up"><i class="fas fa-arrow-up"></i> <?= $monthly_appointments ?></span>
                    <span>this month</span>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon services"><i class="fas fa-concierge-bell"></i></div>
            <div class="stat-info">
                <h3><?= $total_services ?></h3>
                <p>Total Services</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon reviews"><i class="fas fa-star"></i></div>
            <div class="stat-info">
                <h3><?= $total_reviews ?></h3>
                <p>Customer Reviews</p>
            </div>
        </div>
    </div>

    <!-- Upcoming Appointments & Top Services -->
    <div class="row mt-4">
        <div class="col-lg-6 mb-4">
            <div class="section">
                <h2 class="section-title"><i class="fas fa-calendar-check"></i> Upcoming Appointments</h2>
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
                                    <h6 class="mb-1"><?= htmlspecialchars($apt['customer_name']) ?></h6>
                                    <p class="mb-1 text-muted small">
                                        <i class="fas fa-cut"></i> <?= htmlspecialchars($apt['service_name']) ?>
                                    </p>
                                    <p class="mb-0 text-muted small">
                                        <i class="fas fa-store"></i> <?= htmlspecialchars($apt['salon_name']) ?>
                                    </p>
                                </div>
                                <div class="text-end">
                                    <div class="badge bg-primary"><?= date('M d', strtotime($apt['appointment_date'])) ?></div>
                                    <div class="small mt-1"><?= date('h:i A', strtotime($apt['appointment_time'])) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="section">
                <h2 class="section-title"><i class="fas fa-fire"></i> Top Services</h2>
                <?php if (empty($topServices)): ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-line"></i>
                        <p>No service data yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($topServices as $idx => $service): ?>
                        <div class="top-service-item">
                            <div>
                                <span class="badge bg-secondary me-2">#<?= $idx + 1 ?></span>
                                <strong><?= htmlspecialchars($service['name']) ?></strong>
                                <div class="small text-muted"><?= htmlspecialchars($service['salon_name']) ?></div>
                            </div>
                            <div class="text-end">
                                <div class="badge bg-success"><?= $service['booking_count'] ?> bookings</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ANALYTICS SECTION (Collapsible) -->
    <div class="section mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="section-title mb-0"><i class="fa-solid fa-chart-line"></i> Analytics Overview</h2>
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#analyticsSection">
                <i class="fas fa-chart-bar"></i> Toggle Charts
            </button>
        </div>

        <div class="collapse" id="analyticsSection">
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
            <h2 class="section-title"><i class="fas fa-store"></i> Your Salons</h2>
            <a href="salon_add.php" class="btn-add"><i class="fas fa-plus"></i> Add New Salon</a>
        </div>

        <?php if (empty($salons)): ?>
            <div class="empty-state">
                <i class="fas fa-store-slash"></i>
                <h4>No Salons Yet</h4>
                <p>Add your first salon to get started.</p>
                <a href="salon_add.php" class="btn-add mt-3"><i class="fas fa-plus"></i> Add Salon</a>
            </div>
        <?php else: ?>
            <div class="salons-grid">
                <?php foreach ($salons as $salon): ?>
                    <div class="salon-card">
                        <?php if ($salon['image']): ?>
                            <img src="../../<?= htmlspecialchars($salon['image']) ?>" class="salon-image" alt="">
                        <?php else: ?>
                            <div class="salon-image"></div>
                        <?php endif; ?>
                        
                        <div class="salon-body">
                            <h3 class="salon-name"><?= htmlspecialchars($salon['name']) ?></h3>
                            <p class="salon-address"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($salon['address']) ?></p>

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
            <h2 class="section-title"><i class="fas fa-clock"></i> Recent Appointments</h2>
            <a href="appointments.php" class="btn-add"><i class="fas fa-eye"></i> View All</a>
        </div>

        <?php if (empty($recent)): ?>
            <div class="empty-state">
                <i class="far fa-calendar-times"></i>
                <h4>No Appointments Yet</h4>
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
                                <td><strong>#<?= $a['appointment_id'] ?></strong></td>
                                <td><?= htmlspecialchars($a['salon_name']) ?></td>
                                <td><?= htmlspecialchars($a['service_name']) ?></td>
                                <td>
                                    <i class="fas fa-user-circle me-1"></i>
                                    <?= htmlspecialchars($a['customer_name']) ?>
                                    <div class="small text-muted"><?= htmlspecialchars($a['customer_email']) ?></div>
                                </td>
                                <td>
                                    <?= date('M d, Y', strtotime($a['appointment_date'])) ?>
                                    <?php if ($a['days_until'] >= 0 && $a['days_until'] <= 3): ?>
                                        <div class="badge bg-info">In <?= $a['days_until'] ?> days</div>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('h:i A', strtotime($a['appointment_time'])) ?></td>
                                <td><strong>$<?= number_format($a['price'], 2) ?></strong></td>
                                <td><span class="status-badge <?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
                                <td>
                                    <a href="appointments.php?id=<?= $a['appointment_id'] ?>" class="btn btn-sm btn-outline-primary">
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Initialize charts only when analytics section is shown
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
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
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
        'pending': '#fbbf24',
        'confirmed': '#3b82f6',
        'completed': '#10b981',
        'cancelled': '#ef4444'
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
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    chartsInitialized = true;
}

// Listen for collapse show event
const analyticsSection = document.getElementById('analyticsSection');
if (analyticsSection) {
    analyticsSection.addEventListener('shown.bs.collapse', function () {
        initCharts();
    });
}

// Auto-refresh notification for pending appointments
<?php if ($pending_appointments > 0): ?>
setTimeout(() => {
    console.log('You have <?= $pending_appointments ?> pending appointments');
}, 2000);
<?php endif; ?>
</script>

<?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>