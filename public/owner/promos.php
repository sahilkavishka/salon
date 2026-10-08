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
    $_SESSION['flash_error'] = 'Please register a salon first to manage promo codes.';
    header('Location: salon_add.php');
    exit;
}

$salon_id = intval($_GET['salon_id'] ?? 0);
if (!$salon_id || !in_array($salon_id, array_column($owner_salons, 'id'))) {
    $salon_id = $owner_salons[0]['id'];
}

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

// Handle Add, Delete, Toggle Promo Codes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['flash_error'] = 'Security validation failed. Please try again.';
        header("Location: promos.php?salon_id=$salon_id");
        exit;
    }

    if (isset($_POST['add_promo'])) {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $discount_percent = intval($_POST['discount_percent'] ?? 0);
        $discount_amount = floatval($_POST['discount_amount'] ?? 0);
        $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($code)) {
            $_SESSION['flash_error'] = 'Promo code string is required (e.g. SUMMER20).';
        } elseif ($discount_percent <= 0 && $discount_amount <= 0) {
            $_SESSION['flash_error'] = 'Please enter either a discount percentage (%) or fixed discount amount (Rs).';
        } else {
            // Check for duplicate promo code in this salon
            $chk = $pdo->prepare("SELECT id FROM promo_codes WHERE salon_id=? AND code=?");
            $chk->execute([$salon_id, $code]);
            if ($chk->fetch()) {
                $_SESSION['flash_error'] = "Promo code '$code' already exists for this salon.";
            } else {
                $ins = $pdo->prepare("INSERT INTO promo_codes (salon_id, code, discount_percent, discount_amount, valid_until, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                $ins->execute([$salon_id, $code, $discount_percent, $discount_amount, $valid_until, $is_active]);
                $_SESSION['flash_success'] = "Promo code '$code' created successfully!";
            }
        }
        header("Location: promos.php?salon_id=$salon_id");
        exit;
    }

    if (isset($_POST['delete_promo'])) {
        $promo_id = intval($_POST['promo_id'] ?? 0);
        if ($promo_id > 0) {
            $del = $pdo->prepare("DELETE FROM promo_codes WHERE id=? AND salon_id=?");
            $del->execute([$promo_id, $salon_id]);
            $_SESSION['flash_success'] = 'Promo code deleted.';
        }
        header("Location: promos.php?salon_id=$salon_id");
        exit;
    }

    if (isset($_POST['toggle_promo'])) {
        $promo_id = intval($_POST['promo_id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);
        $new_status = $status === 1 ? 0 : 1;
        $upd = $pdo->prepare("UPDATE promo_codes SET is_active=? WHERE id=? AND salon_id=?");
        $upd->execute([$new_status, $promo_id, $salon_id]);
        $_SESSION['flash_success'] = 'Promo code status updated.';
        header("Location: promos.php?salon_id=$salon_id");
        exit;
    }
}

// Fetch promo codes for this salon
$stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE salon_id=? ORDER BY id DESC");
$stmt->execute([$salon_id]);
$promos = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-tags text-warning me-2"></i>Promo Codes & Discounts</h2>
            <p class="text-muted mb-0">Offer discount coupons for customers of <strong><?= htmlspecialchars($current_salon['name']) ?></strong></p>
        </div>
        <div class="d-flex gap-2 align-items-center mt-2 mt-md-0">
            <?php if (count($owner_salons) > 1): ?>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    Switch Salon: <?= htmlspecialchars($current_salon['name']) ?>
                </button>
                <ul class="dropdown-menu">
                    <?php foreach ($owner_salons as $os): ?>
                    <li><a class="dropdown-item <?= $os['id'] == $salon_id ? 'active' : '' ?>" href="promos.php?salon_id=<?= $os['id'] ?>"><?= htmlspecialchars($os['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addPromoModal">
                <i class="fas fa-plus me-1"></i> New Promo Code
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

    <!-- Table of promo codes -->
    <?php if (empty($promos)): ?>
    <div class="text-center py-5 bg-white dark-bg-card rounded-4 shadow-sm border">
        <div class="mb-3 text-muted display-4"><i class="fas fa-ticket-alt"></i></div>
        <h4 class="fw-bold">No Active Coupons</h4>
        <p class="text-muted">Create promo codes to attract new customers and reward loyal clients with discounts.</p>
        <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addPromoModal">
            <i class="fas fa-plus me-1"></i> Create First Promo Code
        </button>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden dark-bg-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Code</th>
                        <th>Discount</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($promos as $p): ?>
                    <tr>
                        <td class="ps-4">
                            <span class="badge bg-primary fs-6 px-3 py-2 font-monospace tracking-wide">
                                <i class="fas fa-tag me-1"></i><?= htmlspecialchars($p['code']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($p['discount_percent'] > 0): ?>
                                <strong class="text-success"><?= $p['discount_percent'] ?>% OFF</strong>
                            <?php elseif ($p['discount_amount'] > 0): ?>
                                <strong class="text-success">Rs <?= number_format($p['discount_amount'], 2) ?> OFF</strong>
                            <?php else: ?>
                                <span class="text-muted">No discount</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($p['valid_until'])): ?>
                                <?php 
                                $isExpired = strtotime($p['valid_until']) < strtotime(date('Y-m-d'));
                                ?>
                                <span class="<?= $isExpired ? 'text-danger fw-bold' : '' ?>">
                                    <?= date('M d, Y', strtotime($p['valid_until'])) ?>
                                    <?= $isExpired ? '<span class="badge bg-danger ms-1">Expired</span>' : '' ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">No expiration</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge rounded-pill <?= $p['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $p['is_active'] ? 'Active' : 'Disabled' ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="promo_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="status" value="<?= $p['is_active'] ?>">
                                <button type="submit" name="toggle_promo" class="btn btn-sm <?= $p['is_active'] ? 'btn-outline-secondary' : 'btn-outline-success' ?> rounded-pill px-3">
                                    <?= $p['is_active'] ? 'Disable' : 'Enable' ?>
                                </button>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete promo code <?= htmlspecialchars($p['code']) ?>?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="promo_id" value="<?= $p['id'] ?>">
                                <button type="submit" name="delete_promo" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Promo Modal -->
<div class="modal fade" id="addPromoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-ticket-alt me-2 text-warning"></i>New Promo Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Coupon Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control text-uppercase font-monospace" required placeholder="e.g. WELCOME20" style="letter-spacing: 1px;">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Percentage (%)</label>
                            <div class="input-group">
                                <input type="number" name="discount_percent" class="form-control" min="0" max="100" placeholder="0">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Fixed Amount (Rs)</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs</span>
                                <input type="number" name="discount_amount" class="form-control" min="0" step="10" placeholder="0">
                            </div>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Enter either % or fixed Rs amount.</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Valid Until (Optional)</label>
                        <input type="date" name="valid_until" class="form-control" min="<?= date('Y-m-d') ?>">
                        <small class="text-muted">Leave empty for no expiry.</small>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isPromoActiveCheck" checked>
                        <label class="form-check-label fw-semibold" for="isPromoActiveCheck">Activate coupon immediately</label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_promo" class="btn btn-gradient rounded-pill px-4">Create Coupon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
