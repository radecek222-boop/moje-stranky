<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Definované konstanty a funkce</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        h1 { color: #4ec9b0; }
        pre { background: #2d2d2d; padding: 15px; border-left: 3px solid #4ec9b0; overflow-x: auto; }
        .error { color: #f48771; background: #2d1d1d; padding: 15px; border-left: 3px solid #f48771; }
        table { background: #2d2d2d; border-collapse: collapse; width: 100%; margin: 20px 0; }
        th { background: #3c3c3c; color: #4ec9b0; padding: 10px; text-align: left; border: 1px solid #555; }
        td { padding: 8px; border: 1px solid #555; }
        .key { color: #ce9178; font-weight: bold; }
    </style>
</head>
<body>

<h1>🔍 DEFINOVANÉ KONSTANTY A FUNKCE</h1>

<?php
// Nejdřív ukázat co je PŘED načtením init.php
echo "<h2>PŘED načtením init.php:</h2>";
echo "<pre>";
echo "DB konstanty: " . (defined('DB_NAME') ? 'ANO' : 'NE') . "\n";
echo "getDbConnection(): " . (function_exists('getDbConnection') ? 'ANO' : 'NE') . "\n";
echo "</pre>";

// Nyní načíst init.php (který by měl načíst config)
echo "<h2>Načítám init.php...</h2>";
try {
    require_once __DIR__ . '/init.php';
    echo "<p style='color:#4ec9b0;'>✅ init.php načten úspěšně</p>";
} catch (Exception $e) {
    echo "<div class='error'>❌ CHYBA: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

// Po načtení init.php
echo "<h2>PO načtení init.php:</h2>";
echo "<pre>";
echo "DB konstanty: " . (defined('DB_NAME') ? 'ANO' : 'NE') . "\n";
echo "getDbConnection(): " . (function_exists('getDbConnection') ? 'ANO' : 'NE') . "\n";
echo "</pre>";

// Pokud nejsou konstanty, zkusit načíst config.php
if (!defined('DB_NAME')) {
    echo "<h2>Načítám config/config.php přímo...</h2>";
    try {
        // Nejdřív musím definovat chybějící funkce
        if (!function_exists('getEnvValue')) {
            function getEnvValue($key, $default = null) {
                return $_SERVER[$key] ?? getenv($key) ?: $default;
            }
        }
        if (!function_exists('requireEnvValue')) {
            function requireEnvValue($key, $errorMsg = null) {
                $value = getEnvValue($key);
                if ($value === null || $value === false || $value === '') {
                    if ($errorMsg) {
                        die($errorMsg);
                    }
                    die("Required environment variable {$key} is not set!");
                }
                return $value;
            }
        }

        // Definovat základní konstanty pokud ještě nejsou
        if (!defined('BASE_PATH')) define('BASE_PATH', __DIR__);
        if (!defined('LOGS_PATH')) define('LOGS_PATH', __DIR__ . '/logs');
        if (!defined('CONFIG_PATH')) define('CONFIG_PATH', __DIR__ . '/config');

        // Hardcodovat DB konstanty pro test
        if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
        if (!defined('DB_NAME')) define('DB_NAME', 'wgs-servicecz01');
        if (!defined('DB_USER')) define('DB_USER', 'root');
        if (!defined('DB_PASS')) define('DB_PASS', '');

        require_once __DIR__ . '/config/config.php';
        echo "<p style='color:#4ec9b0;'>✅ config/config.php načten úspěšně</p>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ CHYBA: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
}

// Zobrazit všechny DB konstanty
echo "<h2>DB KONSTANTY:</h2>";
echo "<table>";
echo "<tr><th>Konstanta</th><th>Hodnota</th><th>Definováno?</th></tr>";

$dbConstants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
foreach ($dbConstants as $const) {
    $isDefined = defined($const);
    $value = $isDefined ? constant($const) : 'N/A';

    // Maskovat heslo
    if ($const === 'DB_PASS' && $isDefined) {
        $value = str_repeat('*', strlen($value));
    }

    echo "<tr>";
    echo "<td class='key'>{$const}</td>";
    echo "<td>" . htmlspecialchars($value) . "</td>";
    echo "<td>" . ($isDefined ? '✅' : '❌') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Pokusit se připojit k databázi
if (function_exists('getDbConnection')) {
    echo "<h2>TEST PŘIPOJENÍ K DATABÁZI:</h2>";
    try {
        $pdo = getDbConnection();
        echo "<p style='color:#4ec9b0;'>✅ Připojení úspěšné!</p>";

        // Zjistit název databáze
        $stmt = $pdo->query("SELECT DATABASE() as db_name");
        $dbName = $stmt->fetch(PDO::FETCH_ASSOC)['db_name'];
        echo "<p>Databáze: <strong style='color:#ce9178;font-size:18px;'>{$dbName}</strong></p>";

        // Zjistit počet záznamů
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_reklamace");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p>Počet reklamací: <strong style='color:#4ec9b0;'>{$count}</strong></p>";

    } catch (Exception $e) {
        echo "<div class='error'>❌ CHYBA PŘIPOJENÍ: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Zobrazit načtené soubory
echo "<h2>NAČTENÉ SOUBORY:</h2>";
echo "<pre>";
$files = get_included_files();
foreach ($files as $file) {
    // Zkrátit cestu pro čitelnost
    $shortPath = str_replace('/home/www/wgs-service.cz/www/wgs-service.cz/www/', '', $file);
    echo htmlspecialchars($shortPath) . "\n";
}
echo "</pre>";
?>

</body>
</html>
