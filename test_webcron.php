<?php
/**
 * Test webcron skriptu s podrobným výstupem
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může testovat webcron.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Test Webcron - Připomenutí</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2D5016;
            border-bottom: 3px solid #2D5016;
            padding-bottom: 10px;
        }
        .section {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #2D5016;
            border-radius: 5px;
        }
        .success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #2D5016;
            color: white;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2D5016;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
        }
        .btn:hover {
            background: #1a300d;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🧪 Test Webcron - Připomenutí návštěv</h1>";

try {
    require_once __DIR__ . '/includes/EmailQueue.php';
    $pdo = getDbConnection();

    // Vypočítat datum zítřka
    $dnes = date('d.m.Y');
    $zitra = date('d.m.Y', strtotime('+1 day'));

    echo "<div class='section'>";
    echo "<h2>📅 Kontrola dat</h2>";
    echo "<p><strong>Dnešní datum:</strong> {$dnes}</p>";
    echo "<p><strong>Zítřejší datum (hledané):</strong> {$zitra}</p>";
    echo "<p><strong>Formát v databázi:</strong> DD.MM.YYYY (český formát)</p>";
    echo "</div>";

    // Kontrola šablony
    echo "<div class='section'>";
    echo "<h2>📧 Kontrola emailové šablony</h2>";

    $stmtTemplate = $pdo->prepare("
        SELECT id, name, subject, template, active
        FROM wgs_notifications
        WHERE id = 'appointment_reminder_customer'
        LIMIT 1
    ");
    $stmtTemplate->execute();
    $template = $stmtTemplate->fetch(PDO::FETCH_ASSOC);

    if ($template) {
        echo "<div class='success'>✓ Šablona nalezena</div>";
        echo "<p><strong>ID:</strong> " . htmlspecialchars($template['id']) . "</p>";
        echo "<p><strong>Název:</strong> " . htmlspecialchars($template['name']) . "</p>";
        echo "<p><strong>Předmět:</strong> " . htmlspecialchars($template['subject']) . "</p>";
        echo "<p><strong>Aktivní:</strong> " . ($template['active'] ? 'ANO' : 'NE') . "</p>";
        echo "<details><summary>Zobrazit šablonu</summary>";
        echo "<pre>" . htmlspecialchars($template['template']) . "</pre>";
        echo "</details>";
    } else {
        echo "<div class='error'>✗ Šablona 'appointment_reminder_customer' nebyla nalezena!</div>";
        echo "<p>Spusťte: <code>instaluj_email_sablony.php</code></p>";
    }
    echo "</div>";

    // Hledání návštěv
    echo "<div class='section'>";
    echo "<h2>🔍 Hledání návštěv na zítřek</h2>";

    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.reklamace_id,
            r.cislo,
            r.jmeno,
            r.email,
            r.telefon,
            r.adresa,
            r.termin,
            r.cas_navstevy,
            r.popis_problemu,
            r.model,
            r.stav,
            r.technik,
            u.phone as technik_telefon
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON u.name = r.technik AND u.role = 'technik'
        WHERE r.stav = 'open'
          AND r.termin = :zitra
          AND r.email IS NOT NULL
          AND r.email != ''
    ");
    $stmt->execute(['zitra' => $zitra]);
    $navstevy = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pocet = count($navstevy);

    echo "<p><strong>SQL WHERE podmínky:</strong></p>";
    echo "<ul>";
    echo "<li><code>stav = 'open'</code> (DOMLUVENÁ návštěva)</li>";
    echo "<li><code>termin = '{$zitra}'</code></li>";
    echo "<li><code>email IS NOT NULL AND email != ''</code></li>";
    echo "</ul>";

    if ($pocet > 0) {
        echo "<div class='success'>✓ Nalezeno <strong>{$pocet}</strong> návštěv pro odeslání připomenutí</div>";

        echo "<table>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th>Zákazník</th>";
        echo "<th>Email</th>";
        echo "<th>Termin</th>";
        echo "<th>Čas</th>";
        echo "<th>Technik</th>";
        echo "<th>Telefon technika</th>";
        echo "<th>Model</th>";
        echo "</tr>";

        foreach ($navstevy as $n) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($n['cislo']) . "</td>";
            echo "<td>" . htmlspecialchars($n['jmeno']) . "</td>";
            echo "<td>" . htmlspecialchars($n['email']) . "</td>";
            echo "<td>" . htmlspecialchars($n['termin']) . "</td>";
            echo "<td>" . htmlspecialchars($n['cas_navstevy'] ?? 'neurčen') . "</td>";
            echo "<td>" . htmlspecialchars($n['technik'] ?? 'nepřiřazen') . "</td>";
            echo "<td>" . htmlspecialchars($n['technik_telefon'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($n['model'] ?? 'neurčeno') . "</td>";
            echo "</tr>";
        }

        echo "</table>";

        // Ukázka vygenerovaného emailu pro první návštěvu
        if ($template && $pocet > 0) {
            $prvni = $navstevy[0];

            $nahradit = [
                '{{customer_name}}' => $prvni['jmeno'] ?? 'Vážený zákazníku',
                '{{date}}' => $prvni['termin'] ?? $zitra,
                '{{time}}' => $prvni['cas_navstevy'] ?? 'neurčen',
                '{{address}}' => $prvni['adresa'] ?? '',
                '{{order_id}}' => $prvni['cislo'] ?? $prvni['reklamace_id'],
                '{{product}}' => $prvni['model'] ?? 'nábytek',
                '{{description}}' => $prvni['popis_problemu'] ?? '',
                '{{technician_name}}' => $prvni['technik'] ?? 'WGS technik',
                '{{technician_phone}}' => $prvni['technik_telefon'] ?? '+420 725 965 826'
            ];

            $predmet = str_replace(array_keys($nahradit), array_values($nahradit), $template['subject']);
            $telo = str_replace(array_keys($nahradit), array_values($nahradit), $template['template']);

            echo "<div class='info'>";
            echo "<h3>📨 Ukázka emailu pro zákazníka: " . htmlspecialchars($prvni['jmeno']) . "</h3>";
            echo "<p><strong>Předmět:</strong> " . htmlspecialchars($predmet) . "</p>";
            echo "<p><strong>Tělo emailu:</strong></p>";
            echo "<pre>" . htmlspecialchars($telo) . "</pre>";
            echo "</div>";
        }

    } else {
        echo "<div class='warning'>⚠️ Nenalezeny žádné návštěvy na zítřek ({$zitra})</div>";

        // Diagnostika - kolik je celkem OPEN návštěv
        $stmtDebug = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_reklamace WHERE stav = 'open'");
        $debug = $stmtDebug->fetch(PDO::FETCH_ASSOC);
        echo "<p><strong>Celkem návštěv se stavem 'open':</strong> {$debug['pocet']}</p>";

        // Ukázka termínů v databázi
        $stmtTerminy = $pdo->query("
            SELECT DISTINCT termin, COUNT(*) as pocet
            FROM wgs_reklamace
            WHERE stav = 'open' AND termin IS NOT NULL
            GROUP BY termin
            ORDER BY termin
            LIMIT 10
        ");
        $terminy = $stmtTerminy->fetchAll(PDO::FETCH_ASSOC);

        if (count($terminy) > 0) {
            echo "<p><strong>Příklady termínů v databázi:</strong></p>";
            echo "<ul>";
            foreach ($terminy as $t) {
                echo "<li><code>{$t['termin']}</code> ({$t['pocet']}× návštěva)</li>";
            }
            echo "</ul>";
        }
    }

    echo "</div>";

    // Kontrola email fronty
    echo "<div class='section'>";
    echo "<h2>📬 Stav emailové fronty</h2>";

    $stmtQueue = $pdo->query("
        SELECT
            status,
            COUNT(*) as pocet
        FROM wgs_email_queue
        GROUP BY status
    ");
    $queueStats = $stmtQueue->fetchAll(PDO::FETCH_ASSOC);

    if (count($queueStats) > 0) {
        echo "<table>";
        echo "<tr><th>Status</th><th>Počet</th></tr>";
        foreach ($queueStats as $stat) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($stat['status']) . "</td>";
            echo "<td>" . htmlspecialchars($stat['pocet']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Fronta je prázdná.</p>";
    }

    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "<br><br><strong>Stack trace:</strong><br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "<div style='margin-top: 30px;'>";
echo "<a href='zobraz_cron_log.php' class='btn'>📋 Zobrazit CRON log</a>";
echo "<a href='admin.php' class='btn'>← Zpět do admin</a>";
echo "</div>";

echo "</div></body></html>";
?>
