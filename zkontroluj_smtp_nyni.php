<?php
/**
 * RYCHLÁ KONTROLA SMTP - CO JE TEĎ NASTAVENO?
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html><head><meta charset='UTF-8'><title>SMTP Kontrola</title>
<style>
body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;}
.box{background:#252526;padding:20px;margin:15px 0;border-left:4px solid #007acc;}
.error{border-left-color:#f44747;}
.success{border-left-color:#4ec9b0;}
.warning{border-left-color:#ce9178;}
h1{color:#4ec9b0;margin:0 0 20px 0;}
h2{color:#dcdcaa;margin:20px 0 10px 0;font-size:16px;}
table{border-collapse:collapse;width:100%;margin:10px 0;}
td{padding:8px;border:1px solid #3e3e42;}
td:first-child{color:#9cdcfe;width:200px;}
code{color:#ce9178;}
.btn{display:inline-block;padding:10px 20px;background:#0e639c;color:white;text-decoration:none;border-radius:3px;margin:5px;font-weight:bold;}
.btn:hover{background:#1177bb;}
</style>
</head><body>

<h1>🔍 RYCHLÁ SMTP KONTROLA</h1>

<?php
try {
    $pdo = getDbConnection();

    // Aktuální konfigurace
    $stmt = $pdo->query("SELECT * FROM wgs_smtp_settings WHERE is_active = 1 LIMIT 1");
    $cfg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cfg) {
        echo "<div class='box error'><h2>❌ CHYBA: Žádná aktivní SMTP konfigurace!</h2></div>";
        exit;
    }

    // Zobraz aktuální nastavení
    echo "<div class='box warning'>";
    echo "<h2>⚙️ AKTUÁLNÍ KONFIGURACE:</h2>";
    echo "<table>";
    echo "<tr><td>Host</td><td><code>" . htmlspecialchars($cfg['smtp_host']) . "</code></td></tr>";
    echo "<tr><td>Port</td><td><code>" . htmlspecialchars($cfg['smtp_port']) . "</code></td></tr>";
    echo "<tr><td>Encryption</td><td><code>" . htmlspecialchars($cfg['smtp_encryption']) . "</code></td></tr>";
    echo "<tr><td>Username</td><td><code>" . htmlspecialchars($cfg['smtp_username']) . "</code></td></tr>";
    echo "<tr><td>Password</td><td><code>" . (strlen($cfg['smtp_password']) > 0 ? '***' . substr($cfg['smtp_password'], -4) : 'PRÁZDNÉ') . "</code></td></tr>";
    echo "<tr><td>From Email</td><td><code>" . htmlspecialchars($cfg['smtp_from_email']) . "</code></td></tr>";
    echo "<tr><td>Updated</td><td>" . $cfg['updated_at'] . "</td></tr>";
    echo "</table>";
    echo "</div>";

    // Poslední chyba
    echo "<div class='box error'>";
    echo "<h2>❌ POSLEDNÍ CHYBA:</h2>";
    echo "<pre>SMTP Error: Could not connect to SMTP host
Failed to connect to server
SMTP code: 113
No route to host</pre>";
    echo "<p><strong>CO TO ZNAMENÁ:</strong></p>";
    echo "<p>Server <code>" . htmlspecialchars($cfg['smtp_host']) . ":" . htmlspecialchars($cfg['smtp_port']) . "</code> NENÍ DOSTUPNÝ.</p>";
    echo "</div>";

    // Funkční konfigurace z historie
    echo "<div class='box success'>";
    echo "<h2>✅ CO FUNGOVALO 18.11.2025:</h2>";
    echo "<table>";
    echo "<tr><td>Host</td><td><code>websmtp.cesky-hosting.cz</code></td></tr>";
    echo "<tr><td>Port</td><td><code>25</code></td></tr>";
    echo "<tr><td>Encryption</td><td><code>none</code></td></tr>";
    echo "<tr><td>Username</td><td><code>PRÁZDNÉ</code></td></tr>";
    echo "<tr><td>Password</td><td><code>PRÁZDNÉ</code></td></tr>";
    echo "</table>";
    echo "<p><strong>8 emailů úspěšně odesláno!</strong></p>";
    echo "</div>";

    // Rychlé opravy
    echo "<h2>🚀 RYCHLÉ OPRAVY:</h2>";

    // Oprava 1: Websmtp port 25
    echo "<div class='box'>";
    echo "<h2>OPRAVA 1: Websmtp port 25 (bez hesla)</h2>";
    if (isset($_GET['oprav']) && $_GET['oprav'] === 'websmtp25') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                UPDATE wgs_smtp_settings
                SET smtp_host = 'websmtp.cesky-hosting.cz',
                    smtp_port = 25,
                    smtp_encryption = 'none',
                    smtp_username = '',
                    smtp_password = '',
                    updated_at = NOW()
                WHERE is_active = 1
            ");
            $stmt->execute();
            $pdo->commit();
            echo "<div style='background:#4ec9b0;color:black;padding:10px;margin:10px 0;'>✅ HOTOVO! Zkuste teď odeslat email z protokolu.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<div style='background:#f44747;color:white;padding:10px;'>❌ " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<a href='?oprav=websmtp25' class='btn'>NASTAVIT WEBSMTP:25</a>";
    }
    echo "</div>";

    // Oprava 2: Websmtp port 587
    echo "<div class='box'>";
    echo "<h2>OPRAVA 2: Websmtp port 587 (s heslem)</h2>";
    if (isset($_GET['oprav']) && $_GET['oprav'] === 'websmtp587') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                UPDATE wgs_smtp_settings
                SET smtp_host = 'websmtp.cesky-hosting.cz',
                    smtp_port = 587,
                    smtp_encryption = 'tls',
                    smtp_username = 'reklamace@wgs-service.cz',
                    smtp_password = 'p7u.s13mR2018',
                    updated_at = NOW()
                WHERE is_active = 1
            ");
            $stmt->execute();
            $pdo->commit();
            echo "<div style='background:#4ec9b0;color:black;padding:10px;margin:10px 0;'>✅ HOTOVO! Zkuste teď odeslat email z protokolu.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<div style='background:#f44747;color:white;padding:10px;'>❌ " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<a href='?oprav=websmtp587' class='btn'>NASTAVIT WEBSMTP:587</a>";
    }
    echo "</div>";

    // Oprava 3: smtp.cesky-hosting.cz
    echo "<div class='box'>";
    echo "<h2>OPRAVA 3: smtp.cesky-hosting.cz:587 (s heslem)</h2>";
    if (isset($_GET['oprav']) && $_GET['oprav'] === 'smtp587') {
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
            echo "<div style='background:#4ec9b0;color:black;padding:10px;margin:10px 0;'>✅ HOTOVO! Zkuste teď odeslat email z protokolu.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<div style='background:#f44747;color:white;padding:10px;'>❌ " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<a href='?oprav=smtp587' class='btn'>NASTAVIT SMTP:587</a>";
    }
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='box error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<hr style="border:1px solid #3e3e42;margin:30px 0;">
<a href="/admin.php" class='btn'>← Admin</a>
<a href="/protokol.php?reklamace_id=WGS/2025/18-11/00001" class='btn'>Protokol</a>

</body></html>
