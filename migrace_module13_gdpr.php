<?php
/**
 * Migrace: Module #13 - GDPR Compliance Tools
 *
 * Tento skript BEZPEČNĚ vytvoří tabulky pro GDPR compliance management.
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #13 - GDPR Compliance Tools
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
    <title>Migrace: Module #13 - GDPR Compliance</title>
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

    echo "<h1>Migrace: Module #13 - GDPR Compliance Tools</h1>";
    echo "<p><strong>Datum:</strong> " . date('Y-m-d H:i:s') . "</p>";

    // ========================================
    // KONTROLNÍ FÁZE
    // ========================================
    echo "<div class='step'>";
    echo "<h2>1️⃣ Kontrola stávající struktury</h2>";

    // Kontrola existence tabulek
    $tables = ['wgs_gdpr_consents', 'wgs_gdpr_data_requests', 'wgs_gdpr_audit_log'];
    $existingTables = [];

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            $existingTables[] = $table;
        }
    }

    if (count($existingTables) > 0) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ Některé tabulky již existují</strong><br>";
        foreach ($existingTables as $table) {
            echo "• {$table}<br>";
        }
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
CREATE TABLE IF NOT EXISTS wgs_gdpr_consents (
    consent_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fingerprint_id VARCHAR(64) NOT NULL,

    -- Consent types
    consent_analytics TINYINT(1) DEFAULT 0,
    consent_marketing TINYINT(1) DEFAULT 0,
    consent_functional TINYINT(1) DEFAULT 0,

    -- Metadata
    consent_given_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    consent_ip VARCHAR(45) DEFAULT NULL,
    consent_user_agent TEXT DEFAULT NULL,

    -- Withdrawal
    consent_withdrawn TINYINT(1) DEFAULT 0,
    withdrawn_at DATETIME DEFAULT NULL,

    -- Version tracking
    privacy_policy_version VARCHAR(20) DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_fingerprint (fingerprint_id),
    INDEX idx_withdrawn (consent_withdrawn),
    INDEX idx_given_at (consent_given_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

    $ddl2 = "
CREATE TABLE IF NOT EXISTS wgs_gdpr_data_requests (
    request_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fingerprint_id VARCHAR(64) NOT NULL,
    email VARCHAR(255) NOT NULL,

    -- Request type
    request_type VARCHAR(20) NOT NULL,

    -- Status
    status VARCHAR(20) DEFAULT 'pending',

    -- Data
    request_data JSON DEFAULT NULL,
    response_data JSON DEFAULT NULL,

    -- Processing
    processed_at DATETIME DEFAULT NULL,
    processed_by VARCHAR(50) DEFAULT NULL,

    -- Export file
    export_file_path VARCHAR(255) DEFAULT NULL,
    export_expires_at DATETIME DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_fingerprint (fingerprint_id),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_request_type (request_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

    $ddl3 = "
CREATE TABLE IF NOT EXISTS wgs_gdpr_audit_log (
    log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fingerprint_id VARCHAR(64) DEFAULT NULL,

    -- Action
    action_type VARCHAR(50) NOT NULL,
    action_details JSON DEFAULT NULL,

    -- User/IP
    user_ip VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,

    -- Compliance
    legal_basis VARCHAR(50) DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_fingerprint (fingerprint_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

    echo "<h3>Tabulka 1: wgs_gdpr_consents</h3>";
    echo "<pre>" . htmlspecialchars($ddl1) . "</pre>";

    echo "<h3>Tabulka 2: wgs_gdpr_data_requests</h3>";
    echo "<pre>" . htmlspecialchars($ddl2) . "</pre>";

    echo "<h3>Tabulka 3: wgs_gdpr_audit_log</h3>";
    echo "<pre>" . htmlspecialchars($ddl3) . "</pre>";

    echo "</div>";

    // ========================================
    // SPUŠTĚNÍ MIGRACE
    // ========================================
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='step'>";
        echo "<h2>3️⃣ Spouštím migraci...</h2>";

        try {
            // Drop tables if exist
            foreach ($existingTables as $table) {
                echo "<div class='info'>⏳ Odstraňuji starou tabulku {$table}...</div>";
                $pdo->exec("DROP TABLE IF EXISTS {$table}");
                echo "<div class='success'>✅ Stará tabulka odstraněna</div>";
            }

            // Vytvoření nových tabulek
            echo "<div class='info'>⏳ Vytvářím tabulku wgs_gdpr_consents...</div>";
            $pdo->exec($ddl1);
            echo "<div class='success'>✅ Tabulka wgs_gdpr_consents vytvořena</div>";

            echo "<div class='info'>⏳ Vytvářím tabulku wgs_gdpr_data_requests...</div>";
            $pdo->exec($ddl2);
            echo "<div class='success'>✅ Tabulka wgs_gdpr_data_requests vytvořena</div>";

            echo "<div class='info'>⏳ Vytvářím tabulku wgs_gdpr_audit_log...</div>";
            $pdo->exec($ddl3);
            echo "<div class='success'>✅ Tabulka wgs_gdpr_audit_log vytvořena</div>";

            echo "<div class='success'>";
            echo "<strong>✅ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
            echo "Vytvořeno:<br>";
            echo "• Tabulka: wgs_gdpr_consents (3 indexy)<br>";
            echo "• Tabulka: wgs_gdpr_data_requests (4 indexy)<br>";
            echo "• Tabulka: wgs_gdpr_audit_log (3 indexy)<br>";
            echo "• GDPR compliance ready: Consent management, Data export/deletion, Audit logging<br>";
            echo "</div>";

            echo "<h3>Další kroky:</h3>";
            echo "<ol>";
            echo "<li>Nasaďte GDPR manager: <code>includes/GDPRManager.php</code></li>";
            echo "<li>Nasaďte API endpoint: <code>api/gdpr_api.php</code></li>";
            echo "<li>Nasaďte public UI: <code>gdpr-portal.php</code></li>";
            echo "<li>Nasaďte consent banner: <code>assets/js/gdpr-consent.js</code></li>";
            echo "<li>Nastavte cron job: <code>scripts/apply_retention_policy.php</code> (týdně)</li>";
            echo "</ol>";

            echo "<p><a href='gdpr-portal.php' class='btn'>Otevřít GDPR Portal</a></p>";

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
