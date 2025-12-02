<?php
/**
 * Control Center - Konfigurace
 * SMTP, API klíče, security settings
 */

require_once __DIR__ . '/../init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized');
}

$pdo = getDbConnection();
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';

// Check if accessed directly (not through admin.php)
$directAccess = !defined('ADMIN_PHP_LOADED');

// If embed mode, output full HTML structure
if ($embedMode && $directAccess):
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfigurace - WGS Admin</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="embed-mode">
<?php
endif;

// Načtení konfigurace (pouze 'system' - ostatní skupiny jsou v jiných kartách)
$configs = [];
try {
    $stmt = $pdo->query("SELECT * FROM wgs_system_config WHERE config_group = 'system' ORDER BY config_key");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $group = $row['config_group'];
        if (!isset($configs[$group])) {
            $configs[$group] = [];
        }

        // Mask sensitive values
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

// Group names (EMAIL/SMTP přesunuto do "Email & SMS", API/Security přesunuto do "Security")
$groupNames = [
    'system' => 'Systém'
];
?>

<?php if (!$directAccess): ?>
<link rel="stylesheet" href="/assets/css/admin.css">
<?php endif; ?>

<div class="control-detail active">
    <?php if (!$directAccess): ?>
    <!-- Header -->
    <div class="control-detail-header">
        <button class="control-detail-back" data-href="admin.php">
            <span>‹</span>
            <span>Zpět</span>
        </button>
        <h2 class="control-detail-title">Konfigurace systému</h2>
    </div>
    <?php endif; ?>

    <div class="control-detail-content">

        <!-- Warning Alert -->
        <div class="cc-alert warning">
            
            <div class="cc-alert-content">
                <div class="cc-alert-title">Důležité upozornění</div>
                <div class="cc-alert-message">
                    Některá nastavení vyžadují restart aplikace nebo jsou read-only.
                    Citlivé hodnoty (hesla, API klíče) jsou maskované (••••).
                </div>
            </div>
        </div>

        <?php if (empty($configs)): ?>
            <div class="cc-alert info">
                <div class="cc-alert-content">
                    <div class="cc-alert-title">Žádná konfigurace</div>
                    <div class="cc-alert-message">
                        Tabulka wgs_system_config je prázdná. Spusťte migraci.
                    </div>
                </div>
            </div>
        <?php else: ?>

            <!-- SYSTEM -->
            <?php if (isset($configs['system'])): ?>
                <div class="setting-group">
                    <h3 class="setting-group-title"><?= $groupNames['system'] ?></h3>

                    <?php foreach ($configs['system'] as $config): ?>
                        <div class="setting-item">
                            <div class="setting-item-left">
                                <div class="setting-item-label">
                                    <?= htmlspecialchars($config['config_key']) ?>
                                </div>
                                <div class="setting-item-description">
                                    <?= htmlspecialchars($config['description']) ?>
                                </div>
                            </div>
                            <div class="setting-item-right">
                                <?php if ($config['is_editable']): ?>
                                    <?php if ($config['config_key'] === 'maintenance_mode'): ?>
                                        <!-- Toggle switch -->
                                        <label class="cc-toggle">
                                            <input type="checkbox"
                                                   id="config-<?= $config['id'] ?>"
                                                   <?= $config['config_value'] == '1' ? 'checked' : '' ?>
                                                   onchange="saveConfig(<?= $config['id'] ?>, '<?= htmlspecialchars($config['config_key']) ?>')">
                                            <span class="cc-toggle-slider"></span>
                                        </label>
                                    <?php else: ?>
                                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                                            <input type="text"
                                                   class="cc-input"
                                                   id="config-<?= $config['id'] ?>"
                                                   value="<?= htmlspecialchars($config['config_value']) ?>">
                                            <button class="cc-btn cc-btn-sm cc-btn-primary"
                                                    data-action="saveConfig"
                                                    data-id="<?= $config['id'] ?>"
                                                    data-key="<?= htmlspecialchars($config['config_key']) ?>">
                                                Uložit
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                    <div id="save-status-<?= $config['id'] ?>" style="margin-top: 0.5rem; display: none;"></div>
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

        <?php endif; ?>

        <!-- Info -->
        <div class="cc-alert info">
            
            <div class="cc-alert-content">
                <div class="cc-alert-title">Poznámka k restartu</div>
                <div class="cc-alert-message">
                    Nastavení označená "Restart" vyžadují restart PHP/Apache nebo reload .env souboru.
                    Kontaktujte administrátora serveru pro aplikaci změn.
                </div>
            </div>
        </div>

    </div>
</div>

<script src="/assets/js/csrf-auto-inject.min.js"></script>
<script>
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

// Save config
/**
 * SaveConfig
 */
async function saveConfig(configId, configKey) {
    const input = document.getElementById(`config-${configId}`);
    const statusEl = document.getElementById(`save-status-${configId}`);

    let value;
    if (input.type === 'checkbox') {
        value = input.checked ? '1' : '0';
    } else {
        value = input.value;
    }

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
            statusEl.innerHTML = '<span style="color: #28A745;"> Uloženo!</span>';
            setTimeout(() => {
                statusEl.style.display = 'none';
            }, 2000);
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        statusEl.innerHTML = '<span style="color: #DC3545;">Chyba: ' + error.message + '</span>';
    }
}

console.log('Configuration section loaded (Email/SMTP moved to Email & SMS tab)');

// ACTION REGISTRY - Step 113
if (typeof Utils !== 'undefined' && Utils.registerAction) {
    Utils.registerAction('saveConfig', (el, data) => {
        if (data.id && data.key) {
            saveConfig(data.id, data.key);
        }
    });
}
</script>


<?php if ($embedMode && $directAccess): ?>
</body>
</html>
<?php endif; ?>
