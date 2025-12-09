<?php
/**
 * Migrace: Sjednoceni COLLATION tabulek
 *
 * Tento skript sjednoti collation vsech relevantnich tabulek
 * na utf8mb4_czech_ci pro spravne fungovani JOIN operaci.
 *
 * BEZPECNE: Lze spustit vicekrat, neprovede duplicitni zmeny.
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Sjednoceni Collation</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #222; border-bottom: 3px solid #222; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f5f5f5; }
        .btn { display: inline-block; padding: 12px 24px;
               background: #222; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer;
               font-size: 16px; }
        .btn:hover { background: #444; }
        .btn-danger { background: #dc3545; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        pre { background: #222; color: #0f0; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>
<h1>Sjednoceni Collation Tabulek</h1>
<p>Spusteno: " . date('Y-m-d H:i:s') . "</p>";

$cilovaCollation = 'utf8mb4_czech_ci';
$cilovyCharset = 'utf8mb4';

// Tabulky ktere potrebuji sjednotit
$tabulky = [
    'wgs_users',
    'wgs_push_subscriptions',
    'wgs_push_log',
    'wgs_reklamace',
    'wgs_notes'
];

try {
    $pdo = getDbConnection();

    // =====================================================
    // 1. AKTUALNI STAV
    // =====================================================
    echo "<h2>1. Aktualni Stav Collation</h2>";

    $stmt = $pdo->query("
        SELECT TABLE_NAME, TABLE_COLLATION
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('" . implode("','", $tabulky) . "')
        ORDER BY TABLE_NAME
    ");
    $aktualni = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>
        <tr><th>Tabulka</th><th>Aktualni Collation</th><th>Cilova Collation</th><th>Status</th></tr>";

    $potrebujeZmenu = [];
    foreach ($aktualni as $row) {
        $ok = ($row['TABLE_COLLATION'] === $cilovaCollation);
        $status = $ok ? '<span style="color:green;">OK</span>' : '<span style="color:orange;">Potrebuje zmenu</span>';
        echo "<tr>
            <td>{$row['TABLE_NAME']}</td>
            <td><code>{$row['TABLE_COLLATION']}</code></td>
            <td><code>{$cilovaCollation}</code></td>
            <td>{$status}</td>
        </tr>";

        if (!$ok) {
            $potrebujeZmenu[] = $row['TABLE_NAME'];
        }
    }
    echo "</table>";

    if (empty($potrebujeZmenu)) {
        echo "<div class='success'><strong>Vse je v poradku!</strong> Vsechny tabulky maji spravnou collation.</div>";
        echo "<a href='/diagnostika_push_notifikace.php' class='btn'>Zpet na diagnostiku</a>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='warning'>
        <strong>" . count($potrebujeZmenu) . " tabulek</strong> potrebuje zmenu collation: <code>" . implode(', ', $potrebujeZmenu) . "</code>
    </div>";

    // =====================================================
    // 2. PROVEDENI MIGRACE
    // =====================================================
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<h2>2. Provadim Migraci...</h2>";

        $uspesne = 0;
        $chyby = 0;

        foreach ($potrebujeZmenu as $tabulka) {
            try {
                echo "<div class='info'>Menim collation pro <strong>{$tabulka}</strong>...</div>";

                // Zmenit charset a collation tabulky
                $sql = "ALTER TABLE `{$tabulka}` CONVERT TO CHARACTER SET {$cilovyCharset} COLLATE {$cilovaCollation}";
                $pdo->exec($sql);

                echo "<div class='success'>Tabulka <strong>{$tabulka}</strong> zmenena na <code>{$cilovaCollation}</code></div>";
                $uspesne++;

            } catch (PDOException $e) {
                echo "<div class='error'>Chyba pri zmene <strong>{$tabulka}</strong>: " . htmlspecialchars($e->getMessage()) . "</div>";
                $chyby++;
            }
        }

        // =====================================================
        // 3. OVERENI
        // =====================================================
        echo "<h2>3. Overeni</h2>";

        $stmt = $pdo->query("
            SELECT TABLE_NAME, TABLE_COLLATION
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME IN ('" . implode("','", $tabulky) . "')
            ORDER BY TABLE_NAME
        ");
        $poZmene = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<table>
            <tr><th>Tabulka</th><th>Nova Collation</th><th>Status</th></tr>";

        foreach ($poZmene as $row) {
            $ok = ($row['TABLE_COLLATION'] === $cilovaCollation);
            $status = $ok ? '<span style="color:green;">OK</span>' : '<span style="color:red;">CHYBA</span>';
            echo "<tr>
                <td>{$row['TABLE_NAME']}</td>
                <td><code>{$row['TABLE_COLLATION']}</code></td>
                <td>{$status}</td>
            </tr>";
        }
        echo "</table>";

        // Souhrn
        if ($chyby === 0) {
            echo "<div class='success'>
                <strong>MIGRACE DOKONCENA USPESNE!</strong><br>
                Zmeneno {$uspesne} tabulek na collation <code>{$cilovaCollation}</code>.<br><br>
                Push notifikace by nyni mely fungovat spravne.
            </div>";
        } else {
            echo "<div class='error'>
                <strong>MIGRACE DOKONCENA S CHYBAMI!</strong><br>
                Uspesne: {$uspesne}, Chyby: {$chyby}
            </div>";
        }

        echo "<a href='/diagnostika_push_notifikace.php' class='btn'>Zpet na diagnostiku</a>";

    } else {
        // =====================================================
        // NAHLED - CO BUDE PROVEDENO
        // =====================================================
        echo "<h2>2. Co bude provedeno</h2>";

        echo "<div class='info'>
            <strong>Nasledujici SQL prikazy budou provedeny:</strong>
        </div>";

        echo "<pre>";
        foreach ($potrebujeZmenu as $tabulka) {
            echo "ALTER TABLE `{$tabulka}` CONVERT TO CHARACTER SET {$cilovyCharset} COLLATE {$cilovaCollation};\n";
        }
        echo "</pre>";

        echo "<div class='warning'>
            <strong>UPOZORNENI:</strong> Tato operace muze trvat nekolik sekund az minut v zavislosti na velikosti tabulek.
            Behem migrace mohou byt dotazy na tyto tabulky pomale.
        </div>";

        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='/diagnostika_push_notifikace.php' class='btn' style='background: #666;'>Zrusit</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
