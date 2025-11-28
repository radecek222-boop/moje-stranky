<?php
/**
 * Migrace: Modul #7 - Session Replay Engine
 *
 * Tento skript BEZPEČNĚ vytvoří tabulku pro session replay frames.
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
 *
 * Tabulka:
 * - wgs_analytics_replay_frames: Ukládá nahrávané frames (mousemove, click, scroll, resize)
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #7 - Session Replay Engine
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
    <title>Migrace: Modul #7 - Session Replay Engine</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
            line-height: 1.6;
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
            margin-bottom: 20px;
        }

        h2 {
            color: #333333;
            margin-top: 25px;
            margin-bottom: 15px;
            font-size: 1.3em;
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
            padding: 10px 20px;
            background: #333333;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn:hover {
            background: #1a300d;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #c7254e;
        }

        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border-left: 4px solid #333333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        table th,
        table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        table th {
            background: #333333;
            color: white;
            font-weight: 600;
        }

        table tr:hover {
            background: #f5f5f5;
        }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Modul #7 - Session Replay Engine</h1>";

    echo "<div class='info'>";
    echo "<strong>ℹ️ O tomto modulu:</strong><br>";
    echo "Session Replay Engine umožňuje nahrávání a přehrávání uživatelských interakcí (pohyby myši, kliky, scrolly) pro analýzu UX problémů.<br><br>";
    echo "<strong>Tabulka:</strong> <code>wgs_analytics_replay_frames</code> - Ukládá jednotlivé replay frames (mousemove, click, scroll, resize)<br>";
    echo "<strong>Retention policy:</strong> 30-day TTL (auto-cleanup)<br>";
    echo "<strong>Expected volume:</strong> 50K-500K rows/day, ~3M rows total (30-day retention)";
    echo "</div>";

    // ========================================
    // KONTROLA EXISTUJÍCÍCH TABULEK
    // ========================================
    echo "<h2>📋 Krok 1: Kontrola existujících tabulek</h2>";

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_replay_frames'");
    $tabulkaExistuje = $stmt->rowCount() > 0;

    if ($tabulkaExistuje) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ VAROVÁNÍ:</strong> Tabulka <code>wgs_analytics_replay_frames</code> již existuje.";
        echo "</div>";
    } else {
        echo "<div class='info'>";
        echo "✅ Tabulka <code>wgs_analytics_replay_frames</code> neexistuje - bude vytvořena.";
        echo "</div>";
    }

    // ========================================
    // PROVEDENÍ MIGRACE (pokud execute=1)
    // ========================================
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {

        echo "<h2>🚀 Krok 2: Provádění migrace</h2>";

        $pdo->beginTransaction();

        try {
            // ========================================
            // VYTVOŘENÍ TABULKY wgs_analytics_replay_frames
            // ========================================
            if (!$tabulkaExistuje) {
                echo "<div class='info'><strong>Vytvářím tabulku:</strong> <code>wgs_analytics_replay_frames</code>...</div>";

                $pdo->exec("
                    CREATE TABLE `wgs_analytics_replay_frames` (
                        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                        -- Relace & stránka
                        `session_id` VARCHAR(64) NOT NULL COMMENT 'Session ID z Modulu #2',
                        `page_url` VARCHAR(500) NOT NULL COMMENT 'URL stránky (normalizovaná)',
                        `page_index` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Pořadí stránky v session (0, 1, 2...)',

                        -- Frame data
                        `frame_index` INT UNSIGNED NOT NULL COMMENT 'Pořadí framu v rámci stránky',
                        `timestamp_offset` INT UNSIGNED NOT NULL COMMENT 'Offset v ms od page load',
                        `event_type` ENUM(
                            'mousemove',
                            'click',
                            'scroll',
                            'resize',
                            'focus',
                            'blur',
                            'load',
                            'unload'
                        ) NOT NULL,

                        -- Event payload (JSON)
                        `event_data` JSON NULL COMMENT 'Data eventu (x, y, scrollY, width, height...)',

                        -- Metadata
                        `viewport_width` SMALLINT UNSIGNED NULL COMMENT 'Šířka viewportu v px',
                        `viewport_height` SMALLINT UNSIGNED NULL COMMENT 'Výška viewportu v px',
                        `device_type` ENUM('desktop', 'mobile', 'tablet') NULL,

                        -- Timestamps
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `expires_at` TIMESTAMP NULL COMMENT '30-day TTL pro auto-cleanup',

                        PRIMARY KEY (`id`),
                        INDEX `idx_session` (`session_id`),
                        INDEX `idx_page` (`session_id`, `page_index`),
                        INDEX `idx_frame` (`session_id`, `page_index`, `frame_index`),
                        INDEX `idx_timestamp` (`timestamp_offset`),
                        INDEX `idx_expires` (`expires_at`),
                        INDEX `idx_device` (`device_type`),
                        INDEX `idx_event_type` (`event_type`),
                        INDEX `idx_created` (`created_at`)

                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Session replay frames - nahrávání uživatelských interakcí (Modul #7)'
                ");

                echo "<div class='success'>";
                echo "✅ Tabulka <code>wgs_analytics_replay_frames</code> úspěšně vytvořena<br>";
                echo "• 13 sloupců<br>";
                echo "• 8 indexů pro optimalizaci dotazů<br>";
                echo "• JSON sloupec pro flexibilní event data<br>";
                echo "• BIGINT id (očekává se high volume)<br>";
                echo "• 30-day TTL pro auto-cleanup";
                echo "</div>";
            } else {
                echo "<div class='warning'>";
                echo "⚠️ Tabulka <code>wgs_analytics_replay_frames</code> již existuje - přeskakuji vytvoření.";
                echo "</div>";
            }

            // ========================================
            // COMMIT
            // ========================================
            $pdo->commit();

            echo "<h2>✅ Migrace úspěšně dokončena</h2>";

            echo "<div class='success'>";
            echo "<strong>HOTOVO!</strong> Modul #7 (Session Replay Engine) byl úspěšně nainstalován.<br><br>";
            echo "<strong>Co dál?</strong><br>";
            echo "1. Ověřte strukturu tabulky níže<br>";
            echo "2. Integrujte <code>replay-recorder.js</code> do frontendu<br>";
            echo "3. Testujte nahrávání framů<br>";
            echo "4. Nastavte cron job pro auto-cleanup (30-day TTL)";
            echo "</div>";

            // ========================================
            // ZOBRAZENÍ STRUKTURY TABULKY
            // ========================================
            echo "<h2>📊 Struktura tabulky: wgs_analytics_replay_frames</h2>";

            $stmt = $pdo->query("DESCRIBE wgs_analytics_replay_frames");
            $struktura = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<table>";
            echo "<tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Klíč</th><th>Default</th><th>Extra</th></tr>";

            foreach ($struktura as $sloupec) {
                echo "<tr>";
                echo "<td><code>{$sloupec['Field']}</code></td>";
                echo "<td>{$sloupec['Type']}</td>";
                echo "<td>{$sloupec['Null']}</td>";
                echo "<td>{$sloupec['Key']}</td>";
                echo "<td>" . ($sloupec['Default'] ?? 'NULL') . "</td>";
                echo "<td>{$sloupec['Extra']}</td>";
                echo "</tr>";
            }

            echo "</table>";

            // ========================================
            // ZOBRAZENÍ INDEXŮ
            // ========================================
            echo "<h2>🔍 Indexy</h2>";

            $stmt = $pdo->query("SHOW INDEX FROM wgs_analytics_replay_frames");
            $indexy = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<table>";
            echo "<tr><th>Index</th><th>Sloupec</th><th>Unique</th><th>Seq</th></tr>";

            foreach ($indexy as $index) {
                echo "<tr>";
                echo "<td><code>{$index['Key_name']}</code></td>";
                echo "<td>{$index['Column_name']}</td>";
                echo "<td>" . ($index['Non_unique'] == 0 ? 'Yes' : 'No') . "</td>";
                echo "<td>{$index['Seq_in_index']}</td>";
                echo "</tr>";
            }

            echo "</table>";

            // ========================================
            // PŘÍKLAD DOTAZU
            // ========================================
            echo "<h2>📝 Příklad použití</h2>";

            echo "<div class='info'>";
            echo "<strong>Načtení replay framů pro session:</strong>";
            echo "<pre>SELECT
    frame_index,
    timestamp_offset,
    event_type,
    event_data,
    viewport_width,
    viewport_height
FROM wgs_analytics_replay_frames
WHERE session_id = 'abc123...'
  AND page_index = 0
ORDER BY frame_index ASC
LIMIT 1000;</pre>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<strong>Počet framů per session:</strong>";
            echo "<pre>SELECT
    session_id,
    page_index,
    COUNT(*) as frame_count,
    MAX(timestamp_offset) as duration_ms,
    MIN(created_at) as first_frame,
    MAX(created_at) as last_frame
FROM wgs_analytics_replay_frames
GROUP BY session_id, page_index
ORDER BY first_frame DESC
LIMIT 20;</pre>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<strong>Cleanup starých framů (30+ days):</strong>";
            echo "<pre>DELETE FROM wgs_analytics_replay_frames
WHERE expires_at < NOW()
LIMIT 10000;</pre>";
            echo "</div>";

            // ========================================
            // CRON JOB SETUP
            // ========================================
            echo "<h2>⏰ Cron Job Setup (Auto-Cleanup)</h2>";

            echo "<div class='warning'>";
            echo "<strong>⚠️ DŮLEŽITÉ:</strong> Nastavte cron job pro automatické mazání starých framů (30-day TTL):<br><br>";
            echo "<pre>0 2 * * * /usr/bin/php /path/to/scripts/cleanup_old_replay_frames.php >> /path/to/logs/cron.log 2>&1</pre>";
            echo "<br><strong>Tento cron job by měl běžet denně ve 2:00 AM.</strong>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();

            echo "<div class='error'>";
            echo "<strong>❌ CHYBA PŘI MIGRACI:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";

            error_log('Module #7 Migration Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
        }

    } else {
        // ========================================
        // NÁHLED - PŘED SPUŠTĚNÍM
        // ========================================
        echo "<h2>👀 Náhled změn (nespuštěno)</h2>";

        echo "<div class='info'>";
        echo "<strong>Tato migrace provede následující:</strong><br><br>";

        if (!$tabulkaExistuje) {
            echo "✅ Vytvoří tabulku <code>wgs_analytics_replay_frames</code><br>";
            echo "• 13 sloupců (BIGINT id, session_id, page_url, page_index, frame_index, timestamp_offset, event_type, event_data, viewport_width/height, device_type, created_at, expires_at)<br>";
            echo "• 8 indexů pro optimalizaci dotazů<br>";
            echo "• JSON sloupec pro flexibilní event data<br>";
            echo "• 30-day TTL pro auto-cleanup<br>";
        } else {
            echo "⚠️ Tabulka <code>wgs_analytics_replay_frames</code> již existuje - žádné změny nebudou provedeny<br>";
        }

        echo "</div>";

        echo "<div class='warning'>";
        echo "<strong>⚠️ POZNÁMKA:</strong> Tato migrace je idempotentní (bezpečná pro opakované spuštění).";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>▶️ SPUSTIT MIGRACI</a>";
        echo "<a href='admin.php' class='btn btn-secondary'>← Zpět na Admin</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ NEOČEKÁVANÁ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";

    error_log('Module #7 Migration Unexpected Error: ' . $e->getMessage());
}

echo "</div></body></html>";
?>
