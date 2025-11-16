<?php
/**
 * ČISTÝ TEST DATABÁZE - bez WGS kódu
 * Ukáže přesnou chybu připojení
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Credentials z phpMyAdmin
$tests = [
    'Test 1: wgs-servicecz01 user' => [
        'host' => '127.0.0.1',
        'dbname' => 'wgs-servicecz01',
        'user' => 'wgs-servicecz01',
        'pass' => 'p7u.s13mR2018'
    ],
    'Test 2: localhost' => [
        'host' => 'localhost',
        'dbname' => 'wgs-servicecz01',
        'user' => 'wgs-servicecz01',
        'pass' => 'p7u.s13mR2018'
    ],
    'Test 3: root user' => [
        'host' => '127.0.0.1',
        'dbname' => 'wgs-servicecz01',
        'user' => 'root',
        'pass' => 'p7u.s13mR2018'
    ],
    'Test 4: root@localhost' => [
        'host' => 'localhost',
        'dbname' => 'wgs-servicecz01',
        'user' => 'root',
        'pass' => 'p7u.s13mR2018'
    ],
];

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Čistý DB Test | WGS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Consolas, Monaco, monospace;
            background: #000;
            color: #0f0;
            padding: 2rem;
            line-height: 1.6;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        h1 {
            color: #0ff;
            margin-bottom: 2rem;
            font-size: 1.5rem;
        }
        .test {
            background: #111;
            border: 1px solid #333;
            padding: 1.5rem;
            margin: 1rem 0;
        }
        .test h2 {
            color: #ff0;
            font-size: 1rem;
            margin-bottom: 1rem;
        }
        .success {
            border-left: 4px solid #0f0;
        }
        .error {
            border-left: 4px solid #f00;
        }
        .info {
            color: #888;
            font-size: 0.9rem;
        }
        .error-msg {
            color: #f00;
            background: #300;
            padding: 0.5rem;
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }
        .success-msg {
            color: #0f0;
            background: #030;
            padding: 0.5rem;
            margin-top: 0.5rem;
        }
        pre {
            background: #000;
            border: 1px solid #333;
            padding: 1rem;
            overflow-x: auto;
            font-size: 0.85rem;
            margin-top: 1rem;
        }
        .php-info {
            background: #111;
            border: 1px solid #333;
            padding: 1rem;
            margin: 2rem 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 ČISTÝ TEST DATABÁZOVÉHO PŘIPOJENÍ</h1>

        <div class="php-info">
            <strong style="color: #0ff;">PHP Konfigurace:</strong><br>
            PHP verze: <?php echo PHP_VERSION; ?><br>
            PDO MySQL: <?php echo extension_loaded('pdo_mysql') ? '✓ NAINSTALOVÁNO' : '✗ CHYBÍ'; ?><br>
            <?php if (extension_loaded('pdo_mysql')): ?>
            PDO Drivers: <?php echo implode(', ', PDO::getAvailableDrivers()); ?><br>
            <?php endif; ?>
        </div>

        <?php if (!extension_loaded('pdo_mysql')): ?>
            <div class="test error">
                <h2>❌ KRITICKÁ CHYBA</h2>
                <div class="error-msg">
                    PDO MySQL extension není nainstalována!<br>
                    Kontaktuj hosting support - MySQL není dostupný přes PHP.
                </div>
            </div>
        <?php else: ?>

            <?php foreach ($tests as $testName => $config): ?>
                <div class="test <?php
                    $success = false;
                    $errorMsg = '';
                    $details = '';

                    try {
                        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";

                        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_TIMEOUT => 3
                        ]);

                        // Test SELECT
                        $stmt = $pdo->query("SELECT VERSION() as version, DATABASE() as db_name");
                        $result = $stmt->fetch();

                        // Test tabulky
                        $stmt = $pdo->query("SHOW TABLES");
                        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

                        $success = true;
                        $details = "MySQL verze: " . $result['version'] . "\n";
                        $details .= "Databáze: " . $result['db_name'] . "\n";
                        $details .= "Počet tabulek: " . count($tables) . "\n";
                        $details .= "Tabulky: " . implode(', ', array_slice($tables, 0, 5));
                        if (count($tables) > 5) {
                            $details .= ", ... (+" . (count($tables) - 5) . " dalších)";
                        }

                        // Zkontrolovat wgs_reklamace
                        if (in_array('wgs_reklamace', $tables)) {
                            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM wgs_reklamace");
                            $count = $stmt->fetch();
                            $details .= "\n✓ wgs_reklamace: " . $count['cnt'] . " záznamů";
                        }

                    } catch (PDOException $e) {
                        $errorMsg = $e->getMessage();
                        $success = false;
                    }

                    echo $success ? 'success' : 'error';
                ?>">
                    <h2><?php echo $success ? '✓' : '✗'; ?> <?php echo htmlspecialchars($testName); ?></h2>

                    <div class="info">
                        Host: <strong><?php echo htmlspecialchars($config['host']); ?></strong> |
                        User: <strong><?php echo htmlspecialchars($config['user']); ?></strong> |
                        DB: <strong><?php echo htmlspecialchars($config['dbname']); ?></strong>
                    </div>

                    <?php if ($success): ?>
                        <div class="success-msg">
                            ✓ PŘIPOJENÍ ÚSPĚŠNÉ!
                        </div>
                        <pre><?php echo htmlspecialchars($details); ?></pre>

                        <div style="margin-top: 1rem; padding: 1rem; background: #030; border: 1px solid #0f0;">
                            <strong style="color: #0f0;">🎉 TOTO FUNGUJE! Použij tyto údaje:</strong><br><br>
                            DB_HOST=<?php echo $config['host']; ?><br>
                            DB_NAME=<?php echo $config['dbname']; ?><br>
                            DB_USER=<?php echo $config['user']; ?><br>
                            DB_PASS=<?php echo $config['pass']; ?>
                        </div>

                    <?php else: ?>
                        <div class="error-msg">
                            <?php
                            echo "❌ " . htmlspecialchars($errorMsg);

                            // Rozebrat chybu a dát tip
                            if (strpos($errorMsg, 'Access denied') !== false) {
                                echo "<br><br><strong>→ Špatné uživatelské jméno nebo heslo</strong>";
                                echo "<br>Zkontroluj v phpMyAdmin → Oprávnění";
                            } elseif (strpos($errorMsg, 'No such file or directory') !== false) {
                                echo "<br><br><strong>→ Unix socket problém</strong>";
                                echo "<br>Zkus jiný host (např. 127.0.0.1 místo localhost)";
                            } elseif (strpos($errorMsg, 'Unknown database') !== false) {
                                echo "<br><br><strong>→ Databáze neexistuje</strong>";
                                echo "<br>Zkontroluj přesný název v phpMyAdmin";
                            } elseif (strpos($errorMsg, "Can't connect") !== false) {
                                echo "<br><br><strong>→ MySQL server není dostupný</strong>";
                                echo "<br>Možná špatný host nebo MySQL není spuštěný";
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>

        <div class="php-info" style="margin-top: 3rem;">
            <strong style="color: #0ff;">📋 Další informace:</strong><br>
            <pre style="margin-top: 0.5rem; font-size: 0.8rem;">
Server Software: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'; ?>

Document Root: <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'unknown'; ?>

PHP SAPI: <?php echo php_sapi_name(); ?>

MySQL Default Socket: <?php echo ini_get('pdo_mysql.default_socket') ?: 'not set'; ?>

MySQLi Default Host: <?php echo ini_get('mysqli.default_host') ?: 'not set'; ?>
            </pre>
        </div>

        <div style="text-align: center; margin-top: 3rem;">
            <a href="aktualizuj_databazi.php" style="color: #0f0; text-decoration: underline;">
                → Přejít na aktualizaci .env (pokud něco funguje)
            </a>
        </div>
    </div>
</body>
</html>
