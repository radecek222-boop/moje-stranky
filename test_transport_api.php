<?php
/**
 * Test Transport API
 */

require_once __DIR__ . '/init.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Test Transport API</h1>";

// 1. Kontrola session
echo "<h2>1. Session</h2>";
echo "<pre>";
echo "is_admin: " . (isset($_SESSION['is_admin']) ? ($_SESSION['is_admin'] ? 'true' : 'false') : 'not set') . "\n";
echo "</pre>";

// 2. Kontrola databaze
echo "<h2>2. Databaze - wgs_transport_akce</h2>";
try {
    $pdo = getDbConnection();

    $stmt = $pdo->query("SELECT * FROM wgs_transport_akce");
    $eventy = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    echo "Pocet eventu: " . count($eventy) . "\n\n";
    foreach ($eventy as $e) {
        echo "ID: {$e['event_id']}, Nazev: {$e['nazev']}\n";
    }
    echo "</pre>";

} catch (Exception $ex) {
    echo "<pre style='color:red'>Chyba: " . htmlspecialchars($ex->getMessage()) . "</pre>";
}

// 3. Test API
echo "<h2>3. Test API volani</h2>";
echo "<pre>";
echo "URL: /api/transport_events_api.php?action=eventy_list\n";
echo "</pre>";

// 4. Odkaz
echo "<p><a href='admin.php?tab=transport'>Zpet na Transport</a></p>";
