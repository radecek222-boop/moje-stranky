<?php
/**
 * Instalátor notifikačního systému
 *
 * Tento skript:
 * 1. Vytvoří tabulku wgs_notifications
 * 2. Naimportuje výchozí email šablony
 *
 * PO SPUŠTĚNÍ TENTO SOUBOR SMAŽTE!
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Pouze admin může spustit instalaci
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    http_response_code(403);
    die('❌ PŘÍSTUP ODEPŘEN: Pouze admin může spustit instalaci notifikačního systému.');
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalace Notifikačního Systému - WGS</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .status {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border: 1px solid #dee2e6;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            font-weight: bold;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        ul {
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Instalace Notifikačního Systému</h1>

<?php
try {
    $pdo = getDbConnection();

    // Kontrola zda tabulka již existuje
    $tableExists = false;
    try {
        $pdo->query("SELECT 1 FROM wgs_notifications LIMIT 1");
        $tableExists = true;
    } catch (PDOException $e) {
        $tableExists = false;
    }

    if ($tableExists) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_notifications");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $result['count'];

        echo '<div class="status warning">';
        echo '⚠️ Tabulka <code>wgs_notifications</code> již existuje s ' . $count . ' šablonami.';
        echo '</div>';

        echo '<p><strong>Možnosti:</strong></p>';
        echo '<ul>';
        echo '<li><a href="admin.php?tab=notifications" class="btn">Zobrazit notifikace v adminu</a></li>';
        echo '<li><a href="?force_reinstall=1" class="btn btn-danger">Přeinstalovat (smaže existující data)</a></li>';
        echo '</ul>';

        if (isset($_GET['force_reinstall'])) {
            echo '<div class="status info">Provádím přeinstalaci...</div>';
            $pdo->exec("DROP TABLE IF EXISTS wgs_notifications");
            $tableExists = false;
        } else {
            exit;
        }
    }

    if (!$tableExists) {
        echo '<div class="status info">📦 Spouštím instalaci...</div>';

        // Vytvoření tabulky
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS wgs_notifications (
            id VARCHAR(50) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            trigger_event VARCHAR(100) NOT NULL,
            recipient_type ENUM('customer', 'admin', 'technician', 'seller') NOT NULL,
            type ENUM('email', 'sms', 'both') NOT NULL DEFAULT 'email',
            subject VARCHAR(255) DEFAULT NULL,
            template TEXT NOT NULL,
            variables JSON DEFAULT NULL,
            cc_emails JSON DEFAULT NULL,
            bcc_emails JSON DEFAULT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $pdo->exec($createTableSQL);
        echo '<div class="status success">✅ Tabulka <code>wgs_notifications</code> vytvořena</div>';

        // Vložení výchozích šablon
        $templates = [
            [
                'id' => 'appointment_confirmed',
                'name' => 'Potvrzení termínu návštěvy',
                'description' => 'Email odesílaný zákazníkovi po potvrzení termínu návštěvy technika',
                'trigger_event' => 'appointment_confirmed',
                'recipient_type' => 'customer',
                'type' => 'email',
                'subject' => 'Potvrzení termínu návštěvy - WGS Servis',
                'template' => "Dobrý den {{customer_name}},\n\npotvrzujeme termín návštěvy technika:\n\nDatum: {{date}}\nČas: {{time}}\nČíslo zakázky: {{order_id}}\n\nV případě jakýchkoli dotazů nás prosím kontaktujte.\n\nS pozdravem,\nWhite Glove Service\nTel: +420 725 965 826\nEmail: reklamace@wgs-service.cz",
                'variables' => json_encode(['{{customer_name}}', '{{date}}', '{{time}}', '{{order_id}}'])
            ],
            [
                'id' => 'order_reopened',
                'name' => 'Zakázka znovu otevřena',
                'description' => 'Notifikace pro admin/techniky při znovuotevření zakázky',
                'trigger_event' => 'order_reopened',
                'recipient_type' => 'admin',
                'type' => 'email',
                'subject' => 'Zakázka #{{order_id}} byla znovu otevřena',
                'template' => "Zákazník: {{customer_name}}\nZakázka č.: {{order_id}}\n\nZakázka byla znovu otevřena uživatelem {{reopened_by}} dne {{reopened_at}}.\n\nStav byl změněn na NOVÁ. Termín byl vymazán.",
                'variables' => json_encode(['{{customer_name}}', '{{order_id}}', '{{reopened_by}}', '{{reopened_at}}'])
            ],
            [
                'id' => 'order_created',
                'name' => 'Nová reklamace vytvořena',
                'description' => 'Notifikace pro admin při vytvoření nové reklamace',
                'trigger_event' => 'order_created',
                'recipient_type' => 'admin',
                'type' => 'email',
                'subject' => 'Nová reklamace #{{order_id}} - {{customer_name}}',
                'template' => "Byla vytvořena nová reklamace:\n\nZákazník: {{customer_name}}\nTelefon: {{customer_phone}}\nEmail: {{customer_email}}\nAdresa: {{address}}\n\nProdukt: {{product}}\nPopis problému: {{description}}\n\nVytvořeno: {{created_at}}",
                'variables' => json_encode(['{{order_id}}', '{{customer_name}}', '{{customer_phone}}', '{{customer_email}}', '{{address}}', '{{product}}', '{{description}}', '{{created_at}}'])
            ],
            [
                'id' => 'appointment_reminder_customer',
                'name' => 'Připomenutí termínu zákazníkovi',
                'description' => 'Email připomenutí den před návštěvou technika',
                'trigger_event' => 'appointment_reminder',
                'recipient_type' => 'customer',
                'type' => 'email',
                'subject' => 'Připomenutí termínu návštěvy - zítra - WGS Servis',
                'template' => "Dobrý den {{customer_name}},\n\npřipomínáme termín návštěvy našeho technika:\n\nDatum: {{date}}\nČas: {{time}}\nAdresa: {{address}}\nČíslo zakázky: {{order_id}}\n\nPokud potřebujete termín změnit, kontaktujte nás prosím co nejdříve.\n\nS pozdravem,\nWhite Glove Service\nTel: +420 725 965 826\nEmail: reklamace@wgs-service.cz",
                'variables' => json_encode(['{{customer_name}}', '{{date}}', '{{time}}', '{{address}}', '{{order_id}}'])
            ],
            [
                'id' => 'appointment_assigned_technician',
                'name' => 'Přiřazení termínu technikovi',
                'description' => 'Notifikace pro technika při přiřazení nového termínu',
                'trigger_event' => 'appointment_assigned',
                'recipient_type' => 'technician',
                'type' => 'email',
                'subject' => 'Nový termín přiřazen - {{date}} {{time}}',
                'template' => "Dobrý den {{technician_name}},\n\nbyl vám přiřazen nový servisní termín:\n\nDatum: {{date}}\nČas: {{time}}\nZákazník: {{customer_name}}\nTelefon: {{customer_phone}}\nAdresa: {{address}}\n\nProdukt: {{product}}\nPopis problému: {{description}}\n\nČíslo zakázky: {{order_id}}\n\nProsím potvrďte přijetí termínu v admin systému.",
                'variables' => json_encode(['{{technician_name}}', '{{date}}', '{{time}}', '{{customer_name}}', '{{customer_phone}}', '{{address}}', '{{product}}', '{{description}}', '{{order_id}}'])
            ],
            [
                'id' => 'order_completed',
                'name' => 'Zakázka dokončena',
                'description' => 'Poděkování zákazníkovi po dokončení zakázky',
                'trigger_event' => 'order_completed',
                'recipient_type' => 'customer',
                'type' => 'email',
                'subject' => 'Děkujeme za využití našich služeb - WGS Servis',
                'template' => "Dobrý den {{customer_name}},\n\nděkujeme, že jste využili služeb White Glove Service.\n\nZakázka č. {{order_id}} byla úspěšně dokončena dne {{completed_at}}.\n\nPokud byste měli jakékoli dotazy nebo připomínky k provedené opravě, neváhejte nás kontaktovat.\n\nBudeme rádi, když nás doporučíte svým známým.\n\nS pozdravem,\nWhite Glove Service\nTel: +420 725 965 826\nEmail: reklamace@wgs-service.cz\nWeb: www.wgs-service.cz",
                'variables' => json_encode(['{{customer_name}}', '{{order_id}}', '{{completed_at}}'])
            ]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO wgs_notifications
            (id, name, description, trigger_event, recipient_type, type, subject, template, variables, active)
            VALUES
            (:id, :name, :description, :trigger_event, :recipient_type, :type, :subject, :template, :variables, 1)
        ");

        $count = 0;
        foreach ($templates as $template) {
            $stmt->execute($template);
            $count++;
        }

        echo '<div class="status success">✅ Naimportováno ' . $count . ' výchozích šablon</div>';

        echo '<h2>📋 Importované šablony:</h2>';
        echo '<ul>';
        foreach ($templates as $t) {
            echo '<li><strong>' . htmlspecialchars($t['name']) . '</strong> - ' . htmlspecialchars($t['description']) . '</li>';
        }
        echo '</ul>';

        echo '<div class="status success">';
        echo '<strong>🎉 Instalace dokončena!</strong><br><br>';
        echo '✅ Tabulka vytvořena<br>';
        echo '✅ Šablony naimportovány<br>';
        echo '✅ Notifikační systém je připraven k použití';
        echo '</div>';

        echo '<h2>🚀 Další kroky:</h2>';
        echo '<ol>';
        echo '<li><strong>Přejděte do admin panelu</strong> pro správu šablon: <a href="admin.php?tab=notifications" class="btn">Otevřít notifikace</a></li>';
        echo '<li><strong>DŮLEŽITÉ: Smažte tento soubor</strong> z bezpečnostních důvodů: <code>install_notifications.php</code></li>';
        echo '</ol>';

        echo '<div class="status warning">';
        echo '⚠️ <strong>BEZPEČNOST:</strong> Nezapomeňte smazat soubor <code>install_notifications.php</code> po dokončení instalace!';
        echo '</div>';
    }

} catch (PDOException $e) {
    echo '<div class="status error">';
    echo '❌ <strong>CHYBA DATABÁZE:</strong><br>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '</div>';
} catch (Exception $e) {
    echo '<div class="status error">';
    echo '❌ <strong>CHYBA:</strong><br>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '</div>';
}
?>

    </div>
</body>
</html>
