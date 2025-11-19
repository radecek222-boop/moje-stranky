<?php
/**
 * Test 3 variant SMTP konfigurace pro WebSMTP
 *
 * Problém: "SMTP Error: Could not authenticate"
 * Zkusíme 3 různé konfigurace
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test 3 variant SMTP</title>
<style>
body{font-family:'Segoe UI',sans-serif;max-width:1200px;margin:50px auto;padding:20px;background:#f5f5f5;}
.container{background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}
h1{color:#2D5016;border-bottom:3px solid #2D5016;padding-bottom:10px;}
h2{color:#2D5016;margin-top:30px;}
.variant{background:#f8f9fa;border-left:4px solid #2D5016;padding:20px;margin:20px 0;}
.success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:15px;border-radius:5px;margin:15px 0;}
.error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:5px;margin:15px 0;}
.info{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;border-radius:5px;margin:15px 0;}
.btn{display:inline-block;padding:12px 24px;background:#2D5016;color:white;text-decoration:none;border-radius:5px;margin:10px 5px 10px 0;font-weight:bold;}
.btn:hover{background:#1a300d;}
table{width:100%;border-collapse:collapse;margin:15px 0;}
th,td{border:1px solid #ddd;padding:10px;text-align:left;}
th{background:#2D5016;color:white;}
code{background:#f4f4f4;padding:3px 8px;border-radius:3px;font-family:monospace;}
</style></head><body><div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🧪 Test 3 variant SMTP konfigurace</h1>";

    echo "<div class='info'>";
    echo "<strong>PROBLÉM:</strong> Autentizace selhává s chybou:<br>";
    echo "<code>535 5.7.8 Error: authentication failed</code>";
    echo "</div>";

    // Aktuální konfigurace
    $stmt = $pdo->query("SELECT * FROM wgs_smtp_settings WHERE is_active = 1 LIMIT 1");
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<div class='info'>";
    echo "<strong>AKTUÁLNÍ KONFIGURACE:</strong><br>";
    echo "Host: <code>{$current['smtp_host']}</code><br>";
    echo "Port: <code>{$current['smtp_port']}</code><br>";
    echo "Username: <code>{$current['smtp_username']}</code><br>";
    echo "Password: <code>" . (strlen($current['smtp_password']) > 0 ? '***' : 'PRÁZDNÉ') . "</code><br>";
    echo "Encryption: <code>{$current['smtp_encryption']}</code>";
    echo "</div>";

    // 3 VARIANTY
    $varianty = [
        [
            'nazev' => 'VARIANTA 1: Port 25 BEZ autentizace',
            'popis' => 'WebSMTP může na portu 25 používat doménovou validaci místo hesla',
            'host' => 'websmtp.cesky-hosting.cz',
            'port' => 25,
            'encryption' => 'none',
            'username' => '',  // PRÁZDNÉ!
            'password' => '',  // PRÁZDNÉ!
        ],
        [
            'nazev' => 'VARIANTA 2: Port 587 S autentizací (TLS)',
            'popis' => 'Standardní SMTP s autentizací pro odesílání emailů',
            'host' => 'websmtp.cesky-hosting.cz',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'reklamace@wgs-service.cz',  // CELÁ ADRESA!
            'password' => $current['smtp_password'],
        ],
        [
            'nazev' => 'VARIANTA 3: smtp.cesky-hosting.cz:587 (původní)',
            'popis' => 'Původní server, možná fungoval lépe (ale timeout byl problém)',
            'host' => 'smtp.cesky-hosting.cz',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'reklamace@wgs-service.cz',
            'password' => $current['smtp_password'],
        ],
    ];

    foreach ($varianty as $i => $varianta) {
        $variantaNum = $i + 1;

        echo "<div class='variant'>";
        echo "<h2>{$varianta['nazev']}</h2>";
        echo "<p>{$varianta['popis']}</p>";

        echo "<table>";
        echo "<tr><th>Parametr</th><th>Hodnota</th></tr>";
        echo "<tr><td>Host</td><td><code>{$varianta['host']}</code></td></tr>";
        echo "<tr><td>Port</td><td><code>{$varianta['port']}</code></td></tr>";
        echo "<tr><td>Encryption</td><td><code>{$varianta['encryption']}</code></td></tr>";
        echo "<tr><td>Username</td><td><code>" . ($varianta['username'] ?: 'PRÁZDNÉ') . "</code></td></tr>";
        echo "<tr><td>Password</td><td><code>" . ($varianta['password'] ? '***' : 'PRÁZDNÉ') . "</code></td></tr>";
        echo "</table>";

        if (isset($_GET['apply']) && $_GET['apply'] == $variantaNum) {
            echo "<div class='info'><strong>APLIKUJI VARIANTU {$variantaNum}...</strong></div>";

            $pdo->beginTransaction();

            try {
                $updateStmt = $pdo->prepare("
                    UPDATE wgs_smtp_settings
                    SET
                        smtp_host = :host,
                        smtp_port = :port,
                        smtp_encryption = :encryption,
                        smtp_username = :username,
                        smtp_password = :password,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $updateStmt->execute([
                    ':host' => $varianta['host'],
                    ':port' => $varianta['port'],
                    ':encryption' => $varianta['encryption'],
                    ':username' => $varianta['username'],
                    ':password' => $varianta['password'],
                    ':id' => $current['id']
                ]);

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>✅ VARIANTA {$variantaNum} APLIKOVÁNA!</strong><br><br>";
                echo "SMTP konfigurace byla změněna. Nyní:<br>";
                echo "1. <a href='/scripts/process_email_queue.php' target='_blank'>Spusť email queue worker</a><br>";
                echo "2. Zkontroluj výsledek<br>";
                echo "3. Pokud nefunguje, zkus jinou variantu";
                echo "</div>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'><strong>❌ CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
            }

        } else {
            echo "<a href='?apply={$variantaNum}' class='btn'>VYZKOUŠET VARIANTU {$variantaNum}</a>";
        }

        echo "</div>";
    }

    echo "<div class='info'>";
    echo "<h3>💡 JAK POSTUPOVAT:</h3>";
    echo "<ol>";
    echo "<li><strong>Zkus VARIANTU 1</strong> (port 25 bez autentizace) - nejpravděpodobnější řešení</li>";
    echo "<li>Spusť <code>/scripts/process_email_queue.php</code></li>";
    echo "<li>Zkontroluj <code>/diagnostika_email_queue.php</code> - podívej se na chybovou zprávu</li>";
    echo "<li>Pokud nefunguje, zkus VARIANTU 2 nebo 3</li>";
    echo "</ol>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<hr>";
echo "<a href='/diagnostika_email_queue.php' class='btn'>Diagnostika</a> ";
echo "<a href='/admin.php' class='btn'>Admin panel</a>";
echo "</div></body></html>";
?>
