<?php
/**
 * OKAMŽITÁ OPRAVA SMTP na WebSMTP
 * Opravuje chybu s ENUM hodnotou 'none'
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Okamžitá oprava SMTP</title>
<style>
body{font-family:'Segoe UI',sans-serif;max-width:800px;margin:50px auto;padding:20px;background:#f5f5f5;}
.container{background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}
h1{color:#2D5016;border-bottom:3px solid #2D5016;padding-bottom:10px;}
.success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:15px;border-radius:5px;margin:15px 0;}
.error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:5px;margin:15px 0;}
.info{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;border-radius:5px;margin:15px 0;}
.btn{display:inline-block;padding:12px 24px;background:#2D5016;color:white;text-decoration:none;border-radius:5px;margin:10px 5px 10px 0;font-weight:bold;}
.btn:hover{background:#1a300d;}
code{background:#f4f4f4;padding:3px 8px;border-radius:3px;font-family:'Courier New',monospace;font-size:14px;}
table{width:100%;border-collapse:collapse;margin:20px 0;}
th,td{border:1px solid #ddd;padding:12px;text-align:left;}
th{background:#2D5016;color:white;font-weight:bold;}
</style></head><body><div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>⚡ Okamžitá oprava SMTP → WebSMTP</h1>";

    // Aktuální konfigurace
    $stmt = $pdo->query("SELECT * FROM wgs_smtp_settings WHERE is_active = 1 LIMIT 1");
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        echo "<div class='error'>❌ Nenalezena aktivní SMTP konfigurace!</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='info'><strong>AKTUÁLNÍ KONFIGURACE:</strong></div>";
    echo "<table>";
    echo "<tr><th>Položka</th><th>Hodnota</th></tr>";
    echo "<tr><td>Host</td><td><code>{$current['smtp_host']}</code></td></tr>";
    echo "<tr><td>Port</td><td><code>{$current['smtp_port']}</code></td></tr>";
    echo "<tr><td>Username</td><td><code>{$current['smtp_username']}</code></td></tr>";
    echo "<tr><td>Šifrování</td><td><code>" . ($current['smtp_encryption'] ?: 'žádné') . "</code></td></tr>";
    echo "</table>";

    if (isset($_GET['spustit']) && $_GET['spustit'] === '1') {
        echo "<div class='info'><strong>⚙️ PROVÁDÍM OPRAVU...</strong></div>";

        $pdo->beginTransaction();

        try {
            // Opravená verze - používá 'none' místo ''
            $updateStmt = $pdo->prepare("
                UPDATE wgs_smtp_settings
                SET
                    smtp_host = :host,
                    smtp_port = :port,
                    smtp_username = :username,
                    smtp_encryption = :encryption,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $updateStmt->execute([
                ':host' => 'websmtp.cesky-hosting.cz',
                ':port' => 25,
                ':username' => 'wgs-service.cz',
                ':encryption' => 'none',  // ✅ OPRAVENO - použita ENUM hodnota 'none'
                ':id' => $current['id']
            ]);

            $pdo->commit();

            echo "<div class='success'>";
            echo "<h2>✅ SMTP ÚSPĚŠNĚ NASTAVENO!</h2>";
            echo "<p><strong>Nová konfigurace WebSMTP:</strong></p>";
            echo "<table>";
            echo "<tr><th>Položka</th><th>Hodnota</th></tr>";
            echo "<tr><td>Host</td><td><code>websmtp.cesky-hosting.cz</code></td></tr>";
            echo "<tr><td>Port</td><td><code>25</code></td></tr>";
            echo "<tr><td>Username</td><td><code>wgs-service.cz</code></td></tr>";
            echo "<tr><td>Šifrování</td><td><code>none</code> (žádné)</td></tr>";
            echo "</table>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<h3>📋 DALŠÍ KROKY:</h3>";
            echo "<ol>";
            echo "<li>✅ SMTP je nastaveno na WebSMTP</li>";
            echo "<li>🧪 Otestuj odeslání emailu v aplikaci</li>";
            echo "<li>📧 Zkus poslat testovací notifikaci</li>";
            echo "</ol>";
            echo "<p><a href='/admin.php' class='btn'>→ Zpět na Admin panel</a></p>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>❌ CHYBA PŘI UKLÁDÁNÍ:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }

    } else {
        // Zobrazit náhled změn
        echo "<div class='info'>";
        echo "<h3>⚠️ CO SE PROVEDE:</h3>";
        echo "<p>Nastavím SMTP na <strong>websmtp.cesky-hosting.cz</strong> (port 25, bez šifrování)</p>";
        echo "</div>";

        echo "<table>";
        echo "<tr><th>Položka</th><th>TEĎ</th><th>→ PO OPRAVĚ</th></tr>";
        echo "<tr><td><strong>Host</strong></td><td><code>{$current['smtp_host']}</code></td><td><code>websmtp.cesky-hosting.cz</code></td></tr>";
        echo "<tr><td><strong>Port</strong></td><td><code>{$current['smtp_port']}</code></td><td><code>25</code></td></tr>";
        echo "<tr><td><strong>Username</strong></td><td><code>{$current['smtp_username']}</code></td><td><code>wgs-service.cz</code></td></tr>";
        echo "<tr><td><strong>Šifrování</strong></td><td><code>{$current['smtp_encryption']}</code></td><td><code>none</code></td></tr>";
        echo "</table>";

        echo "<div class='info'>";
        echo "<p><strong>💡 Podle diagnostiky:</strong></p>";
        echo "<ul>";
        echo "<li>✅ Port 25: FUNGUJE!</li>";
        echo "<li>✅ Port 587: FUNGUJE!</li>";
        echo "<li>❌ Port 465: Nefunguje</li>";
        echo "</ul>";
        echo "<p>Používáme port 25 (standardní, žádné šifrování)</p>";
        echo "</div>";

        echo "<a href='?spustit=1' class='btn'>⚡ OPRAVIT IHNED</a> ";
        echo "<a href='/admin.php' class='btn' style='background:#666;'>Zrušit</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
