<?php
/**
 * Migrace: Přidání šablony protocol_sent do wgs_notifications
 *
 * Tato šablona se použije při odeslání servisního protokolu zákazníkovi.
 * Obsahuje placeholder {{video_section}} pro automatické vložení odkazu na videa.
 *
 * Můžete spustit vícekrát - existující šablona se aktualizuje.
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
    <title>Migrace: Šablona protocol_sent</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; margin-bottom: 20px; }
        h2 { color: #333; margin: 20px 0 10px 0; font-size: 1.1rem; }
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
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 14px; }
        .btn:hover { background: #555; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px;
              overflow-x: auto; font-size: 12px; border: 1px solid #ddd; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #333; color: #fff; }
        .template-preview { background: #fff; border: 2px solid #ddd;
                           padding: 20px; margin: 15px 0; border-radius: 5px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Šablona protocol_sent</h1>";

    // Definice šablony
    $sablonaData = [
        'name' => 'Servisní protokol odeslán',
        'trigger_event' => 'protocol_sent',
        'recipient_type' => 'customer',
        'type' => 'email',
        'subject' => '{{customer_name}} - Reklamace č. {{order_id}} - Servisní protokol WGS',
        'template' => '<p>Dobrý den {{customer_name}},</p>

<p>zasíláme Vám kompletní servisní report k reklamaci č. <strong>{{order_id}}</strong>.</p>

<p><strong>V příloze naleznete:</strong></p>
<ul>
    <li>Servisní protokol s fotodokumentací (PDF)</li>
</ul>

{{video_section}}

<p>V případě dotazů nás prosím kontaktujte.</p>

<p style="margin-top: 30px;">
    S pozdravem,<br>
    <strong>White Glove Service</strong><br>
    <a href="mailto:{{company_email}}" style="color: #333;">{{company_email}}</a><br>
    {{company_phone}}
</p>',
        'to_recipients' => '["customer"]',
        'cc_recipients' => '[]',
        'bcc_recipients' => '[]',
        'cc_emails' => '[]',
        'bcc_emails' => '[]',
        'active' => 1
    ];

    // Kontrola zda šablona existuje
    $stmt = $pdo->prepare("SELECT id FROM wgs_notifications WHERE trigger_event = 'protocol_sent' AND type = 'email' LIMIT 1");
    $stmt->execute();
    $existujiciId = $stmt->fetchColumn();

    echo "<h2>Šablona k vytvoření/aktualizaci</h2>";
    echo "<table>
        <tr><th>Pole</th><th>Hodnota</th></tr>
        <tr><td>Název</td><td>{$sablonaData['name']}</td></tr>
        <tr><td>Trigger event</td><td><code>{$sablonaData['trigger_event']}</code></td></tr>
        <tr><td>Typ příjemce</td><td>{$sablonaData['recipient_type']}</td></tr>
        <tr><td>Předmět</td><td>{$sablonaData['subject']}</td></tr>
        <tr><td>TO příjemci</td><td><code>{$sablonaData['to_recipients']}</code></td></tr>
        <tr><td>CC příjemci</td><td><code>{$sablonaData['cc_recipients']}</code></td></tr>
        <tr><td>BCC příjemci</td><td><code>{$sablonaData['bcc_recipients']}</code></td></tr>
    </table>";

    echo "<h2>Náhled šablony</h2>";
    echo "<div class='template-preview'>";
    echo str_replace(
        ['{{customer_name}}', '{{order_id}}', '{{company_email}}', '{{company_phone}}', '{{video_section}}'],
        ['Jan Novák', 'RK-2025-0042', 'reklamace@wgs-service.cz', '+420 725 965 826', '<p style="background:#f5f5f5;padding:15px;border-radius:5px;"><em>[Zde se automaticky vloží sekce s videodokumentací, pokud existují videa]</em></p>'],
        $sablonaData['template']
    );
    echo "</div>";

    echo "<div class='info'>";
    echo "<strong>Dostupné proměnné:</strong><br>";
    echo "<code>{{customer_name}}</code> - Jméno zákazníka<br>";
    echo "<code>{{order_id}}</code> - Číslo reklamace<br>";
    echo "<code>{{technician_name}}</code> - Jméno technika<br>";
    echo "<code>{{address}}</code> - Adresa zákazníka<br>";
    echo "<code>{{product}}</code> - Model/produkt<br>";
    echo "<code>{{company_email}}</code> - Email firmy (reklamace@wgs-service.cz)<br>";
    echo "<code>{{company_phone}}</code> - Telefon firmy<br>";
    echo "<code>{{video_section}}</code> - Automatická sekce s videy (pokud existují)<br>";
    echo "</div>";

    if ($existujiciId) {
        echo "<div class='warning'>Šablona již existuje (ID: {$existujiciId}). Bude aktualizována.</div>";
    } else {
        echo "<div class='info'>Šablona neexistuje. Bude vytvořena nová.</div>";
    }

    // Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

        if ($existujiciId) {
            // Aktualizace existující šablony
            $stmt = $pdo->prepare("
                UPDATE wgs_notifications SET
                    name = :name,
                    recipient_type = :recipient_type,
                    subject = :subject,
                    template = :template,
                    to_recipients = :to_recipients,
                    cc_recipients = :cc_recipients,
                    bcc_recipients = :bcc_recipients,
                    cc_emails = :cc_emails,
                    bcc_emails = :bcc_emails,
                    active = :active,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                'name' => $sablonaData['name'],
                'recipient_type' => $sablonaData['recipient_type'],
                'subject' => $sablonaData['subject'],
                'template' => $sablonaData['template'],
                'to_recipients' => $sablonaData['to_recipients'],
                'cc_recipients' => $sablonaData['cc_recipients'],
                'bcc_recipients' => $sablonaData['bcc_recipients'],
                'cc_emails' => $sablonaData['cc_emails'],
                'bcc_emails' => $sablonaData['bcc_emails'],
                'active' => $sablonaData['active'],
                'id' => $existujiciId
            ]);

            echo "<div class='success'>";
            echo "<strong>ŠABLONA AKTUALIZOVÁNA</strong><br>";
            echo "ID: {$existujiciId}";
            echo "</div>";

        } else {
            // Vložení nové šablony
            $stmt = $pdo->prepare("
                INSERT INTO wgs_notifications (
                    name, trigger_event, recipient_type, type, subject, template,
                    to_recipients, cc_recipients, bcc_recipients, cc_emails, bcc_emails,
                    active, created_at, updated_at
                ) VALUES (
                    :name, :trigger_event, :recipient_type, :type, :subject, :template,
                    :to_recipients, :cc_recipients, :bcc_recipients, :cc_emails, :bcc_emails,
                    :active, NOW(), NOW()
                )
            ");
            $stmt->execute([
                'name' => $sablonaData['name'],
                'trigger_event' => $sablonaData['trigger_event'],
                'recipient_type' => $sablonaData['recipient_type'],
                'type' => $sablonaData['type'],
                'subject' => $sablonaData['subject'],
                'template' => $sablonaData['template'],
                'to_recipients' => $sablonaData['to_recipients'],
                'cc_recipients' => $sablonaData['cc_recipients'],
                'bcc_recipients' => $sablonaData['bcc_recipients'],
                'cc_emails' => $sablonaData['cc_emails'],
                'bcc_emails' => $sablonaData['bcc_emails'],
                'active' => $sablonaData['active']
            ]);

            $noveId = $pdo->lastInsertId();

            echo "<div class='success'>";
            echo "<strong>ŠABLONA VYTVOŘENA</strong><br>";
            echo "ID: {$noveId}";
            echo "</div>";
        }

        echo "<a href='admin.php?tab=notifications&section=templates' class='btn'>Zobrazit v Admin Panelu</a>";

    } else {
        echo "<br><a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='admin.php' class='btn' style='background:#666;'>Zpět do Admin</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
