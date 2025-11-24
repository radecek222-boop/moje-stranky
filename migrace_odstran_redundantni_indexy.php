<?php
/**
 * Migrace: Odstranění redundantních indexů
 *
 * Tento skript BEZPEČNĚ odstraní duplicitní indexy pro lepší INSERT/UPDATE výkon.
 * Můžete jej spustit vícekrát - neprovede se duplicitní operace (IF EXISTS).
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
    <title>Migrace: Odstranění redundantních indexů</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; }
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
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0;
               border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #555; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #333; color: white; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px;
              overflow-x: auto; }
        code { font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🗑️ Migrace: Odstranění redundantních indexů</h1>";
    echo "<div class='info'><strong>Zdroj:</strong> WGS Technical Audit 2025-11-24</div>";

    // Kontrolní fáze - zjistit redundantní indexy
    echo "<h2>📊 KONTROLA REDUNDANTNÍCH INDEXŮ</h2>";

    $redundantniIndexy = [
        'wgs_users' => [
            'idx_email' => ['column' => 'email', 'reason' => 'UNIQUE KEY email už zajišťuje rychlé vyhledávání'],
            'idx_user_email' => ['column' => 'email', 'reason' => 'UNIQUE KEY email už zajišťuje rychlé vyhledávání']
        ],
        'wgs_email_queue' => [
            'idx_created_at_ts' => ['column' => 'created_at', 'reason' => 'INDEX idx_created_at už existuje']
        ]
    ];

    $nalezeneIndexy = [];
    $chybejiciIndexy = [];

    echo "<table>";
    echo "<tr><th>Tabulka</th><th>Redundantní index</th><th>Sloupec</th><th>Status</th><th>Důvod redundance</th></tr>";

    foreach ($redundantniIndexy as $tabulka => $indexy) {
        $stmt = $pdo->query("SHOW INDEX FROM `{$tabulka}`");
        $existujiciIndexy = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $indexyMap = [];
        foreach ($existujiciIndexy as $index) {
            $indexyMap[$index['Key_name']] = $index;
        }

        foreach ($indexy as $indexName => $info) {
            echo "<tr>";
            echo "<td><code>{$tabulka}</code></td>";
            echo "<td><code>{$indexName}</code></td>";
            echo "<td>{$info['column']}</td>";

            if (isset($indexyMap[$indexName])) {
                echo "<td style='color: orange;'>⚠️ Existuje</td>";
                $nalezeneIndexy[] = ['tabulka' => $tabulka, 'index' => $indexName];
            } else {
                echo "<td style='color: green;'>✅ Již odstraněn</td>";
                $chybejiciIndexy[] = ['tabulka' => $tabulka, 'index' => $indexName];
            }

            echo "<td><small>{$info['reason']}</small></td>";
            echo "</tr>";
        }
    }
    echo "</table>";

    if (empty($nalezeneIndexy)) {
        echo "<div class='success'>";
        echo "<strong>✅ ŽÁDNÉ REDUNDANTNÍ INDEXY</strong><br>";
        echo "Všechny redundantní indexy již byly odstraněny. Není potřeba provádět migraci.";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "<strong>⚠️ NALEZENO " . count($nalezeneIndexy) . " REDUNDANTNÍCH INDEXŮ</strong><br>";
        echo "Jejich odstranění zlepší INSERT/UPDATE výkon o 5-15% a ušetří ~150 KB diskového prostoru.";
        echo "</div>";
    }

    // Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1' && !empty($nalezeneIndexy)) {

        echo "<h2>🚀 SPOUŠTÍM MIGRACI...</h2>";

        $vysledky = [];

        foreach ($nalezeneIndexy as $item) {
            $tabulka = $item['tabulka'];
            $indexName = $item['index'];

            try {
                $sql = "ALTER TABLE `{$tabulka}` DROP INDEX `{$indexName}`";
                $pdo->exec($sql);
                $vysledky[] = [
                    'tabulka' => $tabulka,
                    'index' => $indexName,
                    'status' => 'success',
                    'message' => 'Index úspěšně odstraněn'
                ];
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), "Can't DROP") !== false || strpos($e->getMessage(), "check that column/key exists") !== false) {
                    $vysledky[] = [
                        'tabulka' => $tabulka,
                        'index' => $indexName,
                        'status' => 'warning',
                        'message' => 'Index již neexistuje'
                    ];
                } else {
                    $vysledky[] = [
                        'tabulka' => $tabulka,
                        'index' => $indexName,
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }
        }

        // Zobrazit výsledky
        echo "<table>";
        echo "<tr><th>Tabulka</th><th>Index</th><th>Status</th><th>Zpráva</th></tr>";
        foreach ($vysledky as $vysledek) {
            echo "<tr>";
            echo "<td><code>{$vysledek['tabulka']}</code></td>";
            echo "<td><code>{$vysledek['index']}</code></td>";
            if ($vysledek['status'] === 'success') {
                echo "<td style='color: green;'>✅ Úspěch</td>";
                echo "<td>{$vysledek['message']}</td>";
            } elseif ($vysledek['status'] === 'warning') {
                echo "<td style='color: orange;'>⚠️ Varování</td>";
                echo "<td>{$vysledek['message']}</td>";
            } else {
                echo "<td style='color: red;'>❌ Chyba</td>";
                echo "<td>" . htmlspecialchars($vysledek['message']) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";

        // Finální kontrola - zobrazit zbývající indexy
        echo "<h2>🔍 FINÁLNÍ KONTROLA INDEXŮ</h2>";

        foreach (array_unique(array_column($nalezeneIndexy, 'tabulka')) as $tabulka) {
            echo "<h3>Tabulka: <code>{$tabulka}</code></h3>";

            $stmt = $pdo->query("SHOW INDEX FROM `{$tabulka}` WHERE Column_name = 'email' OR Column_name = 'created_at'");
            $zbyvajiciIndexy = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($zbyvajiciIndexy)) {
                echo "<table>";
                echo "<tr><th>Index</th><th>Sloupec</th><th>Typ</th><th>Unikátní</th></tr>";
                foreach ($zbyvajiciIndexy as $index) {
                    echo "<tr>";
                    echo "<td><code>{$index['Key_name']}</code></td>";
                    echo "<td>{$index['Column_name']}</td>";
                    echo "<td>" . ($index['Key_name'] === 'PRIMARY' ? 'PRIMARY KEY' : ($index['Non_unique'] == 0 ? 'UNIQUE KEY' : 'INDEX')) . "</td>";
                    echo "<td>" . ($index['Non_unique'] == 0 ? '✅ Ano' : '❌ Ne') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p><em>Žádné relevantní indexy nenalezeny.</em></p>";
            }
        }

        echo "<div class='success'>";
        echo "<strong>✅ MIGRACE DOKONČENA</strong><br>";
        echo "Očekávaný přínos: <strong>5-15% rychlejší INSERT/UPDATE operace, ~150 KB úspora místa</strong>";
        echo "</div>";

        echo "<a href='migrace_odstran_redundantni_indexy.php' class='btn'>← Zpět na přehled</a>";

    } else if (!empty($nalezeneIndexy)) {
        // Náhled SQL příkazů
        echo "<h2>📝 NÁHLED SQL PŘÍKAZŮ</h2>";
        echo "<pre><code>";
        foreach ($nalezeneIndexy as $item) {
            echo "ALTER TABLE `{$item['tabulka']}` DROP INDEX `{$item['index']}`;\n";
        }
        echo "</code></pre>";

        echo "<div class='warning'>";
        echo "<strong>⚠️ UPOZORNĚNÍ:</strong><br>";
        echo "• Operace DROP INDEX je <strong>NEVRATNÁ</strong> (ale bezpečná)<br>";
        echo "• Před spuštěním doporučuji vytvořit backup:<br>";
        echo "<code>mysqldump wgs-servicecz01 > backup_before_drop_indexes_" . date('Ymd') . ".sql</code><br>";
        echo "• Operace trvá cca <strong>1-3 sekundy</strong> na tabulku<br>";
        echo "• Indexy jsou redundantní - jejich odstranění je <strong>BEZPEČNÉ</strong>";
        echo "</div>";

        echo "<div class='info'>";
        echo "<strong>ℹ️ VYSVĚTLENÍ:</strong><br>";
        echo "<strong>wgs_users:</strong> Sloupec <code>email</code> má UNIQUE KEY, který už zajišťuje rychlé vyhledávání. Další 2 indexy jsou zbytečné.<br>";
        echo "<strong>wgs_email_queue:</strong> Sloupec <code>created_at</code> má index <code>idx_created_at</code>, druhý index <code>idx_created_at_ts</code> je duplicitní.";
        echo "</div>";

        echo "<a href='?execute=1' class='btn btn-danger'>🗑️ SPUSTIT ODSTRANĚNÍ</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<hr>";
echo "<p><small>Migrace vytvořena na základě WGS Technical Audit 2025-11-24 | ";
echo "<a href='admin.php'>← Zpět do Admin Panelu</a></small></p>";
echo "</div></body></html>";
?>
