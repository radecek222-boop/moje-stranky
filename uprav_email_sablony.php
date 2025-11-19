<?php
/**
 * Úprava emailových šablon - VSTŘÍCNĚJŠÍ VERZE
 *
 * Vylepšení:
 * - Přátelštější tón
 * - Informace o parkování pro technika
 * - Kontakt na konkrétního technika
 * - Lepší struktura a čitelnost
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Úprava emailových šablon</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .template-preview { background: #f9f9f9; padding: 20px; margin: 20px 0;
                           border-left: 4px solid #2D5016; white-space: pre-wrap;
                           font-family: monospace; font-size: 0.9rem; }
        .btn-execute { background: #dc3545; color: white; padding: 15px 30px;
                      font-size: 16px; font-weight: bold; border: none;
                      cursor: pointer; border-radius: 5px; }
        .btn-execute:hover { background: #c82333; }
    </style>
</head>
<body>
<div class="container" style="max-width: 1400px; margin: 30px auto; padding: 20px;">

<h1>📧 Úprava emailových šablon na vstřícnější</h1>

<?php
try {
    $pdo = getDbConnection();

    // Nové šablony
    $sablony = [
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

👨‍🔧 VÁŠ TECHNIK:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Jméno: {{technician_name}}
📞 Telefon: {{technician_phone}}

Náš technik Vás bude kontaktovat den předem pro potvrzení termínu.

⏰ PŘÍJEZD TECHNIKA:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Náš technik se pokusí dorazit přesně v domluvený čas. Situaci však
může ovlivnit dopravní situace, proto Vás žádáme o ohleduplnost.

ℹ️ Při delším zpoždění než 30 minut budete informováni telefonicky
   nebo formou SMS přímo od technika.

✅ CO VÁS ČEKÁ:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
• Náš technik dorazí a provede odbornou opravu
• Navrhne řešení a postup práce
• Odpovídá na všechny Vaše dotazy

🅿️ PARKOVÁNÍ PRO VOZIDLO TECHNIKA:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⚠️ DŮLEŽITÉ UPOZORNĚNÍ:

Prosíme Vás o zajištění BEZPLATNÉHO a BEZPEČNÉHO parkování
pro osobní vozidlo našeho technika v blízkosti místa opravy.

❗ Pokud NENÍ možné parkování ze strany zákazníka zajistit,
   je nutné o tom NEPRODLENĚ informovat technika na uvedeném
   telefonním čísle {{technician_phone}}.

Toto opatření je nezbytné pro bezproblémový průběh servisní
návštěvy a ochranu našeho vozidla a nářadí.

📞 DOTAZY NEBO ZMĚNA TERMÍNU?
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Kontaktujte prosím přímo Vašeho technika:
👨‍🔧 {{technician_name}}: {{technician_phone}}

Nebo naši centrálu:
📧 Email: reklamace@wgs-service.cz

Děkujeme za pochopení a těšíme se na Vás!

S pozdravem,
Váš tým White Glove Service

─────────────────────────────────
White Glove Service | Premium nábytkový servis
www.wgs-service.cz | reklamace@wgs-service.cz'
        ],

        'appointment_reminder_customer' => [
            'name' => 'Připomenutí termínu zákazníkovi',
            'subject' => '⏰ Připomínka: Zítra přijede náš technik!',
            'template' => 'Dobrý den {{customer_name}},

jen Vám chceme připomenout, že **ZÍTRA** k Vám přijede náš technik! 😊

📅 ZÍTŘEJŠÍ TERMÍN:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🗓️ Datum: {{date}}
⏰ Čas: {{time}}
📍 Adresa: {{address}}
📋 Číslo zakázky: {{order_id}}

👨‍🔧 VÁŠ TECHNIK:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{{technician_name}}
📞 {{technician_phone}}

Náš technik se na Vás těší a dnes odpoledne Vás případně kontaktuje
pro finální potvrzení.

🅿️ NEZAPOMEŇTE: PARKOVÁNÍ
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Prosíme o zajištění bezplatného a bezpečného parkování pro vozidlo
technika. Pokud to není možné, informujte technika na tel. {{technician_phone}}.

⚠️ POTŘEBUJETE PŘELOŽIT TERMÍN?
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Kontaktujte prosím DNES technika {{technician_name}} na:
📞 {{technician_phone}}

Děkujeme a těšíme se na Vás!

S úctou,
Váš tým White Glove Service

─────────────────────────────────
White Glove Service | Premium nábytkový servis
www.wgs-service.cz'
        ],

        'new_complaint' => [
            'name' => 'Nová reklamace vytvořena',
            'subject' => '🆕 Nová reklamace #{{order_id}} - {{customer_name}}',
            'template' => 'Dobrý den,

máme pro vás informaci o nové reklamaci vytvořené v systému.

📋 INFORMACE O ZÁKAZNÍKOVI:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
👤 Zákazník: {{customer_name}}
📞 Telefon: {{customer_phone}}
📧 Email: {{customer_email}}
📍 Adresa: {{address}}

🛋️ PRODUKT A PROBLÉM:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Produkt: {{product}}

Popis problému:
{{description}}

✅ DALŠÍ KROKY:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. Přidělte zakázku vhodnému technikovi
2. Domluv s zákazníkem termín návštěvy
3. Aktualizujte stav v systému

📋 Číslo zakázky: #{{order_id}}
🕐 Vytvořeno: {{created_at}}

Přejeme úspěšné vyřízení!
Systém WGS'
        ],

        'appointment_assigned_technician' => [
            'name' => 'Přiřazení termínu technikovi',
            'subject' => '🔧 Nový servisní termín: {{date}} v {{time}}',
            'template' => 'Dobrý den {{technician_name}},

máme pro Vás nový servisní termín! Prosím o potvrzení dostupnosti.

📅 TERMÍN:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🗓️ Datum: {{date}}
⏰ Čas: {{time}}
📋 Zakázka: {{order_id}}

👤 ZÁKAZNÍK:
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

✅ CHECKLIST PRO VÁS:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
☐ Potvrdit dostupnost v systému
☐ Den předem kontaktovat zákazníka (potvrzení + parkování!)
☐ Připravit potřebné nástroje a materiál
☐ Po návštěvě vyplnit protokol

🅿️ DŮLEŽITÉ: Informujte zákazníka o nutnosti zajistit parkování!

Děkujeme za Vaši skvělou práci!
Tým WGS'
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
👨‍🔧 Technik: {{technician_name}}

🎉 VAŠE SPOKOJENOST JE PRO NÁS PRIORITA:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Doufáme, že jste s našimi službami spokojeni a že
Váš nábytek opět slouží jak má!

💬 MÁTE DOTAZ NEBO PŘIPOMÍNKU?
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Neváhejte nás kontaktovat:
📧 Email: reklamace@wgs-service.cz

⭐ ZPĚTNÁ VAZBA:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Budeme vděční za Vaše hodnocení naší práce.
Pomáhá nám to zlepšovat naše služby.

🛋️ PÉČE O VÁŠ NÁBYTEK:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Nezapomeňte na pravidelnou údržbu podle přiložených instrukcí.

Těšíme se na další spolupráci!

S úctou a vděčností,
Váš tým White Glove Service

─────────────────────────────────
White Glove Service | Premium nábytkový servis
www.wgs-service.cz'
        ],

        'order_reopened' => [
            'name' => 'Zakázka znovu otevřena',
            'subject' => '🔄 URGENT: Zakázka #{{order_id}} byla znovu otevřena',
            'template' => 'Dobrý den,

zakázka byla vrácena do aktivního stavu a vyžaduje vaši pozornost.

📋 INFORMACE:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
👤 Zákazník: {{customer_name}}
📋 Zakázka: {{order_id}}

🔄 ZMĚNA:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
• Stav změněn na: NOVÁ
• Otevřel: {{reopened_by}}
• Datum: {{reopened_at}}
• Termín byl vymazán

⚠️ PRIORITNÍ KROKY:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. Zjistit důvod znovuotevření
2. Kontaktovat zákazníka pro vysvětlení
3. Domluvit NOVÝ termín
4. Aktualizovat systém

Prosím o rychlé vyřízení.
Systém WGS'
        ]
    ];

    if (!isset($_GET['execute'])) {
        echo "<div style='background: #fff3cd; padding: 20px; margin: 20px 0; border-left: 4px solid #ffc107;'>";
        echo "<h2>⚠️ NÁHLED ZMĚN</h2>";
        echo "<p>Níže vidíte nové šablony. Klikněte na tlačítko pro provedení změn.</p>";
        echo "</div>";

        foreach ($sablony as $id => $data) {
            echo "<div style='background: white; padding: 20px; margin: 20px 0; border: 1px solid #ddd;'>";
            echo "<h3>📧 {$data['name']}</h3>";
            echo "<p><strong>ID:</strong> <code>$id</code> | <strong>Předmět:</strong> {$data['subject']}</p>";
            echo "<details><summary style='cursor: pointer; font-weight: bold; color: #2D5016;'>► Zobrazit šablonu</summary>";
            echo "<div class='template-preview'>" . htmlspecialchars($data['template']) . "</div>";
            echo "</details></div>";
        }

        echo "<hr style='margin: 40px 0;'>";
        echo "<form method='get'>";
        echo "<input type='hidden' name='execute' value='1'>";
        echo "<button type='submit' class='btn-execute'>✅ POTVRDIT A ULOŽIT VŠECHNY ZMĚNY</button>";
        echo " <a href='/admin.php' style='margin-left: 20px;'>← Zrušit</a>";
        echo "</form>";

    } else {
        echo "<div style='background: #d1ecf1; padding: 20px; margin: 20px 0;'>";
        echo "<h2>⏳ Provádím změny...</h2></div>";

        $pdo->beginTransaction();
        $count = 0;

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
                echo "<div style='background: #d4edda; padding: 10px; margin: 10px 0;'>";
                echo "✅ Aktualizováno: <strong>{$data['name']}</strong></div>";
                $count++;
            }
        }

        $pdo->commit();

        echo "<div style='background: #d4edda; padding: 30px; margin: 30px 0; font-size: 1.2rem;'>";
        echo "<strong>🎉 HOTOVO!</strong><br><br>";
        echo "Úspěšně aktualizováno <strong>$count</strong> šablon.<br>";
        echo "Všechny nové emaily budou vstřícnější a profesionálnější.";
        echo "</div>";

        echo "<a href='/admin.php' class='btn-execute' style='background: #28a745;'>← Zpět do Admin panelu</a>";
    }

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 20px; margin: 20px 0;'>";
    echo "<strong>❌ CHYBA:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>

<div style='margin-top: 50px; padding: 20px; background: #e8f5e9; border-left: 4px solid #2D5016;'>
    <h3>ℹ️ Důležité poznámky:</h3>
    <ul>
        <li><strong>Připomenutí termínu:</strong> Mělo by se odesílat automaticky den před návštěvou v 10:00 ráno</li>
        <li><strong>Nové proměnné:</strong> {{technician_name}} a {{technician_phone}} musí být vyplněny při odesílání</li>
        <li><strong>Parkování:</strong> Informace o parkování je nyní součástí všech šablon pro zákazníky</li>
    </ul>
</div>

</div>
</body>
</html>
