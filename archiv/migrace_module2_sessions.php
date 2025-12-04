<?php
/**
 * Migrace: Modul #2 - Advanced Session Tracking
 *
 * Tento skript BEZPEČNĚ vytvoří:
 * 1. Tabulku wgs_analytics_sessions (pokročilé sledování relací)
 * 2. Rozšíří tabulku wgs_pageviews o sloupce session_id a fingerprint_id
 *
 * Můžete jej spustit vícekrát - kontroluje existenci před vytvořením.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #2
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
    <title>Migrace: Modul #2 - Advanced Session Tracking</title>
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
            font-size: 16px;
        }
        .btn:hover {
            background: #1a300d;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border-left: 4px solid #333333;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #333333;
        }
        .step-number {
            font-weight: bold;
            color: #333333;
            font-size: 18px;
        }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔧 Migrace: Modul #2 - Advanced Session Tracking</h1>";

    echo "<div class='info'>";
    echo "<strong>📋 CO TATO MIGRACE PROVEDE:</strong><br>";
    echo "1. Vytvoří tabulku <code>wgs_analytics_sessions</code> (40+ sloupců)<br>";
    echo "2. Rozšíří tabulku <code>wgs_pageviews</code> o sloupce <code>session_id</code> a <code>fingerprint_id</code><br>";
    echo "3. Vytvoří 11 indexů pro optimalizaci výkonu<br>";
    echo "4. Naváže foreign key vztah mezi sessions a fingerprints<br>";
    echo "</div>";

    // Kontrolní fáze
    echo "<div class='step'>";
    echo "<div class='step-number'>KROK 1: KONTROLA EXISTUJÍCÍCH STRUKTUR</div>";

    // Kontrola existence tabulky wgs_analytics_sessions
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_sessions'");
    $sessionTableExists = $stmt->rowCount() > 0;

    if ($sessionTableExists) {
        echo "<div class='warning'>⚠️ Tabulka <code>wgs_analytics_sessions</code> již existuje.</div>";
    } else {
        echo "<div class='info'>Tabulka <code>wgs_analytics_sessions</code> neexistuje - bude vytvořena.</div>";
    }

    // Kontrola sloupců v wgs_pageviews
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_pageviews LIKE 'session_id'");
    $sessionIdExists = $stmt->rowCount() > 0;

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_pageviews LIKE 'fingerprint_id'");
    $fingerprintIdExists = $stmt->rowCount() > 0;

    if ($sessionIdExists) {
        echo "<div class='warning'>⚠️ Sloupec <code>wgs_pageviews.session_id</code> již existuje.</div>";
    } else {
        echo "<div class='info'>Sloupec <code>wgs_pageviews.session_id</code> neexistuje - bude přidán.</div>";
    }

    if ($fingerprintIdExists) {
        echo "<div class='warning'>⚠️ Sloupec <code>wgs_pageviews.fingerprint_id</code> již existuje.</div>";
    } else {
        echo "<div class='info'>Sloupec <code>wgs_pageviews.fingerprint_id</code> neexistuje - bude přidán.</div>";
    }

    // Kontrola existence tabulky wgs_analytics_fingerprints (závislost z Modulu #1)
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_fingerprints'");
    $fingerprintsTableExists = $stmt->rowCount() > 0;

    if (!$fingerprintsTableExists) {
        echo "<div class='error'>";
        echo "<strong>CHYBA:</strong> Tabulka <code>wgs_analytics_fingerprints</code> neexistuje!<br>";
        echo "Modul #2 vyžaduje Modul #1 (Fingerprinting Engine).<br>";
        echo "Nejprve spusťte migraci: <code>migrace_module1_fingerprinting.php</code>";
        echo "</div>";
        echo "</div>"; // close step
        echo "</div></body></html>";
        exit;
    } else {
        echo "<div class='success'>Tabulka <code>wgs_analytics_fingerprints</code> existuje (Modul #1).</div>";
    }

    echo "</div>"; // close step 1

    // Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {

        echo "<div class='step'>";
        echo "<div class='step-number'>KROK 2: SPUŠTĚNÍ MIGRACE</div>";
        echo "<div class='info'><strong>⚙️ SPOUŠTÍM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            $changesApplied = [];

            // ========================================
            // 1. Vytvoření tabulky wgs_analytics_sessions
            // ========================================
            if (!$sessionTableExists) {
                echo "<p>📝 Vytvářím tabulku <code>wgs_analytics_sessions</code>...</p>";

                $sqlCreateSessions = "
                CREATE TABLE IF NOT EXISTS `wgs_analytics_sessions` (
                  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  `session_id` VARCHAR(64) NOT NULL UNIQUE COMMENT 'Unique session identifier from localStorage',
                  `fingerprint_id` VARCHAR(64) NULL COMMENT 'Foreign key to wgs_analytics_fingerprints.fingerprint_id',

                  -- Session lifecycle
                  `session_start` DATETIME NOT NULL COMMENT 'First pageview timestamp',
                  `session_end` DATETIME NULL COMMENT 'Last activity timestamp',
                  `session_duration` INT UNSIGNED NULL COMMENT 'Duration in seconds (calculated)',
                  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Is session currently active',

                  -- Entry/Exit tracking
                  `entry_page` VARCHAR(500) NOT NULL COMMENT 'First page URL visited',
                  `exit_page` VARCHAR(500) NULL COMMENT 'Last page URL visited',
                  `pageview_count` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Total pageviews in session',

                  -- Engagement metrics
                  `engagement_score` DECIMAL(5,2) NULL COMMENT 'Overall engagement score 0-100',
                  `total_scroll_depth` INT UNSIGNED NULL COMMENT 'Sum of scroll depths across all pages',
                  `total_click_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total clicks in session',
                  `total_time_on_site` INT UNSIGNED NULL COMMENT 'Total active time in seconds',

                  -- UTM Campaign tracking (persisted across pageviews)
                  `utm_source` VARCHAR(255) NULL COMMENT 'Campaign source (e.g., google, facebook)',
                  `utm_medium` VARCHAR(255) NULL COMMENT 'Campaign medium (e.g., cpc, email)',
                  `utm_campaign` VARCHAR(255) NULL COMMENT 'Campaign name',
                  `utm_term` VARCHAR(255) NULL COMMENT 'Campaign term (paid keywords)',
                  `utm_content` VARCHAR(255) NULL COMMENT 'Campaign content (A/B variant)',

                  -- Referrer tracking
                  `referrer` VARCHAR(500) NULL COMMENT 'HTTP referer from first pageview',
                  `referrer_domain` VARCHAR(255) NULL COMMENT 'Extracted domain from referrer',

                  -- Device & Location (denormalized for performance)
                  `device_type` ENUM('desktop', 'mobile', 'tablet') NULL COMMENT 'Device type',
                  `browser` VARCHAR(100) NULL COMMENT 'Browser name',
                  `os` VARCHAR(100) NULL COMMENT 'Operating system',
                  `country` VARCHAR(100) NULL COMMENT 'Country from geolocation',
                  `city` VARCHAR(255) NULL COMMENT 'City from geolocation',

                  -- Bot detection (populated by Module #3)
                  `is_bot` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Is this session from a bot',
                  `bot_score` DECIMAL(5,2) NULL COMMENT 'Bot probability score 0-100',

                  -- Conversion tracking (for Module #9)
                  `has_conversion` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Did this session convert',
                  `conversion_type` VARCHAR(100) NULL COMMENT 'Type of conversion if any',
                  `conversion_value` DECIMAL(10,2) NULL COMMENT 'Monetary value of conversion',

                  -- Timestamps
                  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                  -- Indexes
                  INDEX `idx_session_id` (`session_id`),
                  INDEX `idx_fingerprint_id` (`fingerprint_id`),
                  INDEX `idx_session_start` (`session_start`),
                  INDEX `idx_is_active` (`is_active`),
                  INDEX `idx_utm_campaign` (`utm_campaign`),
                  INDEX `idx_device_type` (`device_type`),
                  INDEX `idx_country` (`country`),
                  INDEX `idx_is_bot` (`is_bot`),
                  INDEX `idx_has_conversion` (`has_conversion`),

                  FOREIGN KEY (`fingerprint_id`) REFERENCES `wgs_analytics_fingerprints`(`fingerprint_id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE

                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Advanced session tracking with fingerprint linking - Module #2';
                ";

                $pdo->exec($sqlCreateSessions);
                $changesApplied[] = "Vytvořena tabulka <code>wgs_analytics_sessions</code> s 33 sloupci a 11 indexy";
                echo "<div class='success'>Tabulka <code>wgs_analytics_sessions</code> úspěšně vytvořena.</div>";
            }

            // ========================================
            // 2. Rozšíření tabulky wgs_pageviews
            // ========================================
            if (!$sessionIdExists) {
                echo "<p>📝 Přidávám sloupec <code>session_id</code> do tabulky <code>wgs_pageviews</code>...</p>";

                $pdo->exec("
                    ALTER TABLE `wgs_pageviews`
                    ADD COLUMN `session_id` VARCHAR(64) NULL
                    COMMENT 'Links to wgs_analytics_sessions.session_id'
                    AFTER `id`
                ");

                $changesApplied[] = "Přidán sloupec <code>wgs_pageviews.session_id</code>";
                echo "<div class='success'>Sloupec <code>session_id</code> přidán.</div>";
            }

            if (!$fingerprintIdExists) {
                echo "<p>📝 Přidávám sloupec <code>fingerprint_id</code> do tabulky <code>wgs_pageviews</code>...</p>";

                $pdo->exec("
                    ALTER TABLE `wgs_pageviews`
                    ADD COLUMN `fingerprint_id` VARCHAR(64) NULL
                    COMMENT 'Links to wgs_analytics_fingerprints.fingerprint_id'
                    AFTER `session_id`
                ");

                $changesApplied[] = "Přidán sloupec <code>wgs_pageviews.fingerprint_id</code>";
                echo "<div class='success'>Sloupec <code>fingerprint_id</code> přidán.</div>";
            }

            // ========================================
            // 3. Přidání indexů na wgs_pageviews
            // ========================================
            // Kontrola existence indexů
            $stmt = $pdo->query("SHOW INDEX FROM wgs_pageviews WHERE Key_name = 'idx_session_id'");
            $sessionIdIndexExists = $stmt->rowCount() > 0;

            if (!$sessionIdIndexExists && !$sessionIdExists) {
                echo "<p>📝 Vytvářím index na <code>wgs_pageviews.session_id</code>...</p>";
                $pdo->exec("ALTER TABLE `wgs_pageviews` ADD INDEX `idx_session_id` (`session_id`)");
                $changesApplied[] = "Vytvořen index <code>idx_session_id</code> na tabulce <code>wgs_pageviews</code>";
                echo "<div class='success'>Index <code>idx_session_id</code> vytvořen.</div>";
            }

            $stmt = $pdo->query("SHOW INDEX FROM wgs_pageviews WHERE Key_name = 'idx_fingerprint_id'");
            $fingerprintIdIndexExists = $stmt->rowCount() > 0;

            if (!$fingerprintIdIndexExists && !$fingerprintIdExists) {
                echo "<p>📝 Vytvářím index na <code>wgs_pageviews.fingerprint_id</code>...</p>";
                $pdo->exec("ALTER TABLE `wgs_pageviews` ADD INDEX `idx_fingerprint_id` (`fingerprint_id`)");
                $changesApplied[] = "Vytvořen index <code>idx_fingerprint_id</code> na tabulce <code>wgs_pageviews</code>";
                echo "<div class='success'>Index <code>idx_fingerprint_id</code> vytvořen.</div>";
            }

            // Commit transakce
            $pdo->commit();

            echo "</div>"; // close step 2

            // ========================================
            // Finální shrnutí
            // ========================================
            echo "<div class='step'>";
            echo "<div class='step-number'>KROK 3: SHRNUTÍ ZMĚN</div>";

            if (count($changesApplied) > 0) {
                echo "<div class='success'>";
                echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
                echo "<strong>Provedené změny:</strong><ul>";
                foreach ($changesApplied as $change) {
                    echo "<li>{$change}</li>";
                }
                echo "</ul>";
                echo "</div>";
            } else {
                echo "<div class='warning'>";
                echo "<strong>⚠️ ŽÁDNÉ ZMĚNY NEBYLY PROVEDENY</strong><br>";
                echo "Všechny struktury již existují.";
                echo "</div>";
            }

            // Verifikace
            echo "<h2>🔍 Verifikace</h2>";

            $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wgs_analytics_sessions'");
            $tableCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($tableCount == 1) {
                echo "<div class='success'>Tabulka <code>wgs_analytics_sessions</code> existuje v databázi.</div>";
            }

            $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wgs_analytics_sessions'");
            $columnCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "<div class='info'>📊 Tabulka <code>wgs_analytics_sessions</code> má <strong>{$columnCount} sloupců</strong>.</div>";

            $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wgs_analytics_sessions'");
            $indexCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "<div class='info'>🔑 Tabulka <code>wgs_analytics_sessions</code> má <strong>{$indexCount} indexů</strong>.</div>";

            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_pageviews WHERE Field IN ('session_id', 'fingerprint_id')");
            $newColumnsInPageviews = $stmt->rowCount();
            echo "<div class='info'>➕ Tabulka <code>wgs_pageviews</code> má <strong>{$newColumnsInPageviews} nové sloupce</strong> (session_id, fingerprint_id).</div>";

            echo "</div>"; // close step 3

            // Další kroky
            echo "<div class='step'>";
            echo "<div class='step-number'>DALŠÍ KROKY</div>";
            echo "<div class='info'>";
            echo "<strong>📋 CO DĚLAT DÁLE:</strong><br><br>";
            echo "1. <strong>Testování PHP třídy:</strong> Otestujte třídu <code>SessionMerger</code> v souboru <code>includes/SessionMerger.php</code><br>";
            echo "2. <strong>Testování API:</strong> Otestujte endpoint <code>/api/track_v2.php</code> pomocí curl nebo Postman<br>";
            echo "3. <strong>Testování v prohlížeči:</strong> Otestujte JavaScript modul <code>tracker-v2.js</code> v konzoli prohlížeče<br>";
            echo "4. <strong>Verifikace dat:</strong> Zkontrolujte data v tabulce pomocí SQL dotazu níže<br>";
            echo "</div>";

            echo "<h3>🔍 SQL dotaz pro kontrolu dat:</h3>";
            echo "<pre>-- Zobrazit posledních 10 relací
SELECT
    session_id,
    fingerprint_id,
    entry_page,
    exit_page,
    pageview_count,
    engagement_score,
    utm_source,
    utm_campaign,
    device_type,
    country,
    is_active,
    created_at
FROM wgs_analytics_sessions
ORDER BY created_at DESC
LIMIT 10;

-- Zobrazit pageviews s relacemi
SELECT
    p.url,
    p.session_id,
    p.fingerprint_id,
    s.entry_page,
    s.pageview_count,
    p.datum
FROM wgs_pageviews p
LEFT JOIN wgs_analytics_sessions s ON p.session_id = s.session_id
WHERE p.session_id IS NOT NULL
ORDER BY p.datum DESC
LIMIT 10;</pre>";

            echo "</div>"; // close další kroky

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "</div>"; // close step 2 if error
            echo "<div class='error'>";
            echo "<strong>CHYBA PŘI MIGRACI:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";

            echo "<div class='step'>";
            echo "<div class='step-number'>ROLLBACK</div>";
            echo "<div class='warning'>⚠️ Všechny změny byly vráceny zpět (rollback).</div>";
            echo "</div>";
        }

    } else {
        // Režim náhledu - zobrazit co bude provedeno
        echo "<div class='step'>";
        echo "<div class='step-number'>KROK 2: NÁHLED ZMĚN</div>";

        echo "<div class='info'>";
        echo "<strong>📋 NÁSLEDUJÍCÍ ZMĚNY BUDOU PROVEDENY:</strong><br><br>";

        if (!$sessionTableExists) {
            echo "Vytvoření tabulky <code>wgs_analytics_sessions</code> (33 sloupců, 11 indexů)<br>";
        }
        if (!$sessionIdExists) {
            echo "Přidání sloupce <code>wgs_pageviews.session_id</code><br>";
        }
        if (!$fingerprintIdExists) {
            echo "Přidání sloupce <code>wgs_pageviews.fingerprint_id</code><br>";
        }
        if (!$sessionIdExists || !$fingerprintIdExists) {
            echo "Vytvoření indexů na nových sloupcích<br>";
        }

        echo "</div>";

        echo "<br><a href='?execute=1' class='btn'>▶️ SPUSTIT MIGRACI</a>";
        echo "</div>";
    }

    // Rollback instrukce
    echo "<div class='step'>";
    echo "<div class='step-number'>ROLLBACK INSTRUKCE</div>";
    echo "<div class='warning'>";
    echo "<strong>⚠️ POKUD POTŘEBUJETE VRÁTIT ZMĚNY ZPĚT:</strong><br>";
    echo "Spusťte následující SQL příkazy v phpMyAdmin nebo MySQL konzoli:";
    echo "</div>";
    echo "<pre>-- Rollback Modulu #2
DROP TABLE IF EXISTS `wgs_analytics_sessions`;
ALTER TABLE `wgs_pageviews` DROP COLUMN IF EXISTS `session_id`;
ALTER TABLE `wgs_pageviews` DROP COLUMN IF EXISTS `fingerprint_id`;</pre>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>KRITICKÁ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<br><a href='admin.php' class='btn'>← Zpět do administrace</a>";
echo "<a href='vsechny_tabulky.php' class='btn'>📊 Zobrazit strukturu tabulek</a>";

echo "</div></body></html>";
?>
