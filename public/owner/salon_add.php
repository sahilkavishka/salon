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

  if ($name === '') $errors[] = 'Salon name is required.';
  if ($address === '') $errors[] = 'Salon address is required.';

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
    $stmt = $pdo->prepare("INSERT INTO salons (owner_id, name, address, image) VALUES (?, ?, ?, ?)");
    $stmt->execute([$owner_id, $name, $address, $imagePath]);
    $_SESSION['flash_success'] = 'Salon added successfully.';
    header('Location: dashboard.php');
    exit;
  }
}

$page_title = "Add New Salon - Salonora";
?>
<?php include __DIR__ . '/../header.php'; ?>

<!-- Add page-specific CSS -->
<link rel="stylesheet" href="/salonora/public/assets/css/salon_add.css">

<!-- Page Header -->
<div class="page-header">
  <div class="container">
    <div class="page-header-content">
      <h1 class="page-title">Add Your Salon</h1>
      <p class="page-subtitle">Register your salon and start attracting customers</p>
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
        <i class="fas fa-store-alt"></i>
      </div>
      <h2 class="form-title">Create Your Salon Profile</h2>
      <p class="form-description">Fill in the details to get started</p>
    </div>

    <!-- Steps Indicator -->
    <div class="steps-indicator">
      <div class="step active">
        <i class="fas fa-info-circle"></i>
        <span>Basic Info</span>
      </div>
      <div class="step">
        <i class="fas fa-image"></i>
        <span>Image</span>
      </div>
      <div class="step">
        <i class="fas fa-check-circle"></i>
        <span>Complete</span>
      </div>
    </div>

    <!-- Preview Card -->
    <div class="preview-card">
      <div class="preview-title">
        <i class="fas fa-eye"></i>
        Live Preview
      </div>
      <div class="preview-content">
        <h3 id="previewName">Your Salon Name</h3>
        <p>
          <i class="fas fa-map-marker-alt"></i>
          <span id="previewAddress">Salon Address</span>
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
          value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
          required
          maxlength="100"
          placeholder="e.g., Elegant Beauty Salon">
        <div class="form-hint">
          <i class="fas fa-info-circle"></i>
          Choose a memorable and professional name
        </div>
      </div>

      <!-- Address -->
      <div class="form-group">
        <label class="form-label">
          <i class="fas fa-map-marker-alt"></i>
          Complete Address
          <span class="required">*</span>
        </label>
        <textarea
          name="address"
          id="salonAddress"
          class="form-textarea"
          required
          maxlength="300"
          placeholder="Enter your complete salon address including street, city, and postal code"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
        <div class="form-hint">
          <i class="fas fa-info-circle"></i>
          Make it easy for customers to find you
        </div>
      </div>

      <!-- Image Upload -->
      <div class="image-upload-section">
        <label class="form-label">
          <i class="fas fa-camera"></i>
          Salon Image (Optional)
        </label>

        <div class="upload-area" onclick="document.getElementById('imageInput').click()">
          <div class="upload-icon">
            <i class="fas fa-cloud-upload-alt"></i>
          </div>
          <div class="upload-text">Click to upload or drag and drop</div>
          <div class="upload-hint">JPG, PNG, GIF or WebP (Max 5MB)</div>
        </div>

        <input
          type="file"
          name="image"
          id="imageInput"
          class="file-input"
          accept="image/*">

        <div class="image-preview" id="imagePreview">
          <img id="previewImg" class="preview-image" alt="Preview">
          <br>
          <button type="button" class="remove-image" onclick="removeImage()">
            <i class="fas fa-trash me-2"></i>Remove Image
          </button>
        </div>
      </div>

      <!-- Form Actions -->
      <div class="form-actions">
        <a href="dashboard.php" class="btn-cancel">
          <i class="fas fa-times"></i>
          Cancel
        </a>
        <button type="submit" class="btn-submit" id="submitBtn">
          <span class="spinner"></span>
          <i class="fas fa-plus-circle"></i>
          Add Salon
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Page-specific Scripts -->
<script>
  // Live preview update
  const salonName = document.getElementById('salonName');
  const salonAddress = document.getElementById('salonAddress');
  const previewName = document.getElementById('previewName');
  const previewAddress = document.getElementById('previewAddress');

  salonName.addEventListener('input', function() {
    previewName.textContent = this.value || 'Your Salon Name';
  });

  salonAddress.addEventListener('input', function() {
    previewAddress.textContent = this.value || 'Salon Address';
  });

  // Image preview
  const imageInput = document.getElementById('imageInput');
  const imagePreview = document.getElementById('imagePreview');
  const previewImg = document.getElementById('previewImg');

  imageInput.addEventListener('change', function(e) {
    const file = e.target.files[0];

    if (file) {
      // Validate file size
      if (file.size > 5 * 1024 * 1024) {
        alert('Image size exceeds 5MB');
        this.value = '';
        return;
      }

      const reader = new FileReader();
      reader.onload = function(e) {
        previewImg.src = e.target.result;
        imagePreview.classList.add('show');
      };
      reader.readAsDataURL(file);
    }
  });

  function removeImage() {
    imageInput.value = '';
    imagePreview.classList.remove('show');
    previewImg.src = '';
  }

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
  const inputs = document.querySelectorAll('.form-control, .form-textarea');
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

  // Drag and drop functionality
  const uploadArea = document.querySelector('.upload-area');

  ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    uploadArea.addEventListener(eventName, preventDefaults, false);
  });

  function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
  }

  ['dragenter', 'dragover'].forEach(eventName => {
    uploadArea.addEventListener(eventName, () => {
      uploadArea.style.background = 'rgba(233, 30, 99, 0.1)';
    }, false);
  });

  ['dragleave', 'drop'].forEach(eventName => {
    uploadArea.addEventListener(eventName, () => {
      uploadArea.style.background = 'transparent';
    }, false);
  });

  uploadArea.addEventListener('drop', function(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    imageInput.files = files;

    const event = new Event('change', {
      bubbles: true
    });
    imageInput.dispatchEvent(event);
  }, false);
</script>

<?php include __DIR__ . '/../footer.php'; ?>