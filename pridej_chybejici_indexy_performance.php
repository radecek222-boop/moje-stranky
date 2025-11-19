<?php
/**
 * Migrace: Přidání chybějících performance indexů
 *
 * Tento skript BEZPEČNĚ přidá doporučené indexy pro zrychlení dotazů.
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
 *
 * Indexy, které budou přidány:
 * - wgs_content_texts.updated_at (pro rychlejší filtrování aktualizací)
 * - wgs_email_queue.updated_at (pro rychlejší správu email fronty)
 * - wgs_users.created_at (pro rychlejší řazení uživatelů)
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
    <title>Migrace: Performance Indexy</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
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
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: 600; }
        .code { background: #f5f5f5; padding: 10px; border-radius: 5px;
                font-family: monospace; font-size: 0.9rem; margin: 10px 0; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Přidání Performance Indexů</h1>";

    // Definice indexů, které chceme přidat
    $indexy = [
        [
            'table' => 'wgs_content_texts',
            'column' => 'updated_at',
            'index_name' => 'idx_updated_at',
            'popis' => 'Zrychlení filtrování podle data aktualizace obsahu'
        ],
        [
            'table' => 'wgs_email_queue',
            'column' => 'updated_at',
            'index_name' => 'idx_updated_at',
            'popis' => 'Zrychlení správy email fronty'
        ],
        [
            'table' => 'wgs_users',
            'column' => 'created_at',
            'index_name' => 'idx_created_at',
            'popis' => 'Zrychlení řazení uživatelů podle data vytvoření'
        ]
    ];

    // Kontrolní fáze
    echo "<div class='info'><strong>KONTROLA AKTUÁLNÍHO STAVU...</strong></div>";

    echo "<table>";
    echo "<tr><th>Tabulka</th><th>Sloupec</th><th>Index</th><th>Status</th></tr>";

    $indexyKPridani = [];
    $jizExistujici = 0;

    foreach ($indexy as $index) {
        $table = $index['table'];
        $indexName = $index['index_name'];
        $column = $index['column'];

        // Kontrola, zda tabulka existuje
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        if (!$stmt->fetch()) {
            echo "<tr>";
            echo "<td>{$table}</td>";
            echo "<td>{$column}</td>";
            echo "<td>{$indexName}</td>";
            echo "<td style='color: #dc3545;'>❌ Tabulka neexistuje</td>";
            echo "</tr>";
            continue;
        }

        // Kontrola, zda sloupec existuje
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
        if (!$stmt->fetch()) {
            echo "<tr>";
            echo "<td>{$table}</td>";
            echo "<td>{$column}</td>";
            echo "<td>{$indexName}</td>";
            echo "<td style='color: #dc3545;'>❌ Sloupec neexistuje</td>";
            echo "</tr>";
            continue;
        }

        // Kontrola, zda index již existuje
        $stmt = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'");
        if ($stmt->fetch()) {
            echo "<tr>";
            echo "<td>{$table}</td>";
            echo "<td>{$column}</td>";
            echo "<td>{$indexName}</td>";
            echo "<td style='color: #28a745;'>✅ Již existuje</td>";
            echo "</tr>";
            $jizExistujici++;
        } else {
            echo "<tr>";
            echo "<td>{$table}</td>";
            echo "<td>{$column}</td>";
            echo "<td>{$indexName}</td>";
            echo "<td style='color: #ffc107;'>⚠️ Chybí - bude přidán</td>";
            echo "</tr>";
            $indexyKPridani[] = $index;
        }
    }

    echo "</table>";

    if (empty($indexyKPridani)) {
        echo "<div class='success'>";
        echo "<strong>✅ VŠECHNY INDEXY JIŽ EXISTUJÍ</strong><br>";
        echo "Není třeba provádět žádné změny. Všech {$jizExistujici} doporučených indexů je již v databázi.";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "<strong>⚠️ NALEZENO " . count($indexyKPridani) . " CHYBĚJÍCÍCH INDEXŮ</strong><br>";
        echo "Kliknutím na tlačítko níže přidáte tyto indexy do databáze.";
        echo "</div>";

        // Automatický režim - pokud je ?auto=1, automaticky provést
        $autoMode = isset($_GET['auto']) && $_GET['auto'] === '1';
        $executeMode = isset($_GET['execute']) && $_GET['execute'] === '1';

        // Pokud je auto režim a není execute, přesměrovat na execute
        if ($autoMode && !$executeMode) {
            echo "<div class='info'>";
            echo "<strong>🤖 AUTOMATICKÝ REŽIM AKTIVNÍ</strong><br>";
            echo "Spouštím migraci automaticky...";
            echo "</div>";
            echo "<script>window.location.href = '?execute=1';</script>";
            echo "<meta http-equiv='refresh' content='1;url=?execute=1'>";
            exit;
        }

        // Pokud je nastaveno ?execute=1, provést migraci
        if ($executeMode) {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            $uspesne = 0;
            $chyby = 0;

            foreach ($indexyKPridani as $index) {
                $table = $index['table'];
                $column = $index['column'];
                $indexName = $index['index_name'];
                $popis = $index['popis'];

                try {
                    $sql = "ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)";
                    echo "<div class='info'>";
                    echo "<strong>Přidávám index:</strong> {$table}.{$column}<br>";
                    echo "<div class='code'>{$sql}</div>";
                    echo "</div>";

                    $pdo->exec($sql);

                    echo "<div class='success'>";
                    echo "✅ <strong>Úspěch:</strong> Index {$indexName} přidán do tabulky {$table}<br>";
                    echo "<em>{$popis}</em>";
                    echo "</div>";
                    $uspesne++;

                } catch (PDOException $e) {
                    echo "<div class='error'>";
                    echo "<strong>❌ CHYBA při přidávání indexu {$indexName}:</strong><br>";
                    echo htmlspecialchars($e->getMessage());
                    echo "</div>";
                    $chyby++;
                }
            }

            // Finální shrnutí
            echo "<div class='success'>";
            echo "<h2>✅ MIGRACE DOKONČENA</h2>";
            echo "<strong>Úspěšně přidáno indexů:</strong> {$uspesne}<br>";
            if ($chyby > 0) {
                echo "<strong style='color: #dc3545;'>Chyb:</strong> {$chyby}<br>";
            }
            echo "<br>";
            echo "<strong>Výsledek:</strong> Databázové dotazy na těchto sloupcích budou nyní rychlejší.";
            echo "</div>";

            // Pokud je nastaveno redirect, automaticky přesměrovat
            $redirectUrl = $_GET['redirect'] ?? null;
            if ($redirectUrl && $autoMode) {
                echo "<div class='info'>";
                echo "<strong>✅ Hotovo! Přesměrovávám...</strong>";
                echo "</div>";
                echo "<script>setTimeout(function() { window.location.href = '" . htmlspecialchars($redirectUrl) . "'; }, 2000);</script>";
                echo "<meta http-equiv='refresh' content='2;url=" . htmlspecialchars($redirectUrl) . "'>";
            } else {
                echo "<a href='admin.php' class='btn'>← Zpět do Admin Panelu</a>";
                echo "<a href='vsechny_tabulky.php' class='btn'>Zobrazit strukturu DB</a>";
            }

        } else {
            // Náhled co bude provedeno
            echo "<h2>Náhled změn:</h2>";
            foreach ($indexyKPridani as $index) {
                echo "<div class='info'>";
                echo "<strong>{$index['table']}.{$index['column']}</strong><br>";
                echo "<em>{$index['popis']}</em><br>";
                echo "<div class='code'>ALTER TABLE `{$index['table']}` ADD INDEX `{$index['index_name']}` (`{$index['column']}`);</div>";
                echo "</div>";
            }

            echo "<a href='?execute=1' class='btn'>✅ SPUSTIT MIGRACI (" . count($indexyKPridani) . " indexů)</a>";
            echo "<a href='admin.php' class='btn' style='background: #6c757d;'>← Zrušit</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>KRITICKÁ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
