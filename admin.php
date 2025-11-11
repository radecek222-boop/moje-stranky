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

  <?php if ($activeTab === 'control_center'): ?>
  <!-- TAB: CONTROL CENTER -->
  <?php
    // Control Center Routing
    $section = $_GET['section'] ?? 'main';

    switch ($section) {
        case 'appearance':
            require_once __DIR__ . '/includes/control_center_appearance.php';
            break;
        case 'diagnostics':
            require_once __DIR__ . '/includes/control_center_diagnostics.php';
            break;
        case 'actions':
            require_once __DIR__ . '/includes/control_center_actions.php';
            break;
        case 'content':
            require_once __DIR__ . '/includes/control_center_content.php';
            break;
        case 'users':
            // Redirect na existující users tab
            header('Location: admin.php?tab=users');
            exit;
        case 'notifications':
            // Redirect na existující notifications tab
            header('Location: admin.php?tab=notifications');
            exit;
        case 'configuration':
            require_once __DIR__ . '/includes/control_center_configuration.php';
            break;
        case 'analytics':
            // Redirect na existující statistiky
            header('Location: statistiky.php');
            exit;
        case 'main':
        default:
            require_once __DIR__ . '/includes/control_center_main.php';
            break;
    }
  ?>
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

      <!-- KOMPLETNÍ NÁSTROJE - VŠE NA JEDNÉ STRÁNCE -->
      <div style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #000;">
        <div style="margin-bottom: 1.5rem;">
          <h3 style="margin: 0 0 0.5rem 0; font-size: 1.3rem; color: #000; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;">🔧 NÁSTROJE PRO OPRAVU A DIAGNOSTIKU</h3>
          <p style="margin: 0; color: #666; font-size: 0.9rem;">Vše na jednom místě - opravy, statistiky, simulace</p>
        </div>

        <?php
        // ===== ZPRACOVÁNÍ AUTO-OPRAVY =====
        $fixMessage = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_fix_all_visibility'])) {
            try {
                // DŮLEŽITÉ: Načíst i 'role' aby se použila správná role, ne vždy 'prodejce'
                $stmt = $pdo->query("SELECT id, email, role FROM wgs_users WHERE email IS NOT NULL AND email != ''");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $totalFixed = 0;
                $details = [];

                foreach ($users as $user) {
                    // Použít SKUTEČNOU roli uživatele, ne vždy 'prodejce'
                    $userRole = strtolower(trim($user['role'] ?? 'user'));

                    $stmt = $pdo->prepare("UPDATE wgs_reklamace
                                          SET created_by = :user_id,
                                              created_by_role = :role
                                          WHERE LOWER(TRIM(email)) = LOWER(:email)
                                          AND (created_by IS NULL OR created_by = 0)");
                    $stmt->execute([
                        ':user_id' => $user['id'],
                        ':email' => $user['email'],
                        ':role' => $userRole
                    ]);

                    $affected = $stmt->rowCount();
                    if ($affected > 0) {
                        $totalFixed += $affected;
                        $details[] = "{$user['email']} ({$userRole}): $affected";
                    }
                }

                $fixMessage = "<div style='background: #0a0; color: white; padding: 1.5rem; margin-bottom: 1.5rem; border-radius: 8px; font-weight: 600;'>
                    ✓ AUTO-OPRAVA DOKONČENA!<br>
                    Celkem opraveno: <strong>$totalFixed reklamací</strong><br>
                    " . (count($details) > 0 ? '<br>' . implode('<br>', $details) : '') . "
                </div>";
            } catch (Exception $e) {
                $fixMessage = "<div style='background: #d00; color: white; padding: 1.5rem; margin-bottom: 1.5rem; border-radius: 8px;'>
                    ✗ CHYBA: " . htmlspecialchars($e->getMessage()) . "
                </div>";
            }
        }

        // ZPRACOVÁNÍ INDIVIDUÁLNÍ OPRAVY
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_email'])) {
            $emailToFix = trim($_POST['fix_email']);
            $userIdToSet = (int)$_POST['fix_user_id'];

            try {
                // Načíst SKUTEČNOU roli uživatele z databáze
                $stmt = $pdo->prepare("SELECT role FROM wgs_users WHERE id = :user_id LIMIT 1");
                $stmt->execute([':user_id' => $userIdToSet]);
                $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                $userRole = strtolower(trim($userRecord['role'] ?? 'user'));

                $stmt = $pdo->prepare("UPDATE wgs_reklamace
                                      SET created_by = :user_id,
                                          created_by_role = :role
                                      WHERE LOWER(TRIM(email)) = LOWER(:email)
                                      AND (created_by IS NULL OR created_by = 0)");
                $stmt->execute([
                    ':user_id' => $userIdToSet,
                    ':email' => $emailToFix,
                    ':role' => $userRole
                ]);

                $affected = $stmt->rowCount();
                $fixMessage = "<div style='background: #0a0; color: white; padding: 1.5rem; margin-bottom: 1.5rem; border-radius: 8px; font-weight: 600;'>
                    ✓ OPRAVENO! $affected reklamací pro $emailToFix<br>
                    (created_by = $userIdToSet, role = $userRole)
                </div>";
            } catch (Exception $e) {
                $fixMessage = "<div style='background: #d00; color: white; padding: 1.5rem; margin-bottom: 1.5rem; border-radius: 8px;'>
                    ✗ CHYBA: " . htmlspecialchars($e->getMessage()) . "
                </div>";
            }
        }

        echo $fixMessage;
        ?>

        <!-- 1. STATISTIKA -->
        <div style="background: #f8f8f8; border: 2px solid #000; padding: 1.5rem; margin-bottom: 1.5rem;">
          <h4 style="margin: 0 0 1rem 0; font-size: 1.1rem; font-weight: 700; text-transform: uppercase;">📊 STATISTIKA REKLAMACÍ</h4>

          <?php
          $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace");
          $totalClaims = $stmt->fetchColumn();

          $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE created_by IS NULL OR created_by = 0");
          $nullCreatedBy = $stmt->fetchColumn();

          $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE created_by IS NOT NULL AND created_by > 0");
          $hasCreatedBy = $stmt->fetchColumn();

          if ($nullCreatedBy > 0): ?>
            <div style="background: #fff3e0; border: 2px solid #f57c00; padding: 1rem; margin-bottom: 1rem;">
              <strong style="color: #e65100;">⚠️ PROBLÉM NALEZEN!</strong><br>
              <span style="color: #e65100; font-size: 1.1rem; font-weight: 700;"><?= $nullCreatedBy ?> reklamací</span> z celkových <strong><?= $totalClaims ?></strong> nemá vyplněné created_by<br>
              → Prodejci tyto reklamace NEVIDÍ v seznam.php
            </div>

            <form method="POST" style="margin-top: 1rem;">
              <button type="submit" name="auto_fix_all_visibility" onclick="return confirm('OPRAVIT všechny reklamace s NULL created_by?')" style="width: 100%; padding: 1rem; background: #0a0; color: white; border: 3px solid #0a0; font-size: 1rem; font-weight: 700; cursor: pointer; text-transform: uppercase; letter-spacing: 0.05em;">
                ⚡ AUTO-OPRAVA VŠECH (<?= $nullCreatedBy ?> reklamací)
              </button>
            </form>
          <?php else: ?>
            <div style="background: #e8f5e9; border: 2px solid #4caf50; padding: 1rem; color: #2e7d32; font-weight: 600;">
              ✓ V POŘÁDKU: Všechny reklamace mají vyplněné created_by
            </div>
          <?php endif; ?>

          <div style="margin-top: 1rem; font-size: 0.9rem;">
            <strong>Celkem reklamací:</strong> <?= $totalClaims ?><br>
            <strong>created_by = NULL:</strong> <span style="color: <?= $nullCreatedBy > 0 ? 'red' : 'green' ?>; font-weight: bold;"><?= $nullCreatedBy ?></span><br>
            <strong>created_by vyplněno:</strong> <span style="color: green; font-weight: bold;"><?= $hasCreatedBy ?></span>
          </div>
        </div>

        <!-- 2. REKLAMACE PODLE EMAILU -->
        <?php if ($nullCreatedBy > 0): ?>
        <div style="background: #f8f8f8; border: 2px solid #000; padding: 1.5rem; margin-bottom: 1.5rem;">
          <h4 style="margin: 0 0 1rem 0; font-size: 1.1rem; font-weight: 700; text-transform: uppercase;">📧 REKLAMACE S CHYBĚJÍCÍM created_by</h4>

          <?php
          $stmt = $pdo->query("SELECT
              r.email,
              COUNT(*) as total,
              SUM(CASE WHEN r.created_by IS NULL OR r.created_by = 0 THEN 1 ELSE 0 END) as null_count,
              u.id as user_id,
              u.name as user_name,
              u.role
          FROM wgs_reklamace r
          LEFT JOIN wgs_users u ON LOWER(TRIM(u.email)) = LOWER(TRIM(r.email))
          WHERE r.email IS NOT NULL AND r.email != ''
          GROUP BY r.email, u.id, u.name, u.role
          HAVING null_count > 0
          ORDER BY null_count DESC, total DESC");

          $emailGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

          if (!empty($emailGroups)): ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
              <tr style="background: #000; color: white;">
                <th style="padding: 0.75rem; text-align: left; border: 1px solid #000;">EMAIL</th>
                <th style="padding: 0.75rem; text-align: left; border: 1px solid #000;">JMÉNO</th>
                <th style="padding: 0.75rem; text-align: left; border: 1px solid #000;">NULL</th>
                <th style="padding: 0.75rem; text-align: left; border: 1px solid #000;">AKCE</th>
              </tr>
              <?php foreach ($emailGroups as $row): ?>
              <tr style="background: white;">
                <td style="padding: 0.75rem; border: 1px solid #ddd;"><?= htmlspecialchars($row['email']) ?></td>
                <td style="padding: 0.75rem; border: 1px solid #ddd;"><?= htmlspecialchars($row['user_name'] ?? '-') ?></td>
                <td style="padding: 0.75rem; border: 1px solid #ddd; color: red; font-weight: bold;"><?= $row['null_count'] ?></td>
                <td style="padding: 0.75rem; border: 1px solid #ddd;">
                  <?php if ($row['user_id']): ?>
                    <form method="POST" style="display: inline;">
                      <input type="hidden" name="fix_email" value="<?= htmlspecialchars($row['email']) ?>">
                      <input type="hidden" name="fix_user_id" value="<?= $row['user_id'] ?>">
                      <button type="submit" style="padding: 0.5rem 1rem; background: #0a0; color: white; border: none; cursor: pointer; font-weight: 600; font-size: 0.8rem;">
                        OPRAVIT (<?= $row['null_count'] ?>)
                      </button>
                    </form>
                  <?php else: ?>
                    <span style="color: red;">⚠️ User neexistuje</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </table>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- 3. PŘEHLED VŠECH REKLAMACÍ -->
        <div style="background: #f8f8f8; border: 2px solid #000; padding: 1.5rem; margin-bottom: 1.5rem;">
          <h4 style="margin: 0 0 1rem 0; font-size: 1.1rem; font-weight: 700; text-transform: uppercase;">📋 VŠECHNY REKLAMACE (poslední 30)</h4>

          <?php
          $stmt = $pdo->query("SELECT
              id,
              reklamace_id,
              cislo,
              email,
              jmeno,
              created_by,
              created_by_role,
              created_at
          FROM wgs_reklamace
          ORDER BY created_at DESC
          LIMIT 30");

          $allClaims = $stmt->fetchAll(PDO::FETCH_ASSOC);
          ?>

          <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem;">
              <tr style="background: #000; color: white;">
                <th style="padding: 0.5rem; text-align: left; border: 1px solid #000;">ID</th>
                <th style="padding: 0.5rem; text-align: left; border: 1px solid #000;">REK_ID</th>
                <th style="padding: 0.5rem; text-align: left; border: 1px solid #000;">EMAIL</th>
                <th style="padding: 0.5rem; text-align: left; border: 1px solid #000;">JMÉNO</th>
                <th style="padding: 0.5rem; text-align: left; border: 1px solid #000;">CREATED_BY</th>
                <th style="padding: 0.5rem; text-align: left; border: 1px solid #000;">ROLE</th>
              </tr>
              <?php foreach ($allClaims as $claim):
                $isNull = ($claim['created_by'] === null || $claim['created_by'] == 0);
                $bgColor = $isNull ? '#fdd' : 'white';
              ?>
              <tr style="background: <?= $bgColor ?>;">
                <td style="padding: 0.5rem; border: 1px solid #ddd;"><?= $claim['id'] ?></td>
                <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.7rem;"><?= $claim['reklamace_id'] ?? '-' ?></td>
                <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.75rem;"><?= htmlspecialchars($claim['email'] ?? '-') ?></td>
                <td style="padding: 0.5rem; border: 1px solid #ddd;"><?= htmlspecialchars($claim['jmeno'] ?? '-') ?></td>
                <td style="padding: 0.5rem; border: 1px solid #ddd; font-weight: bold; <?= $isNull ? 'color: red;' : '' ?>"><?= $isNull ? 'NULL ⚠️' : $claim['created_by'] ?></td>
                <td style="padding: 0.5rem; border: 1px solid #ddd;"><?= $claim['created_by_role'] ?? '-' ?></td>
              </tr>
              <?php endforeach; ?>
            </table>
          </div>
        </div>

      </div>

      <!-- CZ/SK FAKTURACE MIGRACE -->
      <div style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #059669;">
        <div style="margin-bottom: 1rem;">
          <h3 style="margin: 0 0 0.5rem 0; font-size: 1.2rem; color: #333; font-weight: 600; letter-spacing: 0.05em;">🇨🇿🇸🇰 CZ/SK FAKTURACE</h3>
          <p style="margin: 0; color: #666; font-size: 0.9rem;">Přidání sloupce pro rozlišení CZ a SK zákazníků</p>
        </div>

        <?php
        // Kontrola zda sloupec fakturace_firma existuje
        $fakturaceInstalled = false;
        try {
          $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'fakturace_firma'");
          $fakturaceInstalled = $stmt->rowCount() > 0;
        } catch (PDOException $e) {
          // Ignorovat chybu
        }
        ?>

        <?php if ($fakturaceInstalled): ?>
          <!-- JIŽ NAINSTALOVÁNO -->
          <div style="background: #e8f5e9; border: 2px solid #4CAF50; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            <div style="font-size: 0.85rem; color: #2e7d32; font-weight: 600; margin-bottom: 0.5rem;">
              ✓ SLOUPEC JE NAINSTALOVÁN
            </div>
            <div style="font-size: 0.8rem; color: #555;">
              Sloupec <code>fakturace_firma</code> existuje v tabulce <code>wgs_reklamace</code>.
            </div>
          </div>

          <div style="margin-bottom: 1rem;">
            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem;">
              <strong>Co je aktivní:</strong>
            </div>
            <ul style="margin: 0; padding-left: 1.5rem; font-size: 0.85rem; color: #666;">
              <li>Sloupec <code>fakturace_firma VARCHAR(2)</code></li>
              <li>Index pro rychlé vyhledávání</li>
              <li>Výchozí hodnota: 'CZ'</li>
              <li>Zobrazení v seznamu a protokolu</li>
            </ul>
          </div>

        <?php else: ?>
          <!-- JEŠTĚ NENÍ NAINSTALOVÁNO -->
          <div style="background: #fff3cd; border: 2px solid #ffc107; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            <div style="font-size: 0.85rem; color: #856404; font-weight: 600; margin-bottom: 0.5rem;">
              ⚠️ VYŽADUJE INSTALACI
            </div>
            <div style="font-size: 0.8rem; color: #555;">
              Spusťte SQL migraci pro přidání sloupce.
            </div>
          </div>

          <div style="margin-bottom: 1rem;">
            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem;">
              <strong>SQL příkaz pro migraci:</strong>
            </div>
            <div style="background: #f5f5f5; padding: 1rem; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 0.75rem; overflow-x: auto; border: 1px solid #ddd;">
              <code>ALTER TABLE wgs_reklamace<br>
ADD COLUMN IF NOT EXISTS fakturace_firma VARCHAR(2) DEFAULT 'CZ'<br>
COMMENT 'CZ nebo SK firma pro fakturaci';<br><br>

CREATE INDEX IF NOT EXISTS idx_fakturace_firma<br>
ON wgs_reklamace(fakturace_firma);<br><br>

UPDATE wgs_reklamace<br>
SET fakturace_firma = 'CZ'<br>
WHERE fakturace_firma IS NULL OR fakturace_firma = '';</code>
            </div>
          </div>

          <div style="margin-bottom: 1rem;">
            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem;">
              <strong>Jak spustit:</strong>
            </div>
            <ol style="margin: 0; padding-left: 1.5rem; font-size: 0.85rem; color: #666;">
              <li>Otevřít PHPMyAdmin nebo MySQL klienta</li>
              <li>Vybrat databázi</li>
              <li>Zkopírovat SQL příkaz výše</li>
              <li>Spustit jako SQL dotaz</li>
            </ol>
          </div>

          <div style="margin-bottom: 1rem;">
            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem;">
              <strong>Dokumentace:</strong>
            </div>
            <div style="font-size: 0.8rem; color: #666;">
              📄 Soubor: <code>CZ_SK_FAKTURACE_README.md</code><br>
              📄 Migrace: <code>migration_add_fakturace_firma.sql</code>
            </div>
          </div>

        <?php endif; ?>

        <button
          onclick="location.reload()"
          style="width: 100%; padding: 0.875rem 0.75rem; background: #059669; color: white; border: 2px solid #059669; border-radius: 0; font-weight: 600; cursor: pointer; letter-spacing: 0.05em; text-transform: uppercase; font-size: 0.8rem; transition: all 0.3s;"
          onmouseover="this.style.background='#047857'"
          onmouseout="this.style.background='#059669'"
        >
          <?= $fakturaceInstalled ? 'REFRESH STATUS' : 'ZKONTROLOVAT PO INSTALACI' ?>
        </button>
      </div>

      <!-- TESTOVÁNÍ ROLÍ -->
      <div style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #000;">
        <div style="margin-bottom: 1rem;">
          <h3 style="margin: 0 0 0.5rem 0; font-size: 1.2rem; color: #333; font-weight: 600; letter-spacing: 0.05em;">TESTOVÁNÍ ROLÍ</h3>
          <p style="margin: 0; color: #666; font-size: 0.9rem;">Simulace různých uživatelských rolí pro testování přístupů</p>
        </div>

        <?php
        // OBSLUHA SIMULACE ROLÍ - DYNAMICKÉ NAČÍTÁNÍ SKUTEČNÝCH UŽIVATELŮ
        if (!isset($_SESSION['_original_admin_session'])) {
            $_SESSION['_original_admin_session'] = [
                'user_id' => $_SESSION['user_id'] ?? null,
                'user_email' => $_SESSION['user_email'] ?? null,
                'role' => $_SESSION['role'] ?? null,
                'is_admin' => $_SESSION['is_admin'] ?? null,
                'name' => $_SESSION['user_name'] ?? null,
            ];
        }

        $roleAction = $_POST['role_action'] ?? null;

        if ($roleAction === 'simulate') {
            $simulateUserId = (int)($_POST['simulate_user_id'] ?? 0);

            if ($simulateUserId > 0) {
                // Načíst skutečného uživatele z databáze
                $stmt = $pdo->prepare("SELECT id, email, name, role FROM wgs_users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $simulateUserId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['is_admin'] = (strtolower($user['role']) === 'admin');
                    $_SESSION['user_name'] = $user['name'] . ' (TEST)';
                    $_SESSION['_simulating'] = strtolower($user['role']);

                    header('Location: admin.php?tab=tools&simulated=' . urlencode($user['name']));
                    exit;
                }
            }
        }

        if ($roleAction === 'reset') {
            $originalSession = $_SESSION['_original_admin_session'];
            $_SESSION['user_id'] = $originalSession['user_id'];
            $_SESSION['user_email'] = $originalSession['user_email'];  // OPRAVENO: user_email
            $_SESSION['role'] = $originalSession['role'];
            $_SESSION['is_admin'] = $originalSession['is_admin'];
            $_SESSION['user_name'] = $originalSession['name'];  // OPRAVENO: user_name
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
          <div style="color: #555;">user_email: <strong><?= isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : 'NULL' ?></strong></div>
          <div style="color: #555;">role: <strong><?= isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'NULL' ?></strong></div>
          <div style="color: #555;">is_admin: <strong><?= isset($_SESSION['is_admin']) && $_SESSION['is_admin'] ? 'true' : 'false' ?></strong></div>
          <?php if ($currentSimulation): ?>
          <div style="color: #f57c00;">_simulating: <strong><?= htmlspecialchars($currentSimulation) ?></strong></div>
          <?php endif; ?>
        </div>

        <!-- Role výběr - VÝBĚR KONKRÉTNÍHO UŽIVATELE -->
        <?php
        // Načíst všechny uživatele podle rolí
        $stmt = $pdo->query("SELECT id, email, name, role FROM wgs_users ORDER BY role, name");
        $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Seskupit podle rolí
        $usersByRole = [
            'admin' => [],
            'prodejce' => [],
            'technik' => []
        ];

        foreach ($allUsers as $u) {
            $uRole = strtolower(trim($u['role'] ?? 'user'));
            if ($uRole === 'admin') {
                $usersByRole['admin'][] = $u;
            } elseif (in_array($uRole, ['prodejce', 'user'])) {
                $usersByRole['prodejce'][] = $u;
            } elseif (in_array($uRole, ['technik', 'technician'])) {
                $usersByRole['technik'][] = $u;
            }
        }
        ?>

        <form method="POST" style="margin-bottom: 1rem;">
          <input type="hidden" name="role_action" value="simulate">

          <div style="margin-bottom: 0.75rem;">
            <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">
              Vyber uživatele pro simulaci:
            </label>
            <select name="simulate_user_id" required style="width: 100%; padding: 0.75rem; border: 2px solid #000; font-family: 'Poppins', sans-serif; font-size: 0.9rem; background: white;">
              <option value="">-- Vyber uživatele --</option>

              <?php if (!empty($usersByRole['admin'])): ?>
              <optgroup label="⚙️ ADMINISTRÁTOŘI">
                <?php foreach ($usersByRole['admin'] as $u): ?>
                <option value="<?= $u['id'] ?>">
                  <?= htmlspecialchars($u['name'] ?? $u['email']) ?> (<?= $u['email'] ?>)
                </option>
                <?php endforeach; ?>
              </optgroup>
              <?php endif; ?>

              <?php if (!empty($usersByRole['prodejce'])): ?>
              <optgroup label="👤 PRODEJCI">
                <?php foreach ($usersByRole['prodejce'] as $u): ?>
                <option value="<?= $u['id'] ?>">
                  <?= htmlspecialchars($u['name'] ?? $u['email']) ?> (<?= $u['email'] ?>)
                </option>
                <?php endforeach; ?>
              </optgroup>
              <?php endif; ?>

              <?php if (!empty($usersByRole['technik'])): ?>
              <optgroup label="🔧 TECHNICI">
                <?php foreach ($usersByRole['technik'] as $u): ?>
                <option value="<?= $u['id'] ?>">
                  <?= htmlspecialchars($u['name'] ?? $u['email']) ?> (<?= $u['email'] ?>)
                </option>
                <?php endforeach; ?>
              </optgroup>
              <?php endif; ?>
            </select>
          </div>

          <button type="submit" style="width: 100%; padding: 0.875rem; background: #000; color: white; border: 2px solid #000; font-size: 0.85rem; font-weight: 700; cursor: pointer; letter-spacing: 0.05em; text-transform: uppercase; white-space: normal; line-height: 1.3;">
            SIMULOVAT TOHOTO UŽIVATELE
          </button>
        </form>

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
            <a href="/install_admin_control_center.php" target="_blank" style="display: inline-block; padding: 0.5rem 0.75rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; transition: all 0.3s; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);">🎨 INSTALOVAT CONTROL CENTER</a>
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
<script src="assets/js/csrf-auto-inject.js"></script>
<script src="assets/js/logger.js"></script>
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
