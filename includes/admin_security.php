<?php
/**
 * Control Center - SECURITY
 * Centralizované bezpečnostní centrum - registrační klíče, API klíče, audit log
 */

require_once __DIR__ . '/../init.php';

// Bezpečnostní kontrola - POUZE ADMIN
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('Unauthorized');
}

$pdo = getDbConnection();
$currentSection = $_GET['section'] ?? 'registracni_klice';
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';

// Check if accessed directly (not through admin.php)
$directAccess = !defined('ADMIN_PHP_LOADED');

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

    // API klíče - spočítat skutečně nakonfigurované služby z .env
    $nakonfigurovaneSluzby = 0;

    // Geoapify API (mapy)
    $geoapifyKey = getenv('GEOAPIFY_API_KEY') ?: getenv('GEOAPIFY_KEY');
    if (!empty($geoapifyKey) && $geoapifyKey !== false) {
        $nakonfigurovaneSluzby++;
    }

    // SMTP služba (email) - počítá se jako 1 služba, pokud je nastaveno
    $smtpHost = getenv('SMTP_HOST');
    $smtpUser = getenv('SMTP_USER');
    if (!empty($smtpHost) && !empty($smtpUser) && $smtpHost !== false && $smtpUser !== false) {
        $nakonfigurovaneSluzby++;
    }

    $stats['api_klice_celkem'] = $nakonfigurovaneSluzby;

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

// If embed mode, output full HTML structure
if ($embedMode && $directAccess):
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security - WGS Admin</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="embed-mode">
<?php
endif;
?>

<?php if (!$directAccess): ?>
<link rel="stylesheet" href="/assets/css/admin.css">
<?php endif; ?>

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
            <div class="stat-card" title="Geoapify (mapy), SMTP (email)">
                <div class="stat-card-value"><?= $stats['api_klice_celkem'] ?></div>
                <div class="stat-card-label">API služby</div>
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
                <button onclick="otevritPozvanku()" class="cc-btn cc-btn-primary" style="font-size: 0.8rem; padding: 0.4rem 0.75rem;">Pozvat</button>
                <button onclick="vytvorNovyKlic()" class="cc-btn cc-btn-success" style="font-size: 0.8rem; padding: 0.4rem 0.75rem;">+ Nový</button>
                <button onclick="nactiRegistracniKlice()" class="cc-btn cc-btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.75rem;">Obnovit</button>
            </div>
        </div>

        <div class="security-alert">
            <div>
                <strong>Registrační klíče řídí přístup</strong> - Pouze uživatelé s platným klíčem se mohou zaregistrovat.
            </div>
        </div>

        <div id="kontejner-klicu">
            <div style="padding: 1rem; text-align: center; color: #666; font-family: 'Poppins', sans-serif; font-size: 0.85rem;">Načítání klíčů...</div>
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

        <!-- GEOAPIFY API -->
        <div class="setting-group">
            <h3 class="setting-group-title">Geoapify (Mapy)</h3>
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">
                        GEOAPIFY_API_KEY
                        <span class="badge badge-warning">Vyžaduje reload</span>
                    </div>
                    <div class="setting-item-description">
                        API klíč pro mapové služby Geoapify (autocomplete adres, geocoding)
                    </div>
                </div>
                <div class="setting-item-right" style="min-width: 300px;">
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="password"
                               class="cc-input"
                               id="api-geoapify"
                               placeholder="API klíč Geoapify"
                               style="flex: 1; font-family: monospace; font-size: 0.85rem;">
                        <button class="cc-btn cc-btn-sm cc-btn-secondary"
                                onclick="togglePasswordVisibilitySimple('api-geoapify')"
                                style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">
                            Zobrazit
                        </button>
                        <button class="cc-btn cc-btn-sm cc-btn-primary"
                                onclick="ulozitApiKlic('GEOAPIFY_API_KEY', 'api-geoapify')"
                                style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">
                            Uložit
                        </button>
                    </div>
                    <div id="status-api-geoapify" style="margin-top: 0.5rem; display: none; font-size: 0.85rem;"></div>
                </div>
            </div>
        </div>

        <!-- SMTP KONFIGURACE -->
        <div class="setting-group">
            <h3 class="setting-group-title">SMTP Email</h3>

            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">SMTP_HOST</div>
                    <div class="setting-item-description">SMTP server (např. smtp.gmail.com)</div>
                </div>
                <div class="setting-item-right" style="min-width: 300px;">
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="text"
                               class="cc-input"
                               id="api-smtp-host"
                               placeholder="smtp.gmail.com"
                               style="flex: 1; font-size: 0.85rem;">
                        <button class="cc-btn cc-btn-sm cc-btn-primary"
                                onclick="ulozitApiKlic('SMTP_HOST', 'api-smtp-host')"
                                style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">
                            Uložit
                        </button>
                    </div>
                    <div id="status-api-smtp-host" style="margin-top: 0.5rem; display: none; font-size: 0.85rem;"></div>
                </div>
            </div>

            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">SMTP_PORT</div>
                    <div class="setting-item-description">SMTP port (obvykle 587 pro TLS)</div>
                </div>
                <div class="setting-item-right" style="min-width: 300px;">
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="number"
                               class="cc-input"
                               id="api-smtp-port"
                               placeholder="587"
                               style="width: 120px; font-size: 0.85rem;">
                        <button class="cc-btn cc-btn-sm cc-btn-primary"
                                onclick="ulozitApiKlic('SMTP_PORT', 'api-smtp-port')"
                                style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">
                            Uložit
                        </button>
                    </div>
                    <div id="status-api-smtp-port" style="margin-top: 0.5rem; display: none; font-size: 0.85rem;"></div>
                </div>
            </div>

            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">SMTP_USER</div>
                    <div class="setting-item-description">SMTP uživatelské jméno (email)</div>
                </div>
                <div class="setting-item-right" style="min-width: 300px;">
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="text"
                               class="cc-input"
                               id="api-smtp-user"
                               placeholder="user@example.com"
                               style="flex: 1; font-size: 0.85rem;">
                        <button class="cc-btn cc-btn-sm cc-btn-primary"
                                onclick="ulozitApiKlic('SMTP_USER', 'api-smtp-user')"
                                style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">
                            Uložit
                        </button>
                    </div>
                    <div id="status-api-smtp-user" style="margin-top: 0.5rem; display: none; font-size: 0.85rem;"></div>
                </div>
            </div>

            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">SMTP_PASS</div>
                    <div class="setting-item-description">SMTP heslo</div>
                </div>
                <div class="setting-item-right" style="min-width: 300px;">
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="password"
                               class="cc-input"
                               id="api-smtp-pass"
                               placeholder="••••••••"
                               style="flex: 1; font-family: monospace; font-size: 0.85rem;">
                        <button class="cc-btn cc-btn-sm cc-btn-secondary"
                                onclick="togglePasswordVisibilitySimple('api-smtp-pass')"
                                style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">
                            Zobrazit
                        </button>
                        <button class="cc-btn cc-btn-sm cc-btn-primary"
                                onclick="ulozitApiKlic('SMTP_PASS', 'api-smtp-pass')"
                                style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">
                            Uložit
                        </button>
                    </div>
                    <div id="status-api-smtp-pass" style="margin-top: 0.5rem; display: none; font-size: 0.85rem;"></div>
                </div>
            </div>
        </div>

        <!-- OSTATNÍ API KLÍČE Z DATABÁZE -->
        <?php if (isset($configs['api_keys']) && !empty($configs['api_keys'])): ?>
            <div class="setting-group">
                <h3 class="setting-group-title">Další API klíče</h3>
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
        <?php endif; ?>
    </div>

    <!-- SEKCE: BEZPEČNOSTNÍ NASTAVENÍ -->
    <div id="section-bezpecnost" class="cc-section <?= $currentSection === 'bezpecnost' ? 'active' : '' ?>">
        <h2 style="margin-bottom: 0.75rem; color: #000; font-size: 1rem; font-weight: 600; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.5px;">Bezpečnostní nastavení</h2>

        <div class="security-alert">
            <div>
                <strong>KRITICKÉ</strong> - Změny hesel a bezpečnostních nastavení mohou ovlivnit přístup do systému.
            </div>
        </div>

        <!-- SPRÁVA HESEL -->
        <div class="setting-group">
            <h3 class="setting-group-title">Správa hesel</h3>

            <!-- Admin heslo -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">Admin heslo</div>
                    <div class="setting-item-description">
                        Změna přihlašovacího hesla administrátora
                    </div>
                </div>
                <div class="setting-item-right" style="min-width: 400px;">
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="password"
                               class="cc-input"
                               id="admin-current-password"
                               placeholder="Aktuální heslo"
                               style="flex: 1; font-size: 0.85rem;">
                        <input type="password"
                               class="cc-input"
                               id="admin-new-password"
                               placeholder="Nové heslo"
                               style="flex: 1; font-size: 0.85rem;">
                        <button class="cc-btn cc-btn-sm cc-btn-primary"
                                onclick="zmenitAdminHeslo()"
                                style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">
                            Změnit
                        </button>
                    </div>
                    <div id="admin-password-status" style="margin-top: 0.5rem; display: none; font-size: 0.85rem;"></div>
                </div>
            </div>

            <!-- Reset uživatelských hesel -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">Reset uživatelských hesel</div>
                    <div class="setting-item-description">
                        Resetování hesla pro vybraného uživatele (admin může resetovat jakékoli heslo)
                    </div>
                </div>
                <div class="setting-item-right" style="min-width: 400px;">
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <select class="cc-input"
                                id="user-select-reset"
                                style="flex: 1; font-size: 0.85rem;">
                            <option value="">-- Vyberte uživatele --</option>
                        </select>
                        <input type="password"
                               class="cc-input"
                               id="user-new-password"
                               placeholder="Nové heslo"
                               style="flex: 1; font-size: 0.85rem;">
                        <button class="cc-btn cc-btn-sm cc-btn-primary"
                                onclick="resetovatUzivatelskeHeslo()"
                                style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">
                                Reset
                        </button>
                    </div>
                    <div id="user-password-status" style="margin-top: 0.5rem; display: none; font-size: 0.85rem;"></div>
                </div>
            </div>
        </div>

        <!-- OSTATNÍ BEZPEČNOSTNÍ NASTAVENÍ -->
        <?php if (isset($configs['security']) && !empty($configs['security'])): ?>
            <div class="setting-group">
                <h3 class="setting-group-title">Pokročilá nastavení</h3>
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

        <!-- Filtry -->
        <div style="margin: 1rem 0; padding: 1rem; background: #fff; border: 1px solid #000;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; font-family: 'Poppins', sans-serif; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.25rem; color: #000;">Datum od:</label>
                    <input type="date" id="auditDateFrom" value="<?= date('Y-m-d', strtotime('-7 days')) ?>" style="width: 100%; padding: 0.4rem; border: 1px solid #000; font-family: 'Poppins', sans-serif;">
                </div>
                <div>
                    <label style="display: block; font-family: 'Poppins', sans-serif; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.25rem; color: #000;">Datum do:</label>
                    <input type="date" id="auditDateTo" value="<?= date('Y-m-d') ?>" style="width: 100%; padding: 0.4rem; border: 1px solid #000; font-family: 'Poppins', sans-serif;">
                </div>
                <div>
                    <label style="display: block; font-family: 'Poppins', sans-serif; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.25rem; color: #000;">Typ události:</label>
                    <select id="auditActionFilter" style="width: 100%; padding: 0.4rem; border: 1px solid #000; font-family: 'Poppins', sans-serif;">
                        <option value="">Všechny události</option>
                        <option value="admin_login">Přihlášení admina</option>
                        <option value="user_login">Přihlášení uživatele</option>
                        <option value="user_logout">Odhlášení</option>
                        <option value="key_created">Vytvoření klíče</option>
                        <option value="key_deleted">Smazání klíče</option>
                        <option value="key_rotated">Rotace klíče</option>
                        <option value="failed_login">Neúspěšné přihlášení</option>
                        <option value="reklamace_created">Vytvoření reklamace</option>
                        <option value="reklamace_updated">Úprava reklamace</option>
                        <option value="reklamace_deleted">Smazání reklamace</option>
                    </select>
                </div>
                <div style="display: flex; align-items: flex-end;">
                    <button onclick="nactiAuditLogy()" style="width: 100%; padding: 0.5rem 1rem; background: #000; color: #fff; border: 1px solid #000; font-family: 'Poppins', sans-serif; font-size: 0.75rem; font-weight: 600; cursor: pointer; text-transform: uppercase;">
                        Načíst záznamy
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabulka s audit logy -->
        <div id="auditLogContainer" style="margin-top: 1rem;">
            <div style="text-align: center; padding: 2rem; color: #666; font-family: 'Poppins', sans-serif; font-size: 0.8rem;">
                Klikněte na "Načíst záznamy" pro zobrazení audit logů
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/csrf-auto-inject.min.js"></script>
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
        nactiRegistracniKlice();
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

        const response = await fetch('/api/admin.php?action=save_system_config', {
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

// Načíst registrační klíče
async function nactiRegistracniKlice() {
    const kontejner = document.getElementById('kontejner-klicu');
    if (!kontejner) return;

    try {
        kontejner.innerHTML = '<div style="padding: 1rem; text-align: center; color: #666; font-family: \'Poppins\', sans-serif; font-size: 0.85rem;">Načítání klíčů...</div>';

        const odpoved = await fetch('/api/admin_api.php?action=list_keys', {
            credentials: 'same-origin'
        });

        if (!odpoved.ok) {
            throw new Error('HTTP chyba ' + odpoved.status);
        }

        const text = await odpoved.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('API vrátilo neplatný JSON:', text);
            throw new Error('Server vrátil neplatnou odpověď');
        }

        if (data.status === 'success') {
            if (data.keys.length === 0) {
                kontejner.innerHTML = '<div style="padding: 2rem; text-align: center; color: #999; font-family: \'Poppins\', sans-serif;">Žádné klíče</div>';
                return;
            }

            let html = '<table style="width: 100%; border-collapse: collapse; font-family: \'Poppins\', sans-serif; font-size: 0.85rem;">';
            html += '<thead><tr>';
            html += '<th style="padding: 0.5rem; text-align: left; background: #000; color: #fff; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid #ddd;">Typ</th>';
            html += '<th style="padding: 0.5rem; text-align: left; background: #000; color: #fff; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid #ddd;">Kód</th>';
            html += '<th style="padding: 0.5rem; text-align: left; background: #000; color: #fff; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid #ddd;">Použití</th>';
            html += '<th style="padding: 0.5rem; text-align: left; background: #000; color: #fff; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid #ddd;">Aktivní</th>';
            html += '<th style="padding: 0.5rem; text-align: left; background: #000; color: #fff; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid #ddd;">Vytvořen</th>';
            html += '<th style="padding: 0.5rem; text-align: left; background: #000; color: #fff; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid #ddd;">Akce</th>';
            html += '</tr></thead><tbody>';

            data.keys.forEach(klic => {
                html += '<tr style="border-bottom: 1px solid #e0e0e0;" onmouseover="this.style.background=\'#f5f5f5\'" onmouseout="this.style.background=\'#fff\'">';
                html += '<td style="padding: 0.5rem; border: 1px solid #ddd;"><span style="display: inline-block; padding: 0.2rem 0.5rem; background: #000; color: #fff; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.3px; font-weight: 500;">' + escapujHtml(klic.key_type) + '</span></td>';
                html += '<td style="padding: 0.5rem; border: 1px solid #ddd;"><code style="background: #f5f5f5; padding: 0.25rem 0.5rem; font-size: 0.8rem; border: 1px solid #ddd;">' + escapujHtml(klic.key_code) + '</code></td>';
                html += '<td style="padding: 0.5rem; border: 1px solid #ddd;">' + klic.usage_count + ' / ' + (klic.max_usage || '∞') + '</td>';
                html += '<td style="padding: 0.5rem; border: 1px solid #ddd;">' + (klic.is_active ? '<span style="color: #000;">Ano</span>' : '<span style="color: #999;">Ne</span>') + '</td>';
                html += '<td style="padding: 0.5rem; border: 1px solid #ddd;">' + new Date(klic.created_at).toLocaleDateString('cs-CZ') + '</td>';
                html += '<td style="padding: 0.5rem; border: 1px solid #ddd;"><button onclick="kopirovatDoSchranky(\'' + klic.key_code.replace(/'/g, "\\'") + '\')" class="cc-btn cc-btn-sm cc-btn-primary" style="margin-right: 0.25rem;">Kopírovat</button>';
                html += '<button onclick="smazatKlic(\'' + klic.key_code.replace(/'/g, "\\'") + '\')" class="cc-btn cc-btn-sm cc-btn-secondary">Smazat</button></td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            kontejner.innerHTML = html;
        } else {
            throw new Error(data.message || 'Nepodařilo se načíst klíče');
        }
    } catch (chyba) {
        kontejner.innerHTML = '<div class="security-alert"><strong>Chyba při načítání klíčů:</strong> ' + escapujHtml(chyba.message || 'Neznámá chyba') + '</div>';
        console.error('[Security] Chyba načítání klíčů:', chyba);
    }
}

// Vytvořit nový klíč - zobrazí modal pro výběr typu
function vytvorNovyKlic() {
    // Odstranit existující modal pokud existuje
    const existujici = document.getElementById('modalVytvorKlic');
    if (existujici) existujici.remove();

    const modal = document.createElement('div');
    modal.id = 'modalVytvorKlic';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.6); display: flex;
        align-items: center; justify-content: center; z-index: 10000;
    `;

    modal.innerHTML = `
        <div style="background: #1a1a1a; padding: 30px; border-radius: 12px;
                    max-width: 400px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.5);
                    border: 1px solid #333;">
            <h3 style="margin: 0 0 20px 0; color: #fff; font-size: 1.3rem;">
                Vytvořit nový registrační klíč
            </h3>

            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; padding: 15px;
                              background: #252525; border-radius: 8px; cursor: pointer;
                              margin-bottom: 10px; border: 2px solid transparent;">
                    <input type="radio" name="typKliceVyber" value="technik"
                           style="width: 20px; height: 20px; margin-right: 15px; accent-color: #fff;">
                    <div>
                        <div style="color: #fff; font-weight: 600; font-size: 1.1rem;">TECHNIK</div>
                        <div style="color: #888; font-size: 0.85rem;">Pro servisní techniky</div>
                    </div>
                </label>

                <label style="display: flex; align-items: center; padding: 15px;
                              background: #252525; border-radius: 8px; cursor: pointer;
                              border: 2px solid transparent;">
                    <input type="radio" name="typKliceVyber" value="prodejce"
                           style="width: 20px; height: 20px; margin-right: 15px; accent-color: #fff;">
                    <div>
                        <div style="color: #fff; font-weight: 600; font-size: 1.1rem;">PRODEJCE</div>
                        <div style="color: #888; font-size: 0.85rem;">Pro prodejce a obchodníky</div>
                    </div>
                </label>
            </div>

            <div style="display: flex; gap: 10px;">
                <button onclick="odeslatVytvoreniKlice()" style="flex: 1; padding: 12px;
                        background: #fff; color: #000; border: none; border-radius: 6px;
                        font-weight: 600; cursor: pointer; font-size: 1rem;">
                    Vytvořit klíč
                </button>
                <button onclick="document.getElementById('modalVytvorKlic').remove()"
                        style="flex: 1; padding: 12px; background: #333; color: #fff;
                        border: none; border-radius: 6px; cursor: pointer; font-size: 1rem;">
                    Zrušit
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Zavřít při kliknutí na pozadí
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.remove();
    });
}

// Odeslat požadavek na vytvoření klíče
async function odeslatVytvoreniKlice() {
    const vybrany = document.querySelector('input[name="typKliceVyber"]:checked');

    if (!vybrany) {
        alert('Vyberte typ klíče');
        return;
    }

    const typKlice = vybrany.value;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!csrfToken) {
            throw new Error('CSRF token není k dispozici');
        }

        const odpoved = await fetch('/api/admin_api.php?action=create_key', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                key_type: typKlice,
                csrf_token: csrfToken
            })
        });

        const data = await odpoved.json();

        document.getElementById('modalVytvorKlic')?.remove();

        if (data.status === 'success') {
            alert('Klíč vytvořen: ' + data.key_code);
            nactiRegistracniKlice();
        } else {
            alert('Chyba: ' + (data.message || 'Nepodařilo se vytvořit klíč'));
        }
    } catch (chyba) {
        alert('Chyba: ' + chyba.message);
        console.error('[Security] Chyba vytváření klíče:', chyba);
    }
}

// ==========================================
// POZVANKOVY SYSTEM (ZJEDNODUSENY)
// Sablony jsou nyni v databazi wgs_notifications
// ==========================================

// Otevrit modal pro pozvanku
function otevritPozvanku() {
    // Odstranit existujici modal
    const existujici = document.getElementById('modalPozvanka');
    if (existujici) existujici.remove();

    const modal = document.createElement('div');
    modal.id = 'modalPozvanka';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.7); display: flex;
        align-items: center; justify-content: center; z-index: 10000;
    `;

    modal.innerHTML = `
        <div style="background: #1a1a1a; padding: 30px; border-radius: 12px;
                    max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.5);
                    border: 1px solid #333;">

            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3 style="margin: 0; color: #fff; font-size: 1.2rem;">Odeslat pozvanku</h3>
                <button onclick="document.getElementById('modalPozvanka').remove()"
                        style="background: none; border: none; color: #888; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>

            <!-- Vyber role -->
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #aaa; font-size: 0.85rem; margin-bottom: 8px;">Typ pozvanky</label>
                <div style="display: flex; gap: 10px;">
                    <label style="flex: 1; display: flex; align-items: center; padding: 12px;
                                  background: #252525; border-radius: 6px; cursor: pointer;
                                  border: 2px solid transparent;" id="labelTechnik">
                        <input type="radio" name="typPozvanky" value="technik" onchange="aktualizovatVyber()"
                               style="width: 16px; height: 16px; margin-right: 10px; accent-color: #fff;">
                        <span style="color: #fff;">Technik</span>
                    </label>
                    <label style="flex: 1; display: flex; align-items: center; padding: 12px;
                                  background: #252525; border-radius: 6px; cursor: pointer;
                                  border: 2px solid transparent;" id="labelProdejce">
                        <input type="radio" name="typPozvanky" value="prodejce" onchange="aktualizovatVyber()"
                               style="width: 16px; height: 16px; margin-right: 10px; accent-color: #fff;">
                        <span style="color: #fff;">Prodejce</span>
                    </label>
                </div>
            </div>

            <!-- Vyber klice -->
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #aaa; font-size: 0.85rem; margin-bottom: 8px;">Registracni klic</label>
                <select id="vyberKlice" style="width: 100%; padding: 10px; background: #252525; border: 1px solid #444;
                        border-radius: 6px; color: #fff; font-size: 0.9rem;">
                    <option value="auto">Vytvorit novy klic automaticky</option>
                </select>
            </div>

            <!-- Emaily -->
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #aaa; font-size: 0.85rem; margin-bottom: 8px;">
                    Emailove adresy (kazdy na novy radek)
                </label>
                <textarea id="emailyPozvanky" rows="4" placeholder="jan.novak@example.com"
                          style="width: 100%; padding: 10px; background: #252525; border: 1px solid #444;
                          border-radius: 6px; color: #fff; font-size: 0.9rem; resize: vertical; font-family: monospace;"></textarea>
                <div style="color: #666; font-size: 0.75rem; margin-top: 5px;">
                    Zadano: <span id="pocetEmailu">0</span> / 30 emailu
                </div>
            </div>

            <!-- Info o sablone -->
            <div style="background: #252525; border-radius: 6px; padding: 12px; margin-bottom: 20px;">
                <div style="color: #888; font-size: 0.8rem;">
                    Sablona se nacte z databaze (wgs_notifications).
                    Editovat ji muzete v karte "Email sablony".
                </div>
            </div>

            <!-- Tlacitka -->
            <div style="display: flex; gap: 10px;">
                <button onclick="odeslatPozvanky()" id="btnOdeslatPozvanky" disabled
                        style="flex: 1; padding: 12px; background: #fff; color: #000; border: none;
                        border-radius: 6px; font-weight: 600; cursor: pointer; opacity: 0.5;">
                    Odeslat
                </button>
                <button onclick="document.getElementById('modalPozvanka').remove()"
                        style="flex: 1; padding: 12px; background: #333; color: #fff;
                        border: none; border-radius: 6px; cursor: pointer;">
                    Zrusit
                </button>
            </div>

            <!-- Status -->
            <div id="statusPozvanky" style="margin-top: 15px; display: none; padding: 12px; border-radius: 6px;"></div>
        </div>
    `;

    document.body.appendChild(modal);

    // Nacist klice do selectu
    nacistKliceProPozvanku();

    // Event listener pro pocitani emailu
    const emailyEl = document.getElementById('emailyPozvanky');
    if (emailyEl) {
        emailyEl.addEventListener('input', function() {
            const emaily = this.value.split('\\n').filter(e => e.trim() !== '');
            const pocetEl = document.getElementById('pocetEmailu');
            if (pocetEl) pocetEl.textContent = emaily.length;
            aktualizovatTlacitkoOdeslat();
        });
    }

    // Zavrit pri kliknuti na pozadi
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.remove();
    });
}

// Aktualizovat vyber typu pozvanky (vizualni)
function aktualizovatVyber() {
    const typ = document.querySelector('input[name="typPozvanky"]:checked')?.value;
    const labelTechnik = document.getElementById('labelTechnik');
    const labelProdejce = document.getElementById('labelProdejce');

    if (labelTechnik) labelTechnik.style.borderColor = typ === 'technik' ? '#fff' : 'transparent';
    if (labelProdejce) labelProdejce.style.borderColor = typ === 'prodejce' ? '#fff' : 'transparent';

    aktualizovatTlacitkoOdeslat();
}

// Aktualizovat stav tlacitka odeslat
function aktualizovatTlacitkoOdeslat() {
    const typ = document.querySelector('input[name="typPozvanky"]:checked')?.value;
    const emailyEl = document.getElementById('emailyPozvanky');
    const emaily = emailyEl ? emailyEl.value.split('\\n').filter(e => e.trim() !== '') : [];
    const btn = document.getElementById('btnOdeslatPozvanky');

    if (btn) {
        if (typ && emaily.length > 0 && emaily.length <= 30) {
            btn.disabled = false;
            btn.style.opacity = '1';
        } else {
            btn.disabled = true;
            btn.style.opacity = '0.5';
        }
    }
}

// Nacist klice pro pozvanku
async function nacistKliceProPozvanku() {
    try {
        const odpoved = await fetch('/api/admin_api.php?action=get_keys');
        const data = await odpoved.json();

        const select = document.getElementById('vyberKlice');
        if (select && data.status === 'success' && data.keys) {
            let options = '<option value="auto">Vytvorit novy klic automaticky</option>';
            data.keys.forEach(klic => {
                if (klic.is_active) {
                    const limit = klic.max_usage === null ? 'neomezeno' : klic.max_usage;
                    options += `<option value="${klic.key_code}">${klic.key_code} (${klic.key_type.toUpperCase()}) - ${klic.usage_count}/${limit}</option>`;
                }
            });
            select.innerHTML = options;
        }
    } catch (e) {
        console.error('Chyba nacitani klicu:', e);
    }
}

// Odeslat pozvánky
async function odeslatPozvanky() {
    const typ = document.querySelector('input[name="typPozvanky"]:checked')?.value;
    const emailyText = document.getElementById('emailyPozvanky').value;
    const klicSelect = document.getElementById('vyberKlice');
    const klic = klicSelect.value;
    const statusEl = document.getElementById('statusPozvanky');
    const btn = document.getElementById('btnOdeslatPozvanky');

    // Parsovat emaily
    const emaily = emailyText.split('\\n')
        .map(e => e.trim())
        .filter(e => e !== '' && e.includes('@'));

    if (emaily.length === 0) {
        statusEl.style.display = 'block';
        statusEl.style.background = '#f8d7da';
        statusEl.style.color = '#721c24';
        statusEl.textContent = 'Zadejte alespon jeden platny email';
        return;
    }

    if (emaily.length > 30) {
        statusEl.style.display = 'block';
        statusEl.style.background = '#f8d7da';
        statusEl.style.color = '#721c24';
        statusEl.textContent = 'Maximalne 30 emailu najednou';
        return;
    }

    // Disable button
    btn.disabled = true;
    btn.textContent = 'Odesilam...';
    statusEl.style.display = 'block';
    statusEl.style.background = '#d1ecf1';
    statusEl.style.color = '#0c5460';
    statusEl.textContent = `Odesilam pozvanky na ${emaily.length} adres...`;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!csrfToken) {
            throw new Error('CSRF token neni k dispozici');
        }

        const odpoved = await fetch('/api/admin_api.php?action=send_invitations', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                typ: typ,
                klic: klic,
                emaily: emaily,
                csrf_token: csrfToken
            })
        });

        const data = await odpoved.json();

        if (data.status === 'success') {
            statusEl.style.background = '#d4edda';
            statusEl.style.color = '#155724';
            statusEl.innerHTML = `<strong>Uspech!</strong> Odeslano ${data.sent_count} pozvanek.` +
                (data.key_code ? ` Pouzity klic: <code>${data.key_code}</code>` : '');

            // Refresh klíčů
            nactiRegistracniKlice();

            // Vyčistit formulář po 3s
            setTimeout(() => {
                const emailyEl = document.getElementById('emailyPozvanky');
                const pocetEl = document.getElementById('pocetEmailu');
                if (emailyEl) emailyEl.value = '';
                if (pocetEl) pocetEl.textContent = '0';
            }, 3000);
        } else {
            throw new Error(data.message || 'Nepodarilo se odeslat pozvanky');
        }
    } catch (chyba) {
        statusEl.style.background = '#f8d7da';
        statusEl.style.color = '#721c24';
        statusEl.textContent = 'Chyba: ' + chyba.message;
    }

    btn.disabled = false;
    btn.textContent = 'Odeslat pozvanky';
}

// Smazat klíč
async function smazatKlic(kodKlice) {
    if (!confirm('Opravdu chcete smazat klíč ' + kodKlice + '?')) {
        return;
    }

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!csrfToken) {
            throw new Error('CSRF token není k dispozici');
        }

        console.log('[Security] Mazání klíče:', kodKlice);
        console.log('[Security] CSRF token:', csrfToken);

        const requestBody = JSON.stringify({
            key_code: kodKlice,
            csrf_token: csrfToken
        });
        console.log('[Security] Request body:', requestBody);

        const odpoved = await fetch('/api/admin_api.php?action=delete_key', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: requestBody
        });

        console.log('[Security] Response status:', odpoved.status);
        console.log('[Security] Response Content-Type:', odpoved.headers.get('content-type'));

        // OPRAVA: Kontrola HTTP statusu PŘED parsováním JSON
        if (!odpoved.ok) {
            const errorText = await odpoved.text();
            console.error('[Security] HTTP error response body:', errorText);
            console.error('[Security] Response length:', errorText.length);

            // Zkusit parsovat jako JSON
            try {
                const errorJson = JSON.parse(errorText);
                console.error('[Security] Parsed error JSON:', errorJson);
                throw new Error(`HTTP ${odpoved.status}: ${errorJson.message || errorJson.error || errorText}`);
            } catch (parseError) {
                console.error('[Security] Response není validní JSON');
                throw new Error(`HTTP ${odpoved.status}: ${errorText || 'Prázdná odpověď'}`);
            }
        }

        const data = await odpoved.json();
        console.log('[Security] Response:', data);

        if (data.status === 'success') {
            alert('Klíč byl úspěšně smazán');
            nactiRegistracniKlice();
        } else {
            alert('Chyba: ' + (data.message || 'Nepodařilo se smazat klíč'));
        }
    } catch (chyba) {
        alert('Chyba mazání klíče: ' + chyba.message);
        console.error('[Security] Chyba mazání klíče:', chyba);
    }
}

// Kopírovat do schránky
function kopirovatDoSchranky(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Zkopírováno do schránky: ' + text);
    }).catch(chyba => {
        alert('Chyba kopírování: ' + chyba.message);
    });
}

// Escapovat HTML
function escapujHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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

// Změnit admin heslo
async function zmenitAdminHeslo() {
    const aktualniHeslo = document.getElementById('admin-current-password');
    const noveHeslo = document.getElementById('admin-new-password');
    const statusEl = document.getElementById('admin-password-status');

    if (!aktualniHeslo.value || !noveHeslo.value) {
        statusEl.style.display = 'block';
        statusEl.innerHTML = '<span style="color: #dc3545;">Vyplňte obě pole</span>';
        return;
    }

    statusEl.style.display = 'block';
    statusEl.innerHTML = 'Ukládám...';

    try {
        const csrfToken = typeof getCSRFToken === 'function' ? await getCSRFToken() :
                         document.querySelector('meta[name="csrf-token"]')?.content;

        const odpoved = await fetch('/api/admin_api.php?action=change_admin_password', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                current_password: aktualniHeslo.value,
                new_password: noveHeslo.value,
                csrf_token: csrfToken
            })
        });

        const data = await odpoved.json();

        if (data.status === 'success') {
            statusEl.innerHTML = '<span style="color: #28a745;">Heslo bylo změněno</span>';
            aktualniHeslo.value = '';
            noveHeslo.value = '';
            setTimeout(() => { statusEl.style.display = 'none'; }, 3000);
        } else {
            throw new Error(data.message || 'Nepodařilo se změnit heslo');
        }
    } catch (error) {
        statusEl.innerHTML = '<span style="color: #dc3545;">Chyba: ' + escapujHtml(error.message) + '</span>';
    }
}

// Resetovat uživatelské heslo
async function resetovatUzivatelskeHeslo() {
    const userSelect = document.getElementById('user-select-reset');
    const noveHeslo = document.getElementById('user-new-password');
    const statusEl = document.getElementById('user-password-status');

    if (!userSelect.value || !noveHeslo.value) {
        statusEl.style.display = 'block';
        statusEl.innerHTML = '<span style="color: #dc3545;">Vyberte uživatele a zadejte nové heslo</span>';
        return;
    }

    if (!confirm(`Opravdu chcete resetovat heslo pro uživatele ${userSelect.options[userSelect.selectedIndex].text}?`)) {
        return;
    }

    statusEl.style.display = 'block';
    statusEl.innerHTML = 'Ukládám...';

    try {
        const csrfToken = typeof getCSRFToken === 'function' ? await getCSRFToken() :
                         document.querySelector('meta[name="csrf-token"]')?.content;

        const odpoved = await fetch('/api/admin_api.php?action=reset_user_password', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                user_id: parseInt(userSelect.value),
                new_password: noveHeslo.value,
                csrf_token: csrfToken
            })
        });

        const data = await odpoved.json();

        if (data.status === 'success') {
            statusEl.innerHTML = '<span style="color: #28a745;">Heslo bylo resetováno</span>';
            noveHeslo.value = '';
            setTimeout(() => { statusEl.style.display = 'none'; }, 3000);
        } else {
            throw new Error(data.message || 'Nepodařilo se resetovat heslo');
        }
    } catch (error) {
        statusEl.innerHTML = '<span style="color: #dc3545;">Chyba: ' + escapujHtml(error.message) + '</span>';
    }
}

// Uložit API klíč
async function ulozitApiKlic(nazevKlice, inputId) {
    const input = document.getElementById(inputId);
    const statusEl = document.getElementById('status-' + inputId);
    const hodnota = input.value.trim();

    if (!hodnota) {
        statusEl.style.display = 'block';
        statusEl.innerHTML = '<span style="color: #dc3545;">Zadejte hodnotu</span>';
        return;
    }

    statusEl.style.display = 'block';
    statusEl.innerHTML = 'Ukládám...';

    try {
        const csrfToken = typeof getCSRFToken === 'function' ? await getCSRFToken() :
                         document.querySelector('meta[name="csrf-token"]')?.content;

        const odpoved = await fetch('/api/admin_api.php?action=update_api_key', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                key_name: nazevKlice,
                key_value: hodnota,
                csrf_token: csrfToken
            })
        });

        const data = await odpoved.json();

        if (data.status === 'success') {
            statusEl.innerHTML = '<span style="color: #28a745;">Uloženo</span>';
            setTimeout(() => { statusEl.style.display = 'none'; }, 2000);
        } else {
            throw new Error(data.message || 'Nepodařilo se uložit');
        }
    } catch (error) {
        statusEl.innerHTML = '<span style="color: #dc3545;">Chyba: ' + escapujHtml(error.message) + '</span>';
    }
}

// Toggle password visibility (simple version)
function togglePasswordVisibilitySimple(inputId) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
}

// Načíst uživatele pro dropdown
async function nactiUzivateleProDropdown() {
    const select = document.getElementById('user-select-reset');
    if (!select) return;

    try {
        const odpoved = await fetch('/api/admin_api.php?action=get_users');
        const data = await odpoved.json();

        if (data.status === 'success' && data.users) {
            select.innerHTML = '<option value="">-- Vyberte uživatele --</option>';
            data.users.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = `${user.name || user.email} (${user.email})`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Chyba načítání uživatelů:', error);
    }
}

// Načíst aktuální hodnoty API klíčů
async function nactiApiKlice() {
    try {
        const odpoved = await fetch('/api/admin_api.php?action=get_api_keys');
        const data = await odpoved.json();

        if (data.status === 'success' && data.keys) {
            // Nastavit hodnoty do inputů
            if (data.keys.GEOAPIFY_API_KEY) {
                document.getElementById('api-geoapify').value = data.keys.GEOAPIFY_API_KEY;
            }
            if (data.keys.SMTP_HOST) {
                document.getElementById('api-smtp-host').value = data.keys.SMTP_HOST;
            }
            if (data.keys.SMTP_PORT) {
                document.getElementById('api-smtp-port').value = data.keys.SMTP_PORT;
            }
            if (data.keys.SMTP_USER) {
                document.getElementById('api-smtp-user').value = data.keys.SMTP_USER;
            }
            if (data.keys.SMTP_PASS) {
                document.getElementById('api-smtp-pass').value = data.keys.SMTP_PASS;
            }
        }
    } catch (error) {
        console.error('Chyba načítání API klíčů:', error);
    }
}

/**
 * Načíst Audit Logy
 */
async function nactiAuditLogy() {
    try {
        const dateFrom = document.getElementById('auditDateFrom').value;
        const dateTo = document.getElementById('auditDateTo').value;
        const action = document.getElementById('auditActionFilter').value;

        const container = document.getElementById('auditLogContainer');
        container.innerHTML = '<div style="text-align: center; padding: 2rem;"><div style="width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #000; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div><p style="margin-top: 1rem; color: #666;">Načítání audit logů...</p></div>';

        const formData = new FormData();
        formData.append('action', 'get_audit_logs');
        formData.append('date_from', dateFrom + ' 00:00:00');
        formData.append('date_to', dateTo + ' 23:59:59');
        if (action) formData.append('filter_action', action);

        // Přidat CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrfToken) {
            formData.append('csrf_token', csrfToken);
        }

        const response = await fetch('/api/admin/security_api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.status === 'success') {
            zobrazAuditLogy(data.logs);
        } else {
            container.innerHTML = `<div style="text-align: center; padding: 2rem; color: #dc3545;">Chyba: ${data.message}</div>`;
        }
    } catch (error) {
        console.error('Chyba načítání audit logů:', error);
        document.getElementById('auditLogContainer').innerHTML = '<div style="text-align: center; padding: 2rem; color: #dc3545;">Chyba při načítání audit logů</div>';
    }
}

/**
 * Zobrazit Audit Logy v tabulce
 */
function zobrazAuditLogy(logs) {
    const container = document.getElementById('auditLogContainer');

    if (logs.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 2rem; color: #666; font-family: Poppins, sans-serif;">Žádné záznamy nenalezeny</div>';
        return;
    }

    // Mapování akcí na česky
    const akceMapovani = {
        'admin_login': 'Přihlášení admina',
        'user_login': 'Přihlášení uživatele',
        'user_logout': 'Odhlášení',
        'key_created': 'Vytvoření klíče',
        'key_deleted': 'Smazání klíče',
        'key_rotated': 'Rotace klíče',
        'failed_login': 'Neúspěšné přihlášení',
        'reklamace_created': 'Vytvoření reklamace',
        'reklamace_updated': 'Úprava reklamace',
        'reklamace_deleted': 'Smazání reklamace'
    };

    let html = '<div style="background: #fff; border: 1px solid #000; overflow-x: auto;">';
    html += '<table style="width: 100%; border-collapse: collapse; font-family: Poppins, sans-serif; font-size: 0.75rem;">';
    html += '<thead><tr style="background: #f5f5f5; border-bottom: 2px solid #000;">';
    html += '<th style="padding: 0.5rem; text-align: left; font-weight: 600; border-right: 1px solid #ddd;">Datum a čas</th>';
    html += '<th style="padding: 0.5rem; text-align: left; font-weight: 600; border-right: 1px solid #ddd;">Akce</th>';
    html += '<th style="padding: 0.5rem; text-align: left; font-weight: 600; border-right: 1px solid #ddd;">Uživatel</th>';
    html += '<th style="padding: 0.5rem; text-align: left; font-weight: 600; border-right: 1px solid #ddd;">IP adresa</th>';
    html += '<th style="padding: 0.5rem; text-align: left; font-weight: 600;">Detaily</th>';
    html += '</tr></thead><tbody>';

    logs.forEach((log, index) => {
        const backgroundColor = index % 2 === 0 ? '#fff' : '#f9f9f9';
        const akceNazev = akceMapovani[log.action] || log.action;
        const isAdmin = log.is_admin ? '<span style="background: #000; color: #fff; padding: 2px 6px; border-radius: 2px; font-size: 0.65rem; margin-left: 4px;">ADMIN</span>' : '';

        html += `<tr style="background: ${backgroundColor}; border-bottom: 1px solid #e5e5e5;">`;
        html += `<td style="padding: 0.5rem; border-right: 1px solid #ddd; white-space: nowrap;">${log.timestamp}</td>`;
        html += `<td style="padding: 0.5rem; border-right: 1px solid #ddd;"><strong>${akceNazev}</strong></td>`;
        html += `<td style="padding: 0.5rem; border-right: 1px solid #ddd;">${log.user_name || 'Unknown'}${isAdmin}</td>`;
        html += `<td style="padding: 0.5rem; border-right: 1px solid #ddd;"><code>${log.ip}</code></td>`;
        html += `<td style="padding: 0.5rem;"><details><summary style="cursor: pointer; color: #667eea;">Zobrazit detaily</summary><pre style="margin-top: 0.5rem; padding: 0.5rem; background: #f5f5f5; border: 1px solid #ddd; border-radius: 3px; font-size: 0.7rem; overflow-x: auto;">${JSON.stringify(log.details, null, 2)}</pre></details></td>`;
        html += '</tr>';
    });

    html += '</tbody></table></div>';
    html += `<div style="margin-top: 0.75rem; padding: 0.5rem; background: #f5f5f5; border: 1px solid #000; font-family: Poppins, sans-serif; font-size: 0.7rem; text-align: right;">Celkem záznamů: <strong>${logs.length}</strong></div>`;

    container.innerHTML = html;
}

// Načíst data při načtení stránky
document.addEventListener('DOMContentLoaded', function() {
    const aktualniSekce = new URLSearchParams(window.location.search).get('section') || 'registracni_klice';

    // Načíst data pro aktuální sekci
    if (aktualniSekce === 'registracni_klice') {
        nactiRegistracniKlice();
    } else if (aktualniSekce === 'uzivatele') {
        loadUzivateleProSecurity();
    } else if (aktualniSekce === 'bezpecnost') {
        nactiUzivateleProDropdown();
    } else if (aktualniSekce === 'api_klice') {
        nactiApiKlice();
    } else if (aktualniSekce === 'audit') {
        // Automaticky načíst audit logy při otevření sekce
        setTimeout(() => nactiAuditLogy(), 500);
    }
});

console.log('Security centrum načteno');

</script>


<?php if ($embedMode && $directAccess): ?>
</body>
</html>
<?php endif; ?>
