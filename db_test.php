<?php
require_once __DIR__ . '/init.php';
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Přístup odepřen');
}

header('Content-Type: text/plain; charset=utf-8');

// === TEST 1: DB připojení ===
$t = microtime(true);
$pdo = getDbConnection();
$cas1 = round((microtime(true) - $t) * 1000, 2);

$t = microtime(true);
getDbConnection();
$cas2 = round((microtime(true) - $t) * 1000, 3);

$t = microtime(true);
$pdo->query("SELECT 1");
$casDotaz = round((microtime(true) - $t) * 1000, 2);

echo "=== DB PŘIPOJENÍ ===\n";
echo "1. volání:          {$cas1} ms\n";
echo "2. volání (cache):  {$cas2} ms\n";
echo "SELECT 1:           {$casDotaz} ms\n";
echo "Persistent:         " . ($pdo->getAttribute(PDO::ATTR_PERSISTENT) ? 'ANO' : 'NE') . "\n";
echo "DB host:            " . DB_HOST . "\n\n";

// === TEST 2: Session ===
echo "=== SESSION ===\n";
$sessionStav = session_status();
echo "Status:             " . ($sessionStav === PHP_SESSION_ACTIVE ? 'AKTIVNÍ' : 'NEAKTIVNÍ') . "\n";
echo "tenant_id v session:" . ($_SESSION['tenant_id'] ?? 'CHYBÍ') . "\n";
echo "tenant_slug:        " . ($_SESSION['tenant_slug'] ?? 'CHYBÍ') . "\n\n";

// === TEST 3: Skutečný dotaz load.php simulace ===
echo "=== SIMULACE load.php DOTAZU ===\n";
$t = microtime(true);
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM wgs_reklamace r
");
$stmt->execute();
$pocet = $stmt->fetch()['total'];
$casCount = round((microtime(true) - $t) * 1000, 2);

$t = microtime(true);
$stmt = $pdo->prepare("
    SELECT r.id, r.reklamace_id, r.jmeno, r.stav, r.created_at
    FROM wgs_reklamace r
    ORDER BY r.created_at DESC
    LIMIT 20
");
$stmt->execute();
$zaznamy = $stmt->fetchAll();
$casSelect = round((microtime(true) - $t) * 1000, 2);

echo "COUNT(*):           {$casCount} ms  ({$pocet} záznamů celkem)\n";
echo "SELECT TOP 20:      {$casSelect} ms\n\n";

// === TEST 4: TenantManager ===
echo "=== TENANT MANAGER ===\n";
$t = microtime(true);
try {
    $tid = TenantManager::getInstance()->getTenantId();
    $slug = TenantManager::getInstance()->getSlug();
    $casTenant = round((microtime(true) - $t) * 1000, 2);
    echo "tenant_id:          {$tid}\n";
    echo "slug:               {$slug}\n";
    echo "Čas inicializace:   {$casTenant} ms\n";
} catch (Exception $e) {
    echo "CHYBA: " . $e->getMessage() . "\n";
}
echo "\n";

// === CELKOVÝ ČAS ===
$celkem = round(($cas1 + $cas2 + $casDotaz + $casCount + $casSelect) , 2);
echo "=== ZÁVĚR ===\n";
echo "DB latence:         " . ($cas1 < 5 ? "OK (persistent funguje)" : "POMALÉ (>5ms)") . "\n";
echo "Indexy (COUNT):     " . ($casCount < 10 ? "OK" : "POMALÉ – zkontrolujte indexy") . "\n";
echo "Indexy (SELECT 20): " . ($casSelect < 50 ? "OK" : "POMALÉ – zkontrolujte indexy") . "\n";
