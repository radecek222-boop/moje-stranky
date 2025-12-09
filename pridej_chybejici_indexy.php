<?php
/**
 * Migrace: Přidání chybějících indexů pro optimalizaci databáze
 *
 * Tento skript BEZPEČNĚ přidá indexy na sloupce created_at, updated_at a email.
 * Můžete jej spustit vícekrát - nepřidá duplicitní indexy.
 *
 * Indexy zrychlí:
 * - Řazení podle data vytvoření/aktualizace
 * - Filtrování v WHERE podmínkách
 * - JOIN operace
 *
 * @date 2025-12-09
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Migrace: Přidání chybějících indexů</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; margin-top: 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #555; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #333; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .status-ok { color: #155724; font-weight: bold; }
        .status-new { color: #0c5460; font-weight: bold; }
        .status-skip { color: #856404; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Přidání chybějících indexů</h1>";

    // Definice indexů k přidání
    // Formát: [tabulka, sloupec, název_indexu]
    $indexy = [
        ['wgs_analytics_bot_whitelist', 'created_at', 'idx_bot_whitelist_created'],
        ['wgs_analytics_bot_whitelist', 'updated_at', 'idx_bot_whitelist_updated'],
        ['wgs_analytics_ignored_ips', 'created_at', 'idx_ignored_ips_created'],
        ['wgs_natuzzi_aktuality', 'updated_at', 'idx_aktuality_updated'],
        ['wgs_notes', 'updated_at', 'idx_notes_updated'],
        ['wgs_pdf_parser_configs', 'created_at', 'idx_pdf_configs_created'],
        ['wgs_pdf_parser_configs', 'updated_at', 'idx_pdf_configs_updated'],
        ['wgs_pricing', 'created_at', 'idx_pricing_created'],
        ['wgs_pricing', 'updated_at', 'idx_pricing_updated'],
        ['wgs_push_subscriptions', 'email', 'idx_push_subs_email'],
        ['wgs_system_config', 'updated_at', 'idx_system_config_updated'],
        ['wgs_videos', 'created_at', 'idx_videos_created'],
    ];

    // Funkce pro kontrolu existence indexu
    function indexExistuje($pdo, $tabulka, $nazevIndexu) {
        try {
            // Použijeme INFORMATION_SCHEMA pro spolehlivou detekci
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as cnt
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :tabulka
                AND INDEX_NAME = :nazev
            ");
            $stmt->execute(['tabulka' => $tabulka, 'nazev' => $nazevIndexu]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($result['cnt'] ?? 0) > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    // Funkce pro kontrolu existence sloupce
    function sloupecExistuje($pdo, $tabulka, $sloupec) {
        try {
            // SHOW COLUMNS LIKE nefunguje s prepared statements, použijeme INFORMATION_SCHEMA
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as cnt
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :tabulka
                AND COLUMN_NAME = :sloupec
            ");
            $stmt->execute(['tabulka' => $tabulka, 'sloupec' => $sloupec]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($result['cnt'] ?? 0) > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    // Funkce pro kontrolu existence tabulky
    function tabulkaExistuje($pdo, $tabulka) {
        try {
            // SHOW TABLES LIKE nefunguje s prepared statements, použijeme INFORMATION_SCHEMA
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as cnt
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :tabulka
            ");
            $stmt->execute(['tabulka' => $tabulka]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($result['cnt'] ?? 0) > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    // 1. KONTROLNÍ FÁZE - Zobrazit co bude provedeno
    echo "<div class='info'><strong>KONTROLA INDEXŮ...</strong></div>";

    echo "<table>
        <tr>
            <th>Tabulka</th>
            <th>Sloupec</th>
            <th>Index</th>
            <th>Stav</th>
        </tr>";

    $indexyKPridani = [];
    $preskocene = 0;
    $chybejiciTabulky = 0;

    foreach ($indexy as $index) {
        [$tabulka, $sloupec, $nazev] = $index;

        echo "<tr>";
        echo "<td><code>$tabulka</code></td>";
        echo "<td><code>$sloupec</code></td>";
        echo "<td><code>$nazev</code></td>";

        if (!tabulkaExistuje($pdo, $tabulka)) {
            echo "<td class='status-skip'>Tabulka neexistuje</td>";
            $chybejiciTabulky++;
        } elseif (!sloupecExistuje($pdo, $tabulka, $sloupec)) {
            echo "<td class='status-skip'>Sloupec neexistuje</td>";
            $preskocene++;
        } elseif (indexExistuje($pdo, $tabulka, $nazev)) {
            echo "<td class='status-ok'>Již existuje</td>";
            $preskocene++;
        } else {
            echo "<td class='status-new'>Bude přidán</td>";
            $indexyKPridani[] = $index;
        }

        echo "</tr>";
    }

    echo "</table>";

    // 2. SPUŠTĚNÍ MIGRACE
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        if (empty($indexyKPridani)) {
            echo "<div class='success'><strong>Všechny indexy již existují!</strong> Není co přidávat.</div>";
        } else {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            $uspesne = 0;
            $chyby = [];

            foreach ($indexyKPridani as $index) {
                [$tabulka, $sloupec, $nazev] = $index;

                try {
                    $sql = "CREATE INDEX `$nazev` ON `$tabulka` (`$sloupec`)";
                    $pdo->exec($sql);
                    $uspesne++;
                    echo "<div class='success'>Přidán index <code>$nazev</code> na <code>$tabulka.$sloupec</code></div>";
                } catch (PDOException $e) {
                    $chyby[] = "$tabulka.$sloupec: " . $e->getMessage();
                    echo "<div class='error'>Chyba při přidávání <code>$nazev</code>: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }

            echo "<br>";

            if (empty($chyby)) {
                echo "<div class='success'>";
                echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA!</strong><br>";
                echo "Přidáno indexů: $uspesne<br>";
                echo "Přeskočeno (již existují): $preskocene";
                echo "</div>";
            } else {
                echo "<div class='warning'>";
                echo "<strong>MIGRACE DOKONČENA S CHYBAMI</strong><br>";
                echo "Úspěšně přidáno: $uspesne<br>";
                echo "Chyby: " . count($chyby);
                echo "</div>";
            }
        }
    } else {
        // Náhled
        if (empty($indexyKPridani)) {
            echo "<div class='success'><strong>Všechny indexy již existují!</strong> Není co přidávat.</div>";
        } else {
            echo "<div class='warning'>";
            echo "<strong>Bude přidáno " . count($indexyKPridani) . " indexů.</strong><br>";
            echo "Přeskočeno (již existují nebo chybí sloupec): $preskocene<br>";
            if ($chybejiciTabulky > 0) {
                echo "Chybějící tabulky: $chybejiciTabulky";
            }
            echo "</div>";

            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        }
    }

    echo "<br><br>";
    echo "<a href='admin.php?tab=admin_console' class='btn' style='background: #666;'>Zpět na Konzoli</a>";
    echo "<a href='vsechny_tabulky.php' class='btn' style='background: #666;'>SQL Přehled</a>";

} catch (Exception $e) {
    echo "<div class='error'><strong>KRITICKÁ CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
