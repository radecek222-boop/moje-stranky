<?php
/**
 * Reaktivace push subscriptions po chybe
 */
require_once __DIR__ . '/init.php';

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN");
}

$pdo = getDbConnection();

if (isset($_GET['execute'])) {
    $stmt = $pdo->exec("UPDATE wgs_push_subscriptions SET aktivni = 1, pocet_chyb = 0");
    echo "OK: Subscriptions reaktivovany. <a href='/test_push_notifikace.php?send=1'>Zkusit test</a>";
} else {
    $stmt = $pdo->query("SELECT COUNT(*) as c FROM wgs_push_subscriptions WHERE aktivni = 0");
    $count = $stmt->fetch()['c'];
    echo "Neaktivnich subscriptions: $count<br><br>";
    echo "<a href='?execute=1'>Reaktivovat vsechny</a>";
}
