<?php
/**
 * DIAGNOSTIKA: Proc load.php vraci 500?
 *
 * Tento skript krok za krokem testuje vsechny soubory a funkce,
 * ktere load.php pouziva, a ukaze PRESNOU pricinu chyby.
 */

// ZAPNOUT ZOBRAZENI VSECH CHYB
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Custom error handler pro zachyceni VSECH chyb
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "<div style='background:#ffcccc;padding:10px;margin:5px 0;border:1px solid red;'>";
    echo "<strong>PHP Error [$errno]:</strong> $errstr<br>";
    echo "<strong>File:</strong> $errfile:$errline";
    echo "</div>";
    return true;
});

// Exception handler
set_exception_handler(function($e) {
    echo "<div style='background:#ffcccc;padding:10px;margin:5px 0;border:2px solid darkred;'>";
    echo "<strong>EXCEPTION:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . ":" . $e->getLine() . "<br>";
    echo "<strong>Trace:</strong><pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
});

// Shutdown handler pro fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "<div style='background:#ff0000;color:white;padding:15px;margin:10px 0;'>";
        echo "<strong>FATAL ERROR:</strong> " . $error['message'] . "<br>";
        echo "<strong>File:</strong> " . $error['file'] . ":" . $error['line'];
        echo "</div>";
    }
});

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Diagnostika load.php</title>";
echo "<style>body{font-family:monospace;max-width:1200px;margin:20px auto;padding:20px;background:#f5f5f5;}";
echo ".ok{background:#d4edda;padding:8px;margin:5px 0;border-left:4px solid green;}";
echo ".fail{background:#f8d7da;padding:8px;margin:5px 0;border-left:4px solid red;}";
echo ".info{background:#cce5ff;padding:8px;margin:5px 0;border-left:4px solid blue;}";
echo ".warn{background:#fff3cd;padding:8px;margin:5px 0;border-left:4px solid orange;}";
echo "h2{margin-top:30px;border-bottom:2px solid #333;padding-bottom:10px;}";
echo "pre{background:#1a1a1a;color:#eee;padding:15px;overflow-x:auto;}</style></head><body>";

echo "<h1>Diagnostika: Proc load.php vraci HTTP 500?</h1>";

// ============================================
// KROK 1: Test include souboru
// ============================================
echo "<h2>KROK 1: Include soubory</h2>";

$soubory = [
    'init.php' => __DIR__ . '/init.php',
    'env_loader.php' => __DIR__ . '/includes/env_loader.php',
    'config.php' => __DIR__ . '/config/config.php',
    'csrf_helper.php' => __DIR__ . '/includes/csrf_helper.php',
    'db_metadata.php' => __DIR__ . '/includes/db_metadata.php',
    'api_response.php' => __DIR__ . '/includes/api_response.php',
    'WebPush.php' => __DIR__ . '/includes/WebPush.php',
];

foreach ($soubory as $nazev => $cesta) {
    if (file_exists($cesta)) {
        echo "<div class='ok'>$nazev - EXISTUJE ($cesta)</div>";
    } else {
        echo "<div class='fail'>$nazev - CHYBI! ($cesta)</div>";
    }
}

// ============================================
// KROK 2: Postupne nacitani souboru
// ============================================
echo "<h2>KROK 2: Postupne nacitani souboru</h2>";

echo "<div class='info'>Nacitam init.php...</div>";
try {
    require_once __DIR__ . '/init.php';
    echo "<div class='ok'>init.php - NACTEN USPESNE</div>";
} catch (Throwable $e) {
    echo "<div class='fail'>init.php - CHYBA: " . $e->getMessage() . "</div>";
}

echo "<div class='info'>Nacitam db_metadata.php...</div>";
try {
    require_once __DIR__ . '/includes/db_metadata.php';
    echo "<div class='ok'>db_metadata.php - NACTEN USPESNE</div>";
} catch (Throwable $e) {
    echo "<div class='fail'>db_metadata.php - CHYBA: " . $e->getMessage() . "</div>";
}

// ============================================
// KROK 3: Test funkci
// ============================================
echo "<h2>KROK 3: Test existence funkci</h2>";

$funkce = [
    'generateCSRFToken',
    'validateCSRFToken',
    'getDbConnection',
    'db_get_table_columns',
    'db_table_has_column',
    'db_table_exists',
    'getEnvValue',
    'sanitizeInput',
];

foreach ($funkce as $f) {
    if (function_exists($f)) {
        echo "<div class='ok'>$f() - EXISTUJE</div>";
    } else {
        echo "<div class='fail'>$f() - NEEXISTUJE!</div>";
    }
}

// ============================================
// KROK 4: Test databazoveho pripojeni
// ============================================
echo "<h2>KROK 4: Test databazoveho pripojeni</h2>";

try {
    echo "<div class='info'>Vytvarim DB pripojeni...</div>";
    $pdo = getDbConnection();
    echo "<div class='ok'>DB pripojeni - USPESNE</div>";

    // Test dotazu
    echo "<div class='info'>Testuji SELECT 1...</div>";
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<div class='ok'>SELECT 1 - USPESNE (vysledek: " . json_encode($result) . ")</div>";

} catch (Throwable $e) {
    echo "<div class='fail'>DB pripojeni - CHYBA: " . $e->getMessage() . "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// ============================================
// KROK 5: Test tabulky wgs_reklamace
// ============================================
echo "<h2>KROK 5: Test tabulky wgs_reklamace</h2>";

try {
    echo "<div class='info'>Kontroluji existenci tabulky wgs_reklamace...</div>";

    if (function_exists('db_table_exists')) {
        $existuje = db_table_exists($pdo, 'wgs_reklamace');
        if ($existuje) {
            echo "<div class='ok'>Tabulka wgs_reklamace - EXISTUJE</div>";
        } else {
            echo "<div class='fail'>Tabulka wgs_reklamace - NEEXISTUJE!</div>";
        }
    }

    echo "<div class='info'>Nacitam sloupce tabulky wgs_reklamace...</div>";
    $columns = db_get_table_columns($pdo, 'wgs_reklamace');
    echo "<div class='ok'>Sloupce nacteny: " . count($columns) . " sloupcu</div>";
    echo "<pre>" . implode(", ", array_slice($columns, 0, 15)) . (count($columns) > 15 ? '...' : '') . "</pre>";

} catch (Throwable $e) {
    echo "<div class='fail'>Test tabulky - CHYBA: " . $e->getMessage() . "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// ============================================
// KROK 6: Test session
// ============================================
echo "<h2>KROK 6: Test session</h2>";

echo "<div class='info'>Session status: " . session_status() . " (1=disabled, 2=active)</div>";
echo "<div class='info'>user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NENI NASTAVEN') . "</div>";
echo "<div class='info'>is_admin: " . (isset($_SESSION['is_admin']) ? ($_SESSION['is_admin'] ? 'true' : 'false') : 'NENI NASTAVEN') . "</div>";
echo "<div class='info'>role: " . ($_SESSION['role'] ?? 'NENI NASTAVEN') . "</div>";

// ============================================
// KROK 7: Simulace load.php (bez session kontroly)
// ============================================
echo "<h2>KROK 7: Simulace dotazu load.php</h2>";

try {
    echo "<div class='info'>Pripravuji SQL dotaz jako load.php...</div>";

    $columns = db_get_table_columns($pdo, 'wgs_reklamace');

    // Bez filtru - vsechny zaznamy
    $sql = "
        SELECT
            r.*,
            r.id as claim_id,
            u.name as created_by_name,
            t.name as technik_jmeno,
            t.email as technik_email,
            t.phone as technik_telefon
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.id
        LEFT JOIN wgs_users t ON r.assigned_to = t.id
        ORDER BY r.created_at DESC
        LIMIT 5
    ";

    echo "<pre>$sql</pre>";

    echo "<div class='info'>Spoustim dotaz...</div>";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='ok'>Dotaz uspesny! Nacteno " . count($reklamace) . " zaznamu.</div>";

    if (count($reklamace) > 0) {
        echo "<div class='info'>Prvni zaznam (zkraceno):</div>";
        $prvni = $reklamace[0];
        echo "<pre>" . json_encode(array_slice($prvni, 0, 8), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }

} catch (Throwable $e) {
    echo "<div class='fail'>Simulace load.php - CHYBA: " . $e->getMessage() . "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// ============================================
// KROK 8: Test WebPush (muze zpusobit 500)
// ============================================
echo "<h2>KROK 8: Test WebPush knihovny</h2>";

try {
    echo "<div class='info'>Kontroluji vendor/autoload.php...</div>";
    $autoloadPath = __DIR__ . '/vendor/autoload.php';

    if (file_exists($autoloadPath)) {
        echo "<div class='ok'>vendor/autoload.php - EXISTUJE</div>";

        echo "<div class='info'>Nacitam vendor/autoload.php...</div>";
        require_once $autoloadPath;
        echo "<div class='ok'>vendor/autoload.php - NACTEN</div>";

        echo "<div class='info'>Kontroluji tridu Minishlink\\WebPush\\WebPush...</div>";
        if (class_exists('Minishlink\\WebPush\\WebPush')) {
            echo "<div class='ok'>Minishlink\\WebPush\\WebPush - EXISTUJE</div>";
        } else {
            echo "<div class='fail'>Minishlink\\WebPush\\WebPush - NEEXISTUJE!</div>";
        }
    } else {
        echo "<div class='fail'>vendor/autoload.php - NEEXISTUJE!</div>";
    }

    echo "<div class='info'>Nacitam includes/WebPush.php...</div>";
    require_once __DIR__ . '/includes/WebPush.php';
    echo "<div class='ok'>includes/WebPush.php - NACTEN</div>";

    if (class_exists('WGSWebPush')) {
        echo "<div class='ok'>WGSWebPush trida - EXISTUJE</div>";
    } else {
        echo "<div class='fail'>WGSWebPush trida - NEEXISTUJE!</div>";
    }

} catch (Throwable $e) {
    echo "<div class='fail'>WebPush test - CHYBA: " . $e->getMessage() . "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// ============================================
// KROK 9: PHP error log
// ============================================
echo "<h2>KROK 9: Posledni chyby z PHP error logu</h2>";

$logPath = __DIR__ . '/logs/php_errors.log';
if (file_exists($logPath)) {
    $log = file_get_contents($logPath);
    $lines = explode("\n", $log);
    $last20 = array_slice($lines, -20);
    echo "<pre style='max-height:300px;overflow-y:auto;'>" . htmlspecialchars(implode("\n", $last20)) . "</pre>";
} else {
    echo "<div class='warn'>Log soubor neexistuje: $logPath</div>";
}

// ============================================
// ZAVER
// ============================================
echo "<h2>ZAVER</h2>";
echo "<div class='info'>";
echo "<strong>Pokud vsechny testy prosly:</strong><br>";
echo "- Problem je pravdepodobne v SESSION autentizaci (uzivatel neni prihlasen)<br>";
echo "- Zkuste se prihlasit a pak otevrit seznam.php<br>";
echo "- Nebo je PHP opcode cache stale aktivni - pockejte 5 minut nebo restartujte PHP-FPM";
echo "</div>";

echo "<p style='margin-top:30px;'><a href='/admin.php' style='padding:10px 20px;background:#333;color:white;text-decoration:none;border-radius:5px;'>Zpet do Admin</a></p>";

echo "</body></html>";
