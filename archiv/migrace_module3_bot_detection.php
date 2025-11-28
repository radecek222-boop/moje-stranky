<?php
/**
 * Migrace: Modul #3 - Bot Detection Engine
 *
 * Tento skript BEZPEČNĚ vytvoří:
 * 1. Tabulku wgs_analytics_bot_detections (22 sloupců, 8 indexů)
 * 2. Tabulku wgs_analytics_bot_whitelist s seed daty
 *
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #3 - Bot Detection Engine
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
    <title>Migrace: Modul #3 - Bot Detection Engine</title>
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
            color: #4a7c2e;
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
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th, table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Migrace: Modul #3 - Bot Detection Engine</h1>";

try {
    $pdo = getDbConnection();

    // ========================================
    // KONTROLNÍ FÁZE
    // ========================================
    echo "<h2>1️⃣ Kontrola aktuálního stavu</h2>";

    $tabulkyKKontrole = [
        'wgs_analytics_bot_detections',
        'wgs_analytics_bot_whitelist'
    ];

    $existujiciTabulky = [];

    foreach ($tabulkyKKontrole as $tabulka) {
        // SHOW TABLES LIKE nepodporuje bind parametry - použít přímý dotaz
        $stmt = $pdo->query("SHOW TABLES LIKE '{$tabulka}'");

        if ($stmt && $stmt->rowCount() > 0) {
            $existujiciTabulky[] = $tabulka;
            echo "<div class='warning'>⚠️ Tabulka <code>{$tabulka}</code> již existuje</div>";

            // Zobraz počet záznamů
            $count = $pdo->query("SELECT COUNT(*) FROM {$tabulka}")->fetchColumn();
            echo "<div class='info'>📊 Počet záznamů: <strong>{$count}</strong></div>";
        } else {
            echo "<div class='info'>✅ Tabulka <code>{$tabulka}</code> neexistuje - bude vytvořena</div>";
        }
    }

    // ========================================
    // SPUŠTĚNÍ MIGRACE
    // ========================================
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<h2>2️⃣ Spouštím migraci...</h2>";

        $pdo->beginTransaction();

        try {
            // ------------------------------------------------------------
            // TABULKA: wgs_analytics_bot_detections
            // ------------------------------------------------------------
            if (!in_array('wgs_analytics_bot_detections', $existujiciTabulky)) {
                echo "<div class='info'>📦 Vytvářím tabulku <code>wgs_analytics_bot_detections</code>...</div>";

                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `wgs_analytics_bot_detections` (
                        `detection_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        `session_id` VARCHAR(64) NOT NULL COMMENT 'ID relace z wgs_analytics_sessions',
                        `fingerprint_id` VARCHAR(64) NOT NULL COMMENT 'Device fingerprint ID',
                        `detection_timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Časové razítko detekce',

                        -- Bot scoring komponenty (0-100)
                        `bot_score` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Celkové bot score (0-100)',
                        `ua_score` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'User-Agent score (0-30)',
                        `behavioral_score` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Behavioral score (0-40)',
                        `fingerprint_score` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Fingerprint score (0-20)',
                        `network_score` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Network score (0-10)',

                        -- Threat level klasifikace
                        `threat_level` ENUM('none', 'low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'none',
                        `is_bot` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Je detekován jako bot?',
                        `is_whitelisted` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Je na whitelistu?',

                        -- Detekční signály
                        `detected_signals` JSON NULL COMMENT 'Pole detekovaných bot signálů',
                        `user_agent` VARCHAR(512) NULL,
                        `ip_address` VARCHAR(45) NULL COMMENT 'Anonymizovaná IP adresa',
                        `headless_detected` BOOLEAN NULL COMMENT 'Detekce headless browseru',
                        `webdriver_detected` BOOLEAN NULL COMMENT 'Detekce WebDriver',
                        `automation_detected` BOOLEAN NULL COMMENT 'Detekce automatizace',

                        -- Behaviorální metriky
                        `pageview_speed_ms` INT UNSIGNED NULL COMMENT 'Rychlost pageviews (ms)',
                        `mouse_movement_entropy` DECIMAL(5,2) NULL COMMENT 'Entropie pohybu myši (0-1)',
                        `keyboard_timing_variance` DECIMAL(5,2) NULL COMMENT 'Variance klávesnicových timingů',

                        -- Metadata
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                        -- Indexy
                        INDEX `idx_session_id` (`session_id`),
                        INDEX `idx_fingerprint_id` (`fingerprint_id`),
                        INDEX `idx_bot_score` (`bot_score`),
                        INDEX `idx_threat_level` (`threat_level`),
                        INDEX `idx_is_bot` (`is_bot`),
                        INDEX `idx_is_whitelisted` (`is_whitelisted`),
                        INDEX `idx_detection_timestamp` (`detection_timestamp`),
                        INDEX `idx_created_at` (`created_at`)

                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Modul #3: Detekce botů a jejich aktivity'
                ");

                echo "<div class='success'>✅ Tabulka <code>wgs_analytics_bot_detections</code> vytvořena (22 sloupců, 8 indexů)</div>";
            } else {
                echo "<div class='warning'>⚠️ Tabulka <code>wgs_analytics_bot_detections</code> již existuje - přeskakuji</div>";
            }

            // ------------------------------------------------------------
            // TABULKA: wgs_analytics_bot_whitelist
            // ------------------------------------------------------------
            if (!in_array('wgs_analytics_bot_whitelist', $existujiciTabulky)) {
                echo "<div class='info'>📦 Vytvářím tabulku <code>wgs_analytics_bot_whitelist</code>...</div>";

                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `wgs_analytics_bot_whitelist` (
                        `whitelist_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        `bot_name` VARCHAR(100) NOT NULL COMMENT 'Název bota (Googlebot, Bingbot...)',
                        `bot_type` ENUM('search_engine', 'social_media', 'monitoring', 'other') NOT NULL DEFAULT 'other',
                        `ua_pattern` VARCHAR(255) NULL COMMENT 'Regex pattern pro User-Agent',
                        `ip_ranges` JSON NULL COMMENT 'Pole IP CIDR ranges pro verifikaci',
                        `is_active` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Je whitelist aktivní?',
                        `added_by` VARCHAR(100) NULL COMMENT 'Kdo přidal (admin email nebo system)',
                        `notes` TEXT NULL COMMENT 'Poznámky',
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                        INDEX `idx_bot_name` (`bot_name`),
                        INDEX `idx_bot_type` (`bot_type`),
                        INDEX `idx_is_active` (`is_active`)

                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Modul #3: Whitelist legitimních botů'
                ");

                echo "<div class='success'>✅ Tabulka <code>wgs_analytics_bot_whitelist</code> vytvořena</div>";

                // ------------------------------------------------------------
                // SEED DATA: Známé legitimní boty
                // ------------------------------------------------------------
                echo "<div class='info'>🌱 Vkládám seed data pro známé boty...</div>";

                $seedBots = [
                    [
                        'bot_name' => 'Googlebot',
                        'bot_type' => 'search_engine',
                        'ua_pattern' => 'Googlebot|Google-InspectionTool',
                        'ip_ranges' => json_encode(['66.249.64.0/19', '64.233.160.0/19']),
                        'added_by' => 'system',
                        'notes' => 'Google Search crawler - oficiální dokumentace: https://developers.google.com/search/docs/crawling-indexing/googlebot'
                    ],
                    [
                        'bot_name' => 'Bingbot',
                        'bot_type' => 'search_engine',
                        'ua_pattern' => 'bingbot|msnbot',
                        'ip_ranges' => json_encode(['40.77.167.0/24', '157.55.39.0/24']),
                        'added_by' => 'system',
                        'notes' => 'Microsoft Bing crawler'
                    ],
                    [
                        'bot_name' => 'FacebookBot',
                        'bot_type' => 'social_media',
                        'ua_pattern' => 'facebookexternalhit',
                        'ip_ranges' => json_encode(['31.13.24.0/21', '66.220.144.0/20']),
                        'added_by' => 'system',
                        'notes' => 'Facebook Open Graph crawler pro link previews'
                    ],
                    [
                        'bot_name' => 'LinkedInBot',
                        'bot_type' => 'social_media',
                        'ua_pattern' => 'LinkedInBot',
                        'ip_ranges' => null,
                        'added_by' => 'system',
                        'notes' => 'LinkedIn crawler pro link previews'
                    ],
                    [
                        'bot_name' => 'TwitterBot',
                        'bot_type' => 'social_media',
                        'ua_pattern' => 'Twitterbot',
                        'ip_ranges' => null,
                        'added_by' => 'system',
                        'notes' => 'Twitter (X) card validator'
                    ],
                    [
                        'bot_name' => 'WhatsApp',
                        'bot_type' => 'social_media',
                        'ua_pattern' => 'WhatsApp',
                        'ip_ranges' => null,
                        'added_by' => 'system',
                        'notes' => 'WhatsApp link preview crawler'
                    ],
                    [
                        'bot_name' => 'SlackBot',
                        'bot_type' => 'social_media',
                        'ua_pattern' => 'Slackbot-LinkExpanding',
                        'ip_ranges' => null,
                        'added_by' => 'system',
                        'notes' => 'Slack unfurling bot pro link previews'
                    ],
                    [
                        'bot_name' => 'UptimeRobot',
                        'bot_type' => 'monitoring',
                        'ua_pattern' => 'UptimeRobot',
                        'ip_ranges' => null,
                        'added_by' => 'system',
                        'notes' => 'Uptime monitoring service'
                    ]
                ];

                $stmt = $pdo->prepare("
                    INSERT INTO wgs_analytics_bot_whitelist
                    (bot_name, bot_type, ua_pattern, ip_ranges, added_by, notes)
                    VALUES
                    (:bot_name, :bot_type, :ua_pattern, :ip_ranges, :added_by, :notes)
                ");

                $insertCount = 0;
                foreach ($seedBots as $bot) {
                    $stmt->execute($bot);
                    $insertCount++;
                }

                echo "<div class='success'>✅ Vloženo {$insertCount} legitimních botů do whitelistu</div>";
            } else {
                echo "<div class='warning'>⚠️ Tabulka <code>wgs_analytics_bot_whitelist</code> již existuje - přeskakuji seed data</div>";
            }

            $pdo->commit();

            echo "<h2>3️⃣ Výsledek migrace</h2>";
            echo "<div class='success'>";
            echo "<strong>✅ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
            echo "Vytvořeno:<br>";
            echo "• Tabulka <code>wgs_analytics_bot_detections</code> (22 sloupců, 8 indexů)<br>";
            echo "• Tabulka <code>wgs_analytics_bot_whitelist</code> (10 sloupců, 3 indexy)<br>";
            echo "• Seed data: 8 legitimních botů (Googlebot, Bingbot, FacebookBot, LinkedInBot, TwitterBot, WhatsApp, SlackBot, UptimeRobot)";
            echo "</div>";

            // Statistiky
            echo "<h2>4️⃣ Statistiky</h2>";
            echo "<table>";
            echo "<tr><th>Tabulka</th><th>Počet záznamů</th><th>Velikost</th></tr>";

            foreach ($tabulkyKKontrole as $tabulka) {
                $count = $pdo->query("SELECT COUNT(*) FROM {$tabulka}")->fetchColumn();
                $size = $pdo->query("
                    SELECT ROUND(((data_length + index_length) / 1024), 2) AS size_kb
                    FROM information_schema.TABLES
                    WHERE table_schema = DATABASE()
                    AND table_name = '{$tabulka}'
                ")->fetchColumn();

                echo "<tr>";
                echo "<td><code>{$tabulka}</code></td>";
                echo "<td>{$count}</td>";
                echo "<td>{$size} KB</td>";
                echo "</tr>";
            }
            echo "</table>";

            echo "<div class='info'>";
            echo "📚 <strong>Další kroky:</strong><br>";
            echo "1. Vytvořit PHP třídu <code>includes/BotDetector.php</code><br>";
            echo "2. Vytvořit API endpoint <code>api/analytics_bot_activity.php</code><br>";
            echo "3. Vytvořit API endpoint <code>api/admin_bot_whitelist.php</code><br>";
            echo "4. Integrovat do <code>api/track_v2.php</code><br>";
            echo "5. Rozšířit <code>assets/js/tracker-v2.js</code> o bot detection signály";
            echo "</div>";

            echo "<br><a href='/admin.php' class='btn'>← Zpět do Admin Panelu</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();

            echo "<div class='error'>";
            echo "<strong>❌ CHYBA PŘI MIGRACI:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";

            error_log("Module #3 Migration Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
        }

    } else {
        // Náhled co bude provedeno
        echo "<h2>2️⃣ Náhled migrace</h2>";

        echo "<div class='info'>";
        echo "<strong>Bude provedeno:</strong><br>";
        echo "• Vytvoření tabulky <code>wgs_analytics_bot_detections</code> (22 sloupců, 8 indexů)<br>";
        echo "• Vytvoření tabulky <code>wgs_analytics_bot_whitelist</code> (10 sloupců, 3 indexy)<br>";
        echo "• Vložení seed dat: 8 legitimních botů (Googlebot, Bingbot, FacebookBot, LinkedInBot, TwitterBot, WhatsApp, SlackBot, UptimeRobot)<br>";
        echo "</div>";

        echo "<div class='warning'>";
        echo "<strong>⚠️ UPOZORNĚNÍ:</strong><br>";
        echo "Tato migrace vytvoří nové tabulky pro Bot Detection Engine (Modul #3).<br>";
        echo "Operace je BEZPEČNÁ a IDEMPOTENTNÍ - můžete ji spustit opakovaně.<br>";
        echo "Pokud tabulky již existují, budou přeskočeny.";
        echo "</div>";

        echo "<br>";
        echo "<a href='?execute=1' class='btn'>🚀 SPUSTIT MIGRACI</a>";
        echo "<a href='/admin.php' class='btn btn-danger'>❌ Zrušit</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ KRITICKÁ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";

    error_log("Module #3 Migration Critical Error: " . $e->getMessage());
}

echo "</div></body></html>";
?>
