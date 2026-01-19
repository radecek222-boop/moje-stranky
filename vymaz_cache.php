<?php
/**
 * Vymazání PHP opcache
 */

// Bezpečnostní kontrola - pouze pro admina nebo z CLI
$isAdmin = (php_sapi_name() === 'cli') || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);

if (!$isAdmin) {
    session_start();
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        die("PŘÍSTUP ODEPŘEN");
    }
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Vymazání cache</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        h1 { color: #333; }
    </style>
</head>
<body>
<h1>Vymazání cache</h1>";

// Vymazat opcache
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "<div class='success'><strong>✓ PHP OPcache vymazána</strong></div>";
    } else {
        echo "<div class='info'>OPcache reset selhal nebo není aktivní</div>";
    }
} else {
    echo "<div class='info'>OPcache není dostupný</div>";
}

// Vymazat realpath cache
clearstatcache(true);
echo "<div class='success'><strong>✓ Realpath cache vymazána</strong></div>";

echo "<p><a href='/statistiky.php' style='display: inline-block; padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 5px;'>Otevřít statistiky</a></p>";

echo "</body></html>";
?>
