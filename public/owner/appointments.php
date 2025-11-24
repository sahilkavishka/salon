<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

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
    echo "<!DOCTYPE html><html><head><title>No Salons</title>";
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"></head>';
    echo '<body><div class="container mt-5"><div class="alert" style="background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%); color: white; border: none;">';
    echo '<h4><i class="fas fa-info-circle me-2"></i>No Salons Registered Yet</h4>';
    echo '<p>Please register a salon first to manage appointments.</p>';
    echo '<a href="salons.php" class="btn btn-light">Register Salon</a>';
    echo '</div></div></body></html>';
    exit;
}

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$nonce = bin2hex(random_bytes(16));

// CSP: allow only scripts with this nonce + CDNs (no inline onclick)
header("Content-Security-Policy: script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; connect-src 'self';");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Owner Appointments - Salonora</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

body { 
    font-family: 'Poppins', sans-serif; 
    background: linear-gradient(135deg, #fce4ec 0%, #f3e5f5 100%);
    min-height: 100vh;
}

.page-header {
    background: var(--gradient-primary);
    color: white;
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
    box-shadow: 0 8px 20px rgba(233, 30, 99, 0.3);
}

.page-header h2 {
    margin: 0;
    font-weight: 600;
}

.btn-gradient {
    background: var(--gradient-primary);
    border: none;
    color: white;
    transition: all 0.3s;
}

.btn-gradient:hover {
    background: linear-gradient(135deg, #9c27b0 0%, #e91e63 100%);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(156, 39, 176, 0.4);
    color: white;
}

.btn-outline-pink {
    border: 2px solid var(--primary-pink);
    color: var(--primary-pink);
    background: white;
    transition: all 0.3s;
}

.btn-outline-pink:hover {
    background: var(--primary-pink);
    color: white;
    transform: translateY(-2px);
}

.btn-outline-purple {
    border: 2px solid var(--primary-purple);
    color: var(--primary-purple);
    background: white;
    transition: all 0.3s;
}

.btn-outline-purple:hover {
    background: var(--primary-purple);
    color: white;
    transform: translateY(-2px);
}

.appointment-card { 
    background: white; 
    padding: 1.5rem; 
    border-radius: 15px; 
    margin-bottom: 1.25rem; 
    border-left: 5px solid;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}

.appointment-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 150px;
    height: 150px;
    background: var(--gradient-light);
    opacity: 0.1;
    border-radius: 50%;
    transform: translate(50%, -50%);
}

.appointment-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(156, 39, 176, 0.15);
}

.appointment-card.pending { 
    border-left-color: #ff6b9d;
}
.appointment-card.confirmed { 
    border-left-color: #c2185b;
}
.appointment-card.completed { 
    border-left-color: #7b1fa2;
}
.appointment-card.cancelled { 
    border-left-color: #b39ddb;
}

.status-badge { 
    padding: 0.4rem 0.8rem; 
    border-radius: 50px; 
    font-weight: 600; 
    font-size: 0.75rem; 
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.pending { 
    color: #ff6b9d; 
    background: linear-gradient(135deg, #ff6b9d20, #ff6b9d30);
    border: 1px solid #ff6b9d40;
}
.status-badge.confirmed { 
    color: #c2185b; 
    background: linear-gradient(135deg, #c2185b20, #c2185b30);
    border: 1px solid #c2185b40;
}
.status-badge.completed { 
    color: #7b1fa2; 
    background: linear-gradient(135deg, #7b1fa220, #7b1fa230);
    border: 1px solid #7b1fa240;
}
.status-badge.cancelled { 
    color: #9575cd; 
    background: linear-gradient(135deg, #9575cd20, #9575cd30);
    border: 1px solid #9575cd40;
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(156, 39, 176, 0.7);
    backdrop-filter: blur(5px);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.loading-overlay.show {
    display: flex;
}

.spinner-border {
    width: 3rem;
    height: 3rem;
    border-color: var(--light-pink);
    border-right-color: transparent;
}

.alert-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9998;
    max-width: 400px;
}

.appointment-info {
    font-size: 0.95rem;
    color: #666;
    line-height: 1.8;
}

.appointment-info i {
    margin-right: 0.75rem;
    color: var(--primary-purple);
    width: 20px;
    text-align: center;
}

.appointment-info .price {
    background: var(--gradient-primary);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 700;
    font-size: 1.1rem;
}

.nav-tabs {
    border-bottom: 3px solid var(--light-purple);
    background: white;
    padding: 0.5rem 1rem;
    border-radius: 15px 15px 0 0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.nav-tabs .nav-link {
    border: none;
    color: #666;
    font-weight: 500;
    padding: 0.75rem 1.5rem;
    transition: all 0.3s;
    border-radius: 10px;
    margin-right: 0.5rem;
}

.nav-tabs .nav-link:hover {
    background: var(--gradient-light);
    color: var(--dark-purple);
}

.nav-tabs .nav-link.active {
    background: var(--gradient-primary);
    color: white;
    box-shadow: 0 4px 10px rgba(156, 39, 176, 0.3);
}

.nav-tabs .badge {
    font-weight: 600;
}

.tab-content {
    background: white;
    padding: 2rem;
    border-radius: 0 0 15px 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    min-height: 400px;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
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

.action-buttons .btn {
    font-size: 0.85rem;
    padding: 0.4rem 1rem;
    border-radius: 50px;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-confirm {
    background: linear-gradient(135deg, #c2185b 0%, #e91e63 100%);
    border: none;
    color: white;
}

.btn-confirm:hover {
    background: linear-gradient(135deg, #e91e63 0%, #c2185b 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(194, 24, 91, 0.3);
    color: white;
}

.btn-complete {
    background: linear-gradient(135deg, #7b1fa2 0%, #9c27b0 100%);
    border: none;
    color: white;
}

.btn-complete:hover {
    background: linear-gradient(135deg, #9c27b0 0%, #7b1fa2 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(123, 31, 162, 0.3);
    color: white;
}

.btn-reject {
    background: linear-gradient(135deg, #9575cd 0%, #b39ddb 100%);
    border: none;
    color: white;
}

.btn-reject:hover {
    background: linear-gradient(135deg, #b39ddb 0%, #9575cd 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(149, 117, 205, 0.3);
    color: white;
}

.appointment-card h5 {
    font-weight: 600;
    color: var(--dark-purple);
    margin-bottom: 1rem;
}

.stats-card {
    background: white;
    padding: 1.5rem;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

.stats-row {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.stat-item {
    flex: 1;
    min-width: 150px;
    padding: 1rem;
    background: var(--gradient-light);
    border-radius: 10px;
    text-align: center;
}

.stat-item .stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--dark-purple);
}

.stat-item .stat-label {
    font-size: 0.9rem;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

@media (max-width: 768px) {
    .page-header {
        padding: 1.5rem;
    }
    
    .appointment-card {
        padding: 1rem;
    }
    
    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .action-buttons .btn {
        width: 100%;
    }
}
</style>
</head>
<body>

<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner-border text-light" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

<div class="alert-container" id="alertContainer"></div>

<div class="container py-4">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2><i class="fas fa-calendar-check me-2"></i>Appointments Management</h2>
                <p class="mb-0 mt-2" style="opacity: 0.9;">Manage and track all your salon appointments</p>
            </div>
            <div class="mt-3 mt-md-0">
                <!-- NO inline JS here -->
                <button class="btn btn-light me-2" id="backBtn">
                    <i class="fas fa-arrow-left me-1"></i>Back
                </button>
                <button class="btn btn-light" id="refreshBtn">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-card">
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-number" id="total-pending">0</div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="total-confirmed">0</div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="total-completed">0</div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="total-cancelled">0</div>
                <div class="stat-label">Cancelled</div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs" id="appointmentTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" 
                    data-bs-target="#pending" type="button" role="tab">
                <i class="fas fa-clock me-2"></i>Pending 
                <span class="badge bg-light text-dark ms-1" id="pending-count">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="confirmed-tab" data-bs-toggle="tab" 
                    data-bs-target="#confirmed" type="button" role="tab">
                <i class="fas fa-check-circle me-2"></i>Confirmed 
                <span class="badge bg-light text-dark ms-1" id="confirmed-count">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="completed-tab" data-bs-toggle="tab" 
                    data-bs-target="#completed" type="button" role="tab">
                <i class="fas fa-check-double me-2"></i>Completed 
                <span class="badge bg-light text-dark ms-1" id="completed-count">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="cancelled-tab" data-bs-toggle="tab" 
                    data-bs-target="#cancelled" type="button" role="tab">
                <i class="fas fa-ban me-2"></i>Cancelled 
                <span class="badge bg-light text-dark ms-1" id="cancelled-count">0</span>
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="pending">
            <div class="text-center py-5">
                <div class="spinner-border" style="color: var(--primary-purple);" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="confirmed"></div>
        <div class="tab-pane fade" id="completed"></div>
        <div class="tab-pane fade" id="cancelled"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script nonce="<?= $nonce ?>">
const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;

// 🔹 Wire up Back and Refresh buttons here
document.addEventListener('DOMContentLoaded', () => {
    const backBtn = document.getElementById('backBtn');
    const refreshBtn = document.getElementById('refreshBtn');

    if (backBtn) {
        backBtn.addEventListener('click', () => {
            // 🔴 CHANGE THIS PATH IF NEEDED
            window.location.href = 'dashboard.php';
        });
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            fetchAppointments(true);
        });
    }
});

// Utility function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Show alert message
function showAlert(message, type = 'success') {
    const alertContainer = document.getElementById('alertContainer');
    const alertId = 'alert-' + Date.now();
    
    const alertColors = {
        success: 'linear-gradient(135deg, #c2185b 0%, #e91e63 100%)',
        danger: 'linear-gradient(135deg, #9575cd 0%, #b39ddb 100%)',
        info: 'linear-gradient(135deg, #7b1fa2 0%, #9c27b0 100%)'
    };
    
    const alertHtml = `
        <div class="alert alert-dismissible fade show" role="alert" id="${alertId}" 
             style="background: ${alertColors[type] || alertColors.success}; color: white; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
            <strong><i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i></strong>
            ${escapeHtml(message)}
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    `;
    alertContainer.insertAdjacentHTML('beforeend', alertHtml);
    
    setTimeout(() => {
        const alert = document.getElementById(alertId);
        if (alert) {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }
    }, 5000);
}

// Show/hide loading overlay
function toggleLoading(show) {
    const overlay = document.getElementById('loadingOverlay');
    if (show) {
        overlay.classList.add('show');
    } else {
        overlay.classList.remove('show');
    }
}

// Format date and time
function formatDateTime(date, time) {
    const dateObj = new Date(date + ' ' + time);
    const options = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return dateObj.toLocaleDateString('en-US', options);
}

// Fetch appointments
async function fetchAppointments(showMessage = false) {
    try {
        if (showMessage) toggleLoading(true);
        
        // 🔴 Ensure path is correct: this assumes fetch_appointments.php is in same folder
        const res = await fetch('fetch_appointments.php', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!res.ok) {
            throw new Error('Failed to fetch appointments');
        }
        
        const data = await res.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        // Update statistics
        document.getElementById('total-pending').textContent = (data.pending || []).length;
        document.getElementById('total-confirmed').textContent = (data.confirmed || []).length;
        document.getElementById('total-completed').textContent = (data.completed || []).length;
        document.getElementById('total-cancelled').textContent = (data.cancelled || []).length;
        
        // Render appointments for each status
        ['pending', 'confirmed', 'completed', 'cancelled'].forEach(status => {
            renderAppointments(status, data[status] || []);
        });
        
        if (showMessage) {
            showAlert('Appointments refreshed successfully!', 'success');
        }
    } catch (error) {
        console.error('Error fetching appointments:', error);
        showAlert(error.message || 'Failed to load appointments', 'danger');
    } finally {
        if (showMessage) toggleLoading(false);
    }
}

// Render appointments for a specific status
function renderAppointments(status, appointments) {
    const container = document.getElementById(status);
    const countBadge = document.getElementById(status + '-count');
    
    if (!container) return;
    
    // Update count badge
    if (countBadge) {
        countBadge.textContent = appointments.length;
    }
    
    // Render appointments
    if (appointments.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h5 class="mt-3">No ${status} appointments</h5>
                <p class="text-muted">You don't have any ${status} appointments at the moment.</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = appointments.map(apt => {
        let buttons = '';
        
        if (status === 'pending') {
            buttons = `
                <div class="action-buttons">
                    <button class="btn btn-confirm btn-sm me-2" onclick="handleAction(${apt.id}, 'confirm')" title="Confirm appointment">
                        <i class="fas fa-check me-1"></i>Confirm
                    </button>
                    <button class="btn btn-reject btn-sm" onclick="handleAction(${apt.id}, 'reject')" title="Reject appointment">
                        <i class="fas fa-times me-1"></i>Reject
                    </button>
                </div>
            `;
        } else if (status === 'confirmed') {
            buttons = `
                <div class="action-buttons">
                    <button class="btn btn-complete btn-sm" onclick="handleAction(${apt.id}, 'complete')" title="Mark as completed">
                        <i class="fas fa-check-double me-1"></i>Mark Complete
                    </button>
                </div>
            `;
        }
        
        return `
            <div class="appointment-card ${status}" id="apt-${apt.id}">
                <div class="row align-items-start">
                    <div class="col-lg-9 col-md-8">
                        <h5>
                            <i class="fas fa-user-circle me-2"></i>${escapeHtml(apt.user_name)} 
                            <span class="status-badge ${status}">${status}</span>
                        </h5>
                        <div class="appointment-info">
                            <div class="mb-2">
                                <i class="fas fa-cut"></i>
                                <strong>${escapeHtml(apt.service_name)}</strong>
                                <span class="price ms-2">Rs ${parseFloat(apt.service_price).toFixed(2)}</span>
                            </div>
                            <div class="mb-2">
                                <i class="fas fa-store"></i>${escapeHtml(apt.salon_name)}
                            </div>
                            <div>
                                <i class="fas fa-calendar-alt"></i>${formatDateTime(apt.appointment_date, apt.appointment_time)}
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-4 text-end mt-3 mt-md-0">
                        ${buttons}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Handle appointment actions
async function handleAction(id, action) {
    const actionMessages = {
        reject: 'Are you sure you want to reject this appointment? This action cannot be undone.',
        complete: 'Mark this appointment as completed?',
        confirm: 'Confirm this appointment?'
    };
    
    if (actionMessages[action] && !confirm(actionMessages[action])) {
        return;
    }
    
    try {
        toggleLoading(true);
        
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('appointment_id', id);
        fd.append('action', action);
        
        const res = await fetch('appointment_action.php', {
            method: 'POST',
            body: fd,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!res.ok) {
            throw new Error('Request failed');
        }
        
        const data = await res.json();
        
        if (data.success) {
            showAlert(data.message || 'Action completed successfully!', 'success');
            await fetchAppointments();
        } else {
            throw new Error(data.error || 'Action failed');
        }
    } catch (error) {
        console.error('Error handling action:', error);
        showAlert(error.message || 'Failed to perform action', 'danger');
    } finally {
        toggleLoading(false);
    }
}

// Initial load
fetchAppointments();

// Auto-refresh every 2 minutes
setInterval(() => {
    fetchAppointments();
}, 120000);
</script>
</body>
</html>
