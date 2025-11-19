<?php
/**
 * Oprava SMTP - VRÁTIT NA WEBSMTP
 *
 * Vrátí konfiguraci zpět na websmtp.cesky-hosting.cz (původní nastavení)
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Oprava SMTP → WebSMTP</title>
<style>
body{font-family:'Segoe UI',sans-serif;max-width:1000px;margin:50px auto;padding:20px;background:#f5f5f5;}
.container{background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}
h1{color:#2D5016;border-bottom:3px solid #2D5016;padding-bottom:10px;}
.success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:12px;border-radius:5px;margin:10px 0;}
.error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:12px;border-radius:5px;margin:10px 0;}
.warning{background:#fff3cd;border:1px solid #ffeaa7;color:#856404;padding:12px;border-radius:5px;margin:10px 0;}
.info{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:12px;border-radius:5px;margin:10px 0;}
.btn{display:inline-block;padding:10px 20px;background:#2D5016;color:white;text-decoration:none;border-radius:5px;margin:10px 5px 10px 0;}
.btn:hover{background:#1a300d;}
table{width:100%;border-collapse:collapse;margin:20px 0;}
th,td{border:1px solid #ddd;padding:12px;text-align:left;}
th{background:#2D5016;color:white;}
code{background:#f4f4f4;padding:2px 6px;border-radius:3px;font-family:'Courier New',monospace;}
</style></head><body><div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Návrat na WebSMTP konfiguraci</h1>";

    // Aktuální konfigurace
    $stmt = $pdo->query("SELECT * FROM wgs_smtp_settings WHERE is_active = 1");
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<div class='info'><strong>AKTUÁLNÍ KONFIGURACE:</strong></div>";
    echo "<table>";
    echo "<tr><th>Položka</th><th>Hodnota</th></tr>";
    echo "<tr><td>Host</td><td><code>{$current['smtp_host']}</code></td></tr>";
    echo "<tr><td>Port</td><td><code>{$current['smtp_port']}</code></td></tr>";
    echo "<tr><td>Šifrování</td><td><code>" . ($current['smtp_encryption'] ?: 'ŽÁDNÉ') . "</code></td></tr>";
    echo "</table>";

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>PROVÁDÍM OPRAVU...</strong></div>";

        $pdo->beginTransaction();

        try {
            // Vrátit na WebSMTP (původní nastavení)
            $updateStmt = $pdo->prepare("
                UPDATE wgs_smtp_settings
                SET
                    smtp_host = :host,
                    smtp_port = :port,
                    smtp_username = :username,
                    smtp_encryption = :encryption
                WHERE id = :id
            ");

            $updateStmt->execute([
                ':host' => 'websmtp.cesky-hosting.cz',
                ':port' => 25,
                ':username' => 'wgs-service.cz',
                ':encryption' => '',
                ':id' => $current['id']
            ]);

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✅ SMTP VRÁCENO NA WEBSMTP!</strong><br><br>";
            echo "<strong>Nová konfigurace:</strong><br>";
            echo "Host: <code>websmtp.cesky-hosting.cz</code><br>";
            echo "Port: <code>25</code><br>";
            echo "Username: <code>wgs-service.cz</code><br>";
            echo "Šifrování: <code>žádné</code><br>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<h3>📋 OTESTUJ TO:</h3>";
            echo "<ol>";
            echo "<li>Otevři <a href='/protokol.php?id=CCC-test00001' target='_blank'>protokol</a></li>";
            echo "<li>Klikni <strong>ODESLAT ZÁKAZNÍKOVI</strong></li>";
            echo "<li>Mělo by to fungovat jako včera!</li>";
            echo "</ol>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'><strong>❌ CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='warning'>";
        echo "<strong>⚠️ CO SE PROVEDE:</strong><br>";
        echo "Vrátím SMTP konfiguraci zpět na <strong>websmtp.cesky-hosting.cz</strong> (původní nastavení, které fungovalo včera).";
        echo "</div>";

        echo "<table>";
        echo "<tr><th>Položka</th><th>Teď</th><th>Po opravě</th></tr>";
        echo "<tr><td>Host</td><td><code>{$current['smtp_host']}</code></td><td><code>websmtp.cesky-hosting.cz</code></td></tr>";
        echo "<tr><td>Port</td><td><code>{$current['smtp_port']}</code></td><td><code>25</code></td></tr>";
        echo "<tr><td>Username</td><td><code>{$current['smtp_username']}</code></td><td><code>wgs-service.cz</code></td></tr>";
        echo "<tr><td>Šifrování</td><td><code>" . ($current['smtp_encryption'] ?: 'ŽÁDNÉ') . "</code></td><td><code>žádné</code></td></tr>";
        echo "</table>";

        echo "<a href='?execute=1' class='btn'>VRÁTIT NA WEBSMTP</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<hr><a href='/test_websmtp.php' class='btn'>Test WebSMTP</a> ";
echo "<a href='/admin.php' class='btn'>Zpět na Admin</a>";
echo "</div></body></html>";
?>
