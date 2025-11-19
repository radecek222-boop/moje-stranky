<?php
/**
 * Nastavení SMTP hesla do databáze
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Nastavit SMTP heslo</title>
<style>
body{font-family:'Segoe UI',sans-serif;max-width:800px;margin:50px auto;padding:20px;background:#f5f5f5;}
.container{background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}
h1{color:#2D5016;border-bottom:3px solid #2D5016;padding-bottom:10px;}
.success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:15px;border-radius:5px;margin:15px 0;}
.error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:5px;margin:15px 0;}
.info{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;border-radius:5px;margin:15px 0;}
.btn{display:inline-block;padding:12px 24px;background:#2D5016;color:white;text-decoration:none;border-radius:5px;margin:10px 5px 10px 0;font-weight:bold;}
.btn:hover{background:#1a300d;}
code{background:#f4f4f4;padding:3px 8px;border-radius:3px;}
table{width:100%;border-collapse:collapse;margin:15px 0;}
th,td{border:1px solid #ddd;padding:10px;text-align:left;}
th{background:#2D5016;color:white;}
</style></head><body><div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔑 Nastavení SMTP hesla</h1>";

    // Aktuální konfigurace
    $stmt = $pdo->query("SELECT * FROM wgs_smtp_settings WHERE is_active = 1 LIMIT 1");
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<div class='info'>";
    echo "<strong>AKTUÁLNÍ KONFIGURACE:</strong><br>";
    echo "Host: <code>" . htmlspecialchars($current['smtp_host']) . "</code><br>";
    echo "Port: <code>" . htmlspecialchars($current['smtp_port']) . "</code><br>";
    echo "Username: <code>" . htmlspecialchars($current['smtp_username']) . "</code><br>";
    echo "Password: <code>" . (strlen($current['smtp_password']) > 0 ? str_repeat('*', strlen($current['smtp_password'])) : 'PRÁZDNÉ') . "</code><br>";
    echo "Encryption: <code>" . htmlspecialchars($current['smtp_encryption']) . "</code>";
    echo "</div>";

    $heslo = 'p7u.s13mR2018';

    if (isset($_GET['nastav']) && $_GET['nastav'] === '1') {
        echo "<div class='info'><strong>NASTAVUJI HESLO...</strong></div>";

        $pdo->beginTransaction();

        try {
            $updateStmt = $pdo->prepare("
                UPDATE wgs_smtp_settings
                SET smtp_password = :password,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $updateStmt->execute([
                ':password' => $heslo,
                ':id' => $current['id']
            ]);

            $pdo->commit();

            echo "<div class='success'>";
            echo "<h2>✅ HESLO NASTAVENO!</h2>";
            echo "<p>SMTP heslo bylo úspěšně uloženo do databáze.</p>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<h3>📋 DALŠÍ KROKY:</h3>";
            echo "<p><strong>Nyní zkus tyto 2 varianty:</strong></p>";
            echo "<table>";
            echo "<tr><th>Varianta</th><th>Konfigurace</th><th>Akce</th></tr>";

            echo "<tr>";
            echo "<td><strong>A) Port 587 + TLS</strong></td>";
            echo "<td>Host: websmtp.cesky-hosting.cz<br>Port: 587<br>Username: reklamace@wgs-service.cz<br>Password: ***<br>Encryption: tls</td>";
            echo "<td><a href='/nastav_smtp_587_auth.php' class='btn'>Vyzkoušet</a></td>";
            echo "</tr>";

            echo "<tr>";
            echo "<td><strong>B) smtp.cesky-hosting.cz</strong></td>";
            echo "<td>Host: smtp.cesky-hosting.cz<br>Port: 587<br>Username: reklamace@wgs-service.cz<br>Password: ***<br>Encryption: tls</td>";
            echo "<td><a href='/nastav_smtp_cesky.php' class='btn'>Vyzkoušet</a></td>";
            echo "</tr>";

            echo "</table>";
            echo "</div>";

            echo "<a href='/scripts/process_email_queue.php' class='btn'>Spustit Queue Worker</a> ";
            echo "<a href='/diagnostika_email_queue.php' class='btn'>Diagnostika</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'><strong>❌ CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
        }

    } else {
        // Preview
        echo "<div class='info'>";
        echo "<h3>🔑 CO SE PROVEDE:</h3>";
        echo "<p>Nastaví se SMTP heslo do databáze:</p>";
        echo "<code>" . htmlspecialchars($heslo) . "</code>";
        echo "</div>";

        echo "<table>";
        echo "<tr><th>Parametr</th><th>Před</th><th>→ Po</th></tr>";
        echo "<tr><td>Password</td><td><code>PRÁZDNÉ</code></td><td><code>***</code> (16 znaků)</td></tr>";
        echo "</table>";

        echo "<a href='?nastav=1' class='btn'>🔑 NASTAVIT HESLO</a> ";
        echo "<a href='/admin.php' class='btn' style='background:#666;'>Zrušit</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
