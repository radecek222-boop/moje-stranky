<?php
/**
 * CLEAR PHP OPCACHE
 * Spusť jednou po deployi když se změny neprojeví
 * URL: https://www.wgs-service.cz/clear_opcache.php
 */

// Bezpečnostní kontrola - pouze admin
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    // Pokud není přihlášen, zkusit admin klíč z URL
    $adminKey = $_GET['key'] ?? '';
    $expectedHash = getenv('ADMIN_KEY_HASH');

    if (empty($adminKey) || hash('sha256', $adminKey) !== $expectedHash) {
        http_response_code(403);
        die('PŘÍSTUP ODEPŘEN: Pouze administrátor může vyčistit cache.');
    }
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>PHP Opcache Clear</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2D5016;
            border-bottom: 3px solid #2D5016;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🧹 PHP Opcache Clear</h1>";

// Zkontrolovat jestli je opcache aktivní
if (function_exists('opcache_reset')) {

    // Informace před vyčištěním
    $status = opcache_get_status();

    echo "<div class='info'>";
    echo "<strong>📊 Opcache status PŘED vyčištěním:</strong><br>";
    echo "Cached scripts: " . number_format($status['opcache_statistics']['num_cached_scripts']) . "<br>";
    echo "Memory used: " . number_format($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB<br>";
    echo "Hits: " . number_format($status['opcache_statistics']['hits']) . "<br>";
    echo "Misses: " . number_format($status['opcache_statistics']['misses']) . "<br>";
    echo "</div>";

    // Vyčistit opcache
    $result = opcache_reset();

    if ($result) {
        echo "<div class='success'>";
        echo "<strong>✅ OPCACHE ÚSPĚŠNĚ VYČIŠTĚNA!</strong><br><br>";
        echo "Všechny cachované PHP soubory byly odstraněny z paměti.<br>";
        echo "Další request načte aktuální verze souborů z disku.<br><br>";
        echo "<strong>→ Nyní refreshni stránku protokol.php a zkontroluj CSP header.</strong>";
        echo "</div>";

        // Informace po vyčištění
        sleep(1); // Počkat na reset
        $statusAfter = opcache_get_status();

        echo "<div class='info'>";
        echo "<strong>📊 Opcache status PO vyčištění:</strong><br>";
        echo "Cached scripts: " . number_format($statusAfter['opcache_statistics']['num_cached_scripts']) . "<br>";
        echo "Memory used: " . number_format($statusAfter['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB<br>";
        echo "</div>";

    } else {
        echo "<div class='error'>";
        echo "<strong>❌ CHYBA:</strong><br>";
        echo "opcache_reset() vrátilo false. Možná nemáš oprávnění.";
        echo "</div>";
    }

} else {
    echo "<div class='error'>";
    echo "<strong>⚠️ OPCACHE NENÍ AKTIVNÍ</strong><br>";
    echo "Opcache není nakonfigurován nebo není dostupný.<br>";
    echo "V tom případě problém NENÍ v cache.";
    echo "</div>";
}

// Zkontrolovat CSP header
echo "<div class='info'>";
echo "<strong>🔍 Zkontrolovat CSP header:</strong><br><br>";
echo "1. Otevři Developer Tools → Network<br>";
echo "2. Reload protokol.php<br>";
echo "3. Klikni na request → Headers → Response Headers<br>";
echo "4. Najdi <code>Content-Security-Policy:</code><br>";
echo "5. Měl by obsahovat: <code>frame-src 'self' blob:</code><br>";
echo "</div>";

echo "</div></body></html>";
?>
