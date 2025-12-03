<?php
/**
 * Migrace: Vytvoreni tabulky wgs_push_subscriptions
 *
 * Tento skript BEZPECNE vytvori tabulku pro Web Push subscriptions.
 * Muzete jej spustit vicekrat - nevytvori duplicitni tabulku.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

$zpravy = [];
$uspech = false;

// SQL pro vytvoreni tabulky
$sqlTabulka = "
CREATE TABLE IF NOT EXISTS wgs_push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'ID uzivatele (pokud je prihlasen)',
    email VARCHAR(255) NULL COMMENT 'Email uzivatele pro notifikace',
    endpoint VARCHAR(500) NOT NULL COMMENT 'Push endpoint URL',
    p256dh VARCHAR(200) NOT NULL COMMENT 'Public key pro sifrovani',
    auth VARCHAR(100) NOT NULL COMMENT 'Auth secret',
    user_agent VARCHAR(500) NULL COMMENT 'Prohlizec/zarizeni',
    platforma VARCHAR(50) NULL COMMENT 'ios, android, desktop',
    aktivni TINYINT(1) DEFAULT 1 COMMENT '1 = aktivni, 0 = neaktivni',
    datum_vytvoreni DATETIME DEFAULT CURRENT_TIMESTAMP,
    datum_posledni_aktualizace DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    posledni_uspesne_odeslani DATETIME NULL COMMENT 'Kdy byl uspesne odeslan push',
    pocet_chyb INT DEFAULT 0 COMMENT 'Pocet neuspesnych pokusu',
    UNIQUE KEY unique_endpoint (endpoint(255)),
    INDEX idx_user_id (user_id),
    INDEX idx_aktivni (aktivni),
    INDEX idx_platforma (platforma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
COMMENT='Web Push subscriptions pro PWA notifikace'
";

// SQL pro tabulku logu odeslanych notifikaci
$sqlLogTabulka = "
CREATE TABLE IF NOT EXISTS wgs_push_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscription_id INT NULL,
    typ_notifikace VARCHAR(50) NOT NULL COMMENT 'nova_poznamka, zmena_stavu, atd.',
    reklamace_id INT NULL,
    titulek VARCHAR(200) NOT NULL,
    zprava TEXT NOT NULL,
    stav ENUM('odeslano', 'doruceno', 'chyba', 'zamitnuto') DEFAULT 'odeslano',
    chybova_zprava TEXT NULL,
    datum_odeslani DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_subscription (subscription_id),
    INDEX idx_reklamace (reklamace_id),
    INDEX idx_datum (datum_odeslani),
    FOREIGN KEY (subscription_id) REFERENCES wgs_push_subscriptions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
COMMENT='Log odeslanych push notifikaci'
";

// Zpracovani
if (isset($_GET['execute']) && $_GET['execute'] === '1') {
    try {
        $pdo = getDbConnection();

        // Vytvorit hlavni tabulku
        $pdo->exec($sqlTabulka);
        $zpravy[] = ['typ' => 'success', 'text' => 'Tabulka wgs_push_subscriptions vytvorena/existuje'];

        // Vytvorit log tabulku
        $pdo->exec($sqlLogTabulka);
        $zpravy[] = ['typ' => 'success', 'text' => 'Tabulka wgs_push_log vytvorena/existuje'];

        $uspech = true;

    } catch (PDOException $e) {
        $zpravy[] = ['typ' => 'error', 'text' => 'Chyba databaze: ' . $e->getMessage()];
    }
}

// Kontrola aktualniho stavu
$tabulkaExistuje = false;
$logTabulkaExistuje = false;
$pocetSubscriptions = 0;

try {
    $pdo = getDbConnection();

    // Kontrola hlavni tabulky
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_push_subscriptions'");
    $tabulkaExistuje = $stmt->rowCount() > 0;

    if ($tabulkaExistuje) {
        $stmt = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_push_subscriptions");
        $pocetSubscriptions = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];
    }

    // Kontrola log tabulky
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_push_log'");
    $logTabulkaExistuje = $stmt->rowCount() > 0;

} catch (PDOException $e) {
    $zpravy[] = ['typ' => 'error', 'text' => 'Nelze zkontrolovat databazi: ' . $e->getMessage()];
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrace: Push Subscriptions - WGS</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
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
        h2 {
            color: #444;
            margin-top: 30px;
        }
        .success {
            background: #e8e8e8;
            border: 1px solid #ccc;
            color: #222;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .success::before {
            content: "OK: ";
            font-weight: bold;
        }
        .error {
            background: #f5f5f5;
            border: 2px solid #333;
            color: #222;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .error::before {
            content: "CHYBA: ";
            font-weight: bold;
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
            border: none;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #555;
        }
        .btn-secondary {
            background: #777;
        }
        .btn-secondary:hover {
            background: #999;
        }
        .status-box {
            background: #fafafa;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .status-box h3 {
            margin-top: 0;
            color: #333;
        }
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .status-item:last-child {
            border-bottom: none;
        }
        .status-ok {
            color: #333;
            font-weight: bold;
        }
        .status-ok::before {
            content: "[OK] ";
        }
        .status-fail {
            color: #666;
            font-weight: bold;
        }
        .status-fail::before {
            content: "[X] ";
        }
        pre {
            background: #1a1a1a;
            color: #eee;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 0.85rem;
            line-height: 1.5;
        }
        .next-steps {
            background: #fafafa;
            border-left: 4px solid #333;
            padding: 20px;
            margin: 20px 0;
        }
        .next-steps h3 {
            margin-top: 0;
        }
        .next-steps ol {
            margin-bottom: 0;
            padding-left: 20px;
        }
        .next-steps li {
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            text-align: left;
            padding: 10px;
            border: 1px solid #ddd;
        }
        th {
            background: #f5f5f5;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Migrace: Push Subscriptions</h1>

    <?php foreach ($zpravy as $zprava): ?>
        <div class="<?php echo htmlspecialchars($zprava['typ']); ?>">
            <?php echo htmlspecialchars($zprava['text']); ?>
        </div>
    <?php endforeach; ?>

    <?php if ($uspech): ?>
        <div class="success">
            <strong>Migrace uspesne dokoncena!</strong>
        </div>

        <div class="next-steps">
            <h3>Web Push je pripraven!</h3>
            <ol>
                <li>VAPID klice: <a href="setup_web_push.php">zkontrolovat/vygenerovat</a></li>
                <li>Spustte <code>composer update</code> na serveru</li>
                <li>Otestujte v PWA aplikaci</li>
            </ol>
        </div>
    <?php endif; ?>

    <div class="status-box">
        <h3>Aktualni stav:</h3>

        <div class="status-item">
            <span>Tabulka wgs_push_subscriptions</span>
            <span class="<?php echo $tabulkaExistuje ? 'status-ok' : 'status-fail'; ?>">
                <?php echo $tabulkaExistuje ? 'Existuje' : 'Neexistuje'; ?>
            </span>
        </div>

        <div class="status-item">
            <span>Tabulka wgs_push_log</span>
            <span class="<?php echo $logTabulkaExistuje ? 'status-ok' : 'status-fail'; ?>">
                <?php echo $logTabulkaExistuje ? 'Existuje' : 'Neexistuje'; ?>
            </span>
        </div>

        <?php if ($tabulkaExistuje): ?>
        <div class="status-item">
            <span>Pocet registrovanych zarizeni</span>
            <span><?php echo $pocetSubscriptions; ?></span>
        </div>
        <?php endif; ?>
    </div>

    <h2>Struktura tabulky</h2>

    <h3>wgs_push_subscriptions</h3>
    <table>
        <thead>
            <tr>
                <th>Sloupec</th>
                <th>Typ</th>
                <th>Popis</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>id</td><td>INT AUTO_INCREMENT</td><td>Primarni klic</td></tr>
            <tr><td>user_id</td><td>INT NULL</td><td>ID uzivatele</td></tr>
            <tr><td>email</td><td>VARCHAR(255)</td><td>Email uzivatele pro notifikace</td></tr>
            <tr><td>endpoint</td><td>VARCHAR(500)</td><td>Push endpoint URL</td></tr>
            <tr><td>p256dh</td><td>VARCHAR(200)</td><td>Public key pro sifrovani</td></tr>
            <tr><td>auth</td><td>VARCHAR(100)</td><td>Auth secret</td></tr>
            <tr><td>user_agent</td><td>VARCHAR(500)</td><td>Prohlizec/zarizeni</td></tr>
            <tr><td>platforma</td><td>VARCHAR(50)</td><td>ios, android, desktop</td></tr>
            <tr><td>aktivni</td><td>TINYINT(1)</td><td>1 = aktivni, 0 = neaktivni</td></tr>
            <tr><td>datum_vytvoreni</td><td>DATETIME</td><td>Kdy byla subscription vytvorena</td></tr>
            <tr><td>posledni_uspesne_odeslani</td><td>DATETIME</td><td>Posledni uspesny push</td></tr>
            <tr><td>pocet_chyb</td><td>INT</td><td>Pocet neuspesnych pokusu</td></tr>
        </tbody>
    </table>

    <h2>SQL prikazy</h2>
    <pre><?php echo htmlspecialchars(trim($sqlTabulka)); ?></pre>

    <?php if (!$tabulkaExistuje || !$logTabulkaExistuje): ?>
        <a href="?execute=1" class="btn">Spustit Migraci</a>
    <?php else: ?>
        <div class="info">Tabulky jiz existuji. Migrace neni potreba.</div>
    <?php endif; ?>

    <a href="setup_web_push.php" class="btn btn-secondary">VAPID Klice</a>
    <a href="/admin.php" class="btn btn-secondary">Zpet do Admin</a>
</div>
</body>
</html>
