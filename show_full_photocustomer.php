<?php
/**
 * Zobrazení CELÉ autentizační logiky z produkčního photocustomer.php
 */
require_once "init.php";

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) die('403');

$file = __DIR__ . '/photocustomer.php';
$lines = file($file);

header('Content-Type: text/plain; charset=utf-8');

echo "=== PHOTOCUSTOMER.PHP - ŘÁDKY 1-50 ===\n\n";

for ($i = 0; $i < min(50, count($lines)); $i++) {
    echo sprintf("%3d: %s", $i + 1, $lines[$i]);
}

echo "\n\n=== MD5: " . md5_file($file) . " ===";
echo "\n=== Velikost: " . filesize($file) . " bytů ===";
echo "\n=== Upraveno: " . date('Y-m-d H:i:s', filemtime($file)) . " ===";
?>