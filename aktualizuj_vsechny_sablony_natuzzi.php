<?php
/**
 * Migrace: Aktualizace všech email šablon - přidání Natuzzi branding
 *
 * Tento skript aktualizuje VŠECHNY email šablony aby obsahovaly:
 * - Informaci o zastoupení firmy Natuzzi
 * - Profesionální formátování
 * - Upravené SMS texty
 * - Jednotný firemní styl
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
    <title>Migrace: Aktualizace všech šablon - Natuzzi branding</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
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
              overflow-x: auto; white-space: pre-wrap; font-size: 0.85rem; }
        .template-box { border: 2px solid #2D5016; padding: 15px;
                        margin: 15px 0; border-radius: 5px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Aktualizace všech email šablon</h1>";

    // 1. Kontrola současného stavu
    echo "<div class='info'><strong>KONTROLA SOUČASNÉHO STAVU...</strong></div>";

    $stmt = $pdo->query("SELECT id, name, subject FROM wgs_notifications ORDER BY id");
    $sablony = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($sablony) === 0) {
        echo "<div class='error'><strong>CHYBA:</strong> Žádné šablony nenalezeny v databázi!</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='success'>Nalezeno " . count($sablony) . " šablon v databázi:</div>";
    echo "<ul>";
    foreach ($sablony as $s) {
        echo "<li><strong>{$s['name']}</strong> (ID: {$s['id']})</li>";
    }
    echo "</ul>";

    // 2. Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM AKTUALIZACI VŠECH ŠABLON...</strong></div>";

        // Definice všech šablon s PŘESNÝMI názvy z databáze
        $sablony_data = [
            'Pokus o kontakt' => [
                'subject' => 'Pokusili jsme se Vás kontaktovat - Servis Natuzzi',
                'template' => 'Dobrý den {{customer_name}},

kontaktujeme Vás v zastoupení firmy Natuzzi ohledně servisního požadavku.
Pokusili jsme se Vás telefonicky kontaktovat, bohužel se nám to nepodařilo.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
INFORMACE O VAŠÍ SERVISNÍ ZAKÁZCE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Číslo zakázky: {{order_id}}
Produkt Natuzzi: {{product}}
Adresa servisu: {{address}}
Datum pokusu o kontakt: {{date}}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
PROSÍME O ZPĚTNÉ ZAVOLÁNÍ
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Váš kontaktní technik: {{technician_name}}
Email: {{technician_email}}
Telefon: {{technician_phone}}

Zavolejte prosím zpět na výše uvedené číslo.
Rádi s Vámi domluvíme vhodný termín návštěvy našeho servisního technika.

S pozdravem,
{{technician_name}}
White Glove Service
Autorizovaný servis Natuzzi

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
KONTAKT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Web: www.wgs-service.cz
Email: {{company_email}}
Telefon: {{company_phone}}'
            ],

            'Potvrzení termínu návštěvy' => [
                'subject' => 'Potvrzení termínu servisní návštěvy - Natuzzi',
                'template' => 'Dobrý den {{customer_name}},

potvrzujeme Vám termín servisní návštěvy v zastoupení firmy Natuzzi.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
POTVRZENÝ TERMÍN NÁVŠTĚVY
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Číslo zakázky: {{order_id}}
Produkt Natuzzi: {{product}}
Datum návštěvy: {{date}}
Čas návštěvy: {{time}}
Adresa: {{address}}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
VÁŠ KONTAKTNÍ TECHNIK
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Jméno: {{technician_name}}
Email: {{technician_email}}
Telefon: {{technician_phone}}

Náš technik se dostaví na uvedenou adresu v dohodnutém termínu.
Pokud potřebujete termín změnit, kontaktujte nás prosím co nejdříve.

S pozdravem,
White Glove Service
Autorizovaný servis Natuzzi

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
KONTAKT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Web: www.wgs-service.cz
Email: {{company_email}}
Telefon: {{company_phone}}'
            ],

            'Zakázka dokončena' => [
                'subject' => 'Servisní zakázka dokončena - Natuzzi',
                'template' => 'Dobrý den {{customer_name}},

informujeme Vás, že servisní zakázka pro Váš produkt Natuzzi byla úspěšně dokončena.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
INFORMACE O DOKONČENÉ ZAKÁZCE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Číslo zakázky: {{order_id}}
Produkt Natuzzi: {{product}}
Datum dokončení: {{date}}
Adresa: {{address}}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
VÁŠ SERVISNÍ TECHNIK
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Jméno: {{technician_name}}
Email: {{technician_email}}
Telefon: {{technician_phone}}

Děkujeme za využití našich služeb.
Pokud budete mít jakékoliv dotazy nebo připomínky, neváhejte nás kontaktovat.

S pozdravem,
White Glove Service
Autorizovaný servis Natuzzi

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
KONTAKT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Web: www.wgs-service.cz
Email: {{company_email}}
Telefon: {{company_phone}}'
            ],

            'Nová reklamace vytvořena' => [
                'subject' => 'Nová servisní zakázka Natuzzi - č. {{order_id}}',
                'template' => 'Dobrý den {{customer_name}},

byla vytvořena nová servisní zakázka pro Váš produkt Natuzzi.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
INFORMACE O SERVISNÍ ZAKÁZCE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Číslo zakázky: {{order_id}}
Produkt Natuzzi: {{product}}
Datum vytvoření: {{date}}
Adresa servisu: {{address}}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
DALŠÍ POSTUP
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Náš technik Vás bude kontaktovat pro domluvení termínu servisní návštěvy.

Pokud máte jakékoliv dotazy, neváhejte nás kontaktovat.

S pozdravem,
White Glove Service
Autorizovaný servis Natuzzi

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
KONTAKT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Web: www.wgs-service.cz
Email: {{company_email}}
Telefon: {{company_phone}}'
            ],

            'Připomenutí termínu zákazníkovi' => [
                'subject' => 'Připomínka termínu servisní návštěvy - Natuzzi',
                'template' => 'Dobrý den {{customer_name}},

připomínáme Vám blížící se termín servisní návštěvy pro Váš produkt Natuzzi.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
TERMÍN SERVISNÍ NÁVŠTĚVY
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Číslo zakázky: {{order_id}}
Produkt Natuzzi: {{product}}
Datum návštěvy: {{date}}
Čas návštěvy: {{time}}
Adresa: {{address}}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
VÁŠ KONTAKTNÍ TECHNIK
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Jméno: {{technician_name}}
Email: {{technician_email}}
Telefon: {{technician_phone}}

Těšíme se na Vás v dohodnutém termínu.
Pokud potřebujete termín změnit, kontaktujte nás prosím co nejdříve.

S pozdravem,
White Glove Service
Autorizovaný servis Natuzzi

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
KONTAKT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Web: www.wgs-service.cz
Email: {{company_email}}
Telefon: {{company_phone}}'
            ],

            'Zakázka znovu otevřena' => [
                'subject' => 'Servisní zakázka Natuzzi znovu otevřena - č. {{order_id}}',
                'template' => 'Dobrý den {{customer_name}},

vaše servisní zakázka pro produkt Natuzzi byla znovu otevřena.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
INFORMACE O ZAKÁZCE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Číslo zakázky: {{order_id}}
Produkt Natuzzi: {{product}}
Datum znovuotevření: {{date}}
Adresa: {{address}}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
DALŠÍ POSTUP
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Náš technik Vás bude kontaktovat pro domluvení nového termínu návštěvy.

Pokud máte jakékoliv dotazy, neváhejte nás kontaktovat.

S pozdravem,
White Glove Service
Autorizovaný servis Natuzzi

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
KONTAKT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Web: www.wgs-service.cz
Email: {{company_email}}
Telefon: {{company_phone}}'
            ]
        ];

        $pdo->beginTransaction();

        try {
            $updatedCount = 0;

            foreach ($sablony_data as $nazev => $data) {
                $stmt = $pdo->prepare("
                    UPDATE wgs_notifications
                    SET
                        subject = :subject,
                        template = :template,
                        updated_at = NOW()
                    WHERE name = :name
                ");

                $result = $stmt->execute([
                    'subject' => $data['subject'],
                    'template' => $data['template'],
                    'name' => $nazev
                ]);

                if ($result && $stmt->rowCount() > 0) {
                    $updatedCount++;
                    echo "<div class='success'>✓ Aktualizována šablona: <strong>{$nazev}</strong></div>";
                } else {
                    echo "<div class='warning'>⚠ Šablona '{$nazev}' nebyla nalezena nebo nebyla změněna</div>";
                }
            }

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✅ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
            echo "Aktualizováno šablon: {$updatedCount} / " . count($sablony_data) . "<br>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<strong>✅ CO BYLO PROVEDENO:</strong><br>";
            echo "• Přidána informace o zastoupení firmy Natuzzi<br>";
            echo "• Jednotný profesionální formát pro všechny šablony<br>";
            echo "• Zachovány všechny proměnné ({{customer_name}}, {{order_id}}, atd.)<br>";
            echo "• Přidáno čitelné formátování s oddělovači<br>";
            echo "• Aktualizováno updated_at pole u všech šablon<br>";
            echo "• Proměnné pro technika: {{technician_name}}, {{technician_email}}, {{technician_phone}}<br>";
            echo "• Proměnné pro firmu: {{company_email}}, {{company_phone}}<br>";
            echo "</div>";

            echo "<div class='success'>";
            echo "<strong>📧 PŘÍKLAD AKTUALIZOVANÉ ŠABLONY:</strong><br>";
            echo "<div class='template-box'>";
            echo "<pre>" . htmlspecialchars($sablony_data['Pokus o kontakt']['template']) . "</pre>";
            echo "</div>";
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
        echo "• Aktualizace <strong>6 hlavních email šablon</strong>:<br>";
        echo "&nbsp;&nbsp;- Pokus o kontakt<br>";
        echo "&nbsp;&nbsp;- Potvrzení termínu návštěvy<br>";
        echo "&nbsp;&nbsp;- Zakázka dokončena<br>";
        echo "&nbsp;&nbsp;- Nová reklamace vytvořena<br>";
        echo "&nbsp;&nbsp;- Připomenutí termínu zákazníkovi<br>";
        echo "&nbsp;&nbsp;- Zakázka znovu otevřena<br>";
        echo "• Přidání informace o zastoupení firmy Natuzzi<br>";
        echo "• Jednotný profesionální formát všech šablon<br>";
        echo "• Zachování všech proměnných<br>";
        echo "• Přidání proměnných pro technika a firmu<br>";
        echo "</div>";

        echo "<h3>📧 Náhled nové šablony 'Pokus o kontakt':</h3>";
        echo "<div class='template-box'>";
        echo "<pre style='border: 2px solid #2D5016;'>";
        echo htmlspecialchars("Dobrý den {{customer_name}},

kontaktujeme Vás v zastoupení firmy Natuzzi ohledně servisního požadavku.
Pokusili jsme se Vás telefonicky kontaktovat, bohužel se nám to nepodařilo.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
INFORMACE O VAŠÍ SERVISNÍ ZAKÁZCE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Číslo zakázky: {{order_id}}
Produkt Natuzzi: {{product}}
...");
        echo "</pre>";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>✅ SPUSTIT AKTUALIZACI</a>";
        echo "<a href='/admin.php' class='btn' style='background: #666;'>← Zpět do Admin panelu</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
