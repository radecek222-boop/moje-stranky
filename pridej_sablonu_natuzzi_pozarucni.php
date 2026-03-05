<?php
/**
 * Migrace: Přidání emailové šablony pro pozáruční servis Natuzzi
 *
 * Tento skript přidá novou marketingovou šablonu do systému.
 * Můžete jej spustit vícekrát - neprovede duplicitní vložení.
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/email_template_base.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Šablona Natuzzi pozáruční servis</title>
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
              overflow-x: auto; font-size: 13px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Emailová šablona - Natuzzi pozáruční servis</h1>";

    // Kontrola, zda šablona již existuje
    $stmt = $pdo->prepare("SELECT id, name FROM wgs_notifications WHERE trigger_event = :trigger AND recipient_type = :recipient");
    $stmt->execute([
        'trigger' => 'marketing_natuzzi_pozarucni',
        'recipient' => 'customer'
    ]);
    $existujici = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existujici) {
        echo "<div class='warning'>";
        echo "<strong>UPOZORNĚNÍ:</strong> Šablona již existuje v databázi.<br>";
        echo "ID: {$existujici['id']}<br>";
        echo "Název: {$existujici['name']}";
        echo "</div>";
        echo "<p>Pro aktualizaci šablony použijte administrační rozhraní v Control Centre.</p>";
    } else {
        // Pokud je nastaveno ?execute=1, provést vložení
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUŠTÍM VLOŽENÍ ŠABLONY...</strong></div>";

            // Připravit data pro šablonu
            $sablonData = [
                'nadpis' => 'NATUZZI – Pozáruční servis',
                'osloveni' => '{{customer_name}}',
                'obsah' => '**Dovolujeme si Vás kontaktovat, protože Váš email máme z naší předchozí spolupráce** – ať už z doručení nábytku Natuzzi nebo z poskytnutého servisu.

Rádi bychom Vás informovali, že pro společnost **Natuzzi** poskytujeme komplexní **pozáruční servisní služby** a jsme tu pro Vás i nadále.

### Naše služby zahrnují:

- **Řešení vad prosezení** – obnova komfortu sedacích ploch
- **Profesionální přečalounění** – včetně výběru kvalitních materiálů
- **Opravy elektrických prvků** – ovládání polohování, LED osvětlení, USB nabíječky, výměna spínačů, výměna motoru apod.
- **Opravy mechanismů** – výsuvné mechanismy, polohování, otočné hlavy
- **Čištění kožených sedaček** – výhradně originálními prostředky Natuzzi

**Prosezení sedačky není vada, se kterou se musíte smířit!** Většinu problémů vyřešíme během jediné návštěvy přímo u Vás doma – **bez nutnosti odvážet nábytek**. Nemusíte mít obavu z přepravy ani z toho, že byste zůstali bez místa k sezení. Přes 90 % našich oprav lze provést na místě a Vaše sedačka bude vypadat a fungovat jako nová.

Pro čištění používáme **pouze produkty Natuzzi**, které jsou chemicky sladěné s impregnací a povrchovou úpravou Vašeho nábytku. Tím zajišťujeme maximální péči a dlouhou životnost sedacích souprav.',
                'upozorneni' => '**Máte zájem o více informací?**

Navštivte naše webové stránky [www.wgs-service.cz](https://www.wgs-service.cz), kde najdete:
- Kompletní přehled našich služeb
- Cenové podmínky
- Online objednávkový formulář
- Kontaktní údaje a provozní dobu',
                'infobox' => '💡 **Tip:** Pravidelné čištění a údržba kožených sedaček 1-2× ročně výrazně prodlouží jejich životnost a zachová luxusní vzhled.',
                'tlacitko' => [
                    'text' => 'Objednat servis online',
                    'url' => 'https://www.wgs-service.cz/objednatservis.php'
                ]
            ];

            $templateJson = vytvorSablonuJSON($sablonData);

            $pdo->beginTransaction();

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO wgs_notifications (
                        id,
                        name,
                        description,
                        trigger_event,
                        recipient_type,
                        type,
                        subject,
                        template,
                        template_data,
                        active,
                        created_at
                    ) VALUES (
                        :id,
                        :name,
                        :description,
                        :trigger_event,
                        :recipient_type,
                        :type,
                        :subject,
                        :template,
                        :template_data,
                        :active,
                        NOW()
                    )
                ");

                $stmt->execute([
                    'id' => 'marketing_natuzzi_pozarucni',
                    'name' => 'Natuzzi - Pozáruční servis (Marketing)',
                    'description' => 'Marketingový email pro existující zákazníky Natuzzi informující o dostupnosti pozáručních služeb',
                    'trigger_event' => 'marketing_natuzzi_pozarucni',
                    'recipient_type' => 'customer',
                    'type' => 'email',
                    'subject' => 'NATUZZI – Pozáruční servis | WGS Service',
                    'template' => '', // Prázdné - používáme template_data
                    'template_data' => $templateJson,
                    'active' => 1
                ]);

                $novyId = 'marketing_natuzzi_pozarucni';

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>✅ ŠABLONA ÚSPĚŠNĚ PŘIDÁNA</strong><br>";
                echo "ID nové šablony: <strong>{$novyId}</strong><br>";
                echo "Název: Natuzzi - Pozáruční servis (Marketing)<br>";
                echo "Trigger: marketing_natuzzi_pozarucni<br>";
                echo "Typ: email<br>";
                echo "Status: Aktivní";
                echo "</div>";

                echo "<div class='info'>";
                echo "<strong>📧 Šablonu nyní můžete použít v Control Centre:</strong><br>";
                echo "1. Přejděte do <a href='/admin.php'>Control Centre</a><br>";
                echo "2. Karta 'Email & SMS'<br>";
                echo "3. Najděte šablonu 'Natuzzi - Pozáruční servis (Marketing)'<br>";
                echo "4. Upravte nebo použijte pro hromadné odeslání";
                echo "</div>";

                echo "<h3>Náhled šablony JSON:</h3>";
                echo "<pre>" . htmlspecialchars($templateJson) . "</pre>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>CHYBA PŘI VKLÁDÁNÍ:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }

        } else {
            // Zobrazit náhled před provedením
            echo "<div class='info'>";
            echo "<strong>📋 NÁHLED ŠABLONY:</strong><br><br>";
            echo "<strong>Název:</strong> Natuzzi - Pozáruční servis (Marketing)<br>";
            echo "<strong>Popis:</strong> Marketingový email pro existující zákazníky Natuzzi<br>";
            echo "<strong>Trigger:</strong> marketing_natuzzi_pozarucni<br>";
            echo "<strong>Příjemce:</strong> customer<br>";
            echo "<strong>Předmět:</strong> NATUZZI – Pozáruční servis | WGS Service<br>";
            echo "</div>";

            echo "<div class='warning'>";
            echo "<strong>⚠️ DŮLEŽITÉ:</strong><br>";
            echo "Tato šablona bude přidána do tabulky <code>wgs_notifications</code>.<br>";
            echo "Po přidání bude dostupná v Control Centre pro další úpravy a použití.";
            echo "</div>";

            echo "<a href='?execute=1' class='btn'>✅ PŘIDAT ŠABLONU DO DATABÁZE</a>";
            echo "<a href='/admin.php' class='btn' style='background: #6c757d;'>❌ Zrušit</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
