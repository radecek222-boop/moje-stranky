<?php
/**
 * Oprava SMTP konfigurace
 *
 * Tento skript opraví SMTP nastavení v tabulce wgs_smtp_settings
 * aby používalo správný server: smtp.ceskyhosting.cz:587 s TLS
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit opravu.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Oprava SMTP konfigurace</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
             padding-bottom: 10px; }
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
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #2D5016; color: white; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Oprava SMTP konfigurace</h1>";

    // Zobrazit aktuální konfiguraci
    echo "<div class='info'><strong>AKTUÁLNÍ KONFIGURACE:</strong></div>";

    $stmt = $pdo->query("SELECT * FROM wgs_smtp_settings WHERE is_active = 1");
    $currentConfig = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($currentConfig) {
        echo "<table>";
        echo "<tr><th>Položka</th><th>Hodnota</th></tr>";
        echo "<tr><td><strong>SMTP Host</strong></td><td><code>{$currentConfig['smtp_host']}</code></td></tr>";
        echo "<tr><td><strong>Port</strong></td><td><code>{$currentConfig['smtp_port']}</code></td></tr>";
        echo "<tr><td><strong>Šifrování</strong></td><td><code>" . ($currentConfig['smtp_encryption'] ?: 'ŽÁDNÉ ❌') . "</code></td></tr>";
        echo "<tr><td><strong>Username</strong></td><td><code>{$currentConfig['smtp_username']}</code></td></tr>";
        echo "<tr><td><strong>From Email</strong></td><td><code>{$currentConfig['smtp_from_email']}</code></td></tr>";
        echo "</table>";

        echo "<div class='warning'>";
        echo "<strong>⚠️ PROBLÉM:</strong><br>";
        echo "Port 25 bez šifrování je zastaralý a servery ho odmítají (SPF policy error).<br>";
        echo "Je potřeba použít port 587 s TLS šifrováním.";
        echo "</div>";
    }

    // Pokud je nastaveno ?execute=1, provést opravu
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>PROVÁDÍM OPRAVU...</strong></div>";

        $pdo->beginTransaction();

        try {
            // Získat heslo z wgs_system_config
            $stmt = $pdo->query("SELECT config_value FROM wgs_system_config WHERE config_key = 'smtp_password'");
            $passwordRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $password = $passwordRow ? $passwordRow['config_value'] : '';

            // Aktualizovat SMTP konfiguraci
            $updateStmt = $pdo->prepare("
                UPDATE wgs_smtp_settings
                SET
                    smtp_host = :host,
                    smtp_port = :port,
                    smtp_username = :username,
                    smtp_password = :password,
                    smtp_encryption = :encryption,
                    smtp_from_email = :from_email,
                    smtp_from_name = :from_name
                WHERE id = :id
            ");

            $updateStmt->execute([
                ':host' => 'smtp.ceskyhosting.cz',
                ':port' => 587,
                ':username' => 'reklamace@wgs-service.cz',
                ':password' => $password,
                ':encryption' => 'tls',
                ':from_email' => 'reklamace@wgs-service.cz',
                ':from_name' => 'White Glove Service',
                ':id' => $currentConfig['id']
            ]);

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✅ SMTP KONFIGURACE OPRAVENA!</strong><br><br>";
            echo "<strong>Nová konfigurace:</strong><br>";
            echo "Host: <code>smtp.ceskyhosting.cz</code><br>";
            echo "Port: <code>587</code><br>";
            echo "Šifrování: <code>TLS</code><br>";
            echo "Username: <code>reklamace@wgs-service.cz</code><br>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<h3>📋 CO DĚLAT DÁL:</h3>";
            echo "<ol>";
            echo "<li>Otevři <a href='/protokol.php?id=CCC-test00001' target='_blank'>protokol</a></li>";
            echo "<li>Vyplň formulář a klikni <strong>ODESLAT ZÁKAZNÍKOVI</strong></li>";
            echo "<li>Měl by se odeslat email bez chyby!</li>";
            echo "</ol>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>❌ CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    } else {
        // Náhled co bude provedeno
        echo "<div class='info'>";
        echo "<h3>📋 CO SE PROVEDE:</h3>";
        echo "<table>";
        echo "<tr><th>Položka</th><th>Stará hodnota</th><th>Nová hodnota</th></tr>";
        echo "<tr><td>SMTP Host</td><td><code>{$currentConfig['smtp_host']}</code></td><td><code>smtp.ceskyhosting.cz</code></td></tr>";
        echo "<tr><td>Port</td><td><code>{$currentConfig['smtp_port']}</code></td><td><code>587</code></td></tr>";
        echo "<tr><td>Šifrování</td><td><code>" . ($currentConfig['smtp_encryption'] ?: 'ŽÁDNÉ') . "</code></td><td><code>tls</code></td></tr>";
        echo "</table>";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>OPRAVIT SMTP KONFIGURACI</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<hr style='margin: 30px 0;'>";
echo "<a href='/admin.php' class='btn'>Zpět na Admin</a>";
echo "<a href='/diagnoza_smtp.php' class='btn'>SMTP Diagnostika</a>";

echo "</div></body></html>";
?>
