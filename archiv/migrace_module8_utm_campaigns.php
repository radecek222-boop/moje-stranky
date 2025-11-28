<?php
/**
 * Migrace: Module #8 - UTM Campaign Tracking Engine
 *
 * Vytvoří tabulku pro agregované statistiky UTM kampaní.
 *
 * Tabulka: wgs_analytics_utm_campaigns
 * - Denní agregace campaign metrik (sessions, conversions, revenue)
 * - Support pro attribution modely (first-click, last-click, linear)
 * - Device-specific statistiky
 * - UPSERT pattern pro agregaci
 *
 * Spustit: migrace_module8_utm_campaigns.php?execute=1
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #8 - UTM Campaign Tracking
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
    <title>Migrace: Module #8 - UTM Campaign Tracking</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 50px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }

        h1 {
            color: #333333;
            border-bottom: 4px solid #333333;
            padding-bottom: 15px;
            margin-bottom: 30px;
            font-size: 28px;
        }

        h2 {
            color: #444;
            margin: 25px 0 15px 0;
            font-size: 20px;
            border-left: 4px solid #667eea;
            padding-left: 15px;
        }

        .info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(56, 239, 125, 0.4);
        }

        .warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(245, 87, 108, 0.4);
        }

        .error {
            background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(252, 74, 26, 0.4);
        }

        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: transform 0.2s, box-shadow 0.2s;
            margin: 10px 5px 10px 0;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #bdc3c7 0%, #2c3e50 100%);
        }

        pre {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.5;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-primary { background: #667eea; color: white; }
        .badge-success { background: #38ef7d; color: white; }
        .badge-info { background: #3498db; color: white; }
    </style>
</head>
<body>
    <div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🚀 Module #8: UTM Campaign Tracking Engine</h1>";

    echo "<div class='info'>";
    echo "<h2 style='color: white; border: none; margin: 0 0 10px 0; padding: 0;'>📋 Informace o migraci</h2>";
    echo "<strong>Modul:</strong> UTM Campaign Tracking Engine<br>";
    echo "<strong>Verze:</strong> 1.0.0<br>";
    echo "<strong>Datum:</strong> 2025-11-23<br>";
    echo "<strong>Databáze:</strong> " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "<br>";
    echo "<strong>Popis:</strong> Vytvoří tabulku pro agregované statistiky UTM kampaní s podporou attribution modelů.";
    echo "</div>";

    // ========================================
    // KONTROLA EXISTENCE TABULKY
    // ========================================

    echo "<h2>🔍 Kontrola existence tabulky</h2>";

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_utm_campaigns'");
    $tabulkaExistuje = $stmt->rowCount() > 0;

    if ($tabulkaExistuje) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ VAROVÁNÍ:</strong> Tabulka <code>wgs_analytics_utm_campaigns</code> již existuje!<br>";
        echo "Migrace může být již spuštěna. Pokud chcete migraci spustit znovu, nejprve odstraňte tabulku.";
        echo "</div>";

        // Zobrazit strukturu existující tabulky
        $stmt = $pdo->query("DESCRIBE wgs_analytics_utm_campaigns");
        $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<h2>📊 Aktuální struktura tabulky</h2>";
        echo "<table>";
        echo "<tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Klíč</th><th>Default</th></tr>";
        foreach ($sloupce as $sloupec) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($sloupec['Field']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($sloupec['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($sloupec['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($sloupec['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($sloupec['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        echo "<a href='admin.php' class='btn btn-secondary'>← Zpět na Admin</a>";

    } else {
        echo "<div class='success'>";
        echo "Tabulka <code>wgs_analytics_utm_campaigns</code> neexistuje. Můžeme pokračovat s migrací.";
        echo "</div>";

        // ========================================
        // NÁHLED SQL
        // ========================================

        if (!isset($_GET['execute']) || $_GET['execute'] !== '1') {
            echo "<h2>📄 SQL příkazy k provedení</h2>";

            echo "<pre>CREATE TABLE `wgs_analytics_utm_campaigns` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Campaign identification
    `utm_source` VARCHAR(100) NULL,
    `utm_medium` VARCHAR(100) NULL,
    `utm_campaign` VARCHAR(200) NULL,
    `utm_content` VARCHAR(200) NULL,
    `utm_term` VARCHAR(200) NULL,

    -- Agregace
    `date` DATE NOT NULL,
    `device_type` ENUM('desktop', 'mobile', 'tablet') NULL,

    -- Traffic metriky
    `sessions_count` INT UNSIGNED DEFAULT 0,
    `pageviews_count` INT UNSIGNED DEFAULT 0,
    `unique_visitors` INT UNSIGNED DEFAULT 0,

    -- Engagement metriky
    `avg_session_duration` DECIMAL(10,2) DEFAULT 0,
    `avg_pages_per_session` DECIMAL(5,2) DEFAULT 0,
    `bounce_rate` DECIMAL(5,2) DEFAULT 0,

    -- Conversion metriky
    `conversions_count` INT UNSIGNED DEFAULT 0,
    `conversion_value` DECIMAL(12,2) DEFAULT 0,
    `conversion_rate` DECIMAL(5,2) DEFAULT 0,

    -- Attribution models
    `first_click_conversions` INT UNSIGNED DEFAULT 0,
    `last_click_conversions` INT UNSIGNED DEFAULT 0,
    `linear_attribution_value` DECIMAL(12,2) DEFAULT 0,

    -- Timestamps
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_campaign_date` (`utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`, `date`, `device_type`),
    INDEX `idx_date` (`date`),
    INDEX `idx_campaign` (`utm_campaign`),
    INDEX `idx_source` (`utm_source`),
    INDEX `idx_medium` (`utm_medium`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</pre>";

            echo "<div class='info'>";
            echo "<h2 style='color: white; border: none; margin: 0 0 10px 0; padding: 0;'>ℹ️ Co tato migrace provede?</h2>";
            echo "<ol style='margin: 10px 0 0 20px; line-height: 1.8;'>";
            echo "<li>Vytvoří tabulku <code>wgs_analytics_utm_campaigns</code></li>";
            echo "<li>Nastaví UNIQUE constraint pro zamezení duplikátů (utm_* + date + device_type)</li>";
            echo "<li>Vytvoří 5 indexů pro rychlé dotazy (date, campaign, source, medium)</li>";
            echo "<li>Podpora pro 3 attribution modely (first-click, last-click, linear)</li>";
            echo "<li>Připraveno pro UPSERT pattern (INSERT ON DUPLICATE KEY UPDATE)</li>";
            echo "</ol>";
            echo "</div>";

            echo "<a href='?execute=1' class='btn btn-success'>▶ SPUSTIT MIGRACI</a>";
            echo "<a href='admin.php' class='btn btn-secondary'>← Zpět na Admin</a>";
        }

        // ========================================
        // PROVEDENÍ MIGRACE
        // ========================================

        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<h2>⚙️ Provádím migraci...</h2>";

            $pdo->beginTransaction();

            try {
                // Vytvoření tabulky
                $pdo->exec("
                    CREATE TABLE `wgs_analytics_utm_campaigns` (
                        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                        -- Campaign identification
                        `utm_source` VARCHAR(100) NULL COMMENT 'UTM source (facebook, google, newsletter)',
                        `utm_medium` VARCHAR(100) NULL COMMENT 'UTM medium (cpc, email, social)',
                        `utm_campaign` VARCHAR(200) NULL COMMENT 'UTM campaign name',
                        `utm_content` VARCHAR(200) NULL COMMENT 'UTM content variant',
                        `utm_term` VARCHAR(200) NULL COMMENT 'UTM search term/keyword',

                        -- Agregace
                        `date` DATE NOT NULL COMMENT 'Den agregace',
                        `device_type` ENUM('desktop', 'mobile', 'tablet') NULL COMMENT 'Typ zařízení',

                        -- Traffic metriky
                        `sessions_count` INT UNSIGNED DEFAULT 0 COMMENT 'Počet sessions',
                        `pageviews_count` INT UNSIGNED DEFAULT 0 COMMENT 'Počet pageviews',
                        `unique_visitors` INT UNSIGNED DEFAULT 0 COMMENT 'Unikátní fingerprint_id',

                        -- Engagement metriky
                        `avg_session_duration` DECIMAL(10,2) DEFAULT 0 COMMENT 'Průměrná délka session (sekundy)',
                        `avg_pages_per_session` DECIMAL(5,2) DEFAULT 0 COMMENT 'Průměrný počet stránek/session',
                        `bounce_rate` DECIMAL(5,2) DEFAULT 0 COMMENT 'Bounce rate (%)',

                        -- Conversion metriky
                        `conversions_count` INT UNSIGNED DEFAULT 0 COMMENT 'Počet konverzí',
                        `conversion_value` DECIMAL(12,2) DEFAULT 0 COMMENT 'Celková hodnota konverzí',
                        `conversion_rate` DECIMAL(5,2) DEFAULT 0 COMMENT 'Conversion rate (%)',

                        -- Attribution models
                        `first_click_conversions` INT UNSIGNED DEFAULT 0 COMMENT 'First-click attribution',
                        `last_click_conversions` INT UNSIGNED DEFAULT 0 COMMENT 'Last-click attribution',
                        `linear_attribution_value` DECIMAL(12,2) DEFAULT 0 COMMENT 'Linear attribution value',

                        -- Timestamps
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                        PRIMARY KEY (`id`),
                        UNIQUE KEY `idx_campaign_date` (`utm_source`(50), `utm_medium`(50), `utm_campaign`(100), `utm_content`(100), `utm_term`(100), `date`, `device_type`),
                        INDEX `idx_date` (`date`),
                        INDEX `idx_campaign` (`utm_campaign`(100)),
                        INDEX `idx_source` (`utm_source`(50)),
                        INDEX `idx_medium` (`utm_medium`(50))
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                      COMMENT='Agregované denní statistiky UTM kampaní (Module #8)'
                ");

                $pdo->commit();

                echo "<div class='success'>";
                echo "<h2 style='color: white; border: none; margin: 0 0 10px 0; padding: 0;'>MIGRACE ÚSPĚŠNĚ DOKONČENA</h2>";
                echo "<strong>Tabulka vytvořena:</strong> wgs_analytics_utm_campaigns<br>";
                echo "<strong>Engine:</strong> InnoDB<br>";
                echo "<strong>Charset:</strong> utf8mb4_unicode_ci<br>";
                echo "<strong>Indexy:</strong> 5 (PRIMARY + 4 indexy)<br>";
                echo "<strong>Unique constraint:</strong> utm_source + utm_medium + utm_campaign + utm_content + utm_term + date + device_type";
                echo "</div>";

                // Zobrazit strukturu vytvořené tabulky
                $stmt = $pdo->query("DESCRIBE wgs_analytics_utm_campaigns");
                $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo "<h2>📊 Struktura vytvořené tabulky</h2>";
                echo "<table>";
                echo "<tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Klíč</th><th>Default</th><th>Extra</th></tr>";
                foreach ($sloupce as $sloupec) {
                    echo "<tr>";
                    echo "<td><strong>" . htmlspecialchars($sloupec['Field']) . "</strong></td>";
                    echo "<td><span class='badge badge-info'>" . htmlspecialchars($sloupec['Type']) . "</span></td>";
                    echo "<td>" . htmlspecialchars($sloupec['Null']) . "</td>";
                    echo "<td>" . ($sloupec['Key'] ? "<span class='badge badge-primary'>" . htmlspecialchars($sloupec['Key']) . "</span>" : '-') . "</td>";
                    echo "<td>" . htmlspecialchars($sloupec['Default'] ?? 'NULL') . "</td>";
                    echo "<td>" . htmlspecialchars($sloupec['Extra']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";

                // Zobrazit indexy
                $stmt = $pdo->query("SHOW INDEX FROM wgs_analytics_utm_campaigns");
                $indexy = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo "<h2>🔑 Indexy tabulky</h2>";
                echo "<table>";
                echo "<tr><th>Index</th><th>Sloupec</th><th>Typ</th><th>Unique</th></tr>";
                foreach ($indexy as $index) {
                    echo "<tr>";
                    echo "<td><strong>" . htmlspecialchars($index['Key_name']) . "</strong></td>";
                    echo "<td>" . htmlspecialchars($index['Column_name']) . "</td>";
                    echo "<td><span class='badge badge-info'>" . htmlspecialchars($index['Index_type']) . "</span></td>";
                    echo "<td>" . ($index['Non_unique'] == 0 ? "<span class='badge badge-success'>YES</span>" : "NO") . "</td>";
                    echo "</tr>";
                }
                echo "</table>";

                echo "<div class='info'>";
                echo "<h2 style='color: white; border: none; margin: 0 0 10px 0; padding: 0;'>📝 Další kroky</h2>";
                echo "<ol style='margin: 10px 0 0 20px; line-height: 1.8;'>";
                echo "<li>Implementovat backend třídu <code>includes/CampaignAttribution.php</code></li>";
                echo "<li>Vytvořit API endpoint <code>api/analytics_campaigns.php</code></li>";
                echo "<li>Vytvořit admin UI <code>analytics-campaigns.php</code></li>";
                echo "<li>Vytvořit cron job <code>scripts/aggregate_campaign_stats.php</code></li>";
                echo "<li>Upravit <code>tracker-v2.js</code> pro UTM parsing</li>";
                echo "<li>Testovat agregaci a attribution modely</li>";
                echo "</ol>";
                echo "</div>";

                echo "<a href='analytics-campaigns.php' class='btn btn-success'>📊 Otevřít Campaign Dashboard</a>";
                echo "<a href='admin.php' class='btn btn-secondary'>← Zpět na Admin</a>";

            } catch (PDOException $e) {
                $pdo->rollBack();

                echo "<div class='error'>";
                echo "<h2 style='color: white; border: none; margin: 0 0 10px 0; padding: 0;'>CHYBA PŘI MIGRACI</h2>";
                echo "<strong>Chybová zpráva:</strong><br>";
                echo "<pre style='background: rgba(255,255,255,0.2); border: none; color: white;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
                echo "<strong>Trace:</strong><br>";
                echo "<pre style='background: rgba(255,255,255,0.2); border: none; color: white; max-height: 200px; overflow-y: auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
                echo "</div>";

                echo "<a href='?execute=0' class='btn'>🔄 Zkusit znovu</a>";
                echo "<a href='admin.php' class='btn btn-secondary'>← Zpět na Admin</a>";
            }
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2 style='color: white; border: none; margin: 0 0 10px 0; padding: 0;'>NEOČEKÁVANÁ CHYBA</h2>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";

    echo "<a href='admin.php' class='btn btn-secondary'>← Zpět na Admin</a>";
}

echo "</div>
</body>
</html>";
?>
