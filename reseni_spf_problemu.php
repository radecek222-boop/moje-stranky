<?php
/**
 * Řešení SPF Policy Error
 *
 * CHYBA: "550 5.7.1 Permanent error in sender's domain SPF policy"
 *
 * CO SE STALO:
 * ✅ SMTP autentizace už FUNGUJE (oprava protokol_api.php + EmailQueue.php)
 * ❌ NOVÝ problém: SPF DNS záznam blokuje odesílání
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Řešení SPF problému</title>
<style>
body{font-family:'Segoe UI',sans-serif;max-width:1000px;margin:50px auto;padding:20px;background:#f5f5f5;}
.container{background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}
h1{color:#2D5016;border-bottom:3px solid #2D5016;padding-bottom:10px;}
h2{color:#2D5016;margin-top:30px;}
.success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:15px;border-radius:5px;margin:15px 0;}
.error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:5px;margin:15px 0;}
.info{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;border-radius:5px;margin:15px 0;}
.warning{background:#fff3cd;border:1px solid #ffeaa7;color:#856404;padding:15px;border-radius:5px;margin:15px 0;}
.btn{display:inline-block;padding:12px 24px;background:#2D5016;color:white;text-decoration:none;border-radius:5px;margin:10px 5px 10px 0;font-weight:bold;}
.btn:hover{background:#1a300d;}
table{width:100%;border-collapse:collapse;margin:15px 0;}
th,td{border:1px solid #ddd;padding:10px;text-align:left;}
th{background:#2D5016;color:white;}
code{background:#f4f4f4;padding:3px 8px;border-radius:3px;font-family:monospace;}
pre{background:#f4f4f4;padding:15px;border-radius:5px;overflow-x:auto;font-size:12px;}
</style></head><body><div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🚨 SPF Policy Error - Řešení</h1>";

    // Aktuální stav
    echo "<div class='success'>";
    echo "<h2>✅ CO UŽ FUNGUJE:</h2>";
    echo "<p><strong>SMTP autentizace je OPRAVENA!</strong></p>";
    echo "<ul>";
    echo "<li>✅ EmailQueue.php má správnou podmínečnou autentizaci</li>";
    echo "<li>✅ protokol_api.php má správnou podmínečnou autentizaci</li>";
    echo "<li>✅ Už NENÍ chyba \"Could not authenticate\"</li>";
    echo "</ul>";
    echo "</div>";

    // Nový problém
    echo "<div class='error'>";
    echo "<h2>❌ NOVÝ PROBLÉM - SPF Policy Error:</h2>";
    echo "<pre>MAIL FROM command failed, Permanent error in sender's domain SPF policy
550 5.7.1</pre>";
    echo "<p><strong>CO TO ZNAMENÁ?</strong></p>";
    echo "<p>SPF (Sender Policy Framework) je DNS záznam, který říká: \"Které servery mohou posílat emaily z mé domény?\"</p>";
    echo "<p>Příjemcův mailový server zkontroloval SPF záznam pro doménu <code>wgs-service.cz</code> a zjistil, že <code>websmtp.cesky-hosting.cz</code> NENÍ autorizován.</p>";
    echo "</div>";

    // Historie
    echo "<div class='info'>";
    echo "<h2>📊 CO FUNGOVALO 18.11.2025:</h2>";
    $stmt = $pdo->query("
        SELECT COUNT(*) as pocet
        FROM wgs_email_queue
        WHERE status = 'sent'
          AND DATE(sent_at) = '2025-11-18'
    ");
    $stat = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>{$stat['pocet']} emailů úspěšně odesláno!</strong></p>";
    echo "<p>To znamená, že SPF záznam byl 18.11.2025 v pořádku.</p>";
    echo "<p><strong>CO SE ZMĚNILO?</strong></p>";
    echo "<ul>";
    echo "<li>❓ Změnil hosting provider SPF záznam?</li>";
    echo "<li>❓ Změnila se konfigurace serveru?</li>";
    echo "<li>❓ Používal se jiný SMTP server?</li>";
    echo "</ul>";
    echo "</div>";

    // Řešení
    echo "<h2>💡 MOŽNÁ ŘEŠENÍ:</h2>";

    // Řešení 1: Zkusit smtp.cesky-hosting.cz
    echo "<div class='warning'>";
    echo "<h3>ŘEŠENÍ 1: Zkusit smtp.cesky-hosting.cz místo websmtp</h3>";
    echo "<p>Možná <code>smtp.cesky-hosting.cz</code> je v SPF záznamu povolen.</p>";
    echo "<table>";
    echo "<tr><th>Parametr</th><th>Hodnota</th></tr>";
    echo "<tr><td>Host</td><td><code>smtp.cesky-hosting.cz</code></td></tr>";
    echo "<tr><td>Port</td><td><code>587</code></td></tr>";
    echo "<tr><td>Encryption</td><td><code>tls</code></td></tr>";
    echo "<tr><td>Username</td><td><code>reklamace@wgs-service.cz</code></td></tr>";
    echo "<tr><td>Password</td><td><code>p7u.s13mR2018</code></td></tr>";
    echo "</table>";

    if (isset($_GET['zkus']) && $_GET['zkus'] === 'smtp') {
        echo "<div class='info'><strong>APLIKUJI...</strong></div>";

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                UPDATE wgs_smtp_settings
                SET smtp_host = 'smtp.cesky-hosting.cz',
                    smtp_port = 587,
                    smtp_encryption = 'tls',
                    smtp_username = 'reklamace@wgs-service.cz',
                    smtp_password = 'p7u.s13mR2018',
                    updated_at = NOW()
                WHERE is_active = 1
            ");
            $stmt->execute();
            $pdo->commit();

            echo "<div class='success'>";
            echo "<h2>✅ SMTP NASTAVEN NA smtp.cesky-hosting.cz:587</h2>";
            echo "<p><strong>TEĎKA:</strong></p>";
            echo "<ol>";
            echo "<li>Otevřete protokol (např. WGS/2025/19-11/00001)</li>";
            echo "<li>Klikněte 'ODESLAT ZÁKAZNÍKOVI'</li>";
            echo "<li>Zkontrolujte, jestli funguje</li>";
            echo "</ol>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
        }

    } else {
        echo "<a href='?zkus=smtp' class='btn'>VYZKOUŠET ŘEŠENÍ 1</a>";
    }
    echo "</div>";

    // Řešení 2: Kontaktovat hosting
    echo "<div class='info'>";
    echo "<h3>ŘEŠENÍ 2: Kontaktovat Český hosting support</h3>";
    echo "<p><strong>Napište jim email s těmito informacemi:</strong></p>";
    echo "<pre>Předmět: SPF záznam pro wgs-service.cz

Dobrý den,

potřebuji pomoct s odesíláním emailů přes SMTP.

Doména: wgs-service.cz
Email: reklamace@wgs-service.cz

Problém:
Když odesílám emaily přes websmtp.cesky-hosting.cz:25,
dostávám chybu \"550 5.7.1 Permanent error in sender's domain SPF policy\".

18.11.2025 to fungovalo (8 emailů úspěšně odesláno),
ale od 19.11.2025 to nefunguje.

Otázky:
1. Změnil se SPF záznam pro wgs-service.cz?
2. Jaký SMTP server mám používat pro odesílání z PHP skriptů?
3. Je websmtp.cesky-hosting.cz autorizován v SPF záznamu?
4. Můžete zkontrolovat SPF konfiguraci?

Děkuji,
[Vaše jméno]</pre>";
    echo "<p><strong>Support email:</strong> <code>info@cesky-hosting.cz</code></p>";
    echo "</div>";

    // Řešení 3: Zkontrolovat SPF
    echo "<div class='info'>";
    echo "<h3>ŘEŠENÍ 3: Zkontrolovat SPF záznam</h3>";
    echo "<p>SPF záznam můžete zkontrolovat pomocí:</p>";
    echo "<pre>dig TXT wgs-service.cz</pre>";
    echo "<p>nebo online nástroje: <a href='https://mxtoolbox.com/spf.aspx' target='_blank'>MXToolbox SPF Checker</a></p>";
    echo "<p><strong>SPF záznam by měl obsahovat:</strong></p>";
    echo "<code>v=spf1 include:cesky-hosting.cz ~all</code>";
    echo "<p>nebo podobný include, který autorizuje SMTP servery Českého hostingu.</p>";
    echo "</div>";

    // Debug info
    echo "<h2>🔧 DEBUG INFO:</h2>";
    $stmt = $pdo->query("SELECT * FROM wgs_smtp_settings WHERE is_active = 1 LIMIT 1");
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>Parametr</th><th>Hodnota</th></tr>";
    echo "<tr><td>Host</td><td><code>" . htmlspecialchars($current['smtp_host']) . "</code></td></tr>";
    echo "<tr><td>Port</td><td><code>" . htmlspecialchars($current['smtp_port']) . "</code></td></tr>";
    echo "<tr><td>Encryption</td><td><code>" . htmlspecialchars($current['smtp_encryption']) . "</code></td></tr>";
    echo "<tr><td>Username</td><td><code>" . htmlspecialchars($current['smtp_username']) . "</code></td></tr>";
    echo "<tr><td>Password</td><td><code>" . (strlen($current['smtp_password']) > 0 ? str_repeat('*', min(20, strlen($current['smtp_password']))) : 'PRÁZDNÉ') . "</code></td></tr>";
    echo "<tr><td>From Email</td><td><code>" . htmlspecialchars($current['smtp_from_email']) . "</code></td></tr>";
    echo "</table>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<hr><a href='/admin.php' class='btn'>← Admin panel</a>";
echo "<a href='/diagnostika_email_queue.php' class='btn'>Diagnostika</a>";
echo "</div></body></html>";
?>
