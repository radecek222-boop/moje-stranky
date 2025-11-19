<?php
/**
 * Instalace a aktualizace emailových šablon
 *
 * Tento skript:
 * 1. Vytvoří tabulku wgs_notifications (pokud neexistuje)
 * 2. Vloží nebo aktualizuje nové vstřícnější šablony
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
    <title>Instalace emailových šablon</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .log { background: #1e1e1e; color: #00ff00; font-family: 'Courier New', monospace;
               padding: 20px; margin: 20px 0; border-radius: 5px; max-height: 500px;
               overflow-y: auto; white-space: pre-wrap; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        .btn { background: #2D5016; color: white; padding: 15px 30px;
               font-size: 16px; font-weight: bold; border: none;
               cursor: pointer; border-radius: 5px; text-decoration: none;
               display: inline-block; margin: 10px 5px; }
        .btn:hover { background: #1a300d; }
    </style>
</head>
<body>
<div class="container" style="max-width: 1400px; margin: 30px auto; padding: 20px;">

<h1>📧 Instalace a aktualizace emailových šablon</h1>

<div class="log">
<?php

function log_message($message, $type = 'info') {
    $colors = [
        'success' => '#00ff00',
        'error' => '#ff5555',
        'warning' => '#ffaa00',
        'info' => '#00aaff'
    ];
    $color = $colors[$type] ?? '#00ff00';
    echo "<span style='color: $color;'>[" . date('H:i:s') . "] $message</span>\n";
    flush();
}

try {
    $pdo = getDbConnection();

    log_message("=== START: Instalace emailových šablon ===");

    // Krok 1: Zkontrolovat a vytvořit tabulku
    log_message("KROK 1: Kontrola existence tabulky wgs_notifications...");

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_notifications'");
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        log_message("Tabulka neexistuje - vytvářím...", 'warning');

        $pdo->exec("
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
        ");

        log_message("✓ Tabulka wgs_notifications vytvořena", 'success');
    } else {
        log_message("✓ Tabulka wgs_notifications existuje", 'success');
    }

    // Krok 2: Definovat nové šablony
    log_message("\nKROK 2: Příprava nových šablon...");

    $sablony = [
        'appointment_confirmed' => [
            'name' => 'Potvrzení termínu návštěvy',
            'description' => 'Email odesílaný zákazníkovi po potvrzení termínu návštěvy technika',
            'trigger_event' => 'appointment_confirmed',
            'recipient_type' => 'customer',
            'type' => 'email',
            'subject' => '✅ Potvrzení termínu návštěvy - White Glove Service',
            'template' => 'Dobrý den {{customer_name}},

✅ POTVRZENÍ TERMÍNU
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
s radostí Vám potvrzujeme termín návštěvy našeho technika:

🗓️ Datum: {{date}}
⏰ Čas: {{time}}
📍 Adresa: {{address}}
📋 Číslo zakázky: {{order_id}}

👨‍🔧 VÁŠ TECHNIK:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Jméno: {{technician_name}}
📞 Telefon: {{technician_phone}}

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

S úctou,
Váš tým White Glove Service

─────────────────────────────────
White Glove Service | Premium nábytkový servis
📞 +420 725 965 826
📧 reklamace@wgs-service.cz
🌐 www.wgs-service.cz',
            'variables' => '["{{customer_name}}", "{{date}}", "{{time}}", "{{address}}", "{{order_id}}", "{{technician_name}}", "{{technician_phone}}"]'
        ],

        'appointment_reminder_customer' => [
            'name' => 'Připomenutí termínu zákazníkovi',
            'description' => 'Email připomenutí den před návštěvou technika',
            'trigger_event' => 'appointment_reminder',
            'recipient_type' => 'customer',
            'type' => 'email',
            'subject' => '📅 ZÍTRA JE VÁŠ TERMÍN - White Glove Service',
            'template' => 'Dobrý den {{customer_name}},

📅 ZÍTRA JE VÁŠ TERMÍN!
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Připomínáme termín návštěvy našeho technika:

🗓️ Datum: {{date}} ({{day}})
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
www.wgs-service.cz',
            'variables' => '["{{customer_name}}", "{{date}}", "{{day}}", "{{time}}", "{{address}}", "{{order_id}}", "{{technician_name}}", "{{technician_phone}}"]'
        ],

        'new_complaint' => [
            'name' => 'Nová reklamace vytvořena',
            'description' => 'Notifikace pro admin při vytvoření nové reklamace',
            'trigger_event' => 'order_created',
            'recipient_type' => 'admin',
            'type' => 'email',
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
Popis: {{description}}

📅 VYTVOŘENO: {{created_at}}

🔗 Přihlaste se do admin panelu pro další akce:
https://www.wgs-service.cz/admin.php

S pozdravem,
WGS Systém',
            'variables' => '["{{order_id}}", "{{customer_name}}", "{{customer_phone}}", "{{customer_email}}", "{{address}}", "{{product}}", "{{description}}", "{{created_at}}"]'
        ]
    ];

    log_message("Připraveno " . count($sablony) . " šablon", 'success');

    // Krok 3: Vložit nebo aktualizovat šablony
    log_message("\nKROK 3: Ukládání šablon do databáze...");

    $pdo->beginTransaction();
    $inserted = 0;
    $updated = 0;

    foreach ($sablony as $id => $data) {
        // Zkontrolovat, jestli šablona existuje
        $stmt = $pdo->prepare("SELECT id FROM wgs_notifications WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $exists = $stmt->fetch();

        if ($exists) {
            // UPDATE
            $stmt = $pdo->prepare("
                UPDATE wgs_notifications
                SET name = :name,
                    description = :description,
                    subject = :subject,
                    template = :template,
                    variables = :variables,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                ':id' => $id,
                ':name' => $data['name'],
                ':description' => $data['description'],
                ':subject' => $data['subject'],
                ':template' => $data['template'],
                ':variables' => $data['variables']
            ]);

            $updated++;
            log_message("  ↻ Aktualizováno: {$data['name']}", 'warning');

        } else {
            // INSERT
            $stmt = $pdo->prepare("
                INSERT INTO wgs_notifications (
                    id, name, description, trigger_event, recipient_type, type,
                    subject, template, variables, active
                ) VALUES (
                    :id, :name, :description, :trigger_event, :recipient_type, :type,
                    :subject, :template, :variables, 1
                )
            ");

            $stmt->execute([
                ':id' => $id,
                ':name' => $data['name'],
                ':description' => $data['description'],
                ':trigger_event' => $data['trigger_event'],
                ':recipient_type' => $data['recipient_type'],
                ':type' => $data['type'],
                ':subject' => $data['subject'],
                ':template' => $data['template'],
                ':variables' => $data['variables']
            ]);

            $inserted++;
            log_message("  + Vytvořeno: {$data['name']}", 'success');
        }
    }

    $pdo->commit();

    log_message("\n✓ HOTOVO!", 'success');
    log_message("  • Nově vytvořeno: $inserted", 'success');
    log_message("  • Aktualizováno: $updated", 'warning');
    log_message("  • Celkem: " . ($inserted + $updated), 'info');

    // Krok 4: Ověření
    log_message("\nKROK 4: Ověření...");
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_notifications");
    $total = $stmt->fetch()['count'];
    log_message("V databázi je celkem $total šablon", 'info');

    log_message("\n=== KONEC ===");
    log_message("\nMůžete nyní přejít do Admin panelu -> Email & SMS a zkontrolovat šablony.", 'success');

} catch (Exception $e) {
    log_message("\n✗ CHYBA: " . $e->getMessage(), 'error');
    log_message("Stack trace: " . $e->getTraceAsString(), 'error');
}

?>
</div>

<div style="text-align: center; margin: 30px 0;">
    <a href="/admin.php?section=email_sms" class="btn">📧 Otevřít Email & SMS kartu</a>
    <a href="/admin.php" class="btn" style="background: #6c757d;">← Zpět do Admin panelu</a>
</div>

</div>
</body>
</html>
