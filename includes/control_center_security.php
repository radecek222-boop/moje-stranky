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
/* Security - Minimalistický černobílý design */
.security-container {
    padding: 1rem;
    background: #fff;
    min-height: calc(100vh - 200px);
    font-family: 'Poppins', sans-serif;
}

.cc-tabs {
    display: flex;
    gap: 0;
    margin-bottom: 1rem;
    border-bottom: 1px solid #000;
    flex-wrap: wrap;
}

.cc-tab {
    padding: 0.5rem 1rem;
    background: #fff;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 500;
    font-family: 'Poppins', sans-serif;
    color: #666;
    transition: all 0.2s;
}

.cc-tab:hover {
    color: #000;
    background: #f5f5f5;
}

.cc-tab.active {
    color: #000;
    border-bottom-color: #000;
    background: #fff;
}

.cc-section {
    display: none;
}

.cc-section.active {
    display: block;
}

/* Stats - Minimální černobílý grid */
.security-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.stat-card {
    background: #fff;
    border: 1px solid #000;
    padding: 1rem;
    text-align: center;
}

.stat-card-value {
    font-size: 1.5rem;
    font-weight: 600;
    font-family: 'Poppins', sans-serif;
    color: #000;
    margin-bottom: 0.25rem;
}

.stat-card-label {
    font-size: 0.75rem;
    font-family: 'Poppins', sans-serif;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Alert - Minimální design */
.security-alert {
    background: #f5f5f5;
    border: 1px solid #000;
    border-left: 3px solid #000;
    color: #000;
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    font-size: 0.85rem;
    font-family: 'Poppins', sans-serif;
}

.security-alert strong {
    font-weight: 600;
}

/* Tabulky */
#keys-container table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.5rem;
    font-size: 0.85rem;
    font-family: 'Poppins', sans-serif;
}

#keys-container th,
#keys-container td {
    padding: 0.5rem;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

#keys-container th {
    background: #000;
    color: #fff;
    font-weight: 500;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

#keys-container tr:hover {
    background: #f5f5f5;
}

/* Badges - Černobílé */
.badge {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    font-size: 0.7rem;
    font-weight: 500;
    font-family: 'Poppins', sans-serif;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.badge-success {
    background: #000;
    color: #fff;
}

.badge-danger {
    background: #fff;
    color: #000;
    border: 1px solid #000;
}

.badge-warning {
    background: #e0e0e0;
    color: #000;
}

/* User access table */
.user-access-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.5rem;
    font-size: 0.85rem;
    font-family: 'Poppins', sans-serif;
}

.user-access-table th,
.user-access-table td {
    padding: 0.5rem;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

.user-access-table th {
    background: #000;
    color: #fff;
    font-weight: 500;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.user-access-table tr:hover {
    background: #f5f5f5;
}

/* Setting group minimální */
.setting-group {
    margin-bottom: 1.5rem;
}

.setting-group-title {
    font-size: 0.9rem;
    font-weight: 600;
    font-family: 'Poppins', sans-serif;
    color: #000;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #000;
}

.setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e0e0e0;
    font-size: 0.85rem;
}

.setting-item-label {
    font-weight: 500;
    font-family: 'Poppins', sans-serif;
    color: #000;
}

.setting-item-description {
    font-size: 0.75rem;
    color: #666;
    margin-top: 0.25rem;
}
</style>

<div class="security-container">

    <!-- Tab Navigation -->
    <div class="cc-tabs">
        <button class="cc-tab <?= $currentSection === 'prehled' ? 'active' : '' ?>" onclick="switchSection('prehled')">
            Přehled
        </button>
        <button class="cc-tab <?= $currentSection === 'registracni_klice' ? 'active' : '' ?>" onclick="switchSection('registracni_klice')">
            Registrační klíče
        </button>
        <button class="cc-tab <?= $currentSection === 'api_klice' ? 'active' : '' ?>" onclick="switchSection('api_klice')">
            API Klíče
        </button>
        <button class="cc-tab <?= $currentSection === 'bezpecnost' ? 'active' : '' ?>" onclick="switchSection('bezpecnost')">
            Bezpečnost
        </button>
        <button class="cc-tab <?= $currentSection === 'uzivatele' ? 'active' : '' ?>" onclick="switchSection('uzivatele')">
            Uživatelé
        </button>
        <button class="cc-tab <?= $currentSection === 'audit' ? 'active' : '' ?>" onclick="switchSection('audit')">
            Audit Log
        </button>
    </div>

    <!-- SEKCE: PŘEHLED -->
    <div id="section-prehled" class="cc-section <?= $currentSection === 'prehled' ? 'active' : '' ?>">
        <h2 style="margin-bottom: 0.75rem; color: #000; font-size: 1rem; font-weight: 600; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.5px;">Security Dashboard</h2>

        <!-- Security Alert -->
        <div class="security-alert">
            <div>
                <strong>Vysoká úroveň zabezpečení</strong> - Všechny citlivé údaje jsou maskovány. Pouze admin má přístup.
            </div>
        </div>

        <!-- Statistiky -->
        <div class="security-stats">
            <div class="stat-card">
                <div class="stat-card-value"><?= $stats['registracni_klice_aktivni'] ?> / <?= $stats['registracni_klice_celkem'] ?></div>
                <div class="stat-card-label">Registrační klíče</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-value"><?= $stats['uzivatele_aktivni'] ?> / <?= $stats['uzivatele_celkem'] ?></div>
                <div class="stat-card-label">Aktivní uživatelé</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-value"><?= $stats['api_klice_celkem'] ?></div>
                <div class="stat-card-label">API klíče</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-value">
                    <?php if ($stats['posledni_prihlaseni']): ?>
                        <?= date('H:i', strtotime($stats['posledni_prihlaseni']['last_login'])) ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </div>
                <div class="stat-card-label">
                    <?php if ($stats['posledni_prihlaseni']): ?>
                        <?= htmlspecialchars($stats['posledni_prihlaseni']['email']) ?>
                    <?php else: ?>
                        Žádná přihlášení
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="setting-group">
            <h3 class="setting-group-title">Rychlé akce</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.5rem;">
                <button class="cc-btn cc-btn-primary" onclick="switchSection('registracni_klice')" style="font-size: 0.8rem; padding: 0.5rem;">
                    Spravovat klíče
                </button>
                <button class="cc-btn cc-btn-secondary" onclick="switchSection('uzivatele')" style="font-size: 0.8rem; padding: 0.5rem;">
                    Zobrazit uživatele
                </button>
                <button class="cc-btn cc-btn-secondary" onclick="switchSection('audit')" style="font-size: 0.8rem; padding: 0.5rem;">
                    Audit Log
                </button>
                <button class="cc-btn cc-btn-secondary" onclick="switchSection('api_klice')" style="font-size: 0.8rem; padding: 0.5rem;">
                    API Klíče
                </button>
            </div>
        </div>
    </div>

    <!-- SEKCE: REGISTRAČNÍ KLÍČE -->
    <div id="section-registracni_klice" class="cc-section <?= $currentSection === 'registracni_klice' ? 'active' : '' ?>">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <h2 style="margin: 0; color: #000; font-size: 1rem; font-weight: 600; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.5px;">Registrační klíče</h2>
            <div style="display: flex; gap: 0.5rem;">
                <button class="cc-btn cc-btn-success" id="createKeyBtn" style="font-size: 0.8rem; padding: 0.4rem 0.75rem;">+ Nový</button>
                <button class="cc-btn cc-btn-secondary" id="refreshKeysBtn" style="font-size: 0.8rem; padding: 0.4rem 0.75rem;">Obnovit</button>
            </div>
        </div>

        <div class="security-alert">
            <div>
                <strong>Registrační klíče řídí přístup</strong> - Pouze uživatelé s platným klíčem se mohou zaregistrovat.
            </div>
        </div>

        <div id="keys-container">
            <div class="loading">Načítání klíčů...</div>
        </div>
    </div>

    <!-- SEKCE: API KLÍČE -->
    <div id="section-api_klice" class="cc-section <?= $currentSection === 'api_klice' ? 'active' : '' ?>">
        <h2 style="margin-bottom: 0.75rem; color: #000; font-size: 1rem; font-weight: 600; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.5px;">API Klíče</h2>

        <div class="security-alert">
            <div>
                <strong>KRITICKÉ</strong> - Nikdy nesdílejte API klíče. Tyto klíče poskytují přístup k externím službám.
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
                                            onclick="togglePasswordVisibility(<?= $config['id'] ?>)"
                                            style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">
                                        Zobrazit
                                    </button>
                                    <button class="cc-btn cc-btn-sm cc-btn-primary"
                                            onclick="saveConfig(<?= $config['id'] ?>, '<?= htmlspecialchars($config['config_key']) ?>')"
                                            style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">
                                        Uložit
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
            <div class="security-alert">
                <div>Žádné API klíče nejsou nakonfigurovány.</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- SEKCE: BEZPEČNOSTNÍ NASTAVENÍ -->
    <div id="section-bezpecnost" class="cc-section <?= $currentSection === 'bezpecnost' ? 'active' : '' ?>">
        <h2 style="margin-bottom: 0.75rem; color: #000; font-size: 1rem; font-weight: 600; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.5px;">Bezpečnostní nastavení</h2>

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
                                            onclick="saveConfig(<?= $config['id'] ?>, '<?= htmlspecialchars($config['config_key']) ?>')"
                                            style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">
                                        Uložit
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
            <div class="security-alert">
                <div>Žádná bezpečnostní nastavení nenalezena.</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- SEKCE: UŽIVATELÉ & PŘÍSTUPY -->
    <div id="section-uzivatele" class="cc-section <?= $currentSection === 'uzivatele' ? 'active' : '' ?>">
        <h2 style="margin-bottom: 0.75rem; color: #000; font-size: 1rem; font-weight: 600; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.5px;">Uživatelé & Přístupy</h2>

        <div class="security-alert">
            <div>
                <strong>Správa účtů</strong> - Přehled všech registrovaných uživatelů, jejich rolí a poslední aktivity.
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
        <h2 style="margin-bottom: 0.75rem; color: #000; font-size: 1rem; font-weight: 600; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.5px;">Audit Log</h2>

        <div class="security-alert">
            <div>
                <strong>Bezpečnostní audit</strong> - Všechny relevantní události jsou zaznamenány. Pravidelně kontrolujte podezřelou aktivitu.
            </div>
        </div>

        <div style="margin-top: 1rem; padding: 1.5rem; background: #f5f5f5; border: 1px solid #000; text-align: center; font-family: 'Poppins', sans-serif;">
            <h3 style="color: #000; margin-bottom: 0.75rem; font-size: 0.9rem; font-weight: 600;">V PŘÍPRAVĚ</h3>
            <p style="color: #666; font-size: 0.8rem; line-height: 1.6;">
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
            statusEl.innerHTML = '<span style="color: #000;">Uloženo</span>';
            setTimeout(() => {
                statusEl.style.display = 'none';
            }, 2000);
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        statusEl.innerHTML = '<span style="color: #000;">Chyba: ' + error.message + '</span>';
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
        container.innerHTML = '<div class="security-alert"><div>Načítání klíčů vyžaduje reload stránky</div></div>';
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
