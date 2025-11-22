<?php
/**
 * Control Center - Email & SMS Management
 * Sjednocená karta pro správu emailů, SMS a SMTP
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
    <title>Email & SMS - WGS Admin</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="embed-mode">
<?php
endif;

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

// Load email templates for templates tab
$emailSablony = [];
try {
    $stmt = $pdo->query("
        SELECT
            id, name, description, trigger_event, recipient_type,
            type, subject, template, active, created_at, updated_at
        FROM wgs_notifications
        ORDER BY name ASC
    ");
    $emailSablony = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $emailSablony = [];
}

// Load email queue for management tab
$filterStatus = $_GET['filter'] ?? 'all';
$emaily = [];
try {
    $whereClause = '';
    if ($filterStatus !== 'all') {
        $whereClause = "WHERE status = :status";
    }

    $sql = "
        SELECT
            id, to_email, subject, body, status, retry_count,
            last_error, created_at, updated_at, sent_at
        FROM wgs_email_queue
        $whereClause
        ORDER BY created_at DESC
        LIMIT 100
    ";

    $stmt = $pdo->prepare($sql);
    if ($filterStatus !== 'all') {
        $stmt->execute(['status' => $filterStatus]);
    } else {
        $stmt->execute();
    }
    $emaily = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $emaily = [];
}
?>

<?php if (!$directAccess): ?>
<link rel="stylesheet" href="/assets/css/admin.css">
<?php endif; ?>

<div class="control-detail active">
    <?php if (!$directAccess): ?>
    <!-- Header -->
    <div class="control-detail-header">
        <button class="control-detail-back" onclick="window.location.href='admin.php'">
            <span>‹</span>
            <span>Zpět</span>
        </button>
        <h2 class="control-detail-title" style="font-family: 'Poppins', sans-serif; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Email & SMS Management</h2>
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
            <h3 style="margin-bottom: 0.75rem; font-family: 'Poppins', sans-serif; font-size: 0.9rem; font-weight: 600; color: #000; text-transform: uppercase; letter-spacing: 0.5px;">Emailová fronta</h3>

            <!-- Stats Grid - kompaktní -->
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <div style="background: #fff; border: 1px solid #000; padding: 0.4rem 0.75rem; text-align: center; min-width: 90px;">
                    <span style="font-size: 0.9rem; font-weight: 600; font-family: 'Poppins', sans-serif; color: #000;"><?= $emailStats['all'] ?></span>
                    <span style="font-size: 0.7rem; color: #666; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.3px; margin-left: 0.3rem;">Celkem</span>
                </div>
                <div style="background: #fff; border: 1px solid #000; padding: 0.4rem 0.75rem; text-align: center; min-width: 100px;">
                    <span style="font-size: 0.9rem; font-weight: 600; font-family: 'Poppins', sans-serif; color: #000;"><?= $emailStats['sent'] ?></span>
                    <span style="font-size: 0.7rem; color: #666; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.3px; margin-left: 0.3rem;">Odesláno</span>
                </div>
                <div style="background: #fff; border: 1px solid #000; padding: 0.4rem 0.75rem; text-align: center; min-width: 100px;">
                    <span style="font-size: 0.9rem; font-weight: 600; font-family: 'Poppins', sans-serif; color: #000;"><?= $emailStats['pending'] ?></span>
                    <span style="font-size: 0.7rem; color: #666; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.3px; margin-left: 0.3rem;">Ve frontě</span>
                </div>
                <div style="background: #fff; border: 1px solid #000; padding: 0.4rem 0.75rem; text-align: center; min-width: 90px;">
                    <span style="font-size: 0.9rem; font-weight: 600; font-family: 'Poppins', sans-serif; color: #000;"><?= $emailStats['failed'] ?></span>
                    <span style="font-size: 0.7rem; color: #666; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.3px; margin-left: 0.3rem;">Selhalo</span>
                </div>
            </div>
        </div>

        <!-- SMTP KONFIGURACE -->
        <div id="section-smtp" class="cc-section <?= $currentSection === 'smtp' ? 'active' : '' ?>">
            <?php if (empty($smtpConfigs)): ?>
                <div style="background: #f5f5f5; border: 1px solid #000; border-left: 3px solid #000; color: #000; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.85rem; font-family: 'Poppins', sans-serif;">
                    <strong>SMTP konfigurace nenalezena</strong> - Tabulka wgs_system_config neobsahuje SMTP nastavení (group='email'). Spusťte instalaci SMTP.
                </div>
            <?php else: ?>
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="font-size: 0.9rem; font-weight: 600; font-family: 'Poppins', sans-serif; color: #000; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid #000;">Email (SMTP) Konfigurace</h3>

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
                                                    onclick="togglePasswordVisibility(<?= $config['id'] ?>)"
                                                    style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">
                                                Zobrazit
                                            </button>
                                        <?php else: ?>
                                            <input type="text"
                                                   class="cc-input"
                                                   id="config-<?= $config['id'] ?>"
                                                   value="<?= htmlspecialchars($config['config_value']) ?>"
                                                   style="flex: 1; font-size: 0.85rem;">
                                        <?php endif; ?>
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
                            <button class="cc-btn cc-btn-sm cc-btn-primary" onclick="sendTestEmail()" style="font-size: 0.75rem; padding: 0.4rem 0.75rem;">
                                Odeslat test
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- EMAIL ŠABLONY -->
        <div id="section-templates" class="cc-section <?= $currentSection === 'templates' ? 'active' : '' ?>">
            <h3 style="margin-bottom: 0.75rem; font-family: 'Poppins', sans-serif; font-size: 0.9rem; font-weight: 600; color: #000; text-transform: uppercase; letter-spacing: 0.5px;">Email notifikační šablony</h3>

            <div style="background: #f0f9ff; border: 1px solid #0ea5e9; border-left: 3px solid #0ea5e9; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.85rem; font-family: 'Poppins', sans-serif;">
                <strong>Info:</strong> Tyto šablony se automaticky odesílají při různých událostech v systému. Můžete je zapínat/vypínat nebo upravovat v hlavním admin panelu (tab "Notifications").
            </div>

            <?php if (empty($emailSablony)): ?>
                <div style="background: #fff3cd; border: 1px solid #ffc107; border-left: 3px solid #ffc107; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.85rem; font-family: 'Poppins', sans-serif;">
                    <strong>Varování:</strong> Žádné email šablony nenalezeny. Pravděpodobně není nainstalován notifikační systém. Pro instalaci přejděte do sekce "Nástroje" v hlavním admin panelu.
                </div>
            <?php else: ?>
                <!-- Šablony Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1rem;">
                    <?php foreach ($emailSablony as $sablona): ?>
                        <div style="background: #fff; border: 1px solid <?= $sablona['active'] ? '#000' : '#ddd' ?>; padding: 1rem; border-radius: 4px; transition: all 0.2s;">
                            <!-- Header šablony -->
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                                <div style="flex: 1;">
                                    <h4 style="font-family: 'Poppins', sans-serif; font-size: 0.9rem; font-weight: 600; color: #000; margin-bottom: 0.25rem;">
                                        <?= htmlspecialchars($sablona['name']) ?>
                                    </h4>
                                    <p style="font-size: 0.75rem; color: #666; margin: 0;">
                                        <?= htmlspecialchars($sablona['description']) ?>
                                    </p>
                                </div>
                                <span style="display: inline-block; padding: 0.25rem 0.5rem; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid #000; background: <?= $sablona['active'] ? '#000' : '#fff' ?>; color: <?= $sablona['active'] ? '#fff' : '#000' ?>; border-radius: 3px;">
                                    <?= $sablona['active'] ? 'AKTIVNÍ' : 'VYPNUTO' ?>
                                </span>
                            </div>

                            <!-- Informace -->
                            <div style="border-top: 1px solid #e0e0e0; padding-top: 0.75rem; margin-top: 0.75rem;">
                                <div style="display: grid; grid-template-columns: 100px 1fr; gap: 0.5rem; font-size: 0.75rem; font-family: 'Poppins', sans-serif;">
                                    <div style="color: #666;">Spouštěč:</div>
                                    <div style="font-weight: 500; color: #000;">
                                        <?php
                                            $triggerLabels = [
                                                'appointment_confirmed' => 'Potvrzení termínu',
                                                'appointment_assigned' => 'Přiřazení termínu',
                                                'appointment_reminder' => 'Připomínka termínu',
                                                'complaint_created' => 'Nová reklamace',
                                                'complaint_completed' => 'Dokončení zakázky',
                                                'complaint_reopened' => 'Znovu otevřeno'
                                            ];
                                            echo htmlspecialchars($triggerLabels[$sablona['trigger_event']] ?? $sablona['trigger_event']);
                                        ?>
                                    </div>

                                    <div style="color: #666;">Příjemce:</div>
                                    <div style="font-weight: 500; color: #000;">
                                        <?php
                                            $recipientLabels = [
                                                'customer' => 'Zákazník',
                                                'admin' => 'Administrátor',
                                                'technician' => 'Technik',
                                                'seller' => 'Prodejce'
                                            ];
                                            echo htmlspecialchars($recipientLabels[$sablona['recipient_type']] ?? $sablona['recipient_type']);
                                        ?>
                                    </div>

                                    <div style="color: #666;">Předmět:</div>
                                    <div style="font-weight: 500; color: #000;">
                                        <?= htmlspecialchars(substr($sablona['subject'], 0, 40)) ?><?= strlen($sablona['subject']) > 40 ? '...' : '' ?>
                                    </div>

                                    <div style="color: #666;">Aktualizováno:</div>
                                    <div style="color: #666;">
                                        <?= $sablona['updated_at'] ? date('d.m.Y H:i', strtotime($sablona['updated_at'])) : '-' ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Náhled šablony -->
                            <div style="border-top: 1px solid #e0e0e0; padding-top: 0.75rem; margin-top: 0.75rem;">
                                <div style="font-size: 0.7rem; color: #666; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                                    Náhled šablony:
                                </div>
                                <div style="background: #f5f5f5; border: 1px solid #ddd; padding: 0.5rem; font-size: 0.7rem; font-family: monospace; color: #333; max-height: 80px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word; line-height: 1.4;">
                                    <?= htmlspecialchars(substr($sablona['template'], 0, 200)) ?><?= strlen($sablona['template']) > 200 ? '...' : '' ?>
                                </div>
                            </div>

                            <!-- Akce -->
                            <div style="border-top: 1px solid #e0e0e0; padding-top: 0.75rem; margin-top: 0.75rem; text-align: center;">
                                <button onclick="otevritNotifikace('<?= $sablona['id'] ?>')"
                                   style="display: inline-block; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; border-radius: 3px; transition: all 0.2s; border: none; cursor: pointer;">
                                    Upravit šablonu
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Informace o proměnných -->
                <div style="margin-top: 1.5rem; background: #fafafa; border: 1px solid #ddd; padding: 1rem;">
                    <h4 style="font-family: 'Poppins', sans-serif; font-size: 0.85rem; font-weight: 600; color: #000; margin-bottom: 0.75rem;">
                        Dostupné proměnné v šablonách:
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem; font-size: 0.75rem; font-family: monospace;">
                        <div><code style="background: #fff; padding: 0.25rem 0.5rem; border: 1px solid #ddd;">{{customer_name}}</code></div>
                        <div><code style="background: #fff; padding: 0.25rem 0.5rem; border: 1px solid #ddd;">{{customer_email}}</code></div>
                        <div><code style="background: #fff; padding: 0.25rem 0.5rem; border: 1px solid #ddd;">{{customer_phone}}</code></div>
                        <div><code style="background: #fff; padding: 0.25rem 0.5rem; border: 1px solid #ddd;">{{date}}</code></div>
                        <div><code style="background: #fff; padding: 0.25rem 0.5rem; border: 1px solid #ddd;">{{time}}</code></div>
                        <div><code style="background: #fff; padding: 0.25rem 0.5rem; border: 1px solid #ddd;">{{order_id}}</code></div>
                        <div><code style="background: #fff; padding: 0.25rem 0.5rem; border: 1px solid #ddd;">{{address}}</code></div>
                        <div><code style="background: #fff; padding: 0.25rem 0.5rem; border: 1px solid #ddd;">{{product}}</code></div>
                        <div><code style="background: #fff; padding: 0.25rem 0.5rem; border: 1px solid #ddd;">{{description}}</code></div>
                        <div><code style="background: #fff; padding: 0.25rem 0.5rem; border: 1px solid #ddd;">{{technician_name}}</code></div>
                        <div><code style="background: #fff; padding: 0.25rem 0.5rem; border: 1px solid #ddd;">{{seller_name}}</code></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- SMS -->
        <div id="section-sms" class="cc-section <?= $currentSection === 'sms' ? 'active' : '' ?>">
            <div class="cc-alert info">
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

            <!-- Alert -->
            <div id="email-alert" style="display: none; padding: 0.75rem; margin-bottom: 1rem; border: 1px solid #000; font-family: 'Poppins', sans-serif; font-size: 0.85rem;"></div>

            <!-- Filter Stats -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.75rem; margin-bottom: 1rem;">
                <div onclick="filterEmaily('all')" style="background: <?= $filterStatus === 'all' ? '#000' : '#fff' ?>; border: 1px solid #000; padding: 0.75rem; text-align: center; cursor: pointer; transition: all 0.2s;">
                    <div style="font-size: 1.5rem; font-weight: 600; font-family: 'Poppins', sans-serif; color: <?= $filterStatus === 'all' ? '#fff' : '#000' ?>;"><?= $emailStats['all'] ?></div>
                    <div style="font-size: 0.75rem; color: <?= $filterStatus === 'all' ? '#fff' : '#666' ?>; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.5px;">Celkem</div>
                </div>
                <div onclick="filterEmaily('sent')" style="background: <?= $filterStatus === 'sent' ? '#000' : '#fff' ?>; border: 1px solid #000; padding: 0.75rem; text-align: center; cursor: pointer; transition: all 0.2s;">
                    <div style="font-size: 1.5rem; font-weight: 600; font-family: 'Poppins', sans-serif; color: <?= $filterStatus === 'sent' ? '#fff' : '#000' ?>;"><?= $emailStats['sent'] ?></div>
                    <div style="font-size: 0.75rem; color: <?= $filterStatus === 'sent' ? '#fff' : '#666' ?>; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.5px;">Odesláno</div>
                </div>
                <div onclick="filterEmaily('pending')" style="background: <?= $filterStatus === 'pending' ? '#000' : '#fff' ?>; border: 1px solid #000; padding: 0.75rem; text-align: center; cursor: pointer; transition: all 0.2s;">
                    <div style="font-size: 1.5rem; font-weight: 600; font-family: 'Poppins', sans-serif; color: <?= $filterStatus === 'pending' ? '#fff' : '#000' ?>;"><?= $emailStats['pending'] ?></div>
                    <div style="font-size: 0.75rem; color: <?= $filterStatus === 'pending' ? '#fff' : '#666' ?>; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.5px;">Ve frontě</div>
                </div>
                <div onclick="filterEmaily('failed')" style="background: <?= $filterStatus === 'failed' ? '#000' : '#fff' ?>; border: 1px solid #000; padding: 0.75rem; text-align: center; cursor: pointer; transition: all 0.2s;">
                    <div style="font-size: 1.5rem; font-weight: 600; font-family: 'Poppins', sans-serif; color: <?= $filterStatus === 'failed' ? '#fff' : '#000' ?>;"><?= $emailStats['failed'] ?></div>
                    <div style="font-size: 0.75rem; color: <?= $filterStatus === 'failed' ? '#fff' : '#666' ?>; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.5px;">Selhalo</div>
                </div>
            </div>

            <!-- Toolbar -->
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: #f5f5f5; border: 1px solid #000; margin-bottom: 1rem;">
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-family: 'Poppins', sans-serif;">
                        <input type="checkbox" id="select-all-emails" onchange="toggleSelectAllEmails()">
                        <span>Vybrat vše</span>
                    </label>
                    <span style="font-size: 0.85rem; color: #666; font-family: 'Poppins', sans-serif;">
                        Vybráno: <strong id="selected-email-count">0</strong>
                    </span>
                </div>
                <button id="resend-emails-btn" onclick="resendVybraneEmaily()" disabled
                        style="padding: 0.5rem 1rem; background: #000; color: #fff; border: 1px solid #000; font-family: 'Poppins', sans-serif; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; font-size: 0.75rem; transition: all 0.2s;">
                    Znovu odeslat vybrané
                </button>
            </div>

            <!-- Email Table -->
            <?php if (count($emaily) > 0): ?>
            <div style="overflow-x: auto; border: 1px solid #000;">
                <table style="width: 100%; border-collapse: collapse; font-family: 'Poppins', sans-serif;">
                    <thead>
                        <tr>
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd; background: #000; color: #fff; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.7rem; width: 30px;">
                                <input type="checkbox" id="select-all-emails-header" onchange="toggleSelectAllEmails()">
                            </th>
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd; background: #000; color: #fff; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.7rem;">ID</th>
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd; background: #000; color: #fff; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.7rem;">Status</th>
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd; background: #000; color: #fff; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.7rem;">Příjemce</th>
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd; background: #000; color: #fff; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.7rem;">Předmět</th>
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd; background: #000; color: #fff; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.7rem;">Pokusy</th>
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd; background: #000; color: #fff; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.7rem;">Vytvořeno</th>
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd; background: #000; color: #fff; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.7rem;">Odesláno</th>
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd; background: #000; color: #fff; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.7rem;">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emaily as $email): ?>
                        <tr style="transition: background 0.2s;" onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='#fff'">
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;">
                                <input type="checkbox" class="email-checkbox-item" value="<?= $email['id'] ?>" onchange="updateSelectedEmailCount()">
                            </td>
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;"><?= $email['id'] ?></td>
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;">
                                <span style="display: inline-block; padding: 0.25rem 0.5rem; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid #000; background: <?= $email['status'] === 'sent' ? '#000' : '#fff' ?>; color: <?= $email['status'] === 'sent' ? '#fff' : '#000' ?>;">
                                    <?php
                                        if ($email['status'] === 'sent') echo 'SENT';
                                        elseif ($email['status'] === 'pending') echo 'PENDING';
                                        else echo 'FAILED';
                                    ?>
                                </span>
                            </td>
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;"><?= htmlspecialchars($email['to_email']) ?></td>
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;"><?= htmlspecialchars(substr($email['subject'], 0, 40)) ?><?= strlen($email['subject']) > 40 ? '...' : '' ?></td>
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;"><?= $email['retry_count'] ?> / 3</td>
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;"><?= date('d.m.Y H:i', strtotime($email['created_at'])) ?></td>
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;"><?= $email['sent_at'] ? date('d.m.Y H:i', strtotime($email['sent_at'])) : '-' ?></td>
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;">
                                <button class="cc-btn cc-btn-sm cc-btn-link" onclick="toggleEmailDetail(<?= $email['id'] ?>)">
                                    Zobrazit
                                </button>
                                <div id="email-detail-<?= $email['id'] ?>" style="display: none; margin-top: 0.5rem;">
                                    <div style="background: #f5f5f5; border: 1px solid #ddd; padding: 0.5rem; font-size: 0.75rem; max-height: 150px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;">
                                        <strong>Tělo emailu:</strong><br><br>
                                        <?= htmlspecialchars($email['body']) ?>
                                    </div>
                                    <?php if ($email['last_error']): ?>
                                    <div style="background: #fef2f2; border: 1px solid #ef4444; padding: 0.5rem; margin-top: 0.5rem; font-size: 0.75rem; font-family: monospace; color: #991b1b; white-space: pre-wrap; word-wrap: break-word;">
                                        <strong>Chyba:</strong><br>
                                        <?= htmlspecialchars($email['last_error']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 3rem 2rem; color: #888; border: 1px solid #ddd; background: #f5f5f5;">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">-</div>
                <h3 style="font-family: 'Poppins', sans-serif; font-size: 1rem; color: #666; margin-bottom: 0.5rem;">Žádné emaily nenalezeny</h3>
                <p style="font-size: 0.85rem; color: #999;">Pro vybraný filtr neexistují žádné emaily.</p>
            </div>
            <?php endif; ?>

        </div>

    </div>
</div>

<!-- Modal pro editaci email šablony -->
<div id="sablona-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; overflow-y: auto;">
    <div style="max-width: 800px; margin: 2rem auto; background: #fff; border-radius: 8px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        <!-- Header -->
        <div style="padding: 1.5rem; background: #000; color: #fff; display: flex; justify-content: space-between; align-items: center; border-radius: 8px 8px 0 0;">
            <h2 id="sablona-modal-title" style="font-family: 'Poppins', sans-serif; font-size: 1.2rem; font-weight: 600; margin: 0;">Editace email šablony</h2>
            <button onclick="zavritSablonaModal()" style="background: none; border: none; color: #fff; font-size: 2rem; cursor: pointer; line-height: 1;">&times;</button>
        </div>

        <!-- Obsah -->
        <div id="sablona-modal-content" style="padding: 1.5rem;">
            <div style="text-align: center; padding: 2rem; color: #999;">Načítám...</div>
        </div>
    </div>
</div>

<style>
.cc-tabs {
    display: flex;
    gap: 0;
    margin-bottom: 1rem;
    border-bottom: 1px solid #000;
    overflow-x: auto;
    flex-wrap: wrap;
}

.cc-tab {
    padding: 0.5rem 1rem;
    background: #fff;
    border: none;
    border-bottom: 2px solid transparent;
    font-size: 0.85rem;
    font-weight: 500;
    font-family: 'Poppins', sans-serif;
    color: #666;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}

.cc-tab:hover {
    color: #000;
    background: #f5f5f5;
}

.cc-tab.active {
    color: #000;
    border-bottom-color: #000;
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
        const response = await fetch('/api/admin.php?action=save_system_config', {
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
            status.textContent = 'Uloženo';
            setTimeout(() => { status.style.display = 'none'; }, 3000);
        } else {
            throw new Error(result.message || 'Chyba při ukládání');
        }
    } catch (error) {
        status.style.display = 'block';
        status.style.color = '#dc3545';
        status.textContent = 'Chyba: ' + error.message;
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
        const response = await fetch('/api/admin.php?action=send_test_email', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: csrfToken,
                test_email: email
            })
        });

        const result = await response.json();

        if (result.status === 'success') {
            alert('Testovací email byl odeslán na ' + email);
        } else {
            throw new Error(result.message || 'Chyba při odesílání');
        }
    } catch (error) {
        alert('Chyba: ' + error.message);
    }
}

// === EMAIL MANAGEMENT FUNCTIONS ===

// Filter emaily podle statusu
function filterEmaily(status) {
    const url = new URL(window.location);
    url.searchParams.set('filter', status);
    url.searchParams.set('section', 'management');
    window.location.href = url.toString();
}

// Toggle select all emails
function toggleSelectAllEmails() {
    const selectAll = document.getElementById('select-all-emails');
    const checkboxes = document.querySelectorAll('.email-checkbox-item');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateSelectedEmailCount();
}

// Update selected email count
function updateSelectedEmailCount() {
    const checkboxes = document.querySelectorAll('.email-checkbox-item:checked');
    const count = checkboxes.length;
    document.getElementById('selected-email-count').textContent = count;
    document.getElementById('resend-emails-btn').disabled = count === 0;

    // Sync select-all checkbox
    const allCheckboxes = document.querySelectorAll('.email-checkbox-item');
    const selectAll = document.getElementById('select-all-emails');
    if (selectAll) {
        selectAll.checked = count === allCheckboxes.length && count > 0;
    }
}

// Toggle email detail
function toggleEmailDetail(id) {
    const detail = document.getElementById('email-detail-' + id);
    if (detail) {
        detail.style.display = detail.style.display === 'none' ? 'block' : 'none';
    }
}

// Resend selected emails
async function resendVybraneEmaily() {
    const checkboxes = document.querySelectorAll('.email-checkbox-item:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);

    if (ids.length === 0) {
        zobrazEmailAlert('Nejsou vybrány žádné emaily', 'error');
        return;
    }

    if (!confirm(`Opravdu chcete znovu odeslat ${ids.length} emailů?`)) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    try {
        const response = await fetch('/api/email_resend_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                email_ids: ids
            })
        });

        const data = await response.json();

        if (data.status === 'success') {
            zobrazEmailAlert(`Úspěch! ${data.count} emailů bylo přesunuto zpět do fronty.`, 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            zobrazEmailAlert(`Chyba: ${data.message}`, 'error');
        }
    } catch (error) {
        zobrazEmailAlert(`Síťová chyba: ${error.message}`, 'error');
    }
}

// Show email alert
function zobrazEmailAlert(message, type) {
    const alert = document.getElementById('email-alert');
    if (!alert) return;

    alert.textContent = message;
    alert.style.display = 'block';
    alert.style.background = type === 'success' ? '#f0fdf4' : '#fef2f2';
    alert.style.borderColor = type === 'success' ? '#22c55e' : '#ef4444';
    alert.style.color = type === 'success' ? '#15803d' : '#991b1b';

    setTimeout(() => {
        alert.style.display = 'none';
    }, 5000);
}

// Initialize email management when section is loaded
if (document.getElementById('section-management')) {
    updateSelectedEmailCount();
}

// Otevřít modal pro editaci šablony
async function otevritNotifikace(sablonaId) {
    const modal = document.getElementById('sablona-modal');
    const content = document.getElementById('sablona-modal-content');

    // Zobrazit modal
    modal.style.display = 'block';
    content.innerHTML = '<div style="text-align: center; padding: 2rem; color: #999;">Načítám šablonu...</div>';

    try {
        // Načíst data šablony z databáze
        const sablona = <?= json_encode($emailSablony) ?>.find(s => s.id == sablonaId);

        if (!sablona) {
            content.innerHTML = '<div style="color: #dc3545; text-align: center; padding: 2rem;">Šablona nebyla nalezena</div>';
            return;
        }

        // Aktualizovat title
        document.getElementById('sablona-modal-title').textContent = 'Editace: ' + sablona.name;

        // Vytvořit formulář
        content.innerHTML = `
            <form id="sablona-form" style="display: flex; flex-direction: column; gap: 1rem;">
                <!-- Název -->
                <div>
                    <label style="display: block; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.5rem; color: #000;">
                        Název šablony:
                    </label>
                    <input type="text" id="sablona-name" value="${sablona.name.replace(/"/g, '&quot;')}"
                           style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; font-family: 'Poppins', sans-serif; font-size: 0.85rem;" readonly disabled />
                </div>

                <!-- Popis -->
                <div>
                    <label style="display: block; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.5rem; color: #000;">
                        Popis:
                    </label>
                    <input type="text" id="sablona-description" value="${sablona.description.replace(/"/g, '&quot;')}"
                           style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; font-family: 'Poppins', sans-serif; font-size: 0.85rem;" readonly disabled />
                </div>

                <!-- Předmět -->
                <div>
                    <label style="display: block; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.5rem; color: #000;">
                        Předmět emailu:
                    </label>
                    <input type="text" id="sablona-subject" value="${sablona.subject.replace(/"/g, '&quot;')}"
                           style="width: 100%; padding: 0.75rem; border: 1px solid #000; font-family: 'Poppins', sans-serif; font-size: 0.85rem;" />
                </div>

                <!-- Šablona -->
                <div>
                    <label style="display: block; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.5rem; color: #000;">
                        Obsah emailu (HTML):
                    </label>
                    <textarea id="sablona-template" rows="12"
                              style="width: 100%; padding: 0.75rem; border: 1px solid #000; font-family: monospace; line-height: 1.5;">${sablona.template.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</textarea>
                    <div style="margin-top: 0.5rem; font-size: 0.75rem; color: #666;">
                        Použijte proměnné: {{customer_name}}, {{customer_email}}, {{date}}, {{time}}, atd.
                    </div>
                </div>

                <!-- Aktivní -->
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <input type="checkbox" id="sablona-active" ${sablona.active ? 'checked' : ''} />
                    <label for="sablona-active" style="font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; color: #000; cursor: pointer;">
                        Šablona je aktivní
                    </label>
                </div>

                <!-- Alert -->
                <div id="sablona-alert" style="display: none; padding: 0.75rem; border: 1px solid #000; font-size: 0.85rem; font-family: 'Poppins', sans-serif;"></div>

                <!-- Tlačítka -->
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 0.5rem;">
                    <button type="button" onclick="zavritSablonaModal()"
                            style="padding: 0.75rem 1.5rem; background: #fff; color: #000; border: 1px solid #000; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; cursor: pointer; border-radius: 3px;">
                        Zrušit
                    </button>
                    <button type="button" onclick="ulozitSablonu('${sablonaId}')"
                            style="padding: 0.75rem 1.5rem; background: #000; color: #fff; border: 1px solid #000; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; cursor: pointer; border-radius: 3px;">
                        Uložit změny
                    </button>
                </div>
            </form>
        `;
    } catch (error) {
        console.error('Chyba načítání šablony:', error);
        content.innerHTML = '<div style="color: #dc3545; text-align: center; padding: 2rem;">Chyba načítání šablony</div>';
    }
}

// Zavřít modal
function zavritSablonaModal() {
    document.getElementById('sablona-modal').style.display = 'none';
}

// Uložit šablonu
async function ulozitSablonu(sablonaId) {
    const subject = document.getElementById('sablona-subject').value;
    const template = document.getElementById('sablona-template').value;
    const active = document.getElementById('sablona-active').checked;
    const alertEl = document.getElementById('sablona-alert');

    if (!subject || !template) {
        alertEl.style.display = 'block';
        alertEl.style.background = '#fef2f2';
        alertEl.style.color = '#991b1b';
        alertEl.textContent = 'Vyplňte předmět i obsah emailu';
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    try {
        const response = await fetch('/api/admin_api.php?action=update_email_template', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: csrfToken,
                template_id: sablonaId,
                subject: subject,
                template: template,
                active: active
            })
        });

        const result = await response.json();

        if (result.status === 'success') {
            alertEl.style.display = 'block';
            alertEl.style.background = '#f0fdf4';
            alertEl.style.color = '#15803d';
            alertEl.textContent = 'Šablona byla úspěšně uložena';

            // Zavřít modal po 1.5s a obnovit stránku
            setTimeout(() => {
                zavritSablonaModal();
                window.location.reload();
            }, 1500);
        } else {
            throw new Error(result.message || 'Nepodařilo se uložit šablonu');
        }
    } catch (error) {
        console.error('Chyba ukládání šablony:', error);
        alertEl.style.display = 'block';
        alertEl.style.background = '#fef2f2';
        alertEl.style.color = '#991b1b';
        alertEl.textContent = 'Chyba: ' + error.message;
    }
}
</script>


<?php if ($embedMode && $directAccess): ?>
</body>
</html>
<?php endif; ?>
