<?php
/**
 * Migrace: Aktualizace šablony "Nová reklamace vytvořena"
 *
 * Tento skript aktualizuje email šablonu pro zákazníka při vytvoření nové reklamace.
 * Můžete jej spustit vícekrát - přepíše existující šablonu.
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
    <title>Migrace: Aktualizace šablony nové reklamace</title>
    <style>
        body { font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .preview { background: #f8f9fa; border: 1px solid #dee2e6;
                   padding: 15px; border-radius: 5px; margin: 15px 0;
                   white-space: pre-wrap; font-family: monospace; font-size: 0.9rem; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #000; }
        .btn-back { background: #666; }
    </style>
</head>
<body>
<div class='container'>";

// Nová šablona
$novaSubject = 'Potvrzení přijetí Vaší žádosti o servis - WGS Service č. {{order_id}}';

$novaSablona = 'Dobrý den,

děkujeme, že jste se na nás obrátili. Vaše žádost o servis byla úspěšně zaregistrována v našem systému.

INFORMACE O VAŠÍ ŽÁDOSTI:
Číslo zakázky: {{order_id}}
Datum přijetí: {{created_at}}
Zadal: {{created_by}}

CO BUDE NÁSLEDOVAT:
Náš technik nyní důkladně prostuduje Vaši žádost a veškeré poskytnuté podklady. Jakmile bude mít k dispozici všechny potřebné informace, spojí se s Vámi a navrhne možné termíny návštěvy.

Prosíme o trpělivost - snažíme se každou žádost zpracovat co nejdříve a s maximální péčí.

V případě jakýchkoli dotazů se na nás neváhejte obrátit:
Email: reklamace@wgs-service.cz
Telefon: +420 725 965 826

Děkujeme za Vaši důvěru.

S pozdravem,
Tým White Glove Service
www.wgs-service.cz';

$variables = json_encode([
    '{{order_id}}',
    '{{created_at}}',
    '{{created_by}}',
    '{{customer_name}}',
    '{{customer_email}}',
    '{{customer_phone}}'
]);

try {
    $pdo = getDbConnection();

    echo "<h1>Aktualizace šablony: Nová reklamace vytvořena</h1>";

    // Zobrazit náhled
    echo "<div class='info'><strong>NÁHLED NOVÉ ŠABLONY:</strong></div>";
    echo "<div class='preview'><strong>Předmět:</strong>\n" . htmlspecialchars($novaSubject) . "\n\n<strong>Obsah:</strong>\n" . htmlspecialchars($novaSablona) . "</div>";

    // Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM AKTUALIZACI...</strong></div>";

        // Zkontrolovat, jestli existuje šablona
        $stmt = $pdo->prepare("SELECT id, recipient_type FROM wgs_notifications WHERE id = 'order_created'");
        $stmt->execute();
        $existujici = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existujici) {
            // Aktualizovat existující šablonu
            $stmt = $pdo->prepare("
                UPDATE wgs_notifications
                SET
                    name = 'Potvrzení přijetí žádosti o servis',
                    description = 'Email odesílaný zákazníkovi při vytvoření nové reklamace/žádosti o servis',
                    recipient_type = 'customer',
                    subject = :subject,
                    template = :template,
                    variables = :variables,
                    active = 1,
                    updated_at = NOW()
                WHERE id = 'order_created'
            ");
            $stmt->execute([
                'subject' => $novaSubject,
                'template' => $novaSablona,
                'variables' => $variables
            ]);

            echo "<div class='success'>";
            echo "<strong>ŠABLONA ÚSPĚŠNĚ AKTUALIZOVÁNA</strong><br><br>";
            echo "ID: order_created<br>";
            echo "Příjemce změněn z: <strong>" . htmlspecialchars($existujici['recipient_type']) . "</strong> na: <strong>customer</strong>";
            echo "</div>";
        } else {
            // Vytvořit novou šablonu
            $stmt = $pdo->prepare("
                INSERT INTO wgs_notifications (
                    id, name, description, trigger_event, recipient_type, type, subject, template, variables, active
                ) VALUES (
                    'order_created',
                    'Potvrzení přijetí žádosti o servis',
                    'Email odesílaný zákazníkovi při vytvoření nové reklamace/žádosti o servis',
                    'order_created',
                    'customer',
                    'email',
                    :subject,
                    :template,
                    :variables,
                    1
                )
            ");
            $stmt->execute([
                'subject' => $novaSubject,
                'template' => $novaSablona,
                'variables' => $variables
            ]);

            echo "<div class='success'>";
            echo "<strong>ŠABLONA ÚSPĚŠNĚ VYTVOŘENA</strong>";
            echo "</div>";
        }

        echo "<a href='/admin.php' class='btn btn-back'>Zpět do admin</a>";

    } else {
        // Zobrazit aktuální stav
        $stmt = $pdo->prepare("SELECT * FROM wgs_notifications WHERE id = 'order_created'");
        $stmt->execute();
        $aktualni = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($aktualni) {
            echo "<div class='info'><strong>AKTUÁLNÍ ŠABLONA V DATABÁZI:</strong></div>";
            echo "<div class='preview'><strong>Příjemce:</strong> " . htmlspecialchars($aktualni['recipient_type']) . "\n<strong>Předmět:</strong> " . htmlspecialchars($aktualni['subject']) . "\n\n<strong>Obsah:</strong>\n" . htmlspecialchars($aktualni['template']) . "</div>";
        } else {
            echo "<div class='info'>Šablona 'order_created' zatím neexistuje - bude vytvořena.</div>";
        }

        echo "<a href='?execute=1' class='btn'>AKTUALIZOVAT ŠABLONU</a>";
        echo "<a href='/admin.php' class='btn btn-back'>Zrušit</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
