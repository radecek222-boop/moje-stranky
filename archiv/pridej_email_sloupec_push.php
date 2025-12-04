<?php
/**
 * Migrace: Pridani sloupce email do wgs_push_subscriptions
 *
 * WebPush.php pouziva sloupec email, ale v CREATE TABLE chybi.
 * Tento skript BEZPECNE prida chybejici sloupec.
 * Muzete jej spustit vicekrat - neprovede duplicitni zmeny.
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

$zpravy = [];
$uspech = false;

// Zpracovani
if (isset($_GET['execute']) && $_GET['execute'] === '1') {
    try {
        $pdo = getDbConnection();

        // Zkontrolovat zda sloupec jiz existuje
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_push_subscriptions LIKE 'email'");
        $sloupcExistuje = $stmt->rowCount() > 0;

        if ($sloupcExistuje) {
            $zpravy[] = ['typ' => 'info', 'text' => 'Sloupec email jiz existuje - zadna zmena'];
            $uspech = true;
        } else {
            // Pridat sloupec email
            $pdo->exec("
                ALTER TABLE wgs_push_subscriptions
                ADD COLUMN email VARCHAR(255) NULL COMMENT 'Email uzivatele pro notifikace'
                AFTER user_id
            ");

            $zpravy[] = ['typ' => 'success', 'text' => 'Sloupec email uspesne pridan'];

            // Pridat index pro vyhledavani
            try {
                $pdo->exec("
                    ALTER TABLE wgs_push_subscriptions
                    ADD INDEX idx_email (email)
                ");
                $zpravy[] = ['typ' => 'success', 'text' => 'Index idx_email pridan'];
            } catch (PDOException $e) {
                // Index mozna uz existuje
                $zpravy[] = ['typ' => 'info', 'text' => 'Index idx_email: ' . $e->getMessage()];
            }

            $uspech = true;
        }

    } catch (PDOException $e) {
        $zpravy[] = ['typ' => 'error', 'text' => 'Chyba databaze: ' . $e->getMessage()];
    }
}

// Kontrola aktualniho stavu
$sloupcExistuje = false;
$aktualniStruktura = [];

try {
    $pdo = getDbConnection();

    // Zkontrolovat strukturu tabulky
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_push_subscriptions");
    $aktualniStruktura = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($aktualniStruktura as $sloupec) {
        if ($sloupec['Field'] === 'email') {
            $sloupcExistuje = true;
            break;
        }
    }

} catch (PDOException $e) {
    $zpravy[] = ['typ' => 'error', 'text' => 'Nelze zkontrolovat databazi: ' . $e->getMessage()];
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrace: Pridani sloupce email - WGS</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
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
        .success {
            background: #e8e8e8;
            border: 1px solid #ccc;
            color: #222;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .success::before { content: "OK: "; font-weight: bold; }
        .error {
            background: #f5f5f5;
            border: 2px solid #333;
            color: #222;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .error::before { content: "CHYBA: "; font-weight: bold; }
        .info {
            background: #f9f9f9;
            border: 1px solid #ddd;
            color: #444;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .warning {
            background: #fff;
            border: 2px solid #666;
            color: #222;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .warning::before { content: "UPOZORNENI: "; font-weight: bold; }
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
        }
        .btn:hover { background: #555; }
        .btn-secondary { background: #777; }
        .btn-secondary:hover { background: #999; }
        .status-box {
            background: #fafafa;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .status-ok { color: #333; font-weight: bold; }
        .status-ok::before { content: "[OK] "; }
        .status-fail { color: #666; font-weight: bold; }
        .status-fail::before { content: "[CHYBI] "; }
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
        th { background: #f5f5f5; font-weight: 600; }
        pre {
            background: #1a1a1a;
            color: #eee;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 0.85rem;
        }
        .highlight { background: #ffffcc; }
    </style>
</head>
<body>
<div class="container">
    <h1>Migrace: Pridani sloupce email</h1>

    <?php if (!$sloupcExistuje && !$uspech): ?>
        <div class="warning">
            <strong>Problem nalezen!</strong><br>
            Sloupec <code>email</code> chybi v tabulce <code>wgs_push_subscriptions</code>.<br>
            Soubor <code>includes/WebPush.php</code> tento sloupec pouziva na radcich 414, 439, 448.<br>
            Bez tohoto sloupce registrace push subscription selze s SQL chybou.
        </div>
    <?php endif; ?>

    <?php foreach ($zpravy as $zprava): ?>
        <div class="<?php echo htmlspecialchars($zprava['typ']); ?>">
            <?php echo htmlspecialchars($zprava['text']); ?>
        </div>
    <?php endforeach; ?>

    <?php if ($uspech): ?>
        <div class="success">
            <strong>Migrace dokoncena!</strong><br>
            Push notifikace by nyni mely fungovat spravne.
        </div>
    <?php endif; ?>

    <div class="status-box">
        <h3>Aktualni stav:</h3>
        <p>
            Sloupec <code>email</code>:
            <span class="<?php echo $sloupcExistuje ? 'status-ok' : 'status-fail'; ?>">
                <?php echo $sloupcExistuje ? 'Existuje' : 'Chybi'; ?>
            </span>
        </p>
    </div>

    <h2>Aktualni struktura tabulky</h2>
    <table>
        <thead>
            <tr>
                <th>Sloupec</th>
                <th>Typ</th>
                <th>Null</th>
                <th>Klic</th>
                <th>Default</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($aktualniStruktura as $sloupec): ?>
                <tr class="<?php echo $sloupec['Field'] === 'email' ? 'highlight' : ''; ?>">
                    <td><?php echo htmlspecialchars($sloupec['Field']); ?></td>
                    <td><?php echo htmlspecialchars($sloupec['Type']); ?></td>
                    <td><?php echo htmlspecialchars($sloupec['Null']); ?></td>
                    <td><?php echo htmlspecialchars($sloupec['Key']); ?></td>
                    <td><?php echo htmlspecialchars($sloupec['Default'] ?? 'NULL'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>SQL prikaz</h2>
    <pre>ALTER TABLE wgs_push_subscriptions
ADD COLUMN email VARCHAR(255) NULL COMMENT 'Email uzivatele pro notifikace'
AFTER user_id;

ALTER TABLE wgs_push_subscriptions
ADD INDEX idx_email (email);</pre>

    <?php if (!$sloupcExistuje): ?>
        <a href="?execute=1" class="btn">Spustit Migraci</a>
    <?php else: ?>
        <div class="info">Sloupec email jiz existuje. Migrace neni potreba.</div>
    <?php endif; ?>

    <a href="pridej_push_subscriptions_tabulku.php" class="btn btn-secondary">Push Tabulka</a>
    <a href="setup_web_push.php" class="btn btn-secondary">VAPID Klice</a>
    <a href="/admin.php" class="btn btn-secondary">Zpet do Admin</a>
</div>
</body>
</html>
