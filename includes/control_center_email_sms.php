<?php
/**
 * Control Center - Email & SMS Management
 * Sjednocená karta pro správu emailů, SMS a SMTP
 */

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized');
}

$pdo = getDbConnection();
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';

// Načíst aktuální sekci
$currentSection = $_GET['section'] ?? 'overview';

// Načtení SMTP konfigurace z databáze
$smtpConfigs = [];
try {
    $stmt = $pdo->query("SELECT * FROM wgs_system_config WHERE config_group = 'email' ORDER BY config_key");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
        $smtpConfigs[] = $row;
    }
} catch (PDOException $e) {
    $smtpConfigs = [];
}

// Statistiky emailové fronty
$emailStats = ['all' => 0, 'sent' => 0, 'pending' => 0, 'failed' => 0];
try {
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM wgs_email_queue GROUP BY status");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $emailStats[$row['status']] = (int)$row['count'];
        $emailStats['all'] += (int)$row['count'];
    }
} catch (PDOException $e) {}
?>

<link rel="stylesheet" href="/assets/css/control-center.css">

<div class="control-detail active">
    <?php if (!$embedMode): ?>
    <!-- Header -->
    <div class="control-detail-header">
        <button class="control-detail-back" onclick="window.location.href='admin.php?tab=control_center'">
            <span>‹</span>
            <span>Zpět</span>
        </button>
        <h2 class="control-detail-title">📧 Email & SMS Management</h2>
    </div>
    <?php endif; ?>

    <div class="control-detail-content">

        <!-- Navigation Tabs -->
        <div class="cc-tabs">
            <button class="cc-tab <?= $currentSection === 'overview' ? 'active' : '' ?>"
                    onclick="switchSection('overview')">
                Přehled
            </button>
            <button class="cc-tab <?= $currentSection === 'smtp' ? 'active' : '' ?>"
                    onclick="switchSection('smtp')">
                SMTP Konfigurace
            </button>
            <button class="cc-tab <?= $currentSection === 'templates' ? 'active' : '' ?>"
                    onclick="switchSection('templates')">
                Email šablony
            </button>
            <button class="cc-tab <?= $currentSection === 'sms' ? 'active' : '' ?>"
                    onclick="switchSection('sms')">
                SMS
            </button>
            <button class="cc-tab <?= $currentSection === 'management' ? 'active' : '' ?>"
                    onclick="switchSection('management')">
                Email Management
            </button>
        </div>

        <!-- PŘEHLED -->
        <div id="section-overview" class="cc-section <?= $currentSection === 'overview' ? 'active' : '' ?>">
            <h3 style="margin-bottom: 1.5rem;">Rychlý přehled</h3>

            <!-- Stats Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                <div class="cc-alert info" style="margin: 0;">
                    <div class="cc-alert-content">
                        <div class="cc-alert-title" style="font-size: 2rem;"><?= $emailStats['all'] ?></div>
                        <div class="cc-alert-message">Celkem emailů</div>
                    </div>
                </div>
                <div class="cc-alert success" style="margin: 0;">
                    <div class="cc-alert-content">
                        <div class="cc-alert-title" style="font-size: 2rem;"><?= $emailStats['sent'] ?></div>
                        <div class="cc-alert-message">✅ Odesláno</div>
                    </div>
                </div>
                <div class="cc-alert warning" style="margin: 0;">
                    <div class="cc-alert-content">
                        <div class="cc-alert-title" style="font-size: 2rem;"><?= $emailStats['pending'] ?></div>
                        <div class="cc-alert-message">⏳ Ve frontě</div>
                    </div>
                </div>
                <div class="cc-alert danger" style="margin: 0;">
                    <div class="cc-alert-content">
                        <div class="cc-alert-title" style="font-size: 2rem;"><?= $emailStats['failed'] ?></div>
                        <div class="cc-alert-message">❌ Selhalo</div>
                    </div>
                </div>
            </div>

            <!-- Rychlé odkazy -->
            <div class="setting-group">
                <h3 class="setting-group-title">Rychlé akce</h3>

                <div class="setting-item" onclick="window.location.href='email_management.php'" style="cursor: pointer;">
                    <div class="setting-item-left">
                        <div class="setting-item-label">📧 Email Management</div>
                        <div class="setting-item-description">Kompletní správa emailové fronty - historie, čekající, selhavší</div>
                    </div>
                    <div class="setting-item-right">
                        <span style="font-size: 1.5rem;">›</span>
                    </div>
                </div>

                <div class="setting-item" onclick="window.location.href='cleanup_failed_emails.php'" style="cursor: pointer;">
                    <div class="setting-item-left">
                        <div class="setting-item-label">🧹 Vyčistit selhavší emaily</div>
                        <div class="setting-item-description">Odstranit všechny emaily se statusem 'failed' z fronty</div>
                    </div>
                    <div class="setting-item-right">
                        <span style="font-size: 1.5rem;">›</span>
                    </div>
                </div>

                <div class="setting-item" onclick="switchSection('smtp')" style="cursor: pointer;">
                    <div class="setting-item-left">
                        <div class="setting-item-label">⚙️ SMTP Konfigurace</div>
                        <div class="setting-item-description">Nastavení SMTP serveru pro odesílání emailů</div>
                    </div>
                    <div class="setting-item-right">
                        <span style="font-size: 1.5rem;">›</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SMTP KONFIGURACE -->
        <div id="section-smtp" class="cc-section <?= $currentSection === 'smtp' ? 'active' : '' ?>">
            <?php if (empty($smtpConfigs)): ?>
                <div class="cc-alert warning">
                    <div class="cc-alert-icon">⚠️</div>
                    <div class="cc-alert-content">
                        <div class="cc-alert-title">SMTP konfigurace nenalezena</div>
                        <div class="cc-alert-message">
                            Tabulka wgs_system_config neobsahuje SMTP nastavení (group='email').
                            Spusťte instalaci SMTP nebo přidejte konfiguraci ručně.
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="setting-group">
                    <h3 class="setting-group-title">Email (SMTP) Konfigurace</h3>

                    <?php foreach ($smtpConfigs as $config): ?>
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
                                                Zobrazit
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
                            <div class="setting-item-label">Test Email</div>
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
        </div>

        <!-- EMAIL ŠABLONY -->
        <div id="section-templates" class="cc-section <?= $currentSection === 'templates' ? 'active' : '' ?>">
            <div class="cc-alert info">
                <div class="cc-alert-icon">ℹ️</div>
                <div class="cc-alert-content">
                    <div class="cc-alert-title">Email šablony</div>
                    <div class="cc-alert-message">
                        Tato sekce je ve vývoji. Pro správu emailů použijte Email Management.
                    </div>
                </div>
            </div>
        </div>

        <!-- SMS -->
        <div id="section-sms" class="cc-section <?= $currentSection === 'sms' ? 'active' : '' ?>">
            <div class="cc-alert info">
                <div class="cc-alert-icon">ℹ️</div>
                <div class="cc-alert-content">
                    <div class="cc-alert-title">SMS Notifikace</div>
                    <div class="cc-alert-message">
                        SMS funkce je ve vývoji. Prozatím použijte pouze emailové notifikace.
                    </div>
                </div>
            </div>
        </div>

        <!-- EMAIL MANAGEMENT -->
        <div id="section-management" class="cc-section <?= $currentSection === 'management' ? 'active' : '' ?>">
            <div class="cc-alert success">
                <div class="cc-alert-content">
                    <div class="cc-alert-title">Email Management</div>
                    <div class="cc-alert-message">
                        Pokročilá správa emailů je dostupná na samostatné stránce.
                        <div style="margin-top: 1rem;">
                            <a href="email_management.php" class="cc-btn cc-btn-primary">
                                Otevřít Email Management
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
.cc-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid #e0e0e0;
    overflow-x: auto;
    flex-wrap: wrap;
}

.cc-tab {
    padding: 0.75rem 1.5rem;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    font-size: 0.9rem;
    font-weight: 500;
    color: #666;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}

.cc-tab:hover {
    color: #2D5016;
    background: #f5f5f5;
}

.cc-tab.active {
    color: #2D5016;
    border-bottom-color: #2D5016;
    font-weight: 600;
}

.cc-section {
    display: none;
}

.cc-section.active {
    display: block;
}
</style>

<script>
// Switch mezi sekcemi
function switchSection(section) {
    // Update URL
    const url = new URL(window.location);
    url.searchParams.set('section', section);
    window.history.pushState({}, '', url);

    // Update tabs
    document.querySelectorAll('.cc-tab').forEach(tab => tab.classList.remove('active'));
    event.target?.classList.add('active');

    // Update sections
    document.querySelectorAll('.cc-section').forEach(sec => sec.classList.remove('active'));
    document.getElementById('section-' + section)?.classList.add('active');
}

// Toggle password visibility
function togglePasswordVisibility(configId) {
    const input = document.getElementById('config-' + configId);
    const btn = event.target;

    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = 'Skrýt';
    } else {
        input.type = 'password';
        btn.textContent = 'Zobrazit';
    }
}

// Save config
async function saveConfig(configId, configKey) {
    const input = document.getElementById('config-' + configId);
    const status = document.getElementById('save-status-' + configId);
    const value = input.value;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    try {
        const response = await fetch('/api/control_center_api.php?action=save_system_config', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: csrfToken,
                config_id: configId,
                config_key: configKey,
                config_value: value
            })
        });

        const result = await response.json();

        if (result.status === 'success') {
            status.style.display = 'block';
            status.style.color = '#28a745';
            status.textContent = '✓ Uloženo';
            setTimeout(() => { status.style.display = 'none'; }, 3000);
        } else {
            throw new Error(result.message || 'Chyba při ukládání');
        }
    } catch (error) {
        status.style.display = 'block';
        status.style.color = '#dc3545';
        status.textContent = '✗ ' + error.message;
    }
}

// Send test email
async function sendTestEmail() {
    const emailInput = document.getElementById('test-email');
    const email = emailInput.value.trim();

    if (!email) {
        alert('Zadejte email pro test');
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    try {
        const response = await fetch('/api/control_center_api.php?action=send_test_email', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: csrfToken,
                test_email: email
            })
        });

        const result = await response.json();

        if (result.status === 'success') {
            alert('✓ Testovací email byl odeslán na ' + email);
        } else {
            throw new Error(result.message || 'Chyba při odesílání');
        }
    } catch (error) {
        alert('✗ Chyba: ' + error.message);
    }
}
</script>
