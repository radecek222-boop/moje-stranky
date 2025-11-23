<?php
/**
 * Migrace: Module #10 - User Interest AI Scoring
 *
 * Tento skript BEZPEČNĚ vytvoří tabulku wgs_analytics_user_scores pro ukládání
 * AI-vypočítaných skóre pro každou session.
 *
 * Můžete jej spustit vícekrát - nekompromituje duplicitní operace.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #10 - User Interest AI Scoring
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
    <title>Migrace: Module #10 - User Interest AI Scoring</title>
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
            color: #4a7c2c;
            margin-top: 30px;
        }
        .info-box {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
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
        .btn {
            display: inline-block;
            padding: 12px 24px;
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
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        table th {
            background: #333333;
            color: white;
        }
        table tr:nth-child(even) {
            background: #f9f9f9;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🚀 Module #10: User Interest AI Scoring</h1>";

echo "<div class='info-box'>
    <h3>📋 Informace o migraci</h3>
    <table>
        <tr><th>Modul:</th><td>User Interest AI Scoring</td></tr>
        <tr><th>Verze:</th><td>1.0.0</td></tr>
        <tr><th>Datum:</th><td>2025-11-23</td></tr>
        <tr><th>Databáze:</th><td>" . DB_NAME . "</td></tr>
        <tr><th>Popis:</th><td>Vytvoří tabulku pro ukládání AI-vypočítaných engagement, frustration a interest skóre pro každou session.</td></tr>
    </table>
</div>";

try {
    $pdo = getDbConnection();

    // ========================================
    // KONTROLA EXISTENCE TABULKY
    // ========================================
    echo "<h2>🔍 Kontrola existence tabulky</h2>";

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_user_scores'");
    $tableExists = $stmt->rowCount() > 0;

    if ($tableExists) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ VAROVÁNÍ:</strong> Tabulka <code>wgs_analytics_user_scores</code> již existuje!<br>";
        echo "Migrace může být již spuštěna. Pokud chcete migraci spustit znovu, nejprve odstraňte tabulku.";
        echo "</div>";

        // Zobrazit aktuální strukturu
        echo "<h3>Aktuální struktura tabulky:</h3>";
        $stmt = $pdo->query("DESCRIBE wgs_analytics_user_scores");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<table>";
        echo "<tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Klíč</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td><code>{$col['Field']}</code></td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>" . ($col['Default'] ?? '<em>NULL</em>') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Zobrazit count záznamů
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM wgs_analytics_user_scores");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        echo "<p><strong>Počet záznamů:</strong> " . number_format($count, 0, ',', ' ') . "</p>";

        echo "<p><a href='admin.php' class='btn btn-secondary'>← Zpět na Admin</a></p>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='success'>";
    echo "✅ Tabulka <code>wgs_analytics_user_scores</code> neexistuje. Můžeme pokračovat.";
    echo "</div>";

    // ========================================
    // NÁHLED MIGRACE (bez ?execute=1)
    // ========================================
    if (!isset($_GET['execute']) || $_GET['execute'] !== '1') {
        echo "<h2>📄 Náhled migrace</h2>";

        echo "<div class='info-box'>";
        echo "<h3>Co bude vytvořeno:</h3>";
        echo "<ul>";
        echo "<li><strong>Tabulka:</strong> <code>wgs_analytics_user_scores</code> (24 sloupců)</li>";
        echo "<li><strong>Indexy:</strong> 6 indexů pro optimalizaci dotazů</li>";
        echo "<li><strong>Sloupce:</strong> engagement_score, frustration_score, interest_score + metriky</li>";
        echo "<li><strong>JSON sloupce:</strong> engagement_factors, frustration_factors, interest_factors</li>";
        echo "</ul>";
        echo "</div>";

        echo "<h3>Struktura tabulky:</h3>";
        echo "<table>";
        echo "<tr><th>Sloupec</th><th>Typ</th><th>Popis</th></tr>";
        echo "<tr><td><code>id</code></td><td>BIGINT UNSIGNED</td><td>Primární klíč (auto-increment)</td></tr>";
        echo "<tr><td><code>session_id</code></td><td>VARCHAR(50)</td><td>ID relace (UNIQUE)</td></tr>";
        echo "<tr><td><code>fingerprint_id</code></td><td>VARCHAR(64)</td><td>Device fingerprint ID</td></tr>";
        echo "<tr><td><code>engagement_score</code></td><td>DECIMAL(5,2)</td><td>Engagement skóre (0-100)</td></tr>";
        echo "<tr><td><code>engagement_factors</code></td><td>JSON</td><td>Faktory výpočtu engagement</td></tr>";
        echo "<tr><td><code>frustration_score</code></td><td>DECIMAL(5,2)</td><td>Frustration skóre (0-100)</td></tr>";
        echo "<tr><td><code>frustration_factors</code></td><td>JSON</td><td>Faktory výpočtu frustration</td></tr>";
        echo "<tr><td><code>interest_score</code></td><td>DECIMAL(5,2)</td><td>Interest skóre (0-100)</td></tr>";
        echo "<tr><td><code>interest_factors</code></td><td>JSON</td><td>Faktory výpočtu interest</td></tr>";
        echo "<tr><td><code>total_clicks</code></td><td>INT</td><td>Celkový počet kliknutí</td></tr>";
        echo "<tr><td><code>total_scroll_events</code></td><td>INT</td><td>Celkový počet scroll eventů</td></tr>";
        echo "<tr><td><code>total_duration</code></td><td>INT</td><td>Celková doba trvání (sekundy)</td></tr>";
        echo "<tr><td><code>total_pageviews</code></td><td>INT</td><td>Celkový počet pageviews</td></tr>";
        echo "<tr><td><code>click_quality</code></td><td>DECIMAL(5,2)</td><td>Kvalita kliknutí (0-100)</td></tr>";
        echo "<tr><td><code>scroll_quality</code></td><td>DECIMAL(5,2)</td><td>Kvalita scrollování (0-100)</td></tr>";
        echo "<tr><td><code>reading_time</code></td><td>INT</td><td>Čas čtení (sekundy)</td></tr>";
        echo "<tr><td><code>created_at</code></td><td>TIMESTAMP</td><td>Datum vytvoření</td></tr>";
        echo "<tr><td><code>updated_at</code></td><td>TIMESTAMP</td><td>Datum poslední aktualizace</td></tr>";
        echo "</table>";

        echo "<h3>Indexy:</h3>";
        echo "<ul>";
        echo "<li><code>UNIQUE KEY unique_session (session_id)</code></li>";
        echo "<li><code>INDEX idx_fingerprint (fingerprint_id)</code></li>";
        echo "<li><code>INDEX idx_engagement (engagement_score)</code></li>";
        echo "<li><code>INDEX idx_frustration (frustration_score)</code></li>";
        echo "<li><code>INDEX idx_interest (interest_score)</code></li>";
        echo "<li><code>INDEX idx_created (created_at)</code></li>";
        echo "</ul>";

        echo "<div class='warning'>";
        echo "<strong>⚠️ DŮLEŽITÉ:</strong> Po vytvoření tabulky budete muset spustit přepočítání skóre pro existující sessions pomocí skriptu <code>recalculate_user_scores.php</code>.";
        echo "</div>";

        echo "<h3>🚀 Spustit migraci</h3>";
        echo "<p>Kliknutím na tlačítko níže spustíte migraci databáze:</p>";
        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='admin.php' class='btn btn-secondary'>Zrušit</a>";

        echo "</div></body></html>";
        exit;
    }

    // ========================================
    // SPUŠTĚNÍ MIGRACE
    // ========================================
    echo "<h2>⚙️ Spouštím migraci...</h2>";

    $pdo->beginTransaction();

    try {
        // ========================================
        // 1. VYTVOŘENÍ TABULKY wgs_analytics_user_scores
        // ========================================
        echo "<h3>1️⃣ Vytváření tabulky <code>wgs_analytics_user_scores</code>...</h3>";

        $sql = "
        CREATE TABLE wgs_analytics_user_scores (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(50) NOT NULL,
            fingerprint_id VARCHAR(64) NOT NULL,

            -- Engagement score (0-100)
            engagement_score DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Míra aktivity uživatele (0-100)',
            engagement_factors JSON DEFAULT NULL COMMENT 'Faktory výpočtu engagement',

            -- Frustration score (0-100)
            frustration_score DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Míra frustrace uživatele (0-100)',
            frustration_factors JSON DEFAULT NULL COMMENT 'Faktory výpočtu frustration',

            -- Interest score (0-100)
            interest_score DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Míra zájmu o obsah (0-100)',
            interest_factors JSON DEFAULT NULL COMMENT 'Faktory výpočtu interest',

            -- Agregované metriky
            total_clicks INT DEFAULT 0 COMMENT 'Celkový počet kliknutí',
            total_scroll_events INT DEFAULT 0 COMMENT 'Celkový počet scroll eventů',
            total_duration INT DEFAULT 0 COMMENT 'Celková doba trvání session (sekundy)',
            total_pageviews INT DEFAULT 0 COMMENT 'Celkový počet pageviews',

            -- Quality metrics
            click_quality DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Kvalita kliknutí (0-100)',
            scroll_quality DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Kvalita scrollování (0-100)',
            reading_time INT DEFAULT 0 COMMENT 'Odhadovaný čas čtení (sekundy)',

            -- Timestamps
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            -- Indexy
            UNIQUE KEY unique_session (session_id),
            INDEX idx_fingerprint (fingerprint_id),
            INDEX idx_engagement (engagement_score),
            INDEX idx_frustration (frustration_score),
            INDEX idx_interest (interest_score),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='AI-vypočítaná skóre pro engagement, frustration a interest';
        ";

        $pdo->exec($sql);

        echo "<div class='success'>";
        echo "✅ Tabulka <code>wgs_analytics_user_scores</code> úspěšně vytvořena.";
        echo "</div>";

        // ========================================
        // COMMIT TRANSAKCE
        // ========================================
        $pdo->commit();

        echo "<div class='success'>";
        echo "<h2>✅ MIGRACE ÚSPĚŠNĚ DOKONČENA</h2>";
        echo "<p>Všechny databázové struktury byly úspěšně vytvořeny.</p>";
        echo "</div>";

        echo "<h3>📊 Další kroky:</h3>";
        echo "<ol>";
        echo "<li>Spustit přepočítání skóre pro existující sessions: <code>recalculate_user_scores.php</code></li>";
        echo "<li>Otevřít admin UI pro zobrazení skóre: <code>analytics-user-scores.php</code></li>";
        echo "<li>Zkontrolovat tabulku v databázi: <code>wgs_analytics_user_scores</code></li>";
        echo "</ol>";

        echo "<h3>🧪 Testování:</h3>";
        echo "<pre><code>";
        echo "-- Zkontrolovat strukturu tabulky:\n";
        echo "DESCRIBE wgs_analytics_user_scores;\n\n";
        echo "-- Zobrazit všechny scores:\n";
        echo "SELECT session_id, engagement_score, frustration_score, interest_score \n";
        echo "FROM wgs_analytics_user_scores \n";
        echo "ORDER BY created_at DESC LIMIT 10;\n";
        echo "</code></pre>";

        echo "<p>";
        echo "<a href='admin.php' class='btn'>← Zpět na Admin</a>";
        echo "<a href='analytics-user-scores.php' class='btn'>Zobrazit User Scores</a>";
        echo "</p>";

    } catch (PDOException $e) {
        $pdo->rollBack();

        echo "<div class='error'>";
        echo "<h3>❌ CHYBA PŘI MIGRACI</h3>";
        echo "<p><strong>Chybová zpráva:</strong></p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "<p><strong>SQL State:</strong> " . $e->getCode() . "</p>";
        echo "</div>";

        echo "<p><a href='admin.php' class='btn btn-secondary'>← Zpět na Admin</a></p>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ NEOČEKÁVANÁ CHYBA</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";

    echo "<p><a href='admin.php' class='btn btn-secondary'>← Zpět na Admin</a></p>";
}

echo "</div></body></html>";
?>
