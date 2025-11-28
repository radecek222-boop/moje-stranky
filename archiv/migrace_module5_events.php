<?php
/**
 * Migrace: Modul #5 - Event Tracking Engine
 *
 * Tento skript BEZPEČNĚ vytvoří tabulku pro ukládání uživatelských událostí
 * (kliky, scrollování, rage clicks, copy/paste, formuláře, nečinnost).
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
 *
 * Co tento skript dělá:
 * 1. Vytvoří tabulku `wgs_analytics_events` pro všechny typy událostí
 * 2. Přidá všechny potřebné indexy pro optimální výkon
 * 3. Integruje se s Modulem #2 (Session Tracking) přes session_id a fingerprint_id
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #5 - Event Tracking Engine
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
    <title>Migrace: Modul #5 - Event Tracking Engine</title>
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

    echo "<h1>Migrace: Modul #5 - Event Tracking Engine</h1>";
    echo "<p><strong>Datum:</strong> " . date('d.m.Y H:i:s') . "</p>";

    // =======================================================
    // KONTROLNÍ FÁZE - Zjistit aktuální stav databáze
    // =======================================================
    echo "<h2>KROK 1: Kontrola aktuálního stavu</h2>";

    $tabulkyKKontrole = [
        'wgs_analytics_events'
    ];

    $existujiciTabulky = [];
    $chybejiciTabulky = [];

    foreach ($tabulkyKKontrole as $tabulka) {
        // SHOW TABLES LIKE nepodporuje bind parametry - použít přímý dotaz
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
        echo "<strong>DOKONČENO:</strong> Všechny tabulky pro Modul #5 již existují.";
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
            // TABULKA: wgs_analytics_events
            // ===============================================
            if (in_array('wgs_analytics_events', $chybejiciTabulky)) {
                echo "<div class='info'><strong>Vytvářím tabulku:</strong> wgs_analytics_events</div>";

                $pdo->exec("
                    CREATE TABLE `wgs_analytics_events` (
                        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `session_id` VARCHAR(64) NOT NULL COMMENT 'FK → wgs_analytics_sessions.session_id',
                        `fingerprint_id` VARCHAR(64) NULL COMMENT 'FK → wgs_analytics_fingerprints.fingerprint_id',

                        -- Event metadata
                        `event_type` ENUM('click', 'scroll', 'rage_click', 'copy', 'paste', 'form_focus', 'form_blur', 'idle', 'active') NOT NULL COMMENT 'Typ události',
                        `page_url` VARCHAR(500) NOT NULL COMMENT 'URL stránky kde došlo k události',
                        `timestamp` BIGINT UNSIGNED NOT NULL COMMENT 'Unix timestamp v ms (pro přesnou synchronizaci)',

                        -- Click data
                        `click_x` SMALLINT UNSIGNED NULL COMMENT 'X pozice kliku (px)',
                        `click_y` SMALLINT UNSIGNED NULL COMMENT 'Y pozice kliku (px)',
                        `click_x_percent` DECIMAL(5,2) NULL COMMENT 'X pozice jako % viewport šířky',
                        `click_y_percent` DECIMAL(5,2) NULL COMMENT 'Y pozice jako % viewport výšky',

                        -- Element data
                        `element_selector` VARCHAR(500) NULL COMMENT 'CSS selector elementu (např. div.button#submit)',
                        `element_text` VARCHAR(255) NULL COMMENT 'Text obsahu elementu (tlačítko \"Odeslat\")',
                        `element_tag` VARCHAR(50) NULL COMMENT 'HTML tag (DIV, BUTTON, A...)',

                        -- Scroll data
                        `scroll_depth` TINYINT UNSIGNED NULL COMMENT 'Scroll depth v % (0-100)',

                        -- Viewport data
                        `viewport_width` SMALLINT UNSIGNED NULL COMMENT 'Šířka viewportu (px)',
                        `viewport_height` SMALLINT UNSIGNED NULL COMMENT 'Výška viewportu (px)',

                        -- Rage click data
                        `rage_click_count` TINYINT UNSIGNED NULL COMMENT 'Počet kliků v rage sequenci (3, 4, 5...)',

                        -- Form data
                        `form_field_name` VARCHAR(255) NULL COMMENT 'Název formulářového pole',

                        -- Copy/paste data
                        `copied_text_length` SMALLINT UNSIGNED NULL COMMENT 'Délka zkopírovaného textu',

                        -- Idle/active data
                        `idle_duration` INT UNSIGNED NULL COMMENT 'Doba nečinnosti v ms',

                        -- Timestamps
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Čas uložení do DB',

                        PRIMARY KEY (`id`),
                        INDEX `idx_session_id` (`session_id`),
                        INDEX `idx_fingerprint_id` (`fingerprint_id`),
                        INDEX `idx_event_type` (`event_type`),
                        INDEX `idx_page_url` (`page_url`(255)),
                        INDEX `idx_timestamp` (`timestamp`),
                        INDEX `idx_created_at` (`created_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Uživatelské události (kliky, scroll, rage clicks, copy/paste, formuláře)'
                ");

                $vytvorenoTabulek++;
                $pridanoIndexu += 7; // PRIMARY + 6 indexů

                echo "<div class='success'>Tabulka <code>wgs_analytics_events</code> úspěšně vytvořena (21 sloupců, 7 indexů)</div>";
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
            echo "Modul #5 (Event Tracking Engine) je nyní připraven k použití.";
            echo "</div>";

            // =======================================================
            // NEXT STEPS
            // =======================================================
            echo "<h2>DALŠÍ KROKY</h2>";
            echo "<div class='info'>";
            echo "<strong>Co dál:</strong><br>";
            echo "1. Zkontrolujte strukturu tabulky přes SQL kartu v Control Centre<br>";
            echo "2. Otestujte event tracking v prohlížeči (kliknutí, scrollování)<br>";
            echo "3. Zkontrolujte uložené události v tabulce <code>wgs_analytics_events</code><br>";
            echo "4. Sledujte rage clicks a další behavioral signály v admin dashboardu<br>";
            echo "</div>";

            echo "<a href='admin.php' class='btn'>Zpět na Admin</a>";
            echo "<a href='vsechny_tabulky.php' class='btn'>Zobrazit SQL strukturu</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();

            echo "<div class='error'>";
            echo "<strong>CHYBA PŘI MIGRACI:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";

            error_log("Migrace Module #5 FAILED: " . $e->getMessage());
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
        echo "<li>Vytvoří tabulku <code>wgs_analytics_events</code> (21 sloupců, 7 indexů)</li>";
        echo "<li>Nastaví indexy pro rychlé dotazování podle session_id, event_type, page_url</li>";
        echo "<li>Umožní sledování všech typů událostí: click, scroll, rage_click, copy, paste, form_focus, form_blur, idle, active</li>";
        echo "</ol>";
        echo "</div>";

        echo "<h3>Detaily tabulky: wgs_analytics_events</h3>";
        echo "<table>";
        echo "<tr><th>Sloupec</th><th>Typ</th><th>Popis</th></tr>";
        echo "<tr><td>id</td><td>BIGINT UNSIGNED PK</td><td>Primární klíč</td></tr>";
        echo "<tr><td>session_id</td><td>VARCHAR(64) INDEX</td><td>FK → sessions</td></tr>";
        echo "<tr><td>fingerprint_id</td><td>VARCHAR(64) INDEX</td><td>FK → fingerprints</td></tr>";
        echo "<tr><td>event_type</td><td>ENUM INDEX</td><td>Typ události (click, scroll...)</td></tr>";
        echo "<tr><td>page_url</td><td>VARCHAR(500) INDEX</td><td>URL stránky</td></tr>";
        echo "<tr><td>timestamp</td><td>BIGINT UNSIGNED INDEX</td><td>Unix timestamp ms</td></tr>";
        echo "<tr><td>click_x</td><td>SMALLINT UNSIGNED</td><td>X pozice kliku (px)</td></tr>";
        echo "<tr><td>click_y</td><td>SMALLINT UNSIGNED</td><td>Y pozice kliku (px)</td></tr>";
        echo "<tr><td>click_x_percent</td><td>DECIMAL(5,2)</td><td>X jako % viewportu</td></tr>";
        echo "<tr><td>click_y_percent</td><td>DECIMAL(5,2)</td><td>Y jako % viewportu</td></tr>";
        echo "<tr><td>element_selector</td><td>VARCHAR(500)</td><td>CSS selector</td></tr>";
        echo "<tr><td>element_text</td><td>VARCHAR(255)</td><td>Text elementu</td></tr>";
        echo "<tr><td>element_tag</td><td>VARCHAR(50)</td><td>HTML tag</td></tr>";
        echo "<tr><td>scroll_depth</td><td>TINYINT UNSIGNED</td><td>Scroll % (0-100)</td></tr>";
        echo "<tr><td>viewport_width</td><td>SMALLINT UNSIGNED</td><td>Šířka viewportu</td></tr>";
        echo "<tr><td>viewport_height</td><td>SMALLINT UNSIGNED</td><td>Výška viewportu</td></tr>";
        echo "<tr><td>rage_click_count</td><td>TINYINT UNSIGNED</td><td>Počet rage kliků</td></tr>";
        echo "<tr><td>form_field_name</td><td>VARCHAR(255)</td><td>Název pole formuláře</td></tr>";
        echo "<tr><td>copied_text_length</td><td>SMALLINT UNSIGNED</td><td>Délka zkopírovaného textu</td></tr>";
        echo "<tr><td>idle_duration</td><td>INT UNSIGNED</td><td>Doba nečinnosti (ms)</td></tr>";
        echo "<tr><td>created_at</td><td>DATETIME INDEX</td><td>Čas uložení do DB</td></tr>";
        echo "</table>";

        echo "<h3>Indexy</h3>";
        echo "<ul>";
        echo "<li><code>PRIMARY KEY</code> na <code>id</code></li>";
        echo "<li><code>INDEX</code> na <code>session_id</code> (rychlý výběr eventů pro session)</li>";
        echo "<li><code>INDEX</code> na <code>fingerprint_id</code> (výběr podle fingerprintu)</li>";
        echo "<li><code>INDEX</code> na <code>event_type</code> (filtrace podle typu)</li>";
        echo "<li><code>INDEX</code> na <code>page_url</code> (analýza podle stránky)</li>";
        echo "<li><code>INDEX</code> na <code>timestamp</code> (chronologické řazení)</li>";
        echo "<li><code>INDEX</code> na <code>created_at</code> (denní statistiky)</li>";
        echo "</ul>";

        echo "<h3>Podporované typy událostí</h3>";
        echo "<ul>";
        echo "<li><code>click</code> - Standardní kliknutí na element</li>";
        echo "<li><code>scroll</code> - Scrollování stránky (ukládá max scroll depth)</li>";
        echo "<li><code>rage_click</code> - Detekce frustrovaného chování (3+ kliky rychle za sebou)</li>";
        echo "<li><code>copy</code> - Uživatel zkopíroval text</li>";
        echo "<li><code>paste</code> - Uživatel vložil text</li>";
        echo "<li><code>form_focus</code> - Focus na formulářové pole</li>";
        echo "<li><code>form_blur</code> - Blur z formulářového pole</li>";
        echo "<li><code>idle</code> - Uživatel je neaktivní (30s+)</li>";
        echo "<li><code>active</code> - Uživatel se vrátil k aktivitě po idle</li>";
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

    error_log("Migrace Module #5 Exception: " . $e->getMessage());
}

echo "</div></body></html>";
?>
