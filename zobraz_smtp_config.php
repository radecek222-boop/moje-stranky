<?php
/**
 * Diagnostika SMTP konfigurace a SPF problému
 */
require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika SMTP a SPF</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016; padding-bottom: 10px; }
        h2 { color: #2D5016; margin-top: 30px; }
        .section { background: #f8f9fa; padding: 15px; border-radius: 5px;
                   margin: 15px 0; border-left: 4px solid #2D5016; }
        .success { background: #d4edda; border-left-color: #28a745; color: #155724; }
        .error { background: #f8d7da; border-left-color: #dc3545; color: #721c24; }
        .warning { background: #fff3cd; border-left-color: #ffc107; color: #856404; }
        .info { background: #d1ecf1; border-left-color: #17a2b8; color: #0c5460; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #2D5016; color: white; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; }
        .btn { display: inline-block; padding: 10px 20px; background: #2D5016;
               color: white; text-decoration: none; border-radius: 5px;
               margin: 10px 5px 10px 0; cursor: pointer; border: none; }
        .btn:hover { background: #1a300d; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #a02a2a; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 Diagnostika SMTP a SPF Problému</h1>";

    // 1. Aktuální SMTP nastavení
    echo "<h2>1️⃣ Aktuální SMTP nastavení</h2>";
    $stmt = $pdo->query("SELECT * FROM wgs_smtp_settings WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $smtp = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($smtp) {
        echo "<div class='section'>";
        echo "<table>";
        echo "<tr><th>Parametr</th><th>Hodnota</th></tr>";
        echo "<tr><td>SMTP Host</td><td><code>{$smtp['smtp_host']}</code></td></tr>";
        echo "<tr><td>SMTP Port</td><td><code>{$smtp['smtp_port']}</code></td></tr>";
        echo "<tr><td>SMTP Username</td><td><code>{$smtp['smtp_username']}</code></td></tr>";
        echo "<tr><td>SMTP Password</td><td><code>" . (empty($smtp['smtp_password']) ? '(prázdné)' : '***') . "</code></td></tr>";
        echo "<tr><td>SMTP Encryption</td><td><code>{$smtp['smtp_encryption']}</code></td></tr>";
        echo "<tr><td><strong>FROM Email</strong></td><td><code><strong>{$smtp['smtp_from_email']}</strong></code></td></tr>";
        echo "<tr><td>FROM Name</td><td><code>{$smtp['smtp_from_name']}</code></td></tr>";
        echo "</table>";
        echo "</div>";
    }

    // 2. DNS kontrola pomocí PHP
    echo "<h2>2️⃣ DNS Záznamy (kontrola přes PHP)</h2>";

    echo "<div class='section'>";
    echo "<h3>SPF záznam pro wgs-service.cz:</h3>";
    $spfRecords = @dns_get_record('wgs-service.cz', DNS_TXT);
    if ($spfRecords) {
        foreach ($spfRecords as $record) {
            if (isset($record['txt']) && strpos($record['txt'], 'v=spf1') !== false) {
                echo "<code>{$record['txt']}</code><br>";
            }
        }
    } else {
        echo "<span class='error'>Nepodařilo se načíst SPF záznam</span>";
    }
    echo "</div>";

    echo "<div class='section'>";
    echo "<h3>DMARC záznam pro _dmarc.wgs-service.cz:</h3>";
    $dmarcRecords = @dns_get_record('_dmarc.wgs-service.cz', DNS_TXT);
    if ($dmarcRecords) {
        foreach ($dmarcRecords as $record) {
            if (isset($record['txt']) && strpos($record['txt'], 'v=DMARC') !== false) {
                $dmarc = $record['txt'];
                echo "<code>{$dmarc}</code><br><br>";

                // Analyzovat DMARC politiku
                if (strpos($dmarc, 'p=reject') !== false) {
                    echo "<div class='error'>⚠️ <strong>PROBLÉM NALEZEN!</strong><br>";
                    echo "DMARC politika je nastavena na <code>p=reject</code> (velmi přísná).<br>";
                    echo "To způsobuje zamítnutí emailů při nesouladu SPF/DKIM.<br>";
                    echo "<strong>DOPORUČENÍ:</strong> Změnit na <code>p=quarantine</code> nebo <code>p=none</code></div>";
                } elseif (strpos($dmarc, 'p=quarantine') !== false) {
                    echo "<div class='warning'>✓ DMARC politika je <code>p=quarantine</code> (vhodná)</div>";
                } else {
                    echo "<div class='info'>✓ DMARC politika OK</div>";
                }

                // Kontrola alignment modes
                if (strpos($dmarc, 'aspf=s') !== false || strpos($dmarc, 'adkim=s') !== false) {
                    echo "<div class='error'>⚠️ <strong>PROBLÉM NALEZEN!</strong><br>";
                    echo "DMARC má strict alignment mode (<code>aspf=s</code> nebo <code>adkim=s</code>).<br>";
                    echo "To vyžaduje PŘESNOU shodu mezi From a SMTP envelope.<br>";
                    echo "<strong>DOPORUČENÍ:</strong> Změnit na relaxed mode (<code>aspf=r</code>, <code>adkim=r</code>)</div>";
                }
            }
        }
    } else {
        echo "<span class='warning'>DMARC záznam nenalezen (což může být OK)</span>";
    }
    echo "</div>";

    // 3. Doporučení řešení
    echo "<h2>3️⃣ Analýza problému</h2>";

    $problems = [];
    $recommendations = [];

    // Kontrola From emailu
    if ($smtp && $smtp['smtp_from_email'] === 'reklamace@wgs-service.cz') {
        echo "<div class='section info'>";
        echo "<strong>FROM email je nastaven správně:</strong> <code>reklamace@wgs-service.cz</code><br>";
        echo "Tento email účet existuje a je použitelný pro WebSMTP.";
        echo "</div>";
    }

    // Kontrola WebSMTP konfigurace
    if ($smtp && $smtp['smtp_host'] === 'websmtp.cesky-hosting.cz' && $smtp['smtp_port'] == 25) {
        echo "<div class='section success'>";
        echo "✓ SMTP je správně nakonfigurován pro WebSMTP (websmtp.cesky-hosting.cz:25)";
        echo "</div>";
    }

    // Hlavní diagnóza
    echo "<div class='section error'>";
    echo "<h3>🔴 Pravděpodobná příčina problému:</h3>";
    echo "<strong>SPF Policy Error při MAIL FROM</strong> znamená, že:<br><br>";
    echo "1. <strong>DMARC politika je příliš přísná</strong> (p=reject s strict alignment)<br>";
    echo "2. <strong>DNS změny ještě nestihl propagovat</strong> (může trvat až 24-48 hodin)<br>";
    echo "3. <strong>SMTP server kontroluje SPF striktně</strong> při MAIL FROM příkazu<br><br>";
    echo "<strong>Možná řešení:</strong><br>";
    echo "• Zkontrolovat DMARC záznam v cPanel DNS Zone Editor<br>";
    echo "• Změnit DMARC na: <code>v=DMARC1; p=quarantine; rua=mailto:reklamace@wgs-service.cz; fo=1; adkim=r; aspf=r</code><br>";
    echo "• Případně dočasně odstranit DMARC záznam pro testování<br>";
    echo "• Počkat 2-4 hodiny na propagaci DNS změn<br>";
    echo "</div>";

    // 4. Testovací akce
    echo "<h2>4️⃣ Další kroky</h2>";
    echo "<div class='section'>";
    echo "<strong>Online nástroje pro kontrolu DNS:</strong><br>";
    echo "<a href='https://mxtoolbox.com/SuperTool.aspx?action=dmarc%3awgs-service.cz' target='_blank' class='btn'>MXToolbox - DMARC Check</a>";
    echo "<a href='https://mxtoolbox.com/SuperTool.aspx?action=spf%3awgs-service.cz' target='_blank' class='btn'>MXToolbox - SPF Check</a><br><br>";

    echo "<strong>Akce:</strong><br>";
    echo "<a href='/admin.php' class='btn'>Zpět do Admin Panelu</a>";
    echo "<a href='?' class='btn'>Obnovit diagnostiku</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='section error'>";
    echo "<strong>Chyba:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
