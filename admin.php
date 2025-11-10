<?php
require_once "init.php";
require_once __DIR__ . '/includes/admin_navigation.php';

// BEZPEČNOST: Kontrola admin přihlášení
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    header('Location: login.php?redirect=admin.php');
    exit;
}

$tabConfig = loadAdminTabNavigation();
$activeTab = $_GET['tab'] ?? 'dashboard';
if (!array_key_exists($activeTab, $tabConfig)) {
    $activeTab = 'dashboard';
}
$currentTabMeta = $tabConfig[$activeTab];
$currentTabLabel = $currentTabMeta['tab_label'] ?? 'Přehled';

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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Administrační panel White Glove Service. Správa uživatelů, registračních klíčů, emailů, SMS notifikací a systémových nastavení.">
  <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
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
</head>

<body>
<?php require_once __DIR__ . "/includes/admin_header.php"; ?>

<!-- MAIN CONTENT -->
<main>
<div class="container">

  <h1 class="page-title">Admin Panel</h1>
  <p class="page-subtitle">Správa systému White Glove Service</p>
  <p class="page-subtitle">Sekce: <?php echo htmlspecialchars($currentTabLabel, ENT_QUOTES, 'UTF-8'); ?></p>

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

  <?php if ($activeTab === 'tools'): ?>
  <!-- TAB: TOOLS & MIGRATIONS -->
  <div id="tab-tools" class="tab-content">
    <h2 class="page-title" style="font-size: 1.8rem; margin-bottom: 0.5rem;">Nástroje & Migrace</h2>
    <p class="page-subtitle" style="margin-bottom: 2rem;">Správa databáze, instalace nových funkcí a debug nástroje</p>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem;">

      <!-- INSTALÁTOR: Role-Based Access -->
      <div style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #000;">
        <div style="margin-bottom: 1rem;">
          <h3 style="margin: 0 0 0.5rem 0; font-size: 1.2rem; color: #333; font-weight: 600; letter-spacing: 0.05em;">ROLE-BASED ACCESS CONTROL</h3>
          <p style="margin: 0; color: #666; font-size: 0.9rem;">Škálovatelný systém rolí pro neomezený počet prodejců a techniků</p>
        </div>

        <?php if ($rbacInstalled): ?>
          <!-- JIŽ NAINSTALOVÁNO -->
          <div style="background: #e8f5e9; border: 2px solid #4CAF50; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            <div style="font-size: 0.85rem; color: #2e7d32; font-weight: 600; margin-bottom: 0.5rem;">
              ✓ SYSTÉM JE AKTIVNÍ
            </div>
            <div style="font-size: 0.8rem; color: #555;">
              Role-Based Access Control je nainstalován a funkční.
            </div>
          </div>

          <div style="margin-bottom: 1rem;">
            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem;">
              <strong>Co je aktivní:</strong>
            </div>
            <ul style="margin: 0; padding-left: 1.5rem; font-size: 0.85rem; color: #666;">
              <li>Sloupce <code>created_by</code> a <code>created_by_role</code></li>
              <li>Indexy pro rychlé vyhledávání</li>
              <li>Podpora neomezeného počtu uživatelů</li>
              <li>Role prodejce, technik, admin, guest</li>
            </ul>
          </div>

          <button
            onclick="window.location.href='install_role_based_access.php'"
            style="width: 100%; padding: 0.875rem 0.75rem; background: #fff; color: #000; border: 2px solid #000; border-radius: 0; font-weight: 600; cursor: pointer; letter-spacing: 0.05em; text-transform: uppercase; font-size: 0.8rem; transition: all 0.3s; white-space: normal; line-height: 1.3;"
            onmouseover="this.style.background='#000'; this.style.color='#fff'"
            onmouseout="this.style.background='#fff'; this.style.color='#000'"
          >
            ZOBRAZIT DETAIL
          </button>

        <?php else: ?>
          <!-- JEŠTĚ NENÍ NAINSTALOVÁNO -->
          <div style="margin-bottom: 1rem;">
            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem;">
              <strong>Co se nainstaluje:</strong>
            </div>
            <ul style="margin: 0; padding-left: 1.5rem; font-size: 0.85rem; color: #666;">
              <li>Sloupce <code>created_by</code> a <code>created_by_role</code></li>
              <li>Naplnění existujících dat</li>
              <li>Indexy pro rychlé vyhledávání</li>
              <li>Nastavení rolí pro uživatele</li>
            </ul>
          </div>

          <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
            <span style="background: #e3f2fd; color: #1976d2; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8rem; font-weight: 500;">v2.0</span>
            <span style="background: #f3e5f5; color: #7b1fa2; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8rem; font-weight: 500;">Vyžaduje migraci</span>
          </div>

          <button
            onclick="window.location.href='install_role_based_access.php'"
            style="width: 100%; padding: 0.875rem 0.75rem; background: #000; color: white; border: 2px solid #000; border-radius: 0; font-weight: 600; cursor: pointer; letter-spacing: 0.05em; text-transform: uppercase; font-size: 0.8rem; transition: all 0.3s; white-space: normal; line-height: 1.3;"
            onmouseover="this.style.background='#fff'; this.style.color='#000'"
            onmouseout="this.style.background='#000'; this.style.color='#fff'"
          >
            SPUSTIT INSTALACI
          </button>
        <?php endif; ?>
      </div>

      <!-- DEBUG NÁSTROJE -->
      <div style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #000;">
        <div style="margin-bottom: 1rem;">
          <h3 style="margin: 0 0 0.5rem 0; font-size: 1.2rem; color: #333; font-weight: 600; letter-spacing: 0.05em;">DEBUG NÁSTROJE</h3>
          <p style="margin: 0; color: #666; font-size: 0.9rem;">Diagnostika databáze, reklamací, fotek a struktur</p>
        </div>

        <div style="margin-bottom: 1rem;">
          <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem;">
            <strong>Dostupné nástroje:</strong>
          </div>
          <ul style="margin: 0; padding-left: 1.5rem; font-size: 0.85rem; color: #666;">
            <li>Struktura tabulek</li>
            <li>Debug reklamací a viditelnosti</li>
            <li>Debug fotek a propojení</li>
            <li>Test databázového připojení</li>
          </ul>
        </div>

        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
          <span style="background: #e8f5e9; color: #388e3c; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8rem; font-weight: 500;">Bezpečné</span>
          <span style="background: #fff3e0; color: #f57c00; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8rem; font-weight: 500;">Pouze čtení</span>
        </div>

        <div style="margin-bottom: 1rem;">
          <button
            onclick="window.open('diagnostic_access_active.php', '_blank')"
            style="width: 100%; padding: 0.875rem 0.75rem; background: #000; color: white; border: 2px solid #000; border-radius: 0; font-size: 0.8rem; font-weight: 600; cursor: pointer; letter-spacing: 0.05em; text-transform: uppercase; transition: all 0.3s; white-space: normal; line-height: 1.3; margin-bottom: 0.5rem;"
            onmouseover="this.style.background='#fff'; this.style.color='#000'"
            onmouseout="this.style.background='#000'; this.style.color='#fff'"
          >
            ⚡ AKTIVNÍ DIAGNOSTIKA
          </button>

          <button
            onclick="window.open('diagnostic_tool.php', '_blank')"
            style="width: 100%; padding: 0.875rem 0.75rem; background: #555; color: white; border: 2px solid #555; border-radius: 0; font-size: 0.75rem; font-weight: 600; cursor: pointer; letter-spacing: 0.05em; text-transform: uppercase; transition: all 0.3s; white-space: normal; line-height: 1.3; margin-bottom: 0.5rem;"
          >
            SQL/PHP Debug
          </button>

          <button
            onclick="window.open('diagnostic_access_control.php', '_blank')"
            style="width: 100%; padding: 0.875rem 0.75rem; background: #555; color: white; border: 2px solid #555; border-radius: 0; font-size: 0.75rem; font-weight: 600; cursor: pointer; letter-spacing: 0.05em; text-transform: uppercase; transition: all 0.3s; white-space: normal; line-height: 1.3;"
          >
            Dokumentace systému
          </button>
        </div>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem;">
          <button
            onclick="window.open('show_table_structure.php', '_blank')"
            style="padding: 0.625rem 0.5rem; background: #555; color: white; border: none; border-radius: 0; font-size: 0.75rem; cursor: pointer; letter-spacing: 0.03em; text-transform: uppercase; white-space: normal; line-height: 1.3;"
          >
            STRUKTURA
          </button>
          <button
            onclick="window.open('debug_photos.php', '_blank')"
            style="padding: 0.625rem 0.5rem; background: #555; color: white; border: none; border-radius: 0; font-size: 0.75rem; cursor: pointer; letter-spacing: 0.03em; text-transform: uppercase; white-space: normal; line-height: 1.3;"
          >
            FOTKY
          </button>
          <button
            onclick="window.open('validate_tools.php', '_blank')"
            style="padding: 0.625rem 0.5rem; background: #555; color: white; border: none; border-radius: 0; font-size: 0.75rem; cursor: pointer; letter-spacing: 0.03em; text-transform: uppercase; white-space: normal; line-height: 1.3;"
          >
            TEST ✓
          </button>
        </div>
      </div>

      <!-- TESTOVÁNÍ ROLÍ -->
      <div style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #000;">
        <div style="margin-bottom: 1rem;">
          <h3 style="margin: 0 0 0.5rem 0; font-size: 1.2rem; color: #333; font-weight: 600; letter-spacing: 0.05em;">TESTOVÁNÍ ROLÍ</h3>
          <p style="margin: 0; color: #666; font-size: 0.9rem;">Simulace různých uživatelských rolí pro testování přístupů</p>
        </div>

        <?php
        // OBSLUHA SIMULACE ROLÍ
        if (!isset($_SESSION['_original_admin_session'])) {
            $_SESSION['_original_admin_session'] = [
                'user_id' => $_SESSION['user_id'] ?? null,
                'email' => $_SESSION['email'] ?? null,
                'role' => $_SESSION['role'] ?? null,
                'is_admin' => $_SESSION['is_admin'] ?? null,
                'name' => $_SESSION['name'] ?? null,
            ];
        }

        $roleAction = $_POST['role_action'] ?? null;

        if ($roleAction === 'simulate') {
            $simulateRole = $_POST['simulate_role'] ?? null;

            switch ($simulateRole) {
                case 'admin':
                    $_SESSION['user_id'] = 1;
                    $_SESSION['email'] = 'admin@wgs-service.cz';
                    $_SESSION['role'] = 'admin';
                    $_SESSION['is_admin'] = true;
                    $_SESSION['name'] = 'Admin (TEST)';
                    $_SESSION['_simulating'] = 'admin';
                    break;

                case 'prodejce':
                    $_SESSION['user_id'] = 7;
                    $_SESSION['email'] = 'naty@naty.cz';
                    $_SESSION['role'] = 'prodejce';
                    $_SESSION['is_admin'] = false;
                    $_SESSION['name'] = 'Naty Prodejce (TEST)';
                    $_SESSION['_simulating'] = 'prodejce';
                    break;

                case 'technik':
                    $_SESSION['user_id'] = 15;
                    $_SESSION['email'] = 'milan@technik.cz';
                    $_SESSION['role'] = 'technik';
                    $_SESSION['is_admin'] = false;
                    $_SESSION['name'] = 'Milan Technik (TEST)';
                    $_SESSION['_simulating'] = 'technik';
                    break;

                case 'guest':
                    $_SESSION['user_id'] = null;
                    $_SESSION['email'] = 'jiri@novacek.cz';
                    $_SESSION['role'] = 'guest';
                    $_SESSION['is_admin'] = false;
                    $_SESSION['name'] = 'Jiří Nováček (TEST)';
                    $_SESSION['_simulating'] = 'guest';
                    break;
            }

            header('Location: admin.php?tab=tools&simulated=' . urlencode($simulateRole));
            exit;
        }

        if ($roleAction === 'reset') {
            $originalSession = $_SESSION['_original_admin_session'];
            $_SESSION['user_id'] = $originalSession['user_id'];
            $_SESSION['email'] = $originalSession['email'];
            $_SESSION['role'] = $originalSession['role'];
            $_SESSION['is_admin'] = $originalSession['is_admin'];
            $_SESSION['name'] = $originalSession['name'];
            unset($_SESSION['_simulating']);

            header('Location: admin.php?tab=tools&reset=1');
            exit;
        }

        $currentSimulation = $_SESSION['_simulating'] ?? null;
        ?>

        <!-- Upozornění -->
        <?php if (isset($_GET['simulated'])): ?>
          <div style="background: #fff3e0; border: 2px solid #f57c00; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            <div style="font-size: 0.85rem; color: #e65100; font-weight: 600;">
              SIMULACE AKTIVNÍ: <?= htmlspecialchars($_GET['simulated']) ?>
            </div>
          </div>
        <?php elseif (isset($_GET['reset'])): ?>
          <div style="background: #e8f5e9; border: 2px solid #4CAF50; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            <div style="font-size: 0.85rem; color: #2e7d32; font-weight: 600;">
              ✓ RESET NA ADMIN SESSION
            </div>
          </div>
        <?php elseif ($currentSimulation): ?>
          <div style="background: #fff3e0; border: 2px solid #f57c00; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            <div style="font-size: 0.85rem; color: #e65100; font-weight: 600;">
              POZOR: Simuluješ roli "<?= htmlspecialchars($currentSimulation) ?>"
            </div>
          </div>
        <?php endif; ?>

        <!-- Aktuální session -->
        <div style="background: #f8f8f8; border: 1px solid #E0E0E0; padding: 1rem; margin-bottom: 1rem; font-size: 0.8rem; font-family: monospace;">
          <div style="margin-bottom: 0.5rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #000;">Aktuální Session:</div>
          <div style="color: #555;">user_id: <strong><?= isset($_SESSION['user_id']) ? htmlspecialchars($_SESSION['user_id']) : 'NULL' ?></strong></div>
          <div style="color: #555;">email: <strong><?= isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'NULL' ?></strong></div>
          <div style="color: #555;">role: <strong><?= isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'NULL' ?></strong></div>
          <div style="color: #555;">is_admin: <strong><?= isset($_SESSION['is_admin']) && $_SESSION['is_admin'] ? 'true' : 'false' ?></strong></div>
          <?php if ($currentSimulation): ?>
          <div style="color: #f57c00;">_simulating: <strong><?= htmlspecialchars($currentSimulation) ?></strong></div>
          <?php endif; ?>
        </div>

        <!-- Role výběr -->
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; margin-bottom: 1rem;">
          <form method="POST" style="margin: 0;">
            <input type="hidden" name="role_action" value="simulate">
            <input type="hidden" name="simulate_role" value="admin">
            <button type="submit" style="width: 100%; padding: 0.75rem 0.5rem; background: <?= $currentSimulation === 'admin' ? '#000' : '#fff' ?>; color: <?= $currentSimulation === 'admin' ? '#fff' : '#000' ?>; border: 2px solid #000; font-size: 0.75rem; font-weight: 600; cursor: pointer; letter-spacing: 0.05em; text-transform: uppercase; transition: all 0.3s; white-space: normal; line-height: 1.3;">
              Admin
            </button>
          </form>

          <form method="POST" style="margin: 0;">
            <input type="hidden" name="role_action" value="simulate">
            <input type="hidden" name="simulate_role" value="prodejce">
            <button type="submit" style="width: 100%; padding: 0.75rem 0.5rem; background: <?= $currentSimulation === 'prodejce' ? '#000' : '#fff' ?>; color: <?= $currentSimulation === 'prodejce' ? '#fff' : '#000' ?>; border: 2px solid #000; font-size: 0.75rem; font-weight: 600; cursor: pointer; letter-spacing: 0.05em; text-transform: uppercase; transition: all 0.3s; white-space: normal; line-height: 1.3;">
              Prodejce
            </button>
          </form>

          <form method="POST" style="margin: 0;">
            <input type="hidden" name="role_action" value="simulate">
            <input type="hidden" name="simulate_role" value="technik">
            <button type="submit" style="width: 100%; padding: 0.75rem 0.5rem; background: <?= $currentSimulation === 'technik' ? '#000' : '#fff' ?>; color: <?= $currentSimulation === 'technik' ? '#fff' : '#000' ?>; border: 2px solid #000; font-size: 0.75rem; font-weight: 600; cursor: pointer; letter-spacing: 0.05em; text-transform: uppercase; transition: all 0.3s; white-space: normal; line-height: 1.3;">
              Technik
            </button>
          </form>

          <form method="POST" style="margin: 0;">
            <input type="hidden" name="role_action" value="simulate">
            <input type="hidden" name="simulate_role" value="guest">
            <button type="submit" style="width: 100%; padding: 0.75rem 0.5rem; background: <?= $currentSimulation === 'guest' ? '#000' : '#fff' ?>; color: <?= $currentSimulation === 'guest' ? '#fff' : '#000' ?>; border: 2px solid #000; font-size: 0.75rem; font-weight: 600; cursor: pointer; letter-spacing: 0.05em; text-transform: uppercase; transition: all 0.3s; white-space: normal; line-height: 1.3;">
              Guest
            </button>
          </form>
        </div>

        <!-- Reset button -->
        <?php if ($currentSimulation): ?>
        <form method="POST" style="margin-bottom: 1rem;">
          <input type="hidden" name="role_action" value="reset">
          <button type="submit" style="width: 100%; padding: 0.75rem; background: #555; color: white; border: 2px solid #555; font-size: 0.75rem; font-weight: 600; cursor: pointer; letter-spacing: 0.05em; text-transform: uppercase; transition: all 0.3s; white-space: normal; line-height: 1.3;">
            RESET NA ADMIN
          </button>
        </form>
        <?php endif; ?>

        <!-- Testovací odkazy -->
        <div style="background: #f8f8f8; border: 1px solid #E0E0E0; padding: 1rem;">
          <div style="margin-bottom: 0.5rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #000;">Testuj v novém okně:</div>
          <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
            <a href="/seznam.php" target="_blank" style="display: inline-block; padding: 0.5rem 0.75rem; background: #000; color: white; text-decoration: none; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; transition: all 0.3s;">SEZNAM</a>
            <a href="/show_table_structure.php" target="_blank" style="display: inline-block; padding: 0.5rem 0.75rem; background: #000; color: white; text-decoration: none; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; transition: all 0.3s;">DB</a>
            <a href="/diagnostic_web.php" target="_blank" style="display: inline-block; padding: 0.5rem 0.75rem; background: #000; color: white; text-decoration: none; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; transition: all 0.3s;">DIAGNOSTIKA</a>
          </div>
        </div>
      </div>

      <!-- DOKUMENTACE -->
      <div style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #000;">
        <div style="margin-bottom: 1rem;">
          <h3 style="margin: 0 0 0.5rem 0; font-size: 1.2rem; color: #333; font-weight: 600; letter-spacing: 0.05em;">DOKUMENTACE</h3>
          <p style="margin: 0; color: #666; font-size: 0.9rem;">Návody, postupy a technická dokumentace systému</p>
        </div>

        <div style="margin-bottom: 1rem;">
          <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem;">
            <strong>Dostupné dokumenty:</strong>
          </div>
          <ul style="margin: 0; padding-left: 1.5rem; font-size: 0.85rem; color: #666;">
            <li>Admin Tools README</li>
            <li>Role-Based Access README</li>
            <li>PDF Protokol System</li>
            <li>Security Review</li>
          </ul>
        </div>

        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
          <span style="background: #e8f5e9; color: #388e3c; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8rem; font-weight: 500;">Aktuální</span>
        </div>

        <button
          onclick="window.open('ADMIN_TOOLS_README.md', '_blank')"
          style="width: 100%; padding: 0.75rem; background: #555; color: white; border: none; border-radius: 0; font-weight: 600; cursor: pointer; letter-spacing: 0.1em; text-transform: uppercase; font-size: 0.75rem;"
        >
          OTEVŘÍT DOKUMENTACI
        </button>
      </div>

    </div>

    <!-- Info box -->
    <div style="margin-top: 2rem; background: #f8f8f8; border-left: 4px solid #000; border-radius: 0; padding: 1.5rem;">
      <div>
        <h4 style="margin: 0 0 0.5rem 0; color: #000; font-weight: 600; letter-spacing: 0.05em;">JAK TO FUNGUJE?</h4>
        <p style="margin: 0; color: #555; line-height: 1.6; font-size: 0.9rem;">
          Po každém merge na GitHubu se zde automaticky objeví nové instalace a migrace.
          Stačí kliknout na tlačítko "Spustit instalaci" a systém se automaticky aktualizuje.
          <strong>Žádné SQL příkazy nejsou potřeba!</strong>
        </p>
      </div>
    </div>
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
<script src="assets/js/csrf-auto-inject.js" defer></script>
<script src="assets/js/logger.js" defer></script>
<script src="assets/js/admin-notifications.js" defer></script>
<script src="assets/js/admin.js" defer></script>

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
