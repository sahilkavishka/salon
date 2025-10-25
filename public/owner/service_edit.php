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
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
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
      max-width: 800px;
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

    .input-group {
      position: relative;
    }

    .input-prefix {
      position: absolute;
      left: 1.25rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-light);
      font-weight: 600;
      pointer-events: none;
    }

    .form-control.with-prefix {
      padding-left: 3rem;
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

    /* Character Counter */
    .char-counter {
      position: absolute;
      right: 0.75rem;
      bottom: -1.75rem;
      font-size: 0.8rem;
      color: var(--text-light);
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

    /* Service Preview Card */
    .preview-card {
      background: linear-gradient(135deg, rgba(233, 30, 99, 0.05) 0%, rgba(156, 39, 176, 0.05) 100%);
      border-radius: 16px;
      padding: 2rem;
      margin-bottom: 2rem;
      border: 2px dashed var(--primary);
    }

    .preview-header {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .preview-icon {
      width: 50px;
      height: 50px;
      background: var(--gradient-primary);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.25rem;
    }

    .preview-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--text-dark);
    }

    .preview-details {
      display: flex;
      gap: 2rem;
      margin-top: 1rem;
    }

    .preview-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--text-light);
    }

    .preview-item i {
      color: var(--primary);
    }

    .preview-item strong {
      color: var(--text-dark);
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

      .preview-details {
        flex-direction: column;
        gap: 0.75rem;
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