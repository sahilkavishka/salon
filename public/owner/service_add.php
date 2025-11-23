<?php
session_start();
require_once '../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];
$salon_id = intval($_GET['salon_id'] ?? 0);

if ($salon_id <= 0) {
    $_SESSION['flash_error'] = 'Salon not specified.';
    header('Location: dashboard.php');
    exit;
}

// Verify the salon belongs to the owner
$stmt = $pdo->prepare("SELECT id, name FROM salons WHERE id=? AND owner_id=?");
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
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $duration = trim($_POST['duration'] ?? '');

    if ($name === '') $errors[] = "Service name is required.";
    if ($price === '' || !is_numeric($price) || $price <= 0) $errors[] = "Valid price is required.";
    if ($duration !== '' && (!is_numeric($duration) || $duration <= 0)) $errors[] = "Duration must be a positive number.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO services (salon_id, name, description, price, duration) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$salon_id, $name, $description, $price, $duration]);

        $_SESSION['flash_success'] = "Service added successfully.";
        header("Location: services.php?salon_id=$salon_id");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Service - <?= htmlspecialchars($salon['name']) ?> | Salonora</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <link rel="stylesheet" href="../assets/css/service_add.css">

 
</head>
<body>
  

  <!-- Page Header -->
  <div class="page-header">
    <div class="container">
      <div class="page-header-content">
        <h1 class="page-title">Add New Service</h1>
        <p class="page-subtitle">
          <i class="fas fa-store"></i>
          <?= htmlspecialchars($salon['name']) ?>
        </p>
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
          <i class="fas fa-plus"></i>
        </div>
        <h2 class="form-title">Create New Service</h2>
        <p class="form-description">Add a new service to attract more customers</p>
      </div>

      <!-- Quick Templates -->
      <div class="templates-section">
        <h3 class="templates-title">
          <i class="fas fa-magic"></i>
          Quick Templates
        </h3>
        <div class="templates-grid">
          <div class="template-card" onclick="applyTemplate('Haircut', 'Professional haircut and styling', 1500, 30)">
            <i class="fas fa-cut"></i>
            <div class="template-name">Haircut</div>
          </div>
          <div class="template-card" onclick="applyTemplate('Hair Coloring', 'Full head color treatment', 3500, 90)">
            <i class="fas fa-paint-brush"></i>
            <div class="template-name">Coloring</div>
          </div>
          <div class="template-card" onclick="applyTemplate('Facial Treatment', 'Deep cleansing facial', 2500, 45)">
            <i class="fas fa-spa"></i>
            <div class="template-name">Facial</div>
          </div>
          <div class="template-card" onclick="applyTemplate('Manicure', 'Hand care and nail polish', 800, 30)">
            <i class="fas fa-hand-sparkles"></i>
            <div class="template-name">Manicure</div>
          </div>
          <div class="template-card" onclick="applyTemplate('Pedicure', 'Foot care and nail treatment', 1000, 45)">
            <i class="fas fa-shoe-prints"></i>
            <div class="template-name">Pedicure</div>
          </div>
          <div class="template-card" onclick="applyTemplate('Makeup', 'Professional makeup application', 2000, 60)">
            <i class="fas fa-magic"></i>
            <div class="template-name">Makeup</div>
          </div>
        </div>
      </div>

      <!-- Preview Card -->
      <div class="preview-card">
        <div class="preview-header">
          <div class="preview-icon">
            <i class="fas fa-scissors"></i>
          </div>
          <div>
            <div class="preview-title" id="previewName">Service Name</div>
            <div class="preview-details">
              <div class="preview-item">
                <i class="fas fa-tag"></i>
                <span>Rs <strong id="previewPrice">0.00</strong></span>
              </div>
              <div class="preview-item">
                <i class="far fa-clock"></i>
                <span><strong id="previewDuration">—</strong> mins</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <form method="POST" id="serviceForm">
        <!-- Service Name -->
        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-signature"></i>
            Service Name
            <span class="required">*</span>
          </label>
          <input 
            type="text" 
            name="name" 
            id="serviceName"
            class="form-control" 
            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
            required
            maxlength="100"
            placeholder="e.g., Haircut & Styling">
          <div class="form-hint">
            <i class="fas fa-info-circle"></i>
            Choose a clear and descriptive name for your service
          </div>
        </div>

        <!-- Description -->
        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-align-left"></i>
            Description
          </label>
          <textarea 
            name="description" 
            id="serviceDescription"
            class="form-textarea"
            maxlength="500"
            placeholder="Describe what's included in this service..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
          <div class="form-hint">
            <i class="fas fa-info-circle"></i>
            Help customers understand what to expect
          </div>
          <span class="char-counter">
            <span id="charCount">0</span>/500
          </span>
        </div>

        <!-- Price and Duration Row -->
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">
                <i class="fas fa-tag"></i>
                Price
                <span class="required">*</span>
              </label>
              <div class="input-group">
                <span class="input-prefix">Rs</span>
                <input 
                  type="number" 
                  name="price" 
                  id="servicePrice"
                  class="form-control with-prefix" 
                  value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" 
                  step="0.01"
                  min="0"
                  required
                  placeholder="0.00">
              </div>
              <div class="form-hint">
                <i class="fas fa-info-circle"></i>
                Set a competitive price for your service
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">
                <i class="far fa-clock"></i>
                Duration (minutes)
              </label>
              <input 
                type="number" 
                name="duration" 
                id="serviceDuration"
                class="form-control" 
                value="<?= htmlspecialchars($_POST['duration'] ?? '') ?>" 
                min="1"
                placeholder="e.g., 30">
              <div class="form-hint">
                <i class="fas fa-info-circle"></i>
                How long does this service take?
              </div>
            </div>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
          <a href="services.php?salon_id=<?= $salon_id ?>" class="btn-cancel">
            <i class="fas fa-times"></i>
            Cancel
          </a>
          <button type="submit" class="btn-submit" id="submitBtn">
            <span class="spinner"></span>
            <i class="fas fa-plus-circle"></i>
            Add Service
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Apply template function
    function applyTemplate(name, description, price, duration) {
      document.getElementById('serviceName').value = name;
      document.getElementById('serviceDescription').value = description;
      document.getElementById('servicePrice').value = price;
      document.getElementById('serviceDuration').value = duration;
      
      // Update preview
      updatePreview();
      
      // Scroll to form
      document.getElementById('serviceName').scrollIntoView({ behavior: 'smooth', block: 'center' });
      document.getElementById('serviceName').focus();
    }

    // Character counter
    const descTextarea = document.getElementById('serviceDescription');
    const charCount = document.getElementById('charCount');

    descTextarea.addEventListener('input', function() {
      charCount.textContent = this.value.length;
      updatePreview();
    });

    // Live preview update function
    function updatePreview() {
      const name = document.getElementById('serviceName').value || 'Service Name';
      const price = parseFloat(document.getElementById('servicePrice').value) || 0;
      const duration = document.getElementById('serviceDuration').value || '—';
      
      document.getElementById('previewName').textContent = name;
      document.getElementById('previewPrice').textContent = price.toFixed(2);
      document.getElementById('previewDuration').textContent = duration;
    }

    // Live preview listeners
    document.getElementById('serviceName').addEventListener('input', updatePreview);
    document.getElementById('servicePrice').addEventListener('input', updatePreview);
    document.getElementById('serviceDuration').addEventListener('input', updatePreview);

    // Form submission with loading state
    const form = document.getElementById('serviceForm');
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

    // Initialize character count on page load
    charCount.textContent = descTextarea.value.length;
  </script>
</body>
</html>