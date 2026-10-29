<?php
// public/owner/salon_add.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];
$errors = [];

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    // Basic inputs
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $lat = $_POST['lat'] ?? null;
    $lng = $_POST['lng'] ?? null;
    $opening_time = $_POST['opening_time'] ?? '';
    $closing_time = $_POST['closing_time'] ?? '';
    $slot_duration = $_POST['slot_duration'] ?? '';
    
    // Additional fields
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
    if (!$lat || !$lng) $errors[] = 'Please select the salon location on the interactive map.';
    if ($opening_time === '') $errors[] = 'Opening time is required.';
    if ($closing_time === '') $errors[] = 'Closing time is required.';
    if ($slot_duration === '' || intval($slot_duration) <= 0) $errors[] = 'Slot duration must be a positive number.';
    
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
        $errors[] = 'Invalid website URL format (must include http:// or https://).';
    }

    // Image upload
    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error uploading salon image.';
        } elseif (!in_array(mime_content_type($file['tmp_name']), $allowed, true)) {
            $errors[] = 'Only JPG, PNG, GIF, or WebP images are allowed.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Image size exceeds maximum limit of 5MB.';
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
        $daysSelected = isset($_POST['operating_days']) && is_array($_POST['operating_days']) 
            ? $_POST['operating_days'] 
            : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $operating_days = implode(',', array_map('trim', $daysSelected));

        $stmt = $pdo->prepare("
            INSERT INTO salons 
            (owner_id, name, address, lat, lng, image, opening_time, closing_time, slot_duration, phone, email, description, website, facebook, instagram, parking_available, wheelchair_accessible, wifi_available, air_conditioned, operating_days) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $owner_id, $name, $address, $lat, $lng, $imagePath, 
            $opening_time, $closing_time, $slot_duration, $phone, 
            $email, $description, $website, $facebook, $instagram, 
            $parking_available, $wheelchair_accessible, $wifi_available, 
            $air_conditioned, $operating_days
        ]);

        $_SESSION['flash_success'] = 'Salon added successfully! Clients can now discover and book your services.';
        header('Location: dashboard.php');
        exit;
    }
}

// Default operating days
$defaultDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$selectedDays = isset($_POST['operating_days']) && is_array($_POST['operating_days']) ? $_POST['operating_days'] : $defaultDays;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Your Salon - Salonora Partner</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Leaflet Map CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

    <script>
        if (localStorage.getItem('salonora_theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <style>
        :root {
            --primary: #e91e63;
            --primary-dark: #c2185b;
            --secondary: #9c27b0;
            --accent: #ff6b9d;
            --gradient-primary: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
            --gradient-secondary: linear-gradient(135deg, #ff6b9d 0%, #c471ed 100%);
            --gradient-card: linear-gradient(135deg, #fdf2f8 0%, #f5f3ff 100%);
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 14px 35px rgba(233, 30, 99, 0.15);
            --border-radius: 20px;
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
        }

        /* Top Header Nav */
        .partner-nav {
            background: #ffffff;
            box-shadow: var(--shadow-sm);
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .partner-logo {
            font-size: 1.4rem;
            font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        /* Hero Banner */
        .page-hero {
            background: linear-gradient(135deg, rgba(233, 30, 99, 0.08) 0%, rgba(156, 39, 176, 0.08) 100%);
            border-radius: var(--border-radius);
            padding: 2.2rem 2rem;
            margin-bottom: 2.5rem;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(233, 30, 99, 0.15);
        }

        .page-hero::after {
            content: '';
            position: absolute;
            right: -30px;
            bottom: -30px;
            width: 180px;
            height: 180px;
            background: var(--gradient-primary);
            opacity: 0.1;
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: white;
            color: var(--primary);
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            box-shadow: var(--shadow-sm);
            margin-bottom: 0.8rem;
        }

        /* Form Card */
        .form-section-card {
            background: #ffffff;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 1.8rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid #e2e8f0;
            transition: var(--transition);
        }

        .form-section-card:hover {
            box-shadow: var(--shadow-md);
            border-color: #cbd5e1;
        }

        .section-header-title {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .section-step-num {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: var(--gradient-primary);
            color: #ffffff;
            font-weight: 800;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(233, 30, 99, 0.3);
            flex-shrink: 0;
        }

        .section-header-title h4 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
        }

        .section-header-title p {
            margin: 0;
            font-size: 0.85rem;
            color: #64748b;
        }

        /* Form Inputs */
        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #334155;
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: var(--transition);
            color: #1e293b;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(233, 30, 99, 0.15);
            outline: none;
        }

        /* Map styling */
        #salonMap {
            height: 380px;
            border-radius: 16px;
            border: 2px solid #e2e8f0;
            box-shadow: var(--shadow-sm);
            z-index: 1;
        }

        .location-info-badge {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
            padding: 0.6rem 1rem;
            border-radius: 12px;
            font-size: 0.88rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        /* Operating Days Chip Selector */
        .days-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .day-chip {
            cursor: pointer;
            user-select: none;
        }

        .day-chip input {
            display: none;
        }

        .day-chip-label {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.6rem 1.1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            border: 2px solid #e2e8f0;
            background: #ffffff;
            color: #64748b;
            transition: var(--transition);
        }

        .day-chip input:checked + .day-chip-label {
            background: var(--gradient-primary);
            color: #ffffff;
            border-color: transparent;
            box-shadow: 0 4px 12px rgba(233, 30, 99, 0.3);
            transform: translateY(-2px);
        }

        /* Modern Amenities Card Selector */
        .amenity-card-option {
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            padding: 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.85rem;
            background: #ffffff;
            height: 100%;
        }

        .amenity-card-option:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .amenity-card-option.active {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(233, 30, 99, 0.05) 0%, rgba(156, 39, 176, 0.05) 100%);
        }

        .amenity-icon-box {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #fdf2f8;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
            transition: var(--transition);
        }

        .amenity-card-option.active .amenity-icon-box {
            background: var(--gradient-primary);
            color: #ffffff;
        }

        .amenity-text strong {
            display: block;
            font-size: 0.92rem;
            color: #0f172a;
        }

        .amenity-text small {
            font-size: 0.78rem;
            color: #64748b;
        }

        /* Drag-and-drop Photo Box */
        .upload-dropzone {
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            padding: 2.5rem 1.5rem;
            text-align: center;
            cursor: pointer;
            background: #f8fafc;
            transition: var(--transition);
        }

        .upload-dropzone:hover {
            border-color: var(--primary);
            background: #fdf2f8;
        }

        .upload-icon-circle {
            width: 65px;
            height: 65px;
            border-radius: 50%;
            background: white;
            color: var(--primary);
            font-size: 1.8rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
        }

        /* Live Preview Card (Sticky) */
        .live-preview-wrapper {
            position: sticky;
            top: 90px;
        }

        .preview-header-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--primary);
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .client-preview-card {
            background: #ffffff;
            border-radius: 22px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: var(--transition);
        }

        .preview-img-top {
            height: 200px;
            width: 100%;
            background: var(--gradient-primary);
            position: relative;
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .preview-overlay-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(8px);
            color: #ffffff;
            padding: 0.35rem 0.85rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .preview-card-body {
            padding: 1.5rem;
        }

        .preview-card-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.4rem;
        }

        .preview-meta-line {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 0.4rem;
        }

        .preview-amenities-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-top: 1rem;
            margin-bottom: 1.2rem;
        }

        .preview-tag {
            background: #f1f5f9;
            color: #475569;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.65rem;
            border-radius: 6px;
        }

        .btn-preview-book {
            width: 100%;
            background: var(--gradient-primary);
            color: white !important;
            border-radius: 50px;
            font-weight: 700;
            padding: 0.75rem;
            text-align: center;
            display: block;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.35);
        }

        /* Buttons */
        .btn-save-salon {
            background: var(--gradient-primary);
            color: white !important;
            font-weight: 700;
            font-size: 1.1rem;
            padding: 0.95rem 2.5rem;
            border-radius: 50px;
            border: none;
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.4);
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
        }

        .btn-save-salon:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(233, 30, 99, 0.55);
        }

        /* ========================================================
           DARK MODE SUPPORT
           ======================================================== */
        html.dark-mode, body.dark-mode {
            background-color: #0b0b14 !important;
            color: #f1f5f9 !important;
        }

        body.dark-mode .partner-nav {
            background: #141426 !important;
            border-color: #262640 !important;
        }

        body.dark-mode .page-hero {
            background: linear-gradient(135deg, rgba(233, 30, 99, 0.15) 0%, rgba(156, 39, 176, 0.15) 100%) !important;
            border-color: rgba(233, 30, 99, 0.25) !important;
        }

        body.dark-mode .hero-badge {
            background: #1e1e38 !important;
            color: #f472b6 !important;
        }

        body.dark-mode .form-section-card,
        body.dark-mode .client-preview-card {
            background: #141426 !important;
            border-color: #262640 !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4) !important;
        }

        body.dark-mode .section-header-title {
            border-color: #262640 !important;
        }

        body.dark-mode .section-header-title h4,
        body.dark-mode .preview-card-title,
        body.dark-mode .amenity-text strong {
            color: #ffffff !important;
        }

        body.dark-mode .section-header-title p,
        body.dark-mode .preview-meta-line,
        body.dark-mode .amenity-text small {
            color: #94a3b8 !important;
        }

        body.dark-mode .form-label {
            color: #e2e8f0 !important;
        }

        body.dark-mode .form-control,
        body.dark-mode .form-select,
        body.dark-mode textarea {
            background-color: #1a1a32 !important;
            color: #ffffff !important;
            border-color: #313154 !important;
        }

        body.dark-mode .form-control:focus,
        body.dark-mode .form-select:focus {
            border-color: var(--primary) !important;
            background-color: #20203d !important;
        }

        body.dark-mode .amenity-card-option {
            background: #1a1a32 !important;
            border-color: #313154 !important;
        }

        body.dark-mode .amenity-card-option:hover {
            border-color: var(--primary) !important;
            background: #252547 !important;
        }

        body.dark-mode .amenity-card-option.active {
            border-color: var(--primary) !important;
            background: #261f3d !important;
        }

        body.dark-mode .day-chip-label {
            background: #1a1a32 !important;
            border-color: #313154 !important;
            color: #cbd5e1 !important;
        }

        body.dark-mode .upload-dropzone {
            background: #1a1a32 !important;
            border-color: #313154 !important;
        }

        body.dark-mode .upload-icon-circle {
            background: #252547 !important;
            color: #f472b6 !important;
        }

        body.dark-mode .location-info-badge {
            background: #142b1f !important;
            border-color: #22543d !important;
            color: #86efac !important;
        }

        body.dark-mode .preview-tag {
            background: #222240 !important;
            color: #cbd5e1 !important;
        }

        body.dark-mode .btn-outline-secondary {
            color: #cbd5e1 !important;
            border-color: #3b3b5e !important;
            background: #1a1a32 !important;
        }
    </style>
</head>
<body>

    <!-- Sticky Navigation Bar -->
    <header class="partner-nav">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="dashboard.php" class="partner-logo">
                <i class="fas fa-spa"></i> Salonora <span class="badge bg-primary fs-6 fw-normal px-2 py-1 ms-1 rounded-pill">Owner</span>
            </a>
            
            <div class="d-flex align-items-center gap-2">
                <button type="button" id="themeToggleBtn" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm" title="Toggle Dark/Light Mode">
                    <i class="fas fa-moon me-1" id="themeToggleIcon"></i> Theme
                </button>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
                    <i class="fas fa-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </header>

    <div class="container py-4">
        
        <!-- Hero Header -->
        <div class="page-hero">
            <span class="hero-badge">
                <i class="fas fa-sparkles"></i> Grow Your Business
            </span>
            <h1 class="display-6 fw-bold mb-2">Register Your Salon</h1>
            <p class="text-muted fs-6 mb-0" style="max-width: 680px;">
                Showcase your services to nearby clients, set up your schedule, and begin receiving automated 24/7 appointments with instant QR passes.
            </p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4 p-3" role="alert">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="fas fa-exclamation-circle fa-lg text-danger"></i>
                    <strong class="fs-6">Please resolve the following items:</strong>
                </div>
                <ul class="mb-0 ps-4">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Form & Live Preview Row -->
        <form method="post" enctype="multipart/form-data" id="salonForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div class="row g-4">
                
                <!-- Left Column: Form Cards (col-lg-8) -->
                <div class="col-lg-8">
                    
                    <!-- Section 1: Basic Information -->
                    <div class="form-section-card">
                        <div class="section-header-title">
                            <div class="section-step-num">01</div>
                            <div>
                                <h4>General Information</h4>
                                <p>Basic identity and contact details for your salon</p>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label"><i class="fas fa-store text-primary"></i> Salon Name *</label>
                                <input type="text" name="name" id="salonName" class="form-control form-control-lg" required 
                                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                       placeholder="e.g., Luxe Glow Beauty Lounge">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label"><i class="fas fa-phone text-primary"></i> Phone Number</label>
                                <input type="tel" name="phone" id="salonPhone" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                       placeholder="e.g., +94 77 123 4567">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label"><i class="fas fa-envelope text-primary"></i> Email Address</label>
                                <input type="email" name="email" id="salonEmail" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                       placeholder="e.g., contact@luxeglow.com">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label"><i class="fas fa-globe text-primary"></i> Official Website (Optional)</label>
                                <input type="url" name="website" id="salonWebsite" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['website'] ?? '') ?>"
                                       placeholder="https://www.luxeglowsalon.com">
                            </div>

                            <div class="col-md-12">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label mb-0"><i class="fas fa-align-left text-primary"></i> Description & Story</label>
                                    <small class="text-muted" id="descCounter">0/500</small>
                                </div>
                                <textarea name="description" id="salonDesc" class="form-control" rows="3" 
                                          maxlength="500" placeholder="Describe your salon ambiance, expertise, and what sets you apart..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Location & Leaflet Map -->
                    <div class="form-section-card">
                        <div class="section-header-title">
                            <div class="section-step-num">02</div>
                            <div>
                                <h4>Location & Interactive Map</h4>
                                <p>Pinpoint your salon so nearby customers can discover you</p>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-map-marker-alt text-danger"></i> Street Address *</label>
                            <div class="input-group">
                                <input type="text" name="address" id="salonAddress" class="form-control" required 
                                       placeholder="e.g., 123 Galle Road, Colombo 03"
                                       value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                                <button type="button" class="btn btn-outline-primary px-3 fw-semibold" id="geocodeBtn">
                                    <i class="fas fa-search-location me-1"></i> Locate on Map
                                </button>
                            </div>
                            <small class="text-muted">Type the address and click "Locate on Map", or simply click anywhere on the map to drop your pin.</small>
                        </div>

                        <div class="mb-2">
                            <div id="salonMap"></div>
                            <input type="hidden" name="lat" id="latInput" value="<?= htmlspecialchars($_POST['lat'] ?? '') ?>">
                            <input type="hidden" name="lng" id="lngInput" value="<?= htmlspecialchars($_POST['lng'] ?? '') ?>">
                        </div>

                        <div class="location-info-badge" id="locationInfo" style="<?= (!empty($_POST['lat']) && !empty($_POST['lng'])) ? 'display:inline-flex;' : 'display:none;' ?>">
                            <i class="fas fa-check-circle"></i>
                            <span>Coordinates locked: <strong id="coordsDisplay"><?= (!empty($_POST['lat']) && !empty($_POST['lng'])) ? htmlspecialchars($_POST['lat'] . ', ' . $_POST['lng']) : '' ?></strong></span>
                        </div>
                    </div>

                    <!-- Section 3: Operating Schedule & Booking Intervals -->
                    <div class="form-section-card">
                        <div class="section-header-title">
                            <div class="section-step-num">03</div>
                            <div>
                                <h4>Business Schedule</h4>
                                <p>Define your operating hours, appointment slot lengths, and working days</p>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-door-open text-primary"></i> Opening Time *</label>
                                <input type="time" name="opening_time" id="openingTime" class="form-control" required 
                                       value="<?= $_POST['opening_time'] ?? '09:00' ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-door-closed text-primary"></i> Closing Time *</label>
                                <input type="time" name="closing_time" id="closingTime" class="form-control" required 
                                       value="<?= $_POST['closing_time'] ?? '19:00' ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-stopwatch text-primary"></i> Slot Duration *</label>
                                <select name="slot_duration" id="slotDuration" class="form-select" required>
                                    <option value="15" <?= (($_POST['slot_duration'] ?? '30') == 15) ? 'selected' : '' ?>>15 Minutes</option>
                                    <option value="30" <?= (($_POST['slot_duration'] ?? '30') == 30) ? 'selected' : '' ?>>30 Minutes (Recommended)</option>
                                    <option value="45" <?= (($_POST['slot_duration'] ?? '30') == 45) ? 'selected' : '' ?>>45 Minutes</option>
                                    <option value="60" <?= (($_POST['slot_duration'] ?? '30') == 60) ? 'selected' : '' ?>>60 Minutes (1 Hour)</option>
                                    <option value="90" <?= (($_POST['slot_duration'] ?? '30') == 90) ? 'selected' : '' ?>>90 Minutes</option>
                                </select>
                            </div>
                        </div>

                        <!-- Weekly Operating Days Selector -->
                        <div>
                            <label class="form-label mb-2"><i class="fas fa-calendar-check text-primary"></i> Weekly Open Days</label>
                            <p class="text-muted small mb-3">Select the days your salon is open for appointments:</p>
                            
                            <div class="days-selector">
                                <?php foreach ($defaultDays as $day): ?>
                                    <label class="day-chip">
                                        <input type="checkbox" name="operating_days[]" value="<?= $day ?>" class="day-checkbox" 
                                               <?= in_array($day, $selectedDays) ? 'checked' : '' ?>>
                                        <span class="day-chip-label">
                                            <i class="fas fa-check me-1"></i> <?= $day ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Amenities & Features -->
                    <div class="form-section-card">
                        <div class="section-header-title">
                            <div class="section-step-num">04</div>
                            <div>
                                <h4>Salon Amenities</h4>
                                <p>Highlight the comfort facilities available at your salon</p>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="amenity-card-option <?= isset($_POST['parking_available']) ? 'active' : '' ?>" for="parking">
                                    <input type="checkbox" name="parking_available" id="parking" class="d-none amenity-check" 
                                           <?= isset($_POST['parking_available']) ? 'checked' : '' ?>>
                                    <div class="amenity-icon-box">
                                        <i class="fas fa-parking"></i>
                                    </div>
                                    <div class="amenity-text">
                                        <strong>Parking Available</strong>
                                        <small>Free or reserved customer parking</small>
                                    </div>
                                </label>
                            </div>

                            <div class="col-md-6">
                                <label class="amenity-card-option <?= isset($_POST['wifi_available']) ? 'active' : '' ?>" for="wifi">
                                    <input type="checkbox" name="wifi_available" id="wifi" class="d-none amenity-check" 
                                           <?= isset($_POST['wifi_available']) ? 'checked' : '' ?>>
                                    <div class="amenity-icon-box">
                                        <i class="fas fa-wifi"></i>
                                    </div>
                                    <div class="amenity-text">
                                        <strong>High-Speed WiFi</strong>
                                        <small>Complimentary guest internet</small>
                                    </div>
                                </label>
                            </div>

                            <div class="col-md-6">
                                <label class="amenity-card-option <?= isset($_POST['air_conditioned']) ? 'active' : '' ?>" for="ac">
                                    <input type="checkbox" name="air_conditioned" id="ac" class="d-none amenity-check" 
                                           <?= isset($_POST['air_conditioned']) ? 'checked' : '' ?>>
                                    <div class="amenity-icon-box">
                                        <i class="fas fa-snowflake"></i>
                                    </div>
                                    <div class="amenity-text">
                                        <strong>Air Conditioned</strong>
                                        <small>Fully climate-controlled salon</small>
                                    </div>
                                </label>
                            </div>

                            <div class="col-md-6">
                                <label class="amenity-card-option <?= isset($_POST['wheelchair_accessible']) ? 'active' : '' ?>" for="wheelchair">
                                    <input type="checkbox" name="wheelchair_accessible" id="wheelchair" class="d-none amenity-check" 
                                           <?= isset($_POST['wheelchair_accessible']) ? 'checked' : '' ?>>
                                    <div class="amenity-icon-box">
                                        <i class="fas fa-wheelchair"></i>
                                    </div>
                                    <div class="amenity-text">
                                        <strong>Wheelchair Accessible</strong>
                                        <small>Accessible entrance & stations</small>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Section 5: Social Media & Cover Photo -->
                    <div class="form-section-card">
                        <div class="section-header-title">
                            <div class="section-step-num">05</div>
                            <div>
                                <h4>Branding & Cover Photo</h4>
                                <p>Upload high-resolution photography and social links</p>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label"><i class="fab fa-facebook text-primary"></i> Facebook Page URL</label>
                                <input type="url" name="facebook" id="salonFacebook" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['facebook'] ?? '') ?>"
                                       placeholder="https://facebook.com/yoursalon">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><i class="fab fa-instagram text-danger"></i> Instagram Profile URL</label>
                                <input type="url" name="instagram" id="salonInstagram" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['instagram'] ?? '') ?>"
                                       placeholder="https://instagram.com/yoursalon">
                            </div>
                        </div>

                        <div>
                            <label class="form-label mb-2"><i class="fas fa-camera text-primary"></i> Main Salon Cover Image</label>
                            
                            <div class="upload-dropzone" onclick="document.getElementById('imageInput').click()">
                                <input type="file" name="image" id="imageInput" accept="image/*" class="d-none">
                                <div class="upload-icon-circle">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <h6 class="fw-bold mb-1">Click to browse or drag and drop your photo</h6>
                                <p class="text-muted small mb-0">Recommended size: 1200x800px (JPG, PNG, WebP up to 5MB)</p>
                            </div>

                            <div id="imagePreviewContainer" class="mt-3 p-2 bg-light rounded-3 d-flex align-items-center justify-content-between" style="display:none !important;">
                                <div class="d-flex align-items-center gap-3">
                                    <img id="previewThumbnail" style="width: 70px; height: 50px; object-fit: cover; border-radius: 8px;">
                                    <div>
                                        <strong id="previewFileName" class="d-block text-truncate" style="max-width: 250px;">salon_cover.jpg</strong>
                                        <small class="text-success"><i class="fas fa-check-circle"></i> Ready to upload</small>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="clearSelectedImage()">
                                    <i class="fas fa-trash me-1"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Action Controls -->
                    <div class="d-flex align-items-center justify-content-between gap-3 mt-4 mb-5 flex-wrap">
                        <a href="dashboard.php" class="btn btn-outline-secondary btn-lg rounded-pill px-4">
                            <i class="fas fa-arrow-left me-1"></i> Return to Dashboard
                        </a>
                        <button type="submit" class="btn-save-salon">
                            <i class="fas fa-check-circle"></i> Complete & Register Salon
                        </button>
                    </div>

                </div><!-- End Left Col -->

                <!-- Right Column: Live Sticky Customer Preview (col-lg-4) -->
                <div class="col-lg-4">
                    <div class="live-preview-wrapper">
                        
                        <div class="preview-header-badge">
                            <i class="fas fa-eye"></i> Real-time Client View
                        </div>

                        <!-- Card Mockup -->
                        <div class="client-preview-card">
                            <div class="preview-img-top" id="cardMockupImg">
                                <div class="text-center p-3" id="mockupEmptyState">
                                    <i class="fas fa-spa fa-3x mb-2 opacity-75"></i>
                                    <span class="d-block fw-semibold small">Upload a cover photo to preview</span>
                                </div>
                                <div class="preview-overlay-badge">
                                    <i class="fas fa-star text-warning me-1"></i> 5.0 (New)
                                </div>
                            </div>

                            <div class="preview-card-body">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="badge bg-success rounded-pill px-3 py-1">
                                        <i class="fas fa-clock me-1"></i> Open Today
                                    </span>
                                    <small class="text-muted fw-bold" id="mockupSlotTime">30 min slots</small>
                                </div>

                                <h3 class="preview-card-title text-truncate" id="mockupName">Your Salon Name</h3>
                                
                                <div class="preview-meta-line text-truncate">
                                    <i class="fas fa-map-marker-alt text-danger"></i>
                                    <span id="mockupAddress">123 Location Address...</span>
                                </div>

                                <div class="preview-meta-line" id="mockupPhoneLine" style="display:none;">
                                    <i class="fas fa-phone text-primary"></i>
                                    <span id="mockupPhone"></span>
                                </div>

                                <div class="preview-meta-line" id="mockupEmailLine" style="display:none;">
                                    <i class="fas fa-envelope text-primary"></i>
                                    <span id="mockupEmail"></span>
                                </div>

                                <p class="text-muted small my-2 text-truncate-2" id="mockupDesc" style="display:none;"></p>

                                <!-- Amenities list -->
                                <div class="preview-amenities-tags" id="mockupAmenitiesTags">
                                    <!-- Dynamic badges inserted via JS -->
                                </div>

                                <div class="pt-2 border-top">
                                    <a href="javascript:void(0)" class="btn-preview-book">
                                        <i class="fas fa-calendar-check me-2"></i> Book Appointment
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Guide Card -->
                        <div class="card border-0 rounded-4 shadow-sm mt-3 p-3 bg-white" style="border: 1px solid #e2e8f0 !important;">
                            <h6 class="fw-bold mb-2 text-dark"><i class="fas fa-lightbulb text-warning me-2"></i>Tips for Great Salons</h6>
                            <ul class="text-muted small ps-3 mb-0">
                                <li>Use high-lighting photos of your interior & styling stations.</li>
                                <li>Accurate map pinning helps clients get directions easily.</li>
                                <li>Select realistic slot durations to prevent customer delays.</li>
                            </ul>
                        </div>

                    </div>
                </div><!-- End Right Col -->

            </div>
        </form>

    </div><!-- End Container -->

    <?php include __DIR__ . '/../footer.php'; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

    <script>
        // Live Preview Bindings
        const salonName = document.getElementById('salonName');
        const salonAddress = document.getElementById('salonAddress');
        const salonPhone = document.getElementById('salonPhone');
        const salonEmail = document.getElementById('salonEmail');
        const salonDesc = document.getElementById('salonDesc');
        const slotDuration = document.getElementById('slotDuration');

        const mockupName = document.getElementById('mockupName');
        const mockupAddress = document.getElementById('mockupAddress');
        const mockupPhoneLine = document.getElementById('mockupPhoneLine');
        const mockupPhone = document.getElementById('mockupPhone');
        const mockupEmailLine = document.getElementById('mockupEmailLine');
        const mockupEmail = document.getElementById('mockupEmail');
        const mockupDesc = document.getElementById('mockupDesc');
        const mockupSlotTime = document.getElementById('mockupSlotTime');
        const mockupAmenitiesTags = document.getElementById('mockupAmenitiesTags');

        salonName.addEventListener('input', () => {
            mockupName.textContent = salonName.value.trim() || 'Your Salon Name';
        });

        salonAddress.addEventListener('input', () => {
            mockupAddress.textContent = salonAddress.value.trim() || '123 Location Address...';
        });

        salonPhone.addEventListener('input', () => {
            if (salonPhone.value.trim()) {
                mockupPhoneLine.style.display = 'flex';
                mockupPhone.textContent = salonPhone.value.trim();
            } else {
                mockupPhoneLine.style.display = 'none';
            }
        });

        salonEmail.addEventListener('input', () => {
            if (salonEmail.value.trim()) {
                mockupEmailLine.style.display = 'flex';
                mockupEmail.textContent = salonEmail.value.trim();
            } else {
                mockupEmailLine.style.display = 'none';
            }
        });

        salonDesc.addEventListener('input', () => {
            const len = salonDesc.value.length;
            document.getElementById('descCounter').textContent = len + '/500';
            if (salonDesc.value.trim()) {
                mockupDesc.style.display = 'block';
                mockupDesc.textContent = salonDesc.value.trim();
            } else {
                mockupDesc.style.display = 'none';
            }
        });

        slotDuration.addEventListener('change', () => {
            mockupSlotTime.textContent = slotDuration.value + ' min slots';
        });

        // Amenities Checkboxes & Mockup tags
        const amenityChecks = document.querySelectorAll('.amenity-check');
        
        function updateAmenities() {
            mockupAmenitiesTags.innerHTML = '';
            amenityChecks.forEach(chk => {
                const parentCard = chk.closest('.amenity-card-option');
                if (chk.checked) {
                    parentCard.classList.add('active');
                    const labelText = parentCard.querySelector('strong').textContent.trim();
                    const tag = document.createElement('span');
                    tag.className = 'preview-tag';
                    tag.innerHTML = `<i class="fas fa-check text-success me-1"></i> ${labelText}`;
                    mockupAmenitiesTags.appendChild(tag);
                } else {
                    parentCard.classList.remove('active');
                }
            });
        }

        amenityChecks.forEach(chk => {
            chk.addEventListener('change', updateAmenities);
        });
        updateAmenities();

        // Image Upload & Preview
        const imageInput = document.getElementById('imageInput');
        const imagePreviewContainer = document.getElementById('imagePreviewContainer');
        const previewThumbnail = document.getElementById('previewThumbnail');
        const previewFileName = document.getElementById('previewFileName');
        const cardMockupImg = document.getElementById('cardMockupImg');
        const mockupEmptyState = document.getElementById('mockupEmptyState');

        imageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = e => {
                    previewThumbnail.src = e.target.result;
                    previewFileName.textContent = file.name;
                    imagePreviewContainer.style.setProperty('display', 'flex', 'important');

                    cardMockupImg.style.backgroundImage = `url('${e.target.result}')`;
                    mockupEmptyState.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });

        function clearSelectedImage() {
            imageInput.value = '';
            imagePreviewContainer.style.setProperty('display', 'none', 'important');
            previewThumbnail.src = '';
            cardMockupImg.style.backgroundImage = '';
            mockupEmptyState.style.display = 'block';
        }

        // Leaflet Interactive Map
        var defaultLat = 6.9271;
        var defaultLng = 79.8612;
        var oldLat = document.getElementById('latInput').value;
        var oldLng = document.getElementById('lngInput').value;

        if (oldLat && oldLng) {
            defaultLat = parseFloat(oldLat);
            defaultLng = parseFloat(oldLng);
        }

        var map = L.map('salonMap').setView([defaultLat, defaultLng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        var marker = null;
        if (oldLat && oldLng) {
            marker = L.marker([defaultLat, defaultLng]).addTo(map);
            updateLocationBadge(defaultLat, defaultLng);
        }

        map.on('click', function(e) {
            if (marker) map.removeLayer(marker);
            marker = L.marker([e.latlng.lat, e.latlng.lng]).addTo(map);
            document.getElementById('latInput').value = e.latlng.lat;
            document.getElementById('lngInput').value = e.latlng.lng;
            updateLocationBadge(e.latlng.lat, e.latlng.lng);
        });

        function updateLocationBadge(lat, lng) {
            const badge = document.getElementById('locationInfo');
            const display = document.getElementById('coordsDisplay');
            badge.style.display = 'inline-flex';
            display.textContent = `${parseFloat(lat).toFixed(6)}, ${parseFloat(lng).toFixed(6)}`;
        }

        // Geocoding Search
        const geocodeBtn = document.getElementById('geocodeBtn');
        geocodeBtn.addEventListener('click', function() {
            const address = salonAddress.value.trim();
            if (!address) {
                alert('Please enter an address first');
                salonAddress.focus();
                return;
            }

            geocodeBtn.disabled = true;
            geocodeBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Searching...';

            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`)
                .then(res => res.json())
                .then(data => {
                    geocodeBtn.disabled = false;
                    geocodeBtn.innerHTML = '<i class="fas fa-search-location me-1"></i> Locate on Map';

                    if (data && data.length > 0) {
                        const lat = parseFloat(data[0].lat);
                        const lng = parseFloat(data[0].lon);

                        if (marker) map.removeLayer(marker);
                        marker = L.marker([lat, lng]).addTo(map);
                        map.setView([lat, lng], 15);

                        document.getElementById('latInput').value = lat;
                        document.getElementById('lngInput').value = lng;
                        updateLocationBadge(lat, lng);
                    } else {
                        alert('Location not found for this address. Please click on your location directly on the map.');
                    }
                })
                .catch(err => {
                    geocodeBtn.disabled = false;
                    geocodeBtn.innerHTML = '<i class="fas fa-search-location me-1"></i> Locate on Map';
                    alert('Geocoding service unavailable. Please click your location on the map.');
                });
        });

        // Form Submit Validation
        document.getElementById('salonForm').addEventListener('submit', function(e) {
            const lat = document.getElementById('latInput').value;
            const lng = document.getElementById('lngInput').value;

            if (!lat || !lng) {
                e.preventDefault();
                alert('Please select your salon location on the interactive map.');
                document.getElementById('salonMap').scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
        });

        // Theme Toggle
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeToggleIcon = document.getElementById('themeToggleIcon');

        function updateThemeUI() {
            const isDark = document.body.classList.contains('dark-mode') || document.documentElement.classList.contains('dark-mode');
            if (themeToggleIcon) {
                themeToggleIcon.className = isDark ? 'fas fa-sun me-1 text-warning' : 'fas fa-moon me-1';
            }
        }
        updateThemeUI();

        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                const isDark = !document.documentElement.classList.contains('dark-mode');
                document.documentElement.classList.toggle('dark-mode', isDark);
                document.body.classList.toggle('dark-mode', isDark);
                localStorage.setItem('salonora_theme', isDark ? 'dark' : 'light');
                updateThemeUI();
            });
        }
    </script>
</body>
</html>
