<?php
/**
 * ZOBRAZENÍ STÁVAJÍCÍHO .ENV
 * Ukáže aktuální DB credentials
 */

session_start();
$jeAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

if (!$jeAdmin && !isset($_GET['show'])) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Consolas; background: #000; color: #0f0; padding: 40px;"><h1>❌ PŘÍSTUP ODEPŘEN</h1><p>Přidej ?show=1 k URL pokud chceš zobrazit .env bez přihlášení (nebezpečné!)</p></body></html>');
}

$envFile = __DIR__ . '/.env';
$envBackup = __DIR__ . '/.env.backup';

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zobrazení .env | WGS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Consolas, Monaco, monospace;
            background: #000;
            color: #0f0;
            padding: 2rem;
            line-height: 1.6;
        }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #0ff; margin-bottom: 2rem; font-size: 1.5rem; }
        h2 { color: #ff0; margin: 2rem 0 1rem 0; font-size: 1.2rem; }
        .file-box {
            background: #111;
            border: 2px solid #0f0;
            padding: 1.5rem;
            margin: 1rem 0;
        }
        .file-box.missing {
            border-color: #f00;
        }
        pre {
            background: #000;
            border: 1px solid #333;
            padding: 1rem;
            overflow-x: auto;
            font-size: 0.9rem;
            color: #0f0;
        }
        .highlight {
            background: #330;
            color: #ff0;
            padding: 0.2rem 0.5rem;
        }
        .info {
            background: #030;
            border-left: 4px solid #0f0;
            padding: 1rem;
            margin: 1rem 0;
            color: #0f0;
        }
        .warning {
            background: #300;
            border-left: 4px solid #f00;
            padding: 1rem;
            margin: 1rem 0;
            color: #f00;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 ZOBRAZENÍ AKTUÁLNÍHO .ENV SOUBORU</h1>

        <?php if (file_exists($envFile)): ?>
            <div class="file-box">
                <h2>📄 Aktuální .env soubor:</h2>
                <pre><?php
                    $envContent = file_get_contents($envFile);

                    // Zvýraznit DB credentials
                    $envContent = preg_replace('/(DB_HOST=.*)/', '<span class="highlight">$1</span>', $envContent);
                    $envContent = preg_replace('/(DB_NAME=.*)/', '<span class="highlight">$1</span>', $envContent);
                    $envContent = preg_replace('/(DB_USER=.*)/', '<span class="highlight">$1</span>', $envContent);
                    $envContent = preg_replace('/(DB_PASS=.*)/', '<span class="highlight">$1</span>', $envContent);

                    echo $envContent;
                ?></pre>
            </div>

            <?php
            // Parsovat .env
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $envVars = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || $line[0] === '#') continue;
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $envVars[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
                }
            }

            $dbHost = $envVars['DB_HOST'] ?? 'NOT SET';
            $dbName = $envVars['DB_NAME'] ?? 'NOT SET';
            $dbUser = $envVars['DB_USER'] ?? 'NOT SET';
            $dbPass = $envVars['DB_PASS'] ?? 'NOT SET';
            ?>

            <div class="info">
                <strong>📋 Extrahované DB credentials:</strong><br><br>
                <strong>DB_HOST:</strong> <?php echo htmlspecialchars($dbHost); ?><br>
                <strong>DB_NAME:</strong> <?php echo htmlspecialchars($dbName); ?><br>
                <strong>DB_USER:</strong> <?php echo htmlspecialchars($dbUser); ?><br>
                <strong>DB_PASS:</strong> <?php echo str_repeat('•', strlen($dbPass)); ?> (<?php echo strlen($dbPass); ?> znaků)<br>
                <strong>Heslo plain:</strong> <code style="background: #330; padding: 0.2rem 0.5rem;"><?php echo htmlspecialchars($dbPass); ?></code>
            </div>

            <?php
            // Test připojení s těmito credentials
            echo '<h2>🧪 Test připojení s .env credentials:</h2>';

            $testHosts = [$dbHost, '127.0.0.1', 'localhost'];
            $testHosts = array_unique($testHosts);

            foreach ($testHosts as $testHost) {
                if (empty($testHost) || $testHost === 'NOT SET') continue;

                echo '<div class="file-box';

                try {
                    $dsn = "mysql:host={$testHost};dbname={$dbName};charset=utf8mb4";
                    $pdo = new PDO($dsn, $dbUser, $dbPass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 3
                    ]);

                    echo '">';
                    echo '<strong style="color: #0f0;">✓ ÚSPĚCH!</strong> Host: <code>' . htmlspecialchars($testHost) . '</code><br>';

                    $stmt = $pdo->query("SELECT VERSION() as v, DATABASE() as db");
                    $r = $stmt->fetch();
                    echo 'MySQL: ' . htmlspecialchars($r['v']) . '<br>';
                    echo 'Databáze: ' . htmlspecialchars($r['db']);

                    echo '</div>';
                    echo '<div class="info" style="background: #030; border-color: #0f0;">';
                    echo '<strong>🎉 NALEZENO FUNGUJÍCÍ PŘIPOJENÍ!</strong><br><br>';
                    echo 'Tyto credentials FUNGUJÍ:<br>';
                    echo '<pre style="background: #000; border: 1px solid #0f0; margin-top: 0.5rem;">';
                    echo "DB_HOST={$testHost}\n";
                    echo "DB_NAME={$dbName}\n";
                    echo "DB_USER={$dbUser}\n";
                    echo "DB_PASS={$dbPass}";
                    echo '</pre>';
                    echo '</div>';

                    break; // První fungující = hotovo

                } catch (PDOException $e) {
                    echo ' missing">';
                    echo '<strong style="color: #f00;">✗ SELHALO</strong> Host: <code>' . htmlspecialchars($testHost) . '</code><br>';
                    echo '<small style="color: #888;">' . htmlspecialchars($e->getMessage()) . '</small>';
                    echo '</div>';
                }
            }
            ?>

        <?php else: ?>
            <div class="file-box missing">
                <h2>❌ .env soubor NEEXISTUJE!</h2>
                <p>Soubor <?php echo htmlspecialchars($envFile); ?> nebyl nalezen.</p>
            </div>
        <?php endif; ?>

        <?php if (file_exists($envBackup)): ?>
            <h2>💾 Záloha .env.backup:</h2>
            <div class="file-box">
                <pre><?php echo htmlspecialchars(file_get_contents($envBackup)); ?></pre>
            </div>
        <?php endif; ?>

        <div style="margin-top: 3rem; text-align: center;">
            <a href="aktualizuj_databazi.php" style="color: #0f0; text-decoration: underline;">
                → Přejít na aktualizaci .env
            </a>
        </div>
    </div>
</body>
</html>
