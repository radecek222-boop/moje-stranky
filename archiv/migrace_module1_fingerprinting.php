<?php
/**
 * MODULE #1: FINGERPRINTING ENGINE - DATABASE MIGRATION
 *
 * This migration creates the wgs_analytics_fingerprints table for device fingerprinting.
 *
 * Safe to run multiple times - checks for existing table before creating.
 *
 * Purpose:
 * - Store device fingerprints for cross-session user tracking
 * - Enable privacy-safe analytics without cookies
 * - GDPR-compliant pseudonymous identifiers
 */

require_once __DIR__ . '/init.php';

// Security check - admin only
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

?>
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Module #1 - Fingerprinting Engine</title>
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
            color: #0066cc;
            margin-top: 20px;
            padding-bottom: 5px;
            border-bottom: 2px solid #0066cc;
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
            font-weight: 600;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #1a300d;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #d63384;
        }
        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border: 1px solid #ddd;
        }
        .feature-list {
            background: #f8f9fa;
            padding: 15px;
            border-left: 4px solid #0066cc;
            margin: 15px 0;
        }
        .feature-list ul {
            margin: 5px 0;
        }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔐 Module #1: Fingerprinting Engine - Migrace</h1>

    <div class='info'>
        <strong>📊 Co tato migrace vytvoří:</strong><br>
        Tabulka <code>wgs_analytics_fingerprints</code> pro ukládání device fingerprintů.<br>
        Umožňuje cross-session tracking bez cookies s plnou GDPR compliance.
    </div>

    <div class='feature-list'>
        <strong>Fingerprinting komponenty:</strong>
        <ul>
            <li>🎨 Canvas fingerprinting (SHA-256 hash renderingu)</li>
            <li>🎮 WebGL fingerprinting (vendor & renderer)</li>
            <li>🔊 Audio fingerprinting (oscilator hash)</li>
            <li>🖥️ Screen properties (rozlišení, pixel ratio)</li>
            <li>🌍 Timezone & geolokace</li>
            <li>🔤 Fonts detection</li>
            <li>🔌 Plugins detection</li>
            <li>⚙️ Hardware info (CPU, RAM, platform)</li>
        </ul>
    </div>

    <?php
    try {
        $pdo = getDbConnection();

        echo "<h2>📋 Kontrola před migrací</h2>";

        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_fingerprints'");
        $tableExists = $stmt->rowCount() > 0;

        if ($tableExists) {
            echo "<div class='warning'>";
            echo "<strong>⚠️ UPOZORNĚNÍ:</strong> Tabulka <code>wgs_analytics_fingerprints</code> již existuje.<br>";
            echo "Pokud chcete provést migraci znovu, nejprve smažte starou tabulku.";
            echo "</div>";

            // Show structure
            $stmtStructure = $pdo->query("DESCRIBE wgs_analytics_fingerprints");
            $structure = $stmtStructure->fetchAll(PDO::FETCH_ASSOC);

            echo "<h3>Aktuální struktura tabulky:</h3>";
            echo "<pre>";
            printf("%-30s %-30s %-10s %-10s\n", "Field", "Type", "Null", "Key");
            echo str_repeat("-", 80) . "\n";
            foreach ($structure as $col) {
                printf("%-30s %-30s %-10s %-10s\n",
                    $col['Field'],
                    $col['Type'],
                    $col['Null'],
                    $col['Key']
                );
            }
            echo "</pre>";

            // Count records
            $stmtCount = $pdo->query("SELECT COUNT(*) as count FROM wgs_analytics_fingerprints");
            $count = $stmtCount->fetch(PDO::FETCH_ASSOC)['count'];

            echo "<div class='info'>";
            echo "📊 <strong>Počet záznamů:</strong> " . number_format($count, 0, ',', ' ');
            echo "</div>";

        } else {
            echo "<div class='success'>";
            echo "✅ Tabulka <code>wgs_analytics_fingerprints</code> neexistuje. Připraveno k vytvoření.";
            echo "</div>";
        }

        // Check if wgs_pageviews needs fingerprint_id column
        $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_pageviews'");
        if ($stmt->rowCount() > 0) {
            $stmtCol = $pdo->query("SHOW COLUMNS FROM wgs_pageviews LIKE 'fingerprint_id'");
            $columnExists = $stmtCol->rowCount() > 0;

            if ($columnExists) {
                echo "<div class='info'>";
                echo "✅ Sloupec <code>fingerprint_id</code> již existuje v tabulce <code>wgs_pageviews</code>.";
                echo "</div>";
            } else {
                echo "<div class='warning'>";
                echo "⚠️ Sloupec <code>fingerprint_id</code> bude přidán do tabulky <code>wgs_pageviews</code>.";
                echo "</div>";
            }
        }

        // Execute migration
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {

            if ($tableExists) {
                echo "<div class='error'>";
                echo "<strong>CHYBA:</strong> Tabulka již existuje. Migrace nebyla provedena.";
                echo "</div>";
            } else {
                echo "<h2>⚙️ Spouštím migraci...</h2>";

                $pdo->beginTransaction();

                try {
                    // Create wgs_analytics_fingerprints table
                    $sql = "
                        CREATE TABLE `wgs_analytics_fingerprints` (
                            -- Primary Key
                            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                            `fingerprint_id` VARCHAR(64) NOT NULL UNIQUE
                                COMMENT 'SHA-256 hash of combined fingerprint components',

                            -- Canvas Fingerprinting
                            `canvas_hash` VARCHAR(64) NULL
                                COMMENT 'Hash of canvas rendering output',

                            -- WebGL Fingerprinting
                            `webgl_vendor` VARCHAR(200) NULL
                                COMMENT 'WebGL vendor string (e.g., Intel Inc.)',
                            `webgl_renderer` VARCHAR(200) NULL
                                COMMENT 'WebGL renderer string (e.g., Intel Iris OpenGL Engine)',

                            -- Audio Fingerprinting
                            `audio_hash` VARCHAR(64) NULL
                                COMMENT 'Hash of audio context oscillator output',

                            -- Timezone & Location
                            `timezone` VARCHAR(50) NULL
                                COMMENT 'IANA timezone (e.g., Europe/Prague)',
                            `timezone_offset` INT NULL
                                COMMENT 'Timezone offset in minutes from UTC',

                            -- Screen Properties
                            `screen_width` INT NULL,
                            `screen_height` INT NULL,
                            `color_depth` INT NULL
                                COMMENT 'Bits per pixel (e.g., 24, 32)',
                            `pixel_ratio` DECIMAL(4,2) NULL
                                COMMENT 'Device pixel ratio (e.g., 2.00 for Retina)',
                            `avail_width` INT NULL
                                COMMENT 'Available screen width (excluding OS taskbar)',
                            `avail_height` INT NULL
                                COMMENT 'Available screen height (excluding OS taskbar)',

                            -- Device Capabilities
                            `touch_support` TINYINT(1) DEFAULT 0
                                COMMENT 'Touch events supported (0 = no, 1 = yes)',
                            `hardware_concurrency` INT NULL
                                COMMENT 'Number of logical CPU cores',
                            `device_memory` DECIMAL(4,1) NULL
                                COMMENT 'Device RAM in GB (if available via Device Memory API)',
                            `platform` VARCHAR(100) NULL
                                COMMENT 'Operating system platform (e.g., MacIntel, Win32)',
                            `max_touch_points` INT NULL
                                COMMENT 'Maximum simultaneous touch points',

                            -- Browser Features
                            `plugins_hash` VARCHAR(64) NULL
                                COMMENT 'Hash of navigator.plugins list',
                            `fonts_hash` VARCHAR(64) NULL
                                COMMENT 'Hash of detectable system fonts',

                            -- Tracking Metadata
                            `first_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                COMMENT 'First time this fingerprint was detected',
                            `last_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                                COMMENT 'Most recent activity with this fingerprint',
                            `session_count` INT UNSIGNED DEFAULT 1
                                COMMENT 'Total number of sessions associated with this fingerprint',
                            `device_map` JSON NULL
                                COMMENT 'Map of user agents seen with this fingerprint',

                            -- Performance Indexes
                            INDEX `idx_fingerprint` (`fingerprint_id`),
                            INDEX `idx_canvas` (`canvas_hash`),
                            INDEX `idx_webgl` (`webgl_vendor`, `webgl_renderer`),
                            INDEX `idx_first_seen` (`first_seen`),
                            INDEX `idx_last_seen` (`last_seen`),
                            INDEX `idx_session_count` (`session_count`)

                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                          COMMENT='Device fingerprinting for privacy-safe cross-session tracking';
                    ";

                    $pdo->exec($sql);

                    echo "<div class='success'>";
                    echo "✅ Tabulka <code>wgs_analytics_fingerprints</code> vytvořena.";
                    echo "</div>";

                    // Add fingerprint_id column to wgs_pageviews if it doesn't exist
                    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_pageviews'");
                    if ($stmt->rowCount() > 0) {
                        $stmtCol = $pdo->query("SHOW COLUMNS FROM wgs_pageviews LIKE 'fingerprint_id'");
                        if ($stmtCol->rowCount() == 0) {
                            $pdo->exec("
                                ALTER TABLE wgs_pageviews
                                ADD COLUMN fingerprint_id VARCHAR(64) NULL
                                COMMENT 'Device fingerprint ID for cross-session tracking'
                                AFTER session_id,
                                ADD INDEX idx_fingerprint (fingerprint_id)
                            ");

                            echo "<div class='success'>";
                            echo "✅ Sloupec <code>fingerprint_id</code> přidán do tabulky <code>wgs_pageviews</code>.";
                            echo "</div>";
                        }
                    }

                    $pdo->commit();

                    echo "<h2>✅ Migrace úspěšně dokončena!</h2>";

                    echo "<div class='info'>";
                    echo "<strong>🎯 Další kroky:</strong><br>";
                    echo "1. Implementovat PHP třídu <code>FingerprintEngine.php</code><br>";
                    echo "2. Vytvořit API endpoint <code>/api/fingerprint_store.php</code><br>";
                    echo "3. Implementovat JS modul <code>fingerprint-module.js</code><br>";
                    echo "4. Integrovat do <code>tracker-v2.js</code><br>";
                    echo "5. Otestovat cross-session tracking<br>";
                    echo "</div>";

                    echo "<div class='success'>";
                    echo "<strong>📊 Vytvořená struktura:</strong><br>";
                    echo "• Hlavní tabulka: <code>wgs_analytics_fingerprints</code><br>";
                    echo "• Sloupců: 24 (včetně metadata)<br>";
                    echo "• Indexů: 6 (pro výkon)<br>";
                    echo "• JSON sloupec: <code>device_map</code> (sledování UA variant)<br>";
                    echo "• Integrace: <code>wgs_pageviews.fingerprint_id</code><br>";
                    echo "</div>";

                } catch (PDOException $e) {
                    $pdo->rollBack();
                    echo "<div class='error'>";
                    echo "<strong>❌ CHYBA PŘI MIGRACI:</strong><br>";
                    echo htmlspecialchars($e->getMessage());
                    echo "</div>";
                }
            }

        } else {
            // Preview mode
            if (!$tableExists) {
                echo "<h2>⚡ Připraveno k spuštění</h2>";

                echo "<div class='info'>";
                echo "<strong>Tato migrace provede:</strong><br>";
                echo "• Vytvoření tabulky <code>wgs_analytics_fingerprints</code> (24 sloupců)<br>";
                echo "• Přidání sloupce <code>fingerprint_id</code> do <code>wgs_pageviews</code><br>";
                echo "• Vytvoření 6 indexů pro optimální výkon<br>";
                echo "• Podpora pro Canvas, WebGL, Audio fingerprinting<br>";
                echo "• JSON storage pro device mapping<br>";
                echo "</div>";

                echo "<a href='?execute=1' class='btn'>🚀 SPUSTIT MIGRACI</a>";
            }
        }

    } catch (Exception $e) {
        echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
    }
    ?>

    <br><br>
    <a href='admin.php' class='btn' style='background: #666;'>← Zpět na Admin</a>
</div>
</body>
</html>
