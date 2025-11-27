<?php
/**
 * Test Push Notifikace
 * Odesle testovaci push notifikaci vsem registrovanym zarizenim
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/WebPush.php';

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator.");
}

$zprava = '';
$typ = '';

// Odeslat test
if (isset($_GET['send']) && $_GET['send'] === '1') {
    try {
        $pdo = getDbConnection();
        $webPush = new WGSWebPush($pdo);

        if (!$webPush->jeInicializovano()) {
            $zprava = "WebPush neni inicializovan: " . $webPush->getChyba();
            $typ = 'error';
        } else {
            $payload = [
                'title' => 'WGS Test',
                'body' => 'Testovaci push notifikace - ' . date('H:i:s'),
                'icon' => '/icon192.png',
                'tag' => 'wgs-test-' . time(),
                'data' => ['test' => true, 'timestamp' => time()]
            ];

            $vysledek = $webPush->odeslatVsem($payload);

            if ($vysledek['uspech']) {
                $zprava = "Notifikace odeslana! Uspesne: {$vysledek['odeslano']}, Selhalo: {$vysledek['chyby']}";
                $typ = 'success';
            } else {
                $zprava = "Chyba: " . ($vysledek['zprava'] ?? 'Neznama chyba');
                $typ = 'error';

                // Detaily - zkontrolovat subscription data
                $stmt = $pdo->query("SELECT id, endpoint, p256dh, auth, platforma FROM wgs_push_subscriptions WHERE aktivni = 1 LIMIT 1");
                $sub = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($sub) {
                    $zprava .= "<br><br><strong>Debug info:</strong><br>";
                    $zprava .= "Endpoint: " . substr($sub['endpoint'], 0, 80) . "...<br>";
                    $zprava .= "p256dh delka: " . strlen($sub['p256dh']) . " znaku<br>";
                    $zprava .= "auth delka: " . strlen($sub['auth']) . " znaku<br>";
                    $zprava .= "Platforma: " . ($sub['platforma'] ?: 'neurcena');
                }
            }
        }
    } catch (Exception $e) {
        $zprava = "Vyjimka: " . $e->getMessage();
        $typ = 'error';
    }
}

// Statistiky
$statistiky = [];
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT COUNT(*) as celkem, SUM(aktivni = 1) as aktivni FROM wgs_push_subscriptions");
    $statistiky = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $statistiky = ['celkem' => '?', 'aktivni' => '?'];
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Push Notifikace - WGS</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        h1 {
            color: #222;
            border-bottom: 3px solid #333;
            padding-bottom: 15px;
            margin-top: 0;
        }
        .success {
            background: #e8e8e8;
            border: 1px solid #ccc;
            color: #222;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .error {
            background: #f5f5f5;
            border: 2px solid #333;
            color: #222;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .info {
            background: #f9f9f9;
            border: 1px solid #ddd;
            color: #444;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 15px 10px 15px 0;
            font-size: 1rem;
            font-weight: 600;
        }
        .btn:hover { background: #555; }
        .btn-secondary { background: #777; }
        .stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        .stat-box {
            flex: 1;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #ddd;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #222;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Test Push Notifikace</h1>

    <?php if ($zprava): ?>
    <div class="<?php echo $typ; ?>">
        <?php echo htmlspecialchars($zprava); ?>
    </div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat-box">
            <div class="stat-number"><?php echo $statistiky['celkem'] ?? 0; ?></div>
            <div class="stat-label">Celkem subscriptions</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $statistiky['aktivni'] ?? 0; ?></div>
            <div class="stat-label">Aktivnich</div>
        </div>
    </div>

    <div class="info">
        <strong>Co se stane:</strong><br>
        Odesle se testovaci push notifikace na vsechna aktivni zarizeni.<br>
        Notifikace se zobrazi i kdyz je stranka zavrena (pokud je browser otevreny).
    </div>

    <a href="?send=1" class="btn">Odeslat Testovaci Notifikaci</a>
    <a href="/admin.php" class="btn btn-secondary">Zpet do Admin</a>
</div>
</body>
</html>
