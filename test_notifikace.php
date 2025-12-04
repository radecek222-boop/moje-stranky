<?php
/**
 * Test Notifikací - Ověření všech email šablon
 *
 * Tento skript zobrazí přehled všech notifikací, jejich příjemců
 * a náhled emailů s testovacími daty - BEZ skutečného odeslání.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit tento test.");
}

$pdo = getDbConnection();

// Testovací data pro náhled
$testData = [
    'order_id' => 'WGS/2025/04-12/00001',
    'customer_name' => 'Jan Novák',
    'customer_email' => 'jan.novak@example.com',
    'customer_phone' => '+420 777 888 999',
    'address' => 'Hlavní 123, 110 00 Praha 1',
    'product' => 'Natuzzi Editions - Sofa B845',
    'description' => 'Poškozené čalounění na levé straně sedačky',
    'date' => date('d.m.Y'),
    'time' => '10:30',
    'created_at' => date('d.m.Y H:i'),
    'created_by' => 'Online formulář',
    'completed_at' => date('d.m.Y H:i'),
    'technician_name' => 'Petr Technik',
    'reopened_by' => 'Admin',
    'reopened_at' => date('d.m.Y H:i'),
    'company_email' => 'reklamace@wgs-service.cz',
    'company_phone' => '+420 725 965 826',
    'contact_attempt_date' => date('d.m.Y'),
    'contact_attempt_time' => '14:30',
    'contact_attempt_note' => 'Zákazník nezvedá telefon'
];

// Funkce pro nahrazení proměnných
function nahraditPromenne($text, $data) {
    foreach ($data as $key => $value) {
        $text = str_replace('{{' . $key . '}}', $value, $text);
    }
    return $text;
}

// Funkce pro dekódování příjemců
function dekodovatPrijemce($recipientsJson) {
    if (empty($recipientsJson)) {
        return null;
    }

    $recipients = json_decode($recipientsJson, true);
    if (!$recipients) {
        return null;
    }

    return $recipients;
}

// Funkce pro zobrazení příjemců
function zobrazitPrijemce($recipients, $testData) {
    if (!$recipients) {
        return '<span style="color: #999;">Výchozí (podle recipient_type)</span>';
    }

    $output = '<table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">';
    $output .= '<tr style="background: #f5f5f5;"><th style="padding: 5px; border: 1px solid #ddd; text-align: left;">Příjemce</th><th style="padding: 5px; border: 1px solid #ddd;">Aktivní</th><th style="padding: 5px; border: 1px solid #ddd;">Typ</th><th style="padding: 5px; border: 1px solid #ddd;">Email</th></tr>';

    $prijemciInfo = [
        'customer' => ['nazev' => 'Zákazník', 'email' => $testData['customer_email']],
        'seller' => ['nazev' => 'Prodejce', 'email' => 'prodejce@natuzzi.cz'],
        'technician' => ['nazev' => 'Technik', 'email' => 'technik@wgs-service.cz'],
        'importer' => ['nazev' => 'Importér', 'email' => ''],
        'other' => ['nazev' => 'Ostatní', 'email' => '']
    ];

    foreach ($recipients as $key => $config) {
        if (!isset($prijemciInfo[$key])) continue;

        $info = $prijemciInfo[$key];
        $enabled = isset($config['enabled']) && $config['enabled'];
        $type = $config['type'] ?? 'to';
        $email = !empty($config['email']) ? $config['email'] : $info['email'];

        $enabledIcon = $enabled ? '<span style="color: green;">Ano</span>' : '<span style="color: #ccc;">Ne</span>';
        $typeLabel = ['to' => 'Příjemce (To)', 'cc' => 'Kopie (Cc)', 'bcc' => 'Skrytá (Bcc)'][$type] ?? $type;

        $rowStyle = $enabled ? '' : 'opacity: 0.5;';
        $output .= "<tr style=\"{$rowStyle}\"><td style=\"padding: 5px; border: 1px solid #ddd;\">{$info['nazev']}</td><td style=\"padding: 5px; border: 1px solid #ddd; text-align: center;\">{$enabledIcon}</td><td style=\"padding: 5px; border: 1px solid #ddd; text-align: center;\">{$typeLabel}</td><td style=\"padding: 5px; border: 1px solid #ddd;\">" . ($enabled ? htmlspecialchars($email) : '-') . "</td></tr>";
    }

    $output .= '</table>';
    return $output;
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Notifikací - WGS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5;
            padding: 20px;
            color: #333;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 {
            background: #000;
            color: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .info-box {
            background: #e8f4fd;
            border: 1px solid #b3d9f2;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .info-box h3 { margin-bottom: 10px; color: #0066cc; }
        .info-box code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.85rem;
        }
        .notification-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .notification-header {
            background: #333;
            color: #fff;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notification-header h2 { font-size: 1.1rem; font-weight: 600; }
        .notification-header .id {
            background: rgba(255,255,255,0.2);
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        .notification-meta {
            background: #f8f8f8;
            padding: 15px 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            border-bottom: 1px solid #eee;
        }
        .meta-item { }
        .meta-item label {
            display: block;
            font-size: 0.75rem;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .meta-item span { font-weight: 500; }
        .status-active { color: #28a745; }
        .status-inactive { color: #dc3545; }
        .notification-body { padding: 20px; }
        .section-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: #666;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        .recipients-section { margin-bottom: 20px; }
        .preview-section { }
        .email-preview {
            background: #fafafa;
            border: 1px solid #ddd;
            border-radius: 6px;
            overflow: hidden;
        }
        .email-subject {
            background: #eee;
            padding: 10px 15px;
            font-weight: 600;
            border-bottom: 1px solid #ddd;
        }
        .email-body {
            padding: 15px;
            white-space: pre-wrap;
            font-size: 0.9rem;
            line-height: 1.6;
            max-height: 300px;
            overflow-y: auto;
        }
        .variables-list {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 10px;
        }
        .variable-tag {
            background: #e9ecef;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.75rem;
            font-family: monospace;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 10px 15px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 0.85rem;
        }
        .success-check {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 10px 15px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 0.85rem;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #333;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover { background: #000; }
        .summary-box {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .summary-item {
            text-align: center;
            padding: 15px;
            background: #f8f8f8;
            border-radius: 6px;
        }
        .summary-item .number {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
        }
        .summary-item .label {
            font-size: 0.8rem;
            color: #666;
        }
        .trigger-badge {
            display: inline-block;
            padding: 3px 8px;
            background: #6c757d;
            color: #fff;
            border-radius: 3px;
            font-size: 0.75rem;
            margin-left: 10px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Test Notifikací - Ověření všech šablon</h1>

    <div class="info-box">
        <h3>Testovací data</h3>
        <p>Níže jsou zobrazeny náhledy emailů s těmito testovacími daty:</p>
        <div style="margin-top: 10px; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px;">
            <?php foreach ($testData as $key => $value): ?>
            <div><code>{{<?= $key ?>}}</code> = <?= htmlspecialchars($value) ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    // Načíst všechny notifikace
    $stmt = $pdo->query("SELECT * FROM wgs_notifications ORDER BY trigger_event, id");
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $celkem = count($notifications);
    $aktivnich = 0;
    $sChybou = 0;
    $bezPrijemcu = 0;

    foreach ($notifications as $n) {
        if ($n['active']) $aktivnich++;
        $recipients = dekodovatPrijemce($n['recipients'] ?? null);
        if (!$recipients) $bezPrijemcu++;
    }
    ?>

    <div class="summary-box">
        <h3>Souhrn</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="number"><?= $celkem ?></div>
                <div class="label">Celkem šablon</div>
            </div>
            <div class="summary-item">
                <div class="number" style="color: #28a745;"><?= $aktivnich ?></div>
                <div class="label">Aktivních</div>
            </div>
            <div class="summary-item">
                <div class="number" style="color: #dc3545;"><?= $celkem - $aktivnich ?></div>
                <div class="label">Neaktivních</div>
            </div>
            <div class="summary-item">
                <div class="number" style="color: #ffc107;"><?= $bezPrijemcu ?></div>
                <div class="label">Bez nastavení příjemců</div>
            </div>
        </div>
    </div>

    <?php foreach ($notifications as $notification):
        $recipients = dekodovatPrijemce($notification['recipients'] ?? null);
        $subjectPreview = nahraditPromenne($notification['subject'] ?? '', $testData);
        $bodyPreview = nahraditPromenne($notification['template'] ?? '', $testData);

        // Kontrola nenahrazených proměnných
        preg_match_all('/\{\{[^}]+\}\}/', $bodyPreview, $nenahrazene);
        $maNenahrazene = !empty($nenahrazene[0]);

        // Kontrola příjemců
        $aktivniPrijemci = [];
        if ($recipients) {
            foreach ($recipients as $key => $config) {
                if (isset($config['enabled']) && $config['enabled']) {
                    $aktivniPrijemci[] = $key;
                }
            }
        }
    ?>

    <div class="notification-card">
        <div class="notification-header">
            <div>
                <h2><?= htmlspecialchars($notification['name']) ?></h2>
                <span class="trigger-badge"><?= htmlspecialchars($notification['trigger_event']) ?></span>
            </div>
            <span class="id">ID: <?= htmlspecialchars($notification['id']) ?></span>
        </div>

        <div class="notification-meta">
            <div class="meta-item">
                <label>Stav</label>
                <span class="<?= $notification['active'] ? 'status-active' : 'status-inactive' ?>">
                    <?= $notification['active'] ? 'Aktivní' : 'Neaktivní' ?>
                </span>
            </div>
            <div class="meta-item">
                <label>Typ příjemce</label>
                <span><?= htmlspecialchars($notification['recipient_type']) ?></span>
            </div>
            <div class="meta-item">
                <label>Typ notifikace</label>
                <span><?= htmlspecialchars($notification['type']) ?></span>
            </div>
            <div class="meta-item">
                <label>Popis</label>
                <span><?= htmlspecialchars($notification['description'] ?? '-') ?></span>
            </div>
        </div>

        <div class="notification-body">
            <div class="recipients-section">
                <div class="section-title">Příjemci emailu</div>
                <?= zobrazitPrijemce($recipients, $testData) ?>

                <?php if (empty($aktivniPrijemci) && $recipients): ?>
                <div class="warning">
                    Žádný příjemce není aktivní! Email se neodešle nikomu.
                </div>
                <?php elseif (!$recipients): ?>
                <div class="warning">
                    Příjemci nejsou nastaveni. Email půjde výchozímu příjemci podle typu (<?= htmlspecialchars($notification['recipient_type']) ?>).
                </div>
                <?php else: ?>
                <div class="success-check">
                    Email bude odeslán: <?= implode(', ', array_map(function($k) {
                        $nazvy = ['customer' => 'Zákazník', 'seller' => 'Prodejce', 'technician' => 'Technik', 'importer' => 'Importér', 'other' => 'Ostatní'];
                        return $nazvy[$k] ?? $k;
                    }, $aktivniPrijemci)) ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="preview-section">
                <div class="section-title">Náhled emailu</div>
                <div class="email-preview">
                    <div class="email-subject">
                        Předmět: <?= htmlspecialchars($subjectPreview) ?>
                    </div>
                    <div class="email-body"><?= htmlspecialchars($bodyPreview) ?></div>
                </div>

                <?php if ($maNenahrazene): ?>
                <div class="warning">
                    Některé proměnné nebyly nahrazeny: <?= implode(', ', array_unique($nenahrazene[0])) ?>
                </div>
                <?php else: ?>
                <div class="success-check">
                    Všechny proměnné byly úspěšně nahrazeny.
                </div>
                <?php endif; ?>

                <?php if (!empty($notification['variables'])):
                    $variables = json_decode($notification['variables'], true);
                    if ($variables):
                ?>
                <div style="margin-top: 10px;">
                    <span style="font-size: 0.8rem; color: #666;">Definované proměnné:</span>
                    <div class="variables-list">
                        <?php foreach ($variables as $var): ?>
                        <span class="variable-tag"><?= htmlspecialchars($var) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; endif; ?>
            </div>
        </div>
    </div>

    <?php endforeach; ?>

    <a href="/admin.php?tab=notifications" class="btn">Zpět do admin</a>
</div>
</body>
</html>
