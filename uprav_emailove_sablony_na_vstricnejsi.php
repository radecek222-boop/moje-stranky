<?php
/**
 * Úprava emailových šablon na vstřícnější verze
 *
 * Tento skript upraví všechny emailové šablony, aby byly méně strohé
 * a více přátelské pro zákazníky i zaměstnance.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit úpravu šablon.");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Úprava emailových šablon</title>
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
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .template-box { background: #f9f9f9; padding: 15px; margin: 15px 0;
                        border-left: 4px solid #2D5016; border-radius: 4px; }
        .template-name { font-weight: bold; color: #2D5016; margin-bottom: 10px;
                         font-size: 1.1rem; }
        .template-before { background: #fff3cd; padding: 10px; margin: 5px 0;
                           border-radius: 4px; white-space: pre-wrap;
                           font-family: monospace; font-size: 0.85rem; }
        .template-after { background: #d4edda; padding: 10px; margin: 5px 0;
                          border-radius: 4px; white-space: pre-wrap;
                          font-family: monospace; font-size: 0.85rem; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 14px; }
        .btn:hover { background: #1a300d; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>📧 Úprava emailových šablon na vstřícnější verze</h1>";

try {
    $pdo = getDbConnection();

    // Nové vstřícnější šablony
    $sablony = [
        'new_complaint' => [
            'name' => 'Nová reklamace vytvořena',
            'subject' => 'Nová reklamace #{{order_id}} - {{customer_name}}',
            'template' => 'Dobrý den,

máme pro vás informaci o nové reklamaci, která byla právě vytvořena v systému.

📋 INFORMACE O ZÁKAZNÍKOVI:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
👤 Zákazník: {{customer_name}}
📞 Telefon: {{customer_phone}}
📧 Email: {{customer_email}}
📍 Adresa: {{address}}

🛋️ INFORMACE O PRODUKTU:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Produkt: {{product}}

📝 POPIS PROBLÉMU:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{{description}}

✅ DALŠÍ KROKY:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Prosím o co nejrychlejší zpracování a přidělení termínu technikovi.

Vytvořeno: {{created_at}}
Číslo zakázky: #{{order_id}}

Přejeme hezký den!
Váš tým White Glove Service'
        ],
        'appointment_confirmed' => [
            'name' => 'Potvrzení termínu návštěvy',
            'subject' => 'Potvrzení termínu návštěvy - White Glove Service',
            'template' => 'Dobrý den {{customer_name}},

děkujeme za Vaši důvěru! S radostí Vám potvrzujeme termín návštěvy našeho technika.

📅 VÁŠ TERMÍN:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🗓️ Datum: {{date}}
⏰ Čas: {{time}}
📋 Číslo zakázky: {{order_id}}

✅ CO VÁS ČEKÁ:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
• Náš technik dorazí ve smluvený čas
• Prohlédne si Váš nábytek a posoudí stav
• Navrhne řešení a postup opravy
• Odpovídá na všechny Vaše dotazy

💡 PROSÍME O PŘÍPRAVU:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
• Zajistěte prosím přístup k nábytkuNáš technik Vás bude kontaktovat den předem pro potvrzení.

📞 POTŘEBUJETE ZMĚNIT TERMÍN?
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Kontaktujte nás prosím co nejdříve:
📧 Email: reklamace@wgs-service.cz
📞 Telefon: [váš telefon]

S pozdravem,
Váš tým White Glove Service

─────────────────────────────────
White Glove Service | Premium nábytkový servis
www.wgs-service.cz'
        ],
        'appointment_reminder_customer' => [
            'name' => 'Připomenutí termínu zákazníkovi',
            'subject' => 'Připomínka: Zítra přijede náš technik! ⏰',
            'template' => 'Dobrý den {{customer_name}},

jen Vám chceme připomenout, že **zítra** k Vám přijede náš technik! 😊

📅 ZÍTŘEJŠÍ TERMÍN:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🗓️ Datum: {{date}}
⏰ Čas: {{time}}
📍 Adresa: {{address}}
📋 Číslo zakázky: {{order_id}}

✅ PŘIPRAVTE SI PROSÍM:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
• Přístup k nábytkuNáš technik se na Vás těší! V případě potřeby Vás bude kontaktovat dnes odpoledne.

⚠️ POTŘEBUJETE PŘELOŽIT TERMÍN?
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Kontaktujte nás prosím DNES:
📧 Email: reklamace@wgs-service.cz
📞 Telefon: [váš telefon]

Děkujeme a těšíme se na Vás!

S úctou,
Váš tým White Glove Service

─────────────────────────────────
White Glove Service | Premium nábytkový servis
www.wgs-service.cz'
        ],
        'appointment_assigned_technician' => [
            'name' => 'Přiřazení termínu technikovi',
            'subject' => 'Nový servisní termín: {{date}} v {{time}} 🔧',
            'template' => 'Dobrý den {{technician_name}},

máme pro Vás nový servisní termín! Prosím o potvrzení dostupnosti.

📅 TERMÍN:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🗓️ Datum: {{date}}
⏰ Čas: {{time}}

👤 INFORMACE O ZÁKAZNÍKOVI:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Jméno: {{customer_name}}
📞 Telefon: {{customer_phone}}
📍 Adresa: {{address}}

🛋️ PRODUKT:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{{product}}

📝 POPIS PROBLÉMU:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{{description}}

✅ CO JE POTŘEBA UDĚLAT:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. Potvrďte prosím dostupnost v systému
2. Den předem kontaktujte zákazníka pro potvrzení
3. Připravte si potřebné nástroje a materiál
4. Po návštěvě vyplňte protokol v systému

📋 Číslo zakázky: {{order_id}}

Děkujeme za Vaši skvělou práci!

S pozdravem,
Tým White Glove Service

─────────────────────────────────
Pro přístup do systému: www.wgs-service.cz'
        ],
        'order_completed' => [
            'name' => 'Zakázka dokončena',
            'subject' => '✅ Vaše zakázka byla úspěšně dokončena!',
            'template' => 'Dobrý den {{customer_name}},

děkujeme Vám za využití služeb White Glove Service! 🙏

✅ ZAKÁZKA DOKONČENA:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📋 Číslo zakázky: {{order_id}}
📅 Dokončeno: {{completed_at}}

🎉 VAŠE SPOKOJENOST JE PRO NÁS PRIORITA:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Doufáme, že jste s našimi službami spokojeni a že Váš nábytek opět slouží jak má!

💬 MÁTE DOTAZ NEBO PŘIPOMÍNKU?
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Neváhejte nás kontaktovat:
📧 Email: reklamace@wgs-service.cz
📞 Telefon: [váš telefon]

⭐ POMOZTE NÁM BÝT LEPŠÍ:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Budeme vděční za zpětnou vazbu k naší práci.
Vaše hodnocení nám pomáhá zlepšovat naše služby.

🛋️ PÉČE O VÁŠ NÁBYTEK:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Nezapomeňte na pravidelnou údržbu svého nábytku podle přiložených instrukcí.

Těšíme se na další spolupráci!

S úctou a vděčností,
Váš tým White Glove Service

─────────────────────────────────
White Glove Service | Premium nábytkový servis
www.wgs-service.cz | reklamace@wgs-service.cz'
        ],
        'order_reopened' => [
            'name' => 'Zakázka znovu otevřena',
            'subject' => '🔄 Zakázka #{{order_id}} byla znovu otevřena',
            'template' => 'Dobrý den,

informujeme Vás, že zakázka byla vrácena do aktivního stavu.

📋 INFORMACE O ZAKÁZCE:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
👤 Zákazník: {{customer_name}}
📋 Číslo zakázky: {{order_id}}

🔄 ZMĚNA STAVU:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
• Zakázka byla znovu otevřena
• Otevřel: {{reopened_by}}
• Datum: {{reopened_at}}
• Nový stav: NOVÁ
• Termín byl vymazán a je třeba domluvit nový

⚠️ CO JE POTŘEBA UDĚLAT:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. Zkontrolujte důvod znovuotevření
2. Kontaktujte zákazníka pro vysvětlení
3. Domluv nový termín návštěvy technika
4. Aktualizujte informace v systému

📞 DOPORUČENÉ KROKY:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Prosím o prioritní zpracování této zakázky a kontaktování zákazníka co nejdříve.

S pozdravem,
Systém White Glove Service

─────────────────────────────────
Pro přístup do systému: www.wgs-service.cz'
        ]
    ];

    if (!isset($_GET['potvrdit'])) {
        echo "<div class='info'><strong>NÁHLED ZMĚN</strong><br><br>";
        echo "Níže vidíte náhled nových šablon. Klikněte na tlačítko pro provedení změn.</div>";

        foreach ($sablony as $id => $data) {
            echo "<div class='template-box'>";
            echo "<div class='template-name'>📧 {$data['name']}</div>";
            echo "<div style='margin: 10px 0;'><strong>ID:</strong> <code>$id</code></div>";
            echo "<div style='margin: 10px 0;'><strong>Předmět:</strong> {$data['subject']}</div>";

            echo "<details style='margin: 10px 0;'>";
            echo "<summary style='cursor: pointer; font-weight: bold; color: #2D5016;'>📄 Zobrazit novou šablonu</summary>";
            echo "<div class='template-after'>" . htmlspecialchars($data['template']) . "</div>";
            echo "</details>";

            echo "</div>";
        }

        echo "<hr style='margin: 30px 0;'>";
        echo "<a href='?potvrdit=1' class='btn btn-danger' style='font-size: 16px;'>✅ POTVRDIT A ULOŽIT VŠECHNY ZMĚNY</a>";
        echo "<a href='/admin.php' class='btn' style='background: #6c757d;'>← Zrušit a vrátit se</a>";

    } else {
        echo "<div class='info'><strong>PROVÁDÍM ZMĚNY...</strong></div>";

        $pdo->beginTransaction();

        try {
            $updateCount = 0;

            foreach ($sablony as $id => $data) {
                $stmt = $pdo->prepare("
                    UPDATE wgs_notifications
                    SET name = :name,
                        subject = :subject,
                        template = :template,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':id' => $id,
                    ':name' => $data['name'],
                    ':subject' => $data['subject'],
                    ':template' => $data['template']
                ]);

                if ($stmt->rowCount() > 0) {
                    echo "<div class='success'>✅ Aktualizováno: <strong>{$data['name']}</strong> (ID: $id)</div>";
                    $updateCount++;
                } else {
                    echo "<div class='info'>ℹ️ Bez změny: <strong>{$data['name']}</strong> (ID: $id) - šablona možná neexistuje</div>";
                }
            }

            $pdo->commit();

            echo "<div class='success' style='margin: 30px 0; font-size: 1.1rem;'>";
            echo "<strong>🎉 HOTOVO!</strong><br><br>";
            echo "Úspěšně aktualizováno <strong>$updateCount</strong> emailových šablon.<br>";
            echo "Všechny nové emaily budou nyní používat vstřícnější a přátelštější texty.";
            echo "</div>";

            echo "<a href='/admin.php' class='btn' style='background: #28a745;'>← Zpět do Admin panelu</a>";
            echo "<a href='?refresh=1' class='btn' style='background: #17a2b8;'>🔄 Obnovit náhled</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'><strong>❌ CHYBA PŘI UKLÁDÁNÍ:</strong><br>";
            echo htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>❌ <strong>CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
