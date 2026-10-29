<?php
// public/user/ai_advisor.php
session_start();
require_once __DIR__ . '/../../config.php';

// Set page title for header
$page_title = 'AI Beauty & Style Studio | Salonora';

// Fetch all active salons for dropdown
$salonsStmt = $pdo->query("SELECT id, name, address, phone FROM salons ORDER BY name ASC");
$allSalons = $salonsStmt->fetchAll(PDO::FETCH_ASSOC);

$preselect_salon_id = intval($_GET['salon_id'] ?? 0);

include __DIR__ . '/../header.php';
?>

<!-- Marked.js for rendering rich Markdown consultation reports -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<!-- Canvas Confetti for celebratory consultation reveal -->
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>

<style>
/* ========================================================
   SALONORA AI ADVISOR - LUXURY BEAUTY STUDIO STYLES
   ======================================================== */

:root {
  --ai-primary: #e91e63;
  --ai-purple: #8b5cf6;
  --ai-dark-bg: #120b22;
  --ai-card-bg: #ffffff;
  --ai-border: rgba(139, 92, 246, 0.18);
  --ai-glow: rgba(233, 30, 99, 0.25);
  --ai-gradient-hero: linear-gradient(135deg, #120826 0%, #290b3b 40%, #5b1251 75%, #881337 100%);
  --ai-gradient-accent: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%);
  --ai-text-muted: #64748b;
}

/* Hero Section */
.ai-hero-card {
  background: var(--ai-gradient-hero);
  color: #ffffff;
  border-radius: 28px;
  overflow: hidden;
  position: relative;
  box-shadow: 0 20px 45px -10px rgba(76, 5, 59, 0.45);
  border: 1px solid rgba(255, 255, 255, 0.12);
}

.ai-hero-card::before {
  content: '';
  position: absolute;
  top: -60px;
  right: -60px;
  width: 260px;
  height: 260px;
  background: radial-gradient(circle, rgba(236, 72, 153, 0.35) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
  filter: blur(20px);
}

.ai-hero-card::after {
  content: '';
  position: absolute;
  bottom: -80px;
  left: 20%;
  width: 300px;
  height: 300px;
  background: radial-gradient(circle, rgba(139, 92, 246, 0.25) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
  filter: blur(30px);
}

.ai-avatar-badge {
  position: relative;
  width: 100px;
  height: 100px;
  border-radius: 50%;
  background: linear-gradient(135deg, #ec4899, #8b5cf6);
  padding: 3px;
  box-shadow: 0 10px 25px rgba(236, 72, 153, 0.4);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.ai-avatar-inner {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background: #1c0c32;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #f472b6;
  font-size: 2.8rem;
  position: relative;
}

.ai-pulse-dot {
  position: absolute;
  bottom: 4px;
  right: 4px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #10b981;
  border: 3px solid #1c0c32;
  box-shadow: 0 0 10px #10b981;
  animation: pulseGreen 2s infinite;
}

@keyframes pulseGreen {
  0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
  70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
  100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* Feature tags */
.ai-pill-tag {
  background: rgba(255, 255, 255, 0.12);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.18);
  color: #f1f5f9;
  font-size: 0.82rem;
  font-weight: 500;
  padding: 0.35rem 0.85rem;
  border-radius: 50px;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

/* Preset Quick Prompts Bar */
.ai-quick-prompts-bar {
  display: flex;
  gap: 0.6rem;
  overflow-x: auto;
  padding-bottom: 0.5rem;
  scrollbar-width: thin;
}

.ai-quick-prompts-bar::-webkit-scrollbar {
  height: 5px;
}

.ai-quick-prompts-bar::-webkit-scrollbar-thumb {
  background: rgba(233, 30, 99, 0.3);
  border-radius: 10px;
}

.btn-preset-chip {
  background: #ffffff;
  color: #334155;
  border: 1px solid #e2e8f0;
  border-radius: 50px;
  padding: 0.45rem 1rem;
  font-size: 0.84rem;
  font-weight: 600;
  white-space: nowrap;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
  cursor: pointer;
}

.btn-preset-chip:hover {
  background: var(--gradient-primary);
  color: #ffffff;
  border-color: transparent;
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(233, 30, 99, 0.3);
}

.btn-preset-chip.active {
  background: var(--gradient-primary);
  color: #ffffff;
  border-color: transparent;
  box-shadow: 0 6px 18px rgba(233, 30, 99, 0.35);
}

/* Interactive Studio Cards */
.ai-studio-card {
  background: #ffffff;
  border: 1px solid rgba(226, 232, 240, 0.9);
  border-radius: 24px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
  transition: all 0.3s ease;
  overflow: hidden;
}

.ai-studio-card:hover {
  box-shadow: 0 16px 40px rgba(233, 30, 99, 0.08);
}

.ai-card-header {
  padding: 1.5rem 1.75rem 1rem 1.75rem;
  border-bottom: 1px solid #f1f5f9;
  background: transparent;
}

/* Interactive Selection Chips */
.chip-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 0.65rem;
}

.chip-grid-3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.65rem;
}

.selection-chip {
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 14px;
  padding: 0.7rem 0.6rem;
  text-align: center;
  cursor: pointer;
  transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  user-select: none;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 72px;
}

.selection-chip:hover {
  background: #ffffff;
  border-color: #cbd5e1;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

.selection-chip.active {
  background: #fdf2f8;
  border-color: #ec4899;
  color: #be185d;
  box-shadow: 0 4px 15px rgba(236, 72, 153, 0.2);
  transform: translateY(-2px);
}

.selection-chip .chip-icon {
  font-size: 1.4rem;
  margin-bottom: 0.3rem;
  transition: transform 0.2s ease;
}

.selection-chip.active .chip-icon {
  transform: scale(1.15);
}

.selection-chip .chip-label {
  font-size: 0.82rem;
  font-weight: 600;
  line-height: 1.2;
}

.selection-chip .chip-sub {
  font-size: 0.7rem;
  color: var(--ai-text-muted);
  margin-top: 2px;
}

.selection-chip.active .chip-sub {
  color: #9d174d;
}

.selection-chip .chip-check {
  position: absolute;
  top: 5px;
  right: 6px;
  font-size: 0.65rem;
  color: #ec4899;
  opacity: 0;
  transform: scale(0.5);
  transition: all 0.2s ease;
}

.selection-chip.active .chip-check {
  opacity: 1;
  transform: scale(1);
}

/* Primary CTA Button */
.btn-ai-generate {
  background: linear-gradient(135deg, #e91e63 0%, #9c27b0 50%, #673ab7 100%);
  color: #ffffff;
  border: none;
  border-radius: 50px;
  padding: 0.95rem 1.75rem;
  font-size: 1.05rem;
  font-weight: 700;
  box-shadow: 0 8px 25px rgba(233, 30, 99, 0.4);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.btn-ai-generate:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 12px 30px rgba(233, 30, 99, 0.55);
  color: #ffffff;
}

.btn-ai-generate:disabled {
  opacity: 0.8;
  cursor: not-allowed;
}

.btn-ai-generate::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, transparent 70%);
  opacity: 0;
  transition: opacity 0.3s ease;
}

.btn-ai-generate:hover::before {
  opacity: 1;
}

/* Right Column - Consultation Area */
.consultation-box {
  background: #fafafa;
  border-radius: 20px;
  padding: 1.75rem;
  border: 1px solid #f1f5f9;
}

/* Audio Player Bar */
.audio-action-bar {
  background: linear-gradient(135deg, #fdf2f8 0%, #f5f3ff 100%);
  border: 1px solid rgba(236, 72, 153, 0.25);
  border-radius: 16px;
  padding: 0.75rem 1.25rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.soundwave-box {
  display: flex;
  align-items: center;
  gap: 3px;
  height: 22px;
}

.soundwave-bar {
  width: 3px;
  height: 6px;
  background: #ec4899;
  border-radius: 3px;
  animation: none;
}

.soundwave-box.playing .soundwave-bar {
  animation: soundwaveAnim 1s ease-in-out infinite alternate;
}

.soundwave-box.playing .soundwave-bar:nth-child(2) { animation-delay: 0.15s; }
.soundwave-box.playing .soundwave-bar:nth-child(3) { animation-delay: 0.3s; }
.soundwave-box.playing .soundwave-bar:nth-child(4) { animation-delay: 0.45s; }
.soundwave-box.playing .soundwave-bar:nth-child(5) { animation-delay: 0.2s; }
.soundwave-box.playing .soundwave-bar:nth-child(6) { animation-delay: 0.35s; }

@keyframes soundwaveAnim {
  0% { height: 4px; }
  100% { height: 20px; }
}

/* Recommended Service Cards */
.recommended-service-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 1rem 1.25rem;
  transition: all 0.25s ease;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}

.recommended-service-card:hover {
  border-color: #f472b6;
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(233, 30, 99, 0.12);
}

/* Rich Markdown Consultation Output Styling */
.rendered-advice {
  font-size: 0.96rem;
  line-height: 1.8;
  color: #1e293b;
}

.rendered-advice h1,
.rendered-advice h2,
.rendered-advice h3 {
  font-family: 'Playfair Display', serif;
  color: #1e1b4b;
  font-weight: 700;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.rendered-advice h3 {
  font-size: 1.2rem;
  border-bottom: 2px dashed rgba(233, 30, 99, 0.2);
  padding-bottom: 0.4rem;
}

.rendered-advice ul {
  padding-left: 1.25rem;
  margin-bottom: 1.25rem;
}

.rendered-advice li {
  margin-bottom: 0.5rem;
  position: relative;
}

.rendered-advice hr {
  border-color: rgba(233, 30, 99, 0.15);
  margin: 1.5rem 0;
}

.rendered-advice strong {
  color: #be185d;
  font-weight: 600;
}

.rendered-advice blockquote {
  border-left: 4px solid #ec4899;
  background: #fdf2f8;
  padding: 0.75rem 1.25rem;
  border-radius: 0 12px 12px 0;
  margin: 1rem 0;
  font-style: italic;
  color: #831843;
}

/* Multi-step Loading Animation */
.step-loader {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  max-width: 380px;
  margin: 0 auto;
}

.step-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.88rem;
  color: #64748b;
  padding: 0.5rem 0.85rem;
  border-radius: 12px;
  background: #f8fafc;
  transition: all 0.3s ease;
}

.step-item.active {
  background: #fdf2f8;
  color: #be185d;
  font-weight: 600;
  box-shadow: 0 4px 12px rgba(236, 72, 153, 0.15);
}

.step-item.done {
  color: #10b981;
}

/* ========================================================
   DARK MODE ADAPTATIONS
   ======================================================== */
body.dark-mode .ai-studio-card {
  background: #1a1a2e !important;
  border-color: #2d2d48 !important;
  box-shadow: 0 12px 35px rgba(0, 0, 0, 0.5) !important;
}

body.dark-mode .ai-card-header {
  border-bottom-color: #2d2d48 !important;
}

body.dark-mode .btn-preset-chip {
  background: #252542 !important;
  color: #e2e8f0 !important;
  border-color: #3b3b5e !important;
}

body.dark-mode .btn-preset-chip:hover,
body.dark-mode .btn-preset-chip.active {
  background: var(--gradient-primary) !important;
  color: #ffffff !important;
  border-color: transparent !important;
}

body.dark-mode .selection-chip {
  background: #1e1e38 !important;
  border-color: #2d2d48 !important;
  color: #e2e8f0 !important;
}

body.dark-mode .selection-chip:hover {
  background: #252542 !important;
  border-color: #475569 !important;
}

body.dark-mode .selection-chip.active {
  background: rgba(236, 72, 153, 0.15) !important;
  border-color: #ec4899 !important;
  color: #f472b6 !important;
  box-shadow: 0 4px 15px rgba(236, 72, 153, 0.3) !important;
}

body.dark-mode .selection-chip .chip-sub {
  color: #94a3b8 !important;
}

body.dark-mode .selection-chip.active .chip-sub {
  color: #fbcfe8 !important;
}

body.dark-mode .consultation-box {
  background: #151528 !important;
  border-color: #2d2d48 !important;
}

body.dark-mode .audio-action-bar {
  background: rgba(236, 72, 153, 0.12) !important;
  border-color: rgba(236, 72, 153, 0.3) !important;
}

body.dark-mode .recommended-service-card {
  background: #1e1e38 !important;
  border-color: #2d2d48 !important;
  color: #f8fafc !important;
}

body.dark-mode .recommended-service-card:hover {
  border-color: #ec4899 !important;
}

body.dark-mode .rendered-advice {
  color: #cbd5e1 !important;
}

body.dark-mode .rendered-advice h1,
body.dark-mode .rendered-advice h2,
body.dark-mode .rendered-advice h3 {
  color: #f8fafc !important;
}

body.dark-mode .rendered-advice strong {
  color: #f472b6 !important;
}

body.dark-mode .rendered-advice blockquote {
  background: rgba(236, 72, 153, 0.15) !important;
  color: #fbcfe8 !important;
  border-left-color: #ec4899 !important;
}

body.dark-mode .step-item {
  background: #1e1e38 !important;
  color: #94a3b8 !important;
}

body.dark-mode .step-item.active {
  background: rgba(236, 72, 153, 0.2) !important;
  color: #f472b6 !important;
}

/* Print Styling */
@media print {
  header, footer, .ai-hero-card, #inputColumn, .ai-quick-prompts-bar, .audio-action-bar, .btn-print, .btn-copy {
    display: none !important;
  }
  #outputColumn {
    width: 100% !important;
  }
  .ai-studio-card {
    border: none !important;
    box-shadow: none !important;
  }
}
</style>

<div class="container py-4">

  <!-- ==========================================
       HERO BANNER - LUXURY AI BEAUTY STUDIO
       ========================================== -->
  <div class="ai-hero-card p-4 p-md-5 mb-4">
    <div class="row align-items-center position-relative" style="z-index: 2;">
      <div class="col-lg-8">
        <div class="d-flex flex-wrap gap-2 mb-3">
          <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold shadow-sm">
            <i class="fas fa-sparkles me-1"></i> Google Gemini AI Powered
          </span>
          <span class="ai-pill-tag">
            <i class="fas fa-store"></i> Menu-Matched
          </span>
          <span class="ai-pill-tag">
            <i class="fas fa-volume-up"></i> Voice Consultation
          </span>
        </div>

        <h1 class="display-6 fw-bold mb-2">Salonora AI Style & Beauty Studio</h1>
        <p class="lead mb-3 text-white-50" style="max-width: 680px; font-size: 1.05rem;">
          Your private celebrity stylist and aesthetician. Discover the ideal haircuts, skin therapies, hair textures, and beauty treatments formulated specifically for your face shape and personal vibe.
        </p>

        <div class="d-flex flex-wrap align-items-center gap-3 text-white-50 small">
          <div><i class="fas fa-check-circle text-success me-1"></i> 100% Tailored to You</div>
          <div><i class="fas fa-check-circle text-success me-1"></i> Instant Booking Links</div>
          <div><i class="fas fa-check-circle text-success me-1"></i> English & සිංහල</div>
        </div>
      </div>

      <div class="col-lg-4 text-center mt-4 mt-lg-0">
        <div class="d-inline-flex flex-column align-items-center">
          <div class="ai-avatar-badge mb-2">
            <div class="ai-avatar-inner">
              <i class="fas fa-magic"></i>
              <div class="ai-pulse-dot" title="Aura AI is Online"></div>
            </div>
          </div>
          <div class="fw-bold text-white fs-6">Aura • Master AI Stylist</div>
          <div class="text-white-50 small">Real-Time Salon Consultation</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ==========================================
       QUICK INSPIRATION PROMPTS BAR
       ========================================== -->
  <div class="mb-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <span class="fw-bold small text-muted text-uppercase letter-spacing-1">
        <i class="fas fa-bolt text-warning me-1"></i> Instant Style Presets:
      </span>
      <small class="text-muted">Click any preset to auto-fill</small>
    </div>

    <div class="ai-quick-prompts-bar">
      <button type="button" class="btn-preset-chip" onclick="applyPreset('round_face_layers')">
        ✨ Round Face Slimming Layers
      </button>
      <button type="button" class="btn-preset-chip" onclick="applyPreset('bridal_glow')">
        👰 Bridal Glow & Hair Elegance
      </button>
      <button type="button" class="btn-preset-chip" onclick="applyPreset('mens_crop_fade')">
        🧔 Men's Textured Fade & Beard
      </button>
      <button type="button" class="btn-preset-chip" onclick="applyPreset('curly_frizz_repair')">
        🌀 Curly Hair Frizz & Hydration
      </button>
      <button type="button" class="btn-preset-chip" onclick="applyPreset('chic_french_bob')">
        💇‍♀️ Modern Chic French Bob
      </button>
      <button type="button" class="btn-preset-chip" onclick="applyPreset('hair_damage_repair')">
        🌿 Keratin & Damage Recovery
      </button>
    </div>
  </div>

  <!-- ==========================================
       STUDIO MAIN GRID
       ========================================== -->
  <div class="row g-4">

    <!-- LEFT COLUMN: PREFERENCES STUDIO FORM -->
    <div class="col-lg-5" id="inputColumn">
      <div class="ai-studio-card h-100">
        <div class="ai-card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="fw-bold mb-1"><i class="fas fa-sliders-h text-primary me-2"></i>Personalize Style</h5>
            <p class="text-muted small mb-0">Select your attributes for precision advice.</p>
          </div>
          <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="resetStudioForm()">
            <i class="fas fa-redo-alt me-1"></i> Reset
          </button>
        </div>

        <div class="p-4">
          <form id="aiAdvisorForm">
            
            <!-- Salon Selector -->
            <div class="mb-4">
              <label class="form-label fw-bold small text-dark d-flex justify-content-between align-items-center">
                <span><i class="fas fa-store text-primary me-1"></i> Target Salon (Menu Match)</span>
                <span class="badge bg-light text-muted border fw-normal">Optional</span>
              </label>
              <select class="form-select rounded-3 py-2 shadow-sm" id="aiSalonSelect">
                <option value="0">⭐ All Salons / Universal Beauty Advice</option>
                <?php foreach ($allSalons as $salon): ?>
                  <option value="<?= $salon['id'] ?>" <?= $salon['id'] == $preselect_salon_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($salon['name']) ?> (<?= htmlspecialchars($salon['address']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text small text-muted mt-1">
                <i class="fas fa-info-circle me-1"></i> Selecting a salon matches Gemini's recommendations directly to their bookable menu.
              </div>
            </div>

            <!-- Language Switcher -->
            <div class="mb-4">
              <label class="form-label fw-bold small text-dark d-block">
                <i class="fas fa-language text-primary me-1"></i> Consultation Language / භාෂාව
              </label>
              <div class="btn-group w-100 shadow-sm rounded-3 overflow-hidden" role="group">
                <input type="radio" class="btn-check" name="langOption" id="langEn" value="en" checked>
                <label class="btn btn-outline-primary py-2 fw-semibold" for="langEn">
                  🇬🇧 English
                </label>

                <input type="radio" class="btn-check" name="langOption" id="langSi" value="si">
                <label class="btn btn-outline-primary py-2 fw-semibold" for="langSi">
                  🇱🇰 සිංහල (Sinhala)
                </label>
              </div>
            </div>

            <!-- Gender Selection (Chips) -->
            <div class="mb-4">
              <label class="form-label fw-bold small text-dark d-block">
                <i class="fas fa-user text-primary me-1"></i> Client Identity
              </label>
              <div class="chip-grid-3" id="genderChips">
                <div class="selection-chip active" data-value="Female" onclick="selectChip('gender', this)">
                  <span class="chip-icon">👩</span>
                  <span class="chip-label">Female</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
                <div class="selection-chip" data-value="Male" onclick="selectChip('gender', this)">
                  <span class="chip-icon">👨</span>
                  <span class="chip-label">Male</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
                <div class="selection-chip" data-value="Unisex" onclick="selectChip('gender', this)">
                  <span class="chip-icon">✨</span>
                  <span class="chip-label">Unisex / Any</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
              </div>
              <input type="hidden" id="aiGender" value="Female">
            </div>

            <!-- Face Shape Visual Selection -->
            <div class="mb-4">
              <label class="form-label fw-bold small text-dark d-flex justify-content-between">
                <span><i class="fas fa-smile text-primary me-1"></i> Face Shape</span>
                <span class="text-primary small fw-semibold" id="selectedFaceLabel">Oval</span>
              </label>
              <div class="chip-grid" id="faceShapeChips">
                <div class="selection-chip active" data-value="Oval" onclick="selectChip('faceShape', this)">
                  <span class="chip-icon">🥚</span>
                  <span class="chip-label">Oval</span>
                  <span class="chip-sub">Balanced</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
                <div class="selection-chip" data-value="Round" onclick="selectChip('faceShape', this)">
                  <span class="chip-icon">⭕</span>
                  <span class="chip-label">Round</span>
                  <span class="chip-sub">Soft & Full</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
                <div class="selection-chip" data-value="Square" onclick="selectChip('faceShape', this)">
                  <span class="chip-icon">🔲</span>
                  <span class="chip-label">Square</span>
                  <span class="chip-sub">Defined Jaw</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
                <div class="selection-chip" data-value="Heart" onclick="selectChip('faceShape', this)">
                  <span class="chip-icon">💖</span>
                  <span class="chip-label">Heart</span>
                  <span class="chip-sub">Wide Forehead</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
                <div class="selection-chip" data-value="Diamond" onclick="selectChip('faceShape', this)">
                  <span class="chip-icon">💎</span>
                  <span class="chip-label">Diamond</span>
                  <span class="chip-sub">High Cheeks</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
                <div class="selection-chip" data-value="Oblong" onclick="selectChip('faceShape', this)">
                  <span class="chip-icon">📏</span>
                  <span class="chip-label">Oblong</span>
                  <span class="chip-sub">Elongated</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
              </div>
              <input type="hidden" id="aiFaceShape" value="Oval">
            </div>

            <!-- Hair Type / Texture Selection -->
            <div class="mb-4">
              <label class="form-label fw-bold small text-dark d-flex justify-content-between">
                <span><i class="fas fa-feather-alt text-primary me-1"></i> Hair Texture & Type</span>
                <span class="text-primary small fw-semibold" id="selectedHairLabel">Straight</span>
              </label>
              <div class="chip-grid" id="hairTypeChips">
                <div class="selection-chip active" data-value="Straight" onclick="selectChip('hairType', this)">
                  <span class="chip-icon">💇‍♀️</span>
                  <span class="chip-label">Straight</span>
                  <span class="chip-sub">Sleek</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
                <div class="selection-chip" data-value="Wavy" onclick="selectChip('hairType', this)">
                  <span class="chip-icon">🌊</span>
                  <span class="chip-label">Wavy</span>
                  <span class="chip-sub">Natural S-Waves</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
                <div class="selection-chip" data-value="Curly" onclick="selectChip('hairType', this)">
                  <span class="chip-icon">🌀</span>
                  <span class="chip-label">Curly</span>
                  <span class="chip-sub">Defined Spirals</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
                <div class="selection-chip" data-value="Coily" onclick="selectChip('hairType', this)">
                  <span class="chip-icon">➰</span>
                  <span class="chip-label">Coily / Kinky</span>
                  <span class="chip-sub">Tight Ringlets</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
                <div class="selection-chip" data-value="Fine / Thin" onclick="selectChip('hairType', this)">
                  <span class="chip-icon">🪶</span>
                  <span class="chip-label">Fine / Thin</span>
                  <span class="chip-sub">Needs Volume</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
                <div class="selection-chip" data-value="Thick / Coarse" onclick="selectChip('hairType', this)">
                  <span class="chip-icon">🦁</span>
                  <span class="chip-label">Thick / Dense</span>
                  <span class="chip-sub">Needs Debulking</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
              </div>
              <input type="hidden" id="aiHairType" value="Straight">
            </div>

            <!-- Occasion / Vibe Selection -->
            <div class="mb-4">
              <label class="form-label fw-bold small text-dark d-flex justify-content-between">
                <span><i class="fas fa-sparkles text-primary me-1"></i> Desired Vibe / Occasion</span>
                <span class="text-primary small fw-semibold" id="selectedVibeLabel">Modern Everyday</span>
              </label>
              <div class="chip-grid" id="vibeChips">
                <div class="selection-chip active" data-value="Modern Everyday" onclick="selectChip('vibe', this)">
                  <span class="chip-icon">✨</span>
                  <span class="chip-label">Modern Everyday</span>
                  <span class="chip-sub">Effortless Chic</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
                <div class="selection-chip" data-value="Wedding / Festive Event" onclick="selectChip('vibe', this)">
                  <span class="chip-icon">💍</span>
                  <span class="chip-label">Wedding & Festive</span>
                  <span class="chip-sub">Glamour & Glow</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
                <div class="selection-chip" data-value="Professional Office" onclick="selectChip('vibe', this)">
                  <span class="chip-icon">💼</span>
                  <span class="chip-label">Corporate Office</span>
                  <span class="chip-sub">Polished & Clean</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
                <div class="selection-chip" data-value="Bold / Edgy Trend" onclick="selectChip('vibe', this)">
                  <span class="chip-icon">⚡</span>
                  <span class="chip-label">Bold & Edgy</span>
                  <span class="chip-sub">Trendsetter</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
                <div class="selection-chip" data-value="Low Maintenance" onclick="selectChip('vibe', this)">
                  <span class="chip-icon">☕</span>
                  <span class="chip-label">Low Maintenance</span>
                  <span class="chip-sub">Wash & Go</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
                <div class="selection-chip" data-value="Hair Damage Repair" onclick="selectChip('vibe', this)">
                  <span class="chip-icon">🌿</span>
                  <span class="chip-label">Damage Recovery</span>
                  <span class="chip-sub">Nourish & Heal</span>
                  <i class="fas fa-check-circle chip-check"></i>
                </div>
              </div>
              <input type="hidden" id="aiVibe" value="Modern Everyday">
            </div>

            <!-- Custom Notes or Specific Needs -->
            <div class="mb-4">
              <label class="form-label fw-bold small text-dark d-flex justify-content-between align-items-center">
                <span><i class="fas fa-pen-fancy text-primary me-1"></i> Specific Wishes, Skin Concerns, or Questions</span>
                <span class="badge bg-light text-muted border fw-normal">Optional</span>
              </label>
              <textarea class="form-control rounded-3 shadow-sm" id="aiQuery" rows="3" placeholder="e.g. I have a wedding this Saturday, wear glasses, and prefer low maintenance layers with a hydrating skin treatment..."></textarea>
            </div>

            <!-- Generate Button -->
            <button type="submit" id="btnGetAdvice" class="btn btn-ai-generate w-100 py-3 shadow-lg">
              <i class="fas fa-sparkles me-2"></i> Generate AI Aesthetic Blueprint
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- RIGHT COLUMN: CONSULTATION BLUEPRINT STUDIO -->
    <div class="col-lg-7" id="outputColumn">
      <div class="ai-studio-card h-100 d-flex flex-column">
        
        <!-- Header -->
        <div class="ai-card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="fw-bold mb-1 font-playfair"><i class="fas fa-crown text-warning me-2"></i>Aesthetic Blueprint</h5>
            <p class="text-muted small mb-0">Your customized salon consultation report.</p>
          </div>
          <span id="aiStatusBadge" class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-2 small fw-semibold">
            <i class="fas fa-clock me-1"></i> Ready
          </span>
        </div>

        <div class="p-4 flex-grow-1 d-flex flex-column">
          
          <!-- STATE 1: WAITING / PLACEHOLDER -->
          <div id="aiPlaceholder" class="text-center py-5 my-auto">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light mb-4 shadow-sm" style="width: 100px; height: 100px; border: 2px dashed rgba(233, 30, 99, 0.3);">
              <i class="fas fa-magic display-5 text-primary opacity-75"></i>
            </div>
            <h4 class="fw-bold mb-2">Ready to Discover Your Signature Look?</h4>
            <p class="text-muted small mx-auto mb-4" style="max-width: 460px; line-height: 1.6;">
              Select your face shape, hair texture, and style preferences on the left, or click any of the <strong>Instant Style Presets</strong> above. Gemini AI will curate a personalized beauty masterplan and match live salon services.
            </p>

            <div class="d-flex flex-wrap justify-content-center gap-2">
              <span class="badge bg-light text-dark border px-3 py-2 rounded-pill small">
                <i class="fas fa-check text-success me-1"></i> Face Contour Analysis
              </span>
              <span class="badge bg-light text-dark border px-3 py-2 rounded-pill small">
                <i class="fas fa-check text-success me-1"></i> Hair Volume Strategy
              </span>
              <span class="badge bg-light text-dark border px-3 py-2 rounded-pill small">
                <i class="fas fa-check text-success me-1"></i> At-Home Pro Tips
              </span>
            </div>
          </div>

          <!-- STATE 2: LOADING PROGRESS -->
          <div id="aiLoading" class="text-center py-5 my-auto d-none">
            <div class="spinner-grow text-primary mb-3" style="width: 3.5rem; height: 3.5rem;" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <h4 class="fw-bold mb-2 font-playfair">Formulating Your Blueprint...</h4>
            <p class="text-muted small mb-4">Gemini AI is analyzing face geometry and matching real salon services.</p>

            <div class="step-loader">
              <div class="step-item active" id="step1">
                <i class="fas fa-spinner fa-spin text-primary"></i>
                <span>Analyzing face contour & hair physics</span>
              </div>
              <div class="step-item" id="step2">
                <i class="far fa-circle"></i>
                <span>Cross-referencing salon treatment catalogue</span>
              </div>
              <div class="step-item" id="step3">
                <i class="far fa-circle"></i>
                <span>Curating personalized aesthetic prescription</span>
              </div>
            </div>
          </div>

          <!-- STATE 3: COMPLETE CONSULTATION RESULT -->
          <div id="aiResultArea" class="d-none">

            <!-- Audio & Utility Action Bar -->
            <div class="audio-action-bar">
              <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-sm btn-gradient rounded-pill px-3 py-2 fw-semibold d-flex align-items-center gap-2" id="btnAudioSpeak" onclick="toggleAudioSpeech()">
                  <i class="fas fa-volume-up" id="audioBtnIcon"></i>
                  <span id="audioBtnText">Listen to Advice</span>
                </button>

                <div class="soundwave-box d-none" id="soundwaveBox">
                  <div class="soundwave-bar"></div>
                  <div class="soundwave-bar"></div>
                  <div class="soundwave-bar"></div>
                  <div class="soundwave-bar"></div>
                  <div class="soundwave-bar"></div>
                  <div class="soundwave-bar"></div>
                </div>
              </div>

              <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 btn-copy" onclick="copyConsultationText()" title="Copy Plan to Clipboard">
                  <i class="far fa-copy me-1"></i> Copy
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 btn-print" onclick="window.print()" title="Print / Save Blueprint">
                  <i class="fas fa-print me-1"></i> Print
                </button>
              </div>
            </div>

            <!-- Matched Salon Services Container -->
            <div id="matchedServicesContainer" class="mb-4 d-none">
              <div class="card border-0 bg-primary-subtle bg-opacity-10 rounded-4 p-3 border-start border-4 border-warning shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <h6 class="fw-bold text-dark mb-0 d-flex align-items-center">
                    <i class="fas fa-sparkles text-warning me-2"></i> Recommended Services at This Salon:
                  </h6>
                  <span class="badge bg-warning text-dark rounded-pill px-2 py-1 small fw-bold">Live Menu</span>
                </div>
                <div id="matchedServicesList" class="d-flex flex-column gap-2"></div>
              </div>
            </div>

            <!-- Rendered Rich Markdown Consultation -->
            <div class="consultation-box">
              <div id="aiAdviceText" class="rendered-advice"></div>
            </div>

            <!-- Follow-up prompt -->
            <div class="text-center mt-4 pt-3 border-top">
              <button type="button" class="btn btn-outline-primary rounded-pill px-4" onclick="document.getElementById('inputColumn').scrollIntoView({behavior:'smooth'})">
                <i class="fas fa-sliders-h me-1"></i> Adjust Preferences & Re-Consult
              </button>
            </div>

          </div>

        </div>
      </div>
    </div>

  </div>
</div>

<script>
// ==========================================
// PRESETS CONFIGURATION
// ==========================================
const PRESETS = {
  'round_face_layers': {
    gender: 'Female',
    faceShape: 'Round',
    hairType: 'Wavy',
    vibe: 'Modern Everyday',
    query: 'I have a round face shape and soft wavy hair. I want an effortless, face-slimming haircut with long layers and a hydrating glow treatment.',
    lang: 'en'
  },
  'bridal_glow': {
    gender: 'Female',
    faceShape: 'Oval',
    hairType: 'Straight',
    vibe: 'Wedding / Festive Event',
    query: 'I am preparing for an upcoming wedding celebration. Looking for high-glamour hairstyle recommendations, bridal hair prep, and radiant facial packages.',
    lang: 'en'
  },
  'mens_crop_fade': {
    gender: 'Male',
    faceShape: 'Square',
    hairType: 'Thick / Coarse',
    vibe: 'Modern Everyday',
    query: 'Looking for a sharp, modern textured crop with a skin fade, beard shaping advice, and scalp wellness treatment for thick hair.',
    lang: 'en'
  },
  'curly_frizz_repair': {
    gender: 'Female',
    faceShape: 'Heart',
    hairType: 'Curly',
    vibe: 'Hair Damage Repair',
    query: 'My curly hair suffers from humidity frizz and dryness. Need specialized curl definition cut advice and deep moisturizing spa treatments.',
    lang: 'en'
  },
  'chic_french_bob': {
    gender: 'Female',
    faceShape: 'Oval',
    hairType: 'Fine / Thin',
    vibe: 'Bold / Edgy Trend',
    query: 'I want a chic French bob with curtain bangs to add instant volume and effortless French girl chic to my fine hair.',
    lang: 'en'
  },
  'hair_damage_repair': {
    gender: 'Unisex',
    faceShape: 'Oval',
    hairType: 'Fine / Thin',
    vibe: 'Hair Damage Repair',
    query: 'My hair is damaged from color/bleach and heat styling. Recommend the most effective restorative salon keratin and deep conditioning treatments.',
    lang: 'en'
  }
};

function applyPreset(key) {
  const p = PRESETS[key];
  if (!p) return;

  // Highlight chip
  document.querySelectorAll('.btn-preset-chip').forEach(btn => btn.classList.remove('active'));
  event.target.classList.add('active');

  // Set gender
  setChipValue('gender', p.gender);
  // Set face shape
  setChipValue('faceShape', p.faceShape);
  // Set hair type
  setChipValue('hairType', p.hairType);
  // Set vibe
  setChipValue('vibe', p.vibe);

  // Set language
  if (p.lang === 'si') {
    document.getElementById('langSi').checked = true;
  } else {
    document.getElementById('langEn').checked = true;
  }

  // Set query
  document.getElementById('aiQuery').value = p.query;

  // Scroll to form smoothly
  document.getElementById('aiAdvisorForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function selectChip(type, element) {
  const container = element.parentElement;
  container.querySelectorAll('.selection-chip').forEach(c => c.classList.remove('active'));
  element.classList.add('active');

  const val = element.getAttribute('data-value');

  if (type === 'gender') {
    document.getElementById('aiGender').value = val;
  } else if (type === 'faceShape') {
    document.getElementById('aiFaceShape').value = val;
    document.getElementById('selectedFaceLabel').innerText = val;
  } else if (type === 'hairType') {
    document.getElementById('aiHairType').value = val;
    document.getElementById('selectedHairLabel').innerText = val;
  } else if (type === 'vibe') {
    document.getElementById('aiVibe').value = val;
    document.getElementById('selectedVibeLabel').innerText = val;
  }
}

function setChipValue(type, val) {
  let containerId = '';
  let inputId = '';
  let labelId = '';

  if (type === 'gender') { containerId = 'genderChips'; inputId = 'aiGender'; }
  if (type === 'faceShape') { containerId = 'faceShapeChips'; inputId = 'aiFaceShape'; labelId = 'selectedFaceLabel'; }
  if (type === 'hairType') { containerId = 'hairTypeChips'; inputId = 'aiHairType'; labelId = 'selectedHairLabel'; }
  if (type === 'vibe') { containerId = 'vibeChips'; inputId = 'aiVibe'; labelId = 'selectedVibeLabel'; }

  const container = document.getElementById(containerId);
  if (!container) return;

  const targetChip = Array.from(container.querySelectorAll('.selection-chip')).find(c => c.getAttribute('data-value') === val);
  if (targetChip) {
    container.querySelectorAll('.selection-chip').forEach(c => c.classList.remove('active'));
    targetChip.classList.add('active');
    document.getElementById(inputId).value = val;
    if (labelId) document.getElementById(labelId).innerText = val;
  }
}

function resetStudioForm() {
  document.getElementById('aiAdvisorForm').reset();
  setChipValue('gender', 'Female');
  setChipValue('faceShape', 'Oval');
  setChipValue('hairType', 'Straight');
  setChipValue('vibe', 'Modern Everyday');
  document.getElementById('langEn').checked = true;
  document.getElementById('aiQuery').value = '';
  document.querySelectorAll('.btn-preset-chip').forEach(btn => btn.classList.remove('active'));
}

// ==========================================
// FORM SUBMISSION & GEMINI CONSULTATION
// ==========================================
let currentAdviceRaw = '';
let isSpeaking = false;

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

  // Stop any ongoing speech
  stopAudioSpeech();

  // Loading state
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Formulating with Gemini AI...';
  statusBadge.className = 'badge bg-warning text-dark rounded-pill px-3 py-2 small fw-semibold';
  statusBadge.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Consulting...';

  placeholder.classList.add('d-none');
  resultArea.classList.add('d-none');
  loading.classList.remove('d-none');

  // Step loader progress sequence
  const step1 = document.getElementById('step1');
  const step2 = document.getElementById('step2');
  const step3 = document.getElementById('step3');

  step1.className = 'step-item active';
  step2.className = 'step-item';
  step3.className = 'step-item';

  const t1 = setTimeout(() => {
    step1.className = 'step-item done';
    step1.innerHTML = '<i class="fas fa-check-circle text-success"></i> <span>Face contour & hair physics analyzed</span>';
    step2.className = 'step-item active';
    step2.innerHTML = '<i class="fas fa-spinner fa-spin text-primary"></i> <span>Cross-referencing salon menu catalogue...</span>';
  }, 1500);

  const t2 = setTimeout(() => {
    step2.className = 'step-item done';
    step2.innerHTML = '<i class="fas fa-check-circle text-success"></i> <span>Salon catalogue matched</span>';
    step3.className = 'step-item active';
    step3.innerHTML = '<i class="fas fa-spinner fa-spin text-primary"></i> <span>Curating personalized aesthetic prescription...</span>';
  }, 3200);

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

    clearTimeout(t1);
    clearTimeout(t2);

    const data = await response.json();

    loading.classList.add('d-none');
    resultArea.classList.remove('d-none');

    if (data.success) {
      statusBadge.className = 'badge bg-success text-white rounded-pill px-3 py-2 small fw-semibold';
      statusBadge.innerHTML = '<i class="fas fa-check-circle me-1"></i> Consultation Complete';

      currentAdviceRaw = data.advice;

      // Render Rich Markdown via Marked.js
      if (typeof marked !== 'undefined') {
        adviceText.innerHTML = marked.parse(data.advice);
      } else {
        adviceText.innerText = data.advice;
      }

      // Confetti burst for delight!
      if (typeof confetti === 'function') {
        confetti({
          particleCount: 50,
          spread: 60,
          origin: { y: 0.6 }
        });
      }

      // Render Matched Services
      if (data.matched_services && data.matched_services.length > 0) {
        matchedList.innerHTML = '';
        data.matched_services.forEach(s => {
          const item = document.createElement('div');
          item.className = 'recommended-service-card';
          const salonIdForService = (s.salon_id && s.salon_id > 0) ? s.salon_id : (data.salon_id > 0 ? data.salon_id : 1);
          item.innerHTML = `
            <div>
              <div class="d-flex align-items-center gap-2">
                <span class="fw-bold text-dark fs-6">${escapeHtml(s.name)}</span>
                ${s.category ? `<span class="badge bg-secondary-subtle text-secondary rounded-pill small">${escapeHtml(s.category)}</span>` : ''}
              </div>
              <div class="small text-muted d-flex align-items-center gap-3 mt-1">
                <span class="text-primary fw-bold">Rs. ${parseFloat(s.price).toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
                <span><i class="far fa-clock me-1"></i> ~${s.duration} mins</span>
              </div>
            </div>
            <a href="<?= url('user/book_appointment.php') ?>?salon_id=${salonIdForService}&service_id=${s.id}" class="btn btn-sm btn-gradient rounded-pill px-3 py-2 fw-semibold">
              <i class="fas fa-calendar-check me-1"></i> Book Now
            </a>
          `;
          matchedList.appendChild(item);
        });
        matchedContainer.classList.remove('d-none');
      } else {
        matchedContainer.classList.add('d-none');
      }

      // Smooth scroll to results on mobile
      if (window.innerWidth < 992) {
        document.getElementById('outputColumn').scrollIntoView({ behavior: 'smooth' });
      }

    } else {
      statusBadge.className = 'badge bg-danger text-white rounded-pill px-3 py-2 small fw-semibold';
      statusBadge.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Notice';
      adviceText.innerHTML = `<div class="alert alert-warning border-0 rounded-4">${escapeHtml(data.message || 'Unable to generate advice right now. Please try again.')}</div>`;
      matchedContainer.classList.add('d-none');
    }

  } catch (err) {
    clearTimeout(t1);
    clearTimeout(t2);
    loading.classList.add('d-none');
    resultArea.classList.remove('d-none');
    statusBadge.className = 'badge bg-danger text-white rounded-pill px-3 py-2 small fw-semibold';
    statusBadge.innerHTML = '<i class="fas fa-times-circle me-1"></i> Network Error';
    adviceText.innerHTML = `<div class="alert alert-danger border-0 rounded-4">Network error connecting to Gemini AI. Please check your internet connection and try again.</div>`;
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-sparkles me-2"></i> Generate AI Aesthetic Blueprint';
  }
});

// ==========================================
// VOICE NARRATION (SpeechSynthesis)
// ==========================================
function toggleAudioSpeech() {
  if (!('speechSynthesis' in window)) {
    alert('Voice narration is not supported in this browser.');
    return;
  }

  if (isSpeaking) {
    stopAudioSpeech();
  } else {
    startAudioSpeech();
  }
}

function startAudioSpeech() {
  if (!currentAdviceRaw) return;

  window.speechSynthesis.cancel();

  // Strip Markdown characters for smooth speech
  let cleanText = currentAdviceRaw
    .replace(/[#*_~`>]/g, '')
    .replace(/\[ID:\s*\d+\]/gi, '')
    .replace(/---/g, '')
    .trim();

  const utterance = new SpeechSynthesisUtterance(cleanText);
  utterance.rate = 0.95;
  utterance.pitch = 1.05;

  const btnIcon = document.getElementById('audioBtnIcon');
  const btnText = document.getElementById('audioBtnText');
  const soundwave = document.getElementById('soundwaveBox');

  utterance.onstart = function() {
    isSpeaking = true;
    btnIcon.className = 'fas fa-pause';
    btnText.innerText = 'Pause Narration';
    soundwave.classList.remove('d-none');
    soundwave.classList.add('playing');
  };

  utterance.onend = function() {
    stopAudioSpeech();
  };

  utterance.onerror = function() {
    stopAudioSpeech();
  };

  window.speechSynthesis.speak(utterance);
}

function stopAudioSpeech() {
  if ('speechSynthesis' in window) {
    window.speechSynthesis.cancel();
  }
  isSpeaking = false;
  const btnIcon = document.getElementById('audioBtnIcon');
  const btnText = document.getElementById('audioBtnText');
  const soundwave = document.getElementById('soundwaveBox');
  if (btnIcon) btnIcon.className = 'fas fa-volume-up';
  if (btnText) btnText.innerText = 'Listen to Advice';
  if (soundwave) {
    soundwave.classList.add('d-none');
    soundwave.classList.remove('playing');
  }
}

// ==========================================
// COPY TO CLIPBOARD
// ==========================================
function copyConsultationText() {
  if (!currentAdviceRaw) return;

  navigator.clipboard.writeText(currentAdviceRaw).then(() => {
    const copyBtn = document.querySelector('.btn-copy');
    const originalHtml = copyBtn.innerHTML;
    copyBtn.innerHTML = '<i class="fas fa-check text-success me-1"></i> Copied!';
    setTimeout(() => {
      copyBtn.innerHTML = originalHtml;
    }, 2000);
  }).catch(() => {
    alert('Failed to copy to clipboard.');
  });
}

// Helper to escape HTML safely
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}
</script>

<?php include __DIR__ . '/../footer.php'; ?>
