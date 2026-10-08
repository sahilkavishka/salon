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
    $_SESSION['flash_error'] = 'Please register a salon first to manage gallery photos.';
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

// Handle Add / Delete photo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['flash_error'] = 'Security validation failed. Please try again.';
        header("Location: gallery.php?salon_id=$salon_id");
        exit;
    }

    if (isset($_POST['add_photo'])) {
        $image_url = trim($_POST['image_url'] ?? '');
        $title = trim($_POST['title'] ?? '');

        if (empty($image_url)) {
            $_SESSION['flash_error'] = 'Please provide an image URL.';
        } else {
            $ins = $pdo->prepare("INSERT INTO salon_gallery (salon_id, image_url, title) VALUES (?, ?, ?)");
            $ins->execute([$salon_id, $image_url, $title]);
            $_SESSION['flash_success'] = 'Photo added to salon gallery!';
        }
        header("Location: gallery.php?salon_id=$salon_id");
        exit;
    }

    if (isset($_POST['delete_photo'])) {
        $photo_id = intval($_POST['photo_id'] ?? 0);
        if ($photo_id > 0) {
            $del = $pdo->prepare("DELETE FROM salon_gallery WHERE id=? AND salon_id=?");
            $del->execute([$photo_id, $salon_id]);
            $_SESSION['flash_success'] = 'Photo removed from gallery.';
        }
        header("Location: gallery.php?salon_id=$salon_id");
        exit;
    }
}

// Fetch photos for this salon
$stmt = $pdo->prepare("SELECT * FROM salon_gallery WHERE salon_id=? ORDER BY id DESC");
$stmt->execute([$salon_id]);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-images text-info me-2"></i>Photo Gallery & Portfolio</h2>
            <p class="text-muted mb-0">Showcase haircuts, styling, interior, and work for <strong><?= htmlspecialchars($current_salon['name']) ?></strong></p>
        </div>
        <div class="d-flex gap-2 align-items-center mt-2 mt-md-0">
            <?php if (count($owner_salons) > 1): ?>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    Switch Salon: <?= htmlspecialchars($current_salon['name']) ?>
                </button>
                <ul class="dropdown-menu">
                    <?php foreach ($owner_salons as $os): ?>
                    <li><a class="dropdown-item <?= $os['id'] == $salon_id ? 'active' : '' ?>" href="gallery.php?salon_id=<?= $os['id'] ?>"><?= htmlspecialchars($os['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addPhotoModal">
                <i class="fas fa-plus me-1"></i> Add Photo
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

    <!-- Photos Grid -->
    <?php if (empty($photos)): ?>
    <div class="text-center py-5 bg-white dark-bg-card rounded-4 shadow-sm border">
        <div class="mb-3 text-muted display-4"><i class="far fa-images"></i></div>
        <h4 class="fw-bold">Your Gallery is Empty</h4>
        <p class="text-muted">Upload or link photos of hair styles, bridal dressing, salon interior, and treatments.</p>
        <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addPhotoModal">
            <i class="fas fa-plus me-1"></i> Add First Photo
        </button>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($photos as $p): ?>
        <div class="col-sm-6 col-md-4 col-lg-3">
            <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden dark-bg-card position-relative group">
                <img src="<?= htmlspecialchars($p['image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($p['title']) ?>" style="height: 200px; object-fit: cover;">
                <div class="card-body p-3">
                    <h6 class="card-title fw-bold text-truncate mb-1"><?= htmlspecialchars($p['title'] ?: 'Salon Showcase') ?></h6>
                    <small class="text-muted"><i class="far fa-clock me-1"></i><?= date('M d, Y', strtotime($p['created_at'])) ?></small>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3 d-flex justify-content-end">
                    <form method="POST" onsubmit="return confirm('Delete this image from gallery?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="photo_id" value="<?= $p['id'] ?>">
                        <button type="submit" name="delete_photo" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                            <i class="fas fa-trash-alt me-1"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add Photo Modal -->
<div class="modal fade" id="addPhotoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-image me-2 text-info"></i>Add Gallery Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Image URL <span class="text-danger">*</span></label>
                        <input type="url" name="image_url" class="form-control" required placeholder="https://images.unsplash.com/photo-...">
                        <small class="text-muted">Enter a direct image link or web photo URL.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Photo Title / Caption</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Modern Fade Haircut / Bridal Makeup">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_photo" class="btn btn-gradient rounded-pill px-4">Upload Photo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
