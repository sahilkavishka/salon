<?php
// public/owner/salon_add.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $lat = $_POST['lat'] ?? null;
    $lng = $_POST['lng'] ?? null;
    $opening_time = $_POST['opening_time'] ?? '';
    $closing_time = $_POST['closing_time'] ?? '';
    $slot_duration = $_POST['slot_duration'] ?? '';
    
    // New fields
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $facebook = trim($_POST['facebook'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');
    $parking_available = isset($_POST['parking_available']) ? 1 : 0;
    $wheelchair_accessible = isset($_POST['wheelchair_accessible']) ? 1 : 0;
    $wifi_available = isset($_POST['wifi_available']) ? 1 : 0;
    $air_conditioned = isset($_POST['air_conditioned']) ? 1 : 0;
    
    // Validation
    if ($name === '') $errors[] = 'Salon name is required.';
    if ($address === '') $errors[] = 'Salon address is required.';
    if (!$lat || !$lng) $errors[] = 'Please select the salon location on the map.';
    if ($opening_time === '') $errors[] = 'Opening time is required.';
    if ($closing_time === '') $errors[] = 'Closing time is required.';
    if ($slot_duration === '' || $slot_duration <= 0) $errors[] = 'Slot duration must be positive.';
    
    // Phone validation
    if ($phone && !preg_match('/^[\d\s\+\-\(\)]+$/', $phone)) {
        $errors[] = 'Invalid phone number format.';
    }
    
    // Email validation
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }
    
    // URL validation
    if ($website && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors[] = 'Invalid website URL.';
    }

    // Image upload
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
        // Note: You'll need to add these columns to your salons table:
        // ALTER TABLE salons ADD COLUMN phone VARCHAR(20);
        // ALTER TABLE salons ADD COLUMN email VARCHAR(100);
        // ALTER TABLE salons ADD COLUMN description TEXT;
        // ALTER TABLE salons ADD COLUMN website VARCHAR(255);
        // ALTER TABLE salons ADD COLUMN facebook VARCHAR(255);
        // ALTER TABLE salons ADD COLUMN instagram VARCHAR(255);
        // ALTER TABLE salons ADD COLUMN parking_available TINYINT(1) DEFAULT 0;
        // ALTER TABLE salons ADD COLUMN wheelchair_accessible TINYINT(1) DEFAULT 0;
        // ALTER TABLE salons ADD COLUMN wifi_available TINYINT(1) DEFAULT 0;
        // ALTER TABLE salons ADD COLUMN air_conditioned TINYINT(1) DEFAULT 0;
        
        $stmt = $pdo->prepare("INSERT INTO salons (owner_id, name, address, lat, lng, image, opening_time, closing_time, slot_duration, phone, email, description, website, facebook, instagram, parking_available, wheelchair_accessible, wifi_available, air_conditioned) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$owner_id, $name, $address, $lat, $lng, $imagePath, $opening_time, $closing_time, $slot_duration, $phone, $email, $description, $website, $facebook, $instagram, $parking_available, $wheelchair_accessible, $wifi_available, $air_conditioned]);
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
#salonMap { height: 400px; border: 1px solid #ccc; margin-bottom:10px; border-radius: 8px; }
.image-preview { display:none; margin-top:10px; }
.image-preview.show { display:block; }
.preview-card { 
    border:2px solid #e9ecef; 
    padding:20px; 
    margin-bottom:25px; 
    border-radius:10px;
    background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.preview-card h5 { 
    font-size: 1.5rem; 
    margin-bottom: 10px;
    font-weight: bold;
}
.preview-card p { 
    margin-bottom: 5px;
    opacity: 0.95;
}
.preview-card .badge {
    margin-right: 5px;
    margin-top: 5px;
}
.section-header {
    background: linear-gradient(135deg,  #e91e63 0%, #9c27b0 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    margin-top: 30px;
}
.section-header:first-of-type {
    margin-top: 0;
}
.amenity-checkbox {
    padding: 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 10px;
    transition: all 0.3s;
    cursor: pointer;
}
.amenity-checkbox:hover {
    border-color: #e91e63 ;
    background-color: #f8f9fa;
}
.amenity-checkbox input:checked + label {
    color: #e91e63 ;
    font-weight: bold;
}
.char-counter {
    font-size: 0.875rem;
    color: #7d6c7bff;
    float: right;
}
.form-control:focus, .form-select:focus {
    border-color:  #e91e63 ;
    box-shadow: 0 0 0 0.25rem rgba(234, 102, 205, 0.25);
}
.btn-primary {
    background: linear-gradient(135deg,  #e91e63 0%, #9c27b0 100%);
    border: none;
    padding: 12px 30px;
    font-size: 1.1rem;
}
.btn-primary:hover {
    background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}
.preview-img-container {
    position: relative;
    display: inline-block;
}
.preview-img-container img {
    border-radius: 8px;
    border: 3px solid #ea66c9ff;
}
#geocodeBtn {
    margin-top: 10px;
}
.location-info {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 5px;
    margin-top: 10px;
    font-size: 0.9rem;
}
</style>
</head>
<body>

<div class="container py-5">
    <h2 class="mb-4"><i class="fas fa-plus-circle"></i> Add Your Salon</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle"></i> <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="salonForm" novalidate>
        
        <!-- Live Preview Card -->
        <div class="preview-card">
            <div class="row">
                <div class="col-md-8">
                    <h5 id="previewName">Your Salon Name</h5>
                    <p><i class="fas fa-map-marker-alt"></i> <span id="previewAddress">Salon Address</span></p>
                    <p id="previewPhone" style="display:none;"><i class="fas fa-phone"></i> <span></span></p>
                    <p id="previewEmail" style="display:none;"><i class="fas fa-envelope"></i> <span></span></p>
                    <p id="previewDesc" class="mt-2" style="display:none; font-size:0.9rem; opacity:0.9;"></p>
                    <div id="previewAmenities" class="mt-2"></div>
                </div>
                <div class="col-md-4 text-end">
                    <div id="previewImageContainer" style="display:none;">
                        <img id="previewCardImg" style="max-width:150px; border-radius:8px; border:2px solid white;">
                    </div>
                </div>
            </div>
        </div>

        <!-- Basic Information Section -->
        <div class="section-header">
            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Basic Information</h5>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label"><i class="fas fa-store"></i> Salon Name *</label>
                <input type="text" name="name" id="salonName" class="form-control" required 
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                       placeholder="e.g., Elegance Hair & Beauty">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label"><i class="fas fa-phone"></i> Phone Number</label>
                <input type="tel" name="phone" id="salonPhone" class="form-control" 
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                       placeholder="e.g., +94 77 123 4567">
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label"><i class="fas fa-envelope"></i> Email Address</label>
                <input type="email" name="email" id="salonEmail" class="form-control" 
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="e.g., info@yoursalon.com">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label"><i class="fas fa-globe"></i> Website</label>
                <input type="url" name="website" class="form-control" 
                       value="<?= htmlspecialchars($_POST['website'] ?? '') ?>"
                       placeholder="https://www.yoursalon.com">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fas fa-align-left"></i> Description</label>
            <span class="char-counter" id="descCounter">0/500</span>
            <textarea name="description" id="salonDesc" class="form-control" rows="4" 
                      maxlength="500" placeholder="Tell customers about your salon, services, and what makes you special..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <!-- Location Section -->
        <div class="section-header">
            <h5 class="mb-0"><i class="fas fa-map-marked-alt"></i> Location</h5>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fas fa-map-marker-alt"></i> Address *</label>
            <textarea name="address" id="salonAddress" class="form-control" rows="2" required 
                      placeholder="Enter full address..."><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
            <button type="button" class="btn btn-sm btn-outline-primary" id="geocodeBtn">
                <i class="fas fa-search-location"></i> Find on Map
            </button>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fas fa-map"></i> Select Salon Location on Map *</label>
            <div id="salonMap"></div>
            <input type="hidden" name="lat" id="latInput" value="<?= htmlspecialchars($_POST['lat'] ?? '') ?>">
            <input type="hidden" name="lng" id="lngInput" value="<?= htmlspecialchars($_POST['lng'] ?? '') ?>">
            <div class="location-info" id="locationInfo" style="display:none;">
                <i class="fas fa-check-circle text-success"></i> Location selected: 
                <span id="coordsDisplay"></span>
            </div>
            <small class="form-text text-muted">Click on the map to set your salon's location, or use the "Find on Map" button to search by address.</small>
        </div>

        <!-- Operating Hours Section -->
        <div class="section-header">
            <h5 class="mb-0"><i class="fas fa-clock"></i> Operating Hours</h5>
        </div>

        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">Opening Time *</label>
                <input type="time" name="opening_time" class="form-control" required 
                       value="<?= $_POST['opening_time'] ?? '09:00' ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Closing Time *</label>
                <input type="time" name="closing_time" class="form-control" required 
                       value="<?= $_POST['closing_time'] ?? '19:00' ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Slot Duration (Minutes) *</label>
                <input type="number" name="slot_duration" class="form-control" required min="5" max="180" 
                       value="<?= $_POST['slot_duration'] ?? 30 ?>"
                       placeholder="e.g., 30">
                <small class="text-muted">Time between appointments</small>
            </div>
        </div>

        <!-- Amenities Section -->
        <div class="section-header">
            <h5 class="mb-0"><i class="fas fa-star"></i> Amenities & Features</h5>
        </div>

        <div class="row">
            <div class="col-md-6 col-lg-3">
                <div class="amenity-checkbox">
                    <input type="checkbox" name="parking_available" id="parking" class="form-check-input me-2" 
                           <?= isset($_POST['parking_available']) ? 'checked' : '' ?>>
                    <label for="parking" class="form-check-label" style="cursor:pointer;">
                        <i class="fas fa-parking"></i> Parking Available
                    </label>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="amenity-checkbox">
                    <input type="checkbox" name="wheelchair_accessible" id="wheelchair" class="form-check-input me-2"
                           <?= isset($_POST['wheelchair_accessible']) ? 'checked' : '' ?>>
                    <label for="wheelchair" class="form-check-label" style="cursor:pointer;">
                        <i class="fas fa-wheelchair"></i> Wheelchair Accessible
                    </label>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="amenity-checkbox">
                    <input type="checkbox" name="wifi_available" id="wifi" class="form-check-input me-2"
                           <?= isset($_POST['wifi_available']) ? 'checked' : '' ?>>
                    <label for="wifi" class="form-check-label" style="cursor:pointer;">
                        <i class="fas fa-wifi"></i> Free WiFi
                    </label>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="amenity-checkbox">
                    <input type="checkbox" name="air_conditioned" id="ac" class="form-check-input me-2"
                           <?= isset($_POST['air_conditioned']) ? 'checked' : '' ?>>
                    <label for="ac" class="form-check-label" style="cursor:pointer;">
                        <i class="fas fa-snowflake"></i> Air Conditioned
                    </label>
                </div>
            </div>
        </div>

        <!-- Social Media Section -->
        <div class="section-header">
            <h5 class="mb-0"><i class="fas fa-share-alt"></i> Social Media</h5>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label"><i class="fab fa-facebook"></i> Facebook Page</label>
                <input type="url" name="facebook" class="form-control" 
                       value="<?= htmlspecialchars($_POST['facebook'] ?? '') ?>"
                       placeholder="https://www.facebook.com/yoursalon">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label"><i class="fab fa-instagram"></i> Instagram Profile</label>
                <input type="url" name="instagram" class="form-control" 
                       value="<?= htmlspecialchars($_POST['instagram'] ?? '') ?>"
                       placeholder="https://www.instagram.com/yoursalon">
            </div>
        </div>

        <!-- Image Upload Section -->
        <div class="section-header">
            <h5 class="mb-0"><i class="fas fa-images"></i> Salon Image</h5>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fas fa-camera"></i> Upload Salon Photo</label>
            <input type="file" name="image" id="imageInput" accept="image/*" class="form-control">
            <small class="text-muted">Max size: 5MB. Formats: JPG, PNG, GIF, WebP</small>
            <div class="image-preview" id="imagePreview">
                <div class="preview-img-container mt-3">
                    <img id="previewImg" style="max-width:300px;">
                </div>
                <button type="button" class="btn btn-sm btn-danger mt-2" onclick="removeImage()">
                    <i class="fas fa-trash"></i> Remove Image
                </button>
            </div>
        </div>

        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
            <a href="dashboard.php" class="btn btn-outline-secondary btn-lg">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-check-circle"></i> Add Salon
            </button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
// Live preview updates
const salonName = document.getElementById('salonName');
const salonAddress = document.getElementById('salonAddress');
const salonPhone = document.getElementById('salonPhone');
const salonEmail = document.getElementById('salonEmail');
const salonDesc = document.getElementById('salonDesc');
const previewName = document.getElementById('previewName');
const previewAddress = document.getElementById('previewAddress');
const previewPhone = document.getElementById('previewPhone');
const previewEmail = document.getElementById('previewEmail');
const previewDesc = document.getElementById('previewDesc');

salonName.addEventListener('input', () => {
    previewName.textContent = salonName.value || 'Your Salon Name';
});

salonAddress.addEventListener('input', () => {
    previewAddress.textContent = salonAddress.value || 'Salon Address';
});

salonPhone.addEventListener('input', () => {
    if(salonPhone.value) {
        previewPhone.style.display = 'block';
        previewPhone.querySelector('span').textContent = salonPhone.value;
    } else {
        previewPhone.style.display = 'none';
    }
});

salonEmail.addEventListener('input', () => {
    if(salonEmail.value) {
        previewEmail.style.display = 'block';
        previewEmail.querySelector('span').textContent = salonEmail.value;
    } else {
        previewEmail.style.display = 'none';
    }
});

salonDesc.addEventListener('input', () => {
    const charCount = salonDesc.value.length;
    document.getElementById('descCounter').textContent = charCount + '/500';
    
    if(salonDesc.value) {
        previewDesc.style.display = 'block';
        previewDesc.textContent = salonDesc.value;
    } else {
        previewDesc.style.display = 'none';
    }
});

// Amenities preview
const amenityCheckboxes = document.querySelectorAll('.amenity-checkbox input[type="checkbox"]');
const previewAmenities = document.getElementById('previewAmenities');

function updateAmenitiesPreview() {
    const checked = [];
    amenityCheckboxes.forEach(checkbox => {
        if(checkbox.checked) {
            const label = checkbox.nextElementSibling.textContent.trim();
            checked.push(label);
        }
    });
    
    if(checked.length > 0) {
        previewAmenities.innerHTML = checked.map(a => 
            `<span class="badge bg-light text-dark">${a}</span>`
        ).join(' ');
    } else {
        previewAmenities.innerHTML = '';
    }
}

amenityCheckboxes.forEach(cb => cb.addEventListener('change', updateAmenitiesPreview));

// Image preview
const imageInput = document.getElementById('imageInput');
const imagePreview = document.getElementById('imagePreview');
const previewImg = document.getElementById('previewImg');
const previewCardImg = document.getElementById('previewCardImg');
const previewImageContainer = document.getElementById('previewImageContainer');

imageInput.addEventListener('change', function() {
    const file = this.files[0];
    if(file){
        const reader = new FileReader();
        reader.onload = e => {
            previewImg.src = e.target.result;
            previewCardImg.src = e.target.result;
            imagePreview.classList.add('show');
            previewImageContainer.style.display = 'block';
        }
        reader.readAsDataURL(file);
    }
});

function removeImage() {
    imageInput.value = '';
    imagePreview.classList.remove('show');
    previewImg.src = '';
    previewCardImg.src = '';
    previewImageContainer.style.display = 'none';
}

// Leaflet Map
var map = L.map('salonMap').setView([6.9271, 79.8612], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19
}).addTo(map);

var marker;
var oldLat = document.getElementById('latInput').value;
var oldLng = document.getElementById('lngInput').value;

if(oldLat && oldLng) {
    marker = L.marker([oldLat, oldLng]).addTo(map);
    map.setView([oldLat, oldLng], 15);
    updateLocationInfo(oldLat, oldLng);
}

map.on('click', function(e){
    if(marker) map.removeLayer(marker);
    marker = L.marker([e.latlng.lat, e.latlng.lng]).addTo(map);
    document.getElementById('latInput').value = e.latlng.lat;
    document.getElementById('lngInput').value = e.latlng.lng;
    updateLocationInfo(e.latlng.lat, e.latlng.lng);
});

function updateLocationInfo(lat, lng) {
    document.getElementById('locationInfo').style.display = 'block';
    document.getElementById('coordsDisplay').textContent = 
        `${parseFloat(lat).toFixed(6)}, ${parseFloat(lng).toFixed(6)}`;
}

// Geocoding - Find address on map
document.getElementById('geocodeBtn').addEventListener('click', function() {
    const address = salonAddress.value.trim();
    if(!address) {
        alert('Please enter an address first');
        return;
    }
    
    // Using Nominatim geocoding service
    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`)
        .then(response => response.json())
        .then(data => {
            if(data && data.length > 0) {
                const lat = data[0].lat;
                const lng = data[0].lon;
                
                if(marker) map.removeLayer(marker);
                marker = L.marker([lat, lng]).addTo(map);
                map.setView([lat, lng], 15);
                
                document.getElementById('latInput').value = lat;
                document.getElementById('lngInput').value = lng;
                updateLocationInfo(lat, lng);
            } else {
                alert('Address not found. Please try a different address or click on the map manually.');
            }
        })
        .catch(error => {
            console.error('Geocoding error:', error);
            alert('Error finding address. Please try clicking on the map manually.');
        });
});

// Form validation
document.getElementById('salonForm').addEventListener('submit', function(e) {
    const lat = document.getElementById('latInput').value;
    const lng = document.getElementById('lngInput').value;
    
    if(!lat || !lng) {
        e.preventDefault();
        alert('Please select your salon location on the map');
        document.getElementById('salonMap').scrollIntoView({ behavior: 'smooth' });
        return false;
    }
});

// Initialize character counter
document.getElementById('descCounter').textContent = salonDesc.value.length + '/500';
</script>

<?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>