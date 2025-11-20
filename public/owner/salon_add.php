<?php
// public/owner/salon_add.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $lat = $_POST['lat'] ?? null;
    $lng = $_POST['lng'] ?? null;
    $opening_time = $_POST['opening_time'] ?? '';
    $closing_time = $_POST['closing_time'] ?? '';
    $slot_duration = $_POST['slot_duration'] ?? '';

    if ($name === '') $errors[] = 'Salon name is required.';
    if ($address === '') $errors[] = 'Salon address is required.';
    if (!$lat || !$lng) $errors[] = 'Please select the salon location on the map.';
    if ($opening_time === '') $errors[] = 'Opening time is required.';
    if ($closing_time === '') $errors[] = 'Closing time is required.';
    if ($slot_duration === '' || $slot_duration <= 0) $errors[] = 'Slot duration must be positive.';

    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload error.';
        } elseif (!in_array(mime_content_type($file['tmp_name']), $allowed, true)) {
            $errors[] = 'Only JPG, PNG, GIF or WebP images allowed.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Image size exceeds 5MB.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $safe = 'uploads/salon_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $destDir = __DIR__ . '/../../uploads';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            $destFull = __DIR__ . '/../../' . $safe;
            if (!move_uploaded_file($file['tmp_name'], $destFull)) {
                $errors[] = 'Failed to save uploaded image.';
            } else {
                $imagePath = $safe;
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO salons (owner_id, name, address, lat, lng, image, opening_time, closing_time, slot_duration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$owner_id, $name, $address, $lat, $lng, $imagePath, $opening_time, $closing_time, $slot_duration]);
        $_SESSION['flash_success'] = 'Salon added successfully.';
        header('Location: dashboard.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Salon - Salonora</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<style>
#salonMap { height: 300px; border: 1px solid #ccc; margin-bottom:10px; }
.image-preview { display:none; margin-top:10px; }
.image-preview.show { display:block; }
.preview-card { border:1px solid #ccc; padding:10px; margin-bottom:15px; border-radius:5px; }
</style>
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>

<div class="container py-5">
    <h2 class="mb-4">Add Your Salon</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="salonForm">
        <!-- Salon Name -->
        <div class="mb-3">
            <label class="form-label"><i class="fas fa-store"></i> Salon Name *</label>
            <input type="text" name="name" id="salonName" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>

        <!-- Address -->
        <div class="mb-3">
            <label class="form-label"><i class="fas fa-map-marker-alt"></i> Address *</label>
            <textarea name="address" id="salonAddress" class="form-control" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
        </div>

        <!-- Live Preview -->
        <div class="preview-card">
            <h5 id="previewName">Your Salon Name</h5>
            <p><i class="fas fa-map-marker-alt"></i> <span id="previewAddress">Salon Address</span></p>
        </div>

        <!-- Image Upload -->
        <div class="mb-3">
            <label class="form-label"><i class="fas fa-camera"></i> Salon Image</label>
            <input type="file" name="image" id="imageInput" accept="image/*" class="form-control">
            <div class="image-preview" id="imagePreview">
                <img id="previewImg" style="max-width:200px;">
                <button type="button" class="btn btn-sm btn-danger mt-1" onclick="removeImage()"><i class="fas fa-trash"></i> Remove</button>
            </div>
        </div>

        <!-- Opening / Closing / Slot Duration -->
        <div class="row mb-3">
            <div class="col">
                <label>Opening Time *</label>
                <input type="time" name="opening_time" class="form-control" required value="<?= $_POST['opening_time'] ?? '09:00' ?>">
            </div>
            <div class="col">
                <label>Closing Time *</label>
                <input type="time" name="closing_time" class="form-control" required value="<?= $_POST['closing_time'] ?? '19:00' ?>">
            </div>
            <div class="col">
                <label>Slot Duration (Minutes) *</label>
                <input type="number" name="slot_duration" class="form-control" required min="5" value="<?= $_POST['slot_duration'] ?? 30 ?>">
            </div>
        </div>

        <!-- Map -->
        <div class="mb-3">
            <label class="form-label"><i class="fas fa-map-marker-alt"></i> Select Salon Location *</label>
            <div id="salonMap"></div>
            <input type="hidden" name="lat" id="latInput" value="<?= htmlspecialchars($_POST['lat'] ?? '') ?>">
            <input type="hidden" name="lng" id="lngInput" value="<?= htmlspecialchars($_POST['lng'] ?? '') ?>">
            <small class="form-text text-muted">Click on the map to set your salon's location.</small>
        </div>

        <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Add Salon</button>
    </form>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
const salonName = document.getElementById('salonName');
const salonAddress = document.getElementById('salonAddress');
const previewName = document.getElementById('previewName');
const previewAddress = document.getElementById('previewAddress');

salonName.addEventListener('input', () => previewName.textContent = salonName.value || 'Your Salon Name');
salonAddress.addEventListener('input', () => previewAddress.textContent = salonAddress.value || 'Salon Address');

// Image preview
const imageInput = document.getElementById('imageInput');
const imagePreview = document.getElementById('imagePreview');
const previewImg = document.getElementById('previewImg');
imageInput.addEventListener('change', function() {
    const file = this.files[0];
    if(file){
        const reader = new FileReader();
        reader.onload = e => { previewImg.src = e.target.result; imagePreview.classList.add('show'); }
        reader.readAsDataURL(file);
    }
});
function removeImage(){ imageInput.value=''; imagePreview.classList.remove('show'); previewImg.src=''; }

// Leaflet Map
var map = L.map('salonMap').setView([6.9271, 79.8612], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(map);

var marker;
var oldLat = document.getElementById('latInput').value;
var oldLng = document.getElementById('lngInput').value;
if(oldLat && oldLng){ marker = L.marker([oldLat, oldLng]).addTo(map); map.setView([oldLat, oldLng],15); }

map.on('click', function(e){
    if(marker) map.removeLayer(marker);
    marker = L.marker([e.latlng.lat, e.latlng.lng]).addTo(map);
    document.getElementById('latInput').value = e.latlng.lat;
    document.getElementById('lngInput').value = e.latlng.lng;
});
</script>

<?php include __DIR__ . '/../footer.php'; ?>
