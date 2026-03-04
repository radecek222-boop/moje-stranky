<?php
/**
 * Testovací skript pro ověření CC/BCC příjemců v emailových šablonách
 *
 * Tento skript:
 * 1. Načte emailovou šablonu z databáze
 * 2. Zkontroluje nastavení to_recipients, cc_recipients, bcc_recipients
 * 3. Simuluje, komu by se email odeslal (TO, CC, BCC)
 * 4. Zobrazí výsledek
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit test.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Test CC/BCC příjemců</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; }
        h2 { color: #555; margin-top: 2rem; }
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
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #333; color: white; font-weight: 600; }
        tr:hover { background: #f5f5f5; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px;
                 font-size: 0.85rem; font-weight: 600; }
        .badge-to { background: #28a745; color: white; }
        .badge-cc { background: #ffc107; color: #000; }
        .badge-bcc { background: #6c757d; color: white; }
        .email-preview { background: #f8f9fa; border: 1px solid #dee2e6;
                        padding: 15px; border-radius: 5px; margin: 15px 0;
                        font-family: monospace; font-size: 0.9rem; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #000; }
        select { padding: 8px; border: 1px solid #ddd; border-radius: 5px;
                font-size: 1rem; margin-right: 10px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Test CC/BCC příjemců v emailových šablonách</h1>";

    // Výběr šablony
    $sablonaId = $_GET['sablona'] ?? null;

    // Načíst všechny šablony pro select
    $stmt = $pdo->query("
        SELECT id, name, recipient_type, active
        FROM wgs_notifications
        WHERE type = 'email'
        ORDER BY name ASC
    ");
    $sablony = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<form method='get' style='margin: 20px 0;'>";
    echo "<label for='sablona' style='font-weight: 600; margin-right: 10px;'>Vyberte šablonu:</label>";
    echo "<select name='sablona' id='sablona' onchange='this.form.submit()'>";
    echo "<option value=''>-- Vyberte šablonu --</option>";
    foreach ($sablony as $s) {
        $selected = ($sablonaId === $s['id']) ? 'selected' : '';
        $status = $s['active'] ? 'AKTIVNÍ' : 'VYPNUTO';
        echo "<option value='{$s['id']}' {$selected}>{$s['name']} ({$status})</option>";
    }
    echo "</select>";
    echo "</form>";

    if ($sablonaId) {
        // Načíst vybranou šablonu
        $stmt = $pdo->prepare("
            SELECT *
            FROM wgs_notifications
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $sablonaId]);
        $sablona = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sablona) {
            throw new Exception("Šablona s ID '{$sablonaId}' nebyla nalezena.");
        }

        echo "<div class='info'>";
        echo "<strong>Testujeme šablonu:</strong> {$sablona['name']}<br>";
        echo "<strong>ID:</strong> {$sablona['id']}<br>";
        echo "<strong>Trigger:</strong> {$sablona['trigger_event']}<br>";
        echo "<strong>Status:</strong> " . ($sablona['active'] ? '✅ AKTIVNÍ' : '❌ VYPNUTO');
        echo "</div>";

        // Dekódovat JSON pole
        $toRecipients = json_decode($sablona['to_recipients'] ?? '[]', true) ?: [];
        $ccRecipients = json_decode($sablona['cc_recipients'] ?? '[]', true) ?: [];
        $bccRecipients = json_decode($sablona['bcc_recipients'] ?? '[]', true) ?: [];
        $ccEmails = json_decode($sablona['cc_emails'] ?? '[]', true) ?: [];
        $bccEmails = json_decode($sablona['bcc_emails'] ?? '[]', true) ?: [];
        $recipients = json_decode($sablona['recipients'] ?? 'null', true);

        echo "<h2>1. Nastavení příjemců v databázi</h2>";

        // Tabulka s nastavením
        echo "<table>";
        echo "<tr><th>Sloupec DB</th><th>Hodnota</th><th>Typ</th></tr>";

        echo "<tr>";
        echo "<td><code>to_recipients</code></td>";
        echo "<td>" . (empty($toRecipients) ? '<em>prázdné</em>' : implode(', ', array_map(function($r) {
            return "<span class='badge badge-to'>{$r}</span>";
        }, $toRecipients))) . "</td>";
        echo "<td>Role-based</td>";
        echo "</tr>";

        echo "<tr>";
        echo "<td><code>cc_recipients</code></td>";
        echo "<td>" . (empty($ccRecipients) ? '<em>prázdné</em>' : implode(', ', array_map(function($r) {
            return "<span class='badge badge-cc'>{$r}</span>";
        }, $ccRecipients))) . "</td>";
        echo "<td>Role-based</td>";
        echo "</tr>";

        echo "<tr>";
        echo "<td><code>bcc_recipients</code></td>";
        echo "<td>" . (empty($bccRecipients) ? '<em>prázdné</em>' : implode(', ', array_map(function($r) {
            return "<span class='badge badge-bcc'>{$r}</span>";
        }, $bccRecipients))) . "</td>";
        echo "<td>Role-based</td>";
        echo "</tr>";

        echo "<tr>";
        echo "<td><code>cc_emails</code></td>";
        echo "<td>" . (empty($ccEmails) ? '<em>prázdné</em>' : implode(', ', array_map(function($e) {
            return "<span class='badge badge-cc'>{$e}</span>";
        }, $ccEmails))) . "</td>";
        echo "<td>Explicitní emaily</td>";
        echo "</tr>";

        echo "<tr>";
        echo "<td><code>bcc_emails</code></td>";
        echo "<td>" . (empty($bccEmails) ? '<em>prázdné</em>' : implode(', ', array_map(function($e) {
            return "<span class='badge badge-bcc'>{$e}</span>";
        }, $bccEmails))) . "</td>";
        echo "<td>Explicitní emaily</td>";
        echo "</tr>";

        echo "</table>";

        // Zobrazit recipients JSON (nový formát)
        if ($recipients) {
            echo "<h2>2. Recipients JSON (admin UI formát)</h2>";
            echo "<div class='email-preview'>";
            echo "<pre>" . json_encode($recipients, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            echo "</div>";
        }

        // Simulace odeslání
        echo "<h2>3. Simulace odeslání emailu</h2>";

        // Příklad dat pro simulaci
        $simulovanaData = [
            'customer_name' => 'Jan Novák',
            'customer_email' => 'jan.novak@example.cz',
            'seller_email' => 'prodejce@example.cz',
            'seller_name' => 'Pavel Prodejce',
            'technician_email' => 'technik@example.cz',
            'technician_name' => 'Martin Technik',
            'order_id' => '2026-0042',
            'appointment_date' => '25.01.2026',
            'appointment_time' => '14:00'
        ];

        // Funkce pro resolvení role na email (kopie z notification_sender.php)
        $resolveRoleToEmail = function($role, $data) use ($pdo) {
            switch ($role) {
                case 'customer':
                    return $data['customer_email'] ?? null;
                case 'admin':
                    $stmt = $pdo->prepare("SELECT config_value FROM wgs_system_config WHERE config_key = 'admin_email' LIMIT 1");
                    $stmt->execute();
                    return $stmt->fetchColumn() ?: null;
                case 'technician':
                    return $data['technician_email'] ?? null;
                case 'seller':
                    return $data['seller_email'] ?? null;
                default:
                    return null;
            }
        };

        // TO příjemci
        $finalni_TO = [];
        foreach ($toRecipients as $role) {
            $email = $resolveRoleToEmail($role, $simulovanaData);
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $finalni_TO[] = $email;
            }
        }

        // CC příjemci (role + explicitní)
        $finalni_CC = [];
        foreach ($ccRecipients as $role) {
            $email = $resolveRoleToEmail($role, $simulovanaData);
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $finalni_CC[] = $email;
            }
        }
        $finalni_CC = array_merge($finalni_CC, $ccEmails);

        // BCC příjemci (role + explicitní)
        $finalni_BCC = [];
        foreach ($bccRecipients as $role) {
            $email = $resolveRoleToEmail($role, $simulovanaData);
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $finalni_BCC[] = $email;
            }
        }
        $finalni_BCC = array_merge($finalni_BCC, $bccEmails);

        // Odstranit duplicity
        $finalni_TO = array_unique($finalni_TO);
        $finalni_CC = array_unique(array_filter($finalni_CC, function($e) {
            return filter_var($e, FILTER_VALIDATE_EMAIL);
        }));
        $finalni_BCC = array_unique(array_filter($finalni_BCC, function($e) {
            return filter_var($e, FILTER_VALIDATE_EMAIL);
        }));

        echo "<div class='info'>";
        echo "<strong>Simulovaná data pro test:</strong><br>";
        echo "Zákazník: {$simulovanaData['customer_name']} ({$simulovanaData['customer_email']})<br>";
        echo "Prodejce: {$simulovanaData['seller_name']} ({$simulovanaData['seller_email']})<br>";
        echo "Technik: {$simulovanaData['technician_name']} ({$simulovanaData['technician_email']})";
        echo "</div>";

        echo "<h3>Výsledek - komu by se email odeslal:</h3>";

        echo "<div class='email-preview'>";
        echo "<strong>TO (Příjemci):</strong><br>";
        if (empty($finalni_TO)) {
            echo "<div class='warning'>⚠️ ŽÁDNÍ TO PŘÍJEMCI! Email by se NEODESLAL.</div>";
        } else {
            foreach ($finalni_TO as $email) {
                echo "<span class='badge badge-to'>{$email}</span> ";
            }
        }
        echo "<br><br>";

        echo "<strong>CC (Kopie):</strong><br>";
        if (empty($finalni_CC)) {
            echo "<em>žádné</em>";
        } else {
            foreach ($finalni_CC as $email) {
                echo "<span class='badge badge-cc'>{$email}</span> ";
            }
        }
        echo "<br><br>";

        echo "<strong>BCC (Skrytá kopie):</strong><br>";
        if (empty($finalni_BCC)) {
            echo "<em>žádné</em>";
        } else {
            foreach ($finalni_BCC as $email) {
                echo "<span class='badge badge-bcc'>{$email}</span> ";
            }
        }
        echo "</div>";

        // Závěrečné vyhodnocení
        if (!empty($finalni_TO)) {
            echo "<div class='success'>";
            echo "<strong>✅ TEST ÚSPĚŠNÝ</strong><br>";
            echo "Email by se odeslal " . count($finalni_TO) . " příjemcům (TO)";
            if (!empty($finalni_CC)) {
                echo " + " . count($finalni_CC) . " kopiím (CC)";
            }
            if (!empty($finalni_BCC)) {
                echo " + " . count($finalni_BCC) . " skrytým kopiím (BCC)";
            }
            echo "</div>";
        } else {
            echo "<div class='error'>";
            echo "<strong>❌ TEST SELHAL</strong><br>";
            echo "Email by se NEODESLAL, protože nejsou nastaveni TO příjemci.";
            echo "</div>";
        }

        // Návod na opravu
        if (empty($ccRecipients) && empty($ccEmails) && empty($bccRecipients) && empty($bccEmails)) {
            echo "<div class='warning'>";
            echo "<strong>⚠️ UPOZORNĚNÍ:</strong> Nejsou nastaveni žádní CC/BCC příjemci.<br>";
            echo "Pokud chcete posílat kopie, nastavte je v admin UI:<br>";
            echo "1. Přejděte do <a href='/admin.php?tab=notifications&section=templates'>Email šablony</a><br>";
            echo "2. Klikněte 'UPRAVIT SABLONU' u šablony '{$sablona['name']}'<br>";
            echo "3. Klikněte 'Nastavit příjemce'<br>";
            echo "4. Zaškrtněte role a nastavte typ (To/Cc/Bcc)";
            echo "</div>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='/admin.php' class='btn'>Zpět do admin</a>";
echo "</div></body></html>";
?>
