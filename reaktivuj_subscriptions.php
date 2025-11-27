<?php
/**
 * Reaktivace push subscriptions
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN");
}

$pdo = getDbConnection();

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Reaktivace Subscriptions</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #222; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .success { background: #e8e8e8; border-left: 4px solid #333; padding: 12px; margin: 10px 0; }
        .info { background: #f5f5f5; border-left: 4px solid #666; padding: 12px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #333; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #555; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f9f9f9; }
    </style>
</head>
<body>
<div class='container'>
<h1>Reaktivace Push Subscriptions</h1>";

// Aktualni stav
$stmt = $pdo->query("SELECT id, LEFT(endpoint, 50) as endpoint_zkraceny, platforma, aktivni, pocet_chyb, datum_registrace FROM wgs_push_subscriptions");
$subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Aktualni stav</h2>";
if (empty($subs)) {
    echo "<div class='info'>Zadne subscriptions v databazi.</div>";
} else {
    echo "<table>";
    echo "<tr><th>ID</th><th>Endpoint</th><th>Platforma</th><th>Aktivni</th><th>Chyby</th><th>Registrace</th></tr>";
    foreach ($subs as $sub) {
        echo "<tr>";
        echo "<td>{$sub['id']}</td>";
        echo "<td><code>{$sub['endpoint_zkraceny']}...</code></td>";
        echo "<td>" . ($sub['platforma'] ?: '-') . "</td>";
        echo "<td>" . ($sub['aktivni'] ? 'Ano' : 'Ne') . "</td>";
        echo "<td>{$sub['pocet_chyb']}</td>";
        echo "<td>{$sub['datum_registrace']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Reaktivace
if (isset($_GET['reaktivuj']) && $_GET['reaktivuj'] === '1') {
    $stmt = $pdo->exec("UPDATE wgs_push_subscriptions SET aktivni = 1, pocet_chyb = 0");
    echo "<div class='success'><strong>Hotovo!</strong> Vsechny subscriptions byly reaktivovany a pocitadlo chyb vynulovano.</div>";
    echo "<p><a href='reaktivuj_subscriptions.php' class='btn'>Obnovit stranku</a></p>";
} else {
    echo "<p>Kliknete pro reaktivaci vsech subscriptions:</p>";
    echo "<a href='?reaktivuj=1' class='btn'>Reaktivovat vse</a>";
}

echo "<p><a href='/test_push_notifikace.php' class='btn'>Test Push</a>";
echo "<a href='/admin.php' class='btn'>Zpet do Admin</a></p>";

echo "</div></body></html>";
?>
