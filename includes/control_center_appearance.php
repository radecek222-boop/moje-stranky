<?php
/**
 * Control Center - Vzhled & Design
 * Správa barev, fontů, loga
 */

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized');
}

$pdo = getDbConnection();

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
    $themeSettings = []; // Tabulka neexistuje
}

// Default hodnoty pokud tabulka neexistuje
$defaults = [
    'primary_color' => '#000000',
    'secondary_color' => '#FFFFFF',
    'success_color' => '#28A745',
    'warning_color' => '#FFC107',
    'danger_color' => '#DC3545',
    'grey_color' => '#555555',
    'light_grey_color' => '#999999',
    'border_color' => '#E0E0E0',
    'font_family' => 'Poppins',
    'font_size_base' => '16px',
    'border_radius' => '8px',
];

foreach ($defaults as $key => $value) {
    if (!isset($themeSettings[$key])) {
        $themeSettings[$key] = ['value' => $value, 'type' => 'color'];
    }
}
?>

<link rel="stylesheet" href="/assets/css/control-center.css">

<div class="control-detail active">
    <!-- Header -->
    <div class="control-detail-header">
        <button class="control-detail-back" onclick="window.location.href='admin.php?tab=control_center'">
            <span>‹</span>
            <span>Zpět</span>
        </button>
        <h2 class="control-detail-title">🎨 Vzhled & Design</h2>
    </div>

    <div class="control-detail-content">

        <!-- Alert -->
        <div class="cc-alert info">
            <div class="cc-alert-icon">💡</div>
            <div class="cc-alert-content">
                <div class="cc-alert-title">Barevná paleta</div>
                <div class="cc-alert-message">
                    Změny barev se projeví okamžitě v celé aplikaci. Používají se CSS proměnné.
                </div>
            </div>
        </div>

        <!-- BARVY -->
        <div class="setting-group">
            <h3 class="setting-group-title">Barevná paleta</h3>

            <!-- Primární barva -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">Primární barva</div>
                    <div class="setting-item-description">Hlavní barva pro tlačítka a akční prvky</div>
                </div>
                <div class="setting-item-right">
                    <div class="cc-color-picker">
                        <div class="cc-color-preview"
                             style="background: <?= htmlspecialchars($themeSettings['primary_color']['value']) ?>"
                             onclick="document.getElementById('color-primary').click()"></div>
                        <input type="color"
                               id="color-primary"
                               value="<?= htmlspecialchars($themeSettings['primary_color']['value']) ?>"
                               class="cc-input cc-color-input"
                               onchange="updateColor('primary_color', this.value)">
                    </div>
                </div>
            </div>

            <!-- Sekundární barva -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">Sekundární barva</div>
                    <div class="setting-item-description">Barva pozadí a textů</div>
                </div>
                <div class="setting-item-right">
                    <div class="cc-color-picker">
                        <div class="cc-color-preview"
                             style="background: <?= htmlspecialchars($themeSettings['secondary_color']['value']) ?>"
                             onclick="document.getElementById('color-secondary').click()"></div>
                        <input type="color"
                               id="color-secondary"
                               value="<?= htmlspecialchars($themeSettings['secondary_color']['value']) ?>"
                               class="cc-input cc-color-input"
                               onchange="updateColor('secondary_color', this.value)">
                    </div>
                </div>
            </div>

            <!-- Barva úspěchu -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">Barva úspěchu</div>
                    <div class="setting-item-description">Zelená pro úspěšné akce</div>
                </div>
                <div class="setting-item-right">
                    <div class="cc-color-picker">
                        <div class="cc-color-preview"
                             style="background: <?= htmlspecialchars($themeSettings['success_color']['value']) ?>"
                             onclick="document.getElementById('color-success').click()"></div>
                        <input type="color"
                               id="color-success"
                               value="<?= htmlspecialchars($themeSettings['success_color']['value']) ?>"
                               class="cc-input cc-color-input"
                               onchange="updateColor('success_color', this.value)">
                    </div>
                </div>
            </div>

            <!-- Barva varování -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">Barva varování</div>
                    <div class="setting-item-description">Žlutá/oranžová pro upozornění</div>
                </div>
                <div class="setting-item-right">
                    <div class="cc-color-picker">
                        <div class="cc-color-preview"
                             style="background: <?= htmlspecialchars($themeSettings['warning_color']['value']) ?>"
                             onclick="document.getElementById('color-warning').click()"></div>
                        <input type="color"
                               id="color-warning"
                               value="<?= htmlspecialchars($themeSettings['warning_color']['value']) ?>"
                               class="cc-input cc-color-input"
                               onchange="updateColor('warning_color', this.value)">
                    </div>
                </div>
            </div>

            <!-- Barva chyby -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">Barva chyby</div>
                    <div class="setting-item-description">Červená pro chybové stavy</div>
                </div>
                <div class="setting-item-right">
                    <div class="cc-color-picker">
                        <div class="cc-color-preview"
                             style="background: <?= htmlspecialchars($themeSettings['danger_color']['value']) ?>"
                             onclick="document.getElementById('color-danger').click()"></div>
                        <input type="color"
                               id="color-danger"
                               value="<?= htmlspecialchars($themeSettings['danger_color']['value']) ?>"
                               class="cc-input cc-color-input"
                               onchange="updateColor('danger_color', this.value)">
                    </div>
                </div>
            </div>
        </div>

        <!-- TYPOGRAFIE -->
        <div class="setting-group">
            <h3 class="setting-group-title">Typografie</h3>

            <!-- Font rodina -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">Font rodina</div>
                    <div class="setting-item-description">Hlavní font používaný v aplikaci</div>
                </div>
                <div class="setting-item-right">
                    <select class="cc-input cc-select"
                            id="font-family"
                            onchange="updateTheme('font_family', this.value)">
                        <option value="Poppins" <?= $themeSettings['font_family']['value'] === 'Poppins' ? 'selected' : '' ?>>Poppins (výchozí)</option>
                        <option value="Inter" <?= $themeSettings['font_family']['value'] === 'Inter' ? 'selected' : '' ?>>Inter</option>
                        <option value="Roboto" <?= $themeSettings['font_family']['value'] === 'Roboto' ? 'selected' : '' ?>>Roboto</option>
                        <option value="Open Sans" <?= $themeSettings['font_family']['value'] === 'Open Sans' ? 'selected' : '' ?>>Open Sans</option>
                        <option value="Arial" <?= $themeSettings['font_family']['value'] === 'Arial' ? 'selected' : '' ?>>Arial</option>
                    </select>
                </div>
            </div>

            <!-- Základní velikost fontu -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">Základní velikost fontu</div>
                    <div class="setting-item-description">Body text (16px doporučeno)</div>
                </div>
                <div class="setting-item-right">
                    <input type="number"
                           class="cc-input"
                           id="font-size"
                           value="<?= intval($themeSettings['font_size_base']['value']) ?>"
                           min="12"
                           max="24"
                           onchange="updateTheme('font_size_base', this.value + 'px')"
                           style="width: 100px">
                </div>
            </div>
        </div>

        <!-- LAYOUT -->
        <div class="setting-group">
            <h3 class="setting-group-title">Layout & Komponenty</h3>

            <!-- Border radius -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">Zaoblení rohů</div>
                    <div class="setting-item-description">Border radius pro tlačítka a karty</div>
                </div>
                <div class="setting-item-right">
                    <select class="cc-input cc-select"
                            id="border-radius"
                            onchange="updateTheme('border_radius', this.value)">
                        <option value="0px" <?= $themeSettings['border_radius']['value'] === '0px' ? 'selected' : '' ?>>Ostré (0px)</option>
                        <option value="4px" <?= $themeSettings['border_radius']['value'] === '4px' ? 'selected' : '' ?>>Mírné (4px)</option>
                        <option value="8px" <?= $themeSettings['border_radius']['value'] === '8px' ? 'selected' : '' ?>>Střední (8px)</option>
                        <option value="12px" <?= $themeSettings['border_radius']['value'] === '12px' ? 'selected' : '' ?>>Velké (12px)</option>
                        <option value="16px" <?= $themeSettings['border_radius']['value'] === '16px' ? 'selected' : '' ?>>Extra (16px)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Akce -->
        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
            <button class="cc-btn cc-btn-primary" onclick="saveAllChanges()">
                <span>💾</span>
                <span>Uložit změny</span>
            </button>
            <button class="cc-btn cc-btn-secondary" onclick="resetToDefaults()">
                <span>🔄</span>
                <span>Výchozí nastavení</span>
            </button>
            <button class="cc-btn cc-btn-secondary" onclick="previewChanges()">
                <span>👁️</span>
                <span>Náhled</span>
            </button>
        </div>

        <!-- Success message -->
        <div id="save-success" class="cc-alert success cc-hidden" style="margin-top: 1rem;">
            <div class="cc-alert-icon">✅</div>
            <div class="cc-alert-content">
                <div class="cc-alert-title">Uloženo!</div>
                <div class="cc-alert-message">Změny byly úspěšně uloženy a aplikovány.</div>
            </div>
        </div>

    </div>
</div>

<script src="/assets/js/csrf-auto-inject.js"></script>
<script>
// Color update function
async function updateColor(key, value) {
    // Update preview
    const preview = document.querySelector(`#color-${key.replace('_color', '')}`).previousElementSibling;
    if (preview) {
        preview.style.background = value;
    }

    // Apply immediately to CSS variables
    document.documentElement.style.setProperty(`--wgs-${key.replace('_color', '').replace('_', '-')}`, value);
}

// Theme update function
async function updateTheme(key, value) {
    console.log('Updating theme:', key, value);
}

// Save all changes
async function saveAllChanges() {
    const saveBtn = event.target;
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<span class="cc-loading"></span> Ukládám...';
    saveBtn.disabled = true;

    try {
        // Get CSRF token
        const csrfToken = typeof getCSRFToken === 'function' ? await getCSRFToken() : null;

        // Collect all theme settings
        const settings = {
            primary_color: document.getElementById('color-primary').value,
            secondary_color: document.getElementById('color-secondary').value,
            success_color: document.getElementById('color-success').value,
            warning_color: document.getElementById('color-warning').value,
            danger_color: document.getElementById('color-danger').value,
            font_family: document.getElementById('font-family').value,
            font_size_base: document.getElementById('font-size').value + 'px',
            border_radius: document.getElementById('border-radius').value
        };

        // Save to API
        const response = await fetch('/api/control_center_api.php?action=save_theme', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                settings: settings,
                csrf_token: csrfToken
            })
        });

        if (!response.ok) {
            throw new Error('Nepodařilo se uložit změny');
        }

        const result = await response.json();

        if (result.status === 'success') {
            // Show success message
            document.getElementById('save-success').classList.remove('cc-hidden');
            setTimeout(() => {
                document.getElementById('save-success').classList.add('cc-hidden');
            }, 3000);
        } else {
            throw new Error(result.message || 'Chyba při ukládání');
        }

    } catch (error) {
        console.error('Save error:', error);
        alert('Chyba při ukládání: ' + error.message);
    } finally {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    }
}

// Reset to defaults
async function resetToDefaults() {
    if (!confirm('Opravdu chcete obnovit výchozí nastavení? Tato akce je nevratná.')) {
        return;
    }

    const defaults = {
        primary_color: '#000000',
        secondary_color: '#FFFFFF',
        success_color: '#28A745',
        warning_color: '#FFC107',
        danger_color: '#DC3545',
        font_family: 'Poppins',
        font_size_base: '16',
        border_radius: '8px'
    };

    // Update UI
    document.getElementById('color-primary').value = defaults.primary_color;
    document.getElementById('color-secondary').value = defaults.secondary_color;
    document.getElementById('color-success').value = defaults.success_color;
    document.getElementById('color-warning').value = defaults.warning_color;
    document.getElementById('color-danger').value = defaults.danger_color;
    document.getElementById('font-family').value = defaults.font_family;
    document.getElementById('font-size').value = defaults.font_size_base;
    document.getElementById('border-radius').value = defaults.border_radius;

    // Update previews
    document.querySelectorAll('.cc-color-preview').forEach((preview, i) => {
        const keys = ['primary_color', 'secondary_color', 'success_color', 'warning_color', 'danger_color'];
        preview.style.background = defaults[keys[i]];
    });

    // Save
    await saveAllChanges();
}

// Preview changes
function previewChanges() {
    alert('Náhled změn bude implementován v budoucí verzi.\n\nV současnosti se změny aplikují okamžitě po uložení.');
}

console.log('✅ Appearance section loaded');
</script>
