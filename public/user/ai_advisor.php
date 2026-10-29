<?php
session_start();
require_once __DIR__ . '/../../config.php';

// Fetch all active salons for dropdown
$salonsStmt = $pdo->query("SELECT id, name, address FROM salons ORDER BY name ASC");
$allSalons = $salonsStmt->fetchAll(PDO::FETCH_ASSOC);

$preselect_salon_id = intval($_GET['salon_id'] ?? 0);

include __DIR__ . '/../header.php';
?>

<div class="container py-4">
    <!-- Hero Banner -->
    <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-4" style="background: linear-gradient(135deg, #1e1b4b 0%, #311042 50%, #6b21a8 100%); color: #fff;">
        <div class="card-body p-4 p-md-5 position-relative">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold mb-3 shadow-sm">
                        <i class="fas fa-sparkles me-1"></i> Powered by Google Gemini 3.5 AI
                    </span>
                    <h1 class="display-6 fw-bold mb-2">Salonora AI Style & Beauty Advisor</h1>
                    <p class="lead mb-0 text-white-50" style="max-width: 650px;">
                        Not sure which haircut, treatment, or spa therapy best flatters your face shape and hair texture? Let our intelligent AI consultant recommend the perfect match from real salon menus.
                    </p>
                </div>
                <div class="col-lg-4 text-center mt-4 mt-lg-0">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-10 shadow-lg" style="width: 110px; height: 110px; border: 2px dashed rgba(255,255,255,0.3);">
                        <i class="fas fa-magic text-warning display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="row g-4">
        <!-- Input Form Column -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-1"><i class="fas fa-sliders-h text-primary me-2"></i>Your Preferences</h5>
                    <p class="text-muted small mb-0">Fill in your details for hyper-personalized recommendations.</p>
                </div>
                <div class="card-body p-4">
                    <form id="aiAdvisorForm">
                        <!-- Salon Selector -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Choose Salon (Optional)</label>
                            <select class="form-select rounded-3" id="aiSalonSelect">
                                <option value="0">All Salons / General Style Advice</option>
                                <?php foreach ($allSalons as $salon): ?>
                                    <option value="<?= $salon['id'] ?>" <?= $salon['id'] == $preselect_salon_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($salon['name']) ?> (<?= htmlspecialchars($salon['address']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Selecting a salon matches recommendations directly to their actual menu.</div>
                        </div>

                        <!-- Language Option -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Language / භාෂාව</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="langOption" id="langEn" value="en" checked>
                                <label class="btn btn-outline-primary btn-sm py-2" for="langEn"><i class="fas fa-globe me-1"></i> English</label>

                                <input type="radio" class="btn-check" name="langOption" id="langSi" value="si">
                                <label class="btn btn-outline-primary btn-sm py-2" for="langSi"><i class="fas fa-language me-1"></i> සිංහල (Sinhala)</label>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <!-- Gender -->
                            <div class="col-6">
                                <label class="form-label fw-semibold small">Gender</label>
                                <select class="form-select rounded-3" id="aiGender">
                                    <option value="Female">Female</option>
                                    <option value="Male">Male</option>
                                    <option value="Unisex">Unisex / Any</option>
                                </select>
                            </div>

                            <!-- Face Shape -->
                            <div class="col-6">
                                <label class="form-label fw-semibold small">Face Shape</label>
                                <select class="form-select rounded-3" id="aiFaceShape">
                                    <option value="Oval">Oval</option>
                                    <option value="Round">Round</option>
                                    <option value="Square">Square</option>
                                    <option value="Heart">Heart</option>
                                    <option value="Diamond">Diamond</option>
                                    <option value="Oblong">Oblong / Long</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <!-- Hair Type -->
                            <div class="col-6">
                                <label class="form-label fw-semibold small">Hair Type</label>
                                <select class="form-select rounded-3" id="aiHairType">
                                    <option value="Straight">Straight</option>
                                    <option value="Wavy">Wavy</option>
                                    <option value="Curly">Curly</option>
                                    <option value="Coily">Coily</option>
                                    <option value="Fine / Thin">Fine / Thinning</option>
                                    <option value="Thick / Coarse">Thick / Coarse</option>
                                </select>
                            </div>

                            <!-- Vibe / Occasion -->
                            <div class="col-6">
                                <label class="form-label fw-semibold small">Occasion / Vibe</label>
                                <select class="form-select rounded-3" id="aiVibe">
                                    <option value="Modern Everyday">Modern Everyday</option>
                                    <option value="Wedding / Festive Event">Wedding / Festive</option>
                                    <option value="Professional Office">Corporate / Office</option>
                                    <option value="Bold / Edgy Trend">Bold & Edgy</option>
                                    <option value="Low Maintenance">Low Maintenance</option>
                                    <option value="Hair Damage Repair">Damage Recovery</option>
                                </select>
                            </div>
                        </div>

                        <!-- User Query -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold small">Custom Notes or Questions</label>
                            <textarea class="form-control rounded-3" id="aiQuery" rows="3" placeholder="e.g. I have a wedding coming up next weekend and need a fresh modern haircut and a glowing facial treatment..."></textarea>
                        </div>

                        <button type="submit" id="btnGetAdvice" class="btn btn-gradient w-100 py-2 rounded-pill shadow-sm fw-semibold">
                            <i class="fas fa-magic me-1"></i> Generate AI Style Consultation
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Consultation Output Column -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-1"><i class="fas fa-comment-dots text-warning me-2"></i>Consultation Plan</h5>
                        <p class="text-muted small mb-0">Your customized aesthetic and styling blueprint.</p>
                    </div>
                    <span id="aiStatusBadge" class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-1 small">
                        Ready
                    </span>
                </div>

                <div class="card-body p-4">
                    <!-- Placeholder State -->
                    <div id="aiPlaceholder" class="text-center py-5">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light mb-3 text-muted" style="width: 80px; height: 80px;">
                            <i class="fas fa-robot display-5 text-primary opacity-50"></i>
                        </div>
                        <h5 class="fw-bold text-muted">Awaiting Your Preferences</h5>
                        <p class="text-muted small mx-auto" style="max-width: 420px;">
                            Select your face shape, hair texture, and what you’re looking for on the left. Gemini AI will generate professional styling advice tailored specifically for you!
                        </p>
                    </div>

                    <!-- Loading State -->
                    <div id="aiLoading" class="text-center py-5 d-none">
                        <div class="spinner-border text-primary mb-3" style="width: 3.5rem; height: 3.5rem;" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <h5 class="fw-bold">Analyzing Your Profile...</h5>
                        <p class="text-muted small">Gemini 3.5 AI is formulating custom style recommendations and matching salon services.</p>
                    </div>

                    <!-- AI Result Content -->
                    <div id="aiResultArea" class="d-none">
                        <!-- Matched Services Card -->
                        <div id="matchedServicesContainer" class="mb-4 d-none">
                            <div class="card border-0 bg-light rounded-4 p-3 border-start border-4 border-warning shadow-sm">
                                <h6 class="fw-bold text-dark mb-2">
                                    <i class="fas fa-check-circle text-warning me-1"></i> Recommended Salon Services to Book:
                                </h6>
                                <div id="matchedServicesList" class="d-flex flex-column gap-2"></div>
                            </div>
                        </div>

                        <!-- Formatted Markdown Advice -->
                        <div id="aiAdviceText" class="p-3 bg-light rounded-4 text-dark" style="font-size: 0.95rem; line-height: 1.7; white-space: pre-wrap;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('aiAdvisorForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const salonId = document.getElementById('aiSalonSelect').value;
    const gender = document.getElementById('aiGender').value;
    const faceShape = document.getElementById('aiFaceShape').value;
    const hairType = document.getElementById('aiHairType').value;
    const vibe = document.getElementById('aiVibe').value;
    const query = document.getElementById('aiQuery').value.trim();
    const lang = document.querySelector('input[name="langOption"]:checked').value;

    const btn = document.getElementById('btnGetAdvice');
    const statusBadge = document.getElementById('aiStatusBadge');
    const placeholder = document.getElementById('aiPlaceholder');
    const loading = document.getElementById('aiLoading');
    const resultArea = document.getElementById('aiResultArea');
    const adviceText = document.getElementById('aiAdviceText');
    const matchedContainer = document.getElementById('matchedServicesContainer');
    const matchedList = document.getElementById('matchedServicesList');

    // UI Loading state
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Consulting Gemini AI...';
    statusBadge.className = 'badge bg-warning text-dark rounded-pill px-3 py-1 small';
    statusBadge.innerText = 'Processing...';

    placeholder.classList.add('d-none');
    resultArea.classList.add('d-none');
    loading.classList.remove('d-none');

    try {
        const response = await fetch('<?= url('api/ai_advisor.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                salon_id: parseInt(salonId),
                gender: gender,
                face_shape: faceShape,
                hair_type: hairType,
                style_vibe: vibe,
                query: query || `Recommend best style for ${gender} with ${faceShape} face shape and ${hairType} hair for ${vibe}`,
                lang: lang
            })
        });

        const data = await response.json();

        loading.classList.add('d-none');
        resultArea.classList.remove('d-none');

        if (data.success) {
            statusBadge.className = 'badge bg-success text-white rounded-pill px-3 py-1 small';
            statusBadge.innerText = 'Complete';

            // Clean formatted advice
            adviceText.innerText = data.advice;

            // Render matched services
            if (data.matched_services && data.matched_services.length > 0) {
                matchedList.innerHTML = '';
                data.matched_services.forEach(s => {
                    const item = document.createElement('div');
                    item.className = 'd-flex justify-content-between align-items-center bg-white p-2 px-3 rounded-3 shadow-sm border';
                    const targetSalon = data.salon_id > 0 ? data.salon_id : 1;
                    item.innerHTML = `
                        <div>
                            <strong>${s.name}</strong>
                            <div class="small text-muted">Rs. ${parseFloat(s.price).toLocaleString()} • ~${s.duration} mins</div>
                        </div>
                        <a href="<?= url('user/salon_details.php') ?>?id=${targetSalon}&service_id=${s.id}#services" class="btn btn-sm btn-gradient rounded-pill">
                            <i class="fas fa-calendar-plus me-1"></i> Book This
                        </a>
                    `;
                    matchedList.appendChild(item);
                });
                matchedContainer.classList.remove('d-none');
            } else {
                matchedContainer.classList.add('d-none');
            }
        } else {
            statusBadge.className = 'badge bg-danger text-white rounded-pill px-3 py-1 small';
            statusBadge.innerText = 'Error';
            adviceText.innerText = data.message || 'Unable to generate advice at this moment. Please try again.';
            matchedContainer.classList.add('d-none');
        }
    } catch (err) {
        loading.classList.add('d-none');
        resultArea.classList.remove('d-none');
        statusBadge.className = 'badge bg-danger text-white rounded-pill px-3 py-1 small';
        statusBadge.innerText = 'Error';
        adviceText.innerText = 'Network error while connecting to Gemini AI. Please check your connection and try again.';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-magic me-1"></i> Generate AI Style Consultation';
    }
});
</script>

<?php include __DIR__ . '/../footer.php'; ?>
