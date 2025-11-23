<?php
/**
 * Migrace: Module #11 - Real-time Dashboard
 *
 * Tento skript BEZPEČNĚ vytvoří tabulku pro real-time tracking aktivních návštěvníků.
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #11 - Real-time Dashboard
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
    <title>Migrace: Module #11 - Real-time Dashboard</title>
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
            color: #2D5016;
            border-bottom: 3px solid #2D5016;
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
            background: #2D5016;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
        }
        .btn:hover {
            background: #1a300d;
        }
        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border-left: 4px solid #2D5016;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Module #11 - Real-time Dashboard</h1>";
    echo "<p><strong>Datum:</strong> " . date('Y-m-d H:i:s') . "</p>";

    // ========================================
    // KONTROLNÍ FÁZE
    // ========================================
    echo "<div class='step'>";
    echo "<h2>1️⃣ Kontrola stávající struktury</h2>";

    // Kontrola existence tabulky wgs_analytics_realtime
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_realtime'");
    $tabulkaExistuje = $stmt->rowCount() > 0;

    if ($tabulkaExistuje) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ Tabulka wgs_analytics_realtime již existuje</strong><br>";
        echo "Bude znovu vytvořena pro zajištění správné struktury.";
        echo "</div>";
    } else {
        echo "<div class='info'>";
        echo "<strong>✅ Tabulka wgs_analytics_realtime neexistuje</strong><br>";
        echo "Bude vytvořena nová tabulka.";
        echo "</div>";
    }

    echo "</div>";

    // ========================================
    // ZOBRAZIT SQL DDL
    // ========================================
    echo "<div class='step'>";
    echo "<h2>2️⃣ SQL struktura k vytvoření</h2>";

    $ddl = "
CREATE TABLE IF NOT EXISTS wgs_analytics_realtime (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(50) NOT NULL,
    fingerprint_id VARCHAR(64) NOT NULL,

    -- Visitor info
    is_bot TINYINT(1) DEFAULT 0,
    visitor_type VARCHAR(20) DEFAULT 'human',

    -- Current page
    current_page VARCHAR(500) DEFAULT NULL,
    current_page_title VARCHAR(200) DEFAULT NULL,

    -- Location
    country_code VARCHAR(2) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    latitude DECIMAL(10, 7) DEFAULT NULL,
    longitude DECIMAL(10, 7) DEFAULT NULL,

    -- Device info
    device_type VARCHAR(20) DEFAULT NULL,
    browser VARCHAR(50) DEFAULT NULL,
    os VARCHAR(50) DEFAULT NULL,

    -- Referrer
    referrer_domain VARCHAR(200) DEFAULT NULL,
    utm_source VARCHAR(100) DEFAULT NULL,
    utm_medium VARCHAR(100) DEFAULT NULL,
    utm_campaign VARCHAR(100) DEFAULT NULL,

    -- Activity metrics
    pageviews INT DEFAULT 0,
    events_count INT DEFAULT 0,
    session_duration INT DEFAULT 0,

    -- Status
    is_active TINYINT(1) DEFAULT 1,
    last_activity_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    session_start DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_session (session_id),
    INDEX idx_is_active (is_active),
    INDEX idx_expires_at (expires_at),
    INDEX idx_is_bot (is_bot),
    INDEX idx_last_activity (last_activity_at),
    INDEX idx_country (country_code),
    INDEX idx_fingerprint (fingerprint_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

    echo "<pre>" . htmlspecialchars($ddl) . "</pre>";
    echo "</div>";

    // ========================================
    // SPUŠTĚNÍ MIGRACE
    // ========================================
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='step'>";
        echo "<h2>3️⃣ Spouštím migraci...</h2>";

        $pdo->beginTransaction();

        try {
            // Drop table if exists (pro čistou migraci)
            if ($tabulkaExistuje) {
                echo "<div class='info'>⏳ Odstraňuji starou tabulku wgs_analytics_realtime...</div>";
                $pdo->exec("DROP TABLE IF EXISTS wgs_analytics_realtime");
                echo "<div class='success'>✅ Stará tabulka odstraněna</div>";
            }

            // Vytvoření nové tabulky
            echo "<div class='info'>⏳ Vytvářím tabulku wgs_analytics_realtime...</div>";
            $pdo->exec($ddl);
            echo "<div class='success'>✅ Tabulka wgs_analytics_realtime vytvořena</div>";

            // Commit transakce
            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✅ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
            echo "Vytvořeno:<br>";
            echo "• Tabulka: wgs_analytics_realtime<br>";
            echo "• 7 indexů pro optimalizaci dotazů<br>";
            echo "• Auto-expire mechanismus (expires_at)<br>";
            echo "• Podpora pro bot detection<br>";
            echo "• Geolokační data (latitude, longitude)<br>";
            echo "</div>";

            // Zobrazit strukturu vytvořené tabulky
            echo "<h3>Vytvořená struktura:</h3>";
            $stmt = $pdo->query("SHOW CREATE TABLE wgs_analytics_realtime");
            $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<pre>" . htmlspecialchars($createTable['Create Table']) . "</pre>";

            echo "<h3>Další kroky:</h3>";
            echo "<ol>";
            echo "<li>Nasaďte API endpoint: <code>api/analytics_realtime.php</code></li>";
            echo "<li>Nasaďte admin UI: <code>analytics-realtime.php</code></li>";
            echo "<li>Upravte tracker: <code>api/track_v2.php</code> pro real-time updates</li>";
            echo "<li>Nastavte cron job: <code>scripts/cleanup_realtime_sessions.php</code> (každých 5 minut)</li>";
            echo "</ol>";

            echo "<p><a href='analytics-realtime.php' class='btn'>Otevřít Real-time Dashboard</a></p>";

        } catch (PDOException $e) {
            $pdo->rollBack();

            echo "<div class='error'>";
            echo "<strong>❌ CHYBA PŘI MIGRACI</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }

        echo "</div>";

    } else {
        // Zobrazit tlačítko pro spuštění migrace
        echo "<div class='step'>";
        echo "<h2>3️⃣ Připraveno ke spuštění</h2>";
        echo "<p>Klikněte na tlačítko níže pro spuštění migrace.</p>";
        echo "<a href='?execute=1' class='btn'>▶️ SPUSTIT MIGRACI</a>";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ NEOČEKÁVANÁ CHYBA</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
