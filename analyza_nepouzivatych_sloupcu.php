<?php
/**
 * Analýza nepoužívaných sloupců v databázi
 *
 * Tento skript analyzuje tabulku wgs_reklamace a najde:
 * 1. Sloupce, které jsou vždy NULL (nikdy se nepoužívají)
 * 2. Duplicitní sloupce (např. cena vs cena_celkem)
 * 3. Sloupce, které se NEPOUŽÍVAJÍ v PHP kódu
 * 4. Doporučení co odstranit
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit analýzu.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Analýza nepoužívaných sloupců</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1400px; margin: 20px auto; padding: 20px;
               background: #1e1e1e; color: #d4d4d4; }
        .container { background: #252526; padding: 20px; border-radius: 8px; }
        h1, h2 { color: #4ec9b0; }
        .success { background: #1e5a1e; border-left: 3px solid #4ec9b0; padding: 12px; margin: 10px 0; }
        .error { background: #5a1e1e; border-left: 3px solid #f48771; padding: 12px; margin: 10px 0; }
        .warning { background: #5a4e1e; border-left: 3px solid #dcdcaa; padding: 12px; margin: 10px 0; }
        .info { background: #1e3a5a; border-left: 3px solid #4fc1ff; padding: 12px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #3e3e42; padding: 10px; text-align: left; }
        th { background: #2d2d30; color: #4ec9b0; }
        code { background: #3c3c3c; padding: 3px 8px; border-radius: 3px; color: #ce9178; }
        .btn { display: inline-block; padding: 10px 20px; background: #0e639c;
               color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #1177bb; }
        .btn-danger { background: #c82333; }
        .btn-danger:hover { background: #bd2130; }
        .status-unused { color: #f48771; font-weight: bold; }
        .status-used { color: #4ec9b0; font-weight: bold; }
        .status-maybe { color: #dcdcaa; font-weight: bold; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 Analýza nepoužívaných sloupců v tabulce wgs_reklamace</h1>";

    // 1. Získat všechny sloupce
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
    $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='info'>";
    echo "<strong>Celkem sloupců:</strong> " . count($sloupce);
    echo "</div>";

    // 2. Zjistit počet záznamů
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM wgs_reklamace");
    $pocetZaznamu = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo "<div class='info'>";
    echo "<strong>Celkem záznamů:</strong> {$pocetZaznamu}";
    echo "</div>";

    // 3. Analyzovat každý sloupec
    echo "<h2>1. Analýza NULL hodnot v každém sloupci</h2>";

    $vzhdyNull = [];
    $casteNull = [];
    $pouzivane = [];

    foreach ($sloupce as $sloupec) {
        $nazevSloupce = $sloupec['Field'];

        // Spočítat NULL hodnoty
        $stmt = $pdo->prepare("SELECT COUNT(*) as null_count FROM wgs_reklamace WHERE `{$nazevSloupce}` IS NULL");
        $stmt->execute();
        $nullCount = $stmt->fetch(PDO::FETCH_ASSOC)['null_count'];

        $procentoNull = $pocetZaznamu > 0 ? round(($nullCount / $pocetZaznamu) * 100, 2) : 0;

        if ($nullCount === $pocetZaznamu && $pocetZaznamu > 0) {
            // Všechny hodnoty jsou NULL
            $vzhdyNull[] = [
                'sloupec' => $nazevSloupce,
                'typ' => $sloupec['Type'],
                'null_count' => $nullCount,
                'procento' => $procentoNull
            ];
        } elseif ($procentoNull > 80) {
            // Více než 80% NULL
            $casteNull[] = [
                'sloupec' => $nazevSloupce,
                'typ' => $sloupec['Type'],
                'null_count' => $nullCount,
                'procento' => $procentoNull
            ];
        } else {
            // Používá se
            $pouzivane[] = [
                'sloupec' => $nazevSloupce,
                'typ' => $sloupec['Type'],
                'null_count' => $nullCount,
                'procento' => $procentoNull
            ];
        }
    }

    // Zobrazit sloupce vždy NULL
    if (!empty($vzhdyNull)) {
        echo "<h3>❌ Sloupce vždy NULL (lze odstranit)</h3>";
        echo "<table>";
        echo "<tr><th>Sloupec</th><th>Typ</th><th>NULL záznamů</th><th>% NULL</th></tr>";
        foreach ($vzhdyNull as $item) {
            echo "<tr>";
            echo "<td><code>{$item['sloupec']}</code></td>";
            echo "<td>{$item['typ']}</td>";
            echo "<td class='status-unused'>{$item['null_count']} / {$pocetZaznamu}</td>";
            echo "<td class='status-unused'>{$item['procento']}%</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='success'>✅ Žádné sloupce vždy NULL</div>";
    }

    // Zobrazit sloupce často NULL
    if (!empty($casteNull)) {
        echo "<h3>⚠️ Sloupce často NULL (>80%) - možná se nepoužívají</h3>";
        echo "<table>";
        echo "<tr><th>Sloupec</th><th>Typ</th><th>NULL záznamů</th><th>% NULL</th></tr>";
        foreach ($casteNull as $item) {
            echo "<tr>";
            echo "<td><code>{$item['sloupec']}</code></td>";
            echo "<td>{$item['typ']}</td>";
            echo "<td class='status-maybe'>{$item['null_count']} / {$pocetZaznamu}</td>";
            echo "<td class='status-maybe'>{$item['procento']}%</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // 4. Hledat duplicitní sloupce
    echo "<h2>2. Možné duplicitní sloupce</h2>";

    $duplicity = [
        ['sloupec1' => 'cena', 'sloupec2' => 'cena_celkem', 'popis' => 'Dva sloupce pro cenu - možná jeden je legacy'],
        ['sloupec1' => 'castka', 'sloupec2' => 'cena_celkem', 'popis' => 'Možná legacy sloupec pro cenu'],
        ['sloupec1' => 'adresa', 'sloupec2' => 'ulice + mesto + psc', 'popis' => 'Adresa v jednom sloupci vs. rozdělaná'],
    ];

    echo "<table>";
    echo "<tr><th>Možná duplicita</th><th>Popis</th><th>Akce</th></tr>";
    foreach ($duplicity as $dup) {
        echo "<tr>";
        echo "<td><code>{$dup['sloupec1']}</code> vs <code>{$dup['sloupec2']}</code></td>";
        echo "<td>{$dup['popis']}</td>";
        echo "<td>Zkontrolujte, který se používá</td>";
        echo "</tr>";
    }
    echo "</table>";

    // 5. Hledat sloupce v PHP kódu
    echo "<h2>3. Kontrola použití v PHP kódu</h2>";

    echo "<div class='info'>";
    echo "Hledám výskyty sloupců v PHP souborech...";
    echo "</div>";

    // Seznam podezřelých sloupců ke kontrole
    $podezreleSloupce = array_merge(
        array_column($vzhdyNull, 'sloupec'),
        array_column($casteNull, 'sloupec')
    );

    if (!empty($podezreleSloupce)) {
        echo "<table>";
        echo "<tr><th>Sloupec</th><th>Výskyty v PHP</th><th>Status</th></tr>";

        foreach ($podezreleSloupce as $sloupec) {
            // Hledat v PHP souborech
            $searchPaths = [
                __DIR__ . '/app',
                __DIR__ . '/api',
                __DIR__ . '/includes'
            ];

            $vyskyt = 0;
            foreach ($searchPaths as $path) {
                if (is_dir($path)) {
                    exec("grep -r \"{$sloupec}\" {$path} --include='*.php' 2>/dev/null | wc -l", $output);
                    $vyskyt += (int)($output[0] ?? 0);
                    unset($output);
                }
            }

            $status = $vyskyt > 0 ? "<span class='status-used'>✅ Používá se ({$vyskyt}×)</span>" : "<span class='status-unused'>❌ NEPOUŽÍVÁ SE</span>";

            echo "<tr>";
            echo "<td><code>{$sloupec}</code></td>";
            echo "<td>{$vyskyt}</td>";
            echo "<td>{$status}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // 6. Doporučení
    echo "<h2>4. Doporučení</h2>";

    echo "<div class='warning'>";
    echo "<strong>⚠️ DOPORUČENÍ K ODSTRANĚNÍ:</strong><br><br>";

    if (!empty($vzhdyNull)) {
        echo "<strong>Sloupce vždy NULL (bezpečné odstranit):</strong><br>";
        echo "<ul>";
        foreach ($vzhdyNull as $item) {
            echo "<li><code>{$item['sloupec']}</code> ({$item['typ']})</li>";
        }
        echo "</ul>";
    }

    if (!empty($casteNull)) {
        echo "<br><strong>Sloupce často NULL (zkontrolovat použití):</strong><br>";
        echo "<ul>";
        foreach ($casteNull as $item) {
            echo "<li><code>{$item['sloupec']}</code> ({$item['typ']}) - {$item['procento']}% NULL</li>";
        }
        echo "</ul>";
    }

    echo "<br><strong>Před odstraněním:</strong><br>";
    echo "<ol>";
    echo "<li>Udělejte zálohu databáze (Admin Panel → SQL → Stáhnout všechny DDL)</li>";
    echo "<li>Zkontrolujte výskyty v PHP kódu</li>";
    echo "<li>Vytvořte migrační skript pro odstranění</li>";
    echo "</ol>";
    echo "</div>";

    // 7. Generovat SQL pro odstranění
    if (!empty($vzhdyNull)) {
        echo "<h2>5. SQL příkazy pro odstranění (DRAFT)</h2>";

        echo "<div class='error'>";
        echo "<strong>⚠️ NEPOUŽÍVEJTE PŘÍMO! Pouze jako náhled.</strong>";
        echo "</div>";

        echo "<pre style='background: #1e1e1e; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
        echo "-- Odstranění sloupců vždy NULL\n";
        echo "ALTER TABLE wgs_reklamace\n";

        $sqlParts = [];
        foreach ($vzhdyNull as $item) {
            $sqlParts[] = "  DROP COLUMN `{$item['sloupec']}`";
        }

        echo implode(",\n", $sqlParts) . ";";
        echo "</pre>";
    }

    echo "<div class='info'>";
    echo "<strong>ℹ️ Další kroky:</strong><br>";
    echo "1. Zkontrolujte výsledky výše<br>";
    echo "2. Vytvořte migrační skript pro bezpečné odstranění<br>";
    echo "3. Testujte na testovacím prostředí<br>";
    echo "4. Spusťte na produkci";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<a href='vsechny_tabulky.php' class='btn'>← Zpět na SQL přehled</a>";
echo "<a href='admin.php' class='btn'>← Zpět do Admin Panelu</a>";

echo "</div></body></html>";
?>
