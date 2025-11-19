<?php
/**
 * FINÁLNÍ ŘEŠENÍ: Nastavení FUNKČNÍ SMTP konfigurace
 *
 * Problém: smtp.cesky-hosting.cz je nedostupný (No route to host)
 * Řešení: Použít websmtp.cesky-hosting.cz (který včera fungoval)
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>FINÁLNÍ řešení SMTP</title>
<style>
body{font-family:'Segoe UI',sans-serif;max-width:900px;margin:50px auto;padding:20px;background:#f5f5f5;}
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

    echo "<h1>🎯 FINÁLNÍ Řešení SMTP</h1>";

    // Aktuální konfigurace
    $stmt = $pdo->query("SELECT * FROM wgs_smtp_settings WHERE is_active = 1 LIMIT 1");
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<div class='error'>";
    echo "<h2>❌ CO JE ŠPATNĚ:</h2>";
    echo "<p><strong>1. smtp.cesky-hosting.cz je NEDOSTUPNÝ</strong></p>";
    echo "<pre>SMTP ERROR: Failed to connect to server: No route to host (113)</pre>";
    echo "<p><strong>2. SPF Policy Error</strong></p>";
    echo "<pre>Permanent error in sender's domain SPF policy (550 5.7.1)</pre>";
    echo "</div>";

    echo "<div class='info'>";
    echo "<h2>✅ CO FUNGOVALO VČERA (18.11.2025):</h2>";
    echo "<p>5 emailů úspěšně odesláno! To znamená, že konfigurace VČERA byla správná.</p>";
    echo "<p><strong>Pravděpodobně to byla konfigurace:</strong></p>";
    echo "<ul>";
    echo "<li>Host: <code>websmtp.cesky-hosting.cz</code></li>";
    echo "<li>Port: <code>25</code> nebo <code>587</code></li>";
    echo "<li>S heslem: <code>p7u.s13mR2018</code></li>";
    echo "</ul>";
    echo "</div>";

    echo "<h2>🧪 ZKUSÍME 2 FUNKČNÍ VARIANTY:</h2>";

    // VARIANTA 1
    echo "<div class='info'>";
    echo "<h3>VARIANTA 1: websmtp.cesky-hosting.cz:587 + TLS + AUTH</h3>";
    echo "<table>";
    echo "<tr><th>Parametr</th><th>Hodnota</th></tr>";
    echo "<tr><td>Host</td><td><code>websmtp.cesky-hosting.cz</code></td></tr>";
    echo "<tr><td>Port</td><td><code>587</code></td></tr>";
    echo "<tr><td>Encryption</td><td><code>tls</code></td></tr>";
    echo "<tr><td>Username</td><td><code>reklamace@wgs-service.cz</code></td></tr>";
    echo "<tr><td>Password</td><td><code>p7u.s13mR2018</code></td></tr>";
    echo "</table>";

    if (isset($_GET['apply']) && $_GET['apply'] === '1') {
        echo "<div class='info'><strong>APLIKUJI VARIANTU 1...</strong></div>";

        $pdo->beginTransaction();
        try {
            $updateStmt = $pdo->prepare("
                UPDATE wgs_smtp_settings
                SET smtp_host = 'websmtp.cesky-hosting.cz',
                    smtp_port = 587,
                    smtp_encryption = 'tls',
                    smtp_username = 'reklamace@wgs-service.cz',
                    smtp_password = 'p7u.s13mR2018',
                    updated_at = NOW()
                WHERE id = :id
            ");
            $updateStmt->execute([':id' => $current['id']]);
            $pdo->commit();

            echo "<div class='success'>";
            echo "<h2>✅ VARIANTA 1 APLIKOVÁNA!</h2>";
            echo "<p>Konfigurace nastavena na: <code>websmtp.cesky-hosting.cz:587</code> s TLS autentizací</p>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<h3>📋 CO TEĎKA:</h3>";
            echo "<ol>";
            echo "<li>Vytvořte NOVÝ testovací email v aplikaci (protokol nebo reklamace)</li>";
            echo "<li>Email se automaticky přidá do fronty</li>";
            echo "<li>Zkontrolujte <a href='/diagnostika_email_queue.php'>diagnostiku</a></li>";
            echo "</ol>";
            echo "</div>";

            echo "<a href='/diagnostika_email_queue.php' class='btn'>→ Diagnostika</a> ";
            echo "<a href='/vycisti_testovaci_emaily.php' class='btn'>Vyčistit staré testy</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'><strong>❌ CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
        }

    } else {
        echo "<a href='?apply=1' class='btn'>VYZKOUŠET VARIANTU 1</a>";
    }
    echo "</div>";

    // VARIANTA 2
    echo "<div class='info'>";
    echo "<h3>VARIANTA 2: websmtp.cesky-hosting.cz:25 BEZ autentizace</h3>";
    echo "<table>";
    echo "<tr><th>Parametr</th><th>Hodnota</th></tr>";
    echo "<tr><td>Host</td><td><code>websmtp.cesky-hosting.cz</code></td></tr>";
    echo "<tr><td>Port</td><td><code>25</code></td></tr>";
    echo "<tr><td>Encryption</td><td><code>none</code></td></tr>";
    echo "<tr><td>Username</td><td><code>PRÁZDNÉ</code></td></tr>";
    echo "<tr><td>Password</td><td><code>PRÁZDNÉ</code></td></tr>";
    echo "</table>";

    if (isset($_GET['apply']) && $_GET['apply'] === '2') {
        echo "<div class='info'><strong>APLIKUJI VARIANTU 2...</strong></div>";

        $pdo->beginTransaction();
        try {
            $updateStmt = $pdo->prepare("
                UPDATE wgs_smtp_settings
                SET smtp_host = 'websmtp.cesky-hosting.cz',
                    smtp_port = 25,
                    smtp_encryption = 'none',
                    smtp_username = '',
                    smtp_password = '',
                    updated_at = NOW()
                WHERE id = :id
            ");
            $updateStmt->execute([':id' => $current['id']]);
            $pdo->commit();

            echo "<div class='success'>";
            echo "<h2>✅ VARIANTA 2 APLIKOVÁNA!</h2>";
            echo "<p>Konfigurace nastavena na: <code>websmtp.cesky-hosting.cz:25</code> BEZ autentizace</p>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<h3>📋 CO TEĎKA:</h3>";
            echo "<ol>";
            echo "<li>Vytvořte NOVÝ testovací email v aplikaci</li>";
            echo "<li>Zkontrolujte <a href='/diagnostika_email_queue.php'>diagnostiku</a></li>";
            echo "</ol>";
            echo "</div>";

            echo "<a href='/diagnostika_email_queue.php' class='btn'>→ Diagnostika</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'><strong>❌ CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
        }

    } else {
        echo "<a href='?apply=2' class='btn'>VYZKOUŠET VARIANTU 2</a>";
    }
    echo "</div>";

    echo "<div class='warning'>";
    echo "<h3>💡 MÉ DOPORUČENÍ:</h3>";
    echo "<p><strong>Zkuste VARIANTU 1</strong> (websmtp.cesky-hosting.cz:587 s heslem)</p>";
    echo "<p>Toto je standardní SMTP konfigurace pro odesílání emailů a pravděpodobně to fungovalo včera.</p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<hr><a href='/admin.php' class='btn'>← Admin panel</a>";
echo "</div></body></html>";
?>
