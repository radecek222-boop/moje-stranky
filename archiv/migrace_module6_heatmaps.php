<?php
/**
 * Migrace: Modul #6 - Heatmap Engine
 *
 * Tento skript BEZPEČNĚ vytvoří tabulky pro ukládání agregovaných heatmap dat.
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
 *
 * Co tento skript dělá:
 * 1. Vytvoří tabulku `wgs_analytics_heatmap_clicks` pro agregované click data
 * 2. Vytvoří tabulku `wgs_analytics_heatmap_scroll` pro agregované scroll data
 * 3. Přidá všechny potřebné indexy pro optimální výkon
 * 4. Integruje se s Modulem #5 (Event Tracking) jako agregační vrstva
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #6 - Heatmap Engine
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
    <title>Migrace: Modul #6 - Heatmap Engine</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
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
            color: #333333;
            border-bottom: 3px solid #333333;
            padding-bottom: 10px;
        }
        h2 {
            color: #333333;
            margin-top: 30px;
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
            background: #333333;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
            font-weight: bold;
        }
        .btn:hover {
            background: #1a300d;
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
            background: #333333;
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

    echo "<h1>Migrace: Modul #6 - Heatmap Engine</h1>";
    echo "<p><strong>Datum:</strong> " . date('d.m.Y H:i:s') . "</p>";

    // =======================================================
    // KONTROLNÍ FÁZE - Zjistit aktuální stav databáze
    // =======================================================
    echo "<h2>KROK 1: Kontrola aktuálního stavu</h2>";

    $tabulkyKKontrole = [
        'wgs_analytics_heatmap_clicks',
        'wgs_analytics_heatmap_scroll'
    ];

    $existujiciTabulky = [];
    $chybejiciTabulky = [];

    foreach ($tabulkyKKontrole as $tabulka) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$tabulka}'");
        if ($stmt && $stmt->rowCount() > 0) {
            $existujiciTabulky[] = $tabulka;
        } else {
            $chybejiciTabulky[] = $tabulka;
        }
    }

    // Zobrazit výsledky kontroly
    if (count($existujiciTabulky) > 0) {
        echo "<div class='warning'>";
        echo "<strong>VAROVÁNÍ:</strong> Některé tabulky již existují:<br>";
        foreach ($existujiciTabulky as $tab) {
            echo "- <code>{$tab}</code><br>";
        }
        echo "</div>";
    }

    if (count($chybejiciTabulky) > 0) {
        echo "<div class='info'>";
        echo "<strong>INFO:</strong> Následující tabulky budou vytvořeny:<br>";
        foreach ($chybejiciTabulky as $tab) {
            echo "- <code>{$tab}</code><br>";
        }
        echo "</div>";
    } else {
        echo "<div class='success'>";
        echo "<strong>DOKONČENO:</strong> Všechny tabulky pro Modul #6 již existují.";
        echo "</div>";
        echo "<a href='admin.php' class='btn'>Zpět na Admin</a>";
        echo "</div></body></html>";
        exit;
    }

    // =======================================================
    // POKUD JE ?execute=1, PROVÉST MIGRACI
    // =======================================================
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<h2>KROK 2: Spouštím migraci...</h2>";

        $pdo->beginTransaction();

        try {
            $vytvorenoTabulek = 0;
            $pridanoIndexu = 0;

            // ===============================================
            // TABULKA 1: wgs_analytics_heatmap_clicks
            // ===============================================
            if (in_array('wgs_analytics_heatmap_clicks', $chybejiciTabulky)) {
                echo "<div class='info'><strong>Vytvářím tabulku:</strong> wgs_analytics_heatmap_clicks</div>";

                $pdo->exec("
                    CREATE TABLE `wgs_analytics_heatmap_clicks` (
                        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `page_url` VARCHAR(500) NOT NULL COMMENT 'URL stránky (normalizovaná bez query params)',
                        `device_type` ENUM('desktop', 'mobile', 'tablet') NOT NULL COMMENT 'Typ zařízení',
                        `click_x_percent` DECIMAL(5,2) NOT NULL COMMENT 'X pozice jako % viewportu (0-100)',
                        `click_y_percent` DECIMAL(5,2) NOT NULL COMMENT 'Y pozice jako % viewportu (0-100)',
                        `click_count` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Počet kliků na této pozici',
                        `viewport_width_avg` SMALLINT UNSIGNED NULL COMMENT 'Průměrná šířka viewportu (px)',
                        `viewport_height_avg` SMALLINT UNSIGNED NULL COMMENT 'Průměrná výška viewportu (px)',
                        `first_click` DATETIME NOT NULL COMMENT 'První klik na této pozici',
                        `last_click` DATETIME NOT NULL COMMENT 'Poslední klik na této pozici',
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Čas vytvoření záznamu',
                        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Čas poslední aktualizace',

                        PRIMARY KEY (`id`),
                        UNIQUE INDEX `idx_unique_position` (`page_url`(255), `device_type`, `click_x_percent`, `click_y_percent`),
                        INDEX `idx_page_url` (`page_url`(255)),
                        INDEX `idx_device_type` (`device_type`),
                        INDEX `idx_click_count` (`click_count`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Agregované click data pro heatmap vizualizaci'
                ");

                $vytvorenoTabulek++;
                $pridanoIndexu += 5; // PRIMARY + 4 indexy

                echo "<div class='success'>Tabulka <code>wgs_analytics_heatmap_clicks</code> úspěšně vytvořena (12 sloupců, 5 indexů)</div>";
            }

            // ===============================================
            // TABULKA 2: wgs_analytics_heatmap_scroll
            // ===============================================
            if (in_array('wgs_analytics_heatmap_scroll', $chybejiciTabulky)) {
                echo "<div class='info'><strong>Vytvářím tabulku:</strong> wgs_analytics_heatmap_scroll</div>";

                $pdo->exec("
                    CREATE TABLE `wgs_analytics_heatmap_scroll` (
                        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `page_url` VARCHAR(500) NOT NULL COMMENT 'URL stránky',
                        `device_type` ENUM('desktop', 'mobile', 'tablet') NOT NULL COMMENT 'Typ zařízení',
                        `scroll_depth_bucket` TINYINT UNSIGNED NOT NULL COMMENT 'Bucket scroll depth (0, 10, 20, ..., 100)',
                        `reach_count` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Kolik uživatelů dosáhlo této hloubky',
                        `viewport_width_avg` SMALLINT UNSIGNED NULL COMMENT 'Průměrná šířka viewportu',
                        `viewport_height_avg` SMALLINT UNSIGNED NULL COMMENT 'Průměrná výška viewportu',
                        `first_reach` DATETIME NOT NULL COMMENT 'První dosažení této hloubky',
                        `last_reach` DATETIME NOT NULL COMMENT 'Poslední dosažení',
                        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Čas aktualizace',

                        PRIMARY KEY (`id`),
                        UNIQUE INDEX `idx_unique_bucket` (`page_url`(255), `device_type`, `scroll_depth_bucket`),
                        INDEX `idx_page_url` (`page_url`(255)),
                        INDEX `idx_device_type` (`device_type`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Agregované scroll depth data pro heatmap vizualizaci'
                ");

                $vytvorenoTabulek++;
                $pridanoIndexu += 4; // PRIMARY + 3 indexy

                echo "<div class='success'>Tabulka <code>wgs_analytics_heatmap_scroll</code> úspěšně vytvořena (10 sloupců, 4 indexy)</div>";
            }

            // Commit transakce
            $pdo->commit();

            // =======================================================
            // SOUHRN MIGRACE
            // =======================================================
            echo "<h2>SHRNUTÍ MIGRACE</h2>";

            echo "<table>";
            echo "<tr><th>Operace</th><th>Počet</th></tr>";
            echo "<tr><td>Vytvořeno tabulek</td><td><strong>{$vytvorenoTabulek}</strong></td></tr>";
            echo "<tr><td>Přidáno indexů</td><td><strong>{$pridanoIndexu}</strong></td></tr>";
            echo "</table>";

            echo "<div class='success'>";
            echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>";
            echo "Modul #6 (Heatmap Engine) je nyní připraven k použití.";
            echo "</div>";

            // =======================================================
            // NEXT STEPS
            // =======================================================
            echo "<h2>DALŠÍ KROKY</h2>";
            echo "<div class='info'>";
            echo "<strong>Co dál:</strong><br>";
            echo "1. Zkontrolujte strukturu tabulek přes SQL kartu v Control Centre<br>";
            echo "2. Otevřete admin UI: <code>analytics-heatmap.php</code><br>";
            echo "3. Vyberte stránku a typ heatmap (click/scroll)<br>";
            echo "4. Klikejte na stránky a sledujte agregaci v real-time<br>";
            echo "5. Zkontrolujte vizualizaci s barevným gradientem (modrá → červená)<br>";
            echo "</div>";

            echo "<a href='admin.php' class='btn'>Zpět na Admin</a>";
            echo "<a href='vsechny_tabulky.php' class='btn'>Zobrazit SQL strukturu</a>";
            echo "<a href='analytics-heatmap.php' class='btn'>Otevřít Heatmap Viewer</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();

            echo "<div class='error'>";
            echo "<strong>CHYBA PŘI MIGRACI:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";

            error_log("Migrace Module #6 FAILED: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
        }

    } else {
        // =======================================================
        // ZOBRAZIT NÁHLED CO BUDE PROVEDENO
        // =======================================================
        echo "<h2>KROK 2: Co bude provedeno?</h2>";

        echo "<div class='info'>";
        echo "<strong>Tato migrace provede následující operace:</strong>";
        echo "<ol>";
        echo "<li>Vytvoří tabulku <code>wgs_analytics_heatmap_clicks</code> (12 sloupců, 5 indexů)</li>";
        echo "<li>Vytvoří tabulku <code>wgs_analytics_heatmap_scroll</code> (10 sloupců, 4 indexy)</li>";
        echo "<li>Nastaví UNIQUE indexy pro prevenci duplikátů při agregaci</li>";
        echo "</ol>";
        echo "</div>";

        echo "<h3>Detaily tabulky: wgs_analytics_heatmap_clicks</h3>";
        echo "<table>";
        echo "<tr><th>Sloupec</th><th>Typ</th><th>Popis</th></tr>";
        echo "<tr><td>id</td><td>BIGINT UNSIGNED PK</td><td>Primární klíč</td></tr>";
        echo "<tr><td>page_url</td><td>VARCHAR(500) INDEX</td><td>URL stránky (normalizovaná)</td></tr>";
        echo "<tr><td>device_type</td><td>ENUM INDEX</td><td>desktop/mobile/tablet</td></tr>";
        echo "<tr><td>click_x_percent</td><td>DECIMAL(5,2)</td><td>X pozice % (0-100)</td></tr>";
        echo "<tr><td>click_y_percent</td><td>DECIMAL(5,2)</td><td>Y pozice % (0-100)</td></tr>";
        echo "<tr><td>click_count</td><td>INT UNSIGNED INDEX</td><td>Počet kliků (agregace)</td></tr>";
        echo "<tr><td>viewport_width_avg</td><td>SMALLINT UNSIGNED</td><td>Průměrná šířka viewportu</td></tr>";
        echo "<tr><td>viewport_height_avg</td><td>SMALLINT UNSIGNED</td><td>Průměrná výška viewportu</td></tr>";
        echo "<tr><td>first_click</td><td>DATETIME</td><td>První klik</td></tr>";
        echo "<tr><td>last_click</td><td>DATETIME</td><td>Poslední klik</td></tr>";
        echo "<tr><td>created_at</td><td>DATETIME</td><td>Čas vytvoření</td></tr>";
        echo "<tr><td>updated_at</td><td>DATETIME</td><td>Čas aktualizace (auto)</td></tr>";
        echo "</table>";

        echo "<h3>Detaily tabulky: wgs_analytics_heatmap_scroll</h3>";
        echo "<table>";
        echo "<tr><th>Sloupec</th><th>Typ</th><th>Popis</th></tr>";
        echo "<tr><td>id</td><td>BIGINT UNSIGNED PK</td><td>Primární klíč</td></tr>";
        echo "<tr><td>page_url</td><td>VARCHAR(500) INDEX</td><td>URL stránky</td></tr>";
        echo "<tr><td>device_type</td><td>ENUM INDEX</td><td>desktop/mobile/tablet</td></tr>";
        echo "<tr><td>scroll_depth_bucket</td><td>TINYINT UNSIGNED</td><td>Bucket (0, 10, 20, ..., 100)</td></tr>";
        echo "<tr><td>reach_count</td><td>INT UNSIGNED</td><td>Počet dosažení (agregace)</td></tr>";
        echo "<tr><td>viewport_width_avg</td><td>SMALLINT UNSIGNED</td><td>Průměrná šířka</td></tr>";
        echo "<tr><td>viewport_height_avg</td><td>SMALLINT UNSIGNED</td><td>Průměrná výška</td></tr>";
        echo "<tr><td>first_reach</td><td>DATETIME</td><td>První dosažení</td></tr>";
        echo "<tr><td>last_reach</td><td>DATETIME</td><td>Poslední dosažení</td></tr>";
        echo "<tr><td>updated_at</td><td>DATETIME</td><td>Čas aktualizace</td></tr>";
        echo "</table>";

        echo "<h3>Klíčové vlastnosti</h3>";
        echo "<ul>";
        echo "<li><strong>UPSERT pattern:</strong> INSERT ON DUPLICATE KEY UPDATE pro agregaci</li>";
        echo "<li><strong>Normalizace URL:</strong> Odstranění query parametrů před uložením</li>";
        echo "<li><strong>Bucket strategie:</strong> Scroll depth zaokrouhlena na 10% intervaly (0, 10, 20, ..., 100)</li>";
        echo "<li><strong>Device-specific:</strong> Separátní data pro desktop/mobile/tablet</li>";
        echo "<li><strong>Performance:</strong> UNIQUE indexy zabraňují duplikátům při vysokém traffic</li>";
        echo "</ul>";

        echo "<div class='warning'>";
        echo "<strong>POZOR:</strong> Tato migrace je BEZPEČNÁ a IDEMPOTENTNÍ (můžete ji spustit vícekrát).";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='admin.php' class='btn' style='background: #6c757d;'>Zrušit</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>NEOČEKÁVANÁ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";

    error_log("Migrace Module #6 Exception: " . $e->getMessage());
}

echo "</div></body></html>";
?>
