<?php
/**
 * Migrace: Přidání geolokace do heatmap tabulek
 *
 * Tento skript BEZPEČNĚ přidá sloupce pro geolokaci:
 * - country_code (CZ, SK, IT, ...)
 * - city (Praha, Brno, ...)
 * - lat, lng (GPS souřadnice)
 *
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
 *
 * @version 1.0.0
 * @date 2025-11-24
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
    <title>Migrace: Geolokace pro Heatmap</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #333;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
            font-weight: bold;
        }
        .btn:hover {
            background: #555;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        table th {
            background: #333;
            color: white;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Geolokace pro Heatmap</h1>";
    echo "<p><strong>Datum:</strong> " . date('d.m.Y H:i:s') . "</p>";

    // =======================================================
    // DEFINICE SLOUPCŮ K PŘIDÁNÍ
    // =======================================================
    $sloupcePridani = [
        'wgs_analytics_heatmap_clicks' => [
            'country_code' => "VARCHAR(2) NULL COMMENT 'ISO kód země (CZ, SK, IT, ...)'",
            'city' => "VARCHAR(100) NULL COMMENT 'Město'",
            'latitude' => "DECIMAL(10,7) NULL COMMENT 'GPS šířka'",
            'longitude' => "DECIMAL(10,7) NULL COMMENT 'GPS délka'"
        ],
        'wgs_analytics_heatmap_scroll' => [
            'country_code' => "VARCHAR(2) NULL COMMENT 'ISO kód země (CZ, SK, IT, ...)'",
            'city' => "VARCHAR(100) NULL COMMENT 'Město'",
            'latitude' => "DECIMAL(10,7) NULL COMMENT 'GPS šířka'",
            'longitude' => "DECIMAL(10,7) NULL COMMENT 'GPS délka'"
        ]
    ];

    // =======================================================
    // KONTROLNÍ FÁZE
    // =======================================================
    echo "<h2>KROK 1: Kontrola aktuálního stavu</h2>";

    $chybejiciSloupce = [];
    $existujiciSloupce = [];

    foreach ($sloupcePridani as $tabulka => $sloupce) {
        // Zkontrolovat, jestli tabulka existuje
        $stmt = $pdo->query("SHOW TABLES LIKE '{$tabulka}'");
        if (!$stmt || $stmt->rowCount() === 0) {
            echo "<div class='error'>";
            echo "<strong>CHYBA:</strong> Tabulka <code>{$tabulka}</code> neexistuje!<br>";
            echo "Nejdříve spusťte migraci: <code>/migrace_module6_heatmaps.php?execute=1</code>";
            echo "</div>";
            echo "<a href='admin.php' class='btn'>Zpět na Admin</a>";
            echo "</div></body></html>";
            exit;
        }

        // Načíst existující sloupce
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$tabulka}`");
        $existujiciSloupceTabulky = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($sloupce as $sloupec => $definice) {
            if (in_array($sloupec, $existujiciSloupceTabulky)) {
                $existujiciSloupce[] = "{$tabulka}.{$sloupec}";
            } else {
                $chybejiciSloupce[] = [
                    'tabulka' => $tabulka,
                    'sloupec' => $sloupec,
                    'definice' => $definice
                ];
            }
        }
    }

    // Zobrazit výsledky
    if (count($existujiciSloupce) > 0) {
        echo "<div class='warning'>";
        echo "<strong>INFO:</strong> Některé sloupce již existují:<br>";
        foreach ($existujiciSloupce as $s) {
            echo "- <code>{$s}</code><br>";
        }
        echo "</div>";
    }

    if (count($chybejiciSloupce) === 0) {
        echo "<div class='success'>";
        echo "<strong>DOKONČENO:</strong> Všechny geolokační sloupce již existují.";
        echo "</div>";
        echo "<a href='admin.php' class='btn'>Zpět na Admin</a>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='info'>";
    echo "<strong>INFO:</strong> Následující sloupce budou přidány:<br>";
    foreach ($chybejiciSloupce as $s) {
        echo "- <code>{$s['tabulka']}.{$s['sloupec']}</code><br>";
    }
    echo "</div>";

    // =======================================================
    // POKUD JE ?execute=1, PROVÉST MIGRACI
    // =======================================================
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<h2>KROK 2: Spouštím migraci...</h2>";

        $pridano = 0;

        foreach ($chybejiciSloupce as $s) {
            try {
                $sql = "ALTER TABLE `{$s['tabulka']}` ADD COLUMN `{$s['sloupec']}` {$s['definice']}";
                $pdo->exec($sql);
                echo "<div class='success'>Přidán sloupec: <code>{$s['tabulka']}.{$s['sloupec']}</code></div>";
                $pridano++;
            } catch (PDOException $e) {
                echo "<div class='error'>Chyba při přidání <code>{$s['tabulka']}.{$s['sloupec']}</code>: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }

        // Přidat indexy pro country_code
        echo "<h2>KROK 3: Přidávám indexy...</h2>";

        $indexy = [
            'wgs_analytics_heatmap_clicks' => 'idx_clicks_country',
            'wgs_analytics_heatmap_scroll' => 'idx_scroll_country'
        ];

        foreach ($indexy as $tabulka => $indexName) {
            try {
                // Zkontrolovat, jestli index existuje
                $stmt = $pdo->query("SHOW INDEX FROM `{$tabulka}` WHERE Key_name = '{$indexName}'");
                if ($stmt->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE `{$tabulka}` ADD INDEX `{$indexName}` (`country_code`)");
                    echo "<div class='success'>Přidán index: <code>{$tabulka}.{$indexName}</code></div>";
                } else {
                    echo "<div class='warning'>Index již existuje: <code>{$tabulka}.{$indexName}</code></div>";
                }
            } catch (PDOException $e) {
                echo "<div class='error'>Chyba při přidání indexu: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }

        echo "<h2>SHRNUTÍ</h2>";
        echo "<div class='success'>";
        echo "<strong>MIGRACE DOKONČENA</strong><br>";
        echo "Přidáno sloupců: <strong>{$pridano}</strong>";
        echo "</div>";

        echo "<a href='admin.php' class='btn'>Zpět na Admin</a>";
        echo "<a href='vsechny_tabulky.php' class='btn'>Zobrazit SQL strukturu</a>";

    } else {
        // =======================================================
        // NÁHLED
        // =======================================================
        echo "<h2>KROK 2: Co bude provedeno?</h2>";

        echo "<table>";
        echo "<tr><th>Tabulka</th><th>Sloupec</th><th>Typ</th></tr>";
        foreach ($chybejiciSloupce as $s) {
            echo "<tr>";
            echo "<td><code>{$s['tabulka']}</code></td>";
            echo "<td><code>{$s['sloupec']}</code></td>";
            echo "<td>{$s['definice']}</td>";
            echo "</tr>";
        }
        echo "</table>";

        echo "<div class='info'>";
        echo "<strong>Použití geolokace:</strong><br>";
        echo "Po migraci bude <code>track_heatmap.php</code> automaticky ukládat:<br>";
        echo "- <code>country_code</code> - ISO kód země (CZ, SK, IT, ...)<br>";
        echo "- <code>city</code> - Město návštěvníka<br>";
        echo "- <code>latitude</code>, <code>longitude</code> - GPS souřadnice<br>";
        echo "<br>";
        echo "Data se získávají z IP adresy přes <strong>Geoapify API</strong>.";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='admin.php' class='btn' style='background: #666;'>Zrušit</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>NEOČEKÁVANÁ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";

    error_log("Migrace geolokace Exception: " . $e->getMessage());
}

echo "</div></body></html>";
?>
