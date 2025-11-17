<?php
/**
 * Control Center - SECURITY
 * Centralizované bezpečnostní centrum - registrační klíče, API klíče, audit log
 */

// Bezpečnostní kontrola - POUZE ADMIN
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('Unauthorized');
}

$pdo = getDbConnection();
$currentSection = $_GET['section'] ?? 'prehled';

// Načtení statistik pro přehled
$stats = [
    'registracni_klice_celkem' => 0,
    'registracni_klice_aktivni' => 0,
    'uzivatele_celkem' => 0,
    'uzivatele_aktivni' => 0,
    'api_klice_celkem' => 0,
    'posledni_prihlaseni' => null
];

try {
    // Registrační klíče statistiky
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active FROM wgs_registration_keys");
    $keyStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['registracni_klice_celkem'] = $keyStats['total'] ?? 0;
    $stats['registracni_klice_aktivni'] = $keyStats['active'] ?? 0;

    // Uživatelé statistiky
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active FROM wgs_users");
    $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['uzivatele_celkem'] = $userStats['total'] ?? 0;
    $stats['uzivatele_aktivni'] = $userStats['active'] ?? 0;

    // API klíče (z wgs_system_config)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM wgs_system_config WHERE config_group = 'api_keys'");
    $apiStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['api_klice_celkem'] = $apiStats['total'] ?? 0;

    // Poslední přihlášení
    $stmt = $pdo->query("SELECT email, last_login FROM wgs_users WHERE last_login IS NOT NULL ORDER BY last_login DESC LIMIT 1");
    $lastLogin = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['posledni_prihlaseni'] = $lastLogin;

} catch (PDOException $e) {
    error_log("Security stats error: " . $e->getMessage());
}

// Načtení konfigurace pro API klíče a Security nastavení
$configs = [];
try {
    $stmt = $pdo->query("SELECT * FROM wgs_system_config WHERE config_group IN ('api_keys', 'security') ORDER BY config_group, config_key");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $group = $row['config_group'];
        if (!isset($configs[$group])) {
            $configs[$group] = [];
        }

        // Maskování citlivých hodnot
        if ($row['is_sensitive']) {
            $value = $row['config_value'];
            if (strlen($value) > 8) {
                $row['config_value_display'] = substr($value, 0, 4) . '••••••••' . substr($value, -4);
            } else {
                $row['config_value_display'] = '••••••••';
            }
        } else {
            $row['config_value_display'] = $row['config_value'];
        }

        $configs[$group][] = $row;
    }
} catch (PDOException $e) {
    $configs = [];
}
?>

<link rel="stylesheet" href="/assets/css/control-center.css">

<style>
/* Security-specific styles */
.security-container {
    padding: 2rem;
    background: #fff;
    min-height: calc(100vh - 200px);
}

.cc-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid #e0e0e0;
    flex-wrap: wrap;
}

.cc-tab {
    padding: 0.75rem 1.5rem;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 0.95rem;
    font-weight: 500;
    color: #666;
    transition: all 0.2s;
}

.cc-tab:hover {
    color: #2D5016;
    background: #f8f9fa;
}

.cc-tab.active {
    color: #2D5016;
    border-bottom-color: #2D5016;
    background: #f8f9fa;
}

.cc-section {
    display: none;
    animation: fadeIn 0.3s;
}

.cc-section.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.security-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 1.5rem;
    border-radius: 12px;
    color: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.stat-card.success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.stat-card.warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-card.info {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-card-value {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.stat-card-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

.security-alert {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.security-alert.danger {
    background: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.security-alert-icon {
    font-size: 1.5rem;
}

/* Keys table styling */
#keys-container table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

#keys-container th,
#keys-container td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

#keys-container th {
    background: #f8f9fa;
    font-weight: 600;
    color: #333;
}

#keys-container tr:hover {
    background: #f8f9fa;
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-success {
    background: #d4edda;
    color: #155724;
}

.badge-danger {
    background: #f8d7da;
    color: #721c24;
}

.badge-warning {
    background: #fff3cd;
    color: #856404;
}

/* User access table */
.user-access-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.user-access-table th,
.user-access-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

.user-access-table th {
    background: #2D5016;
    color: white;
    font-weight: 600;
}

.user-access-table tr:hover {
    background: #f8f9fa;
}
</style>

<div class="security-container">

    <!-- Tab Navigation -->
    <div class="cc-tabs">
        <button class="cc-tab <?= $currentSection === 'prehled' ? 'active' : '' ?>" onclick="switchSection('prehled')">
            📊 Přehled
        </button>
        <button class="cc-tab <?= $currentSection === 'registracni_klice' ? 'active' : '' ?>" onclick="switchSection('registracni_klice')">
            🔑 Registrační klíče
        </button>
        <button class="cc-tab <?= $currentSection === 'api_klice' ? 'active' : '' ?>" onclick="switchSection('api_klice')">
            🔐 API Klíče
        </button>
        <button class="cc-tab <?= $currentSection === 'bezpecnost' ? 'active' : '' ?>" onclick="switchSection('bezpecnost')">
            🛡️ Bezpečnostní nastavení
        </button>
        <button class="cc-tab <?= $currentSection === 'uzivatele' ? 'active' : '' ?>" onclick="switchSection('uzivatele')">
            👥 Uživatelé & Přístupy
        </button>
        <button class="cc-tab <?= $currentSection === 'audit' ? 'active' : '' ?>" onclick="switchSection('audit')">
            📝 Audit Log
        </button>
    </div>

    <!-- SEKCE: PŘEHLED -->
    <div id="section-prehled" class="cc-section <?= $currentSection === 'prehled' ? 'active' : '' ?>">
        <h2 style="margin-bottom: 1.5rem; color: #2D5016;">🔒 Security Dashboard</h2>

        <!-- Security Alert -->
        <div class="security-alert">
            <div class="security-alert-icon">⚠️</div>
            <div>
                <strong>Vysoká úroveň zabezpečení aktivní</strong><br>
                Všechny citlivé údaje jsou maskovány. Pouze admin má přístup k této kartě.
            </div>
        </div>

        <!-- Statistiky -->
        <div class="security-stats">
            <div class="stat-card success">
                <div class="stat-card-value"><?= $stats['registracni_klice_aktivni'] ?> / <?= $stats['registracni_klice_celkem'] ?></div>
                <div class="stat-card-label">Aktivní registrační klíče</div>
            </div>
            <div class="stat-card info">
                <div class="stat-card-value"><?= $stats['uzivatele_aktivni'] ?> / <?= $stats['uzivatele_celkem'] ?></div>
                <div class="stat-card-label">Aktivní uživatelé</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-value"><?= $stats['api_klice_celkem'] ?></div>
                <div class="stat-card-label">Nakonfigurované API klíče</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-card-value">
                    <?php if ($stats['posledni_prihlaseni']): ?>
                        <?= date('H:i', strtotime($stats['posledni_prihlaseni']['last_login'])) ?>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </div>
                <div class="stat-card-label">
                    <?php if ($stats['posledni_prihlaseni']): ?>
                        Poslední přihlášení: <?= htmlspecialchars($stats['posledni_prihlaseni']['email']) ?>
                    <?php else: ?>
                        Žádná přihlášení
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="setting-group">
            <h3 class="setting-group-title">🚀 Rychlé akce</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <button class="cc-btn cc-btn-primary" onclick="switchSection('registracni_klice')">
                    🔑 Spravovat klíče
                </button>
                <button class="cc-btn cc-btn-secondary" onclick="switchSection('uzivatele')">
                    👥 Zobrazit uživatele
                </button>
                <button class="cc-btn cc-btn-secondary" onclick="switchSection('audit')">
                    📝 Audit Log
                </button>
                <button class="cc-btn cc-btn-secondary" onclick="switchSection('api_klice')">
                    🔐 API Klíče
                </button>
            </div>
        </div>
    </div>

    <!-- SEKCE: REGISTRAČNÍ KLÍČE -->
    <div id="section-registracni_klice" class="cc-section <?= $currentSection === 'registracni_klice' ? 'active' : '' ?>">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="margin: 0; color: #2D5016;">🔑 Registrační klíče</h2>
            <div style="display: flex; gap: 0.5rem;">
                <button class="cc-btn cc-btn-success" id="createKeyBtn">+ Nový klíč</button>
                <button class="cc-btn cc-btn-secondary" id="refreshKeysBtn">🔄 Obnovit</button>
            </div>
        </div>

        <div class="cc-alert info">
            <div class="cc-alert-icon">ℹ️</div>
            <div>
                <strong>Registrační klíče řídí přístup k registraci</strong><br>
                Pouze uživatelé s platným klíčem se mohou zaregistrovat. Sledujte využití a deaktivujte podezřelé klíče.
            </div>
        </div>

        <div id="keys-container">
            <div class="loading">Načítání klíčů...</div>
        </div>
    </div>

    <!-- SEKCE: API KLÍČE -->
    <div id="section-api_klice" class="cc-section <?= $currentSection === 'api_klice' ? 'active' : '' ?>">
        <h2 style="margin-bottom: 1.5rem; color: #2D5016;">🔐 API Klíče</h2>

        <div class="security-alert danger">
            <div class="security-alert-icon">🔴</div>
            <div>
                <strong>KRITICKÉ: Nikdy nesdílejte API klíče</strong><br>
                Tyto klíče poskytují přístup k externím službám. Změna klíče vyžaduje restart aplikace.
            </div>
        </div>

        <?php if (isset($configs['api_keys']) && !empty($configs['api_keys'])): ?>
            <div class="setting-group">
                <?php foreach ($configs['api_keys'] as $config): ?>
                    <div class="setting-item">
                        <div class="setting-item-left">
                            <div class="setting-item-label">
                                <?= htmlspecialchars($config['config_key']) ?>
                                <?php if ($config['requires_restart']): ?>
                                    <span class="badge badge-warning">Restart required</span>
                                <?php endif; ?>
                            </div>
                            <div class="setting-item-description">
                                <?= htmlspecialchars($config['description']) ?>
                            </div>
                        </div>
                        <div class="setting-item-right" style="min-width: 300px;">
                            <?php if ($config['is_editable']): ?>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="password"
                                           class="cc-input"
                                           id="config-<?= $config['id'] ?>"
                                           value="<?= htmlspecialchars($config['config_value']) ?>"
                                           placeholder="<?= $config['config_value_display'] ?>"
                                           style="flex: 1; font-family: monospace; font-size: 0.85rem;">
                                    <button class="cc-btn cc-btn-sm cc-btn-secondary"
                                            onclick="togglePasswordVisibility(<?= $config['id'] ?>)">
                                        👁️ Zobrazit
                                    </button>
                                    <button class="cc-btn cc-btn-sm cc-btn-primary"
                                            onclick="saveConfig(<?= $config['id'] ?>, '<?= htmlspecialchars($config['config_key']) ?>')">
                                        💾
                                    </button>
                                </div>
                                <div id="save-status-<?= $config['id'] ?>" style="margin-top: 0.5rem; display: none; font-size: 0.85rem;"></div>
                            <?php else: ?>
                                <span style="color: #999; font-family: monospace; font-size: 0.85rem;">
                                    <?= $config['config_value_display'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="cc-alert info">
                <div class="cc-alert-icon">ℹ️</div>
                <div>Žádné API klíče nejsou nakonfigurovány.</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- SEKCE: BEZPEČNOSTNÍ NASTAVENÍ -->
    <div id="section-bezpecnost" class="cc-section <?= $currentSection === 'bezpecnost' ? 'active' : '' ?>">
        <h2 style="margin-bottom: 1.5rem; color: #2D5016;">🛡️ Bezpečnostní nastavení</h2>

        <?php if (isset($configs['security']) && !empty($configs['security'])): ?>
            <div class="setting-group">
                <?php foreach ($configs['security'] as $config): ?>
                    <div class="setting-item">
                        <div class="setting-item-left">
                            <div class="setting-item-label">
                                <?= htmlspecialchars($config['config_key']) ?>
                                <?php if ($config['requires_restart']): ?>
                                    <span class="badge badge-warning">Restart required</span>
                                <?php endif; ?>
                            </div>
                            <div class="setting-item-description">
                                <?= htmlspecialchars($config['description']) ?>
                            </div>
                        </div>
                        <div class="setting-item-right" style="min-width: 200px;">
                            <?php if ($config['is_editable']): ?>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="number"
                                           class="cc-input"
                                           id="config-<?= $config['id'] ?>"
                                           value="<?= htmlspecialchars($config['config_value']) ?>"
                                           style="width: 120px;">
                                    <button class="cc-btn cc-btn-sm cc-btn-primary"
                                            onclick="saveConfig(<?= $config['id'] ?>, '<?= htmlspecialchars($config['config_key']) ?>')">
                                        💾
                                    </button>
                                </div>
                                <div id="save-status-<?= $config['id'] ?>" style="margin-top: 0.5rem; display: none; font-size: 0.85rem;"></div>
                            <?php else: ?>
                                <span style="color: #999;">
                                    <?= $config['config_value_display'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="cc-alert info">
                <div class="cc-alert-icon">ℹ️</div>
                <div>Žádná bezpečnostní nastavení nenalezena.</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- SEKCE: UŽIVATELÉ & PŘÍSTUPY -->
    <div id="section-uzivatele" class="cc-section <?= $currentSection === 'uzivatele' ? 'active' : '' ?>">
        <h2 style="margin-bottom: 1.5rem; color: #2D5016;">👥 Uživatelé & Přístupy</h2>

        <div class="cc-alert info">
            <div class="cc-alert-icon">ℹ️</div>
            <div>
                <strong>Správa uživatelských účtů</strong><br>
                Přehled všech registrovaných uživatelů, jejich rolí a poslední aktivity.
            </div>
        </div>

        <table class="user-access-table" id="security-users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Jméno</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Registrace</th>
                    <th>Poslední přihlášení</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2rem;">
                        <div class="loading">Načítání uživatelů...</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- SEKCE: AUDIT LOG -->
    <div id="section-audit" class="cc-section <?= $currentSection === 'audit' ? 'active' : '' ?>">
        <h2 style="margin-bottom: 1.5rem; color: #2D5016;">📝 Audit Log</h2>

        <div class="cc-alert warning">
            <div class="security-alert-icon">⚠️</div>
            <div>
                <strong>Bezpečnostní audit trail</strong><br>
                Všechny bezpečnostně relevantní události jsou zaznamenány. Pravidelně kontrolujte podezřelou aktivitu.
            </div>
        </div>

        <div style="margin-top: 1.5rem; padding: 2rem; background: #f8f9fa; border-radius: 8px; text-align: center;">
            <h3 style="color: #666; margin-bottom: 1rem;">🚧 Audit Log - V přípravě</h3>
            <p style="color: #999;">
                Audit logging bude zaznamenávat:<br>
                • Přihlášení a odhlášení uživatelů<br>
                • Změny v registračních klíčích<br>
                • Modifikace API klíčů<br>
                • Změny bezpečnostních nastavení<br>
                • Failed login attempts<br>
                • Podezřelé aktivity
            </p>
        </div>
    </div>
</div>

<script src="/assets/js/csrf-auto-inject.js"></script>
<script>
// Section switching
/**
 * SwitchSection - Přepínání mezi sekcemi
 */
function switchSection(section) {
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('section', section);
    window.history.pushState({}, '', url);

    // Update tabs
    document.querySelectorAll('.cc-tab').forEach(tab => tab.classList.remove('active'));
    document.querySelector(`.cc-tab[onclick*="${section}"]`)?.classList.add('active');

    // Update sections
    document.querySelectorAll('.cc-section').forEach(sec => sec.classList.remove('active'));
    document.getElementById(`section-${section}`)?.classList.add('active');

    // Load data for specific sections
    if (section === 'registracni_klice') {
        loadRegistracniKlice();
    } else if (section === 'uzivatele') {
        loadUzivateleProSecurity();
    }
}

// Toggle password visibility
/**
 * TogglePasswordVisibility
 */
function togglePasswordVisibility(configId) {
    const input = document.getElementById(`config-${configId}`);
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}

// Save config (API keys, security settings)
/**
 * SaveConfig
 */
async function saveConfig(configId, configKey) {
    const input = document.getElementById(`config-${configId}`);
    const statusEl = document.getElementById(`save-status-${configId}`);
    const value = input.value;

    statusEl.style.display = 'block';
    statusEl.innerHTML = '<span class="cc-loading"></span> Ukládám...';

    try {
        const csrfToken = typeof getCSRFToken === 'function' ? await getCSRFToken() : null;

        const response = await fetch('/api/control_center_api.php?action=save_system_config', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                key: configKey,
                value: value,
                csrf_token: csrfToken
            })
        });

        const result = await response.json();

        if (result.status === 'success') {
            statusEl.innerHTML = '<span style="color: #28A745;">✅ Uloženo!</span>';
            setTimeout(() => {
                statusEl.style.display = 'none';
            }, 2000);
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        statusEl.innerHTML = '<span style="color: #DC3545;">❌ Chyba: ' + error.message + '</span>';
    }
}

// Load registrační klíče (from admin.js)
/**
 * LoadRegistracniKlice
 */
function loadRegistracniKlice() {
    const container = document.getElementById('keys-container');
    if (!container) return;

    // Volá existující funkci z admin.js pokud existuje
    if (typeof loadKeys === 'function') {
        loadKeys();
    } else {
        container.innerHTML = '<div class="cc-alert warning"><div class="cc-alert-icon">⚠️</div><div>Načítání klíčů vyžaduje reload stránky nebo admin.js</div></div>';
    }
}

// Load uživatelé pro security tab
/**
 * LoadUzivateleProSecurity
 */
async function loadUzivateleProSecurity() {
    const tbody = document.querySelector('#security-users-table tbody');
    if (!tbody) return;

    try {
        const response = await fetch('/api/admin_api.php?action=get_users');
        const data = await response.json();

        if (data.status === 'success' && data.users) {
            let html = '';
            data.users.forEach(user => {
                const statusBadge = user.is_active ?
                    '<span class="badge badge-success">Aktivní</span>' :
                    '<span class="badge badge-danger">Neaktivní</span>';

                const lastLogin = user.last_login ?
                    new Date(user.last_login).toLocaleString('cs-CZ') :
                    '<span style="color: #999;">Nikdy</span>';

                html += `
                    <tr>
                        <td>${user.id}</td>
                        <td>${htmlEscape(user.name || 'N/A')}</td>
                        <td>${htmlEscape(user.email)}</td>
                        <td>${htmlEscape(user.role || 'N/A')}</td>
                        <td>${statusBadge}</td>
                        <td>${new Date(user.created_at).toLocaleDateString('cs-CZ')}</td>
                        <td>${lastLogin}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html || '<tr><td colspan="7" style="text-align: center; color: #999;">Žádní uživatelé</td></tr>';
        } else {
            throw new Error(data.message || 'Chyba při načítání');
        }
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: #dc3545;">Chyba: ${error.message}</td></tr>`;
    }
}

// HTML escape helper
/**
 * HtmlEscape
 */
function htmlEscape(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Load initial data on page load
document.addEventListener('DOMContentLoaded', function() {
    const currentSection = new URLSearchParams(window.location.search).get('section') || 'prehled';

    // Load data for initial section
    if (currentSection === 'registracni_klice') {
        loadRegistracniKlice();
    } else if (currentSection === 'uzivatele') {
        loadUzivateleProSecurity();
    }
});

console.log('🔒 Security Center loaded');
</script>
