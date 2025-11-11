<?php
/**
 * Control Center - Konfigurace
 * SMTP, API klíče, security settings
 */

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized');
}

$pdo = getDbConnection();

// Načtení konfigurace
$configs = [];
try {
    $stmt = $pdo->query("SELECT * FROM wgs_system_config ORDER BY config_group, config_key");
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

// Group names
$groupNames = [
    'email' => '📧 Email (SMTP)',
    'api_keys' => '🔑 API Klíče',
    'security' => '🔒 Bezpečnost',
    'system' => '⚙️ Systém'
];
?>

<link rel="stylesheet" href="/assets/css/control-center.css">

<div class="control-detail active">
    <!-- Header -->
    <div class="control-detail-header">
        <button class="control-detail-back" onclick="window.location.href='admin.php?tab=control_center'">
            <span>‹</span>
            <span>Zpět</span>
        </button>
        <h2 class="control-detail-title">⚙️ Konfigurace</h2>
    </div>

    <div class="control-detail-content">

        <!-- Warning Alert -->
        <div class="cc-alert warning">
            <div class="cc-alert-icon">⚠️</div>
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
                <div class="cc-alert-icon">ℹ️</div>
                <div class="cc-alert-content">
                    <div class="cc-alert-title">Žádná konfigurace</div>
                    <div class="cc-alert-message">
                        Tabulka wgs_system_config je prázdná. Spusťte migraci.
                    </div>
                </div>
            </div>
        <?php else: ?>

            <!-- EMAIL / SMTP -->
            <?php if (isset($configs['email'])): ?>
                <div class="setting-group">
                    <h3 class="setting-group-title"><?= $groupNames['email'] ?></h3>

                    <?php foreach ($configs['email'] as $config): ?>
                        <div class="setting-item">
                            <div class="setting-item-left">
                                <div class="setting-item-label">
                                    <?= htmlspecialchars($config['config_key']) ?>
                                    <?php if ($config['requires_restart']): ?>
                                        <span style="background: #FFC107; color: #000; padding: 2px 6px; border-radius: 8px; font-size: 0.7rem; margin-left: 0.5rem;">
                                            Restart
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="setting-item-description">
                                    <?= htmlspecialchars($config['description']) ?>
                                </div>
                            </div>
                            <div class="setting-item-right" style="min-width: 250px;">
                                <?php if ($config['is_editable']): ?>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <?php if ($config['is_sensitive']): ?>
                                            <input type="password"
                                                   class="cc-input"
                                                   id="config-<?= $config['id'] ?>"
                                                   value="<?= htmlspecialchars($config['config_value']) ?>"
                                                   placeholder="<?= $config['config_value_display'] ?>"
                                                   style="flex: 1;">
                                            <button class="cc-btn cc-btn-sm cc-btn-secondary"
                                                    onclick="togglePasswordVisibility(<?= $config['id'] ?>)">
                                                👁️
                                            </button>
                                        <?php else: ?>
                                            <input type="text"
                                                   class="cc-input"
                                                   id="config-<?= $config['id'] ?>"
                                                   value="<?= htmlspecialchars($config['config_value']) ?>"
                                                   style="flex: 1;">
                                        <?php endif; ?>
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

                    <!-- Test Email -->
                    <div class="setting-item" style="background: #f8f9fa;">
                        <div class="setting-item-left">
                            <div class="setting-item-label">📧 Test Email</div>
                            <div class="setting-item-description">Odeslat testovací email pro ověření SMTP nastavení</div>
                        </div>
                        <div class="setting-item-right">
                            <input type="email"
                                   id="test-email"
                                   class="cc-input"
                                   placeholder="vas@email.cz"
                                   style="width: 200px; margin-right: 0.5rem;">
                            <button class="cc-btn cc-btn-sm cc-btn-success" onclick="sendTestEmail()">
                                Odeslat test
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- API KEYS -->
            <?php if (isset($configs['api_keys'])): ?>
                <div class="setting-group">
                    <h3 class="setting-group-title"><?= $groupNames['api_keys'] ?></h3>

                    <?php foreach ($configs['api_keys'] as $config): ?>
                        <div class="setting-item">
                            <div class="setting-item-left">
                                <div class="setting-item-label">
                                    <?= htmlspecialchars($config['config_key']) ?>
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
                                               style="flex: 1; font-family: monospace;">
                                        <button class="cc-btn cc-btn-sm cc-btn-secondary"
                                                onclick="togglePasswordVisibility(<?= $config['id'] ?>)">
                                            👁️
                                        </button>
                                        <button class="cc-btn cc-btn-sm cc-btn-primary"
                                                onclick="saveConfig(<?= $config['id'] ?>, '<?= htmlspecialchars($config['config_key']) ?>')">
                                            💾
                                        </button>
                                    </div>
                                    <div id="save-status-<?= $config['id'] ?>" style="margin-top: 0.5rem; display: none; font-size: 0.85rem;"></div>
                                <?php else: ?>
                                    <span style="color: #999; font-family: monospace;">
                                        <?= $config['config_value_display'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- SECURITY -->
            <?php if (isset($configs['security'])): ?>
                <div class="setting-group">
                    <h3 class="setting-group-title"><?= $groupNames['security'] ?></h3>

                    <?php foreach ($configs['security'] as $config): ?>
                        <div class="setting-item">
                            <div class="setting-item-left">
                                <div class="setting-item-label">
                                    <?= htmlspecialchars($config['config_key']) ?>
                                    <?php if ($config['requires_restart']): ?>
                                        <span style="background: #FFC107; color: #000; padding: 2px 6px; border-radius: 8px; font-size: 0.7rem; margin-left: 0.5rem;">
                                            Restart
                                        </span>
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
                                               style="width: 100px;">
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
            <?php endif; ?>

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
                                                    onclick="saveConfig(<?= $config['id'] ?>, '<?= htmlspecialchars($config['config_key']) ?>')">
                                                💾
                                            </button>
                                        </div>
                                    <?php endif; ?>
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

        <?php endif; ?>

        <!-- Info -->
        <div class="cc-alert info">
            <div class="cc-alert-icon">💡</div>
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

<script src="/assets/js/csrf-auto-inject.js"></script>
<script>
// Toggle password visibility
function togglePasswordVisibility(configId) {
    const input = document.getElementById(`config-${configId}`);
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}

// Save config
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

// Send test email
async function sendTestEmail() {
    const emailInput = document.getElementById('test-email');
    const email = emailInput.value.trim();

    if (!email || !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
        alert('Zadejte platnou emailovou adresu');
        return;
    }

    const btn = event.target;
    const originalText = btn.textContent;
    btn.textContent = 'Odesílám...';
    btn.disabled = true;

    try {
        const csrfToken = typeof getCSRFToken === 'function' ? await getCSRFToken() : null;

        const response = await fetch('/api/control_center_api.php?action=send_test_email', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email: email,
                csrf_token: csrfToken
            })
        });

        const result = await response.json();

        if (result.status === 'success') {
            alert('✅ Testovací email byl úspěšně odeslán na ' + email);
            emailInput.value = '';
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        alert('❌ Chyba: ' + error.message);
    } finally {
        btn.textContent = originalText;
        btn.disabled = false;
    }
}

console.log('✅ Configuration section loaded');
</script>
