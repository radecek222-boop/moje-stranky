<?php
require_once "init.php";
require_once __DIR__ . '/includes/admin_navigation.php';

// BEZPEČNOST: Kontrola admin přihlášení
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    header('Location: login.php?redirect=admin.php');
    exit;
}

// Detect embed mode for iframes
// SECURITY FIX: Strict comparison (=== místo ==)
$embedMode = isset($_GET['embed']) && $_GET['embed'] === '1';

// BEZPEČNOST: Security headers
if (!$embedMode) {
    // Content-Security-Policy - ochrana před XSS útoky
    // SECURITY FIX: Odstraněn 'unsafe-eval' pro lepší bezpečnost
    header("Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; " .
        "font-src 'self' https://fonts.gstatic.com; " .
        "img-src 'self' data: https: blob: https://tile.openstreetmap.org https://*.tile.openstreetmap.org; " .
        "connect-src 'self' data: https://api.geoapify.com https://router.project-osrm.org; " .
        "frame-src 'self'; " .
        "object-src 'none'; " .
        "base-uri 'self'; " .
        "form-action 'self';"
    );

    // X-Frame-Options - ochrana před clickjacking
    header("X-Frame-Options: SAMEORIGIN");

    // X-Content-Type-Options - zamezí MIME type sniffing
    header("X-Content-Type-Options: nosniff");

    // Referrer-Policy - kontrola sdílení referrer informací
    header("Referrer-Policy: strict-origin-when-cross-origin");

    // Permissions-Policy - omezení přístupu k browser features
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

$tabConfig = loadAdminTabNavigation();
$activeTab = $_GET['tab'] ?? 'control_center';
if (!array_key_exists($activeTab, $tabConfig)) {
    $activeTab = 'control_center';
}
$currentTabMeta = $tabConfig[$activeTab];
$currentTabLabel = $currentTabMeta['tab_label'] ?? 'Control Center';

// Zkontroluj jestli je RBAC nainstalován
$rbacInstalled = false;
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SHOW COLUMNS FROM `wgs_reklamace` LIKE 'created_by'");
    if ($stmt->rowCount() > 0) {
        $rbacInstalled = true;
    }
} catch (Exception $e) {
    // Ignoruj chyby
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <meta name="description" content="Administrační panel White Glove Service. Správa uživatelů, registračních klíčů, emailů, SMS notifikací a systémových nastavení.">
  <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
  <meta name="theme-color" content="#000000">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <meta name="apple-mobile-web-app-title" content="WGS Admin">
  <title>Administrace | White Glove Service</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet">

    <!-- External CSS -->
    <!-- Unified Design System -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/admin.min.css">
<link rel="stylesheet" href="assets/css/admin-header.css">
<link rel="stylesheet" href="assets/css/admin-notifications.css">
<link rel="stylesheet" href="assets/css/control-center-modal.css">
<link rel="stylesheet" href="assets/css/control-center-mobile.css">

  <!-- Error Handler - zachytává všechny chyby -->
  <script src="assets/js/error-handler.js"></script>
  <script src="assets/js/html-sanitizer.js"></script>
  <script src="assets/js/control-center-modal.js" defer></script>
</head>

<body<?php if ($embedMode): ?> class="embed-mode"<?php endif; ?>>
<?php if (!$embedMode): ?>
<?php require_once __DIR__ . "/includes/admin_header.php"; ?>
<?php endif; ?>

<!-- MAIN CONTENT -->
<main>
<div class="container">

  <?php if (!$embedMode && !str_starts_with($activeTab, 'control_center')): ?>
  <h1 class="page-title"><?php echo htmlspecialchars($currentTabLabel, ENT_QUOTES, 'UTF-8'); ?></h1>
  <p class="page-subtitle">Správa systému White Glove Service</p>
  <?php endif; ?>

  <?php if ($activeTab === 'dashboard'): ?>
  <!-- TAB: DASHBOARD -->
  <div id="tab-dashboard" class="tab-content">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Reklamace</div>
        <div class="stat-value" id="stat-claims">0</div>
      </div>

      <div class="stat-card">
        <div class="stat-label">Uživatelé</div>
        <div class="stat-value" id="stat-users">0</div>
      </div>

      <div class="stat-card">
        <div class="stat-label">Online</div>
        <div class="stat-value" id="stat-online">0</div>
      </div>

      <div class="stat-card">
        <div class="stat-label">Aktivní klíče</div>
        <div class="stat-value" id="stat-keys">0</div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  
  <?php if ($activeTab === 'notifications'): ?>
  <!-- TAB: NOTIFICATIONS -->
  <div id="tab-notifications" class="tab-content">

    <style>
    .notif-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 1rem;
    }

    .notif-header {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
    }

    .notif-title {
        font-size: 1.2rem;
        font-weight: 500;
        margin: 0 0 0.25rem 0;
    }

    .notif-subtitle {
        font-size: 0.75rem;
        color: #666;
        margin: 0;
    }

    .notif-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
    }

    .notif-card {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 1.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .notif-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: #2D5016;
    }

    .notif-card-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #000;
    }

    .notif-card-description {
        font-size: 0.8rem;
        color: #666;
        line-height: 1.4;
        margin-bottom: 0.75rem;
    }

    .notif-card-meta {
        display: flex;
        gap: 0.75rem;
        font-size: 0.7rem;
        color: #999;
    }

    .notif-card-badge {
        display: inline-block;
        padding: 0.2rem 0.5rem;
        background: #f0f0f0;
        border-radius: 3px;
        font-size: 0.65rem;
        font-weight: 600;
    }

    .notif-card-badge.active {
        background: #d4edda;
        color: #155724;
    }

    .notif-card-badge.inactive {
        background: #f8d7da;
        color: #721c24;
    }
    </style>

    <div class="notif-container">

      <!-- Header -->
      <div class="notif-header">
        <h2 class="notif-title">Správa Emailů & SMS</h2>
        <p class="notif-subtitle">Editace šablon, nastavení příjemců a správa automatických notifikací</p>
      </div>

      <!-- Card Grid -->
      <div class="notif-card-grid">

        <!-- Email šablony -->
        <div class="notif-card" onclick="openNotifModal('email-templates')">
          <div class="notif-card-title">Email šablony</div>
          <div class="notif-card-description">Editace šablon pro automatické emaily (nová reklamace, změna stavu, dokončení)</div>
          <div class="notif-card-meta">
            <span class="notif-card-badge active">5 šablon</span>
          </div>
        </div>

        <!-- SMS šablony -->
        <div class="notif-card" onclick="openNotifModal('sms-templates')">
          <div class="notif-card-title">SMS šablony</div>
          <div class="notif-card-description">Nastavení SMS notifikací pro zákazníky a techniky</div>
          <div class="notif-card-meta">
            <span class="notif-card-badge active">3 šablony</span>
          </div>
        </div>

        <!-- Příjemci emailů -->
        <div class="notif-card" onclick="openNotifModal('email-recipients')">
          <div class="notif-card-title">Příjemci emailů</div>
          <div class="notif-card-description">Správa seznamu příjemců pro různé typy notifikací</div>
          <div class="notif-card-meta">
            <span class="notif-card-badge">Administrátoři</span>
          </div>
        </div>

        <!-- Automatické notifikace -->
        <div class="notif-card" onclick="openNotifModal('auto-notifications')">
          <div class="notif-card-title">Automatické notifikace</div>
          <div class="notif-card-description">Nastavení pravidel pro automatické odesílání emailů a SMS</div>
          <div class="notif-card-meta">
            <span class="notif-card-badge active">Aktivní</span>
          </div>
        </div>

        <!-- SMTP nastavení -->
        <div class="notif-card" onclick="openNotifModal('smtp-settings')">
          <div class="notif-card-title">SMTP nastavení</div>
          <div class="notif-card-description">Konfigurace SMTP serveru pro odesílání emailů</div>
          <div class="notif-card-meta">
            <span class="notif-card-badge active">Nakonfigurováno</span>
          </div>
        </div>

        <!-- SMS gateway -->
        <div class="notif-card" onclick="openNotifModal('sms-gateway')">
          <div class="notif-card-title">SMS Gateway</div>
          <div class="notif-card-description">Nastavení SMS brány a API klíčů pro odesílání SMS</div>
          <div class="notif-card-meta">
            <span class="notif-card-badge inactive">Neaktivní</span>
          </div>
        </div>

        <!-- Historie notifikací -->
        <div class="notif-card" onclick="openNotifModal('notification-history')">
          <div class="notif-card-title">Historie notifikací</div>
          <div class="notif-card-description">Přehled odeslaných emailů a SMS s detaily doručení</div>
          <div class="notif-card-meta">
            <span class="notif-card-badge">Poslední 30 dní</span>
          </div>
        </div>

        <!-- Test odesílání -->
        <div class="notif-card" onclick="openNotifModal('test-sending')">
          <div class="notif-card-title">Test odesílání</div>
          <div class="notif-card-description">Otestujte funkčnost email a SMS notifikací</div>
          <div class="notif-card-meta">
            <span class="notif-card-badge">Nástroje</span>
          </div>
        </div>

      </div>

      <!-- Hidden container for admin-notifications.js to load real data -->
      <div id="notifications-container" style="display: none;">
        <div class="loading">Načítání notifikací...</div>
      </div>

    </div>

    <!-- MODAL OVERLAY -->
    <div class="cc-modal-overlay" id="notifModalOverlay" onclick="closeNotifModal()">
        <div class="cc-modal" onclick="event.stopPropagation()">
            <div class="cc-modal-header">
                <h2 class="cc-modal-title" id="notifModalTitle">Notifikace</h2>
                <button class="cc-modal-close" onclick="closeNotifModal()">×</button>
            </div>
            <div class="cc-modal-body" id="notifModalBody">
                <!-- Obsah se načte dynamicky -->
            </div>
        </div>
    </div>

    <script>
    // Modal systém pro notifikace
        /**
     * OpenNotifModal
     */
function openNotifModal(type) {
        const overlay = document.getElementById('notifModalOverlay');
        const modal = overlay.querySelector('.cc-modal');
        const title = document.getElementById('notifModalTitle');
        const body = document.getElementById('notifModalBody');

        // Nastavit title
        const titles = {
            'email-templates': 'Email šablony',
            'sms-templates': 'SMS šablony',
            'email-recipients': 'Příjemci emailů',
            'auto-notifications': 'Automatické notifikace',
            'smtp-settings': 'SMTP nastavení',
            'sms-gateway': 'SMS Gateway',
            'notification-history': 'Historie notifikací',
            'test-sending': 'Test odesílání'
        };

        title.textContent = titles[type] || 'Notifikace';

        // Načíst obsah podle typu
        loadNotifContent(type, body);

        // Zobrazit modal - add active classes
        overlay.classList.add('active');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

        /**
     * CloseNotifModal
     */
function closeNotifModal() {
        const overlay = document.getElementById('notifModalOverlay');
        const modal = overlay.querySelector('.cc-modal');

        // Skrýt modal - remove active classes
        overlay.classList.remove('active');
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }

        /**
     * LoadNotifContent
     */
function loadNotifContent(type, body) {
        // Zobrazit loading
        body.innerHTML = '<div style="text-align: center; padding: 2rem; color: #666;">Načítání...</div>';

        // Pro email-templates zkusit použít reálná data z notifications-container
        if (type === 'email-templates') {
            const realContainer = document.getElementById('notifications-container');
            if (realContainer && realContainer.innerHTML && !realContainer.innerHTML.includes('Načítání')) {
                // Bezpečně klonovat obsah (ochrana před XSS)
                body.innerHTML = '';
                const wrapper = document.createElement('div');
                wrapper.style.padding = '1rem';
                const clonedContent = realContainer.cloneNode(true);
                wrapper.appendChild(clonedContent);
                body.appendChild(wrapper);
                return;
            }
        }

        // Podle typu načíst různý obsah
        const content = {
            'email-templates': `
                <div style="padding: 1rem;">
                    <p style="margin-bottom: 1rem; color: #666; font-size: 0.85rem;">Editace email šablon pro automatické notifikace</p>
                    <div id="notifications-container-clone"></div>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <div style="border: 1px solid #e0e0e0; border-radius: 6px; padding: 1rem;">
                            <div style="font-weight: 600; margin-bottom: 0.5rem;">Nová reklamace</div>
                            <div style="font-size: 0.8rem; color: #666; margin-bottom: 0.5rem;">Odesílá se při vytvoření nové reklamace</div>
                            <button class="btn btn-sm" style="font-size: 0.7rem;">Editovat šablonu</button>
                        </div>
                        <div style="border: 1px solid #e0e0e0; border-radius: 6px; padding: 1rem;">
                            <div style="font-weight: 600; margin-bottom: 0.5rem;">Změna stavu</div>
                            <div style="font-size: 0.8rem; color: #666; margin-bottom: 0.5rem;">Odesílá se při změně stavu reklamace</div>
                            <button class="btn btn-sm" style="font-size: 0.7rem;">Editovat šablonu</button>
                        </div>
                        <div style="border: 1px solid #e0e0e0; border-radius: 6px; padding: 1rem;">
                            <div style="font-weight: 600; margin-bottom: 0.5rem;">Dokončení reklamace</div>
                            <div style="font-size: 0.8rem; color: #666; margin-bottom: 0.5rem;">Odesílá se po dokončení reklamace</div>
                            <button class="btn btn-sm" style="font-size: 0.7rem;">Editovat šablonu</button>
                        </div>
                    </div>
                </div>
            `,
            'sms-templates': `
                <div style="padding: 1rem;">
                    <p style="margin-bottom: 1rem; color: #666; font-size: 0.85rem;">Nastavení SMS šablon pro notifikace</p>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <div style="border: 1px solid #e0e0e0; border-radius: 6px; padding: 1rem;">
                            <div style="font-weight: 600; margin-bottom: 0.5rem;">SMS pro zákazníka</div>
                            <div style="font-size: 0.8rem; color: #666; margin-bottom: 0.5rem;">Text: "Vaše reklamace {cislo} byla přijata"</div>
                            <button class="btn btn-sm" style="font-size: 0.7rem;">Editovat</button>
                        </div>
                        <div style="border: 1px solid #e0e0e0; border-radius: 6px; padding: 1rem;">
                            <div style="font-weight: 600; margin-bottom: 0.5rem;">SMS pro technika</div>
                            <div style="font-size: 0.8rem; color: #666; margin-bottom: 0.5rem;">Text: "Nová zakázka {cislo} - {mesto}"</div>
                            <button class="btn btn-sm" style="font-size: 0.7rem;">Editovat</button>
                        </div>
                    </div>
                </div>
            `,
            'email-recipients': `
                <div style="padding: 1rem;">
                    <p style="margin-bottom: 1rem; color: #666; font-size: 0.85rem;">Správa příjemců automatických notifikací</p>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.8rem; margin-bottom: 0.5rem; font-weight: 600;">Administrátoři</label>
                        <input type="text" placeholder="admin@example.com, admin2@example.com" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.8rem; margin-bottom: 0.5rem; font-weight: 600;">Kopie všech emailů</label>
                        <input type="text" placeholder="office@example.com" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                    </div>
                    <button class="btn btn-sm btn-success" style="font-size: 0.7rem;">Uložit změny</button>
                </div>
            `,
            'auto-notifications': `
                <div style="padding: 1rem;">
                    <p style="margin-bottom: 1rem; color: #666; font-size: 0.85rem;">Nastavení pravidel pro automatické odesílání</p>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem; border: 1px solid #e0e0e0; border-radius: 6px;">
                            <input type="checkbox" checked>
                            <span style="font-size: 0.85rem;">Odeslat email při vytvoření nové reklamace</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem; border: 1px solid #e0e0e0; border-radius: 6px;">
                            <input type="checkbox" checked>
                            <span style="font-size: 0.85rem;">Odeslat SMS zákazníkovi při změně stavu</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem; border: 1px solid #e0e0e0; border-radius: 6px;">
                            <input type="checkbox">
                            <span style="font-size: 0.85rem;">Denní report pro administrátory (8:00)</span>
                        </label>
                    </div>
                    <div style="margin-top: 1rem;">
                        <button class="btn btn-sm btn-success" style="font-size: 0.7rem;">Uložit nastavení</button>
                    </div>
                </div>
            `,
            'smtp-settings': `
                <div style="padding: 1rem;">
                    <p style="margin-bottom: 1rem; color: #666; font-size: 0.85rem;">Konfigurace SMTP serveru</p>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <div>
                            <label style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem; color: #666;">SMTP Server *</label>
                            <input type="text" id="smtp_host" placeholder="smtp.gmail.com" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                            <div>
                                <label style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem; color: #666;">Port</label>
                                <input type="text" id="smtp_port" placeholder="587" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem; color: #666;">Šifrování</label>
                                <select id="smtp_encryption" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="none">Žádné</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem; color: #666;">Uživatelské jméno *</label>
                            <input type="text" id="smtp_username" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem; color: #666;">Heslo *</label>
                            <input type="password" id="smtp_password" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem; color: #666;">FROM Email</label>
                            <input type="email" id="smtp_from" placeholder="reklamace@wgs-service.cz" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem; color: #666;">FROM Name</label>
                            <input type="text" id="smtp_from_name" placeholder="White Glove Service" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                        </div>
                    </div>
                    <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                        <button class="btn btn-sm" id="testSmtpBtn" onclick="testSmtpConnection()" style="font-size: 0.7rem;">Test připojení</button>
                        <button class="btn btn-sm btn-success" id="saveSmtpBtn" onclick="saveSmtpConfig()" style="font-size: 0.7rem;">Uložit</button>
                    </div>
                </div>
            `,
            'sms-gateway': `
                <div style="padding: 1rem;">
                    <p style="margin-bottom: 1rem; color: #666; font-size: 0.85rem;">Nastavení SMS brány</p>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <div>
                            <label style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem; color: #666;">Poskytovatel</label>
                            <select style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                                <option>Twilio</option>
                                <option>Nexmo</option>
                                <option>SMS.cz</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem; color: #666;">API klíč</label>
                            <input type="text" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem; color: #666;">API Secret</label>
                            <input type="password" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                        </div>
                    </div>
                    <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                        <button class="btn btn-sm" style="font-size: 0.7rem;">Test SMS</button>
                        <button class="btn btn-sm btn-success" style="font-size: 0.7rem;">Uložit</button>
                    </div>
                </div>
            `,
            'notification-history': `
                <div style="padding: 1rem;">
                    <p style="margin-bottom: 1rem; color: #666; font-size: 0.85rem;">Historie odeslaných notifikací (poslední 30 dní)</p>
                    <table class="cc-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th style="font-size: 0.75rem;">Datum</th>
                                <th style="font-size: 0.75rem;">Typ</th>
                                <th style="font-size: 0.75rem;">Příjemce</th>
                                <th style="font-size: 0.75rem;">Předmět</th>
                                <th style="font-size: 0.75rem;">Stav</th>
                            </tr>
                        </thead>
                        <tbody style="font-size: 0.75rem;">
                            <tr><td colspan="5" style="text-align: center; color: #999; padding: 2rem;">Načítání historie...</td></tr>
                        </tbody>
                    </table>
                </div>
            `,
            'test-sending': `
                <div style="padding: 1rem;">
                    <p style="margin-bottom: 1rem; color: #666; font-size: 0.85rem;">Otestujte funkčnost notifikací</p>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div style="border: 1px solid #e0e0e0; border-radius: 6px; padding: 1rem;">
                            <h3 style="font-size: 0.9rem; margin-bottom: 0.75rem;">Test Email</h3>
                            <input type="email" placeholder="Zadejte testovací email" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem; margin-bottom: 0.5rem;">
                            <button class="btn btn-sm btn-success" style="font-size: 0.7rem;">Odeslat testovací email</button>
                        </div>
                        <div style="border: 1px solid #e0e0e0; border-radius: 6px; padding: 1rem;">
                            <h3 style="font-size: 0.9rem; margin-bottom: 0.75rem;">Test SMS</h3>
                            <input type="tel" placeholder="Zadejte telefonní číslo (+420...)" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem; margin-bottom: 0.5rem;">
                            <button class="btn btn-sm btn-success" style="font-size: 0.7rem;">Odeslat testovací SMS</button>
                        </div>
                    </div>
                </div>
            `
        };

        // Nastavit obsah (sanitizace pro XSS ochranu)
        setTimeout(() => {
            const htmlContent = content[type] || '<p>Obsah nebyl nalezen</p>';
            // Použij sanitizeHTML pro ochranu před XSS
            body.innerHTML = typeof sanitizeHTML === 'function' ? sanitizeHTML(htmlContent) : htmlContent;

            // Pro SMTP nastavení načíst data z databáze
            if (type === 'smtp-settings' && typeof loadSmtpConfig === 'function') {
                setTimeout(() => loadSmtpConfig(), 100);
            }
        }, 300);
    }

    // ESC key zavře modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeNotifModal();
        }
    });
    </script>

  </div>
  <?php endif; ?>
  
  <?php if ($activeTab === 'keys'): ?>
  <!-- TAB: KEYS -->
  <div id="tab-keys" class="tab-content">
    <div class="table-container">
      <div class="table-header">
        <h3 class="table-title">Registrační klíče</h3>
        <div class="table-actions">
          <button class="btn btn-sm btn-success" id="createKeyBtn">+ Nový klíč</button>
          <button class="btn btn-sm" id="refreshKeysBtn">Obnovit</button>
        </div>
      </div>
      
      <div style="padding: 1.5rem;" id="keys-container">
        <div class="loading">Načítání klíčů...</div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  
  <?php if ($activeTab === 'users'): ?>
  <!-- TAB: USERS -->
  <div id="tab-users" class="tab-content">
    <div class="table-container">
      <div class="table-header">
        <h3 class="table-title">Všichni uživatelé</h3>
        <div class="table-actions">
          <input type="text" class="search-box" id="search-users" placeholder="Hledat...">
          <button class="btn btn-sm btn-success" id="addUserBtn">Přidat</button>
          <button class="btn btn-sm" id="refreshUsersBtn">Obnovit</button>
        </div>
      </div>
      
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Jméno</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Registrace</th>
            <th>Akce</th>
          </tr>
        </thead>
        <tbody id="users-table">
          <tr>
            <td colspan="7" class="loading">Načítání...</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($activeTab === 'control_center'): ?>
  <!-- TAB: CONTROL CENTER - Unified accordion interface -->
  <?php require_once __DIR__ . '/includes/control_center_unified.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'control_center_testing'): ?>
  <!-- TAB: TESTING ENVIRONMENT (OLD) -->
  <?php require_once __DIR__ . '/includes/control_center_testing.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'control_center_testing_interactive'): ?>
  <!-- TAB: INTERACTIVE TESTING ENVIRONMENT -->
  <?php require_once __DIR__ . '/includes/control_center_testing_interactive.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'control_center_testing_simulator'): ?>
  <!-- TAB: E2E WORKFLOW SIMULATOR -->
  <?php require_once __DIR__ . '/includes/control_center_testing_simulator.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'control_center_appearance'): ?>
  <!-- TAB: VZHLED & DESIGN -->
  <?php require_once __DIR__ . '/includes/control_center_appearance.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'control_center_content'): ?>
  <!-- TAB: OBSAH & TEXTY -->
  <?php require_once __DIR__ . '/includes/control_center_content.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'control_center_console'): ?>
  <!-- TAB: KONZOLE -->
  <?php require_once __DIR__ . '/includes/control_center_console.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'control_center_actions'): ?>
  <!-- TAB: AKCE & ÚKOLY -->
  <?php require_once __DIR__ . '/includes/control_center_actions.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'control_center_configuration'): ?>
  <!-- TAB: KONFIGURACE SYSTÉMU -->
  <?php require_once __DIR__ . '/includes/control_center_configuration.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'tools'): ?>
  <!-- TAB: TOOLS & DIAGNOSTICS -->
  <div id="tab-tools" class="tab-content">
  <?php require_once __DIR__ . '/includes/control_center_tools.php'; ?>
  </div>
  <?php endif; ?>

  <?php if ($activeTab === 'online'): ?>
  <!-- TAB: ONLINE -->
  <div id="tab-online" class="tab-content">
    <div class="table-container">
      <div class="table-header">
        <h3 class="table-title">Online uživatelé</h3>
        <div class="table-actions">
          <button class="btn btn-sm" id="refreshOnlineBtn">Obnovit</button>
        </div>
      </div>
      
      <table>
        <thead>
          <tr>
            <th>Status</th>
            <th>Uživatel</th>
            <th>Role</th>
            <th>Email</th>
            <th>Poslední aktivita</th>
          </tr>
        </thead>
        <tbody id="online-table">
          <tr>
            <td colspan="5" class="loading">Načítání...</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

</div>
</main>

<!-- MODAL: Add User -->
<div class="modal" id="addUserModal">
  <div class="modal-content" style="width: 90vw !important; height: 80vh !important; max-width: 90vw !important; max-height: 80vh !important; display: flex; flex-direction: column;">
    <div class="modal-header" style="padding: 1.5rem 2rem; border-bottom: 1px solid #ddd; flex-shrink: 0;">
      <h3 class="modal-title">Přidat uživatele</h3>
      <button class="modal-close" id="closeModalBtn">×</button>
    </div>
    <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 2rem;">
      <div id="modal-error" class="error-message hidden"></div>
      
      <div class="form-group">
        <label class="form-label">Jméno *</label>
        <input type="text" class="form-input" id="add-name" required minlength="2">
      </div>

      <div class="form-group">
        <label class="form-label">Email *</label>
        <input type="email" class="form-input" id="add-email" required>
      </div>

      <div class="form-group">
        <label class="form-label">Telefon</label>
        <input type="tel" class="form-input" id="add-phone">
      </div>

      <div class="form-group">
        <label class="form-label">Adresa</label>
        <input type="text" class="form-input" id="add-address">
      </div>

      <div class="form-group">
        <label class="form-label">Role *</label>
        <select class="form-select" id="add-role" required>
          <option value="prodejce">Prodejce</option>
          <option value="technik">Technik</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Heslo * (min. 8 znaků)</label>
        <input type="password" class="form-input" id="add-password" required minlength="8">
      </div>
    </div>
    <div class="modal-footer" style="padding: 1.5rem 2rem; border-top: 1px solid #ddd; flex-shrink: 0;">
      <button class="btn" id="cancelModalBtn">Zrušit</button>
      <button class="btn btn-success" id="submitUserBtn">Přidat</button>
    </div>
  </div>
</div>

<!-- External JavaScript -->
<script src="assets/js/logger.js"></script>
<script src="assets/js/csrf-auto-inject.js"></script>
<script src="assets/js/utils.js"></script>
<script src="assets/js/admin-notifications.js"></script>
<script src="assets/js/smtp-config.js"></script>
<script src="assets/js/admin.js"></script>

<!-- MODAL: Edit Notification -->
<div class="wgs-modal" id="editNotificationModal" style="display: none;">
  <div class="modal-content" style="width: 1200px; max-width: 90vw; height: 80vh; display: flex; flex-direction: column; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
    <div class="modal-header" style="padding: 1.5rem 2rem; border-bottom: 1px solid #ddd; flex-shrink: 0;">
      <h3 class="modal-title" id="editNotificationTitle">Editovat notifikaci</h3>
      <button class="modal-close" onclick="closeEditNotificationModal()">×</button>
    </div>
    <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 2rem;">
      <div id="edit-notification-error" class="error-message" style="display: none;"></div>
      <div id="edit-notification-success" class="success-message" style="display: none;"></div>
      <div class="form-group">
        <label class="form-label">Příjemce</label>
        <select class="form-select" id="edit-recipient">
          <option value="customer">Zákazník</option>
          <option value="admin">Admin</option>
          <option value="technician">Technik</option>
          <option value="seller">Prodejce</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Předmět (pouze email)</label>
        <input type="text" class="form-input" id="edit-subject">
      </div>
      <div class="form-group">
        <label class="form-label">Šablona zprávy</label>
        <div style="margin-bottom: 0.5rem; font-size: 0.85rem; color: #666;"><strong>Dostupné proměnné:</strong>
          <div id="available-variables" style="display: flex; flex-wrap: wrap; gap: 0.3rem; margin-top: 0.3rem;"></div>
        </div>
        <textarea class="form-input" id="edit-template" rows="8"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Náhled</label>
        <div id="template-preview" style="background: #f5f5f5; padding: 1rem; border: 1px solid #ddd; white-space: pre-wrap; font-family: monospace; font-size: 0.9rem;">Začněte psát...</div>
      </div>
      <div class="form-group">
        <label class="form-label">Dodatečné kopie emailů (CC)</label>
        <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
          <input type="email" class="form-input" id="new-cc-email" placeholder="novy@email.cz" style="flex: 1;">
          <button class="btn btn-sm" onclick="addCCEmail()">+ Přidat</button>
        </div>
        <div id="cc-emails-list" style="display: flex; flex-wrap: wrap; gap: 0.5rem;"></div>
      </div>
      <div class="form-group">
        <label class="form-label">Skryté kopie (BCC)</label>
        <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
          <input type="email" class="form-input" id="new-bcc-email" placeholder="skryta@email.cz" style="flex: 1;">
          <button class="btn btn-sm" onclick="addBCCEmail()">+ Přidat</button>
        </div>
        <div id="bcc-emails-list" style="display: flex; flex-wrap: wrap; gap: 0.5rem;"></div>
      </div>
    </div>
    <div class="modal-footer" style="padding: 1.5rem 2rem; border-top: 1px solid #ddd; flex-shrink: 0;">
      <button class="btn" onclick="closeEditNotificationModal()">Zrušit</button>
      <button class="btn btn-success" onclick="saveNotificationTemplate()">Uložit</button>
    </div>
  </div>
</div>
</body>
</html>
