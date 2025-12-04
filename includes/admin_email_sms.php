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
$smsSablonyAll = [];
$sablonyParovane = [];

// Normalizace trigger_event - mapovani ruznych nazvu na stejny klic
function normalizujTrigger($trigger) {
    $mapa = [
        'order_created' => 'nova_reklamace',
        'complaint_created' => 'nova_reklamace',
        'order_completed' => 'dokonceno',
        'complaint_completed' => 'dokonceno',
        'order_reopened' => 'znovu_otevreno',
        'complaint_reopened' => 'znovu_otevreno',
        'appointment_confirmed' => 'potvrzeni_terminu',
        'appointment_reminder' => 'pripominka_terminu',
        'appointment_assigned' => 'prirazeni_terminu',
        'contact_attempt' => 'pokus_o_kontakt',
        'invitation_send' => 'pozvanka'
    ];
    return $mapa[$trigger] ?? $trigger;
}

try {
    $stmt = $pdo->query("
        SELECT
            id, name, description, trigger_event, recipient_type,
            type, subject, template, active, created_at, updated_at
        FROM wgs_notifications
        ORDER BY name ASC
    ");
    $vsechnySablony = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Rozdelit na EMAIL a SMS podle normalizovaneho triggeru
    foreach ($vsechnySablony as $s) {
        $normTrigger = normalizujTrigger($s['trigger_event']);
        // Pridat recipient_type k klici pro odliseni (napr. pozvanka pro technika vs prodejce)
        $klic = $normTrigger . '_' . $s['recipient_type'];

        if ($s['type'] === 'sms') {
            $smsSablonyAll[$klic] = $s;
        } else {
            $emailSablony[$klic] = $s;
        }
    }

    // Sparovat podle klice - vlastni poradi (logicka posloupnost procesu)
    $vsechnyKlice = array_unique(array_merge(array_keys($emailSablony), array_keys($smsSablonyAll)));

    // Definovat poradi sablon (od zacatku procesu do konce)
    $poradiSablon = [
        'nova_reklamace_customer',      // 1. Nova reklamace - zakaznik
        'nova_reklamace_admin',         // 2. Nova reklamace - admin
        'pokus_o_kontakt_customer',     // 3. Pokus o kontakt
        'potvrzeni_terminu_customer',   // 4. Potvrzeni terminu
        'pripominka_terminu_customer',  // 5. Pripominka terminu
        'prirazeni_terminu_technician', // 6. Prirazeni terminu technikovi
        'dokonceno_customer',           // 7. Dokonceni zakazky
        'znovu_otevreno_admin',         // 8. Znovu otevreno
        'pozvanka_seller',              // 9. Pozvanka pro prodejce (na konci)
        'pozvanka_technician'           // 10. Pozvanka pro technika (na konci)
    ];

    // Seradit podle definovaneho poradi
    usort($vsechnyKlice, function($a, $b) use ($poradiSablon) {
        $indexA = array_search($a, $poradiSablon);
        $indexB = array_search($b, $poradiSablon);
        // Pokud neni v poradi, dat na konec
        if ($indexA === false) $indexA = 999;
        if ($indexB === false) $indexB = 999;
        return $indexA - $indexB;
    });

    foreach ($vsechnyKlice as $klic) {
        $sablonyParovane[] = [
            'trigger' => $klic,
            'email' => $emailSablony[$klic] ?? null,
            'sms' => $smsSablonyAll[$klic] ?? null
        ];
    }
} catch (PDOException $e) {
    $emailSablony = [];
    $sablonyParovane = [];
}

// Load email queue for management tab
$filterStatus = $_GET['filter'] ?? 'all';
$emaily = [];
try {
    $whereClause = '';
    if ($filterStatus !== 'all') {
        $whereClause = "WHERE eq.status = :status";
    }

    $sql = "
        SELECT
            eq.id, eq.recipient_email, eq.recipient_name, eq.subject, eq.body,
            eq.status, eq.attempts, eq.error_message,
            eq.cc_emails, eq.bcc_emails,
            eq.created_at, eq.scheduled_at, eq.sent_at,
            eq.notification_id,
            COALESCE(n.name, eq.notification_id) as template_name
        FROM wgs_email_queue eq
        LEFT JOIN wgs_notifications n ON eq.notification_id = n.id
        $whereClause
        ORDER BY eq.created_at DESC
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
        <button class="control-detail-back" data-href="admin.php">
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
                    data-action="switchEmailSection" data-section="overview">
                Přehled
            </button>
            <button class="cc-tab <?= $currentSection === 'smtp' ? 'active' : '' ?>"
                    data-action="switchEmailSection" data-section="smtp">
                SMTP Konfigurace
            </button>
            <button class="cc-tab <?= $currentSection === 'templates' ? 'active' : '' ?>"
                    data-action="switchEmailSection" data-section="templates">
                Email šablony
            </button>
            <button class="cc-tab <?= $currentSection === 'sms' ? 'active' : '' ?>"
                    data-action="switchEmailSection" data-section="sms">
                SMS
            </button>
            <button class="cc-tab <?= $currentSection === 'management' ? 'active' : '' ?>"
                    data-action="switchEmailSection" data-section="management">
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
                                                    data-action="togglePasswordVisibility" data-id="<?= $config['id'] ?>"
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
                                                data-action="saveEmailConfig" data-id="<?= $config['id'] ?>" data-key="<?= htmlspecialchars($config['config_key']) ?>"
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
                                   autocomplete="email"
                                   style="width: 200px; margin-right: 0.5rem;">
                            <button class="cc-btn cc-btn-sm cc-btn-primary" data-action="sendTestEmail" style="font-size: 0.75rem; padding: 0.4rem 0.75rem;">
                                Odeslat test
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- EMAIL ŠABLONY -->
        <div id="section-templates" class="cc-section <?= $currentSection === 'templates' ? 'active' : '' ?>">
            <h3 style="margin-bottom: 0.75rem; font-family: 'Poppins', sans-serif; font-size: 0.9rem; font-weight: 600; color: #000; text-transform: uppercase; letter-spacing: 0.5px;">Email sablony</h3>

            <?php if (empty($emailSablony)): ?>
                <div style="background: #f5f5f5; border: 1px solid #ddd; padding: 1rem; font-family: 'Poppins', sans-serif;">
                    Zadne email sablony nenalezeny.
                </div>
            <?php else: ?>
                <?php
                $triggerLabels = [
                    'potvrzeni_terminu_customer' => 'Potvrzeni terminu',
                    'prirazeni_terminu_technician' => 'Prirazeni terminu',
                    'pripominka_terminu_customer' => 'Pripominka terminu',
                    'pokus_o_kontakt_customer' => 'Pokus o kontakt',
                    'nova_reklamace_admin' => 'Nova reklamace (admin)',
                    'nova_reklamace_customer' => 'Nova reklamace (zakaznik)',
                    'dokonceno_customer' => 'Dokonceni zakazky',
                    'znovu_otevreno_admin' => 'Znovu otevreno',
                    'pozvanka_seller' => 'Pozvanka pro prodejce',
                    'pozvanka_technician' => 'Pozvanka pro technika'
                ];

                // Seradit email sablony podle definovaneho poradi
                $poradiSablon = [
                    'nova_reklamace_customer',
                    'nova_reklamace_admin',
                    'pokus_o_kontakt_customer',
                    'potvrzeni_terminu_customer',
                    'pripominka_terminu_customer',
                    'prirazeni_terminu_technician',
                    'dokonceno_customer',
                    'znovu_otevreno_admin',
                    'pozvanka_seller',
                    'pozvanka_technician'
                ];

                // Seradit pole podle poradi
                uksort($emailSablony, function($a, $b) use ($poradiSablon) {
                    $indexA = array_search($a, $poradiSablon);
                    $indexB = array_search($b, $poradiSablon);
                    if ($indexA === false) $indexA = 999;
                    if ($indexB === false) $indexB = 999;
                    return $indexA - $indexB;
                });
                ?>

                <!-- Email sablony - jednodussi seznam -->
                <?php foreach ($emailSablony as $klic => $email): ?>
                <div style="border: 2px solid #000; margin-bottom: 1rem; background: #fff;">
                    <!-- Nadpis -->
                    <div style="background: #000; color: #fff; padding: 0.5rem 1rem; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; display: flex; justify-content: space-between; align-items: center;">
                        <span><?= htmlspecialchars($triggerLabels[$klic] ?? $email['name']) ?></span>
                        <span data-action="toggleNotifikaceActive" data-id="<?= htmlspecialchars($email['id']) ?>"
                              style="padding: 0.2rem 0.5rem; font-size: 0.6rem; font-weight: 600; text-transform: uppercase; border: 1px solid #fff; background: <?= $email['active'] ? '#fff' : 'transparent' ?>; color: <?= $email['active'] ? '#000' : '#fff' ?>; cursor: pointer;"
                              data-active="<?= $email['active'] ? '1' : '0' ?>">
                            <?= $email['active'] ? 'AKTIVNI' : 'VYPNUTO' ?>
                        </span>
                    </div>
                    <!-- Obsah -->
                    <div style="padding: 1rem;">
                        <div style="font-size: 0.7rem; color: #666; margin-bottom: 0.5rem;">
                            Prijemce: <strong><?= htmlspecialchars($email['recipient_type']) ?></strong>
                        </div>
                        <div style="font-size: 0.75rem; color: #333; margin-bottom: 0.75rem;">
                            <strong>Predmet:</strong> <?= htmlspecialchars($email['subject']) ?>
                        </div>
                        <button data-action="otevritNotifikace" data-id="<?= $email['id'] ?>"
                            style="padding: 0.5rem 1rem; background: #333; color: #fff; border: none; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; cursor: pointer;">
                            Upravit sablonu
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Promenne -->
                <div style="margin-top: 1rem; background: #f9f9f9; border: 1px solid #ddd; padding: 1rem;">
                    <h4 style="font-family: 'Poppins', sans-serif; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.5rem;">Dostupne promenne:</h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; font-size: 0.7rem; font-family: monospace;">
                        <code style="background: #fff; padding: 0.2rem 0.4rem; border: 1px solid #ddd;">{{customer_name}}</code>
                        <code style="background: #fff; padding: 0.2rem 0.4rem; border: 1px solid #ddd;">{{order_id}}</code>
                        <code style="background: #fff; padding: 0.2rem 0.4rem; border: 1px solid #ddd;">{{date}}</code>
                        <code style="background: #fff; padding: 0.2rem 0.4rem; border: 1px solid #ddd;">{{time}}</code>
                        <code style="background: #fff; padding: 0.2rem 0.4rem; border: 1px solid #ddd;">{{address}}</code>
                        <code style="background: #fff; padding: 0.2rem 0.4rem; border: 1px solid #ddd;">{{technician_name}}</code>
                        <code style="background: #fff; padding: 0.2rem 0.4rem; border: 1px solid #ddd;">{{technician_phone}}</code>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- SMS -->
        <div id="section-sms" class="cc-section <?= $currentSection === 'sms' ? 'active' : '' ?>">
            <h3 style="margin-bottom: 0.75rem; font-family: 'Poppins', sans-serif; font-size: 0.9rem; font-weight: 600; color: #000; text-transform: uppercase; letter-spacing: 0.5px;">SMS sablony</h3>

            <!-- Info box -->
            <div style="background: #f5f5f5; border: 1px solid #ddd; padding: 0.75rem 1rem; margin-bottom: 1rem; font-family: 'Poppins', sans-serif; font-size: 0.75rem; color: #666;">
                SMS se odesilaji pres nativni aplikaci telefonu. Technik klikne na "Odeslat SMS" a otevre se aplikace Zpravy s predvyplnenym textem.
            </div>

            <?php
            // Pouzijeme smsSablonyAll ktere uz mame nactene
            $smsTriggerLabels = [
                'potvrzeni_terminu_customer' => 'Potvrzeni terminu',
                'prirazeni_terminu_technician' => 'Prirazeni terminu',
                'pripominka_terminu_customer' => 'Pripominka terminu',
                'pokus_o_kontakt_customer' => 'Pokus o kontakt',
                'nova_reklamace_admin' => 'Nova reklamace (admin)',
                'nova_reklamace_customer' => 'Nova reklamace (zakaznik)',
                'dokonceno_customer' => 'Dokonceni zakazky',
                'znovu_otevreno_admin' => 'Znovu otevreno'
            ];

            // Seradit SMS sablony podle poradi
            $smsPoradiSablon = [
                'nova_reklamace_customer',
                'nova_reklamace_admin',
                'pokus_o_kontakt_customer',
                'potvrzeni_terminu_customer',
                'pripominka_terminu_customer',
                'prirazeni_terminu_technician',
                'dokonceno_customer',
                'znovu_otevreno_admin'
            ];

            uksort($smsSablonyAll, function($a, $b) use ($smsPoradiSablon) {
                $indexA = array_search($a, $smsPoradiSablon);
                $indexB = array_search($b, $smsPoradiSablon);
                if ($indexA === false) $indexA = 999;
                if ($indexB === false) $indexB = 999;
                return $indexA - $indexB;
            });
            ?>

            <?php if (empty($smsSablonyAll)): ?>
                <div style="background: #f5f5f5; border: 1px solid #ddd; padding: 1rem; font-family: 'Poppins', sans-serif;">
                    Zadne SMS sablony nenalezeny.
                    <a href="/pridej_sms_sablony.php" style="color: #333; text-decoration: underline; margin-left: 0.5rem;">Spustit migraci</a>
                </div>
            <?php else: ?>
                <!-- SMS sablony - stejny format jako email -->
                <?php foreach ($smsSablonyAll as $klic => $sms): ?>
                <div style="border: 2px solid #000; margin-bottom: 1rem; background: #fff;">
                    <!-- Nadpis -->
                    <div style="background: #000; color: #fff; padding: 0.5rem 1rem; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; display: flex; justify-content: space-between; align-items: center;">
                        <span><?= htmlspecialchars($smsTriggerLabels[$klic] ?? $sms['name']) ?></span>
                        <span data-action="toggleNotifikaceActive" data-id="<?= htmlspecialchars($sms['id']) ?>"
                              style="padding: 0.2rem 0.5rem; font-size: 0.6rem; font-weight: 600; text-transform: uppercase; border: 1px solid #fff; background: <?= $sms['active'] ? '#fff' : 'transparent' ?>; color: <?= $sms['active'] ? '#000' : '#fff' ?>; cursor: pointer;"
                              data-active="<?= $sms['active'] ? '1' : '0' ?>">
                            <?= $sms['active'] ? 'AKTIVNI' : 'VYPNUTO' ?>
                        </span>
                    </div>
                    <!-- Obsah -->
                    <div style="padding: 1rem;">
                        <div style="font-size: 0.7rem; color: #666; margin-bottom: 0.5rem;">
                            Prijemce: <strong><?= htmlspecialchars($sms['recipient_type']) ?></strong>
                        </div>
                        <div style="background: #f9f9f9; padding: 0.75rem; font-size: 0.75rem; font-family: monospace; border: 1px solid #eee; margin-bottom: 0.75rem; max-height: 60px; overflow: hidden;">
                            <?= htmlspecialchars(substr($sms['template'], 0, 120)) ?><?= strlen($sms['template']) > 120 ? '...' : '' ?>
                        </div>
                        <button data-action="editSmsTemplate" data-id="<?= htmlspecialchars($sms['id']) ?>"
                            style="padding: 0.5rem 1rem; background: #333; color: #fff; border: none; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; cursor: pointer;">
                            Upravit sablonu
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- SMS promenne -->
                <div style="margin-top: 1rem; background: #f9f9f9; border: 1px solid #ddd; padding: 1rem;">
                    <h4 style="font-family: 'Poppins', sans-serif; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.5rem;">Dostupne promenne:</h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; font-size: 0.7rem; font-family: monospace;">
                        <code style="background: #fff; padding: 0.2rem 0.4rem; border: 1px solid #ddd;">{{customer_name}}</code>
                        <code style="background: #fff; padding: 0.2rem 0.4rem; border: 1px solid #ddd;">{{order_id}}</code>
                        <code style="background: #fff; padding: 0.2rem 0.4rem; border: 1px solid #ddd;">{{date}}</code>
                        <code style="background: #fff; padding: 0.2rem 0.4rem; border: 1px solid #ddd;">{{time}}</code>
                        <code style="background: #fff; padding: 0.2rem 0.4rem; border: 1px solid #ddd;">{{address}}</code>
                        <code style="background: #fff; padding: 0.2rem 0.4rem; border: 1px solid #ddd;">{{technician_name}}</code>
                        <code style="background: #fff; padding: 0.2rem 0.4rem; border: 1px solid #ddd;">{{technician_phone}}</code>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- EMAIL MANAGEMENT -->
        <div id="section-management" class="cc-section <?= $currentSection === 'management' ? 'active' : '' ?>">

            <!-- Alert -->
            <div id="email-alert" style="display: none; padding: 0.75rem; margin-bottom: 1rem; border: 1px solid #000; font-family: 'Poppins', sans-serif; font-size: 0.85rem;"></div>

            <!-- Filter Stats -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.75rem; margin-bottom: 1rem;">
                <button type="button" data-action="filterEmaily" data-filter="all" style="display: block; width: 100%; font-family: inherit; background: <?= $filterStatus === 'all' ? '#000' : '#fff' ?>; border: 1px solid #000; padding: 0.75rem; text-align: center; cursor: pointer; transition: all 0.2s;">
                    <div style="font-size: 1.5rem; font-weight: 600; font-family: 'Poppins', sans-serif; color: <?= $filterStatus === 'all' ? '#fff' : '#000' ?>;"><?= $emailStats['all'] ?></div>
                    <div style="font-size: 0.75rem; color: <?= $filterStatus === 'all' ? '#fff' : '#666' ?>; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.5px;">Celkem</div>
                </button>
                <button type="button" data-action="filterEmaily" data-filter="sent" style="display: block; width: 100%; font-family: inherit; background: <?= $filterStatus === 'sent' ? '#000' : '#fff' ?>; border: 1px solid #000; padding: 0.75rem; text-align: center; cursor: pointer; transition: all 0.2s;">
                    <div style="font-size: 1.5rem; font-weight: 600; font-family: 'Poppins', sans-serif; color: <?= $filterStatus === 'sent' ? '#fff' : '#000' ?>;"><?= $emailStats['sent'] ?></div>
                    <div style="font-size: 0.75rem; color: <?= $filterStatus === 'sent' ? '#fff' : '#666' ?>; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.5px;">Odesláno</div>
                </button>
                <button type="button" data-action="filterEmaily" data-filter="pending" style="display: block; width: 100%; font-family: inherit; background: <?= $filterStatus === 'pending' ? '#000' : '#fff' ?>; border: 1px solid #000; padding: 0.75rem; text-align: center; cursor: pointer; transition: all 0.2s;">
                    <div style="font-size: 1.5rem; font-weight: 600; font-family: 'Poppins', sans-serif; color: <?= $filterStatus === 'pending' ? '#fff' : '#000' ?>;"><?= $emailStats['pending'] ?></div>
                    <div style="font-size: 0.75rem; color: <?= $filterStatus === 'pending' ? '#fff' : '#666' ?>; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.5px;">Ve frontě</div>
                </button>
                <button type="button" data-action="filterEmaily" data-filter="failed" style="display: block; width: 100%; font-family: inherit; background: <?= $filterStatus === 'failed' ? '#000' : '#fff' ?>; border: 1px solid #000; padding: 0.75rem; text-align: center; cursor: pointer; transition: all 0.2s;">
                    <div style="font-size: 1.5rem; font-weight: 600; font-family: 'Poppins', sans-serif; color: <?= $filterStatus === 'failed' ? '#fff' : '#000' ?>;"><?= $emailStats['failed'] ?></div>
                    <div style="font-size: 0.75rem; color: <?= $filterStatus === 'failed' ? '#fff' : '#666' ?>; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.5px;">Selhalo</div>
                </button>
            </div>

            <!-- Toolbar -->
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: #f5f5f5; border: 1px solid #000; margin-bottom: 1rem;">
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-family: 'Poppins', sans-serif;">
                        <input type="checkbox" id="select-all-emails" data-action="toggleSelectAllEmails">
                        <span>Vybrat vše</span>
                    </label>
                    <span style="font-size: 0.85rem; color: #666; font-family: 'Poppins', sans-serif;">
                        Vybráno: <strong id="selected-email-count">0</strong>
                    </span>
                </div>
                <button id="resend-emails-btn" data-action="resendVybraneEmaily" disabled
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
                                <input type="checkbox" id="select-all-emails-header" data-action="toggleSelectAllEmails">
                            </th>
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd; background: #000; color: #fff; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.7rem;">ID</th>
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd; background: #000; color: #fff; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.7rem;">Šablona</th>
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
                        <tr class="email-queue-row">
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;">
                                <input type="checkbox" class="email-checkbox-item" value="<?= $email['id'] ?>" data-action="updateSelectedEmailCount">
                            </td>
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;"><?= $email['id'] ?></td>
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;"><?php
                                $templateName = $email['template_name'] ?? '-';
                                // Ošetření případu kdy notification_id bylo uloženo jako "Array"
                                if ($templateName === 'Array' || empty($templateName)) {
                                    echo '-';
                                } else {
                                    echo htmlspecialchars($templateName);
                                }
                            ?></td>
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;">
                                <span style="display: inline-block; padding: 0.25rem 0.5rem; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid #000; background: <?= $email['status'] === 'sent' ? '#000' : '#fff' ?>; color: <?= $email['status'] === 'sent' ? '#fff' : '#000' ?>;">
                                    <?php
                                        if ($email['status'] === 'sent') echo 'SENT';
                                        elseif ($email['status'] === 'pending') echo 'PENDING';
                                        else echo 'FAILED';
                                    ?>
                                </span>
                            </td>
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;"><?= htmlspecialchars($email['recipient_email']) ?></td>
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;"><?= htmlspecialchars(substr($email['subject'], 0, 40)) ?><?= strlen($email['subject']) > 40 ? '...' : '' ?></td>
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;"><?= $email['attempts'] ?> / 3</td>
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;"><?= date('d.m.Y H:i', strtotime($email['created_at'])) ?></td>
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;"><?= $email['sent_at'] ? date('d.m.Y H:i', strtotime($email['sent_at'])) : '-' ?></td>
                            <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem;">
                                <?php
                                    $ccEmails = $email['cc_emails'] ? json_decode($email['cc_emails'], true) : [];
                                    $bccEmails = $email['bcc_emails'] ? json_decode($email['bcc_emails'], true) : [];
                                ?>
                                <button class="cc-btn cc-btn-sm cc-btn-link" data-action="openEmailDetailModal"
                                    data-id="<?= $email['id'] ?>"
                                    data-recipient="<?= htmlspecialchars($email['recipient_email']) ?>"
                                    data-recipient-name="<?= htmlspecialchars($email['recipient_name'] ?? '') ?>"
                                    data-subject="<?= htmlspecialchars($email['subject']) ?>"
                                    data-cc="<?= htmlspecialchars(is_array($ccEmails) ? implode(', ', $ccEmails) : '') ?>"
                                    data-bcc="<?= htmlspecialchars(is_array($bccEmails) ? implode(', ', $bccEmails) : '') ?>"
                                    data-created="<?= date('d.m.Y H:i:s', strtotime($email['created_at'])) ?>"
                                    data-sent="<?= $email['sent_at'] ? date('d.m.Y H:i:s', strtotime($email['sent_at'])) : '-' ?>"
                                    data-status="<?= $email['status'] ?>"
                                    data-template="<?= htmlspecialchars($email['template_name'] ?? '-') ?>"
                                    data-error="<?= htmlspecialchars($email['error_message'] ?? '') ?>">
                                    Zobrazit
                                </button>
                                <!-- Body uloženo v hidden elementu kvůli délce -->
                                <div id="email-body-<?= $email['id'] ?>" style="display: none;"><?= htmlspecialchars($email['body']) ?></div>
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
            <button data-action="zavritSablonaModal" aria-label="Zavřít" style="background: none; border: none; color: #fff; font-size: 2rem; cursor: pointer; line-height: 1;">&times;</button>
        </div>

        <!-- Obsah -->
        <div id="sablona-modal-content" style="padding: 1.5rem;">
            <div style="text-align: center; padding: 2rem; color: #999;">Načítám...</div>
        </div>
    </div>
</div>

<!-- Modal pro detail emailu -->
<div id="email-detail-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; overflow-y: auto;">
    <div style="max-width: 700px; margin: 2rem auto; background: #fff; border-radius: 8px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        <!-- Header -->
        <div style="padding: 1rem 1.5rem; background: #000; color: #fff; display: flex; justify-content: space-between; align-items: center; border-radius: 8px 8px 0 0;">
            <h2 style="font-family: 'Poppins', sans-serif; font-size: 1.1rem; font-weight: 600; margin: 0;">Detail emailu #<span id="email-modal-id"></span></h2>
            <button data-action="closeEmailDetailModal" aria-label="Zavřít" style="background: none; border: none; color: #fff; font-size: 2rem; cursor: pointer; line-height: 1;">&times;</button>
        </div>

        <!-- Obsah -->
        <div style="padding: 1.5rem;">
            <!-- Info sekce -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; font-size: 0.85rem;">
                <div>
                    <strong style="color: #666; display: block; margin-bottom: 0.25rem;">Příjemce (TO):</strong>
                    <span id="email-modal-recipient" style="color: #000;"></span>
                </div>
                <div>
                    <strong style="color: #666; display: block; margin-bottom: 0.25rem;">Stav:</strong>
                    <span id="email-modal-status" style="display: inline-block; padding: 0.2rem 0.5rem; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; border: 1px solid #000;"></span>
                </div>
                <div>
                    <strong style="color: #666; display: block; margin-bottom: 0.25rem;">Kopie (CC):</strong>
                    <span id="email-modal-cc" style="color: #000;"></span>
                </div>
                <div>
                    <strong style="color: #666; display: block; margin-bottom: 0.25rem;">Skrytá kopie (BCC):</strong>
                    <span id="email-modal-bcc" style="color: #000;"></span>
                </div>
                <div>
                    <strong style="color: #666; display: block; margin-bottom: 0.25rem;">Vytvořeno:</strong>
                    <span id="email-modal-created" style="color: #000;"></span>
                </div>
                <div>
                    <strong style="color: #666; display: block; margin-bottom: 0.25rem;">Odesláno:</strong>
                    <span id="email-modal-sent" style="color: #000;"></span>
                </div>
                <div style="grid-column: 1 / -1;">
                    <strong style="color: #666; display: block; margin-bottom: 0.25rem;">Šablona:</strong>
                    <span id="email-modal-template" style="color: #000;"></span>
                </div>
            </div>

            <!-- Předmět -->
            <div style="margin-bottom: 1rem;">
                <strong style="color: #666; display: block; margin-bottom: 0.25rem; font-size: 0.85rem;">Předmět:</strong>
                <div id="email-modal-subject" style="padding: 0.75rem; background: #f5f5f5; border: 1px solid #ddd; font-size: 0.9rem; font-weight: 500;"></div>
            </div>

            <!-- Tělo emailu -->
            <div style="margin-bottom: 1rem;">
                <strong style="color: #666; display: block; margin-bottom: 0.25rem; font-size: 0.85rem;">Tělo emailu:</strong>
                <div id="email-modal-body" style="padding: 0.75rem; background: #f5f5f5; border: 1px solid #ddd; max-height: 300px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word; font-size: 0.85rem; font-family: 'Courier New', monospace;"></div>
            </div>

            <!-- Chyba (pokud existuje) -->
            <div id="email-modal-error-container" style="display: none; margin-bottom: 1rem;">
                <strong style="color: #991b1b; display: block; margin-bottom: 0.25rem; font-size: 0.85rem;">Chyba:</strong>
                <div id="email-modal-error" style="padding: 0.75rem; background: #fef2f2; border: 1px solid #ef4444; color: #991b1b; font-size: 0.8rem; font-family: monospace;"></div>
            </div>

            <!-- Tlačítko zavřít -->
            <div style="text-align: right; padding-top: 1rem; border-top: 1px solid #ddd;">
                <button data-action="closeEmailDetailModal" style="padding: 0.5rem 1.5rem; background: #000; color: #fff; border: none; font-family: 'Poppins', sans-serif; font-weight: 500; cursor: pointer;">Zavřít</button>
            </div>
        </div>
    </div>
</div>

<script>
// Handler pro otevření modalu detailu emailu
document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-action="openEmailDetailModal"]');
    if (btn) {
        const id = btn.dataset.id;
        const recipient = btn.dataset.recipient;
        const recipientName = btn.dataset.recipientName;
        const subject = btn.dataset.subject;
        const cc = btn.dataset.cc;
        const bcc = btn.dataset.bcc;
        const created = btn.dataset.created;
        const sent = btn.dataset.sent;
        const status = btn.dataset.status;
        const template = btn.dataset.template;
        const error = btn.dataset.error;

        // Získat tělo emailu z hidden elementu
        const bodyEl = document.getElementById('email-body-' + id);
        const body = bodyEl ? bodyEl.textContent : '';

        // Naplnit modal
        document.getElementById('email-modal-id').textContent = id;
        document.getElementById('email-modal-recipient').textContent = recipientName ? recipientName + ' <' + recipient + '>' : recipient;
        document.getElementById('email-modal-subject').textContent = subject;
        document.getElementById('email-modal-cc').textContent = cc || '-';
        document.getElementById('email-modal-bcc').textContent = bcc || '-';
        document.getElementById('email-modal-created').textContent = created;
        document.getElementById('email-modal-sent').textContent = sent;
        document.getElementById('email-modal-template').textContent = template === 'Array' ? '-' : template;
        document.getElementById('email-modal-body').textContent = body;

        // Stav s barvou
        const statusEl = document.getElementById('email-modal-status');
        statusEl.textContent = status.toUpperCase();
        if (status === 'sent') {
            statusEl.style.background = '#000';
            statusEl.style.color = '#fff';
        } else if (status === 'failed') {
            statusEl.style.background = '#fef2f2';
            statusEl.style.color = '#991b1b';
            statusEl.style.borderColor = '#ef4444';
        } else {
            statusEl.style.background = '#fff';
            statusEl.style.color = '#000';
        }

        // Chyba
        const errorContainer = document.getElementById('email-modal-error-container');
        const errorEl = document.getElementById('email-modal-error');
        if (error) {
            errorEl.textContent = error;
            errorContainer.style.display = 'block';
        } else {
            errorContainer.style.display = 'none';
        }

        // Zobrazit modal
        document.getElementById('email-detail-modal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    // Zavření modalu
    if (e.target.closest('[data-action="closeEmailDetailModal"]')) {
        document.getElementById('email-detail-modal').style.display = 'none';
        document.body.style.overflow = '';
    }
});

// Zavření při kliku mimo modal
document.getElementById('email-detail-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
        document.body.style.overflow = '';
    }
});

// Zavření ESC klávesou
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('email-detail-modal');
        if (modal && modal.style.display !== 'none') {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }
});
</script>

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

.email-queue-row {
    transition: background 0.2s;
    background: #fff;
}

.email-queue-row:hover {
    background: #f5f5f5;
}

.admin-hover-row {
    transition: background 0.2s;
    background: #fff;
}

.admin-hover-row:hover {
    background: #f5f5f5;
}

.sms-card-hover {
    transition: box-shadow 0.2s;
}

.sms-card-hover:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.photo-hover-scale {
    transition: transform 0.2s;
}

.photo-hover-scale:hover {
    transform: scale(1.05);
}

.protokol-hover-bg {
    transition: background 0.2s;
}

.protokol-hover-bg:hover {
    background: #e5e5e5 !important;
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

// Toggle aktivni stav notifikace
async function toggleNotifikaceActive(notificationId, element) {
    const currentActive = element.dataset.active === '1';
    const newActive = !currentActive;

    // Vizualni feedback - okamzite prepnout
    element.style.background = newActive ? '#000' : '#fff';
    element.style.color = newActive ? '#fff' : '#000';
    element.textContent = newActive ? 'AKTIVNI' : 'VYPNUTO';
    element.dataset.active = newActive ? '1' : '0';

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        const response = await fetch('/api/notification_api.php?action=toggle', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: csrfToken,
                notification_id: notificationId,
                active: newActive
            })
        });

        const result = await response.json();

        if (result.status !== 'success') {
            // Vratit zpet pri chybe
            element.style.background = currentActive ? '#000' : '#fff';
            element.style.color = currentActive ? '#fff' : '#000';
            element.textContent = currentActive ? 'AKTIVNI' : 'VYPNUTO';
            element.dataset.active = currentActive ? '1' : '0';
            alert('Chyba: ' + (result.message || 'Nepodařilo se změnit stav'));
        }
    } catch (error) {
        // Vratit zpet pri chybe
        element.style.background = currentActive ? '#000' : '#fff';
        element.style.color = currentActive ? '#fff' : '#000';
        element.textContent = currentActive ? 'AKTIVNI' : 'VYPNUTO';
        element.dataset.active = currentActive ? '1' : '0';
        console.error('Chyba toggle notifikace:', error);
        alert('Chyba pri zmene stavu notifikace');
    }
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
        // Načíst data šablony z databáze (Object.values prevede objekt na pole)
        const sablona = Object.values(<?= json_encode($emailSablony) ?>).find(s => s.id == sablonaId);

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
                    <label style="display: block; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.5rem; color: #fff;">
                        Název šablony:
                    </label>
                    <input type="text" id="sablona-name" value="${sablona.name.replace(/"/g, '&quot;')}"
                           style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; font-family: 'Poppins', sans-serif; font-size: 0.85rem;" readonly disabled />
                </div>

                <!-- Popis -->
                <div>
                    <label style="display: block; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.5rem; color: #fff;">
                        Popis:
                    </label>
                    <input type="text" id="sablona-description" value="${sablona.description.replace(/"/g, '&quot;')}"
                           style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; font-family: 'Poppins', sans-serif; font-size: 0.85rem;" readonly disabled />
                </div>

                <!-- Předmět -->
                <div>
                    <label style="display: block; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.5rem; color: #fff;">
                        Předmět emailu:
                    </label>
                    <input type="text" id="sablona-subject" value="${sablona.subject.replace(/"/g, '&quot;')}"
                           style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; font-family: 'Poppins', sans-serif; font-size: 0.85rem;" />
                </div>

                <!-- Šablona -->
                <div>
                    <label style="display: block; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.5rem; color: #fff;">
                        Obsah emailu (HTML):
                    </label>
                    <textarea id="sablona-template" rows="12"
                              style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; font-family: monospace; line-height: 1.5;">${sablona.template.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</textarea>
                    <div style="margin-top: 0.5rem; font-size: 0.75rem; color: #ccc;">
                        Dostupné proměnné:<br>
                        • Zákazník: {{customer_name}}, {{customer_email}}, {{order_id}}, {{product}}, {{address}}<br>
                        • Technik: {{technician_name}}, {{technician_email}}, {{technician_phone}}<br>
                        • Firma: {{company_email}}, {{company_phone}}<br>
                        • Datum/čas: {{date}}, {{time}}
                    </div>
                </div>

                <!-- Aktivní -->
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <input type="checkbox" id="sablona-active" ${sablona.active ? 'checked' : ''} />
                    <label for="sablona-active" style="font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; color: #fff; cursor: pointer;">
                        Šablona je aktivní
                    </label>
                </div>

                <!-- Příjemci -->
                <div style="margin-top: 1rem; padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 5px;">
                    <label style="display: block; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.5rem; color: #fff;">
                        Příjemci emailu:
                    </label>
                    <div id="recipients-summary" style="color: #ccc; font-size: 0.85rem; margin-bottom: 0.75rem;">
                        Načítání...
                    </div>
                    <button type="button" data-action="otevritModalPrijemcu" data-id="${sablonaId}"
                            style="padding: 0.5rem 1rem; background: #fff; color: #000; border: 1px solid #fff; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.8rem; cursor: pointer; border-radius: 3px;">
                        Nastavit příjemce
                    </button>
                </div>

                <!-- Alert -->
                <div id="sablona-alert" style="display: none; padding: 0.75rem; border: 1px solid #000; font-size: 0.85rem; font-family: 'Poppins', sans-serif;"></div>

                <!-- Tlačítka -->
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 0.5rem;">
                    <button type="button" data-action="zavritSablonaModal"
                            style="padding: 0.75rem 1.5rem; background: #fff; color: #000; border: 1px solid #000; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; cursor: pointer; border-radius: 3px;">
                        Zrušit
                    </button>
                    <button type="button" data-action="ulozitSablonu" data-id="${sablonaId}"
                            style="padding: 0.75rem 1.5rem; background: #000; color: #fff; border: 1px solid #000; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; cursor: pointer; border-radius: 3px;">
                        Uložit změny
                    </button>
                </div>
            </form>
        `;

        // Načíst a zobrazit příjemce
        nacistAZobrazitPrijemce(sablonaId);

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

// =======================
// MODAL PRO VÝBĚR PŘÍJEMCŮ
// =======================

// Globální proměnná pro uchování aktuálních příjemců
let currentRecipients = null;
let currentTemplateIdForRecipients = null;

// Zobrazit souhrn příjemců
function zobrazitSouhrnPrijemcu(recipients) {
    const summary = document.getElementById('recipients-summary');
    if (!summary) return;

    if (!recipients) {
        summary.innerHTML = '<span style="color: #999;">Žádní příjemci nastaveni</span>';
        return;
    }

    const prijemci = [];
    const typeLabel = { 'to': 'To', 'cc': 'Cc', 'bcc': 'Bcc' };

    if (recipients.customer && recipients.customer.enabled) {
        prijemci.push('Zákazník (' + typeLabel[recipients.customer.type] + ')');
    }
    if (recipients.seller && recipients.seller.enabled) {
        prijemci.push('Prodejce (' + typeLabel[recipients.seller.type] + ')');
    }
    if (recipients.technician && recipients.technician.enabled) {
        prijemci.push('Technik (' + typeLabel[recipients.technician.type] + ')');
    }
    if (recipients.importer && recipients.importer.enabled) {
        prijemci.push('Výrobce (' + typeLabel[recipients.importer.type] + ', ' + (recipients.importer.email || 'bez emailu') + ')');
    }
    if (recipients.other && recipients.other.enabled) {
        prijemci.push('Jiné (' + typeLabel[recipients.other.type] + ', ' + (recipients.other.email || 'bez emailu') + ')');
    }

    if (prijemci.length === 0) {
        summary.innerHTML = '<span style="color: #f44336;">⚠ Žádní příjemci!</span>';
    } else {
        summary.innerHTML = prijemci.join(', ');
    }
}

// Otevřít modal pro výběr příjemců
async function otevritModalPrijemcu(sablonaId) {
    currentTemplateIdForRecipients = sablonaId;

    // Načíst aktuální nastavení příjemců
    try {
        const response = await fetch(`/api/notification_api.php?action=get&id=${sablonaId}`);
        const data = await response.json();

        if (data.status === 'success' && data.notification) {
            currentRecipients = data.notification.recipients || {
                customer: { enabled: true, type: 'to' },
                seller: { enabled: false, type: 'cc' },
                technician: { enabled: false, type: 'cc' },
                importer: { enabled: false, email: '', type: 'cc' },
                other: { enabled: false, email: '', type: 'cc' }
            };
        }
    } catch (error) {
        console.error('Chyba načítání příjemců:', error);
        currentRecipients = {
            customer: { enabled: true, type: 'to' },
            seller: { enabled: false, type: 'cc' },
            technician: { enabled: false, type: 'cc' },
            importer: { enabled: false, email: '', type: 'cc' },
            other: { enabled: false, email: '', type: 'cc' }
        };
    }

    // Vytvořit modal
    const modalHTML = `
        <div id="recipients-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 100000; display: flex; align-items: center; justify-content: center;">
            <div style="background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); padding: 2rem; border-radius: 10px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
                <h3 style="margin: 0 0 1.5rem 0; color: #fff; font-family: 'Poppins', sans-serif; font-size: 1.3rem;">
                    Nastavení příjemců emailu
                </h3>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <!-- Zákazník -->
                    <div style="padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 5px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                            <input type="checkbox" id="recipient-customer" ${currentRecipients.customer?.enabled ? 'checked' : ''} style="width: 20px; height: 20px; cursor: pointer;">
                            <div style="color: #fff; font-weight: 600; font-size: 0.95rem;">Zákazník</div>
                        </div>
                        <div style="color: #ccc; font-size: 0.8rem; margin-bottom: 0.5rem; padding-left: 30px;">
                            Email bude odeslán zákazníkovi
                        </div>
                        <div style="padding-left: 30px;">
                            <select id="recipient-customer-type" style="padding: 0.5rem; background: rgba(0,0,0,0.3); border: 1px solid #555; color: #fff; border-radius: 5px; font-size: 0.85rem; width: 100%; max-width: 250px;">
                                <option value="to" ${currentRecipients.customer?.type === 'to' ? 'selected' : ''}>Příjemce (To)</option>
                                <option value="cc" ${currentRecipients.customer?.type === 'cc' ? 'selected' : ''}>Kopie (Cc)</option>
                                <option value="bcc" ${currentRecipients.customer?.type === 'bcc' ? 'selected' : ''}>Skrytá kopie (Bcc)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Prodejce -->
                    <div style="padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 5px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                            <input type="checkbox" id="recipient-seller" ${currentRecipients.seller?.enabled ? 'checked' : ''} style="width: 20px; height: 20px; cursor: pointer;">
                            <div style="color: #fff; font-weight: 600; font-size: 0.95rem;">Prodejce</div>
                        </div>
                        <div style="color: #ccc; font-size: 0.8rem; margin-bottom: 0.5rem; padding-left: 30px;">
                            Email bude odeslán prodejci, který vytvořil reklamaci
                        </div>
                        <div style="padding-left: 30px;">
                            <select id="recipient-seller-type" style="padding: 0.5rem; background: rgba(0,0,0,0.3); border: 1px solid #555; color: #fff; border-radius: 5px; font-size: 0.85rem; width: 100%; max-width: 250px;">
                                <option value="to" ${currentRecipients.seller?.type === 'to' ? 'selected' : ''}>Příjemce (To)</option>
                                <option value="cc" ${currentRecipients.seller?.type === 'cc' ? 'selected' : ''}>Kopie (Cc)</option>
                                <option value="bcc" ${currentRecipients.seller?.type === 'bcc' ? 'selected' : ''}>Skrytá kopie (Bcc)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Technik -->
                    <div style="padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 5px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                            <input type="checkbox" id="recipient-technician" ${currentRecipients.technician?.enabled ? 'checked' : ''} style="width: 20px; height: 20px; cursor: pointer;">
                            <div style="color: #fff; font-weight: 600; font-size: 0.95rem;">Technik</div>
                        </div>
                        <div style="color: #ccc; font-size: 0.8rem; margin-bottom: 0.5rem; padding-left: 30px;">
                            Email bude odeslán technikovi, který pracoval na reklamaci
                        </div>
                        <div style="padding-left: 30px;">
                            <select id="recipient-technician-type" style="padding: 0.5rem; background: rgba(0,0,0,0.3); border: 1px solid #555; color: #fff; border-radius: 5px; font-size: 0.85rem; width: 100%; max-width: 250px;">
                                <option value="to" ${currentRecipients.technician?.type === 'to' ? 'selected' : ''}>Příjemce (To)</option>
                                <option value="cc" ${currentRecipients.technician?.type === 'cc' ? 'selected' : ''}>Kopie (Cc)</option>
                                <option value="bcc" ${currentRecipients.technician?.type === 'bcc' ? 'selected' : ''}>Skrytá kopie (Bcc)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Výrobce / Import -->
                    <div style="padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 5px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                            <input type="checkbox" id="recipient-importer" ${currentRecipients.importer?.enabled ? 'checked' : ''} style="width: 20px; height: 20px; cursor: pointer;">
                            <div style="color: #fff; font-weight: 600; font-size: 0.95rem;">Import zastupující / Výrobce</div>
                        </div>
                        <div style="color: #ccc; font-size: 0.8rem; margin-bottom: 0.5rem; padding-left: 30px;">
                            Email bude odeslán na zadanou adresu výrobce
                        </div>
                        <div style="padding-left: 30px; margin-bottom: 0.5rem;">
                            <select id="recipient-importer-type" style="padding: 0.5rem; background: rgba(0,0,0,0.3); border: 1px solid #555; color: #fff; border-radius: 5px; font-size: 0.85rem; width: 100%; max-width: 250px;">
                                <option value="to" ${currentRecipients.importer?.type === 'to' ? 'selected' : ''}>Příjemce (To)</option>
                                <option value="cc" ${currentRecipients.importer?.type === 'cc' ? 'selected' : ''}>Kopie (Cc)</option>
                                <option value="bcc" ${currentRecipients.importer?.type === 'bcc' ? 'selected' : ''}>Skrytá kopie (Bcc)</option>
                            </select>
                        </div>
                        <div style="padding-left: 30px;">
                            <input type="email" id="recipient-importer-email" value="${currentRecipients.importer?.email || ''}" placeholder="email@vyrobce.cz"
                                   style="width: 100%; padding: 0.75rem; background: rgba(0,0,0,0.3); border: 1px solid #555; color: #fff; border-radius: 5px; font-size: 0.9rem;">
                        </div>
                    </div>

                    <!-- Jiné -->
                    <div style="padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 5px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                            <input type="checkbox" id="recipient-other" ${currentRecipients.other?.enabled ? 'checked' : ''} style="width: 20px; height: 20px; cursor: pointer;">
                            <div style="color: #fff; font-weight: 600; font-size: 0.95rem;">Jiné</div>
                        </div>
                        <div style="color: #ccc; font-size: 0.8rem; margin-bottom: 0.5rem; padding-left: 30px;">
                            Email bude odeslán na vlastní emailovou adresu
                        </div>
                        <div style="padding-left: 30px; margin-bottom: 0.5rem;">
                            <select id="recipient-other-type" style="padding: 0.5rem; background: rgba(0,0,0,0.3); border: 1px solid #555; color: #fff; border-radius: 5px; font-size: 0.85rem; width: 100%; max-width: 250px;">
                                <option value="to" ${currentRecipients.other?.type === 'to' ? 'selected' : ''}>Příjemce (To)</option>
                                <option value="cc" ${currentRecipients.other?.type === 'cc' ? 'selected' : ''}>Kopie (Cc)</option>
                                <option value="bcc" ${currentRecipients.other?.type === 'bcc' ? 'selected' : ''}>Skrytá kopie (Bcc)</option>
                            </select>
                        </div>
                        <div style="padding-left: 30px;">
                            <input type="email" id="recipient-other-email" value="${currentRecipients.other?.email || ''}" placeholder="vlastni@email.cz"
                                   style="width: 100%; padding: 0.75rem; background: rgba(0,0,0,0.3); border: 1px solid #555; color: #fff; border-radius: 5px; font-size: 0.9rem;">
                        </div>
                    </div>
                </div>

                <!-- Tlačítka -->
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" data-action="zavritModalPrijemcu"
                            style="padding: 0.75rem 1.5rem; background: #666; color: #fff; border: none; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; cursor: pointer; border-radius: 5px;">
                        Zrušit
                    </button>
                    <button type="button" data-action="ulozitPrijemce"
                            style="padding: 0.75rem 1.5rem; background: #fff; color: #000; border: none; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.85rem; cursor: pointer; border-radius: 5px;">
                        Uložit příjemce
                    </button>
                </div>
            </div>
        </div>
    `;

    // Přidat modal do stránky
    const existingModal = document.getElementById('recipients-modal');
    if (existingModal) {
        existingModal.remove();
    }

    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// Zavřít modal příjemců
function zavritModalPrijemcu() {
    const modal = document.getElementById('recipients-modal');
    if (modal) {
        modal.remove();
    }
}

// Uložit příjemce
async function ulozitPrijemce() {
    const recipients = {
        customer: {
            enabled: document.getElementById('recipient-customer').checked,
            type: document.getElementById('recipient-customer-type').value
        },
        seller: {
            enabled: document.getElementById('recipient-seller').checked,
            type: document.getElementById('recipient-seller-type').value
        },
        technician: {
            enabled: document.getElementById('recipient-technician').checked,
            type: document.getElementById('recipient-technician-type').value
        },
        importer: {
            enabled: document.getElementById('recipient-importer').checked,
            email: document.getElementById('recipient-importer-email').value,
            type: document.getElementById('recipient-importer-type').value
        },
        other: {
            enabled: document.getElementById('recipient-other').checked,
            email: document.getElementById('recipient-other-email').value,
            type: document.getElementById('recipient-other-type').value
        }
    };

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    try {
        const response = await fetch('/api/admin_api.php?action=update_email_recipients', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: csrfToken,
                template_id: currentTemplateIdForRecipients,
                recipients: recipients
            })
        });

        const result = await response.json();

        if (result.status === 'success') {
            // Aktualizovat souhrn
            zobrazitSouhrnPrijemcu(recipients);
            zavritModalPrijemcu();
        } else {
            throw new Error(result.message || 'Nepodařilo se uložit příjemce');
        }
    } catch (error) {
        console.error('Chyba ukládání příjemců:', error);
        alert('Chyba: ' + error.message);
    }
}

// Načíst a zobrazit příjemce při otevření editace šablony
async function nacistAZobrazitPrijemce(sablonaId) {
    try {
        const response = await fetch(`/api/notification_api.php?action=get&id=${sablonaId}`);
        const data = await response.json();

        if (data.status === 'success' && data.notification && data.notification.recipients) {
            zobrazitSouhrnPrijemcu(data.notification.recipients);
        } else {
            zobrazitSouhrnPrijemcu(null);
        }
    } catch (error) {
        console.error('Chyba načítání příjemců:', error);
        zobrazitSouhrnPrijemcu(null);
    }
}

// ========================================
// SMS SABLONY - EDITACE
// ========================================

// Aktualni editovana SMS sablona
let currentSmsTemplateId = null;

// Otevrit modal pro editaci SMS sablony
async function editSmsTemplate(id) {
    currentSmsTemplateId = id;

    try {
        const response = await fetch(`/api/notification_api.php?action=get&id=${id}`);
        const data = await response.json();

        if (data.status !== 'success' || !data.notification) {
            alert('Chyba: Nelze nacist SMS sablonu');
            return;
        }

        const sablona = data.notification;

        // Zobrazit modal
        const modal = document.getElementById('editSmsModal');
        if (!modal) {
            vytvorSmsModal();
        }

        // Naplnit data
        document.getElementById('sms-template-name').textContent = sablona.name || '';
        document.getElementById('sms-template-description').textContent = sablona.description || '';
        document.getElementById('sms-template-content').value = sablona.template || '';
        document.getElementById('sms-template-active').checked = sablona.active == 1;

        // Zobrazit modal
        document.getElementById('editSmsModal').style.display = 'flex';

    } catch (error) {
        console.error('Chyba pri nacitani SMS sablony:', error);
        alert('Chyba pri nacitani SMS sablony');
    }
}

// Vytvorit SMS modal (pokud neexistuje)
function vytvorSmsModal() {
    const modalHtml = `
    <div id="editSmsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100001; justify-content: center; align-items: center; padding: 1rem;">
        <div style="background: white; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; border: 2px solid #000;">
            <!-- Cerny header -->
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; background: #000; color: #fff;">
                <h3 style="margin: 0; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 1rem; text-transform: uppercase; letter-spacing: 0.5px;">Upravit SMS sablonu</h3>
                <button data-action="closeSmsModal" aria-label="Zavrit" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #fff; line-height: 1;">&times;</button>
            </div>
            <div style="padding: 1.5rem;">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.25rem; font-family: 'Poppins', sans-serif; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Nazev:</label>
                    <div id="sms-template-name" style="padding: 0.5rem; background: #f5f5f5; border: 1px solid #ddd; font-family: 'Poppins', sans-serif;"></div>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.25rem; font-family: 'Poppins', sans-serif; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Popis:</label>
                    <div id="sms-template-description" style="padding: 0.5rem; background: #f5f5f5; border: 1px solid #ddd; font-family: 'Poppins', sans-serif; font-size: 0.85rem; color: #666;"></div>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.25rem; font-family: 'Poppins', sans-serif; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Text SMS zpravy:</label>
                    <textarea id="sms-template-content" rows="6" style="width: 100%; padding: 0.75rem; border: 2px solid #000; font-family: monospace; font-size: 0.9rem; resize: vertical; box-sizing: border-box;"></textarea>
                    <div style="font-size: 0.7rem; color: #666; margin-top: 0.25rem;">Max 160 znaku pro 1 SMS. Delsi zpravy se rozdeli.</div>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-family: 'Poppins', sans-serif; font-size: 0.85rem;">
                        <input type="checkbox" id="sms-template-active">
                        <span>Sablona je aktivni</span>
                    </label>
                </div>
                <!-- Promenne -->
                <div style="background: #f5f5f5; border: 1px solid #ddd; padding: 0.75rem; margin-bottom: 1rem;">
                    <div style="font-size: 0.7rem; font-weight: 600; margin-bottom: 0.5rem; font-family: 'Poppins', sans-serif; text-transform: uppercase;">Dostupne promenne:</div>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.25rem; font-size: 0.65rem; font-family: monospace;">
                        <code style="background: #fff; padding: 0.15rem 0.3rem; border: 1px solid #ddd; cursor: pointer;" onclick="navigator.clipboard.writeText('{{customer_name}}')">{{customer_name}}</code>
                        <code style="background: #fff; padding: 0.15rem 0.3rem; border: 1px solid #ddd; cursor: pointer;" onclick="navigator.clipboard.writeText('{{order_id}}')">{{order_id}}</code>
                        <code style="background: #fff; padding: 0.15rem 0.3rem; border: 1px solid #ddd; cursor: pointer;" onclick="navigator.clipboard.writeText('{{date}}')">{{date}}</code>
                        <code style="background: #fff; padding: 0.15rem 0.3rem; border: 1px solid #ddd; cursor: pointer;" onclick="navigator.clipboard.writeText('{{time}}')">{{time}}</code>
                        <code style="background: #fff; padding: 0.15rem 0.3rem; border: 1px solid #ddd; cursor: pointer;" onclick="navigator.clipboard.writeText('{{technician_name}}')">{{technician_name}}</code>
                        <code style="background: #fff; padding: 0.15rem 0.3rem; border: 1px solid #ddd; cursor: pointer;" onclick="navigator.clipboard.writeText('{{technician_phone}}')">{{technician_phone}}</code>
                    </div>
                    <div style="font-size: 0.65rem; color: #999; margin-top: 0.25rem;">Klikni pro zkopirovat</div>
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.5rem; border-top: 2px solid #000; background: #f5f5f5;">
                <button data-action="closeSmsModal" style="padding: 0.5rem 1.5rem; background: #fff; border: 2px solid #000; color: #000; font-family: 'Poppins', sans-serif; cursor: pointer; font-weight: 600; text-transform: uppercase; font-size: 0.75rem;">Zrusit</button>
                <button data-action="saveSmsTemplate" style="padding: 0.5rem 1.5rem; background: #000; border: 2px solid #000; color: #fff; font-family: 'Poppins', sans-serif; cursor: pointer; font-weight: 600; text-transform: uppercase; font-size: 0.75rem;">Ulozit</button>
            </div>
        </div>
    </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

// Zavrit SMS modal
function closeSmsModal() {
    const modal = document.getElementById('editSmsModal');
    if (modal) {
        modal.style.display = 'none';
    }
    currentSmsTemplateId = null;
}

// Ulozit SMS sablonu
async function saveSmsTemplate() {
    if (!currentSmsTemplateId) {
        alert('Chyba: ID sablony neni nastaveno');
        return;
    }

    const template = document.getElementById('sms-template-content').value;
    const active = document.getElementById('sms-template-active').checked ? 1 : 0;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('id', currentSmsTemplateId);
        formData.append('template', template);
        formData.append('active', active);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('/api/notification_api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.status === 'success') {
            alert('SMS sablona byla ulozena');
            closeSmsModal();
            // Reload stranky pro zobrazeni zmen
            window.location.reload();
        } else {
            alert('Chyba: ' + (data.message || 'Nepodarilo se ulozit'));
        }

    } catch (error) {
        console.error('Chyba pri ukladani SMS sablony:', error);
        alert('Chyba pri ukladani SMS sablony');
    }
}

// ACTION REGISTRY - Step 113
// Počkat na načtení Utils (defer skripty)
function initEmailSmsActions() {
    if (typeof Utils === 'undefined' || !Utils.registerAction) {
        setTimeout(initEmailSmsActions, 50);
        return;
    }

    Utils.registerAction('switchEmailSection', (el, data) => {
        if (data.section) switchSection(data.section);
    });
    Utils.registerAction('togglePasswordVisibility', (el, data) => {
        if (data.id) togglePasswordVisibility(parseInt(data.id));
    });
    Utils.registerAction('saveEmailConfig', (el, data) => {
        if (data.id && data.key) saveConfig(parseInt(data.id), data.key);
    });
    Utils.registerAction('sendTestEmail', () => sendTestEmail());
    Utils.registerAction('toggleNotifikaceActive', (el, data) => {
        if (data.id) toggleNotifikaceActive(data.id, el);
    });
    Utils.registerAction('otevritNotifikace', (el, data) => {
        if (data.id) otevritNotifikace(data.id);
    });
    Utils.registerAction('editSmsTemplate', (el, data) => {
        if (data.id) editSmsTemplate(data.id);
    });
    Utils.registerAction('filterEmaily', (el, data) => {
        if (data.filter) filterEmaily(data.filter);
    });
    Utils.registerAction('resendVybraneEmaily', () => resendVybraneEmaily());
    Utils.registerAction('toggleEmailDetail', (el, data) => {
        if (data.id) toggleEmailDetail(parseInt(data.id));
    });
    Utils.registerAction('zavritSablonaModal', () => zavritSablonaModal());
    Utils.registerAction('otevritModalPrijemcu', (el, data) => {
        if (data.id) otevritModalPrijemcu(data.id);
    });
    Utils.registerAction('ulozitSablonu', (el, data) => {
        if (data.id) ulozitSablonu(data.id);
    });
    Utils.registerAction('zavritModalPrijemcu', () => zavritModalPrijemcu());
    Utils.registerAction('ulozitPrijemce', () => ulozitPrijemce());
    Utils.registerAction('closeSmsModal', () => closeSmsModal());
    Utils.registerAction('saveSmsTemplate', () => saveSmsTemplate());
}

// Spustit po načtení DOM
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEmailSmsActions);
} else {
    initEmailSmsActions();
}
</script>


<?php if ($embedMode && $directAccess): ?>
</body>
</html>
<?php endif; ?>
