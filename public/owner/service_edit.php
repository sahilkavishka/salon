<?php
session_start();
require_once '../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];
$service_id = intval($_GET['id'] ?? 0);

if ($service_id <= 0) {
    $_SESSION['flash_error'] = 'Service ID is missing.';
    header('Location: dashboard.php');
    exit;
}

// Fetch the service and ensure it belongs to a salon owned by this owner
$stmt = $pdo->prepare("
    SELECT svc.*, s.name AS salon_name
    FROM services svc
    JOIN salons s ON svc.salon_id = s.id
    WHERE svc.id = ? AND s.owner_id = ?
");
$stmt->execute([$service_id, $owner_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    $_SESSION['flash_error'] = 'Service not found or unauthorized.';
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
        $stmt = $pdo->prepare("UPDATE services SET name=?, description=?, price=?, duration=? WHERE id=?");
        $stmt->execute([$name, $description, $price, $duration, $service_id]);

        $_SESSION['flash_success'] = "Service updated successfully.";
        header("Location: services.php?salon_id={$service['salon_id']}");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Service - <?= htmlspecialchars($service['salon_name']) ?> | Salonora</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/service_edit.css">

  
  
</head>
<body>
  

  <!-- Page Header -->
  <div class="page-header">
    <div class="container">
      <div class="page-header-content">
        <h1 class="page-title">Edit Service</h1>
        <p class="page-subtitle">
          <i class="fas fa-store"></i>
          <?= htmlspecialchars($service['salon_name']) ?>
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
          <i class="fas fa-edit"></i>
        </div>
        <h2 class="form-title">Update Service Details</h2>
        <p class="form-description">Modify the information for this service</p>
      </div>

      <!-- Preview Card -->
      <div class="preview-card">
        <div class="preview-header">
          <div class="preview-icon">
            <i class="fas fa-scissors"></i>
          </div>
          <div>
            <div class="preview-title" id="previewName"><?= htmlspecialchars($service['name']) ?></div>
            <div class="preview-details">
              <div class="preview-item">
                <i class="fas fa-tag"></i>
                <span>Rs <strong id="previewPrice"><?= number_format($service['price'], 2) ?></strong></span>
              </div>
              <div class="preview-item">
                <i class="far fa-clock"></i>
                <span><strong id="previewDuration"><?= $service['duration'] ?: '—' ?></strong> mins</span>
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
            value="<?= htmlspecialchars($service['name']) ?>" 
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
            placeholder="Describe what's included in this service..."><?= htmlspecialchars($service['description']) ?></textarea>
          <div class="form-hint">
            <i class="fas fa-info-circle"></i>
            Help customers understand what to expect
          </div>
          <span class="char-counter">
            <span id="charCount"><?= strlen($service['description']) ?></span>/500
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
                  value="<?= htmlspecialchars($service['price']) ?>" 
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
                value="<?= htmlspecialchars($service['duration']) ?>" 
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
          <a href="services.php?salon_id=<?= $service['salon_id'] ?>" class="btn-cancel">
            <i class="fas fa-times"></i>
            Cancel
          </a>
          <button type="submit" class="btn-submit" id="submitBtn">
            <span class="spinner"></span>
            <i class="fas fa-save"></i>
            Update Service
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Character counter
    const descTextarea = document.getElementById('serviceDescription');
    const charCount = document.getElementById('charCount');

    descTextarea.addEventListener('input', function() {
      charCount.textContent = this.value.length;
    });

    // Live preview update
    const serviceName = document.getElementById('serviceName');
    const servicePrice = document.getElementById('servicePrice');
    const serviceDuration = document.getElementById('serviceDuration');
    const previewName = document.getElementById('previewName');
    const previewPrice = document.getElementById('previewPrice');
    const previewDuration = document.getElementById('previewDuration');

    serviceName.addEventListener('input', function() {
      previewName.textContent = this.value || 'Service Name';
    });

    servicePrice.addEventListener('input', function() {
      const price = parseFloat(this.value) || 0;
      previewPrice.textContent = price.toFixed(2);
    });

    serviceDuration.addEventListener('input', function() {
      previewDuration.textContent = this.value || '—';
    });

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
  </script>
</body>
</html>