
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
    echo '<body><div class="container mt-5"><div class="alert alert-info">';
    echo '<h4>No Salons Registered Yet</h4>';
    echo '<p>Please register a salon first to manage appointments.</p>';
    echo '<a href="salons.php" class="btn btn-primary">Register Salon</a>';
    echo '</div></div></body></html>';
    exit;
}

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$nonce = bin2hex(random_bytes(16));
header("Content-Security-Policy: script-src 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; connect-src 'self';");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Owner Appointments - Salonora</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
body { 
    font-family: 'Poppins', sans-serif; 
    background: #f5f7fa; 
}
.appointment-card { 
    background: white; 
    padding: 1.25rem; 
    border-radius: 12px; 
    margin-bottom: 1rem; 
    border-left: 4px solid;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: transform 0.2s, box-shadow 0.2s;
}
.appointment-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.appointment-card.pending { border-left-color: #f39c12; }
.appointment-card.confirmed { border-left-color: #27ae60; }
.appointment-card.completed { border-left-color: #3498db; }
.appointment-card.cancelled { border-left-color: #95a5a6; }

.status-badge { 
    padding: 0.3rem 0.6rem; 
    border-radius: 50px; 
    font-weight: 600; 
    font-size: 0.85rem; 
}
.status-badge.pending { color: #f39c12; background: #f39c121a; }
.status-badge.confirmed { color: #27ae60; background: #27ae601a; }
.status-badge.completed { color: #3498db; background: #3498db1a; }
.status-badge.cancelled { color: #95a5a6; background: #95a5a61a; }

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
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
}
.alert-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9998;
    max-width: 400px;
}
.appointment-info {
    font-size: 0.9rem;
    color: #666;
}
.appointment-info i {
    margin-right: 0.5rem;
    color: #888;
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-calendar-check me-2"></i>Appointments Management</h2>
        <div>
            <button class="btn btn-outline-secondary btn-sm me-2" onclick="window.location.href='dashboard.php'">
                <i class="fas fa-arrow-left me-1"></i>Back
            </button>
            <button class="btn btn-outline-primary btn-sm" onclick="fetchAppointments(true)">
                <i class="fas fa-sync-alt me-1"></i>Refresh
            </button>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" id="appointmentTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" 
                    data-bs-target="#pending" type="button" role="tab">
                Pending <span class="badge bg-warning text-dark ms-1" id="pending-count">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="confirmed-tab" data-bs-toggle="tab" 
                    data-bs-target="#confirmed" type="button" role="tab">
                Confirmed <span class="badge bg-success ms-1" id="confirmed-count">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="completed-tab" data-bs-toggle="tab" 
                    data-bs-target="#completed" type="button" role="tab">
                Completed <span class="badge bg-primary ms-1" id="completed-count">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="cancelled-tab" data-bs-toggle="tab" 
                    data-bs-target="#cancelled" type="button" role="tab">
                Cancelled <span class="badge bg-secondary ms-1" id="cancelled-count">0</span>
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="pending">
            <div class="text-center py-5">
                <div class="spinner-border" role="status">
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
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert" id="${alertId}">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    alertContainer.insertAdjacentHTML('beforeend', alertHtml);
    
    // Auto-dismiss after 5 seconds
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
            <div class="text-center py-5 text-muted">
                <i class="fas fa-calendar-times fa-3x mb-3"></i>
                <p>No ${status} appointments.</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = appointments.map(apt => {
        let buttons = '';
        
        if (status === 'pending') {
            buttons = `
                <button class="btn btn-success btn-sm me-2" onclick="handleAction(${apt.id}, 'confirm')" title="Confirm appointment">
                    <i class="fas fa-check me-1"></i>Confirm
                </button>
                <button class="btn btn-danger btn-sm" onclick="handleAction(${apt.id}, 'reject')" title="Reject appointment">
                    <i class="fas fa-times me-1"></i>Reject
                </button>
            `;
        } else if (status === 'confirmed') {
            buttons = `
                <button class="btn btn-primary btn-sm" onclick="handleAction(${apt.id}, 'complete')" title="Mark as completed">
                    <i class="fas fa-check-double me-1"></i>Complete
                </button>
            `;
        }
        
        return `
            <div class="appointment-card ${status}" id="apt-${apt.id}">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h5 class="mb-2">
                            <i class="fas fa-user me-2"></i>${escapeHtml(apt.user_name)} 
                            <span class="status-badge ${status}">${status.toUpperCase()}</span>
                        </h5>
                        <div class="appointment-info mb-2">
                            <div class="mb-1">
                                <i class="fas fa-cut"></i>${escapeHtml(apt.service_name)} 
                                <strong class="text-success">- Rs ${parseFloat(apt.service_price).toFixed(2)}</strong>
                            </div>
                            <div class="mb-1">
                                <i class="fas fa-map-marker-alt"></i>${escapeHtml(apt.salon_name)}
                            </div>
                            <div>
                                <i class="fas fa-clock"></i>${formatDateTime(apt.appointment_date, apt.appointment_time)}
                            </div>
                        </div>
                    </div>
                    <div class="text-end ms-3">
                        ${buttons}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Handle appointment actions
async function handleAction(id, action) {
    // Confirmation for reject action
    if (action === 'reject') {
        if (!confirm('Are you sure you want to reject this appointment? This action cannot be undone.')) {
            return;
        }
    }
    
    // Confirmation for complete action
    if (action === 'complete') {
        if (!confirm('Mark this appointment as completed?')) {
            return;
        }
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