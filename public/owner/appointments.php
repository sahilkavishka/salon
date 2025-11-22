<?php
// public/owner/appointments.php
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

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$nonce = bin2hex(random_bytes(16));
header("Content-Security-Policy: script-src 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;");
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
body { font-family: 'Poppins', sans-serif; background:#f5f7fa; }
.appointment-card { background:white; padding:1rem; border-radius:12px; margin-bottom:1rem; border-left:4px solid; }
.appointment-card.pending { border-left-color:#f39c12; }
.appointment-card.confirmed { border-left-color:#27ae60; }
.appointment-card.completed { border-left-color:#3498db; }
.appointment-card.cancelled { border-left-color:#95a5a6; }
.status-badge { padding:0.3rem 0.6rem; border-radius:50px; font-weight:600; font-size:0.85rem; }
.status-badge.pending { color:#f39c12; background: #f39c121a; }
.status-badge.confirmed { color:#27ae60; background:#27ae601a; }
.status-badge.completed { color:#3498db; background:#3498db1a; }
.status-badge.cancelled { color:#95a5a6; background:#95a5a61a; }
</style>
</head>
<body>

<div id="alertContainer"></div>

<div class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-calendar-check me-2"></i>Appointments</h2>
    <button class="btn btn-outline-primary btn-sm" onclick="fetchAppointments(true)">
        <i class="fas fa-sync-alt me-1"></i>Refresh
    </button>
</div>

<ul class="nav nav-tabs mb-3" id="appointmentTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" 
            data-bs-target="#pending" type="button" role="tab">Pending <span class="badge bg-warning text-dark ms-1" id="pending-count">0</span></button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="confirmed-tab" data-bs-toggle="tab" 
            data-bs-target="#confirmed" type="button" role="tab">Confirmed <span class="badge bg-success ms-1" id="confirmed-count">0</span></button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="completed-tab" data-bs-toggle="tab" 
            data-bs-target="#completed" type="button" role="tab">Completed <span class="badge bg-primary ms-1" id="completed-count">0</span></button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="cancelled-tab" data-bs-toggle="tab" 
            data-bs-target="#cancelled" type="button" role="tab">Cancelled <span class="badge bg-secondary ms-1" id="cancelled-count">0</span></button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="pending"></div>
  <div class="tab-pane fade" id="confirmed"></div>
  <div class="tab-pane fade" id="completed"></div>
  <div class="tab-pane fade" id="cancelled"></div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script nonce="<?= $nonce ?>">
const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
async function fetchAppointments(showLoading=false){
    const res = await fetch('fetch_appointments.php',{headers:{'X-Requested-With':'XMLHttpRequest'}});
    const data = await res.json();
    ['pending','confirmed','completed','cancelled'].forEach(status=>{
        const container = document.getElementById(status);
        const countBadge = document.getElementById(status+'-count');
        if(container){
            if(data[status].length===0){
                container.innerHTML='<p>No '+status+' appointments.</p>';
            } else {
                container.innerHTML=data[status].map(apt=>{
                    let buttons='';
                    if(status==='pending'){
                        buttons=`<button class="btn btn-success btn-sm me-2" onclick="handleAction(${apt.id},'confirm')">Confirm</button>
                                 <button class="btn btn-danger btn-sm" onclick="handleAction(${apt.id},'reject')">Reject</button>`;
                    } else if(status==='confirmed'){
                        buttons=`<button class="btn btn-primary btn-sm" onclick="handleAction(${apt.id},'complete')">Mark Completed</button>`;
                    }
                    return `<div class="appointment-card ${status}" id="apt-${apt.id}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5>${apt.user_name} <span class="status-badge ${status}">${status}</span></h5>
                                <p>${apt.service_name} - Rs ${parseFloat(apt.service_price).toFixed(2)}</p>
                                <p>${apt.salon_name} | ${apt.appointment_date} ${apt.appointment_time}</p>
                            </div>
                            <div>${buttons}</div>
                        </div>
                    </div>`;
                }).join('');
            }
        }
        if(countBadge) countBadge.textContent=data[status].length;
    });
    if(showLoading) alert('Appointments refreshed!');
}

async function handleAction(id,action){
    if(action==='reject' && !confirm('Reject this appointment?')) return;
    const fd=new FormData();
    fd.append('csrf_token',csrfToken);
    fd.append('appointment_id',id);
    fd.append('action',action);
    const res = await fetch('appointment_action.php',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
    const data = await res.json();
    if(data.success){ fetchAppointments(); } else { alert(data.error || 'Action failed'); }
}

fetchAppointments();
</script>
</body>
</html>
