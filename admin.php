<?php
require_once "init.php";
require_once __DIR__ . '/includes/admin_navigation.php';

// Definovat konstantu pro include soubory
define('ADMIN_PHP_LOADED', true);

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
        "script-src 'self' 'unsafe-inline' https://unpkg.com; " .
        "style-src 'self' 'unsafe-inline' https://unpkg.com; " .
        "font-src 'self'; " .
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
$activeTab = $_GET['tab'] ?? 'dashboard';

// CLEAN URL: Admin dashboard je výchozí stránka
if (isset($_GET['tab']) && $_GET['tab'] === 'dashboard' && !$embedMode) {
    header('Location: admin.php', true, 301); // 301 Permanent Redirect
    exit;
}

if (!array_key_exists($activeTab, $tabConfig)) {
    $activeTab = 'dashboard';
}
$currentTabMeta = $tabConfig[$activeTab];
$currentTabLabel = $currentTabMeta['tab_label'] ?? 'Admin';

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

// Získání statistik pro dashboard
$activeKeys = 0;
$pendingActions = 0;
$pocetNeuspesnychPrihlaseniDnes = 0;

if ($activeTab === 'dashboard') {
    try {
        $pdo = getDbConnection();

        $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_registration_keys WHERE is_active = 1");
        $activeKeys = $stmt->fetchColumn();

        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_pending_actions WHERE status = 'pending'");
            $pendingActions = $stmt->fetchColumn();
        } catch (Exception $e) {
            $pendingActions = 0;
        }
    } catch (Exception $e) {
        $activeKeys = 0;
        $pendingActions = 0;
    }

    // Počet neúspěšných přihlášení dnes z audit logu
    try {
        $logDir = defined('LOGS_PATH') ? LOGS_PATH : __DIR__ . '/logs';
        $logSoubor = $logDir . '/audit_' . date('Y-m') . '.log';
        $dnesRano = date('Y-m-d') . ' 00:00:00';
        if (file_exists($logSoubor)) {
            $radky = file($logSoubor, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($radky as $radek) {
                $z = json_decode($radek, true);
                if ($z && $z['action'] === 'failed_login' && isset($z['timestamp']) && $z['timestamp'] >= $dnesRano) {
                    $pocetNeuspesnychPrihlaseniDnes++;
                }
            }
        }
    } catch (Exception $e) {
        $pocetNeuspesnychPrihlaseniDnes = 0;
    }
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
  <link rel="preload" href="/assets/css/styles.min.css?v=<?= filemtime(__DIR__ . '/assets/css/styles.min.css') ?>" as="style">
  <link rel="preload" href="/assets/css/admin.min.css?v=<?= filemtime(__DIR__ . '/assets/css/admin.min.css') ?>" as="style">

  <!-- Google Fonts -->

    <!-- External CSS -->
    <!-- Unified Design System -->
  <link rel="stylesheet" href="/assets/css/styles.min.css?v=<?= filemtime(__DIR__ . '/assets/css/styles.min.css') ?>">
  <link rel="stylesheet" href="/assets/css/admin.min.css?v=<?= filemtime(__DIR__ . '/assets/css/admin.min.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-header.min.css?v=<?= filemtime(__DIR__ . '/assets/css/admin-header.min.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-notifications.min.css?v=<?= filemtime(__DIR__ . '/assets/css/admin-notifications.min.css') ?>">
  <link rel="stylesheet" href="/assets/css/mobile-responsive.min.css">
  <!-- admin-mobile-fixes.css sloučen do admin.css (Step 51) -->
  <link rel="stylesheet" href="/assets/css/button-fixes-global.min.css">
  <!-- Univerzální tmavý styl pro všechny modály -->
  <link rel="stylesheet" href="/assets/css/universal-modal-theme.min.css">
  <!-- PWA optimalizace pro admin -->
  <link rel="stylesheet" href="/assets/css/admin-pwa.min.css">

  <!-- Error Handler - zachytává všechny chyby -->
  <script src="/assets/js/error-handler.min.js"></script>
  <script src="/assets/js/html-sanitizer.min.js"></script>
  <link rel="stylesheet" href="assets/css/poppins-font.css">
</head>

<body<?php
    $bodyClasses = [];
    if ($embedMode) $bodyClasses[] = 'embed-mode';
    if (!empty($bodyClasses)) echo ' class="' . implode(' ', $bodyClasses) . '"';
?>>

<?php if (!$embedMode): ?>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>
<?php endif; ?>

<!-- CSRF Token pro API volání -->
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

<?php
// Dashboard - kartový systém
if (!$embedMode && $activeTab === 'dashboard'):
?>
<div class="admin-dashboard-wrapper">
    <div class="admin-dashboard-header">
        <h1 class="admin-dashboard-title" data-lang-cs="WGS Admin Panel" data-lang-en="WGS Admin Panel" data-lang-it="Pannello Admin WGS">WGS Admin Panel</h1>
        <p class="admin-dashboard-subtitle" data-lang-cs="Centrální řídicí panel pro správu celé aplikace" data-lang-en="Central control panel for managing the entire application" data-lang-it="Pannello di controllo centrale per la gestione dell'intera applicazione">Centrální řídicí panel pro správu celé aplikace</p>
        <div class="admin-dashboard-actions">
            <span class="admin-version-info" id="adminVersionInfo" title="Verze Admin - čas poslední úpravy">v<?= date('Y.m.d-Hi', filemtime(__FILE__)) ?></span>
            <button class="admin-cache-btn" data-action="clearCacheAndReload" data-lang-cs="Vymazat cache & Reload" data-lang-en="Clear cache & Reload" data-lang-it="Cancella cache & Ricarica">Vymazat cache & Reload</button>
        </div>
    </div>

    <div class="cc-seznam">
        <?php if ($pocetNeuspesnychPrihlaseniDnes >= 5): ?>
        <div class="cc-radek cc-radek-varovani" data-href="admin.php?tab=tools" style="border-left:3px solid #dc3545;">
            <div class="cc-radek-info">
                <div class="cc-radek-nazev" style="color:#dc3545;">Bezpečnostní upozornění</div>
                <div class="cc-radek-popis">Dnes bylo zaznamenáno <?= $pocetNeuspesnychPrihlaseniDnes ?> neúspěšných pokusů o přihlášení — zobrazit detail v Diagnostice</div>
            </div>
            <div class="cc-radek-badge" style="background:#dc3545;"><?= $pocetNeuspesnychPrihlaseniDnes ?></div>
        </div>
        <?php endif; ?>
        <div class="cc-radek" data-href="admin.php?tab=zakaznici">
            <div class="cc-radek-info">
                <div class="cc-radek-nazev" data-lang-cs="Seznam zákazníků" data-lang-en="Customer List" data-lang-it="Elenco Clienti">Seznam zákazníků</div>
                <div class="cc-radek-popis" data-lang-cs="Přehled všech zákazníků s kontaktními údaji a zakázkami" data-lang-en="Overview of all customers with contact information and orders" data-lang-it="Panoramica di tutti i clienti con informazioni di contatto e ordini">Přehled všech zákazníků s kontaktními údaji a zakázkami</div>
            </div>
        </div>

        <div class="cc-radek" data-href="admin.php?tab=keys">
            <div class="cc-radek-info">
                <div class="cc-radek-nazev" data-lang-cs="Bezpečnost & Klíče" data-lang-en="Security & Keys" data-lang-it="Sicurezza & Chiavi">Bezpečnost & Klíče</div>
                <div class="cc-radek-popis" data-lang-cs="Registrační klíče, API klíče, bezpečnostní nastavení" data-lang-en="Registration keys, API keys, security settings" data-lang-it="Chiavi di registrazione, chiavi API, impostazioni di sicurezza">Registrační klíče, API klíče, bezpečnostní nastavení</div>
            </div>
            <?php if ($activeKeys > 0): ?>
                <div class="cc-radek-badge"><?= $activeKeys ?></div>
            <?php endif; ?>
        </div>

        <div class="cc-radek" data-href="admin.php?tab=notifications">
            <div class="cc-radek-info">
                <div class="cc-radek-nazev" data-lang-cs="Email & SMS" data-lang-en="Email & SMS" data-lang-it="Email & SMS">Email & SMS</div>
                <div class="cc-radek-popis" data-lang-cs="Správa emailových a SMS notifikací" data-lang-en="Email and SMS notification management" data-lang-it="Gestione notifiche email e SMS">Správa emailových a SMS notifikací</div>
            </div>
        </div>

        <div class="cc-radek" data-href="psa-kalkulator.php">
            <div class="cc-radek-info">
                <div class="cc-radek-nazev" data-lang-cs="PSA Kalkulátor" data-lang-en="PSA Calculator" data-lang-it="Calcolatore PSA">PSA Kalkulátor</div>
                <div class="cc-radek-popis" data-lang-cs="Výpočet mezd a docházky zaměstnanců" data-lang-en="Employee salary and attendance calculation" data-lang-it="Calcolo stipendi e presenze dipendenti">Výpočet mezd a docházky zaměstnanců</div>
            </div>
        </div>

        <div class="cc-radek" data-href="admin.php?tab=admin_console">
            <div class="cc-radek-info">
                <div class="cc-radek-nazev" data-lang-cs="Konzole" data-lang-en="Console" data-lang-it="Console">Konzole</div>
                <div class="cc-radek-popis" data-lang-cs="Diagnostika HTML/PHP/JS/CSS/SQL" data-lang-en="Diagnostics HTML/PHP/JS/CSS/SQL" data-lang-it="Diagnostica HTML/PHP/JS/CSS/SQL">Diagnostika HTML/PHP/JS/CSS/SQL</div>
            </div>
        </div>

        <div class="cc-radek" data-action="openSQLPage">
            <div class="cc-radek-info">
                <div class="cc-radek-nazev" data-lang-cs="SQL Databáze" data-lang-en="SQL Database" data-lang-it="Database SQL">SQL Databáze</div>
                <div class="cc-radek-popis" data-lang-cs="Zobrazit všechny SQL tabulky (aktuální živá data)" data-lang-en="View all SQL tables (current live data)" data-lang-it="Visualizza tutte le tabelle SQL (dati live attuali)">Zobrazit všechny SQL tabulky (aktuální živá data)</div>
            </div>
        </div>

        <div class="cc-radek" data-href="api-docs.php" data-target="_blank">
            <div class="cc-radek-info">
                <div class="cc-radek-nazev" data-lang-cs="API Dokumentace" data-lang-en="API Documentation" data-lang-it="Documentazione API">API Dokumentace</div>
                <div class="cc-radek-popis" data-lang-cs="Přehled všech API endpointů, akcí a autorizace (63 endpointů)" data-lang-en="Overview of all API endpoints, actions and authorization (63 endpoints)" data-lang-it="Panoramica di tutti gli endpoint API, azioni e autorizzazione (63 endpoint)">Přehled všech API endpointů, akcí a autorizace (63 endpointů)</div>
            </div>
        </div>

        <div class="cc-radek" data-href="admin.php?tab=admin_soubory">
            <div class="cc-radek-info">
                <div class="cc-radek-nazev" data-lang-cs="ROOT Soubory" data-lang-en="ROOT Files" data-lang-it="File ROOT">ROOT Soubory</div>
                <div class="cc-radek-popis" data-lang-cs="Přehled všech souborů, závislostí, využití a správa ke smazání" data-lang-en="Overview of all files, dependencies, usage and deletion management" data-lang-it="Panoramica di tutti i file, dipendenze, utilizzo e gestione eliminazione">Přehled všech souborů, závislostí, využití a správa ke smazání</div>
            </div>
        </div>

        <div class="cc-radek" data-href="transport.php">
            <div class="cc-radek-info">
                <div class="cc-radek-nazev" data-lang-cs="Transport" data-lang-en="Transport" data-lang-it="Trasporto">Transport</div>
                <div class="cc-radek-popis" data-lang-cs="Správa transportů a řidičů" data-lang-en="Transport and driver management" data-lang-it="Gestione trasporti e autisti">Správa transportů a řidičů</div>
            </div>
        </div>
        <div class="cc-radek" data-href="admin.php?tab=audit">
            <div class="cc-radek-info">
                <div class="cc-radek-nazev" data-lang-cs="Audit projektu" data-lang-en="Project Audit" data-lang-it="Audit del Progetto">Audit projektu</div>
                <div class="cc-radek-popis" data-lang-cs="Detailní audit WGS — struktura, bezpečnost, hodnota, doporučení" data-lang-en="Detailed WGS audit — structure, security, value, recommendations" data-lang-it="Audit dettagliato WGS — struttura, sicurezza, valore, raccomandazioni">Detailní audit WGS — struktura, bezpečnost, hodnota, doporučení</div>
            </div>
        </div>
        <div class="cc-radek" data-href="admin.php?tab=tools">
            <div class="cc-radek-info">
                <div class="cc-radek-nazev" data-lang-cs="Diagnostika" data-lang-en="Diagnostics" data-lang-it="Diagnostica">Diagnostika</div>
                <div class="cc-radek-popis" data-lang-cs="Zdraví systému, logy, neúspěšná přihlášení, výkon" data-lang-en="System health, logs, failed logins, performance" data-lang-it="Salute del sistema, log, accessi falliti, prestazioni">Zdraví systému, logy, neúspěšná přihlášení, výkon</div>
            </div>
        </div>
        <div class="cc-radek" data-href="dnes.php">
            <div class="cc-radek-info">
                <div class="cc-radek-nazev" data-lang-cs="Denní přehled" data-lang-en="Daily Overview" data-lang-it="Riepilogo Giornaliero">Denní přehled</div>
                <div class="cc-radek-popis" data-lang-cs="Aktivní zakázky technika — termíny, kontakty, skupiny stavů" data-lang-en="Technician active jobs — deadlines, contacts, status groups" data-lang-it="Lavori attivi del tecnico — scadenze, contatti, gruppi">Aktivní zakázky technika — termíny, kontakty, skupiny stavů</div>
            </div>
        </div>
    </div>
</div>

<style>
/* Dashboard - radkovy vypis */
body {
    font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: #f5f5f7;
    color: #1a1a1a;
}

.admin-dashboard-wrapper {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.admin-dashboard-header {
    text-align: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #d1d1d6;
}

.admin-dashboard-title {
    font-family: 'Poppins', sans-serif;
    font-size: 2rem;
    font-weight: 600;
    color: #1a1a1a;
    margin: 0 0 0.5rem 0;
    letter-spacing: -0.01em;
}

.admin-dashboard-subtitle {
    font-family: 'Poppins', sans-serif;
    font-size: 0.95rem;
    font-weight: 400;
    color: #666;
    margin: 0 0 1rem 0;
}

.admin-dashboard-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    margin-top: 1rem;
}

.admin-version-info {
    font-family: 'Poppins', sans-serif;
    font-size: 0.75rem;
    color: #666;
    padding: 0.5rem 0.75rem;
    background: #fff;
    border: 1px solid #d1d1d6;
    border-radius: 6px;
    height: 36px;
    min-width: 150px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.admin-cache-btn {
    font-family: 'Poppins', sans-serif;
    background: #333;
    color: #fff;
    border: none;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    height: 36px;
    min-width: 150px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.admin-cache-btn:hover {
    background: #111;
}

/* Radkovy seznam */
.cc-seznam {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1px;
    background: #e5e5e5;
    border: 1px solid #e5e5e5;
    border-radius: 6px;
    overflow: hidden;
}

.cc-radek {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.9rem 1rem;
    background: #fff;
    cursor: pointer;
    transition: background 0.15s ease;
}

.cc-radek:first-child {
    border-top: none;
}

.cc-radek:hover {
    background: #f0f0f0;
}

.cc-radek-info {
    flex: 1;
    min-width: 0;
}

.cc-radek-nazev {
    font-family: 'Poppins', sans-serif;
    font-size: 0.9rem;
    font-weight: 600;
    color: #1a1a1a;
    line-height: 1.3;
}

.cc-radek-popis {
    font-family: 'Poppins', sans-serif;
    font-size: 0.75rem;
    color: #888;
    line-height: 1.3;
    margin-top: 0.15rem;
}

.cc-radek-badge {
    flex-shrink: 0;
    margin-left: 1rem;
    background: #dc3545;
    color: #fff;
    font-family: 'Poppins', sans-serif;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 10px;
    min-width: 20px;
    text-align: center;
}

/* Responsive */
@media (max-width: 768px) {
    .admin-dashboard-title {
        font-size: 1.5rem;
    }

    .admin-dashboard-subtitle {
        font-size: 0.85rem;
    }

    .admin-dashboard-actions {
        flex-direction: column;
        width: 100%;
    }

    .admin-version-info,
    .admin-cache-btn {
        width: 100%;
        max-width: 300px;
    }

    .cc-seznam {
        grid-template-columns: 1fr;
    }

    .cc-radek {
        padding: 0.75rem 0.75rem;
    }

    .cc-radek-nazev {
        font-size: 0.85rem;
    }

    .cc-radek-popis {
        font-size: 0.7rem;
    }
}
</style>
<?php endif; ?>

<?php if ($activeTab !== 'dashboard'): ?>
<!-- MAIN CONTENT -->
<main id="main-content">
<div class="container">

  <?php if (!$embedMode && !str_starts_with($activeTab, 'admin_') && $activeTab !== 'dashboard'): ?>
  <h1 class="page-title"><?php echo htmlspecialchars($currentTabLabel, ENT_QUOTES, 'UTF-8'); ?></h1>
  <p class="page-subtitle">Správa systému White Glove Service</p>
  <?php endif; ?>

  <?php if ($activeTab === 'dashboard'): ?>
  <!-- TAB: DASHBOARD -->
  <div id="tab-dashboard" class="tab-content">
    <!-- Dashboard statistics removed per user request -->
  </div>
  <?php endif; ?>
  
  <?php if ($activeTab === 'notifications'): ?>
  <!-- TAB: EMAIL & SMS MANAGEMENT -->
  <?php require_once __DIR__ . '/includes/admin_email_sms.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'notifications_old'): ?>
  <!-- STARÁ VERZE (backup) -->
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
        border-color: #333333;
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

        <!-- Email šablony - Step 53: HTMX migrace -->
        <div class="notif-card"
             hx-get="/api/notification_list_html.php?type=email"
             hx-target="#notifModalBody"
             hx-trigger="click"
             hx-on::before-request="document.getElementById('notifModalTitle').textContent = 'Email šablony'"
             hx-on::after-request="if(window.notifModal) window.notifModal.open()">
          <div class="notif-card-title">Email šablony</div>
          <div class="notif-card-description">Editace šablon pro automatické emaily (nová reklamace, změna stavu, dokončení)</div>
          <div class="notif-card-meta">
            <span class="notif-card-badge active">HTMX</span>
          </div>
        </div>

        <!-- SMS šablony - Step 54: HTMX migrace -->
        <div class="notif-card"
             hx-get="/api/notification_list_html.php?type=sms"
             hx-target="#notifModalBody"
             hx-trigger="click"
             hx-on::before-request="document.getElementById('notifModalTitle').textContent = 'SMS šablony'"
             hx-on::after-request="if(window.notifModal) window.notifModal.open()">
          <div class="notif-card-title">SMS šablony</div>
          <div class="notif-card-description">Nastavení SMS notifikací pro zákazníky a techniky</div>
          <div class="notif-card-meta">
            <span class="notif-card-badge active">HTMX</span>
          </div>
        </div>

        <!-- Příjemci emailů -->
        <div class="notif-card" data-action="openNotifModal" data-modal="email-recipients">
          <div class="notif-card-title">Příjemci emailů</div>
          <div class="notif-card-description">Správa seznamu příjemců pro různé typy notifikací</div>
          <div class="notif-card-meta">
            <span class="notif-card-badge">Administrátoři</span>
          </div>
        </div>

        <!-- Automatické notifikace -->
        <div class="notif-card" data-action="openNotifModal" data-modal="auto-notifications">
          <div class="notif-card-title">Automatické notifikace</div>
          <div class="notif-card-description">Nastavení pravidel pro automatické odesílání emailů a SMS</div>
          <div class="notif-card-meta">
            <span class="notif-card-badge active">Aktivní</span>
          </div>
        </div>

        <!-- SMTP nastavení -->
        <div class="notif-card" data-action="openNotifModal" data-modal="smtp-settings">
          <div class="notif-card-title">SMTP nastavení</div>
          <div class="notif-card-description">Konfigurace SMTP serveru pro odesílání emailů</div>
          <div class="notif-card-meta">
            <span class="notif-card-badge active">Nakonfigurováno</span>
          </div>
        </div>

        <!-- SMS gateway -->
        <div class="notif-card" data-action="openNotifModal" data-modal="sms-gateway">
          <div class="notif-card-title">SMS Gateway</div>
          <div class="notif-card-description">Nastavení SMS brány a API klíčů pro odesílání SMS</div>
          <div class="notif-card-meta">
            <span class="notif-card-badge inactive">Neaktivní</span>
          </div>
        </div>

        <!-- Email Management -->
        <div class="notif-card" data-href="email_management.php">
          <div class="notif-card-title">Email Management</div>
          <div class="notif-card-description">Kompletní správa emailů - historie, fronta, selhavší + možnost znovu odeslat</div>
          <div class="notif-card-meta">
            <span class="notif-card-badge">Historie + Fronta</span>
          </div>
        </div>

        <!-- Test odesílání -->
        <div class="notif-card" data-action="openNotifModal" data-modal="test-sending">
          <div class="notif-card-title">Test odesílání</div>
          <div class="notif-card-description">Otestujte funkčnost email a SMS notifikací</div>
          <div class="notif-card-meta">
            <span class="notif-card-badge">Nástroje</span>
          </div>
        </div>

      </div>

      <!-- Step 53: Hidden container DEPRECATED - HTMX nyní načítá přímo do modalu -->
      <!-- Ponecháno pro zpětnou kompatibilitu s ostatními modal typy -->
      <div id="notifications-container" style="display: none;">
        <div class="loading">Používejte HTMX endpoint</div>
      </div>

    </div>

    <!-- MODAL OVERLAY - Alpine.js (Step 44) -->
    <div class="cc-modal-overlay" id="notifModalOverlay" role="dialog" aria-modal="true" aria-labelledby="notifModalTitle"
         x-data="notifModal" x-init="init" @click="overlayClick">
        <div class="cc-modal" @click.stop>
            <div class="cc-modal-header">
                <h2 class="cc-modal-title" id="notifModalTitle">Notifikace</h2>
                <button class="cc-modal-close" @click="close" aria-label="Zavřít">×</button>
            </div>
            <div class="cc-modal-body" id="notifModalBody">
                <!-- Obsah se načte dynamicky -->
            </div>
        </div>
    </div>

    <script>
    // Modal systém pro notifikace
    // Step 44: Migrace na Alpine.js - close/overlay click/ESC nyní řeší notifModal komponenta
        /**
     * OpenNotifModal
     */
function openNotifModal(type) {
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

        // Step 44: Zobrazit modal přes Alpine.js API
        if (window.notifModal && window.notifModal.open) {
            window.notifModal.open();
        } else {
            // Fallback pro zpětnou kompatibilitu
            const overlay = document.getElementById('notifModalOverlay');
            const modal = overlay?.querySelector('.cc-modal');
            overlay?.classList.add('active');
            modal?.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

        /**
     * CloseNotifModal
     */
function closeNotifModal() {
        // Step 44: Zavřít modal přes Alpine.js API
        if (window.notifModal && window.notifModal.close) {
            window.notifModal.close();
        } else {
            // Fallback pro zpětnou kompatibilitu
            const overlay = document.getElementById('notifModalOverlay');
            const modal = overlay?.querySelector('.cc-modal');
            overlay?.classList.remove('active');
            modal?.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    }

        /**
     * LoadNotifContent
     */
function loadNotifContent(type, body) {
        // Zobrazit loading
        body.innerHTML = `<div style="text-align: center; padding: 2rem; color: #ccc;">${t('loading')}</div>`;

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
                    <p style="margin-bottom: 1rem; color: #ccc; font-size: 0.85rem;">${t('notif_edit_email_templates')}</p>
                    <div id="notifications-container-clone"></div>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <div style="border: 1px solid #e0e0e0; border-radius: 6px; padding: 1rem;">
                            <div style="font-weight: 600; margin-bottom: 0.5rem;">${t('notif_new_claim')}</div>
                            <div style="font-size: 0.8rem; color: #ccc; margin-bottom: 0.5rem;">${t('notif_new_claim_desc')}</div>
                            <button class="btn btn-sm" style="font-size: 0.7rem;">${t('edit_template')}</button>
                        </div>
                        <div style="border: 1px solid #e0e0e0; border-radius: 6px; padding: 1rem;">
                            <div style="font-weight: 600; margin-bottom: 0.5rem;">${t('notif_status_change')}</div>
                            <div style="font-size: 0.8rem; color: #ccc; margin-bottom: 0.5rem;">${t('notif_status_change_desc')}</div>
                            <button class="btn btn-sm" style="font-size: 0.7rem;">${t('edit_template')}</button>
                        </div>
                        <div style="border: 1px solid #e0e0e0; border-radius: 6px; padding: 1rem;">
                            <div style="font-weight: 600; margin-bottom: 0.5rem;">${t('notif_claim_completion')}</div>
                            <div style="font-size: 0.8rem; color: #ccc; margin-bottom: 0.5rem;">${t('notif_claim_completion_desc')}</div>
                            <button class="btn btn-sm" style="font-size: 0.7rem;">${t('edit_template')}</button>
                        </div>
                    </div>
                </div>
            `,
            'sms-templates': `
                <div style="padding: 1rem;">
                    <p style="margin-bottom: 1rem; color: #ccc; font-size: 0.85rem;">${t('sms_templates_desc')}</p>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <div style="border: 1px solid #e0e0e0; border-radius: 6px; padding: 1rem;">
                            <div style="font-weight: 600; margin-bottom: 0.5rem;">${t('sms_for_customer')}</div>
                            <div style="font-size: 0.8rem; color: #ccc; margin-bottom: 0.5rem;">Text: "Vaše reklamace {cislo} byla přijata"</div>
                            <button class="btn btn-sm" style="font-size: 0.7rem;">${t('edit')}</button>
                        </div>
                        <div style="border: 1px solid #e0e0e0; border-radius: 6px; padding: 1rem;">
                            <div style="font-weight: 600; margin-bottom: 0.5rem;">${t('sms_for_technician')}</div>
                            <div style="font-size: 0.8rem; color: #ccc; margin-bottom: 0.5rem;">Text: "Nová zakázka {cislo} - {mesto}"</div>
                            <button class="btn btn-sm" style="font-size: 0.7rem;">${t('edit')}</button>
                        </div>
                    </div>
                </div>
            `,
            'email-recipients': `
                <div style="padding: 1rem;">
                    <p style="margin-bottom: 1rem; color: #ccc; font-size: 0.85rem;">${t('email_recipients_desc')}</p>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.8rem; margin-bottom: 0.5rem; font-weight: 600;">${t('administrators')}</label>
                        <input type="text" placeholder="admin@example.com, admin2@example.com" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.8rem; margin-bottom: 0.5rem; font-weight: 600;">${t('all_emails_copy')}</label>
                        <input type="text" placeholder="office@example.com" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                    </div>
                    <button class="btn btn-sm btn-success" style="font-size: 0.7rem;">${t('save_changes')}</button>
                </div>
            `,
            'auto-notifications': `
                <div style="padding: 1rem;">
                    <p style="margin-bottom: 1rem; color: #ccc; font-size: 0.85rem;">Nastavení pravidel pro automatické odesílání</p>
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
                    <p style="margin-bottom: 1rem; color: #ccc; font-size: 0.85rem;">Konfigurace SMTP serveru</p>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <div>
                            <label style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem; color: #ccc;">SMTP Server *</label>
                            <input type="text" id="smtp_host" placeholder="smtp.gmail.com" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                            <div>
                                <label style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem; color: #ccc;">Port</label>
                                <input type="text" id="smtp_port" placeholder="587" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem; color: #ccc;">Šifrování</label>
                                <select id="smtp_encryption" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="none">Žádné</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem; color: #ccc;">Uživatelské jméno *</label>
                            <input type="text" id="smtp_username" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem; color: #ccc;">Heslo *</label>
                            <input type="password" id="smtp_password" autocomplete="off" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem; color: #ccc;">FROM Email</label>
                            <input type="email" id="smtp_from" placeholder="reklamace@wgs-service.cz" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem; color: #ccc;">FROM Name</label>
                            <input type="text" id="smtp_from_name" placeholder="White Glove Service" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
                        </div>
                    </div>
                    <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                        <button class="btn btn-sm" id="testSmtpBtn" data-action="testSmtpConnection" style="font-size: 0.7rem;">Test připojení</button>
                        <button class="btn btn-sm btn-success" id="saveSmtpBtn" data-action="saveSmtpConfig" style="font-size: 0.7rem;">Uložit</button>
                    </div>
                </div>
            `,
            'sms-gateway': `
                <div style="padding: 1rem;">
                    <p style="margin-bottom: 1rem; color: #ccc; font-size: 0.85rem;">Nastavení SMS brány</p>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <div>
                            <label style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem; color: #ccc;">Poskytovatel</label>
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
                            <input type="password" autocomplete="off" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem;">
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
  <!-- TAB: SECURITY - Bezpečnostní centrum -->
  <?php require_once __DIR__ . '/includes/admin_security.php'; ?>
  <?php endif; ?>
  
  <?php if ($activeTab === 'users'): ?>
  <!-- TAB: USERS -->
  <div id="tab-users" class="tab-content">
    <div class="table-container">
      <div class="table-header">
        <h3 class="table-title" data-lang-cs="Všichni uživatelé" data-lang-en="All Users" data-lang-it="Tutti gli Utenti">Všichni uživatelé</h3>
        <div class="table-actions">
          <input type="search" class="search-box" id="search-users" enterkeyhint="search" aria-label="Hledat uživatele" data-lang-cs-placeholder="Hledat..." data-lang-en-placeholder="Search..." data-lang-it-placeholder="Cerca..." placeholder="Hledat...">
          <button class="btn btn-sm btn-success" id="addUserBtn" data-lang-cs="Přidat" data-lang-en="Add" data-lang-it="Aggiungi">Přidat</button>
          <button class="btn btn-sm" id="refreshUsersBtn" data-lang-cs="Obnovit" data-lang-en="Refresh" data-lang-it="Aggiorna">Obnovit</button>
        </div>
      </div>

      <table>
        <thead>
          <tr>
            <th scope="col">ID</th>
            <th scope="col" data-lang-cs="Jméno" data-lang-en="Name" data-lang-it="Nome">Jméno</th>
            <th scope="col">Email</th>
            <th scope="col" data-lang-cs="Role" data-lang-en="Role" data-lang-it="Ruolo">Role</th>
            <th scope="col">Status</th>
            <th scope="col" data-lang-cs="Registrace" data-lang-en="Registration" data-lang-it="Registrazione">Registrace</th>
            <th scope="col" data-lang-cs="Akce" data-lang-en="Actions" data-lang-it="Azioni">Akce</th>
          </tr>
        </thead>
        <tbody id="users-table" aria-live="polite">
          <tr>
            <td colspan="7" class="loading" role="status" data-lang-cs="Načítání..." data-lang-en="Loading..." data-lang-it="Caricamento...">Načítání...</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($activeTab === 'zakaznici'): ?>
  <!-- TAB: ZÁKAZNÍCI -->
  <div id="tab-zakaznici" class="tab-content">
    <div class="table-container">
      <div class="table-header">
        <h3 class="table-title" data-lang-cs="Všichni zákazníci" data-lang-en="All Customers" data-lang-it="Tutti i Clienti">Všichni zákazníci</h3>
        <div class="table-actions">
          <input type="search" class="search-box" id="search-zakaznici" enterkeyhint="search" aria-label="Hledat zákazníky" data-lang-cs-placeholder="Hledat..." data-lang-en-placeholder="Search..." data-lang-it-placeholder="Cerca..." placeholder="Hledat...">
          <button class="btn btn-sm" id="refreshZakazniciBtn" data-lang-cs="Obnovit" data-lang-en="Refresh" data-lang-it="Aggiorna">Obnovit</button>
        </div>
      </div>

      <table>
        <thead>
          <tr>
            <th scope="col" data-lang-cs="Jméno" data-lang-en="Name" data-lang-it="Nome">Jméno</th>
            <th scope="col" data-lang-cs="Adresa" data-lang-en="Address" data-lang-it="Indirizzo">Adresa</th>
            <th scope="col" data-lang-cs="Telefon" data-lang-en="Phone" data-lang-it="Telefono">Telefon</th>
            <th scope="col">Email</th>
            <th scope="col" data-lang-cs="Počet zakázek" data-lang-en="Orders Count" data-lang-it="Numero Ordini">Počet zakázek</th>
            <th scope="col" data-lang-cs="Akce" data-lang-en="Actions" data-lang-it="Azioni">Akce</th>
          </tr>
        </thead>
        <tbody id="zakaznici-table" aria-live="polite">
          <tr>
            <td colspan="6" class="loading" role="status" data-lang-cs="Načítání..." data-lang-en="Loading..." data-lang-it="Caricamento...">Načítání...</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>


  <!-- Overlay & Modal - MUST be outside dashboard condition so it exists in DOM -->
  <!-- Alpine.js (Step 45) -->
  <?php if (!$embedMode): ?>
  <div class="cc-overlay" id="adminOverlay"
       x-data="adminModal" x-init="init" @click="overlayClick"></div>
  <div class="cc-modal" id="adminModal" role="dialog" aria-modal="true" aria-labelledby="adminModalTitle">
      <div class="cc-modal-header">
          <h2 id="adminModalTitle" class="sr-only">Modal</h2>
          <button class="cc-modal-close" data-action="closeCCModal" aria-label="Zavřít">×</button>
      </div>
      <div class="cc-modal-body" id="adminModalBody">
          <div class="cc-modal-loading">
              <div class="cc-modal-spinner" aria-hidden="true"></div>
              <div style="margin-top: 1rem;">Načítání...</div>
          </div>
      </div>
  </div>
  <?php endif; ?>

  <?php if ($activeTab === 'tenanti'): ?>
  <!-- TAB: TENANTI (Multi-tenant správa) -->
  <div id="tab-tenanti" class="tab-content">
    <div class="table-container">
      <div class="table-header">
        <h3 class="table-title">Správa tenantů</h3>
        <div class="table-actions">
          <button class="btn btn-sm" id="btnNovyTenant">Nový tenant</button>
          <button class="btn btn-sm" id="btnObnovitTenanty">Obnovit</button>
        </div>
      </div>

      <table>
        <thead>
          <tr>
            <th scope="col">ID</th>
            <th scope="col">Slug</th>
            <th scope="col">Název</th>
            <th scope="col">Doména</th>
            <th scope="col">Reklamace</th>
            <th scope="col">Uživatelé</th>
            <th scope="col">Aktivní</th>
            <th scope="col">Akce</th>
          </tr>
        </thead>
        <tbody id="tabulka-tenantu">
          <tr><td colspan="8" class="loading">Načítání...</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Modal: nový / editace tenanta -->
    <div id="modal-tenant" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9000; align-items:center; justify-content:center;">
      <div style="background:#fff; padding:28px; border-radius:6px; width:420px; max-width:95vw;">
        <h3 id="modal-tenant-titulek" style="margin:0 0 20px;">Nový tenant</h3>
        <input type="hidden" id="modal-tenant-id" value="">

        <div style="margin-bottom:14px;">
          <label for="inp-slug" style="display:block; font-weight:600; margin-bottom:4px;">Slug <small>(neměnný identifikátor, jen a-z, 0-9, pomlčka)</small></label>
          <input type="text" id="inp-slug" placeholder="napr-tenant-1" style="width:100%; padding:8px; box-sizing:border-box; border:1px solid #ccc; border-radius:4px;">
        </div>

        <div style="margin-bottom:14px;">
          <label for="inp-nazev" style="display:block; font-weight:600; margin-bottom:4px;">Název</label>
          <input type="text" id="inp-nazev" placeholder="Název organizace" style="width:100%; padding:8px; box-sizing:border-box; border:1px solid #ccc; border-radius:4px;">
        </div>

        <div style="margin-bottom:20px;">
          <label for="inp-domena" style="display:block; font-weight:600; margin-bottom:4px;">Doména <small>(volitelné, např. klient.wgs-service.cz)</small></label>
          <input type="text" id="inp-domena" placeholder="klient.wgs-service.cz" style="width:100%; padding:8px; box-sizing:border-box; border:1px solid #ccc; border-radius:4px;">
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end;">
          <button class="btn btn-sm" id="btn-tenant-zrusit">Zrušit</button>
          <button class="btn btn-sm" id="btn-tenant-ulozit">Uložit</button>
        </div>
      </div>
    </div>
  </div>

  <script>
  (function () {
    'use strict';

    var csrfToken = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>';

    function nactiTenanty() {
      var tbody = document.getElementById('tabulka-tenantu');
      tbody.innerHTML = '<tr><td colspan="8" class="loading">Načítání...</td></tr>';

      fetch('/api/tenants_api.php?akce=seznam')
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.status !== 'success') {
            tbody.innerHTML = '<tr><td colspan="8">Chyba: ' + (data.message || 'Neznámá chyba') + '</td></tr>';
            return;
          }
          var tenanti = data.data.tenanti;
          if (!tenanti || tenanti.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8">Žádní tenanti nenalezeni.</td></tr>';
            return;
          }
          tbody.innerHTML = tenanti.map(function(t) {
            var aktivni = t.je_aktivni == 1 ? 'Ano' : 'Ne';
            var aktualni = (t.tenant_id == <?php echo tenantId(); ?>) ? ' <small>(aktuální)</small>' : '';
            return '<tr>' +
              '<td>' + t.tenant_id + '</td>' +
              '<td><code>' + escHtml(t.slug) + '</code>' + aktualni + '</td>' +
              '<td>' + escHtml(t.nazev) + '</td>' +
              '<td>' + escHtml(t.domena || '—') + '</td>' +
              '<td>' + t.pocet_reklamaci + '</td>' +
              '<td>' + t.pocet_uzivatelu + '</td>' +
              '<td>' + aktivni + '</td>' +
              '<td>' +
                '<button class="btn btn-sm" onclick="upravitTenant(' + t.tenant_id + ')">Upravit</button>' +
                (t.tenant_id != 1 && t.je_aktivni == 1
                  ? ' <button class="btn btn-sm btn-danger" onclick="smazatTenant(' + t.tenant_id + ', \'' + escHtml(t.nazev) + '\')">Deaktivovat</button>'
                  : '') +
              '</td>' +
              '</tr>';
          }).join('');
        })
        .catch(function(e) {
          tbody.innerHTML = '<tr><td colspan="8">Síťová chyba: ' + e.message + '</td></tr>';
        });
    }

    function escHtml(s) {
      return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function otevritModal(titulek, tenantId, slug, nazev, domena) {
      document.getElementById('modal-tenant-titulek').textContent = titulek;
      document.getElementById('modal-tenant-id').value = tenantId || '';
      document.getElementById('inp-slug').value = slug || '';
      document.getElementById('inp-slug').disabled = !!tenantId; // slug nelze měnit při editaci
      document.getElementById('inp-nazev').value = nazev || '';
      document.getElementById('inp-domena').value = domena || '';
      document.getElementById('modal-tenant').style.display = 'flex';
    }

    function zavritModal() {
      document.getElementById('modal-tenant').style.display = 'none';
    }

    window.upravitTenant = function(tenantId) {
      fetch('/api/tenants_api.php?akce=detail&tenant_id=' + tenantId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.status !== 'success') { alert('Chyba: ' + data.message); return; }
          var t = data.data.tenant;
          otevritModal('Upravit tenant', t.tenant_id, t.slug, t.nazev, t.domena);
        });
    };

    window.smazatTenant = function(tenantId, nazev) {
      if (!confirm('Opravdu deaktivovat tenant "' + nazev + '"?\n\nData zůstanou zachována, tenant bude pouze skryt.')) return;
      var fd = new FormData();
      fd.append('akce', 'smazat');
      fd.append('tenant_id', tenantId);
      fd.append('csrf_token', csrfToken);
      fetch('/api/tenants_api.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.status === 'success') { nactiTenanty(); }
          else { alert('Chyba: ' + data.message); }
        });
    };

    document.getElementById('btnNovyTenant').addEventListener('click', function() {
      otevritModal('Nový tenant', '', '', '', '');
    });

    document.getElementById('btnObnovitTenanty').addEventListener('click', nactiTenanty);
    document.getElementById('btn-tenant-zrusit').addEventListener('click', zavritModal);

    document.getElementById('btn-tenant-ulozit').addEventListener('click', function() {
      var tenantId = document.getElementById('modal-tenant-id').value;
      var slug = document.getElementById('inp-slug').value.trim();
      var nazev = document.getElementById('inp-nazev').value.trim();
      var domena = document.getElementById('inp-domena').value.trim();

      if (!nazev) { alert('Název je povinný.'); return; }
      if (!tenantId && !slug) { alert('Slug je povinný.'); return; }

      var fd = new FormData();
      fd.append('akce', tenantId ? 'upravit' : 'vytvorit');
      fd.append('csrf_token', csrfToken);
      fd.append('nazev', nazev);
      fd.append('domena', domena);
      if (tenantId) { fd.append('tenant_id', tenantId); }
      else { fd.append('slug', slug); }

      fetch('/api/tenants_api.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.status === 'success') {
            zavritModal();
            nactiTenanty();
          } else {
            alert('Chyba: ' + data.message);
          }
        });
    });

    // Zavřít modal kliknutím mimo
    document.getElementById('modal-tenant').addEventListener('click', function(e) {
      if (e.target === this) zavritModal();
    });

    // Automaticky načíst při zobrazení tabu
    nactiTenanty();
  })();
  </script>
  <?php endif; ?>

  <?php if ($activeTab === 'admin_testing'): ?>
  <!-- TAB: TESTING ENVIRONMENT (OLD) -->
  <?php require_once __DIR__ . '/includes/admin_testing.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'admin_testing_interactive'): ?>
  <!-- TAB: INTERACTIVE TESTING ENVIRONMENT -->
  <?php require_once __DIR__ . '/includes/admin_testing_interactive.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'admin_testing_simulator'): ?>
  <!-- TAB: E2E WORKFLOW SIMULATOR -->
  <?php require_once __DIR__ . '/includes/admin_testing_simulator.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'admin_console'): ?>
  <!-- TAB: KONZOLE -->
  <?php require_once __DIR__ . '/includes/admin_console.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'admin_actions'): ?>
  <!-- TAB: AKCE & ÚKOLY -->
  <?php require_once __DIR__ . '/includes/admin_actions.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'admin_configuration'): ?>
  <!-- TAB: KONFIGURACE SYSTÉMU -->
  <?php require_once __DIR__ . '/includes/admin_configuration.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'tools'): ?>
  <!-- TAB: DIAGNOSTIKA & ÚDRŽBA -->
  <?php require_once __DIR__ . '/includes/admin_diagnostics.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'admin_soubory'): ?>
  <!-- TAB: ROOT PŘEHLED SOUBORŮ -->
  <?php require_once __DIR__ . '/includes/admin_soubory.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'transport'): ?>
  <!-- TAB: TRANSPORT EVENTS -->
  <?php require_once __DIR__ . '/includes/admin_transport.php'; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'audit'): ?>
  <!-- TAB: AUDIT PROJEKTU -->
  <?php require_once __DIR__ . '/includes/admin_audit.php'; ?>
  <?php endif; ?>

</div>
</main>
<?php endif; // Konec MAIN (pokud není dashboard) ?>

<!-- MODAL: Add User -->
<div class="modal" id="addUserModal" role="dialog" aria-modal="true" aria-labelledby="addUserModalTitle">
  <div class="modal-content" style="width: 90vw !important; height: 80vh !important; max-width: 90vw !important; max-height: 80vh !important; display: flex; flex-direction: column;">
    <div class="modal-header" style="padding: 1.5rem 2rem; border-bottom: 1px solid #ddd; flex-shrink: 0;">
      <h3 class="modal-title" id="addUserModalTitle" data-lang-cs="Přidat uživatele" data-lang-en="Add User" data-lang-it="Aggiungi Utente">Přidat uživatele</h3>
      <button class="modal-close" id="closeModalBtn" aria-label="Zavřít">×</button>
    </div>
    <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 2rem;">
      <div id="modal-error" class="error-message hidden" role="alert"></div>

      <div class="form-group">
        <label class="form-label" data-lang-cs="Jméno *" data-lang-en="Name *" data-lang-it="Nome *">Jméno *</label>
        <input type="text" class="form-input" id="add-name" required minlength="2">
      </div>

      <div class="form-group">
        <label class="form-label">Email *</label>
        <input type="email" class="form-input" id="add-email" required>
      </div>

      <div class="form-group">
        <label class="form-label" data-lang-cs="Telefon" data-lang-en="Phone" data-lang-it="Telefono">Telefon</label>
        <input type="tel" class="form-input" id="add-phone">
      </div>

      <div class="form-group">
        <label class="form-label" data-lang-cs="Adresa" data-lang-en="Address" data-lang-it="Indirizzo">Adresa</label>
        <input type="text" class="form-input" id="add-address">
      </div>

      <div class="form-group">
        <label class="form-label" data-lang-cs="Role *" data-lang-en="Role *" data-lang-it="Ruolo *">Role *</label>
        <select class="form-select" id="add-role" required>
          <option value="prodejce" data-lang-cs="Prodejce" data-lang-en="Seller" data-lang-it="Venditore">Prodejce</option>
          <option value="technik" data-lang-cs="Technik" data-lang-en="Technician" data-lang-it="Tecnico">Technik</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" data-lang-cs="Heslo * (min. 8 znaků)" data-lang-en="Password * (min. 8 characters)" data-lang-it="Password * (min. 8 caratteri)">Heslo * (min. 8 znaků)</label>
        <input type="password" class="form-input" id="add-password" required minlength="8" autocomplete="new-password">
      </div>
    </div>
    <div class="modal-footer" style="padding: 1.5rem 2rem; border-top: 1px solid #ddd; flex-shrink: 0;">
      <button class="btn" id="cancelModalBtn" data-lang-cs="Zrušit" data-lang-en="Cancel" data-lang-it="Annulla">Zrušit</button>
      <button class="btn btn-success" id="submitUserBtn" data-lang-cs="Přidat" data-lang-en="Add" data-lang-it="Aggiungi">Přidat</button>
    </div>
  </div>
</div>

<!-- External JavaScript -->
<script src="/assets/js/logger.min.js?v=<?= filemtime(__DIR__ . '/assets/js/logger.min.js') ?>" defer></script>
<script src="/assets/js/csrf-auto-inject.min.js?v=<?= filemtime(__DIR__ . '/assets/js/csrf-auto-inject.min.js') ?>" defer></script>
<!-- logout-handler.js je v hamburger-menu.php (nacita se VSUDE vcetne dashboardu) -->
<script src="/assets/js/utils.min.js?v=<?= filemtime(__DIR__ . '/assets/js/utils.min.js') ?>" defer></script>
<script src="/assets/js/admin-notifications.min.js?v=<?= filemtime(__DIR__ . '/assets/js/admin-notifications.min.js') ?>" defer></script>
<script src="/assets/js/smtp-config.min.js?v=<?= filemtime(__DIR__ . '/assets/js/smtp-config.min.js') ?>" defer></script>
<script src="/assets/js/admin-actions-registry.js?v=<?= filemtime(__DIR__ . '/assets/js/admin-actions-registry.js') ?>" defer></script>
<script src="/assets/js/admin.js?v=<?= filemtime(__DIR__ . '/assets/js/admin.js') ?>" defer></script>
<!-- Moduly admin.js -->
<script src="/assets/js/admin-klice.js?v=<?= filemtime(__DIR__ . '/assets/js/admin-klice.js') ?>" defer></script>
<script src="/assets/js/admin-uzivatel.js?v=<?= filemtime(__DIR__ . '/assets/js/admin-uzivatel.js') ?>" defer></script>
<script src="/assets/js/admin-supervize.js?v=<?= filemtime(__DIR__ . '/assets/js/admin-supervize.js') ?>" defer></script>

<!-- MODAL: Edit Notification -->
<div class="wgs-modal" id="editNotificationModal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="editNotificationTitle">
  <div class="modal-content" style="width: 1200px; max-width: 90vw; height: 80vh; display: flex; flex-direction: column; background: #1a1a1a; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.5); border: 1px solid #333;">
    <div class="modal-header" style="padding: 1.5rem 2rem; border-bottom: 1px solid #444; flex-shrink: 0; background: #333;">
      <h3 class="modal-title" id="editNotificationTitle" data-lang-cs="Editovat notifikaci" data-lang-en="Edit Notification" data-lang-it="Modifica Notifica">Editovat notifikaci</h3>
      <button class="modal-close" data-action="closeEditNotificationModal" aria-label="Zavřít">×</button>
    </div>
    <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 2rem;">
      <div id="edit-notification-error" class="error-message" style="display: none;" role="alert"></div>
      <div id="edit-notification-success" class="success-message" style="display: none;" role="status"></div>
      <div class="form-group">
        <label class="form-label" data-lang-cs="Příjemce" data-lang-en="Recipient" data-lang-it="Destinatario">Příjemce</label>
        <select class="form-select" id="edit-recipient">
          <option value="customer" data-lang-cs="Zákazník" data-lang-en="Customer" data-lang-it="Cliente">Zákazník</option>
          <option value="admin">Admin</option>
          <option value="technician" data-lang-cs="Technik" data-lang-en="Technician" data-lang-it="Tecnico">Technik</option>
          <option value="seller" data-lang-cs="Prodejce" data-lang-en="Seller" data-lang-it="Venditore">Prodejce</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" data-lang-cs="Předmět (pouze email)" data-lang-en="Subject (email only)" data-lang-it="Oggetto (solo email)">Předmět (pouze email)</label>
        <input type="text" class="form-input" id="edit-subject">
      </div>
      <div class="form-group">
        <label class="form-label" data-lang-cs="Šablona zprávy" data-lang-en="Message Template" data-lang-it="Modello di Messaggio">Šablona zprávy</label>
        <div style="margin-bottom: 0.5rem; font-size: 0.85rem; color: #aaa;"><strong data-lang-cs="Dostupné proměnné:" data-lang-en="Available variables:" data-lang-it="Variabili disponibili:">Dostupné proměnné:</strong>
          <div id="available-variables" style="display: flex; flex-wrap: wrap; gap: 0.3rem; margin-top: 0.3rem;"></div>
        </div>
        <textarea class="form-input" id="edit-template" rows="8"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label" data-lang-cs="Náhled" data-lang-en="Preview" data-lang-it="Anteprima">Náhled</label>
        <div id="template-preview" style="background: #222; padding: 1rem; border: 1px solid #444; white-space: pre-wrap; font-family: monospace; font-size: 0.9rem; color: #ccc;">Začněte psát...</div>
      </div>
      <div class="form-group">
        <label class="form-label" data-lang-cs="Dodatečné kopie emailů (CC)" data-lang-en="Additional Email Copies (CC)" data-lang-it="Copie Email Aggiuntive (CC)">Dodatečné kopie emailů (CC)</label>
        <div style="margin-bottom: 0.5rem; font-size: 0.85rem; color: #888;">
          <strong style="color: #aaa;">Tip:</strong> Můžete použít proměnné jako <code style="background: #333; padding: 2px 6px; border-radius: 3px; color: #ccc;">{{seller_email}}</code>, <code style="background: #333; padding: 2px 6px; border-radius: 3px; color: #ccc;">{{technician_email}}</code> atd.
        </div>
        <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
          <input type="email" class="form-input" id="new-cc-email" data-lang-cs-placeholder="novy@email.cz nebo {{seller_email}}" data-lang-en-placeholder="new@email.com or {{seller_email}}" data-lang-it-placeholder="nuovo@email.it o {{seller_email}}" placeholder="novy@email.cz nebo {{seller_email}}" style="flex: 1;">
          <button class="btn btn-sm" data-action="addCCEmail" data-lang-cs="+ Přidat" data-lang-en="+ Add" data-lang-it="+ Aggiungi">+ Přidat</button>
        </div>
        <div id="admin-emails-list" style="display: flex; flex-wrap: wrap; gap: 0.5rem;"></div>
      </div>
      <div class="form-group">
        <label class="form-label" data-lang-cs="Skryté kopie (BCC)" data-lang-en="Blind Copies (BCC)" data-lang-it="Copie Nascoste (BCC)">Skryté kopie (BCC)</label>
        <div style="margin-bottom: 0.5rem; font-size: 0.85rem; color: #888;">
          <strong style="color: #aaa;">Tip:</strong> Můžete použít proměnné jako <code style="background: #333; padding: 2px 6px; border-radius: 3px; color: #ccc;">{{seller_email}}</code>, <code style="background: #333; padding: 2px 6px; border-radius: 3px; color: #ccc;">{{technician_email}}</code> atd.
        </div>
        <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
          <input type="email" class="form-input" id="new-bcc-email" data-lang-cs-placeholder="skryta@email.cz nebo {{seller_email}}" data-lang-en-placeholder="hidden@email.com or {{seller_email}}" data-lang-it-placeholder="nascosta@email.it o {{seller_email}}" placeholder="skryta@email.cz nebo {{seller_email}}" style="flex: 1;">
          <button class="btn btn-sm" data-action="addBCCEmail" data-lang-cs="+ Přidat" data-lang-en="+ Add" data-lang-it="+ Aggiungi">+ Přidat</button>
        </div>
        <div id="bcc-emails-list" style="display: flex; flex-wrap: wrap; gap: 0.5rem;"></div>
      </div>
    </div>
    <div class="modal-footer" style="padding: 1.5rem 2rem; border-top: 1px solid #ddd; flex-shrink: 0;">
      <button class="btn" data-action="closeEditNotificationModal" data-lang-cs="Zrušit" data-lang-en="Cancel" data-lang-it="Annulla">Zrušit</button>
      <button class="btn btn-success" data-action="saveNotificationTemplate" data-lang-cs="Uložit" data-lang-en="Save" data-lang-it="Salva">Uložit</button>
    </div>
  </div>
</div>

<?php
/**
 * POZNÁMKA: Centralizace registrací JS akcí
 *
 * Problém "hluchých" tlačítek byl vyřešen vytvořením statického souboru
 * /assets/js/admin-actions-registry.js, který se načítá VŽDY (viz řádek 1117)
 *
 * Tento soubor obsahuje všechny Utils.registerAction() volání pro všechny admin akce.
 * Akce jsou zaregistrovány globálně, ale vykonávají se pouze pokud existuje jejich funkce.
 */
?>

<?php require_once __DIR__ . '/includes/pwa_scripts.php'; ?>
</body>
</html>
