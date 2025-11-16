<?php
/**
 * TEST DATABÁZOVÉHO PŘIPOJENÍ
 * Zkusí všechny možné varianty DB hostu a najde fungující
 */

// Credentials z phpMyAdmin
$dbName = 'wgs-servicecz01';
$dbUser = 'wgs-servicecz01';
$dbPass = 'p7u.s13mR2018';

// Všechny možné varianty hostu které zkusíme
$hostsToTry = [
    '127.0.0.1',
    'localhost',
    '127.0.0.1:3306',
    'localhost:3306',
    'mysql',
    'mysql.wgs-service.cz',
    'mysql.server',
    'sql.wgs-service.cz',
    'db.wgs-service.cz',
    // Unix sockets (pokud existují)
    'localhost:/tmp/mysql.sock',
    'localhost:/var/run/mysqld/mysqld.sock',
    'localhost:/var/lib/mysql/mysql.sock',
];

// Také zkusíme načíst z php.ini
$iniValues = [
    ini_get('mysqli.default_host'),
    ini_get('mysql.default_host'),
    ini_get('pdo_mysql.default_socket'),
];

foreach ($iniValues as $iniHost) {
    if ($iniHost && !in_array($iniHost, $hostsToTry)) {
        $hostsToTry[] = $iniHost;
    }
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test DB připojení | WGS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #fff;
            color: #000;
            padding: 2rem;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            border: 2px solid #000;
        }
        .header {
            background: #000;
            color: #fff;
            padding: 2rem;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.5rem;
        }
        .content {
            padding: 2rem;
        }
        .test-result {
            margin: 1rem 0;
            padding: 1rem;
            border-left: 4px solid #ddd;
            background: #f5f5f5;
            font-family: monospace;
            font-size: 0.85rem;
        }
        .test-result.success {
            border-left-color: #006600;
            background: #f0fff0;
        }
        .test-result.error {
            border-left-color: #cc0000;
            background: #fff0f0;
        }
        .success-box {
            background: #006600;
            color: #fff;
            padding: 2rem;
            margin: 2rem 0;
            text-align: center;
        }
        .success-box h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .success-box code {
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1rem;
            font-size: 1.2rem;
            display: block;
            margin: 1rem auto;
            max-width: fit-content;
        }
        .info {
            background: #f0f0f0;
            padding: 1rem;
            margin: 1rem 0;
            font-size: 0.9rem;
        }
        pre {
            background: #000;
            color: #0f0;
            padding: 1rem;
            overflow-x: auto;
            font-size: 0.8rem;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Test databázového připojení</h1>
            <p style="font-size: 0.85rem; opacity: 0.8;">Automatické hledání správného DB hostu</p>
        </div>

        <div class="content">
            <div class="info">
                <strong>Testované credentials:</strong><br>
                • DB Name: <code><?php echo htmlspecialchars($dbName); ?></code><br>
                • DB User: <code><?php echo htmlspecialchars($dbUser); ?></code><br>
                • DB Pass: <code>••••••••••••</code> (<?php echo strlen($dbPass); ?> znaků)
            </div>

            <h2 style="margin: 2rem 0 1rem 0; font-size: 1.2rem; border-bottom: 2px solid #000; padding-bottom: 0.5rem;">Testování všech možných hostů...</h2>

            <?php
            $workingHosts = [];
            $failedHosts = [];

            foreach ($hostsToTry as $host) {
                if (empty($host)) continue;

                echo "<div class='test-result";

                try {
                    $startTime = microtime(true);

                    $dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";
                    $pdo = new PDO($dsn, $dbUser, $dbPass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_TIMEOUT => 3
                    ]);

                    $elapsed = round((microtime(true) - $startTime) * 1000, 2);

                    // Test SELECT
                    $stmt = $pdo->query("SELECT 1");
                    $result = $stmt->fetch();

                    // Test tabulky
                    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_reklamace'");
                    $tableExists = $stmt->rowCount() > 0;

                    echo " success'>";
                    echo "✓ <strong>ÚSPĚCH</strong>: Host '<strong>{$host}</strong>' funguje! ";
                    echo "({$elapsed}ms)";
                    if ($tableExists) {
                        echo " | Tabulka wgs_reklamace ✓";
                    }
                    echo "</div>";

                    $workingHosts[] = [
                        'host' => $host,
                        'time' => $elapsed,
                        'table_exists' => $tableExists
                    ];

                } catch (PDOException $e) {
                    echo " error'>";
                    echo "✗ <strong>SELHALO</strong>: Host '{$host}'<br>";
                    echo "<small style='opacity: 0.7;'>Chyba: " . htmlspecialchars(substr($e->getMessage(), 0, 150)) . "</small>";
                    echo "</div>";

                    $failedHosts[] = [
                        'host' => $host,
                        'error' => $e->getMessage()
                    ];
                }
            }
            ?>

            <?php if (!empty($workingHosts)): ?>
                <?php
                // Seřadit podle rychlosti
                usort($workingHosts, function($a, $b) {
                    return $a['time'] <=> $b['time'];
                });
                $bestHost = $workingHosts[0];
                ?>

                <div class="success-box">
                    <h2>🎉 NALEZEN FUNGUJÍCÍ HOST!</h2>
                    <p>Použij tento host ve svém .env souboru:</p>
                    <code><?php echo htmlspecialchars($bestHost['host']); ?></code>
                    <p style="margin-top: 1rem; font-size: 0.9rem; opacity: 0.9;">
                        Rychlost připojení: <?php echo $bestHost['time']; ?>ms
                        <?php if ($bestHost['table_exists']): ?>
                        <br>✓ Tabulka wgs_reklamace existuje
                        <?php endif; ?>
                    </p>
                </div>

                <h3 style="margin: 2rem 0 1rem 0; font-size: 1.1rem;">Všechny fungující hosty (seřazeno podle rychlosti):</h3>
                <?php foreach ($workingHosts as $idx => $host): ?>
                    <div class="test-result success">
                        <?php echo ($idx + 1); ?>. <strong><?php echo htmlspecialchars($host['host']); ?></strong>
                        - <?php echo $host['time']; ?>ms
                        <?php if ($host['table_exists']): ?>✓ wgs_reklamace<?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="info" style="background: #f0fff0; border-left: 4px solid #006600; margin: 2rem 0;">
                    <h3 style="margin-bottom: 1rem; font-size: 1rem;">📋 Co teď udělat:</h3>
                    <ol style="margin-left: 1.5rem; line-height: 1.8;">
                        <li>Otevři <strong>aktualizuj_databazi.php</strong></li>
                        <li>Do pole "DB Host" zadej: <code style="background: #fff; padding: 0.2rem 0.5rem;"><?php echo htmlspecialchars($bestHost['host']); ?></code></li>
                        <li>Ostatní údaje nech jak jsou</li>
                        <li>Klikni "AKTUALIZOVAT .ENV SOUBOR"</li>
                    </ol>
                </div>

                <pre>
# Přidej do .env souboru:
DB_HOST=<?php echo $bestHost['host']; ?>

DB_NAME=<?php echo $dbName; ?>

DB_USER=<?php echo $dbUser; ?>

DB_PASS=<?php echo $dbPass; ?>

                </pre>

            <?php else: ?>
                <div style="background: #fff0f0; border-left: 4px solid #cc0000; padding: 2rem; margin: 2rem 0;">
                    <h2 style="color: #cc0000; margin-bottom: 1rem;">❌ Žádný host nefunguje</h2>
                    <p>Ani jeden z testovaných hostů se nepodařilo připojit k databázi.</p>

                    <h3 style="margin: 1.5rem 0 1rem 0; font-size: 1rem;">💡 Zkontroluj:</h3>
                    <ol style="margin-left: 1.5rem; line-height: 1.8;">
                        <li>Je heslo <strong>opravdu</strong> <code><?php echo htmlspecialchars($dbPass); ?></code>?</li>
                        <li>Je uživatel <strong>opravdu</strong> <code><?php echo htmlspecialchars($dbUser); ?></code>?</li>
                        <li>V phpMyAdmin → Oprávnění zkontroluj přesný název uživatele</li>
                        <li>Kontaktuj hosting support - možná MySQL není dostupný přes PHP</li>
                    </ol>

                    <h3 style="margin: 1.5rem 0 1rem 0; font-size: 1rem;">Testované hosty:</h3>
                    <pre style="background: #000; color: #f00; max-height: 300px; overflow-y: auto;">
<?php foreach ($failedHosts as $failed): ?>
Host: <?php echo $failed['host']; ?>

Error: <?php echo substr($failed['error'], 0, 200); ?>


<?php endforeach; ?>
                    </pre>
                </div>
            <?php endif; ?>

            <div style="margin-top: 3rem; text-align: center;">
                <a href="aktualizuj_databazi.php" style="background: #000; color: #fff; padding: 1rem 2rem; text-decoration: none; display: inline-block; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em;">
                    → PŘEJÍT NA AKTUALIZACI .ENV
                </a>
            </div>
        </div>
    </div>
</body>
</html>
