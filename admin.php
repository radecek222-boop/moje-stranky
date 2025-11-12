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
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';

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
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'created_by'");
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
  <link rel="preload" href="assets/css/styles.min.css" as="style">
  <link rel="preload" href="assets/css/admin.min.css" as="style">

  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/admin.min.css">
<link rel="stylesheet" href="assets/css/admin-header.css">
<link rel="stylesheet" href="assets/css/admin-notifications.css">
<link rel="stylesheet" href="assets/css/control-center-modal.css">
<link rel="stylesheet" href="assets/css/control-center-mobile.css">

  <!-- Error Handler - zachytává všechny chyby -->
  <script src="assets/js/error-handler.js"></script>
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
    <h2 class="page-title" style="font-size: 1.8rem; margin-bottom: 1rem;">Správa Emailů & SMS</h2>
    <p class="page-subtitle">Editace šablon, nastavení příjemců a správa automatických notifikací</p>

    <div id="notifications-container">
      <div class="loading">Načítání notifikací...</div>
    </div>
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
        <input type="text" class="form-input" id="add-name">
      </div>
      
      <div class="form-group">
        <label class="form-label">Email *</label>
        <input type="email" class="form-input" id="add-email">
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
        <select class="form-select" id="add-role">
          <option value="prodejce">Prodejce</option>
          <option value="technik">Technik</option>
        </select>
      </div>
      
      <div class="form-group">
        <label class="form-label">Heslo * (min. 8 znaků)</label>
        <input type="password" class="form-input" id="add-password">
      </div>
    </div>
    <div class="modal-footer" style="padding: 1.5rem 2rem; border-top: 1px solid #ddd; flex-shrink: 0;">
      <button class="btn" id="cancelModalBtn">Zrušit</button>
      <button class="btn btn-success" id="submitUserBtn">Přidat</button>
    </div>
  </div>
</div>

<!-- External JavaScript -->
<script src="assets/js/csrf-auto-inject.js"></script>
<script src="assets/js/logger.js"></script>
<script src="assets/js/utils.js"></script>
<script src="assets/js/admin-notifications.js"></script>
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
