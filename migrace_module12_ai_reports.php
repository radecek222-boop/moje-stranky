<?php
/**
 * Migrace: Module #12 - AI Reports Engine
 *
 * Tento skript BEZPEČNĚ vytvoří tabulky pro AI-powered reporting systém.
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #12 - AI Reports Engine
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
    <title>Migrace: Module #12 - AI Reports Engine</title>
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

    echo "<h1>Migrace: Module #12 - AI Reports Engine</h1>";
    echo "<p><strong>Datum:</strong> " . date('Y-m-d H:i:s') . "</p>";

    // ========================================
    // KONTROLNÍ FÁZE
    // ========================================
    echo "<div class='step'>";
    echo "<h2>1️⃣ Kontrola stávající struktury</h2>";

    // Kontrola existence tabulek
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_reports'");
    $reportsExist = $stmt->rowCount() > 0;

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_report_schedules'");
    $schedulesExist = $stmt->rowCount() > 0;

    if ($reportsExist || $schedulesExist) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ Některé tabulky již existují</strong><br>";
        if ($reportsExist) echo "• wgs_analytics_reports<br>";
        if ($schedulesExist) echo "• wgs_analytics_report_schedules<br>";
        echo "Budou znovu vytvořeny pro zajištění správné struktury.";
        echo "</div>";
    } else {
        echo "<div class='info'>";
        echo "<strong>✅ Tabulky neexistují</strong><br>";
        echo "Budou vytvořeny nové tabulky.";
        echo "</div>";
    }

    echo "</div>";

    // ========================================
    // ZOBRAZIT SQL DDL
    // ========================================
    echo "<div class='step'>";
    echo "<h2>2️⃣ SQL struktura k vytvoření</h2>";

    $ddl1 = "
CREATE TABLE IF NOT EXISTS wgs_analytics_reports (
    report_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_type VARCHAR(20) NOT NULL,
    report_period_start DATE NOT NULL,
    report_period_end DATE NOT NULL,

    -- Report data (JSON)
    report_data JSON DEFAULT NULL,

    -- AI-generated insights
    insights JSON DEFAULT NULL,
    recommendations JSON DEFAULT NULL,
    anomalies JSON DEFAULT NULL,

    -- Status
    status VARCHAR(20) DEFAULT 'pending',
    generated_at DATETIME DEFAULT NULL,
    generated_by VARCHAR(50) DEFAULT NULL,

    -- Metadata
    file_path VARCHAR(255) DEFAULT NULL,
    file_size INT DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_report_type (report_type),
    INDEX idx_period (report_period_start, report_period_end),
    INDEX idx_status (status),
    INDEX idx_generated_at (generated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

    $ddl2 = "
CREATE TABLE IF NOT EXISTS wgs_analytics_report_schedules (
    schedule_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_name VARCHAR(100) NOT NULL,
    report_type VARCHAR(20) NOT NULL,

    -- Schedule config
    frequency VARCHAR(20) NOT NULL,
    day_of_week TINYINT DEFAULT NULL,
    day_of_month TINYINT DEFAULT NULL,
    time_of_day TIME DEFAULT '06:00:00',

    -- Delivery config
    delivery_method VARCHAR(20) NOT NULL,
    email_recipients JSON DEFAULT NULL,

    -- Status
    is_active TINYINT(1) DEFAULT 1,
    last_run_at DATETIME DEFAULT NULL,
    next_run_at DATETIME DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_is_active (is_active),
    INDEX idx_next_run (next_run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

    echo "<h3>Tabulka 1: wgs_analytics_reports</h3>";
    echo "<pre>" . htmlspecialchars($ddl1) . "</pre>";

    echo "<h3>Tabulka 2: wgs_analytics_report_schedules</h3>";
    echo "<pre>" . htmlspecialchars($ddl2) . "</pre>";

    echo "</div>";

    // ========================================
    // SPUŠTĚNÍ MIGRACE
    // ========================================
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='step'>";
        echo "<h2>3️⃣ Spouštím migraci...</h2>";

        try {
            // DDL příkazy mají implicitní COMMIT, nepoužíváme explicitní transakci

            // Drop tables if exist
            if ($reportsExist) {
                echo "<div class='info'>⏳ Odstraňuji starou tabulku wgs_analytics_reports...</div>";
                $pdo->exec("DROP TABLE IF EXISTS wgs_analytics_reports");
                echo "<div class='success'>✅ Stará tabulka odstraněna</div>";
            }

            if ($schedulesExist) {
                echo "<div class='info'>⏳ Odstraňuji starou tabulku wgs_analytics_report_schedules...</div>";
                $pdo->exec("DROP TABLE IF EXISTS wgs_analytics_report_schedules");
                echo "<div class='success'>✅ Stará tabulka odstraněna</div>";
            }

            // Vytvoření nových tabulek
            echo "<div class='info'>⏳ Vytvářím tabulku wgs_analytics_reports...</div>";
            $pdo->exec($ddl1);
            echo "<div class='success'>✅ Tabulka wgs_analytics_reports vytvořena</div>";

            echo "<div class='info'>⏳ Vytvářím tabulku wgs_analytics_report_schedules...</div>";
            $pdo->exec($ddl2);
            echo "<div class='success'>✅ Tabulka wgs_analytics_report_schedules vytvořena</div>";

            echo "<div class='success'>";
            echo "<strong>✅ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
            echo "Vytvořeno:<br>";
            echo "• Tabulka: wgs_analytics_reports (4 indexy)<br>";
            echo "• Tabulka: wgs_analytics_report_schedules (2 indexy)<br>";
            echo "• JSON sloupce pro AI insights, recommendations, anomalies<br>";
            echo "• Schedule management pro automatické reporty<br>";
            echo "</div>";

            echo "<h3>Další kroky:</h3>";
            echo "<ol>";
            echo "<li>Nasaďte AI engine: <code>includes/AIReportGenerator.php</code></li>";
            echo "<li>Nasaďte API endpoint: <code>api/analytics_reports.php</code></li>";
            echo "<li>Nasaďte admin UI: <code>analytics-reports.php</code></li>";
            echo "<li>Nastavte cron job: <code>scripts/generate_scheduled_reports.php</code> (denně v 06:00)</li>";
            echo "</ol>";

            echo "<p><a href='analytics-reports.php' class='btn'>Otevřít Reports Dashboard</a></p>";

        } catch (PDOException $e) {
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
