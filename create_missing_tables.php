<?php
/**
 * Manual SQL Executor - Create Missing Tables
 * Vytvoření chybějících 4 tabulek Admin Control Center
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized - admin login required');
}

echo "<h1>🔧 Manuální vytvoření chybějících tabulek</h1>";
echo "<style>
body { font-family: monospace; padding: 20px; line-height: 1.6; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.info { color: blue; }
pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
</style>";

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<h2>Krok 1: Kontrola stavu tabulek</h2>";
    $tables = ['wgs_theme_settings', 'wgs_content_texts', 'wgs_system_config', 'wgs_github_webhooks'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        if ($exists) {
            echo "<p class='info'>ℹ️ $table již existuje</p>";
        } else {
            echo "<p class='error'>❌ $table neexistuje (vytvoříme)</p>";
        }
    }

    echo "<hr><h2>Krok 2: Vytvoření tabulek</h2>";

    // 1. wgs_theme_settings
    echo "<h3>1. wgs_theme_settings</h3>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wgs_theme_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            setting_type ENUM('color', 'font', 'size', 'file', 'text') NOT NULL,
            setting_group VARCHAR(50) DEFAULT 'general',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by INT DEFAULT NULL,
            INDEX idx_group (setting_group),
            INDEX idx_type (setting_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p class='success'>✅ wgs_theme_settings vytvořena</p>";

    // Insert default theme settings
    $pdo->exec("
        INSERT INTO wgs_theme_settings (setting_key, setting_value, setting_type, setting_group) VALUES
        ('primary_color', '#000000', 'color', 'colors'),
        ('secondary_color', '#FFFFFF', 'color', 'colors'),
        ('success_color', '#28A745', 'color', 'colors'),
        ('font_family', 'Poppins', 'font', 'typography')
        ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)
    ");
    echo "<p class='success'>✅ Výchozí theme settings vloženy</p>";

    // 2. wgs_content_texts
    echo "<h3>2. wgs_content_texts</h3>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wgs_content_texts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            page VARCHAR(50) NOT NULL,
            section VARCHAR(50) NOT NULL,
            text_key VARCHAR(100) NOT NULL,
            value_cz TEXT,
            value_en TEXT,
            value_sk TEXT,
            editable BOOLEAN DEFAULT TRUE,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by INT DEFAULT NULL,
            UNIQUE KEY unique_text (page, section, text_key),
            INDEX idx_page (page)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p class='success'>✅ wgs_content_texts vytvořena</p>";

    // 3. wgs_system_config ⭐ (NEJDŮLEŽITĚJŠÍ)
    echo "<h3>3. wgs_system_config ⭐</h3>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wgs_system_config (
            id INT PRIMARY KEY AUTO_INCREMENT,
            config_key VARCHAR(100) UNIQUE NOT NULL,
            config_value TEXT,
            config_group VARCHAR(50) DEFAULT 'general',
            is_sensitive BOOLEAN DEFAULT FALSE,
            requires_restart BOOLEAN DEFAULT FALSE,
            is_editable BOOLEAN DEFAULT TRUE,
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by INT DEFAULT NULL,
            INDEX idx_group (config_group)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p class='success'>✅ wgs_system_config vytvořena</p>";

    // Insert SMTP config
    $pdo->exec("
        INSERT INTO wgs_system_config (config_key, config_value, config_group, is_sensitive, description) VALUES
        ('smtp_host', '', 'email', TRUE, 'SMTP server hostname'),
        ('smtp_port', '587', 'email', FALSE, 'SMTP port'),
        ('smtp_username', '', 'email', TRUE, 'SMTP username'),
        ('smtp_password', '', 'email', TRUE, 'SMTP password'),
        ('smtp_from', 'reklamace@wgs-service.cz', 'email', FALSE, 'FROM email'),
        ('smtp_from_name', 'White Glove Service', 'email', FALSE, 'FROM name'),
        ('smtp_encryption', 'tls', 'email', FALSE, 'Encryption method')
        ON DUPLICATE KEY UPDATE config_value=VALUES(config_value)
    ");
    echo "<p class='success'>✅ SMTP konfigurace vložena</p>";

    // 4. wgs_github_webhooks
    echo "<h3>4. wgs_github_webhooks</h3>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wgs_github_webhooks (
            id INT PRIMARY KEY AUTO_INCREMENT,
            event_type VARCHAR(50) NOT NULL,
            repository VARCHAR(255) NOT NULL,
            branch VARCHAR(100),
            commit_sha VARCHAR(40),
            commit_message TEXT,
            author VARCHAR(255),
            payload JSON,
            received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed BOOLEAN DEFAULT FALSE,
            INDEX idx_event_type (event_type),
            INDEX idx_repository (repository),
            INDEX idx_processed (processed)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p class='success'>✅ wgs_github_webhooks vytvořena</p>";

    echo "<hr>";
    echo "<h2 class='success'>🎉 Všechny tabulky vytvořeny!</h2>";

    // Ověření
    echo "<h3>Finální kontrola:</h3>";
    $allTables = ['wgs_theme_settings', 'wgs_content_texts', 'wgs_system_config', 'wgs_pending_actions', 'wgs_action_history', 'wgs_github_webhooks'];
    $created = 0;
    foreach ($allTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $countStmt = $pdo->query("SELECT COUNT(*) as cnt FROM $table");
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
            echo "<p class='success'>✅ $table: $count záznamů</p>";
            $created++;
        } else {
            echo "<p class='error'>❌ $table chybí!</p>";
        }
    }

    echo "<hr>";
    echo "<p class='success'><strong>📊 Celkem: $created/6 tabulek vytvořeno</strong></p>";

    if ($created === 6) {
        echo "<p class='success'>✅ Admin Control Center je plně nainstalován!</p>";
        echo "<p><a href='/admin.php?tab=control_center' style='display: inline-block; background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>✅ Přejít do Control Center</a></p>";
    } else {
        echo "<p class='error'>⚠️ Některé tabulky stále chybí. Kontaktujte podporu.</p>";
    }

} catch (PDOException $e) {
    echo "<h2 class='error'>❌ Chyba databáze:</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
