<?php
/**
 * Migrace: Přidání email šablony pro pokus o kontakt zákazníka
 *
 * Tento skript BEZPEČNĚ přidá novou email šablonu do tabulky wgs_notifications.
 * Šablona se používá když technik klikne na "Odeslat SMS" - automaticky pošle
 * email zákazníkovi s informací o pokusu o kontakt.
 *
 * Můžete jej spustit vícekrát - nepřidá duplicity.
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
    <title>Migrace: Email šablona pokus o kontakt</title>
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
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px;
              overflow-x: auto; font-size: 0.9rem; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Email šablona pro pokus o kontakt</h1>";

    // Kontrola před migrací
    echo "<div class='info'><strong>KONTROLA...</strong></div>";

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM wgs_notifications WHERE name = 'Pokus o kontakt'");
    $stmt->execute();
    $existuje = $stmt->fetchColumn() > 0;

    if ($existuje) {
        echo "<div class='warning'>";
        echo "<strong>Šablona již existuje!</strong><br>";
        echo "Email šablona 'Pokus o kontakt' je již v databázi.";
        echo "</div>";

        // Zobrazit aktuální šablonu
        $stmt = $pdo->prepare("SELECT * FROM wgs_notifications WHERE name = 'Pokus o kontakt'");
        $stmt->execute();
        $sablona = $stmt->fetch(PDO::FETCH_ASSOC);

        echo "<h3>Aktuální šablona:</h3>";
        echo "<pre>";
        echo "ID: " . $sablona['id'] . "\n";
        echo "Název: " . $sablona['name'] . "\n";
        echo "Typ: " . $sablona['type'] . "\n";
        echo "Příjemce: " . $sablona['recipient_type'] . "\n";
        echo "Aktivní: " . ($sablona['active'] ? 'Ano' : 'Ne') . "\n";
        echo "</pre>";

    } else {
        echo "<div class='info'><strong>Šablona neexistuje, bude přidána.</strong></div>";
    }

    // Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            if (!$existuje) {
                // Přidat novou šablonu
                $stmt = $pdo->prepare("
                    INSERT INTO wgs_notifications (
                        id,
                        name,
                        description,
                        type,
                        trigger_event,
                        recipient_type,
                        subject,
                        template,
                        active,
                        created_at,
                        updated_at
                    ) VALUES (
                        :id,
                        :name,
                        :description,
                        :type,
                        :trigger_event,
                        :recipient_type,
                        :subject,
                        :template,
                        :active,
                        NOW(),
                        NOW()
                    )
                ");

                $template = <<<'HTML'
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pokus o kontakt - WGS</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f8f9fa; border-left: 4px solid #2D5016; padding: 20px; margin-bottom: 20px;">
        <h2 style="margin: 0 0 10px 0; color: #2D5016;">Pokusili jsme se Vás kontaktovat</h2>
        <p style="margin: 0; color: #666;">White Glove Service - Servis Natuzzi</p>
    </div>

    <div style="padding: 20px; background: white;">
        <p>Dobrý den <strong>{{customer_name}}</strong>,</p>

        <p>pokusili jsme se Vás kontaktovat telefonicky ohledně servisní prohlídky:</p>

        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p style="margin: 5px 0;"><strong>Číslo zakázky:</strong> {{order_id}}</p>
            <p style="margin: 5px 0;"><strong>Produkt:</strong> {{product}}</p>
            <p style="margin: 5px 0;"><strong>Datum kontaktu:</strong> {{date}}</p>
        </div>

        <p>Bohužel se nám nepodařilo Vás zastihnout. Prosím, kontaktujte nás zpět na telefonním čísle nebo emailu:</p>

        <div style="background: #2D5016; color: white; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p style="margin: 5px 0;"><strong>📞 Telefon:</strong> +420 725 965 826</p>
            <p style="margin: 5px 0;"><strong>📧 Email:</strong> reklamace@wgs-service.cz</p>
        </div>

        <p>Těšíme se na Vaši zpětnou vazbu.</p>

        <p>S pozdravem,<br>
        <strong>Tým White Glove Service</strong></p>
    </div>

    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 12px;">
        <p>© 2025 White Glove Service | Natuzzi Authorized Service</p>
        <p>Do Dubče 364, Běchovice 190 11 CZ | Tel: +420 725 965 826</p>
    </div>
</body>
</html>
HTML;

                $stmt->execute([
                    'id' => 'contact_attempt',
                    'name' => 'Pokus o kontakt',
                    'description' => 'Email odeslaný zákazníkovi po neúspěšném pokusu o telefonický kontakt',
                    'type' => 'email',
                    'trigger_event' => 'contact_attempt',
                    'recipient_type' => 'customer',
                    'subject' => 'Pokusili jsme se Vás kontaktovat - WGS Service',
                    'template' => $template,
                    'active' => 1
                ]);

                echo "<div class='success'>";
                echo "<strong>✅ Email šablona úspěšně přidána!</strong><br><br>";
                echo "Název: <strong>Pokus o kontakt</strong><br>";
                echo "ID: <strong>contact_attempt</strong><br>";
                echo "Typ: email<br>";
                echo "Příjemce: zákazník<br>";
                echo "Trigger: contact_attempt<br>";
                echo "</div>";

            } else {
                echo "<div class='warning'>";
                echo "<strong>Šablona již existuje, nebyla přidána znovu.</strong>";
                echo "</div>";
            }

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
            echo "Můžete nyní používat tuto šablonu pro automatické odesílání emailů při pokusu o kontakt.";
            echo "</div>";

            echo "<h3>Náhled šablony:</h3>";
            echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
            echo htmlspecialchars($template);
            echo "</pre>";

            echo "<div class='info'>";
            echo "<strong>Použití v kódu:</strong><br><br>";
            echo "<pre style='background: #282c34; color: #abb2bf; padding: 15px; border-radius: 5px;'>";
            echo htmlspecialchars("// V JavaScript (seznam.js)
fetch('/api/send_contact_attempt_email.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    reklamace_id: record.id,
    csrf_token: csrfToken
  })
});");
            echo "</pre>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA PŘI MIGRACI:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }

    } else {
        // Náhled co bude provedeno
        echo "<div class='info'>";
        echo "<h3>Co bude provedeno:</h3>";
        echo "<ul>";
        echo "<li>Přidána nová email šablona 'Pokus o kontakt'</li>";
        echo "<li>Typ: email</li>";
        echo "<li>Příjemce: zákazník</li>";
        echo "<li>Trigger event: contact_attempt</li>";
        echo "<li>Obsahuje proměnné: {{customer_name}}, {{order_id}}, {{product}}, {{date}}</li>";
        echo "</ul>";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='admin.php?tab=email-sms' class='btn' style='background: #666;'>Zpět na Admin</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
