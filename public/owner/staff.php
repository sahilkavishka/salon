<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];

// Get all salons owned by this owner
$stmt = $pdo->prepare("SELECT id, name FROM salons WHERE owner_id=? ORDER BY id ASC");
$stmt->execute([$owner_id]);
$owner_salons = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($owner_salons)) {
    $_SESSION['flash_error'] = 'Please register a salon first to manage staff.';
    header('Location: salon_add.php');
    exit;
}

$salon_id = intval($_GET['salon_id'] ?? 0);
if (!$salon_id || !in_array($salon_id, array_column($owner_salons, 'id'))) {
    $salon_id = $owner_salons[0]['id'];
}

// Get current salon
$current_salon = null;
foreach ($owner_salons as $s) {
    if ($s['id'] == $salon_id) {
        $current_salon = $s;
        break;
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Form Submissions (Add, Delete, Toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['flash_error'] = 'Security validation failed. Please try again.';
        header("Location: staff.php?salon_id=$salon_id");
        exit;
    }

    if (isset($_POST['add_staff'])) {
        $name = trim($_POST['name'] ?? '');
        $specialty = trim($_POST['specialty'] ?? '');
        $avatar = trim($_POST['avatar'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name)) {
            $_SESSION['flash_error'] = 'Staff member name is required.';
        } else {
            $insert = $pdo->prepare("INSERT INTO salon_staff (salon_id, name, specialty, avatar, is_active) VALUES (?, ?, ?, ?, ?)");
            $insert->execute([$salon_id, $name, $specialty, $avatar, $is_active]);
            $_SESSION['flash_success'] = 'Staff member added successfully!';
        }
        header("Location: staff.php?salon_id=$salon_id");
        exit;
    }

    if (isset($_POST['delete_staff'])) {
        $staff_id = intval($_POST['staff_id'] ?? 0);
        if ($staff_id > 0) {
            $del = $pdo->prepare("DELETE FROM salon_staff WHERE id=? AND salon_id=?");
            $del->execute([$staff_id, $salon_id]);
            $_SESSION['flash_success'] = 'Staff member removed.';
        }
        header("Location: staff.php?salon_id=$salon_id");
        exit;
    }

    if (isset($_POST['toggle_status'])) {
        $staff_id = intval($_POST['staff_id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);
        $new_status = $status === 1 ? 0 : 1;
        $upd = $pdo->prepare("UPDATE salon_staff SET is_active=? WHERE id=? AND salon_id=?");
        $upd->execute([$new_status, $staff_id, $salon_id]);
        $_SESSION['flash_success'] = 'Staff status updated.';
        header("Location: staff.php?salon_id=$salon_id");
        exit;
    }
}

// Fetch staff for this salon
$stmt = $pdo->prepare("SELECT * FROM salon_staff WHERE salon_id=? ORDER BY is_active DESC, name ASC");
$stmt->execute([$salon_id]);
$staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-user-friends text-primary me-2"></i>Staff & Stylists</h2>
            <p class="text-muted mb-0">Manage stylists, barbers, and specialists for <strong><?= htmlspecialchars($current_salon['name']) ?></strong></p>
        </div>
        <div class="d-flex gap-2 align-items-center mt-2 mt-md-0">
            <?php if (count($owner_salons) > 1): ?>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    Switch Salon: <?= htmlspecialchars($current_salon['name']) ?>
                </button>
                <ul class="dropdown-menu">
                    <?php foreach ($owner_salons as $os): ?>
                    <li><a class="dropdown-item <?= $os['id'] == $salon_id ? 'active' : '' ?>" href="staff.php?salon_id=<?= $os['id'] ?>"><?= htmlspecialchars($os['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                <i class="fas fa-plus me-1"></i> Add Stylist
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Staff Cards Grid -->
    <?php if (empty($staff_list)): ?>
    <div class="text-center py-5 bg-white dark-bg-card rounded-4 shadow-sm border">
        <div class="mb-3 text-muted display-4"><i class="fas fa-user-clock"></i></div>
        <h4 class="fw-bold">No Staff Members Yet</h4>
        <p class="text-muted">Add your team of stylists and specialists so clients can select their preferred professional when booking.</p>
        <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addStaffModal">
            <i class="fas fa-plus me-1"></i> Add First Stylist
        </button>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($staff_list as $st): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden dark-bg-card">
                <div class="card-body p-4 text-center">
                    <div class="position-relative d-inline-block mb-3">
                        <?php if (!empty($st['avatar'])): ?>
                        <img src="<?= htmlspecialchars($st['avatar']) ?>" alt="<?= htmlspecialchars($st['name']) ?>" class="rounded-circle border border-3 border-light shadow-sm" style="width: 90px; height: 90px; object-fit: cover;">
                        <?php else: ?>
                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center border border-3 border-light shadow-sm" style="width: 90px; height: 90px; margin: 0 auto;">
                            <i class="fas fa-user-tie fa-2x text-muted"></i>
                        </div>
                        <?php endif; ?>
                        <span class="position-absolute bottom-0 end-0 badge rounded-pill <?= $st['is_active'] ? 'bg-success' : 'bg-secondary' ?>" style="font-size: 0.75rem;">
                            <?= $st['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>

                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($st['name']) ?></h5>
                    <p class="text-primary small mb-3"><i class="fas fa-star me-1"></i><?= htmlspecialchars($st['specialty'] ?: 'Senior Stylist') ?></p>

                    <div class="d-flex justify-content-center gap-2 pt-2 border-top">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="staff_id" value="<?= $st['id'] ?>">
                            <input type="hidden" name="status" value="<?= $st['is_active'] ?>">
                            <button type="submit" name="toggle_status" class="btn btn-sm <?= $st['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?> rounded-pill px-3">
                                <?= $st['is_active'] ? 'Set Inactive' : 'Activate' ?>
                            </button>
                        </form>

                        <form method="POST" class="d-inline" onsubmit="return confirm('Remove this staff member?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="staff_id" value="<?= $st['id'] ?>">
                            <button type="submit" name="delete_staff" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                <i class="fas fa-trash-alt me-1"></i> Remove
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-user-plus me-2 text-primary"></i>Add New Stylist</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Stylist / Professional Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Kasun Fernando">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Specialty / Role</label>
                        <input type="text" name="specialty" class="form-control" placeholder="e.g. Hair Coloring & Beard Specialist">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Photo URL (Optional)</label>
                        <input type="url" name="avatar" class="form-control" placeholder="https://images.unsplash.com/photo-...">
                        <small class="text-muted">Or leave empty for default profile avatar.</small>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActiveCheck" checked>
                        <label class="form-check-label fw-semibold" for="isActiveCheck">Available for appointments</label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_staff" class="btn btn-gradient rounded-pill px-4">Save Stylist</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
