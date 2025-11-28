<?php
/**
 * ENTERPRISE ANALYTICS SYSTEM - DATABASE MIGRATION
 *
 * This migration creates a complete enterprise-grade analytics platform
 * with 13 advanced tracking modules.
 *
 * SAFE TO RUN MULTIPLE TIMES - checks for existing tables
 *
 * Modules:
 * 1. Session Engine (advanced)
 * 2. Fingerprint Engine
 * 3. Bot & Security Engine
 * 4. Geolocation Engine
 * 5. Event Tracking Engine
 * 6. Heatmap Engine (click + scroll)
 * 7. Session Replay Engine
 * 8. UTM Campaign Engine
 * 9. Conversion Engine
 * 10. User Interest AI Engine
 * 11. Real-time Dashboard
 * 12. AI Reports Engine
 * 13. GDPR Compliance
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
    <title>Migrace: Enterprise Analytics System</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px; margin: 50px auto; padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white; padding: 30px; border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333333; border-bottom: 3px solid #333333;
            padding-bottom: 10px;
        }
        h2 {
            color: #0066cc;
            margin-top: 30px;
            padding-bottom: 5px;
            border-bottom: 2px solid #0066cc;
        }
        .success {
            background: #d4edda; border: 1px solid #c3e6cb;
            color: #155724; padding: 12px; border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da; border: 1px solid #f5c6cb;
            color: #721c24; padding: 12px; border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd; border: 1px solid #ffeaa7;
            color: #856404; padding: 12px; border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1; border: 1px solid #bee5eb;
            color: #0c5460; padding: 12px; border-radius: 5px;
            margin: 10px 0;
        }
        .btn {
            display: inline-block; padding: 12px 24px;
            background: #333333; color: white; text-decoration: none;
            border-radius: 5px; margin: 10px 5px 10px 0;
            font-weight: 600;
            border: none;
            cursor: pointer;
        }
        .btn:hover { background: #1a300d; }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #a02834;
        }
        .module {
            background: #f8f9fa;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #0066cc;
            border-radius: 4px;
        }
        .module h3 {
            margin: 0 0 10px 0;
            color: #0066cc;
        }
        .module ul {
            margin: 5px 0;
            padding-left: 20px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card.green {
            background: linear-gradient(135deg, #333333 0%, #1a300d 100%);
        }
        .stat-card.blue {
            background: linear-gradient(135deg, #0066cc 0%, #004499 100%);
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 5px;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #d63384;
        }
    </style>
</head>
<body>
<div class='container'>
    <h1>🚀 Enterprise Analytics System - Migrace databáze</h1>

    <div class='info'>
        <strong>📊 Co se chystá vytvořit:</strong><br>
        Kompletní enterprise-grade analytický systém s pokročilými funkcemi jako Google Analytics 4, Matomo, Microsoft Clarity, Hotjar a další.
    </div>

    <?php
    try {
        $pdo = getDbConnection();

        // Check existing tables
        echo "<h2>📋 Kontrola existujících tabulek</h2>";

        $tables = [
            'wgs_pageviews' => 'Základní pageviews (existující)',
            'wgs_analytics_sessions' => 'Advanced Session Engine',
            'wgs_analytics_fingerprints' => 'Device Fingerprinting',
            'wgs_analytics_events' => 'Event Tracking',
            'wgs_analytics_heatmap_clicks' => 'Click Heatmap Data',
            'wgs_analytics_heatmap_scroll' => 'Scroll Heatmap Data',
            'wgs_analytics_replay_frames' => 'Session Replay',
            'wgs_analytics_utm_campaigns' => 'UTM Campaign Tracking',
            'wgs_analytics_conversions' => 'Conversion Tracking',
            'wgs_analytics_bot_detections' => 'Bot Detection Logs',
            'wgs_analytics_geolocation_cache' => 'IP Geolocation Cache',
            'wgs_analytics_user_scores' => 'Engagement/Interest Scores',
            'wgs_analytics_realtime' => 'Real-time Tracking',
            'wgs_analytics_reports' => 'AI Reports Storage'
        ];

        $existingTables = [];
        $missingTables = [];

        foreach ($tables as $table => $desc) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                $existingTables[$table] = $desc;
            } else {
                $missingTables[$table] = $desc;
            }
        }

        if (count($existingTables) > 0) {
            echo "<div class='warning'>";
            echo "<strong>⚠️ Existující tabulky (" . count($existingTables) . "):</strong><br>";
            foreach ($existingTables as $table => $desc) {
                echo "• <code>$table</code> - $desc<br>";
            }
            echo "</div>";
        }

        if (count($missingTables) > 0) {
            echo "<div class='success'>";
            echo "<strong>Tabulky k vytvoření (" . count($missingTables) . "):</strong><br>";
            foreach ($missingTables as $table => $desc) {
                echo "• <code>$table</code> - $desc<br>";
            }
            echo "</div>";
        }

        // Display modules
        echo "<h2>🎯 Moduly k implementaci</h2>";

        $modules = [
            [
                'name' => '1. Session Engine (Advanced)',
                'features' => [
                    'Pokročilé sledování relací',
                    'Entry/exit pages',
                    'Engagement score',
                    'Frustration detection',
                    'Conversion paths',
                    'Device fingerprinting merge'
                ]
            ],
            [
                'name' => '2. Fingerprint Engine',
                'features' => [
                    'Canvas fingerprinting',
                    'WebGL vendor/renderer',
                    'Audio context hash',
                    'Browser feature detection',
                    'Stable cross-session ID'
                ]
            ],
            [
                'name' => '3. Bot & Security Engine',
                'features' => [
                    'AI-powered bot detection',
                    'Headless browser detection',
                    'VPN/Proxy/TOR detection',
                    'Scraping behavior analysis',
                    'Bot score computation',
                    'Threat level assessment'
                ]
            ],
            [
                'name' => '4. Geolocation Engine',
                'features' => [
                    'IP geolocation (ipapi.co)',
                    '3-day caching',
                    'Country/city/coordinates',
                    'ASN & ISP detection',
                    'Datacenter detection'
                ]
            ],
            [
                'name' => '5. Event Tracking Engine',
                'features' => [
                    'Click tracking',
                    'Scroll depth',
                    'Rage clicks',
                    'Copy/paste events',
                    'Form interactions',
                    'Mouse heatmap data'
                ]
            ],
            [
                'name' => '6. Heatmap Engine',
                'features' => [
                    'Click heatmap visualization',
                    'Scroll heatmap',
                    'Drop-off analysis',
                    'Device-specific heatmaps'
                ]
            ],
            [
                'name' => '7. Session Replay Engine',
                'features' => [
                    'Mouse movement recording',
                    'Scroll recording',
                    'Click timeline',
                    'DOM snapshot',
                    'Playback controls'
                ]
            ],
            [
                'name' => '8. UTM Campaign Engine',
                'features' => [
                    'UTM parameter tracking',
                    'Campaign performance',
                    'Conversion attribution',
                    'Multi-touch attribution'
                ]
            ],
            [
                'name' => '9. Conversion Engine',
                'features' => [
                    'Goal tracking',
                    'Funnel visualization',
                    'Multi-step funnels',
                    'Conversion paths'
                ]
            ],
            [
                'name' => '10. User Interest AI',
                'features' => [
                    'Interest score',
                    'Frustration score',
                    'Engagement score',
                    'Reading time analysis',
                    'Hesitation detection'
                ]
            ],
            [
                'name' => '11. Real-time Dashboard',
                'features' => [
                    'Live visitor count',
                    'Active sessions',
                    'Real-time events',
                    'Live heatmap',
                    'World map visualization'
                ]
            ],
            [
                'name' => '12. AI Reports Engine',
                'features' => [
                    'Daily/weekly reports',
                    'Trend analysis',
                    'Anomaly detection',
                    'Predictions',
                    'Bot activity reports'
                ]
            ],
            [
                'name' => '13. GDPR Compliance',
                'features' => [
                    'IP anonymization',
                    'Consent management',
                    'Data retention policies',
                    'Export/delete user data'
                ]
            ]
        ];

        foreach ($modules as $module) {
            echo "<div class='module'>";
            echo "<h3>{$module['name']}</h3>";
            echo "<ul>";
            foreach ($module['features'] as $feature) {
                echo "<li>$feature</li>";
            }
            echo "</ul>";
            echo "</div>";
        }

        // Execute migration
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {

            echo "<h2>⚙️ Spouštím migraci...</h2>";

            $pdo->beginTransaction();

            try {
                $createdTables = [];
                $errors = [];

                // TABLE 1: Enhanced pageviews (modify existing)
                if (in_array('wgs_pageviews', array_keys($existingTables))) {
                    echo "<div class='info'>Rozšiřuji existující tabulku <code>wgs_pageviews</code>...</div>";

                    // Add missing columns if needed
                    $alterations = [];

                    $stmt = $pdo->query("DESCRIBE wgs_pageviews");
                    $existingColumns = [];
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $existingColumns[] = $row['Field'];
                    }

                    $newColumns = [
                        'fingerprint_id' => 'VARCHAR(64) NULL AFTER session_id',
                        'engagement_score' => 'DECIMAL(5,2) NULL DEFAULT 0.00 AFTER visit_duration',
                        'frustration_score' => 'DECIMAL(5,2) NULL DEFAULT 0.00 AFTER engagement_score',
                        'scroll_depth' => 'INT NULL DEFAULT 0 COMMENT "Max scroll depth %" AFTER frustration_score',
                        'click_count' => 'INT NULL DEFAULT 0 AFTER scroll_depth',
                        'mouse_distance' => 'INT NULL DEFAULT 0 COMMENT "Total mouse movement in px" AFTER click_count',
                        'idle_time' => 'INT NULL DEFAULT 0 COMMENT "Idle time in seconds" AFTER mouse_distance',
                        'utm_source' => 'VARCHAR(100) NULL AFTER language',
                        'utm_medium' => 'VARCHAR(100) NULL AFTER utm_source',
                        'utm_campaign' => 'VARCHAR(100) NULL AFTER utm_medium',
                        'utm_content' => 'VARCHAR(100) NULL AFTER utm_campaign',
                        'utm_term' => 'VARCHAR(100) NULL AFTER utm_content',
                        'entry_page' => 'TINYINT(1) DEFAULT 0 AFTER utm_term',
                        'exit_page' => 'TINYINT(1) DEFAULT 0 AFTER entry_page'
                    ];

                    foreach ($newColumns as $col => $definition) {
                        if (!in_array($col, $existingColumns)) {
                            $pdo->exec("ALTER TABLE wgs_pageviews ADD COLUMN $col $definition");
                            $alterations[] = $col;
                        }
                    }

                    // Add indexes
                    $indexes = [
                        'idx_fingerprint' => 'fingerprint_id',
                        'idx_utm_campaign' => 'utm_campaign',
                        'idx_entry_page' => 'entry_page',
                        'idx_exit_page' => 'exit_page'
                    ];

                    foreach ($indexes as $indexName => $column) {
                        $stmt = $pdo->query("SHOW INDEX FROM wgs_pageviews WHERE Key_name = '$indexName'");
                        if ($stmt->rowCount() == 0) {
                            $pdo->exec("ALTER TABLE wgs_pageviews ADD INDEX $indexName ($column)");
                        }
                    }

                    if (count($alterations) > 0) {
                        echo "<div class='success'>Přidáno " . count($alterations) . " nových sloupců: " . implode(', ', $alterations) . "</div>";
                    } else {
                        echo "<div class='info'>Tabulka wgs_pageviews je již aktuální.</div>";
                    }
                }

                // TABLE 2: Analytics Sessions (Advanced)
                if (!in_array('wgs_analytics_sessions', array_keys($existingTables))) {
                    $sql = "
                    CREATE TABLE `wgs_analytics_sessions` (
                        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `session_id` VARCHAR(64) NOT NULL UNIQUE,
                        `fingerprint_id` VARCHAR(64) NULL,
                        `user_id` INT UNSIGNED NULL,
                        `ip_address` VARCHAR(45) NOT NULL,
                        `user_agent` VARCHAR(500) NULL,

                        -- Entry/Exit
                        `entry_page` VARCHAR(500) NULL,
                        `exit_page` VARCHAR(500) NULL,
                        `entry_time` TIMESTAMP NULL,
                        `exit_time` TIMESTAMP NULL,
                        `total_duration` INT UNSIGNED NULL DEFAULT 0 COMMENT 'Total session duration in seconds',
                        `active_duration` INT UNSIGNED NULL DEFAULT 0 COMMENT 'Active time excluding idle',
                        `idle_duration` INT UNSIGNED NULL DEFAULT 0,

                        -- Engagement metrics
                        `page_views` INT UNSIGNED DEFAULT 0,
                        `click_count` INT UNSIGNED DEFAULT 0,
                        `scroll_depth_avg` INT NULL DEFAULT 0,
                        `mouse_distance` INT UNSIGNED DEFAULT 0,
                        `engagement_score` DECIMAL(5,2) DEFAULT 0.00,
                        `frustration_score` DECIMAL(5,2) DEFAULT 0.00,
                        `interest_score` DECIMAL(5,2) DEFAULT 0.00,

                        -- Conversion
                        `is_converted` TINYINT(1) DEFAULT 0,
                        `conversion_type` VARCHAR(50) NULL,
                        `conversion_value` DECIMAL(10,2) NULL,

                        -- UTM
                        `utm_source` VARCHAR(100) NULL,
                        `utm_medium` VARCHAR(100) NULL,
                        `utm_campaign` VARCHAR(100) NULL,
                        `utm_content` VARCHAR(100) NULL,
                        `utm_term` VARCHAR(100) NULL,

                        -- Device
                        `device_type` VARCHAR(20) NULL,
                        `browser` VARCHAR(100) NULL,
                        `os` VARCHAR(100) NULL,
                        `screen_resolution` VARCHAR(20) NULL,
                        `viewport_width` INT NULL,
                        `viewport_height` INT NULL,

                        -- Geo
                        `country_code` VARCHAR(2) NULL,
                        `city` VARCHAR(100) NULL,
                        `latitude` DECIMAL(10, 7) NULL,
                        `longitude` DECIMAL(10, 7) NULL,

                        -- Bot detection
                        `is_bot` TINYINT(1) DEFAULT 0,
                        `bot_score` DECIMAL(5,2) DEFAULT 0.00,
                        `bot_type` VARCHAR(50) NULL,

                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                        INDEX `idx_session` (`session_id`),
                        INDEX `idx_fingerprint` (`fingerprint_id`),
                        INDEX `idx_user` (`user_id`),
                        INDEX `idx_ip` (`ip_address`),
                        INDEX `idx_created` (`created_at`),
                        INDEX `idx_is_bot` (`is_bot`),
                        INDEX `idx_is_converted` (`is_converted`),
                        INDEX `idx_utm_campaign` (`utm_campaign`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Advanced session tracking with engagement metrics';
                    ";

                    $pdo->exec($sql);
                    $createdTables[] = 'wgs_analytics_sessions';
                }

                // TABLE 3: Fingerprints
                if (!in_array('wgs_analytics_fingerprints', array_keys($existingTables))) {
                    $sql = "
                    CREATE TABLE `wgs_analytics_fingerprints` (
                        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `fingerprint_id` VARCHAR(64) NOT NULL UNIQUE,
                        `canvas_hash` VARCHAR(64) NULL,
                        `webgl_vendor` VARCHAR(200) NULL,
                        `webgl_renderer` VARCHAR(200) NULL,
                        `audio_hash` VARCHAR(64) NULL,
                        `timezone` VARCHAR(50) NULL,
                        `timezone_offset` INT NULL,
                        `screen_width` INT NULL,
                        `screen_height` INT NULL,
                        `color_depth` INT NULL,
                        `pixel_ratio` DECIMAL(3,2) NULL,
                        `touch_support` TINYINT(1) DEFAULT 0,
                        `hardware_concurrency` INT NULL,
                        `platform` VARCHAR(100) NULL,
                        `plugins_hash` VARCHAR(64) NULL,
                        `fonts_hash` VARCHAR(64) NULL,
                        `first_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `last_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        `session_count` INT UNSIGNED DEFAULT 1,
                        `device_map` JSON NULL COMMENT 'Maps of devices used with this fingerprint',

                        INDEX `idx_fingerprint` (`fingerprint_id`),
                        INDEX `idx_first_seen` (`first_seen`),
                        INDEX `idx_last_seen` (`last_seen`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Device fingerprinting for cross-session tracking';
                    ";

                    $pdo->exec($sql);
                    $createdTables[] = 'wgs_analytics_fingerprints';
                }

                // TABLE 4: Events
                if (!in_array('wgs_analytics_events', array_keys($existingTables))) {
                    $sql = "
                    CREATE TABLE `wgs_analytics_events` (
                        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `session_id` VARCHAR(64) NOT NULL,
                        `fingerprint_id` VARCHAR(64) NULL,
                        `event_type` VARCHAR(50) NOT NULL COMMENT 'click, scroll, copy, paste, rage_click, etc',
                        `page_url` VARCHAR(500) NOT NULL,
                        `timestamp` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
                        `element_selector` VARCHAR(500) NULL,
                        `element_text` VARCHAR(200) NULL,
                        `x_position` INT NULL,
                        `y_position` INT NULL,
                        `viewport_width` INT NULL,
                        `viewport_height` INT NULL,
                        `scroll_depth` INT NULL,
                        `event_data` JSON NULL COMMENT 'Extra event payload',

                        INDEX `idx_session` (`session_id`),
                        INDEX `idx_fingerprint` (`fingerprint_id`),
                        INDEX `idx_event_type` (`event_type`),
                        INDEX `idx_page` (`page_url`(100)),
                        INDEX `idx_timestamp` (`timestamp`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='User interaction events tracking';
                    ";

                    $pdo->exec($sql);
                    $createdTables[] = 'wgs_analytics_events';
                }

                // TABLE 5: Heatmap Clicks
                if (!in_array('wgs_analytics_heatmap_clicks', array_keys($existingTables))) {
                    $sql = "
                    CREATE TABLE `wgs_analytics_heatmap_clicks` (
                        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `page_url` VARCHAR(500) NOT NULL,
                        `x_percent` DECIMAL(5,2) NOT NULL COMMENT 'X position as % of viewport width',
                        `y_percent` DECIMAL(5,2) NOT NULL COMMENT 'Y position as % of viewport height',
                        `viewport_width` INT NOT NULL,
                        `viewport_height` INT NOT NULL,
                        `device_type` VARCHAR(20) NULL,
                        `browser` VARCHAR(100) NULL,
                        `session_id` VARCHAR(64) NULL,
                        `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                        INDEX `idx_page` (`page_url`(100)),
                        INDEX `idx_device` (`device_type`),
                        INDEX `idx_timestamp` (`timestamp`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Click heatmap data points';
                    ";

                    $pdo->exec($sql);
                    $createdTables[] = 'wgs_analytics_heatmap_clicks';
                }

                // TABLE 6: Heatmap Scroll
                if (!in_array('wgs_analytics_heatmap_scroll', array_keys($existingTables))) {
                    $sql = "
                    CREATE TABLE `wgs_analytics_heatmap_scroll` (
                        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `page_url` VARCHAR(500) NOT NULL,
                        `scroll_depth_percent` INT NOT NULL COMMENT 'Max scroll depth reached',
                        `viewport_height` INT NOT NULL,
                        `page_height` INT NOT NULL,
                        `device_type` VARCHAR(20) NULL,
                        `session_id` VARCHAR(64) NULL,
                        `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                        INDEX `idx_page` (`page_url`(100)),
                        INDEX `idx_device` (`device_type`),
                        INDEX `idx_timestamp` (`timestamp`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Scroll depth heatmap data';
                    ";

                    $pdo->exec($sql);
                    $createdTables[] = 'wgs_analytics_heatmap_scroll';
                }

                // TABLE 7: Session Replay Frames
                if (!in_array('wgs_analytics_replay_frames', array_keys($existingTables))) {
                    $sql = "
                    CREATE TABLE `wgs_analytics_replay_frames` (
                        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `session_id` VARCHAR(64) NOT NULL,
                        `page_url` VARCHAR(500) NOT NULL,
                        `frame_index` INT UNSIGNED NOT NULL,
                        `timestamp_offset` INT UNSIGNED NOT NULL COMMENT 'Milliseconds from session start',
                        `event_type` VARCHAR(50) NOT NULL COMMENT 'mousemove, click, scroll, resize',
                        `data` JSON NOT NULL COMMENT 'Frame data (x, y, scroll, etc)',
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                        INDEX `idx_session` (`session_id`),
                        INDEX `idx_page` (`page_url`(100)),
                        INDEX `idx_frame` (`frame_index`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Session replay frame-by-frame data';
                    ";

                    $pdo->exec($sql);
                    $createdTables[] = 'wgs_analytics_replay_frames';
                }

                // TABLE 8: UTM Campaigns
                if (!in_array('wgs_analytics_utm_campaigns', array_keys($existingTables))) {
                    $sql = "
                    CREATE TABLE `wgs_analytics_utm_campaigns` (
                        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `utm_source` VARCHAR(100) NULL,
                        `utm_medium` VARCHAR(100) NULL,
                        `utm_campaign` VARCHAR(100) NULL,
                        `utm_content` VARCHAR(100) NULL,
                        `utm_term` VARCHAR(100) NULL,
                        `visit_count` INT UNSIGNED DEFAULT 0,
                        `unique_visitors` INT UNSIGNED DEFAULT 0,
                        `conversion_count` INT UNSIGNED DEFAULT 0,
                        `conversion_rate` DECIMAL(5,2) DEFAULT 0.00,
                        `total_revenue` DECIMAL(10,2) DEFAULT 0.00,
                        `first_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `last_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                        UNIQUE KEY `idx_campaign` (`utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`),
                        INDEX `idx_utm_campaign` (`utm_campaign`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='UTM campaign performance aggregation';
                    ";

                    $pdo->exec($sql);
                    $createdTables[] = 'wgs_analytics_utm_campaigns';
                }

                // TABLE 9: Conversions
                if (!in_array('wgs_analytics_conversions', array_keys($existingTables))) {
                    $sql = "
                    CREATE TABLE `wgs_analytics_conversions` (
                        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `session_id` VARCHAR(64) NOT NULL,
                        `fingerprint_id` VARCHAR(64) NULL,
                        `user_id` INT UNSIGNED NULL,
                        `conversion_type` VARCHAR(50) NOT NULL COMMENT 'form_submit, login, contact, purchase',
                        `conversion_value` DECIMAL(10,2) NULL,
                        `conversion_goal` VARCHAR(100) NULL,
                        `conversion_path` JSON NULL COMMENT 'Array of pages visited before conversion',
                        `utm_source` VARCHAR(100) NULL,
                        `utm_medium` VARCHAR(100) NULL,
                        `utm_campaign` VARCHAR(100) NULL,
                        `time_to_conversion` INT UNSIGNED NULL COMMENT 'Seconds from entry to conversion',
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                        INDEX `idx_session` (`session_id`),
                        INDEX `idx_fingerprint` (`fingerprint_id`),
                        INDEX `idx_user` (`user_id`),
                        INDEX `idx_type` (`conversion_type`),
                        INDEX `idx_utm_campaign` (`utm_campaign`),
                        INDEX `idx_created` (`created_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Conversion tracking and attribution';
                    ";

                    $pdo->exec($sql);
                    $createdTables[] = 'wgs_analytics_conversions';
                }

                // TABLE 10: Bot Detections
                if (!in_array('wgs_analytics_bot_detections', array_keys($existingTables))) {
                    $sql = "
                    CREATE TABLE `wgs_analytics_bot_detections` (
                        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `session_id` VARCHAR(64) NOT NULL,
                        `ip_address` VARCHAR(45) NOT NULL,
                        `user_agent` VARCHAR(500) NULL,
                        `bot_score` DECIMAL(5,2) NOT NULL COMMENT '0-100 probability of being a bot',
                        `bot_type` VARCHAR(50) NULL COMMENT 'googlebot, scraper, headless, unknown',
                        `threat_level` ENUM('none', 'low', 'medium', 'high', 'critical') DEFAULT 'none',
                        `detection_reasons` JSON NULL COMMENT 'Array of detection flags',
                        `is_vpn` TINYINT(1) DEFAULT 0,
                        `is_proxy` TINYINT(1) DEFAULT 0,
                        `is_tor` TINYINT(1) DEFAULT 0,
                        `is_datacenter` TINYINT(1) DEFAULT 0,
                        `is_headless` TINYINT(1) DEFAULT 0,
                        `anomaly_flags` JSON NULL,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                        INDEX `idx_session` (`session_id`),
                        INDEX `idx_ip` (`ip_address`),
                        INDEX `idx_bot_score` (`bot_score`),
                        INDEX `idx_threat_level` (`threat_level`),
                        INDEX `idx_created` (`created_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Bot and security threat detection logs';
                    ";

                    $pdo->exec($sql);
                    $createdTables[] = 'wgs_analytics_bot_detections';
                }

                // TABLE 11: Geolocation Cache
                if (!in_array('wgs_analytics_geolocation_cache', array_keys($existingTables))) {
                    $sql = "
                    CREATE TABLE `wgs_analytics_geolocation_cache` (
                        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `ip_address` VARCHAR(45) NOT NULL UNIQUE,
                        `country_code` VARCHAR(2) NULL,
                        `country_name` VARCHAR(100) NULL,
                        `region` VARCHAR(100) NULL,
                        `city` VARCHAR(100) NULL,
                        `latitude` DECIMAL(10, 7) NULL,
                        `longitude` DECIMAL(10, 7) NULL,
                        `zip_code` VARCHAR(20) NULL,
                        `timezone` VARCHAR(50) NULL,
                        `isp` VARCHAR(200) NULL,
                        `asn` VARCHAR(20) NULL,
                        `is_proxy` TINYINT(1) DEFAULT 0,
                        `is_vpn` TINYINT(1) DEFAULT 0,
                        `is_tor` TINYINT(1) DEFAULT 0,
                        `is_datacenter` TINYINT(1) DEFAULT 0,
                        `cached_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `expires_at` TIMESTAMP NULL COMMENT '3-day cache expiration',

                        INDEX `idx_ip` (`ip_address`),
                        INDEX `idx_country` (`country_code`),
                        INDEX `idx_expires` (`expires_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='IP geolocation cache (3-day retention)';
                    ";

                    $pdo->exec($sql);
                    $createdTables[] = 'wgs_analytics_geolocation_cache';
                }

                // TABLE 12: User Scores
                if (!in_array('wgs_analytics_user_scores', array_keys($existingTables))) {
                    $sql = "
                    CREATE TABLE `wgs_analytics_user_scores` (
                        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `session_id` VARCHAR(64) NOT NULL,
                        `fingerprint_id` VARCHAR(64) NULL,
                        `page_url` VARCHAR(500) NOT NULL,
                        `engagement_score` DECIMAL(5,2) DEFAULT 0.00 COMMENT '0-100 engagement level',
                        `frustration_score` DECIMAL(5,2) DEFAULT 0.00 COMMENT '0-100 frustration level',
                        `interest_score` DECIMAL(5,2) DEFAULT 0.00 COMMENT '0-100 interest level',
                        `reading_time` INT UNSIGNED NULL COMMENT 'Estimated reading time in seconds',
                        `hesitation_time` INT UNSIGNED NULL COMMENT 'Time before first interaction',
                        `scroll_quality` DECIMAL(5,2) NULL COMMENT 'Smooth vs erratic scrolling',
                        `click_quality` DECIMAL(5,2) NULL COMMENT 'Purposeful vs random clicks',
                        `mouse_activity` DECIMAL(5,2) NULL COMMENT 'Mouse movement intensity',
                        `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                        INDEX `idx_session` (`session_id`),
                        INDEX `idx_fingerprint` (`fingerprint_id`),
                        INDEX `idx_page` (`page_url`(100)),
                        INDEX `idx_engagement` (`engagement_score`),
                        INDEX `idx_frustration` (`frustration_score`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='AI-computed user engagement and interest scores';
                    ";

                    $pdo->exec($sql);
                    $createdTables[] = 'wgs_analytics_user_scores';
                }

                // TABLE 13: Real-time Tracking
                if (!in_array('wgs_analytics_realtime', array_keys($existingTables))) {
                    $sql = "
                    CREATE TABLE `wgs_analytics_realtime` (
                        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `session_id` VARCHAR(64) NOT NULL,
                        `fingerprint_id` VARCHAR(64) NULL,
                        `user_id` INT UNSIGNED NULL,
                        `page_url` VARCHAR(500) NOT NULL,
                        `is_bot` TINYINT(1) DEFAULT 0,
                        `country_code` VARCHAR(2) NULL,
                        `city` VARCHAR(100) NULL,
                        `device_type` VARCHAR(20) NULL,
                        `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        `expires_at` TIMESTAMP NULL COMMENT 'Auto-delete after 5 minutes of inactivity',

                        UNIQUE KEY `idx_session` (`session_id`),
                        INDEX `idx_expires` (`expires_at`),
                        INDEX `idx_last_activity` (`last_activity`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Real-time active sessions (auto-cleanup)';
                    ";

                    $pdo->exec($sql);
                    $createdTables[] = 'wgs_analytics_realtime';
                }

                // TABLE 14: AI Reports
                if (!in_array('wgs_analytics_reports', array_keys($existingTables))) {
                    $sql = "
                    CREATE TABLE `wgs_analytics_reports` (
                        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `report_type` ENUM('daily', 'weekly', 'monthly') NOT NULL,
                        `report_date` DATE NOT NULL,
                        `summary` TEXT NULL,
                        `metrics` JSON NULL COMMENT 'Key metrics summary',
                        `trends` JSON NULL COMMENT 'Trend analysis',
                        `anomalies` JSON NULL COMMENT 'Detected anomalies',
                        `predictions` JSON NULL COMMENT 'Predictions for next period',
                        `top_pages` JSON NULL,
                        `bot_activity` JSON NULL,
                        `conversion_trends` JSON NULL,
                        `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                        UNIQUE KEY `idx_report` (`report_type`, `report_date`),
                        INDEX `idx_date` (`report_date`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='AI-generated analytics reports';
                    ";

                    $pdo->exec($sql);
                    $createdTables[] = 'wgs_analytics_reports';
                }

                $pdo->commit();

                // Success summary
                echo "<h2>Migrace úspěšně dokončena!</h2>";

                echo "<div class='stats'>";
                echo "<div class='stat-card green'>";
                echo "<div class='stat-value'>" . count($createdTables) . "</div>";
                echo "<div class='stat-label'>Nových tabulek</div>";
                echo "</div>";

                echo "<div class='stat-card blue'>";
                echo "<div class='stat-value'>13</div>";
                echo "<div class='stat-label'>Modulů aktivních</div>";
                echo "</div>";

                echo "<div class='stat-card'>";
                echo "<div class='stat-value'>100%</div>";
                echo "<div class='stat-label'>Připraveno</div>";
                echo "</div>";
                echo "</div>";

                if (count($createdTables) > 0) {
                    echo "<div class='success'>";
                    echo "<strong>📊 Vytvořené tabulky:</strong><br>";
                    foreach ($createdTables as $table) {
                        echo "• <code>$table</code><br>";
                    }
                    echo "</div>";
                }

                echo "<div class='info'>";
                echo "<strong>🎯 Další kroky:</strong><br>";
                echo "1. Implementovat tracker.js v2 s pokročilým sledováním<br>";
                echo "2. Vytvořit PHP tracking API endpoints<br>";
                echo "3. Implementovat admin UI v češtině<br>";
                echo "4. Nastavit cron jobs pro reports a cleanup<br>";
                echo "5. Otestovat GDPR compliance<br>";
                echo "</div>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>CHYBA PŘI MIGRACI:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }

        } else {
            // Preview mode
            echo "<h2>⚡ Připraveno k spuštění</h2>";
            echo "<div class='info'>";
            echo "<strong>Tato migrace vytvoří:</strong><br>";
            echo "• " . count($missingTables) . " nových tabulek<br>";
            echo "• Rozšíření existující tabulky wgs_pageviews<br>";
            echo "• Kompletní enterprise analytics systém<br>";
            echo "• Všechny potřebné indexy pro výkon<br>";
            echo "</div>";

            echo "<a href='?execute=1' class='btn'>🚀 SPUSTIT MIGRACI</a>";
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
