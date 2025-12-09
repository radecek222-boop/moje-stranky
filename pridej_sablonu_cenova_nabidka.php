<?php
/**
 * Migrace: Přidání šablon pro cenové nabídky
 *
 * Tento skript přidá emailovou šablonu pro:
 * 1. Odeslání cenové nabídky zákazníkovi
 * 2. Potvrzení přijetí nabídky zákazníkem
 *
 * Spouští se z admin panelu cenova-nabidka.php
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
    <title>Migrace: Šablony - Cenové nabídky</title>
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

    echo "<h1>Migrace: Šablony cenových nabídek</h1>";

    // ========================================
    // Definice šablon
    // ========================================
    echo "<h2>Emailové šablony pro cenové nabídky</h2>";

    // Šablona 1: Odeslání cenové nabídky
    $sablonaNabidka = [
        'id' => 'price_quote_sent',
        'name' => 'Cenová nabídka - odeslání',
        'description' => 'Email odesílaný zákazníkovi s cenovou nabídkou. Obsahuje položky, ceny a tlačítko pro potvrzení.',
        'trigger_event' => 'price_quote_sent',
        'recipient_type' => 'customer',
        'type' => 'email',
        'subject' => 'Cenová nabídka č. {{quote_number}} - White Glove Service',
        'template' => 'Vážený/á {{customer_name}},

děkujeme za Váš zájem o naše služby. Na základě Vašeho požadavku jsme pro Vás připravili následující cenovou nabídku:

Číslo nabídky: {{quote_number}}
Datum vytvoření: {{created_date}}
Platnost do: {{valid_until}}

POLOŽKY NABÍDKY:
{{items_list}}

CELKOVÁ CENA (bez DPH): {{total_price}} {{currency}}

---

Pro potvrzení nabídky klikněte na následující odkaz:
{{confirmation_url}}

---

DŮLEŽITÉ UPOZORNĚNÍ:
Kliknutím na tlačítko "Potvrdit nabídku" potvrzujete, že souhlasíte s touto cenovou nabídkou a uzavíráte tím závaznou smlouvu o dílo dle § 2586 občanského zákoníku s White Glove Service, s.r.o.

Ceny jsou uvedeny bez DPH. U náhradních dílů můžeme požadovat zálohu ve výši jejich ceny. Doba dodání originálních dílů z továrny Natuzzi je 4–8 týdnů.

S pozdravem,
White Glove Service
Tel: +420 725 965 826
Email: reklamace@wgs-service.cz
www.wgs-service.cz',
        'variables' => json_encode([
            '{{customer_name}}',
            '{{quote_number}}',
            '{{created_date}}',
            '{{valid_until}}',
            '{{items_list}}',
            '{{total_price}}',
            '{{currency}}',
            '{{confirmation_url}}'
        ]),
        'active' => 1
    ];

    // Šablona 2: Potvrzení přijetí nabídky
    $sablonaPotvrzeni = [
        'id' => 'price_quote_confirmed',
        'name' => 'Cenová nabídka - potvrzení zákazníkem',
        'description' => 'Email odesílaný zákazníkovi po potvrzení cenové nabídky. Obsahuje souhrn objednávky a další kroky.',
        'trigger_event' => 'price_quote_confirmed',
        'recipient_type' => 'customer',
        'type' => 'email',
        'subject' => 'Potvrzení objednávky č. {{quote_number}} - White Glove Service',
        'template' => 'Vážený/á {{customer_name}},

děkujeme za potvrzení cenové nabídky. Vaše objednávka byla úspěšně přijata.

SOUHRN OBJEDNÁVKY:
Číslo nabídky: {{quote_number}}
Datum potvrzení: {{confirmed_date}}
Čas potvrzení: {{confirmed_time}}

POLOŽKY:
{{items_list}}

CELKOVÁ CENA (bez DPH): {{total_price}} {{currency}}

---

CO BUDE NÁSLEDOVAT:
1. Náš tým Vás bude kontaktovat pro domluvení termínu servisu
2. Připravíme potřebné materiály a náhradní díly
3. Technik přijede v domluveném termínu

---

Toto elektronické potvrzení bylo zaznamenáno a má právní platnost dle § 2586 občanského zákoníku (smlouva o dílo).

V případě dotazů nás neváhejte kontaktovat.

S pozdravem,
White Glove Service
Tel: +420 725 965 826
Email: reklamace@wgs-service.cz
www.wgs-service.cz',
        'variables' => json_encode([
            '{{customer_name}}',
            '{{quote_number}}',
            '{{confirmed_date}}',
            '{{confirmed_time}}',
            '{{items_list}}',
            '{{total_price}}',
            '{{currency}}'
        ]),
        'active' => 1
    ];

    // Šablona 3: Notifikace adminovi o potvrzení
    $sablonaAdminNotif = [
        'id' => 'price_quote_confirmed_admin',
        'name' => 'Cenová nabídka - notifikace admin',
        'description' => 'Interní notifikace pro admina při potvrzení nabídky zákazníkem.',
        'trigger_event' => 'price_quote_confirmed',
        'recipient_type' => 'admin',
        'type' => 'email',
        'subject' => 'Nabídka č. {{quote_number}} POTVRZENA - {{customer_name}}',
        'template' => 'Zákazník potvrdil cenovou nabídku:

ZÁKAZNÍK:
Jméno: {{customer_name}}
Email: {{customer_email}}
Telefon: {{customer_phone}}
Adresa: {{customer_address}}

NABÍDKA:
Číslo: {{quote_number}}
Celková cena: {{total_price}} {{currency}}

POLOŽKY:
{{items_list}}

POTVRZENÍ:
Datum: {{confirmed_date}} {{confirmed_time}}
IP adresa: {{ip_address}}

---
Kontaktujte zákazníka pro domluvení termínu servisu.',
        'variables' => json_encode([
            '{{customer_name}}',
            '{{customer_email}}',
            '{{customer_phone}}',
            '{{customer_address}}',
            '{{quote_number}}',
            '{{total_price}}',
            '{{currency}}',
            '{{items_list}}',
            '{{confirmed_date}}',
            '{{confirmed_time}}',
            '{{ip_address}}'
        ]),
        'active' => 1
    ];

    $vsechnySablony = [$sablonaNabidka, $sablonaPotvrzeni, $sablonaAdminNotif];

    // Kontrola zda šablony už existují
    echo "<h2>Kontrola existujících šablon</h2>";

    $ids = array_map(fn($s) => $s['id'], $vsechnySablony);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name, type FROM wgs_notifications WHERE id IN ($placeholders)");
    $stmt->execute($ids);
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
    foreach ($vsechnySablony as $sablona) {
        echo "<h2>Náhled: {$sablona['name']}</h2>";
        echo "<p><strong>ID:</strong> <code>{$sablona['id']}</code></p>";
        echo "<p><strong>Spouštěč:</strong> <code>{$sablona['trigger_event']}</code></p>";
        echo "<p><strong>Příjemce:</strong> {$sablona['recipient_type']}</p>";
        echo "<p><strong>Předmět:</strong> <code>" . htmlspecialchars($sablona['subject']) . "</code></p>";
        echo "<div class='template-preview'>" . htmlspecialchars($sablona['template']) . "</div>";
    }

    // Provedení migrace
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<h2>Provádím migraci...</h2>";

        $pdo->beginTransaction();

        try {
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

            foreach ($vsechnySablony as $sablona) {
                $stmt->execute([
                    'id' => $sablona['id'],
                    'name' => $sablona['name'],
                    'description' => $sablona['description'],
                    'trigger_event' => $sablona['trigger_event'],
                    'recipient_type' => $sablona['recipient_type'],
                    'type' => $sablona['type'],
                    'subject' => $sablona['subject'],
                    'template' => $sablona['template'],
                    'variables' => $sablona['variables'],
                    'active' => $sablona['active']
                ]);
                echo "<div class='success'>Šablona '<strong>{$sablona['name']}</strong>' byla uložena.</div>";
            }

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>";
            echo "Všechny šablony byly přidány do databáze.";
            echo "</div>";

            echo "<h2>Další kroky</h2>";
            echo "<div class='info'>";
            echo "<p>Šablony jsou nyní k dispozici v Admin panelu pod záložkou <strong>Email & SMS > Šablony</strong>.</p>";
            echo "<p>URL: <a href='admin.php?tab=notifications&section=templates'>admin.php?tab=notifications&section=templates</a></p>";
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
