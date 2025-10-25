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
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload error.';
        } elseif (!in_array(mime_content_type($file['tmp_name']), $allowed, true)) {
            $errors[] = 'Only JPG, PNG, GIF or WebP images allowed.';
        } elseif ($file['size'] > 5*1024*1024) {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add New Salon - Salonora</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    :root {
      --primary: #e91e63;
      --primary-dark: #c2185b;
      --secondary: #9c27b0;
      --accent: #ff6b9d;
      --dark: #1a1a2e;
      --light: #f5f7fa;
      --text-dark: #2d3436;
      --text-light: #636e72;
      --gradient-primary: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
      --gradient-secondary: linear-gradient(135deg, #ff6b9d 0%, #c471ed 100%);
      --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
      --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.12);
      --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.15);
      --shadow-xl: 0 20px 60px rgba(0, 0, 0, 0.2);
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: var(--light);
      color: var(--text-dark);
    }

    /* Navbar */
    .navbar {
      background: white !important;
      box-shadow: var(--shadow-sm);
      padding: 1rem 0;
    }

    .navbar-brand {
      font-size: 1.5rem;
      font-weight: 800;
      background: var(--gradient-primary);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .navbar-brand i {
      background: var(--gradient-primary);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    /* Page Header */
    .page-header {
      background: var(--gradient-primary);
      padding: 3rem 0;
      margin-bottom: 3rem;
      position: relative;
      overflow: hidden;
    }

    .page-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.1)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,144C960,149,1056,139,1152,122.7C1248,107,1344,85,1392,74.7L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
      opacity: 0.5;
    }

    .page-header-content {
      position: relative;
      z-index: 2;
      text-align: center;
    }

    .page-title {
      font-size: 2rem;
      font-weight: 800;
      color: white;
      margin-bottom: 0.5rem;
    }

    .page-subtitle {
      font-size: 1rem;
      color: rgba(255, 255, 255, 0.9);
    }

    /* Alert Styling */
    .alert {
      border: none;
      border-radius: 16px;
      padding: 1.25rem 1.5rem;
      margin-bottom: 2rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      animation: slideIn 0.3s ease;
    }

    .alert i {
      font-size: 1.5rem;
    }

    .alert-danger {
      background: linear-gradient(135deg, #d63031 0%, #e17055 100%);
      color: white;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Form Container */
    .form-container {
      max-width: 900px;
      margin: 0 auto;
      background: white;
      border-radius: 24px;
      padding: 3rem;
      box-shadow: var(--shadow-lg);
      position: relative;
      overflow: hidden;
    }

    .form-container::before {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      width: 200px;
      height: 200px;
      background: var(--gradient-primary);
      opacity: 0.05;
      border-radius: 50%;
      transform: translate(50%, -50%);
    }

    .form-header {
      text-align: center;
      margin-bottom: 2.5rem;
      position: relative;
    }

    .form-icon {
      width: 80px;
      height: 80px;
      background: var(--gradient-primary);
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      font-size: 2rem;
      color: white;
      box-shadow: var(--shadow-md);
    }

    .form-title {
      font-size: 1.8rem;
      font-weight: 800;
      color: var(--text-dark);
      margin-bottom: 0.5rem;
    }

    .form-description {
      color: var(--text-light);
      font-size: 1rem;
    }

    /* Steps Indicator */
    .steps-indicator {
      display: flex;
      justify-content: center;
      gap: 1rem;
      margin-bottom: 2rem;
    }

    .step {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.75rem 1.5rem;
      background: #f8f9fa;
      border-radius: 50px;
      color: var(--text-light);
      font-weight: 600;
      font-size: 0.9rem;
    }

    .step.active {
      background: var(--gradient-primary);
      color: white;
    }

    .step i {
      font-size: 1rem;
    }

    /* Form Groups */
    .form-group {
      margin-bottom: 2rem;
      position: relative;
    }

    .form-label {
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 0.75rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.95rem;
    }

    .form-label i {
      color: var(--primary);
      font-size: 0.9rem;
    }

    .form-label .required {
      color: #e74c3c;
      margin-left: 0.25rem;
    }

    .form-control, .form-textarea {
      border: 2px solid #e9ecef;
      border-radius: 12px;
      padding: 1rem 1.25rem;
      font-size: 1rem;
      transition: var(--transition);
      width: 100%;
    }

    .form-control:focus, .form-textarea:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
      outline: none;
    }

    .form-textarea {
      min-height: 120px;
      resize: vertical;
      font-family: 'Poppins', sans-serif;
    }

    .form-hint {
      font-size: 0.85rem;
      color: var(--text-light);
      margin-top: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .form-hint i {
      font-size: 0.75rem;
    }

    /* Image Upload Section */
    .image-upload-section {
      background: linear-gradient(135deg, rgba(233, 30, 99, 0.05) 0%, rgba(156, 39, 176, 0.05) 100%);
      border-radius: 16px;
      padding: 2rem;
      margin-bottom: 2rem;
      border: 2px dashed var(--primary);
    }

    .upload-area {
      text-align: center;
      padding: 2rem;
      cursor: pointer;
      transition: var(--transition);
    }

    .upload-area:hover {
      background: rgba(233, 30, 99, 0.05);
    }

    .upload-icon {
      width: 80px;
      height: 80px;
      background: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1rem;
      color: var(--primary);
      font-size: 2rem;
    }

    .upload-text {
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 0.5rem;
    }

    .upload-hint {
      color: var(--text-light);
      font-size: 0.9rem;
    }

    .file-input {
      display: none;
    }

    .image-preview {
      display: none;
      margin-top: 1.5rem;
      text-align: center;
    }

    .image-preview.show {
      display: block;
    }

    .preview-image {
      max-width: 300px;
      max-height: 300px;
      border-radius: 12px;
      box-shadow: var(--shadow-md);
      margin-bottom: 1rem;
    }

    .remove-image {
      background: #e74c3c;
      color: white;
      border: none;
      padding: 0.5rem 1.5rem;
      border-radius: 50px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
    }

    .remove-image:hover {
      background: #c0392b;
    }

    /* Preview Card */
    .preview-card {
      background: linear-gradient(135deg, rgba(233, 30, 99, 0.05) 0%, rgba(156, 39, 176, 0.05) 100%);
      border-radius: 16px;
      padding: 2rem;
      margin-bottom: 2rem;
      border: 2px dashed var(--primary);
    }

    .preview-title {
      font-size: 1rem;
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .preview-title i {
      color: var(--primary);
    }

    .preview-content h3 {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 0.5rem;
    }

    .preview-content p {
      color: var(--text-light);
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin: 0;
    }

    .preview-content i {
      color: var(--primary);
    }

    /* Form Actions */
    .form-actions {
      display: flex;
      gap: 1rem;
      margin-top: 3rem;
      padding-top: 2rem;
      border-top: 1px solid #e9ecef;
    }

    .btn-submit {
      flex: 1;
      background: var(--gradient-primary);
      color: white;
      border: none;
      padding: 1rem 2rem;
      border-radius: 12px;
      font-weight: 700;
      font-size: 1.05rem;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
    }

    .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(233, 30, 99, 0.4);
    }

    .btn-cancel {
      flex: 1;
      background: #e9ecef;
      color: var(--text-dark);
      border: none;
      padding: 1rem 2rem;
      border-radius: 12px;
      font-weight: 700;
      font-size: 1.05rem;
      transition: var(--transition);
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
    }

    .btn-cancel:hover {
      background: #dee2e6;
      color: var(--text-dark);
      transform: translateY(-2px);
    }

    /* Responsive */
    @media (max-width: 768px) {
      .form-container {
        padding: 2rem 1.5rem;
      }

      .page-title {
        font-size: 1.5rem;
      }

      .form-actions {
        flex-direction: column;
      }

      .btn-submit, .btn-cancel {
        width: 100%;
      }

      .steps-indicator {
        flex-direction: column;
      }
    }

    /* Loading State */
    .btn-submit:disabled {
      opacity: 0.7;
      cursor: not-allowed;
    }

    .btn-submit .spinner {
      display: none;
      width: 20px;
      height: 20px;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-top-color: white;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }

    .btn-submit.loading .spinner {
      display: block;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg">
    <div class="container">
      <a class="navbar-brand" href="../../index.php">
        <i class="fas fa-spa"></i> Salonora
      </a>
    </div>
  </nav>

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

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
      
      const event = new Event('change', { bubbles: true });
      imageInput.dispatchEvent(event);
    }, false);
  </script>
</body>
</html>