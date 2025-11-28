<?php
/**
 * Migrace: Sjednoceni kolace tabulek na utf8mb4_unicode_ci
 *
 * Sjednoti kolaci vsech tabulek podle wgs_users (utf8mb4_unicode_ci).
 * Resi chybu: "Illegal mix of collations (utf8mb4_czech_ci) and (utf8mb4_unicode_ci)"
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Sjednoceni kolace</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 900px;
               margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        h3 { margin-top: 25px; color: #444; }
        .success { background: #d4edda; color: #155724; padding: 12px;
                   border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 12px;
                 border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px;
                border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 12px;
                   border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #333;
               color: white; text-decoration: none; border-radius: 5px;
               margin: 15px 5px 10px 0; border: none; cursor: pointer;
               font-size: 1rem; }
        .btn:hover { background: #555; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px;
              overflow-x: auto; border: 1px solid #ddd; margin: 5px 0;
              font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
<div class='container'>";

// Cilova kolace - stejna jako wgs_users
$cilovaKolace = 'utf8mb4_unicode_ci';

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Sjednoceni kolace tabulek</h1>";
    echo "<p>Sjednoti kolaci vsech tabulek na <code>{$cilovaKolace}</code> (stejna jako wgs_users).</p>";

    // Tabulky k oprave - vsechny push-related tabulky
    $tabulky = [
        'wgs_push_subscriptions',
        'wgs_push_log',
        'wgs_notes_read',
        'wgs_notes'
    ];

    // Zjistit kolaci wgs_users
    echo "<h3>Referencni tabulka wgs_users:</h3>";
    $stmt = $pdo->query("SHOW TABLE STATUS WHERE Name = 'wgs_users'");
    $status = $stmt->fetch(PDO::FETCH_ASSOC);
    $usersKolace = $status['Collation'] ?? 'neznama';
    echo "<pre>Kolace: " . htmlspecialchars($usersKolace) . "</pre>";

    // Kontrola aktualni kolace
    echo "<h3>Aktualni stav tabulek:</h3>";
    echo "<table><tr><th>Tabulka</th><th>Aktualni kolace</th><th>Stav</th></tr>";

    $potrebaOpravy = false;
    $tabulkyKOprave = [];

    foreach ($tabulky as $tabulka) {
        // Zkontrolovat jestli tabulka existuje
        $stmt = $pdo->query("SHOW TABLES LIKE '{$tabulka}'");
        if ($stmt->rowCount() === 0) {
            echo "<tr><td>{$tabulka}</td><td>-</td><td style='color:#999;'>Neexistuje</td></tr>";
            continue;
        }

        // Zjistit kolaci
        $stmt = $pdo->query("SHOW TABLE STATUS WHERE Name = '{$tabulka}'");
        $status = $stmt->fetch(PDO::FETCH_ASSOC);
        $kolace = $status['Collation'] ?? 'neznama';

        if ($kolace !== $cilovaKolace) {
            echo "<tr><td>{$tabulka}</td><td>{$kolace}</td><td style='color:#c00;font-weight:bold;'>POTREBUJE OPRAVU</td></tr>";
            $potrebaOpravy = true;
            $tabulkyKOprave[] = $tabulka;
        } else {
            echo "<tr><td>{$tabulka}</td><td>{$kolace}</td><td style='color:#080;'>OK</td></tr>";
        }
    }

    echo "</table>";

    // Spustit migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {

        if (!$potrebaOpravy) {
            echo "<div class='success'>Vsechny tabulky maji spravnou kolaci.</div>";
        } else {
            echo "<h3>Provadim migraci:</h3>";

            foreach ($tabulkyKOprave as $tabulka) {
                $sql = "ALTER TABLE {$tabulka} CONVERT TO CHARACTER SET utf8mb4 COLLATE {$cilovaKolace}";
                echo "<pre>{$sql}</pre>";

                try {
                    $pdo->exec($sql);
                    echo "<div class='success'>OK: {$tabulka} zmenena na {$cilovaKolace}</div>";
                } catch (Exception $e) {
                    echo "<div class='error'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }

            echo "<div class='success' style='margin-top:20px;'><strong>MIGRACE DOKONCENA!</strong></div>";
            echo "<div class='info'>Nyni muzete spustit <a href='diagnostika_push.php'>diagnostiku push notifikaci</a> znovu.</div>";
        }

    } else {
        if ($potrebaOpravy) {
            echo "<div class='warning'><strong>Nalezeny tabulky s jinou kolaci!</strong> Kliknete na tlacitko pro sjednoceni.</div>";
            echo "<a href='?execute=1' class='btn'>SJEDNOTIT KOLACI</a>";
        } else {
            echo "<div class='success'>Vsechny tabulky jsou v poradku.</div>";
        }
    }

    echo "<br><a href='diagnostika_push.php' class='btn' style='background:#666;'>Zpet na diagnostiku</a>";
    echo "<a href='admin.php' class='btn' style='background:#666;'>Zpet do Admin</a>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
