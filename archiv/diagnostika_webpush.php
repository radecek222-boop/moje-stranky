<?php
/**
 * Diagnostika WebPush - Kontrola konfigurace a CA certifikatu
 */

require_once __DIR__ . '/init.php';

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Diagnostika WebPush</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #222; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .ok { background: #e8e8e8; border-left: 4px solid #333; padding: 12px; margin: 10px 0; }
        .error { background: #f5f5f5; border-left: 4px solid #999; padding: 12px; margin: 10px 0; }
        .warning { background: #fafafa; border-left: 4px solid #666; padding: 12px; margin: 10px 0; }
        pre { background: #222; color: #fff; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 13px; }
        .status { font-weight: bold; }
        .btn { display: inline-block; padding: 12px 24px; background: #333; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #555; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f9f9f9; }
    </style>
</head>
<body>
<div class='container'>
<h1>Diagnostika WebPush</h1>";

// 1. Kontrola cacert.pem
echo "<h2>1. CA Certificate Bundle</h2>";
$caCertPath = __DIR__ . '/cacert.pem';

if (file_exists($caCertPath)) {
    $velikost = filesize($caCertPath);
    $datum = date('Y-m-d H:i:s', filemtime($caCertPath));
    echo "<div class='ok'>";
    echo "<span class='status'>OK:</span> cacert.pem existuje<br>";
    echo "Cesta: <code>" . htmlspecialchars($caCertPath) . "</code><br>";
    echo "Velikost: " . number_format($velikost) . " bytu<br>";
    echo "Datum: $datum";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<span class='status'>CHYBA:</span> cacert.pem NEEXISTUJE!<br>";
    echo "Ocekavana cesta: <code>" . htmlspecialchars($caCertPath) . "</code><br><br>";
    echo "<strong>Oprava:</strong> Stahnete cacert.pem z Mozilla:<br>";
    echo "<pre>cd " . dirname($caCertPath) . "\nwget https://curl.se/ca/cacert.pem</pre>";
    echo "</div>";
}

// 2. Kontrola VAPID klicu
echo "<h2>2. VAPID Klice</h2>";
$vapidPublic = $_ENV['VAPID_PUBLIC_KEY'] ?? getenv('VAPID_PUBLIC_KEY') ?? '';
$vapidPrivate = $_ENV['VAPID_PRIVATE_KEY'] ?? getenv('VAPID_PRIVATE_KEY') ?? '';

if (!empty($vapidPublic) && !empty($vapidPrivate)) {
    echo "<div class='ok'>";
    echo "<span class='status'>OK:</span> VAPID klice nacteny<br>";
    echo "Public key (prvnich 30 znaku): <code>" . substr($vapidPublic, 0, 30) . "...</code><br>";
    echo "Private key: <code>***nastaveno***</code>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<span class='status'>CHYBA:</span> VAPID klice NEJSOU nastaveny!<br>";
    echo "Spustte: <code>php setup_web_push.php</code>";
    echo "</div>";
}

// 3. Kontrola WebPush tridy
echo "<h2>3. WebPush Trida</h2>";
require_once __DIR__ . '/includes/WebPush.php';

try {
    $pdo = getDbConnection();
    $webPush = new WGSWebPush($pdo);

    if ($webPush->jeInicializovano()) {
        echo "<div class='ok'>";
        echo "<span class='status'>OK:</span> WebPush je inicializovany a pripraveny";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "<span class='status'>CHYBA:</span> " . htmlspecialchars($webPush->getChyba());
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<span class='status'>VYJIMKA:</span> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

// 4. Subscriptions v databazi
echo "<h2>4. Push Subscriptions</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as celkem, SUM(aktivni = 1) as aktivni FROM wgs_push_subscriptions");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<div class='ok'>";
    echo "Celkem subscriptions: <strong>" . ($stats['celkem'] ?? 0) . "</strong><br>";
    echo "Aktivnich: <strong>" . ($stats['aktivni'] ?? 0) . "</strong>";
    echo "</div>";

    // Detail aktivnich
    $stmt = $pdo->query("SELECT id, LEFT(endpoint, 60) as endpoint_short, platforma, pocet_chyb, aktivni, datum_registrace FROM wgs_push_subscriptions ORDER BY datum_registrace DESC LIMIT 5");
    $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($subs)) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Endpoint</th><th>Platforma</th><th>Chyby</th><th>Aktivni</th><th>Registrace</th></tr>";
        foreach ($subs as $sub) {
            echo "<tr>";
            echo "<td>" . $sub['id'] . "</td>";
            echo "<td><code>" . htmlspecialchars($sub['endpoint_short']) . "...</code></td>";
            echo "<td>" . ($sub['platforma'] ?: '-') . "</td>";
            echo "<td>" . $sub['pocet_chyb'] . "</td>";
            echo "<td>" . ($sub['aktivni'] ? 'Ano' : 'Ne') . "</td>";
            echo "<td>" . $sub['datum_registrace'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<div class='error'>Chyba DB: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// 5. PHP error log (posledni WebPush zaznamy)
echo "<h2>5. Posledni logy</h2>";
$logFile = __DIR__ . '/logs/php_errors.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    $webPushLines = array_filter($lines, function($line) {
        return stripos($line, 'webpush') !== false || stripos($line, 'push') !== false || stripos($line, 'curl') !== false;
    });
    $webPushLines = array_slice($webPushLines, -15);

    if (!empty($webPushLines)) {
        echo "<pre>" . htmlspecialchars(implode("\n", $webPushLines)) . "</pre>";
    } else {
        echo "<div class='warning'>Zadne WebPush zaznamy v logu</div>";
    }
} else {
    echo "<div class='warning'>Log soubor neexistuje: $logFile</div>";
}

// 6. Akce
echo "<h2>Akce</h2>";
echo "<a href='/test_push_notifikace.php?send=1' class='btn'>Odeslat testovaci notifikaci</a>";
echo "<a href='/reaktivuj_subscriptions.php' class='btn'>Reaktivovat subscriptions</a>";
echo "<a href='/admin.php' class='btn'>Zpet do Admin</a>";

echo "</div></body></html>";
?>
