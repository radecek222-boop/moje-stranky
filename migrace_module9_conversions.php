<?php
/**
 * Migrace: Module #9 - Conversion Funnel Engine
 *
 * Vytvoří tabulky pro conversion tracking a funnel analýzu.
 *
 * Tabulky:
 * - wgs_analytics_conversions: Zaznamenání všech konverzí
 * - wgs_analytics_funnels: Definice conversion funnelů
 *
 * Spustit: migrace_module9_conversions.php?execute=1
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #9 - Conversion Funnels
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
    <title>Migrace: Module #9 - Conversion Funnels</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
            color: #2D5016;
            border-bottom: 4px solid #2D5016;
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

    echo "<h1>🚀 Module #9: Conversion Funnel Engine</h1>";

    echo "<div class='info'>";
    echo "<h2 style='color: white; border: none; margin: 0 0 10px 0; padding: 0;'>📋 Informace o migraci</h2>";
    echo "<strong>Modul:</strong> Conversion Funnel Engine<br>";
    echo "<strong>Verze:</strong> 1.0.0<br>";
    echo "<strong>Datum:</strong> 2025-11-23<br>";
    echo "<strong>Databáze:</strong> " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "<br>";
    echo "<strong>Popis:</strong> Vytvoří tabulky pro conversion tracking a funnel analýzu včetně seed dat.";
    echo "</div>";

    // ========================================
    // KONTROLA EXISTENCE TABULEK
    // ========================================

    echo "<h2>🔍 Kontrola existence tabulek</h2>";

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_conversions'");
    $conversionsExistuje = $stmt->rowCount() > 0;

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_funnels'");
    $funnelsExistuje = $stmt->rowCount() > 0;

    if ($conversionsExistuje || $funnelsExistuje) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ VAROVÁNÍ:</strong> Některé tabulky již existují!<br>";
        if ($conversionsExistuje) echo "- <code>wgs_analytics_conversions</code> existuje<br>";
        if ($funnelsExistuje) echo "- <code>wgs_analytics_funnels</code> existuje<br>";
        echo "Migrace může být již spuštěna. Pokud chcete migraci spustit znovu, nejprve odstraňte tabulky.";
        echo "</div>";

        echo "<a href='admin.php' class='btn btn-secondary'>← Zpět na Admin</a>";

    } else {
        echo "<div class='success'>";
        echo "✅ Tabulky neexistují. Můžeme pokračovat s migrací.";
        echo "</div>";

        // ========================================
        // NÁHLED SQL
        // ========================================

        if (!isset($_GET['execute']) || $_GET['execute'] !== '1') {
            echo "<h2>📄 SQL příkazy k provedení</h2>";

            echo "<h3>Tabulka 1: wgs_analytics_conversions</h3>";
            echo "<pre>CREATE TABLE `wgs_analytics_conversions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(64) NOT NULL,
    `fingerprint_id` VARCHAR(64) NOT NULL,
    `conversion_type` ENUM(...) NOT NULL,
    `conversion_label` VARCHAR(100) NULL,
    `conversion_value` DECIMAL(12,2) DEFAULT 0,
    `conversion_path` JSON NULL,
    `time_to_conversion` INT UNSIGNED NULL,
    `steps_to_conversion` TINYINT UNSIGNED NULL,
    `utm_source` VARCHAR(100) NULL,
    `utm_medium` VARCHAR(100) NULL,
    `utm_campaign` VARCHAR(200) NULL,
    `utm_content` VARCHAR(200) NULL,
    `utm_term` VARCHAR(200) NULL,
    `page_url` VARCHAR(500) NULL,
    `device_type` ENUM('desktop', 'mobile', 'tablet') NULL,
    `country` VARCHAR(2) NULL,
    `metadata` JSON NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_session` (`session_id`),
    INDEX `idx_fingerprint` (`fingerprint_id`),
    INDEX `idx_type` (`conversion_type`),
    INDEX `idx_date` (`created_at`),
    INDEX `idx_campaign` (`utm_campaign`),
    INDEX `idx_source_medium` (`utm_source`, `utm_medium`)
) ENGINE=InnoDB;</pre>";

            echo "<h3>Tabulka 2: wgs_analytics_funnels</h3>";
            echo "<pre>CREATE TABLE `wgs_analytics_funnels` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `funnel_name` VARCHAR(100) NOT NULL,
    `funnel_description` TEXT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `funnel_steps` JSON NOT NULL,
    `goal_conversion_type` VARCHAR(50) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_name` (`funnel_name`)
) ENGINE=InnoDB;</pre>";

            echo "<div class='info'>";
            echo "<h2 style='color: white; border: none; margin: 0 0 10px 0; padding: 0;'>ℹ️ Co tato migrace provede?</h2>";
            echo "<ol style='margin: 10px 0 0 20px; line-height: 1.8;'>";
            echo "<li>Vytvoří tabulku <code>wgs_analytics_conversions</code> pro ukládání konverzí</li>";
            echo "<li>Vytvoří tabulku <code>wgs_analytics_funnels</code> pro definice trychtýřů</li>";
            echo "<li>Vloží 2 ukázkové funnely (Contact Form, Purchase)</li>";
            echo "<li>Vytvoří 6 indexů pro rychlé dotazy</li>";
            echo "<li>Integrace s UTM campaign tracking (Modul #8)</li>";
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
                // ====================
                // TABULKA 1: conversions
                // ====================
                $pdo->exec("
                    CREATE TABLE `wgs_analytics_conversions` (
                        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                        -- Identifikace
                        `session_id` VARCHAR(64) NOT NULL COMMENT 'FK to wgs_analytics_sessions',
                        `fingerprint_id` VARCHAR(64) NOT NULL COMMENT 'FK to wgs_analytics_fingerprints',

                        -- Conversion details
                        `conversion_type` ENUM('form_submit', 'login', 'contact', 'purchase', 'registration', 'download', 'newsletter', 'quote_request', 'custom') NOT NULL,
                        `conversion_label` VARCHAR(100) NULL COMMENT 'Custom label',
                        `conversion_value` DECIMAL(12,2) DEFAULT 0 COMMENT 'Hodnota v Kč',

                        -- Conversion path & timing
                        `conversion_path` JSON NULL COMMENT 'Array URL stránek před konverzí',
                        `time_to_conversion` INT UNSIGNED NULL COMMENT 'Sekundy od session_start',
                        `steps_to_conversion` TINYINT UNSIGNED NULL COMMENT 'Počet pageviews před konverzí',

                        -- Attribution (UTM parametry)
                        `utm_source` VARCHAR(100) NULL,
                        `utm_medium` VARCHAR(100) NULL,
                        `utm_campaign` VARCHAR(200) NULL,
                        `utm_content` VARCHAR(200) NULL,
                        `utm_term` VARCHAR(200) NULL,

                        -- Context
                        `page_url` VARCHAR(500) NULL COMMENT 'URL stránky konverze',
                        `device_type` ENUM('desktop', 'mobile', 'tablet') NULL,
                        `country` VARCHAR(2) NULL COMMENT 'ISO country code',

                        -- Metadata
                        `metadata` JSON NULL COMMENT 'Custom data (product_id, form_id, etc.)',

                        -- Timestamps
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                        PRIMARY KEY (`id`),
                        INDEX `idx_session` (`session_id`),
                        INDEX `idx_fingerprint` (`fingerprint_id`),
                        INDEX `idx_type` (`conversion_type`),
                        INDEX `idx_date` (`created_at`),
                        INDEX `idx_campaign` (`utm_campaign`(100)),
                        INDEX `idx_source_medium` (`utm_source`(50), `utm_medium`(50))
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                      COMMENT='Conversion tracking (Module #9)'
                ");

                // ====================
                // TABULKA 2: funnels
                // ====================
                $pdo->exec("
                    CREATE TABLE `wgs_analytics_funnels` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,

                        `funnel_name` VARCHAR(100) NOT NULL,
                        `funnel_description` TEXT NULL,
                        `is_active` TINYINT(1) DEFAULT 1,

                        -- Funnel steps (JSON array)
                        `funnel_steps` JSON NOT NULL COMMENT 'Array kroků [{step: 1, url_pattern: \"/product/*\", label: \"View Product\"}]',

                        -- Goal conversion type
                        `goal_conversion_type` VARCHAR(50) NULL COMMENT 'Cílový typ konverze',

                        -- Timestamps
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                        PRIMARY KEY (`id`),
                        UNIQUE KEY `idx_name` (`funnel_name`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                      COMMENT='Funnel definitions (Module #9)'
                ");

                // ====================
                // SEED DATA: Ukázkové funnely
                // ====================
                $pdo->exec("
                    INSERT INTO `wgs_analytics_funnels` (`funnel_name`, `funnel_description`, `funnel_steps`, `goal_conversion_type`) VALUES
                    (
                        'Contact Form Funnel',
                        'Sleduje cestu uživatele od homepage ke kontaktnímu formuláři',
                        '[{\"step\":1,\"url_pattern\":\"/\",\"label\":\"Homepage\"},{\"step\":2,\"url_pattern\":\"/o-nas*\",\"label\":\"O nás\"},{\"step\":3,\"url_pattern\":\"/kontakt\",\"label\":\"Kontakt\"},{\"step\":4,\"url_pattern\":\"/dekujeme\",\"label\":\"Poděkování\"}]',
                        'contact'
                    ),
                    (
                        'Purchase Funnel',
                        'E-commerce nákupní trychtýř',
                        '[{\"step\":1,\"url_pattern\":\"/\",\"label\":\"Homepage\"},{\"step\":2,\"url_pattern\":\"/produkty*\",\"label\":\"Produkty\"},{\"step\":3,\"url_pattern\":\"/kosik\",\"label\":\"Košík\"},{\"step\":4,\"url_pattern\":\"/objednavka\",\"label\":\"Objednávka\"},{\"step\":5,\"url_pattern\":\"/dekujeme-za-nakup\",\"label\":\"Nákup dokončen\"}]',
                        'purchase'
                    )
                ");

                $pdo->commit();

                echo "<div class='success'>";
                echo "<h2 style='color: white; border: none; margin: 0 0 10px 0; padding: 0;'>✅ MIGRACE ÚSPĚŠNĚ DOKONČENA</h2>";
                echo "<strong>Vytvořené tabulky:</strong><br>";
                echo "- wgs_analytics_conversions<br>";
                echo "- wgs_analytics_funnels<br>";
                echo "<strong>Seed data:</strong> 2 ukázkové funnely<br>";
                echo "<strong>Indexy:</strong> 6 indexů pro optimalizaci";
                echo "</div>";

                // Zobrazit strukturu vytvořených tabulek
                echo "<h2>📊 Struktura tabulky: wgs_analytics_conversions</h2>";
                $stmt = $pdo->query("DESCRIBE wgs_analytics_conversions");
                $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo "<table>";
                echo "<tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Klíč</th><th>Default</th></tr>";
                foreach ($sloupce as $sloupec) {
                    echo "<tr>";
                    echo "<td><strong>" . htmlspecialchars($sloupec['Field']) . "</strong></td>";
                    echo "<td><span class='badge badge-info'>" . htmlspecialchars($sloupec['Type']) . "</span></td>";
                    echo "<td>" . htmlspecialchars($sloupec['Null']) . "</td>";
                    echo "<td>" . ($sloupec['Key'] ? "<span class='badge badge-primary'>" . htmlspecialchars($sloupec['Key']) . "</span>" : '-') . "</td>";
                    echo "<td>" . htmlspecialchars($sloupec['Default'] ?? 'NULL') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";

                echo "<h2>📊 Struktura tabulky: wgs_analytics_funnels</h2>";
                $stmt = $pdo->query("DESCRIBE wgs_analytics_funnels");
                $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo "<table>";
                echo "<tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Klíč</th><th>Default</th></tr>";
                foreach ($sloupce as $sloupec) {
                    echo "<tr>";
                    echo "<td><strong>" . htmlspecialchars($sloupec['Field']) . "</strong></td>";
                    echo "<td><span class='badge badge-info'>" . htmlspecialchars($sloupec['Type']) . "</span></td>";
                    echo "<td>" . htmlspecialchars($sloupec['Null']) . "</td>";
                    echo "<td>" . ($sloupec['Key'] ? "<span class='badge badge-primary'>" . htmlspecialchars($sloupec['Key']) . "</span>" : '-') . "</td>";
                    echo "<td>" . htmlspecialchars($sloupec['Default'] ?? 'NULL') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";

                // Zobrazit seed data
                echo "<h2>🌱 Ukázková data: Funnely</h2>";
                $stmt = $pdo->query("SELECT * FROM wgs_analytics_funnels");
                $funnels = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo "<table>";
                echo "<tr><th>ID</th><th>Název</th><th>Popis</th><th>Aktivní</th><th>Kroky</th></tr>";
                foreach ($funnels as $funnel) {
                    $steps = json_decode($funnel['funnel_steps'], true);
                    echo "<tr>";
                    echo "<td>" . $funnel['id'] . "</td>";
                    echo "<td><strong>" . htmlspecialchars($funnel['funnel_name']) . "</strong></td>";
                    echo "<td>" . htmlspecialchars($funnel['funnel_description']) . "</td>";
                    echo "<td>" . ($funnel['is_active'] ? "<span class='badge badge-success'>ANO</span>" : "NE") . "</td>";
                    echo "<td>" . count($steps) . " kroků</td>";
                    echo "</tr>";
                }
                echo "</table>";

                echo "<div class='info'>";
                echo "<h2 style='color: white; border: none; margin: 0 0 10px 0; padding: 0;'>📝 Další kroky</h2>";
                echo "<ol style='margin: 10px 0 0 20px; line-height: 1.8;'>";
                echo "<li>Implementovat backend třídu <code>includes/ConversionFunnel.php</code></li>";
                echo "<li>Vytvořit API endpointy <code>api/track_conversion.php</code> a <code>api/analytics_conversions.php</code></li>";
                echo "<li>Vytvořit admin UI <code>analytics-conversions.php</code></li>";
                echo "<li>Upravit <code>tracker-v2.js</code> pro conversion tracking</li>";
                echo "<li>Testovat conversion tracking a funnel analýzu</li>";
                echo "</ol>";
                echo "</div>";

                echo "<a href='analytics-conversions.php' class='btn btn-success'>📊 Otevřít Conversions Dashboard</a>";
                echo "<a href='admin.php' class='btn btn-secondary'>← Zpět na Admin</a>";

            } catch (PDOException $e) {
                $pdo->rollBack();

                echo "<div class='error'>";
                echo "<h2 style='color: white; border: none; margin: 0 0 10px 0; padding: 0;'>❌ CHYBA PŘI MIGRACI</h2>";
                echo "<strong>Chybová zpráva:</strong><br>";
                echo "<pre style='background: rgba(255,255,255,0.2); border: none; color: white;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
                echo "</div>";

                echo "<a href='?execute=0' class='btn'>🔄 Zkusit znovu</a>";
                echo "<a href='admin.php' class='btn btn-secondary'>← Zpět na Admin</a>";
            }
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2 style='color: white; border: none; margin: 0 0 10px 0; padding: 0;'>❌ NEOČEKÁVANÁ CHYBA</h2>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";

    echo "<a href='admin.php' class='btn btn-secondary'>← Zpět na Admin</a>";
}

echo "</div>
</body>
</html>";
?>
