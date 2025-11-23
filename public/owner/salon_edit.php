<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];
$salon_id = intval($_GET['id'] ?? 0);

if ($salon_id <= 0) {
    $_SESSION['flash_error'] = 'Salon ID is missing.';
    header('Location: dashboard.php');
    exit;
}

// fetch salon
$stmt = $pdo->prepare("SELECT * FROM salons WHERE id = ? AND owner_id = ?");
$stmt->execute([$salon_id, $owner_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$salon) {
    $_SESSION['flash_error'] = 'Salon not found or not authorized.';
    header('Location: dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $imagePath = $salon['image'];

    if ($name === '') $errors[] = "Salon name is required.";
    if ($address === '') $errors[] = "Salon address is required.";

    // handle image upload
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $errors[] = "Invalid image type.";
        } elseif ($_FILES['image']['size'] > 5*1024*1024) {
            $errors[] = "Image size exceeds 5MB.";
        } else {
            $uploadDir = __DIR__ . '/../../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $newName = 'salon_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $uploadPath = $uploadDir . $newName;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                $imagePath = 'uploads/' . $newName;

                // remove old image
                if (!empty($salon['image']) && file_exists(__DIR__ . '/../../' . $salon['image'])) {
                    @unlink(__DIR__ . '/../../' . $salon['image']);
                }
            } else {
                $errors[] = "Failed to upload image.";
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE salons SET name=?, address=?, image=? WHERE id=? AND owner_id=?");
        $stmt->execute([$name, $address, $imagePath, $salon_id, $owner_id]);
        $_SESSION['flash_success'] = 'Salon updated successfully.';
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Salon - Salonora</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="../assets/css/salon_edit.css">
   
  
</head>
<body>
 
  <!-- Page Header -->
  <div class="page-header">
    <div class="container">
      <div class="page-header-content">
        <h1 class="page-title">Edit Salon Details</h1>
        <p class="page-subtitle">Update your salon information and image</p>
      </div>
    </div>
  </div>

  <div class="container pb-5">
    <!-- Alerts -->
    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <div>
          <?php foreach ($errors as $e): ?>
            <?= htmlspecialchars($e) ?><br>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Form Container -->
    <div class="form-container">
      <div class="form-header">
        <div class="form-icon">
          <i class="fas fa-edit"></i>
        </div>
        <h2 class="form-title">Update Salon Information</h2>
        <p class="form-description">Keep your salon details up to date</p>
      </div>

      <!-- Preview Card -->
      <div class="preview-card">
        <div class="preview-title">
          <i class="fas fa-eye"></i>
          Live Preview
        </div>
        <div class="preview-content">
          <h3 id="previewName"><?= htmlspecialchars($salon['name']) ?></h3>
          <p>
            <i class="fas fa-map-marker-alt"></i>
            <span id="previewAddress"><?= htmlspecialchars($salon['address']) ?></span>
          </p>
        </div>
      </div>

      <form method="post" enctype="multipart/form-data" id="salonForm">
        <!-- Salon Name -->
        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-store"></i>
            Salon Name
            <span class="required">*</span>
          </label>
          <input 
            type="text" 
            name="name" 
            id="salonName"
            class="form-control" 
            value="<?= htmlspecialchars($salon['name']) ?>" 
            required
            maxlength="100"
            placeholder="e.g., Elegant Beauty Salon">
          <div class="form-hint">
            <i class="fas fa-info-circle"></i>
            Choose a memorable name for your salon
          </div>
        </div>

        <!-- Address -->
        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-map-marker-alt"></i>
            Address
            <span class="required">*</span>
          </label>
          <input 
            type="text" 
            name="address" 
            id="salonAddress"
            class="form-control" 
            value="<?= htmlspecialchars($salon['address']) ?>" 
            required
            maxlength="200"
            placeholder="e.g., 123 Main Street, Colombo 07">
          <div class="form-hint">
            <i class="fas fa-info-circle"></i>
            Provide your complete salon address
          </div>
        </div>

        <!-- Image Upload Section -->
        <div class="image-upload-section">
          <div class="current-image-label">
            <i class="fas fa-image"></i>
            Salon Image
          </div>
          
          <div class="image-preview-container">
            <div class="current-image-wrapper">
              <?php if ($salon['image']): ?>
                <img src="../../<?= htmlspecialchars($salon['image']) ?>" class="current-image" id="currentImage" alt="Current salon image">
              <?php else: ?>
                <div class="no-image-placeholder" id="noImagePlaceholder">
                  <i class="fas fa-image"></i>
                  <span>No image uploaded</span>
                </div>
              <?php endif; ?>
            </div>

            <div class="upload-controls">
              <div class="file-input-wrapper">
                <label for="imageInput" class="file-input-label">
                  <i class="fas fa-cloud-upload-alt"></i>
                  Choose New Image
                </label>
                <input 
                  type="file" 
                  name="image" 
                  id="imageInput"
                  class="file-input" 
                  accept="image/*">
                <div class="file-name-display" id="fileNameDisplay"></div>
              </div>

              <div class="upload-info">
                <strong>Image Requirements:</strong>
                <ul>
                  <li>Formats: JPG, JPEG, PNG, GIF, WebP</li>
                  <li>Maximum size: 5MB</li>
                  <li>Recommended: 800x600px or higher</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
        <div class="mb-3">
    <label class="form-label">Opening Time</label>
    <input type="time" name="opening_time" class="form-control"
           value="<?= htmlspecialchars($salon['opening_time']) ?>" required>
</div>

<div class="mb-3">
    <label class="form-label">Closing Time</label>
    <input type="time" name="closing_time" class="form-control"
           value="<?= htmlspecialchars($salon['closing_time']) ?>" required>
</div>

<div class="mb-3">
    <label class="form-label">Slot Duration (Minutes)</label>
    <input type="number" name="slot_duration" class="form-control" min="5"
           value="<?= htmlspecialchars($salon['slot_duration']) ?>" required>
</div>


        <!-- Form Actions -->
        <div class="form-actions">
          <a href="dashboard.php" class="btn-cancel">
            <i class="fas fa-times"></i>
            Cancel
          </a>
          <button type="submit" class="btn-submit" id="submitBtn">
            <span class="spinner"></span>
            <i class="fas fa-save"></i>
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Live preview update
    const salonName = document.getElementById('salonName');
    const salonAddress = document.getElementById('salonAddress');
    const previewName = document.getElementById('previewName');
    const previewAddress = document.getElementById('previewAddress');

    salonName.addEventListener('input', function() {
      previewName.textContent = this.value || 'Salon Name';
    });

    salonAddress.addEventListener('input', function() {
      previewAddress.textContent = this.value || 'Salon Address';
    });

    // Image preview
    const imageInput = document.getElementById('imageInput');
    const fileNameDisplay = document.getElementById('fileNameDisplay');
    const currentImage = document.getElementById('currentImage');
    const noImagePlaceholder = document.getElementById('noImagePlaceholder');

    imageInput.addEventListener('change', function(e) {
      const file = e.target.files[0];
      
      if (file) {
        // Show file name
        fileNameDisplay.textContent = file.name;
        fileNameDisplay.classList.add('show');

        // Preview image
        const reader = new FileReader();
        reader.onload = function(e) {
          if (currentImage) {
            currentImage.src = e.target.result;
          } else if (noImagePlaceholder) {
            noImagePlaceholder.outerHTML = `<img src="${e.target.result}" class="current-image" id="currentImage" alt="Preview">`;
          }
        };
        reader.readAsDataURL(file);
      }
    });

    // Form submission with loading state
    const form = document.getElementById('salonForm');
    const submitBtn = document.getElementById('submitBtn');

    form.addEventListener('submit', function() {
      submitBtn.classList.add('loading');
      submitBtn.disabled = true;
    });

    // Auto-hide alerts
    setTimeout(() => {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
      });
    }, 5000);

    // Input validation feedback
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
      input.addEventListener('invalid', function() {
        this.style.borderColor = '#e74c3c';
      });
      
      input.addEventListener('input', function() {
        if (this.validity.valid) {
          this.style.borderColor = '#e9ecef';
        }
      });
    });
  </script>
</body>
</html>