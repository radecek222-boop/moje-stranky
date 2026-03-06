<?php
// Pouze pro admina
require_once __DIR__ . '/init.php';
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Přístup odepřen');
}

$zacatek = microtime(true);
$pdo = getDbConnection();
$cas1 = round((microtime(true) - $zacatek) * 1000, 2);

// Druhé volání – mělo by být 0ms (cached static)
$zacatek2 = microtime(true);
$pdo2 = getDbConnection();
$cas2 = round((microtime(true) - $zacatek2) * 1000, 3);

// Test dotazu
$zacatekDotaz = microtime(true);
$stmt = $pdo->query("SELECT 1");
$casDotaz = round((microtime(true) - $zacatekDotaz) * 1000, 2);

// Persistent info
$persistentni = $pdo->getAttribute(PDO::ATTR_PERSISTENT) ? 'ANO' : 'NE';

header('Content-Type: text/plain; charset=utf-8');
echo "=== TEST DB PŘIPOJENÍ ===\n\n";
echo "1. volání getDbConnection():  {$cas1} ms\n";
echo "2. volání getDbConnection():  {$cas2} ms  (mělo být ~0)\n";
echo "Dotaz SELECT 1:               {$casDotaz} ms\n";
echo "Persistentní spojení:         {$persistentni}\n";
echo "DB host:                      " . DB_HOST . "\n";
echo "\nPokud 1. volání > 50ms → DNS/TCP problém\n";
echo "Pokud 1. volání < 5ms  → persistent connection funguje\n";
