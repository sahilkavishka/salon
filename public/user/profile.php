<?php 
// public/user/profile.php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';

// Check user session
$id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (!$id) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch user data
$stmt = $pdo->prepare('SELECT id, username AS name, email, phone, password, profile_picture, location, role, created_at 
                       FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die('User not found.');
}

// Get stats
$stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE user_id = ?');
$stmt->execute([$id]);
$appointments_count = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM reviews WHERE user_id = ?');
$stmt->execute([$id]);
$reviews_count = $stmt->fetchColumn();

$errors = [];
$success = '';

// Handle Profile Information & Picture Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $profilePicturePath = $user['profile_picture']; // keep old if not changed

    if ($name === '') {
        $errors[] = 'Name is required.';
    }

    if ($phone !== '' && !preg_match('/^[0-9+\-\s]{8,15}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number.';
    }

    // Handle profile picture upload
    if (!empty($_FILES['profile_picture']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        $fileType = $_FILES['profile_picture']['type'];
        $fileSize = $_FILES['profile_picture']['size'];
        $fileTmp  = $_FILES['profile_picture']['tmp_name'];
        $fileErr  = $_FILES['profile_picture']['error'];

        if ($fileErr !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading profile image.";
        } else {
            if (!in_array($fileType, $allowed)) {
                $errors[] = "Only JPG, PNG, and WebP images are allowed.";
            }

            if ($fileSize > 2 * 1024 * 1024) {
                $errors[] = "Image size must be below 2MB.";
            }

            if (empty($errors)) {
                $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                $fileName = "profile_" . time() . "_" . rand(1000, 9999) . "." . $ext;

                $uploadDir = __DIR__ . '/../uploads/profile/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $uploadPath = $uploadDir . $fileName;

                if (move_uploaded_file($fileTmp, $uploadPath)) {
                    $profilePicturePath = "uploads/profile/" . $fileName;
                    @chmod($uploadPath, 0644);
                } else {
                    $errors[] = "Failed to save uploaded image.";
                }
            }
        }
    }

    if (empty($errors)) {
        $query = "UPDATE users SET username=?, phone=?, location=?, profile_picture=? WHERE id=?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$name, $phone, $location, $profilePicturePath, $id]);

        $_SESSION['username'] = $name;
        $_SESSION['user_name'] = $name;

        $success = "Profile details updated successfully.";

        // Update values for current view
        $user['name'] = $name;
        $user['phone'] = $phone;
        $user['location'] = $location;
        $user['profile_picture'] = $profilePicturePath;
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $errors[] = 'All password fields are required.';
    } elseif (!password_verify($current_password, $user['password'])) {
        $errors[] = 'Current password is incorrect.';
    } elseif (strlen($new_password) < 6) {
        $errors[] = 'New password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $errors[] = 'New password and confirmation do not match.';
    }

    if (empty($errors)) {
        $newHash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$newHash, $id]);
        $user['password'] = $newHash;
        $success = "Password changed successfully.";
    }
}

$page_title = 'My Profile | Salonora';
include __DIR__ . '/../header.php';
?>

<style>
/* ========================================================
   SALONORA USER PROFILE - LUXURY STUDIO DESIGN
   ======================================================== */

.profile-hero {
  background: linear-gradient(135deg, #180928 0%, #2f083b 45%, #620f54 85%, #881337 100%);
  color: #ffffff;
  border-radius: 28px;
  padding: 3rem 2rem 2.5rem;
  margin-bottom: 2rem;
  position: relative;
  overflow: hidden;
  box-shadow: 0 15px 35px -10px rgba(98, 15, 84, 0.45);
}

.profile-hero::before {
  content: '';
  position: absolute;
  top: -50px;
  right: -50px;
  width: 240px;
  height: 240px;
  background: radial-gradient(circle, rgba(236, 72, 153, 0.35) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
}

.profile-avatar-wrap {
  position: relative;
  width: 130px;
  height: 130px;
  margin: 0 auto;
}

.profile-avatar-img {
  width: 130px;
  height: 130px;
  border-radius: 50%;
  object-fit: cover;
  border: 4px solid #ffffff;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.25);
  background: #2e083a;
}

.profile-avatar-letter {
  width: 130px;
  height: 130px;
  border-radius: 50%;
  border: 4px solid #ffffff;
  background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3.5rem;
  font-weight: 800;
  box-shadow: 0 8px 25px rgba(236, 72, 153, 0.4);
}

.profile-avatar-edit-badge {
  position: absolute;
  bottom: 4px;
  right: 4px;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: #ec4899;
  color: #ffffff;
  border: 3px solid #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}

.profile-avatar-edit-badge:hover {
  background: #db2777;
  transform: scale(1.1);
}

/* Stat Box Grid */
.profile-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.25rem;
  margin-top: 1.75rem;
}

.profile-stat-card {
  background: rgba(255, 255, 255, 0.12);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 20px;
  padding: 1.25rem 1.5rem;
  text-align: center;
  color: #ffffff;
  transition: all 0.25s ease;
}

.profile-stat-card:hover {
  background: rgba(255, 255, 255, 0.18);
  transform: translateY(-2px);
}

.profile-stat-card .stat-icon {
  font-size: 1.5rem;
  color: #fbcfe8;
  margin-bottom: 0.4rem;
}

.profile-stat-card .stat-val {
  font-size: 1.75rem;
  font-weight: 800;
  line-height: 1.1;
}

.profile-stat-card .stat-lbl {
  font-size: 0.82rem;
  color: rgba(255, 255, 255, 0.75);
  margin-top: 0.3rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Studio Tabs */
.profile-nav-pills {
  display: flex;
  gap: 0.75rem;
  border-bottom: 2px solid #e2e8f0;
  padding-bottom: 0.75rem;
  margin-bottom: 2rem;
  overflow-x: auto;
}

.profile-nav-pills .nav-link {
  background: #ffffff !important;
  color: #1e293b !important;
  border: 1.5px solid #cbd5e1 !important;
  border-radius: 50px;
  padding: 0.6rem 1.5rem;
  font-weight: 600;
  font-size: 0.95rem;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  transition: all 0.25s ease;
}

.profile-nav-pills .nav-link:hover {
  background: #fdf2f8 !important;
  color: #db2777 !important;
  border-color: #fbcfe8 !important;
}

.profile-nav-pills .nav-link.active {
  background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%) !important;
  color: #ffffff !important;
  border-color: transparent !important;
  box-shadow: 0 6px 18px rgba(233, 30, 99, 0.35);
}

/* Studio Card */
.profile-content-card {
  background: #ffffff;
  border-radius: 24px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
  padding: 2.25rem;
  margin-bottom: 2rem;
}

.form-label {
  font-weight: 600;
  color: #334155;
  font-size: 0.88rem;
  margin-bottom: 0.4rem;
}

.form-control, .form-select {
  border-radius: 12px;
  padding: 0.75rem 1rem;
  border: 1.5px solid #e2e8f0;
  font-size: 0.92rem;
  transition: all 0.2s ease;
}

.form-control:focus, .form-select:focus {
  border-color: #ec4899;
  box-shadow: 0 0 0 4px rgba(236, 72, 153, 0.15);
}

/* Quick Action Tiles */
.profile-quick-action {
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 20px;
  padding: 1.5rem;
  text-decoration: none;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 1.25rem;
  transition: all 0.25s ease;
  height: 100%;
}

.profile-quick-action:hover {
  background: #ffffff;
  border-color: #ec4899;
  transform: translateY(-3px);
  box-shadow: 0 10px 25px rgba(236, 72, 153, 0.15);
  color: #db2777;
}

.profile-quick-action .action-icon {
  width: 55px;
  height: 55px;
  border-radius: 16px;
  background: var(--gradient-primary);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  flex-shrink: 0;
  box-shadow: 0 6px 15px rgba(233, 30, 99, 0.25);
}

/* ========================================================
   DARK MODE
   ======================================================== */
body.dark-mode .profile-hero {
  background: linear-gradient(135deg, #11051c 0%, #1c0524 45%, #3d0534 85%, #4c051e 100%) !important;
  box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.6) !important;
}

body.dark-mode .profile-content-card {
  background: #1a1a2e !important;
  border-color: #2d2d48 !important;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4) !important;
}

body.dark-mode .profile-nav-pills {
  border-bottom-color: #2d2d48 !important;
}

body.dark-mode .profile-nav-pills .nav-link {
  background: #1e1e38 !important;
  color: #f8fafc !important;
  border-color: #3b3b5e !important;
}

body.dark-mode .profile-nav-pills .nav-link:hover {
  background: #2a2a4c !important;
  color: #f472b6 !important;
  border-color: #ec4899 !important;
}

body.dark-mode .profile-nav-pills .nav-link.active {
  background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%) !important;
  color: #ffffff !important;
  border-color: transparent !important;
}

body.dark-mode .form-label {
  color: #cbd5e1 !important;
}

body.dark-mode .form-control,
body.dark-mode .form-select {
  background: #1e1e38 !important;
  border-color: #2d2d48 !important;
  color: #f8fafc !important;
}

body.dark-mode .profile-quick-action {
  background: #1e1e38 !important;
  border-color: #2d2d48 !important;
  color: #f8fafc !important;
}

body.dark-mode .profile-quick-action:hover {
  background: #252542 !important;
  border-color: #ec4899 !important;
}
</style>

<div class="container py-4 pb-5">

  <!-- ==========================================
       HERO BANNER & AVATAR
       ========================================== -->
  <div class="profile-hero text-center text-md-start">
    <div class="row align-items-center">
      <div class="col-md-auto text-center mb-3 mb-md-0">
        <div class="profile-avatar-wrap">
          <?php if (!empty($user['profile_picture']) && file_exists(__DIR__ . '/../' . $user['profile_picture'])): ?>
            <img src="../<?= htmlspecialchars($user['profile_picture']) ?>" alt="Avatar" class="profile-avatar-img" id="avatarPreviewImg">
          <?php else: ?>
            <div class="profile-avatar-letter" id="avatarLetterBox">
              <?= strtoupper(substr($user['name'] ?: 'U', 0, 1)) ?>
            </div>
            <img src="" alt="Avatar" class="profile-avatar-img d-none" id="avatarPreviewImg">
          <?php endif; ?>

          <label for="profilePicInput" class="profile-avatar-edit-badge" title="Change Photo">
            <i class="fas fa-camera"></i>
          </label>
        </div>
      </div>

      <div class="col-md">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-1 justify-content-center justify-content-md-start">
          <h2 class="fw-bold mb-0 text-white"><?= htmlspecialchars($user['name']) ?></h2>
          <span class="badge bg-warning text-dark px-3 py-1 rounded-pill small fw-bold">
            <i class="fas fa-crown me-1"></i> VIP Client
          </span>
        </div>

        <p class="text-white-50 mb-2">
          <i class="fas fa-envelope me-1 text-pink"></i> <?= htmlspecialchars($user['email']) ?>
          <?php if (!empty($user['phone'])): ?>
            <span class="mx-2">•</span>
            <i class="fas fa-phone-alt me-1"></i> <?= htmlspecialchars($user['phone']) ?>
          <?php endif; ?>
          <?php if (!empty($user['location'])): ?>
            <span class="mx-2">•</span>
            <i class="fas fa-map-marker-alt me-1 text-danger"></i> <?= htmlspecialchars($user['location']) ?>
          <?php endif; ?>
        </p>

        <div class="text-white-50 small">
          <i class="fas fa-calendar-alt me-1"></i> Member since <?= date('F Y', strtotime($user['created_at'])) ?>
        </div>
      </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="profile-stats-grid">
      <div class="profile-stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="stat-val"><?= $appointments_count ?></div>
        <div class="stat-lbl">Total Appointments</div>
      </div>

      <div class="profile-stat-card">
        <div class="stat-icon"><i class="fas fa-star"></i></div>
        <div class="stat-val"><?= $reviews_count ?></div>
        <div class="stat-lbl">Reviews Given</div>
      </div>

      <div class="profile-stat-card">
        <div class="stat-icon"><i class="fas fa-gem"></i></div>
        <div class="stat-val">Gold</div>
        <div class="stat-lbl">Rewards Tier</div>
      </div>
    </div>
  </div>

  <!-- Global Alerts -->
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
      <i class="fas fa-exclamation-triangle me-2"></i>
      <?php foreach ($errors as $e): ?>
        <div><?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
      <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <!-- ==========================================
       TABBED PROFILE STUDIO
       ========================================== -->
  <div class="profile-nav-pills">
    <button type="button" class="nav-link active" onclick="switchProfileTab('editProfileTab', this)">
      <i class="fas fa-user-edit"></i> Edit Profile
    </button>
    <button type="button" class="nav-link" onclick="switchProfileTab('securityTab', this)">
      <i class="fas fa-lock"></i> Password & Security
    </button>
    <button type="button" class="nav-link" onclick="switchProfileTab('quickActionsTab', this)">
      <i class="fas fa-bolt"></i> Quick Links & Studio
    </button>
  </div>

  <!-- TAB 1: EDIT PROFILE -->
  <div class="profile-tab-pane" id="editProfileTab">
    <div class="profile-content-card">
      <h4 class="fw-bold mb-3 d-flex align-items-center">
        <i class="fas fa-id-card text-primary me-2"></i> Personal Details & Contact
      </h4>
      <p class="text-muted small mb-4">Keep your contact information up to date so salons can confirm your appointments.</p>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="update_profile" value="1">
        
        <!-- Hidden file input triggered by avatar badge or browse button -->
        <input type="file" name="profile_picture" id="profilePicInput" class="d-none" accept="image/jpeg,image/png,image/jpg,image/webp" onchange="previewAvatar(this)">

        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label"><i class="fas fa-user text-primary me-1"></i> Full Name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
          </div>

          <div class="col-md-6">
            <label class="form-label"><i class="fas fa-envelope text-primary me-1"></i> Email Address</label>
            <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($user['email']) ?>" readonly title="Email cannot be changed directly">
            <small class="text-muted">Registered account login email</small>
          </div>

          <div class="col-md-6">
            <label class="form-label"><i class="fas fa-phone text-primary me-1"></i> Phone Number</label>
            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="e.g. 0771234567">
          </div>

          <div class="col-md-6">
            <label class="form-label"><i class="fas fa-map-marker-alt text-primary me-1"></i> Preferred City / Region</label>
            <select name="location" class="form-select" required>
              <option value="">Select your city</option>
              <?php
              $cities = [
                  "Colombo", "Kandy", "Galle", "Matara", "Jaffna", "Negombo", "Anuradhapura",
                  "Kurunegala", "Badulla", "Batticaloa", "Trincomalee", "Kegalle",
                  "Ratnapura", "Hambantota", "Polonnaruwa", "Puttalam", "Mannar",
                  "Nuwara Eliya", "Kalutara", "Gampaha"
              ];
              foreach ($cities as $city):
                  $selected = ($user['location'] === $city) ? "selected" : "";
                  echo "<option value='".htmlspecialchars($city)."' $selected>".htmlspecialchars($city)."</option>";
              endforeach;
              ?>
            </select>
          </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 pt-3 border-top">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="document.getElementById('profilePicInput').click()">
            <i class="fas fa-image me-1"></i> Change Avatar Photo
          </button>

          <button type="submit" class="btn btn-gradient rounded-pill px-5 py-2 fw-semibold shadow-sm">
            <i class="fas fa-save me-1"></i> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- TAB 2: PASSWORD & SECURITY -->
  <div class="profile-tab-pane d-none" id="securityTab">
    <div class="profile-content-card">
      <h4 class="fw-bold mb-3 d-flex align-items-center">
        <i class="fas fa-shield-alt text-primary me-2"></i> Change Account Password
      </h4>
      <p class="text-muted small mb-4">Ensure your account uses a strong, private password.</p>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="change_password" value="1">

        <div class="row g-3 mb-4" style="max-width: 550px;">
          <div class="col-12">
            <label class="form-label"><i class="fas fa-key text-primary me-1"></i> Current Password</label>
            <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
          </div>

          <div class="col-12">
            <label class="form-label"><i class="fas fa-lock text-primary me-1"></i> New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="At least 6 characters" minlength="6" required>
          </div>

          <div class="col-12">
            <label class="form-label"><i class="fas fa-check-double text-primary me-1"></i> Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password" minlength="6" required>
          </div>
        </div>

        <div class="pt-3 border-top">
          <button type="submit" class="btn btn-gradient rounded-pill px-5 py-2 fw-semibold shadow-sm">
            <i class="fas fa-shield-check me-1"></i> Update Password
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- TAB 3: QUICK LINKS & STUDIO -->
  <div class="profile-tab-pane d-none" id="quickActionsTab">
    <div class="row g-4">
      <div class="col-md-4">
        <a href="my_appointments.php" class="profile-quick-action">
          <div class="action-icon">
            <i class="fas fa-calendar-alt"></i>
          </div>
          <div>
            <h6 class="fw-bold mb-1">My Appointments</h6>
            <span class="small text-muted">View upcoming & completed bookings</span>
          </div>
        </a>
      </div>

      <div class="col-md-4">
        <a href="ai_advisor.php" class="profile-quick-action">
          <div class="action-icon" style="background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%);">
            <i class="fas fa-magic"></i>
          </div>
          <div>
            <h6 class="fw-bold mb-1">AI Style Studio</h6>
            <span class="small text-muted">Get tailored beauty & haircut advice</span>
          </div>
        </a>
      </div>

      <div class="col-md-4">
        <a href="salon_view.php" class="profile-quick-action">
          <div class="action-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
            <i class="fas fa-search"></i>
          </div>
          <div>
            <h6 class="fw-bold mb-1">Browse Salons</h6>
            <span class="small text-muted">Explore certified beauty destinations</span>
          </div>
        </a>
      </div>
    </div>
  </div>

</div>

<script>
function switchProfileTab(tabId, btn) {
  document.querySelectorAll('.profile-nav-pills .nav-link').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.profile-tab-pane').forEach(p => p.classList.add('d-none'));

  btn.classList.add('active');
  const target = document.getElementById(tabId);
  if (target) {
    target.classList.remove('d-none');
  }
}

function previewAvatar(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = document.getElementById('avatarPreviewImg');
      const letter = document.getElementById('avatarLetterBox');
      if (img) {
        img.src = e.target.result;
        img.classList.remove('d-none');
      }
      if (letter) {
        letter.classList.add('d-none');
      }
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<?php include __DIR__ . '/../footer.php'; ?>
