<?php
/**
 * Migrace: Přidání šablon pro "Čekání na vyjádření prodejce"
 *
 * Tento skript:
 * 1. Přidá sloupec 'ceka_na_prodejce' do wgs_reklamace (pokud neexistuje)
 * 2. Přidá emailovou a SMS šablonu pro notifikaci zákazníka
 *
 * Spouští se když technik v protokolu zaškrtne "Nutné vyjádření prodejce = ANO"
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Migrace: Šablony - Čekání na prodejce</title>
    <style>
        body { font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; color: #1a1a1a; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #000; border-bottom: 3px solid #000;
             padding-bottom: 10px; font-size: 1.5rem; }
        h2 { color: #333; font-size: 1.1rem; margin-top: 1.5rem; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #e9ecef; border: 1px solid #dee2e6;
                color: #333; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px;
               background: #000; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; font-weight: 600;
               border: none; cursor: pointer; }
        .btn:hover { background: #333; }
        pre { background: #1a1a1a; color: #fff; padding: 15px;
              border-radius: 5px; overflow-x: auto; font-size: 0.85rem;
              line-height: 1.5; }
        .template-preview { background: #f9f9f9; border: 1px solid #ddd;
                           padding: 15px; margin: 10px 0; border-radius: 5px;
                           white-space: pre-wrap; font-size: 0.9rem; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #000; color: #fff; font-weight: 500; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Čekání na vyjádření prodejce</h1>";

    // ========================================
    // KROK 1: Kontrola a přidání sloupce v databázi
    // ========================================
    echo "<h2>1. Databázový sloupec</h2>";

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'ceka_na_prodejce'");
    $sloupcExistuje = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sloupcExistuje) {
        echo "<div class='info'>Sloupec <code>ceka_na_prodejce</code> již existuje v tabulce wgs_reklamace.</div>";
    } else {
        echo "<div class='warning'>Sloupec <code>ceka_na_prodejce</code> NEEXISTUJE - bude přidán při migraci.</div>";
    }

    // ========================================
    // KROK 2: Definice šablon
    // ========================================
    echo "<h2>2. Emailová a SMS šablona</h2>";

    // Definice šablon
    $emailSablona = [
        'id' => 'waiting_dealer_response',
        'name' => 'Čekání na vyjádření prodejce',
        'description' => 'Email odesílaný zákazníkovi, když technik vyplní v protokolu "Nutné vyjádření prodejce = ANO"',
        'trigger_event' => 'waiting_dealer',
        'recipient_type' => 'customer',
        'type' => 'email',
        'subject' => 'Informace o průběhu reklamace č. {{order_id}} - WGS Servis',
        'template' => 'Dobrý den {{customer_name}},

děkujeme za Váš čas při návštěvě našeho technika.

Na základě provedené prohlídky bylo zjištěno, že k úplnému vyřízení Vaší reklamace je nutné vyjádření prodejce.

Co to znamená:
• Dokumentace z návštěvy technika byla předána prodejci k posouzení
• Prodejce prostuduje podklady a rozhodne o dalším postupu
• O výsledku a dalších krocích Vás bude informovat přímo prodejce

Číslo reklamace: {{order_id}}
Prodejce: {{dealer_name}}

Prosíme o trpělivost. V případě dotazů se můžete obrátit na prodejce nebo na nás.

S pozdravem,
White Glove Service
Tel: +420 725 965 826
Email: reklamace@wgs-service.cz',
        'variables' => json_encode(['{{customer_name}}', '{{order_id}}', '{{dealer_name}}']),
        'active' => 1
    ];

    $smsSablona = [
        'id' => 'waiting_dealer_response_sms',
        'name' => 'Čekání na vyjádření prodejce (SMS)',
        'description' => 'SMS odesílaná zákazníkovi, když technik vyplní "Nutné vyjádření prodejce = ANO"',
        'trigger_event' => 'waiting_dealer',
        'recipient_type' => 'customer',
        'type' => 'sms',
        'subject' => null,
        'template' => 'WGS: Dekujeme za navstevu. Reklamace c. {{order_id}} vyzaduje vyjadreni prodejce. O dalsim postupu Vas bude informovat prodejce po prostudovani podkladu od technika.',
        'variables' => json_encode(['{{order_id}}']),
        'active' => 1
    ];

    // Kontrola zda šablony už existují
    echo "<h2>Kontrola existujících šablon</h2>";

    $stmt = $pdo->prepare("SELECT id, name, type FROM wgs_notifications WHERE id IN (?, ?)");
    $stmt->execute([$emailSablona['id'], $smsSablona['id']]);
    $existujici = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($existujici)) {
        echo "<div class='warning'><strong>Upozornění:</strong> Některé šablony již existují:</div>";
        echo "<table><tr><th>ID</th><th>Název</th><th>Typ</th></tr>";
        foreach ($existujici as $s) {
            echo "<tr><td>{$s['id']}</td><td>{$s['name']}</td><td>{$s['type']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>Šablony zatím neexistují - budou vytvořeny.</div>";
    }

    // Náhled šablon
    echo "<h2>Náhled emailové šablony</h2>";
    echo "<p><strong>Předmět:</strong> <code>" . htmlspecialchars($emailSablona['subject']) . "</code></p>";
    echo "<div class='template-preview'>" . htmlspecialchars($emailSablona['template']) . "</div>";

    echo "<h2>Náhled SMS šablony</h2>";
    echo "<p><strong>Délka:</strong> " . strlen($smsSablona['template']) . " znaků</p>";
    echo "<div class='template-preview'>" . htmlspecialchars($smsSablona['template']) . "</div>";

    // Provedení migrace
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<h2>Provádím migraci...</h2>";

        $pdo->beginTransaction();

        try {
            // KROK 1: Přidat sloupec ceka_na_prodejce pokud neexistuje
            if (!$sloupcExistuje) {
                $pdo->exec("
                    ALTER TABLE wgs_reklamace
                    ADD COLUMN ceka_na_prodejce TINYINT(1) DEFAULT 0
                    COMMENT 'Nutné vyjádření prodejce? (1=ano, 0=ne)'
                    AFTER vyreseno
                ");
                echo "<div class='success'>Sloupec <code>ceka_na_prodejce</code> byl přidán do tabulky wgs_reklamace.</div>";
            }

            // KROK 2: Vložit nebo aktualizovat emailovou šablonu
            $stmt = $pdo->prepare("
                INSERT INTO wgs_notifications (id, name, description, trigger_event, recipient_type, type, subject, template, variables, active)
                VALUES (:id, :name, :description, :trigger_event, :recipient_type, :type, :subject, :template, :variables, :active)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    description = VALUES(description),
                    subject = VALUES(subject),
                    template = VALUES(template),
                    variables = VALUES(variables),
                    updated_at = CURRENT_TIMESTAMP
            ");

            $stmt->execute([
                'id' => $emailSablona['id'],
                'name' => $emailSablona['name'],
                'description' => $emailSablona['description'],
                'trigger_event' => $emailSablona['trigger_event'],
                'recipient_type' => $emailSablona['recipient_type'],
                'type' => $emailSablona['type'],
                'subject' => $emailSablona['subject'],
                'template' => $emailSablona['template'],
                'variables' => $emailSablona['variables'],
                'active' => $emailSablona['active']
            ]);
            echo "<div class='success'>Emailová šablona '<strong>{$emailSablona['name']}</strong>' byla uložena.</div>";

            // Vložit nebo aktualizovat SMS šablonu
            $stmt->execute([
                'id' => $smsSablona['id'],
                'name' => $smsSablona['name'],
                'description' => $smsSablona['description'],
                'trigger_event' => $smsSablona['trigger_event'],
                'recipient_type' => $smsSablona['recipient_type'],
                'type' => $smsSablona['type'],
                'subject' => $smsSablona['subject'],
                'template' => $smsSablona['template'],
                'variables' => $smsSablona['variables'],
                'active' => $smsSablona['active']
            ]);
            echo "<div class='success'>SMS šablona '<strong>{$smsSablona['name']}</strong>' byla uložena.</div>";

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>";
            echo "Obě šablony byly přidány do databáze.";
            echo "</div>";

            echo "<h2>Další kroky</h2>";
            echo "<div class='info'>";
            echo "<p>Šablony jsou nyní k dispozici v Admin panelu pod záložkou <strong>Email & SMS → Šablony</strong>.</p>";
            echo "<p>Pro aktivaci automatického odesílání při uložení protokolu je potřeba:</p>";
            echo "<ol>";
            echo "<li>Upravit <code>api/protokol_api.php</code> pro detekci pole 'dealer' = 'ANO'</li>";
            echo "<li>Přidat volání <code>notification_sender.php</code> s triggerem 'waiting_dealer'</li>";
            echo "</ol>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    } else {
        // Zobrazit tlačítko pro spuštění
        echo "<h2>Akce</h2>";
        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='admin.php' class='btn' style='background: #666;'>Zpět do admin</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
