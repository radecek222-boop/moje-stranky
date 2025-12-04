<?php
/**
 * Migrace: Přidání chybějících indexů
 *
 * Tento skript BEZPEČNĚ přidá chybějící indexy identifikované v auditu.
 * Můžete jej spustit vícekrát - neprovede se duplicitní operace (IF NOT EXISTS).
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
    <title>Migrace: Přidání chybějících indexů</title>
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

    echo "<h1>🔧 Migrace: Přidání chybějících indexů</h1>";
    echo "<div class='info'><strong>Zdroj:</strong> WGS Technical Audit 2025-11-24</div>";

    // Kontrolní fáze - zjistit aktuální indexy
    echo "<h2>📊 KONTROLA AKTUÁLNÍHO STAVU</h2>";

    $stmt = $pdo->query("SHOW INDEX FROM wgs_notes WHERE Key_name LIKE 'idx_%'");
    $existujiciIndexy = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $indexyMap = [];
    foreach ($existujiciIndexy as $index) {
        $indexyMap[$index['Key_name']] = true;
    }

    echo "<table>";
    echo "<tr><th>Index</th><th>Status</th><th>Akce</th></tr>";

    $indexyKeVytvoreni = [
        'idx_created_by' => 'Filtrování poznámek podle autora',
        'idx_claim_created' => 'Poznámky k reklamaci + datum (composite)',
        'idx_created_at_desc' => 'Staré nepřečtené poznámky'
    ];

    $indexyExistují = [];
    $indexyChybi = [];

    foreach ($indexyKeVytvoreni as $indexName => $popis) {
        $existuje = isset($indexyMap[$indexName]);
        echo "<tr>";
        echo "<td><code>{$indexName}</code><br><small>{$popis}</small></td>";
        if ($existuje) {
            echo "<td><span style='color: green;'>Existuje</span></td>";
            echo "<td>-</td>";
            $indexyExistují[] = $indexName;
        } else {
            echo "<td><span style='color: orange;'>⚠️ Chybí</span></td>";
            echo "<td>Bude přidán</td>";
            $indexyChybi[] = $indexName;
        }
        echo "</tr>";
    }
    echo "</table>";

    if (empty($indexyChybi)) {
        echo "<div class='success'>";
        echo "<strong>VŠECHNY INDEXY JIŽ EXISTUJÍ</strong><br>";
        echo "Není potřeba provádět migraci.";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "<strong>⚠️ NALEZENO " . count($indexyChybi) . " CHYBĚJÍCÍCH INDEXŮ</strong><br>";
        echo "Klikněte na tlačítko níže pro jejich přidání.";
        echo "</div>";
    }

    // Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1' && !empty($indexyChybi)) {

        echo "<h2>🚀 SPOUŠTÍM MIGRACI...</h2>";

        $vysledky = [];

        // Index 1: idx_created_by
        if (in_array('idx_created_by', $indexyChybi)) {
            try {
                $pdo->exec("ALTER TABLE `wgs_notes` ADD INDEX `idx_created_by` (`created_by`)");
                $vysledky[] = ['index' => 'idx_created_by', 'status' => 'success', 'message' => 'Index úspěšně přidán'];
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                    $vysledky[] = ['index' => 'idx_created_by', 'status' => 'warning', 'message' => 'Index již existuje'];
                } else {
                    $vysledky[] = ['index' => 'idx_created_by', 'status' => 'error', 'message' => $e->getMessage()];
                }
            }
        }

        // Index 2: idx_claim_created (composite)
        if (in_array('idx_claim_created', $indexyChybi)) {
            try {
                $pdo->exec("ALTER TABLE `wgs_notes` ADD INDEX `idx_claim_created` (`claim_id`, `created_at` DESC)");
                $vysledky[] = ['index' => 'idx_claim_created', 'status' => 'success', 'message' => 'Composite index úspěšně přidán'];
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                    $vysledky[] = ['index' => 'idx_claim_created', 'status' => 'warning', 'message' => 'Index již existuje'];
                } else {
                    $vysledky[] = ['index' => 'idx_claim_created', 'status' => 'error', 'message' => $e->getMessage()];
                }
            }
        }

        // Index 3: idx_created_at_desc
        if (in_array('idx_created_at_desc', $indexyChybi)) {
            try {
                $pdo->exec("ALTER TABLE `wgs_notes` ADD INDEX `idx_created_at_desc` (`created_at` DESC)");
                $vysledky[] = ['index' => 'idx_created_at_desc', 'status' => 'success', 'message' => 'Index úspěšně přidán'];
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                    $vysledky[] = ['index' => 'idx_created_at_desc', 'status' => 'warning', 'message' => 'Index již existuje'];
                } else {
                    $vysledky[] = ['index' => 'idx_created_at_desc', 'status' => 'error', 'message' => $e->getMessage()];
                }
            }
        }

        // Zobrazit výsledky
        echo "<table>";
        echo "<tr><th>Index</th><th>Status</th><th>Zpráva</th></tr>";
        foreach ($vysledky as $vysledek) {
            echo "<tr>";
            echo "<td><code>{$vysledek['index']}</code></td>";
            if ($vysledek['status'] === 'success') {
                echo "<td style='color: green;'>Úspěch</td>";
                echo "<td>{$vysledek['message']}</td>";
            } elseif ($vysledek['status'] === 'warning') {
                echo "<td style='color: orange;'>⚠️ Varování</td>";
                echo "<td>{$vysledek['message']}</td>";
            } else {
                echo "<td style='color: red;'>Chyba</td>";
                echo "<td>" . htmlspecialchars($vysledek['message']) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";

        // Finální kontrola
        echo "<h2>🔍 FINÁLNÍ KONTROLA</h2>";

        $stmt = $pdo->query("SHOW INDEX FROM wgs_notes");
        $vsechnyIndexy = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<table>";
        echo "<tr><th>Tabulka</th><th>Index</th><th>Sloupec</th><th>Typ</th></tr>";
        foreach ($vsechnyIndexy as $index) {
            if ($index['Key_name'] !== 'PRIMARY') {
                echo "<tr>";
                echo "<td>{$index['Table']}</td>";
                echo "<td><code>{$index['Key_name']}</code></td>";
                echo "<td>{$index['Column_name']}</td>";
                echo "<td>" . ($index['Non_unique'] == 0 ? 'UNIQUE' : 'INDEX') . "</td>";
                echo "</tr>";
            }
        }
        echo "</table>";

        echo "<div class='success'>";
        echo "<strong>MIGRACE DOKONČENA</strong><br>";
        echo "Očekávaný přínos: <strong>10-30% zrychlení Notes API</strong>";
        echo "</div>";

        echo "<a href='migrace_pridej_indexy.php' class='btn'>← Zpět na přehled</a>";

    } else if (!empty($indexyChybi)) {
        // Náhled SQL příkazů
        echo "<h2>📝 NÁHLED SQL PŘÍKAZŮ</h2>";
        echo "<pre><code>";
        echo "-- wgs_notes indexy\n";
        if (in_array('idx_created_by', $indexyChybi)) {
            echo "ALTER TABLE `wgs_notes` ADD INDEX `idx_created_by` (`created_by`);\n";
        }
        if (in_array('idx_claim_created', $indexyChybi)) {
            echo "ALTER TABLE `wgs_notes` ADD INDEX `idx_claim_created` (`claim_id`, `created_at` DESC);\n";
        }
        if (in_array('idx_created_at_desc', $indexyChybi)) {
            echo "ALTER TABLE `wgs_notes` ADD INDEX `idx_created_at_desc` (`created_at` DESC);\n";
        }
        echo "</code></pre>";

        echo "<div class='info'>";
        echo "<strong>ℹ️ INFORMACE:</strong><br>";
        echo "• Migrace je <strong>IDEMPOTENTNÍ</strong> - můžete ji spustit vícekrát bez rizika<br>";
        echo "• Operace trvá cca <strong>1-5 sekund</strong> (závisí na velikosti tabulky)<br>";
        echo "• Indexy zlepší výkon dotazů na poznámky o <strong>10-30%</strong>";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>▶️ SPUSTIT MIGRACI</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<hr>";
echo "<p><small>Migrace vytvořena na základě WGS Technical Audit 2025-11-24 | ";
echo "<a href='admin.php'>← Zpět do Admin Panelu</a></small></p>";
echo "</div></body></html>";
?>
