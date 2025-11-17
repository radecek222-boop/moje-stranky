<?php
/**
 * Control Center - Vzhled & Design
 * Pokročilý iPhone-style editor s live preview
 */

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized');
}

$pdo = getDbConnection();
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';

// Načtení aktuálního theme
$themeSettings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value, setting_type FROM wgs_theme_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $themeSettings[$row['setting_key']] = [
            'value' => $row['setting_value'],
            'type' => $row['setting_type']
        ];
    }
} catch (PDOException $e) {
    $themeSettings = [];
}

// Default hodnoty
$defaults = [
    // Barvy
    'overlay_bg' => 'rgba(0, 0, 0, 0.7)',
    'modal_bg' => '#ffffff',
    'text_color' => '#000000',
    'heading_color' => '#000000',
    'button_bg' => '#2D5016',
    'button_text' => '#ffffff',

    // Text styling
    'font_size_body' => '16',
    'font_size_heading' => '24',
    'text_align' => 'left',
    'line_height' => '1.5',
    'font_weight' => '400',

    // Glow efekty
    'glow_enabled' => 'false',
    'glow_color' => '#2D5016',
    'glow_intensity' => '10',

    // Layout
    'border_radius' => '8',
    'padding' => '16',
    'gap' => '16',
];

foreach ($defaults as $key => $value) {
    if (!isset($themeSettings[$key])) {
        $themeSettings[$key] = ['value' => $value, 'type' => 'text'];
    }
}

// Helper funkce pro získání hodnoty
function getSetting($settings, $key, $default) {
    return isset($settings[$key]) ? $settings[$key]['value'] : $default;
}
?>

<style>
/* iPhone-style Editor Layout */
.iphone-editor {
    display: grid;
    grid-template-columns: 400px 1fr;
    gap: 1rem;
    height: calc(100vh - 100px);
    padding: 0.75rem;
    background: #f5f5f5;
}

/* Controls Panel (levá strana) */
.controls-panel {
    background: white;
    border-radius: 12px;
    padding: 1rem;
    overflow-y: auto;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.control-section {
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #e0e0e0;
}

.control-section:last-child {
    border-bottom: none;
}

.control-section-title {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #666;
    margin-bottom: 1rem;
}

.control-group {
    margin-bottom: 1rem;
}

.control-label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #333;
}

.control-sublabel {
    font-size: 0.7rem;
    color: #999;
    margin-top: 0.25rem;
}

/* Color Picker - iPhone style */
.color-picker-group {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.color-preview {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    border: 3px solid #e0e0e0;
    cursor: pointer;
    transition: transform 0.2s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.color-preview:hover {
    transform: scale(1.05);
}

.color-input {
    flex: 1;
    height: 50px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    cursor: pointer;
}

.color-value {
    font-family: monospace;
    font-size: 0.85rem;
    color: #666;
    padding: 0.5rem;
    background: #f5f5f5;
    border-radius: 6px;
    text-align: center;
    min-width: 100px;
}

/* Slider - iPhone style */
.slider-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.slider-value-display {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
}

.slider-value {
    font-weight: 700;
    color: #2D5016;
}

input[type="range"] {
    width: 100%;
    height: 6px;
    border-radius: 5px;
    background: #e0e0e0;
    outline: none;
    -webkit-appearance: none;
}

input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #2D5016;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}

input[type="range"]::-moz-range-thumb {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #2D5016;
    cursor: pointer;
    border: none;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}

/* Toggle Switch - iPhone style */
.toggle-group {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.toggle-switch {
    position: relative;
    width: 50px;
    height: 28px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .3s;
    border-radius: 28px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

input:checked + .toggle-slider {
    background-color: #2D5016;
}

input:checked + .toggle-slider:before {
    transform: translateX(22px);
}

/* Button Group - iPhone style */
.button-group {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
}

.btn-option {
    padding: 0.6rem;
    border: 2px solid #e0e0e0;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.75rem;
    font-weight: 600;
    text-align: center;
}

.btn-option:hover {
    border-color: #2D5016;
}

.btn-option.active {
    background: #2D5016;
    color: white;
    border-color: #2D5016;
}

/* Preview Panel (pravá strana) */
.preview-panel {
    background: #1a1a1a;
    border-radius: 12px;
    padding: 2rem;
    overflow-y: auto;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    position: relative;
}

.preview-label {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(255,255,255,0.1);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Live Preview Control Center */
#livePreview {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.preview-overlay {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.preview-modal {
    width: 90%;
    max-width: 600px;
    border-radius: var(--preview-radius, 8px);
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    padding: var(--preview-padding, 16px);
}

.preview-content h2 {
    font-size: var(--preview-heading-size);
    color: var(--preview-heading-color);
    text-align: var(--preview-text-align);
    font-weight: var(--preview-font-weight);
    line-height: var(--preview-line-height);
    margin-bottom: 1rem;
}

.preview-content p {
    font-size: var(--preview-body-size);
    color: var(--preview-text-color);
    text-align: var(--preview-text-align);
    line-height: var(--preview-line-height);
    margin-bottom: 1rem;
}

.preview-button {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: var(--preview-radius);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.75rem;
    padding: 1rem;
    background: white;
    border-radius: 0 0 12px 12px;
    border-top: 1px solid #e0e0e0;
}

.btn-save {
    flex: 1;
    padding: 0.75rem;
    background: #2D5016;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-save:hover {
    background: #1a3009;
}

.btn-reset {
    padding: 0.75rem 1.5rem;
    background: #f5f5f5;
    color: #333;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
}

.btn-reset:hover {
    background: #e0e0e0;
}

/* Mobile responsive */
@media (max-width: 1024px) {
    .iphone-editor {
        grid-template-columns: 1fr;
        height: auto;
    }

    .preview-panel {
        min-height: 500px;
    }
}
</style>

<div class="iphone-editor">
    <!-- Controls Panel -->
    <div class="controls-panel">
        <!-- OVERLAY & MODAL -->
        <div class="control-section">
            <div class="control-section-title">Overlay & Modal</div>

            <div class="control-group">
                <label class="control-label">Pozadí Overlay</label>
                <div class="color-picker-group">
                    <div class="color-preview" id="overlay-bg-preview" onclick="document.getElementById('overlay-bg').click()"></div>
                    <input type="color" id="overlay-bg" class="color-input" onchange="updateStyle()">
                    <div class="color-value" id="overlay-bg-value"></div>
                </div>
                <div class="control-sublabel">Poloprůhledné pozadí overlay</div>
            </div>

            <div class="control-group">
                <label class="control-label">Pozadí Modalu</label>
                <div class="color-picker-group">
                    <div class="color-preview" id="modal-bg-preview" onclick="document.getElementById('modal-bg').click()"></div>
                    <input type="color" id="modal-bg" class="color-input" onchange="updateStyle()">
                    <div class="color-value" id="modal-bg-value"></div>
                </div>
                <div class="control-sublabel">Barva pozadí hlavního okna</div>
            </div>

            <div class="control-group">
                <label class="control-label">Zaoblení rohů</label>
                <div class="slider-group">
                    <div class="slider-value-display">
                        <span>Border Radius</span>
                        <span class="slider-value" id="radius-value">8px</span>
                    </div>
                    <input type="range" id="border-radius" min="0" max="32" value="8" oninput="updateStyle()">
                </div>
            </div>

            <div class="control-group">
                <label class="control-label">Vnitřní odsazení</label>
                <div class="slider-group">
                    <div class="slider-value-display">
                        <span>Padding</span>
                        <span class="slider-value" id="padding-value">16px</span>
                    </div>
                    <input type="range" id="padding" min="8" max="48" value="16" oninput="updateStyle()">
                </div>
            </div>
        </div>

        <!-- TEXT STYLING -->
        <div class="control-section">
            <div class="control-section-title">Textové styly</div>

            <div class="control-group">
                <label class="control-label">Barva textu</label>
                <div class="color-picker-group">
                    <div class="color-preview" id="text-color-preview" onclick="document.getElementById('text-color').click()"></div>
                    <input type="color" id="text-color" class="color-input" onchange="updateStyle()">
                    <div class="color-value" id="text-color-value"></div>
                </div>
            </div>

            <div class="control-group">
                <label class="control-label">Barva nadpisů</label>
                <div class="color-picker-group">
                    <div class="color-preview" id="heading-color-preview" onclick="document.getElementById('heading-color').click()"></div>
                    <input type="color" id="heading-color" class="color-input" onchange="updateStyle()">
                    <div class="color-value" id="heading-color-value"></div>
                </div>
            </div>

            <div class="control-group">
                <label class="control-label">Velikost textu</label>
                <div class="slider-group">
                    <div class="slider-value-display">
                        <span>Body</span>
                        <span class="slider-value" id="font-size-body-value">16px</span>
                    </div>
                    <input type="range" id="font-size-body" min="12" max="24" value="16" oninput="updateStyle()">
                </div>
            </div>

            <div class="control-group">
                <label class="control-label">Velikost nadpisů</label>
                <div class="slider-group">
                    <div class="slider-value-display">
                        <span>Heading</span>
                        <span class="slider-value" id="font-size-heading-value">24px</span>
                    </div>
                    <input type="range" id="font-size-heading" min="18" max="48" value="24" oninput="updateStyle()">
                </div>
            </div>

            <div class="control-group">
                <label class="control-label">Zarovnání textu</label>
                <div class="button-group">
                    <button class="btn-option active" data-align="left" onclick="setTextAlign('left')">Left</button>
                    <button class="btn-option" data-align="center" onclick="setTextAlign('center')">Center</button>
                    <button class="btn-option" data-align="right" onclick="setTextAlign('right')">Right</button>
                    <button class="btn-option" data-align="justify" onclick="setTextAlign('justify')">Justify</button>
                </div>
            </div>

            <div class="control-group">
                <label class="control-label">Tloušťka písma</label>
                <div class="button-group">
                    <button class="btn-option" data-weight="300" onclick="setFontWeight('300')">Light</button>
                    <button class="btn-option active" data-weight="400" onclick="setFontWeight('400')">Normal</button>
                    <button class="btn-option" data-weight="600" onclick="setFontWeight('600')">Semi</button>
                    <button class="btn-option" data-weight="700" onclick="setFontWeight('700')">Bold</button>
                </div>
            </div>

            <div class="control-group">
                <label class="control-label">Výška řádku</label>
                <div class="slider-group">
                    <div class="slider-value-display">
                        <span>Line Height</span>
                        <span class="slider-value" id="line-height-value">1.5</span>
                    </div>
                    <input type="range" id="line-height" min="1" max="2.5" step="0.1" value="1.5" oninput="updateStyle()">
                </div>
            </div>
        </div>

        <!-- BUTTONS -->
        <div class="control-section">
            <div class="control-section-title">Tlačítka</div>

            <div class="control-group">
                <label class="control-label">Pozadí tlačítek</label>
                <div class="color-picker-group">
                    <div class="color-preview" id="button-bg-preview" onclick="document.getElementById('button-bg').click()"></div>
                    <input type="color" id="button-bg" class="color-input" value="#2D5016" onchange="updateStyle()">
                    <div class="color-value" id="button-bg-value">#2D5016</div>
                </div>
            </div>

            <div class="control-group">
                <label class="control-label">Text tlačítek</label>
                <div class="color-picker-group">
                    <div class="color-preview" id="button-text-preview" onclick="document.getElementById('button-text').click()"></div>
                    <input type="color" id="button-text" class="color-input" value="#ffffff" onchange="updateStyle()">
                    <div class="color-value" id="button-text-value">#ffffff</div>
                </div>
            </div>
        </div>

        <!-- GLOW EFFECTS -->
        <div class="control-section">
            <div class="control-section-title">Glow Efekty (Neony)</div>

            <div class="control-group">
                <div class="toggle-group">
                    <label class="control-label">Aktivovat Glow</label>
                    <label class="toggle-switch">
                        <input type="checkbox" id="glow-enabled" onchange="updateStyle()">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <div class="control-group">
                <label class="control-label">Barva Glow</label>
                <div class="color-picker-group">
                    <div class="color-preview" id="glow-color-preview" onclick="document.getElementById('glow-color').click()"></div>
                    <input type="color" id="glow-color" class="color-input" value="#2D5016" onchange="updateStyle()">
                    <div class="color-value" id="glow-color-value">#2D5016</div>
                </div>
            </div>

            <div class="control-group">
                <label class="control-label">Intenzita Glow</label>
                <div class="slider-group">
                    <div class="slider-value-display">
                        <span>Intensity</span>
                        <span class="slider-value" id="glow-intensity-value">10px</span>
                    </div>
                    <input type="range" id="glow-intensity" min="0" max="50" value="10" oninput="updateStyle()">
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn-save" onclick="saveSettings()">Uložit natrvalo</button>
            <button class="btn-reset" onclick="resetToDefaults()">Reset</button>
        </div>
    </div>

    <!-- Preview Panel -->
    <div class="preview-panel">
        <div class="preview-label">Live Preview</div>
        <div id="livePreview">
            <div class="preview-overlay" id="previewOverlay">
                <div class="preview-modal" id="previewModal">
                    <div class="preview-content">
                        <h2>Control Center</h2>
                        <p>Toto je živý náhled vašeho designu. Změny se zobrazují okamžitě při upravování nastavení.</p>
                        <p>Můžete upravit barvy, velikosti textu, zarovnání, glow efekty a mnoho dalšího.</p>
                        <button class="preview-button" id="previewButton">Testovací tlačítko</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Debug mode - set to false in production
const DEBUG_MODE = false;

// Initialize všech controlů
document.addEventListener('DOMContentLoaded', () => {
    initializeControls();
    updateStyle();
});

/**
 * InitializeControls
 */
function initializeControls() {
    // Set initial values from PHP
    const defaults = <?= json_encode($defaults) ?>;

    // Inicializace color pickerů
    document.getElementById('overlay-bg').value = '#000000';
    document.getElementById('modal-bg').value = '#ffffff';
    document.getElementById('text-color').value = '#000000';
    document.getElementById('heading-color').value = '#000000';
    document.getElementById('button-bg').value = '#2D5016';
    document.getElementById('button-text').value = '#ffffff';
    document.getElementById('glow-color').value = '#2D5016';
}

/**
 * UpdateStyle
 */
function updateStyle() {
    // Get všechny hodnoty
    const overlayBg = document.getElementById('overlay-bg').value;
    const modalBg = document.getElementById('modal-bg').value;
    const textColor = document.getElementById('text-color').value;
    const headingColor = document.getElementById('heading-color').value;
    const buttonBg = document.getElementById('button-bg').value;
    const buttonText = document.getElementById('button-text').value;
    const borderRadius = document.getElementById('border-radius').value;
    const padding = document.getElementById('padding').value;
    const fontSizeBody = document.getElementById('font-size-body').value;
    const fontSizeHeading = document.getElementById('font-size-heading').value;
    const lineHeight = document.getElementById('line-height').value;
    const glowEnabled = document.getElementById('glow-enabled').checked;
    const glowColor = document.getElementById('glow-color').value;
    const glowIntensity = document.getElementById('glow-intensity').value;

    // Update value displays
    document.getElementById('overlay-bg-value').textContent = overlayBg;
    document.getElementById('modal-bg-value').textContent = modalBg;
    document.getElementById('text-color-value').textContent = textColor;
    document.getElementById('heading-color-value').textContent = headingColor;
    document.getElementById('button-bg-value').textContent = buttonBg;
    document.getElementById('button-text-value').textContent = buttonText;
    document.getElementById('radius-value').textContent = borderRadius + 'px';
    document.getElementById('padding-value').textContent = padding + 'px';
    document.getElementById('font-size-body-value').textContent = fontSizeBody + 'px';
    document.getElementById('font-size-heading-value').textContent = fontSizeHeading + 'px';
    document.getElementById('line-height-value').textContent = lineHeight;
    document.getElementById('glow-color-value').textContent = glowColor;
    document.getElementById('glow-intensity-value').textContent = glowIntensity + 'px';

    // Update color previews
    document.getElementById('overlay-bg-preview').style.background = overlayBg;
    document.getElementById('modal-bg-preview').style.background = modalBg;
    document.getElementById('text-color-preview').style.background = textColor;
    document.getElementById('heading-color-preview').style.background = headingColor;
    document.getElementById('button-bg-preview').style.background = buttonBg;
    document.getElementById('button-text-preview').style.background = buttonText;
    document.getElementById('glow-color-preview').style.background = glowColor;

    // Apply styles to preview
    const previewOverlay = document.getElementById('previewOverlay');
    const previewModal = document.getElementById('previewModal');
    const previewButton = document.getElementById('previewButton');

    previewOverlay.style.background = hexToRgba(overlayBg, 0.7);
    previewModal.style.background = modalBg;
    previewModal.style.borderRadius = borderRadius + 'px';
    previewModal.style.padding = padding + 'px';

    // Set CSS variables for preview
    previewModal.style.setProperty('--preview-radius', borderRadius + 'px');
    previewModal.style.setProperty('--preview-padding', padding + 'px');
    previewModal.style.setProperty('--preview-text-color', textColor);
    previewModal.style.setProperty('--preview-heading-color', headingColor);
    previewModal.style.setProperty('--preview-body-size', fontSizeBody + 'px');
    previewModal.style.setProperty('--preview-heading-size', fontSizeHeading + 'px');
    previewModal.style.setProperty('--preview-line-height', lineHeight);
    previewModal.style.setProperty('--preview-text-align', document.querySelector('.btn-option.active[data-align]')?.dataset.align || 'left');
    previewModal.style.setProperty('--preview-font-weight', document.querySelector('.btn-option.active[data-weight]')?.dataset.weight || '400');

    // Button styles
    previewButton.style.background = buttonBg;
    previewButton.style.color = buttonText;

    // Glow effects
    if (glowEnabled) {
        previewModal.style.boxShadow = `0 0 ${glowIntensity}px ${glowColor}, 0 20px 60px rgba(0,0,0,0.3)`;
        previewButton.style.boxShadow = `0 0 ${glowIntensity * 0.5}px ${glowColor}`;
    } else {
        previewModal.style.boxShadow = '0 20px 60px rgba(0,0,0,0.3)';
        previewButton.style.boxShadow = 'none';
    }
}

/**
 * SetTextAlign
 */
function setTextAlign(align) {
    document.querySelectorAll('.btn-option[data-align]').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`.btn-option[data-align="${align}"]`).classList.add('active');
    updateStyle();
}

/**
 * SetFontWeight
 */
function setFontWeight(weight) {
    document.querySelectorAll('.btn-option[data-weight]').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`.btn-option[data-weight="${weight}"]`).classList.add('active');
    updateStyle();
}

/**
 * HexToRgba
 */
function hexToRgba(hex, alpha) {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/**
 * SaveSettings
 */
async function saveSettings() {
    const settings = {
        overlay_bg: document.getElementById('overlay-bg').value,
        modal_bg: document.getElementById('modal-bg').value,
        text_color: document.getElementById('text-color').value,
        heading_color: document.getElementById('heading-color').value,
        button_bg: document.getElementById('button-bg').value,
        button_text: document.getElementById('button-text').value,
        border_radius: document.getElementById('border-radius').value,
        padding: document.getElementById('padding').value,
        font_size_body: document.getElementById('font-size-body').value,
        font_size_heading: document.getElementById('font-size-heading').value,
        text_align: document.querySelector('.btn-option.active[data-align]')?.dataset.align || 'left',
        font_weight: document.querySelector('.btn-option.active[data-weight]')?.dataset.weight || '400',
        line_height: document.getElementById('line-height').value,
        glow_enabled: document.getElementById('glow-enabled').checked ? 'true' : 'false',
        glow_color: document.getElementById('glow-color').value,
        glow_intensity: document.getElementById('glow-intensity').value,
    };

    // Uložení přes API
    fetch('/api/control_center_api.php?action=save_theme', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(settings)
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success' || data.success) {
            alert('✓ Nastavení vzhledu uloženo!');
            if (DEBUG_MODE) console.log('Settings saved:', settings);
        } else {
            throw new Error(data.message || 'Chyba při ukládání');
        }
    })
    .catch(err => {
        console.error('Save error:', err);
        alert('❌ Chyba při ukládání nastavení: ' + err.message);
    });
}

/**
 * ResetToDefaults
 */
function resetToDefaults() {
    if (!confirm('Opravdu chcete obnovit výchozí nastavení?')) return;

    document.getElementById('overlay-bg').value = '#000000';
    document.getElementById('modal-bg').value = '#ffffff';
    document.getElementById('text-color').value = '#000000';
    document.getElementById('heading-color').value = '#000000';
    document.getElementById('button-bg').value = '#2D5016';
    document.getElementById('button-text').value = '#ffffff';
    document.getElementById('border-radius').value = '8';
    document.getElementById('padding').value = '16';
    document.getElementById('font-size-body').value = '16';
    document.getElementById('font-size-heading').value = '24';
    document.getElementById('line-height').value = '1.5';
    document.getElementById('glow-enabled').checked = false;
    document.getElementById('glow-color').value = '#2D5016';
    document.getElementById('glow-intensity').value = '10';

    setTextAlign('left');
    setFontWeight('400');

    updateStyle();
}

if (DEBUG_MODE) console.log('[OK] iPhone-style editor loaded');
</script>
