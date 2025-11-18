<?php
/**
 * Diagnostika SMTP konfigurace
 * Zkontroluje proč protokol nelze odeslat
 */

require_once __DIR__ . '/init.php';

// Bezpečnost - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze pro administrátory");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>SMTP Diagnostika</title>
    <style>
        body { font-family: monospace; max-width: 1200px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; }
        h1 { color: #2D5016; }
        .success { background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; color: #155724; }
        .error { background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; color: #721c24; }
        .warning { background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; color: #856404; }
        .info { background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; color: #0c5460; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table th { background: #2D5016; color: white; padding: 10px; text-align: left; }
        table td { padding: 10px; border-bottom: 1px solid #ddd; }
        .btn { display: inline-block; padding: 10px 20px; background: #2D5016; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 SMTP DIAGNOSTIKA - Proč nejde odeslat protokol?</h1>";

try {
    $pdo = getDbConnection();

    // ===== KONTROLA 1: Existence tabulky wgs_smtp_settings =====
    echo "<h2>✅ KONTROLA 1: Tabulka wgs_smtp_settings</h2>";

    $tableExists = false;
    try {
        $pdo->query("SELECT 1 FROM wgs_smtp_settings LIMIT 0");
        $tableExists = true;
        echo "<div class='success'>✅ Tabulka wgs_smtp_settings EXISTUJE</div>";
    } catch (PDOException $e) {
        echo "<div class='error'>❌ Tabulka wgs_smtp_settings NEEXISTUJE!<br>";
        echo "Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "<div class='warning'>⚠️ ŘEŠENÍ: Spusťte <a href='/admin/install_email_system.php'>Email System Installer</a></div>";
    }

    // ===== KONTROLA 2: Aktivní SMTP konfigurace =====
    if ($tableExists) {
        echo "<h2>✅ KONTROLA 2: Aktivní SMTP konfigurace</h2>";

        $stmt = $pdo->query("SELECT * FROM wgs_smtp_settings ORDER BY id DESC");
        $allSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($allSettings)) {
            echo "<div class='error'>❌ Tabulka wgs_smtp_settings je PRÁZDNÁ!<br>";
            echo "Není nakonfigurováno žádné SMTP nastavení.</div>";
            echo "<div class='warning'>⚠️ ŘEŠENÍ: Spusťte <a href='/admin/install_email_system.php'>Email System Installer</a> nebo přidejte SMTP konfiguraci ručně</div>";
        } else {
            echo "<div class='info'>📋 Nalezeno " . count($allSettings) . " SMTP konfigurací:</div>";

            echo "<table>";
            echo "<tr>
                    <th>ID</th>
                    <th>Aktivní</th>
                    <th>SMTP Host</th>
                    <th>Port</th>
                    <th>Username</th>
                    <th>Šifrování</th>
                    <th>From Email</th>
                    <th>From Name</th>
                  </tr>";

            $activeFound = false;
            foreach ($allSettings as $setting) {
                $isActive = $setting['is_active'] == 1;
                if ($isActive) $activeFound = true;

                $rowStyle = $isActive ? "background: #d4edda;" : "";

                echo "<tr style='$rowStyle'>";
                echo "<td>" . htmlspecialchars($setting['id']) . "</td>";
                echo "<td>" . ($isActive ? "✅ ANO" : "❌ NE") . "</td>";
                echo "<td>" . htmlspecialchars($setting['smtp_host']) . "</td>";
                echo "<td>" . htmlspecialchars($setting['smtp_port']) . "</td>";
                echo "<td>" . htmlspecialchars($setting['smtp_username']) . "</td>";
                echo "<td>" . htmlspecialchars($setting['smtp_encryption']) . "</td>";
                echo "<td>" . htmlspecialchars($setting['smtp_from_email']) . "</td>";
                echo "<td>" . htmlspecialchars($setting['smtp_from_name'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";

            if (!$activeFound) {
                echo "<div class='error'>❌ ŽÁDNÁ KONFIGURACE NENÍ AKTIVNÍ (is_active = 1)!</div>";
                echo "<div class='warning'>⚠️ ŘEŠENÍ: Aktivujte jednu z konfigurací SQL příkazem:<br><br>";
                echo "<pre>UPDATE wgs_smtp_settings SET is_active = 1 WHERE id = " . $allSettings[0]['id'] . ";</pre></div>";
            } else {
                echo "<div class='success'>✅ Nalezena aktivní SMTP konfigurace</div>";
            }
        }
    }

    // ===== KONTROLA 3: PHPMailer =====
    echo "<h2>✅ KONTROLA 3: PHPMailer knihovna</h2>";

    $phpmailerPath = __DIR__ . '/vendor/phpmailer/src/PHPMailer.php';
    if (file_exists($phpmailerPath)) {
        echo "<div class='success'>✅ PHPMailer je nainstalován: $phpmailerPath</div>";

        // Zkusit načíst
        try {
            require_once __DIR__ . '/vendor/autoload.php';
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                echo "<div class='success'>✅ PHPMailer class lze načíst</div>";
            } else {
                echo "<div class='error'>❌ PHPMailer class nelze načíst</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>❌ Chyba při načítání PHPMailer: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='error'>❌ PHPMailer NENÍ nainstalován!<br>";
        echo "Cesta: $phpmailerPath</div>";
        echo "<div class='warning'>⚠️ ŘEŠENÍ: Spusťte <a href='/admin/install_email_system.php'>Email System Installer</a></div>";
    }

    // ===== KONTROLA 4: Fallback konfigurace v wgs_system_config =====
    echo "<h2>✅ KONTROLA 4: Fallback konfigurace (wgs_system_config)</h2>";

    try {
        $stmt = $pdo->query("
            SELECT config_key, config_value
            FROM wgs_system_config
            WHERE config_group = 'email' OR config_key LIKE 'smtp_%'
        ");
        $systemConfig = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($systemConfig)) {
            echo "<div class='warning'>⚠️ Žádná email konfigurace v wgs_system_config (to je OK, pokud používáte wgs_smtp_settings)</div>";
        } else {
            echo "<div class='info'>📋 Email konfigurace v wgs_system_config:</div>";
            echo "<table>";
            echo "<tr><th>Klíč</th><th>Hodnota</th></tr>";
            foreach ($systemConfig as $cfg) {
                $value = $cfg['config_value'];
                // Maskovat heslo
                if (strpos($cfg['config_key'], 'password') !== false) {
                    $value = str_repeat('*', strlen($value));
                }
                echo "<tr>";
                echo "<td>" . htmlspecialchars($cfg['config_key']) . "</td>";
                echo "<td>" . htmlspecialchars($value) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (PDOException $e) {
        echo "<div class='warning'>⚠️ Tabulka wgs_system_config neexistuje nebo nemá email konfiguraci</div>";
    }

    // ===== KONTROLA 5: ENV proměnné =====
    echo "<h2>✅ KONTROLA 5: Environment variables (.env)</h2>";

    $envVars = [
        'SMTP_HOST' => getenv('SMTP_HOST'),
        'SMTP_PORT' => getenv('SMTP_PORT'),
        'SMTP_USER' => getenv('SMTP_USER'),
        'SMTP_PASS' => getenv('SMTP_PASS') ? str_repeat('*', strlen(getenv('SMTP_PASS'))) : false,
        'SMTP_FROM' => getenv('SMTP_FROM'),
    ];

    $hasEnv = false;
    foreach ($envVars as $key => $value) {
        if ($value !== false && $value !== '') {
            $hasEnv = true;
            break;
        }
    }

    if ($hasEnv) {
        echo "<div class='info'>📋 ENV proměnné (.env soubor):</div>";
        echo "<table>";
        echo "<tr><th>Proměnná</th><th>Hodnota</th></tr>";
        foreach ($envVars as $key => $value) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($key) . "</td>";
            echo "<td>" . ($value !== false && $value !== '' ? htmlspecialchars($value) : "<em>není nastaveno</em>") . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>⚠️ Žádné SMTP ENV proměnné (to je OK, pokud používáte wgs_smtp_settings)</div>";
    }

    // ===== SHRNUTÍ =====
    echo "<h2>📊 SHRNUTÍ</h2>";

    $errors = [];
    $warnings = [];

    if (!$tableExists) {
        $errors[] = "Tabulka wgs_smtp_settings neexistuje";
    } elseif (empty($allSettings)) {
        $errors[] = "Tabulka wgs_smtp_settings je prázdná";
    } elseif (!$activeFound) {
        $errors[] = "Žádná SMTP konfigurace není aktivní (is_active = 1)";
    }

    if (!file_exists($phpmailerPath)) {
        $errors[] = "PHPMailer není nainstalován";
    }

    if (!empty($errors)) {
        echo "<div class='error'>";
        echo "<strong>❌ NALEZENY CHYBY:</strong><ul>";
        foreach ($errors as $error) {
            echo "<li>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul></div>";

        echo "<div class='warning'><strong>🔧 DOPORUČENÉ ŘEŠENÍ:</strong><br><br>";
        echo "Spusťte Email System Installer, který vše automaticky nakonfiguruje:<br>";
        echo "<a href='/admin/install_email_system.php' class='btn'>📧 Email System Installer</a>";
        echo "</div>";
    } else {
        echo "<div class='success'>";
        echo "<strong>✅ SMTP konfigurace vypadá v pořádku!</strong><br><br>";
        echo "Pokud stále nejde odeslat email, zkontrolujte:<br>";
        echo "<ul>";
        echo "<li>SMTP přihlašovací údaje (heslo, username)</li>";
        echo "<li>Firewall pravidla (port 587 nebo 465)</li>";
        echo "<li>Logy v /logs/php_errors.log</li>";
        echo "</ul>";
        echo "</div>";

        echo "<div class='info'>";
        echo "<strong>🧪 Test odesílání:</strong><br>";
        echo "<a href='/scripts/test-smtp.php' class='btn'>📨 Test SMTP</a>";
        echo "</div>";
    }

    // ===== LOG ERRORS =====
    echo "<h2>📜 Poslední chyby z logů</h2>";

    $logFile = __DIR__ . '/logs/php_errors.log';
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $logLines = explode("\n", $logContent);
        $lastErrors = array_filter($logLines, function($line) {
            return stripos($line, 'smtp') !== false ||
                   stripos($line, 'phpmailer') !== false ||
                   stripos($line, 'protokol_api') !== false;
        });

        if (!empty($lastErrors)) {
            echo "<div class='warning'>";
            echo "<strong>⚠️ Poslední SMTP/PHPMailer chyby:</strong><br>";
            echo "<pre>" . htmlspecialchars(implode("\n", array_slice($lastErrors, -10))) . "</pre>";
            echo "</div>";
        } else {
            echo "<div class='info'>✅ Žádné SMTP chyby v logu</div>";
        }
    } else {
        echo "<div class='warning'>⚠️ Log soubor neexistuje: $logFile</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ KRITICKÁ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
