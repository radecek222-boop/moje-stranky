<?php
/**
 * Migrace: Oprava šablony "Pokus o kontakt"
 *
 * Tento skript opraví šablonu "Pokus o kontakt" která měla chybně celý HTML dokument.
 * Upraví ji na prostý text jako mají ostatní šablony.
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
    <title>Migrace: Oprava šablony Pokus o kontakt</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
             padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px;
              overflow-x: auto; white-space: pre-wrap; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Oprava šablony 'Pokus o kontakt'</h1>";

    // 1. Kontrola současného stavu
    echo "<div class='info'><strong>KONTROLA SOUČASNÉHO STAVU...</strong></div>";

    $stmt = $pdo->prepare("SELECT id, name, subject, template FROM wgs_notifications WHERE name = 'Pokus o kontakt' LIMIT 1");
    $stmt->execute();
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        echo "<div class='error'><strong>CHYBA:</strong> Šablona 'Pokus o kontakt' nebyla nalezena v databázi!</div>";
        echo "<div class='warning'>Možná řešení:<br>1. Vytvořte šablonu ručně v Admin panelu<br>2. Spusťte migrační skript pro vytvoření základních šablon</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='success'>Šablona nalezena. ID: {$current['id']}</div>";

    echo "<h3>Současný stav šablony:</h3>";
    echo "<p><strong>Předmět:</strong> " . htmlspecialchars($current['subject']) . "</p>";
    echo "<p><strong>Délka těla:</strong> " . strlen($current['template']) . " znaků</p>";

    // Kontrola jestli obsahuje HTML
    if (stripos($current['template'], '<!DOCTYPE') !== false || stripos($current['template'], '<html') !== false) {
        echo "<div class='warning'><strong>⚠️ PROBLÉM DETEKOVÁN:</strong> Šablona obsahuje celý HTML dokument místo prostého textu!</div>";
    } else {
        echo "<div class='success'><strong>✓ OK:</strong> Šablona již je v pořádku (obsahuje prostý text).</div>";
        echo "</div></body></html>";
        exit;
    }

    // 2. Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM OPRAVU...</strong></div>";

        // Nová šablona - prostý text jako ostatní šablony
        $novyPredmet = 'Pokusili jsme se Vás kontaktovat - White Glove Service';
        $novaSablona = 'Dobrý den {{customer_name}},

pokusili jsme se Vás kontaktovat ohledně vaší servisní prohlídky.

📋 INFORMACE O ZAKÁZCE:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Číslo zakázky: {{order_id}}
Produkt: {{product}}
Adresa: {{address}}
Datum pokusu o kontakt: {{date}}

📞 PROSÍME O ZPĚTNÉ ZAVOLÁNÍ:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Zavolejte prosím zpět na číslo: +420 725 965 826
Nebo nám napište email na: reklamace@wgs-service.cz

Rádi s Vámi domluvíme vhodný termín návštěvy našeho technika.

S pozdravem,
White Glove Service

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🌐 Web: www.wgs-service.cz
📧 Email: reklamace@wgs-service.cz
📱 Tel: +420 725 965 826';

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                UPDATE wgs_notifications
                SET
                    subject = :subject,
                    template = :template,
                    updated_at = NOW()
                WHERE name = 'Pokus o kontakt'
            ");

            $stmt->execute([
                'subject' => $novyPredmet,
                'template' => $novaSablona
            ]);

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✅ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
            echo "Šablona 'Pokus o kontakt' byla úspěšně opravena.<br>";
            echo "Změněno řádků: " . $stmt->rowCount() . "<br><br>";
            echo "<strong>Nový předmět:</strong><br>";
            echo htmlspecialchars($novyPredmet) . "<br><br>";
            echo "<strong>Nová šablona (náhled):</strong><br>";
            echo "<pre>" . htmlspecialchars(substr($novaSablona, 0, 500)) . "...</pre>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<strong>✅ CO BYLO PROVEDENO:</strong><br>";
            echo "• Odstraněn celý HTML dokument<br>";
            echo "• Převedeno na prostý text jako ostatní šablony<br>";
            echo "• Zachovány všechny proměnné ({{customer_name}}, {{order_id}}, atd.)<br>";
            echo "• Přidáno čitelné formátování s emoji pro přehlednost<br>";
            echo "• Aktualizováno updated_at pole<br>";
            echo "</div>";

            echo "<div class='success'>";
            echo "<strong>📧 TESTOVÁNÍ:</strong><br>";
            echo "1. Otevřete seznam reklamací<br>";
            echo "2. Klikněte na tlačítko 'Odeslat SMS' u nějakého zákazníka<br>";
            echo "3. Zkontrolujte email který zákazník dostane<br>";
            echo "4. Měl by vidět čitelný text bez HTML tagů<br>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA PŘI PROVÁDĚNÍ MIGRACE:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }

    } else {
        // Náhled co bude provedeno
        echo "<h3>📋 Co bude provedeno:</h3>";
        echo "<div class='info'>";
        echo "• Aktualizace šablony 'Pokus o kontakt'<br>";
        echo "• Odstranění HTML dokumentu<br>";
        echo "• Nahrazení prostým textem<br>";
        echo "• Zachování všech proměnných<br>";
        echo "</div>";

        echo "<h3>📧 Ukázka nové šablony:</h3>";
        echo "<pre style='border: 2px solid #2D5016;'>";
        echo htmlspecialchars("Dobrý den {{customer_name}},

pokusili jsme se Vás kontaktovat ohledně vaší servisní prohlídky.

📋 INFORMACE O ZAKÁZCE:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Číslo zakázky: {{order_id}}
Produkt: {{product}}
Adresa: {{address}}
...");
        echo "</pre>";

        echo "<a href='?execute=1' class='btn'>✅ SPUSTIT OPRAVU</a>";
        echo "<a href='/admin.php' class='btn' style='background: #666;'>← Zpět do Admin panelu</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
