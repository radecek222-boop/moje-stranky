<?php
/**
 * Installation Script: SMTP Configuration
 * Tento script přidá chybějící SMTP konfiguraci do databáze
 *
 * POUŽITÍ: Otevřete tento soubor v prohlížeči jako admin
 */

require_once "init.php";

// BEZPEČNOST: Pouze admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    die('ERROR: Přístup pouze pro administrátory. Přihlaste se prosím jako admin.');
}

try {
    $pdo = getDbConnection();

    echo "<h1>Instalace SMTP konfigurace</h1>";
    echo "<pre>";

    // 1. Přidat smtp_password
    echo "1. Přidávám smtp_password do wgs_system_config...\n";
    $stmt = $pdo->prepare("
        INSERT INTO wgs_system_config (config_key, config_value, config_group, is_sensitive, requires_restart, description)
        VALUES ('smtp_password', '', 'email', TRUE, TRUE, 'SMTP authentication password')
        ON DUPLICATE KEY UPDATE description=VALUES(description)
    ");
    $stmt->execute();
    echo "   ✓ smtp_password přidán\n\n";

    // 2. Přidat smtp_encryption
    echo "2. Přidávám smtp_encryption do wgs_system_config...\n";
    $stmt = $pdo->prepare("
        INSERT INTO wgs_system_config (config_key, config_value, config_group, is_sensitive, requires_restart, description)
        VALUES ('smtp_encryption', 'tls', 'email', FALSE, TRUE, 'SMTP encryption method (tls, ssl, none)')
        ON DUPLICATE KEY UPDATE description=VALUES(description)
    ");
    $stmt->execute();
    echo "   ✓ smtp_encryption přidán\n\n";

    // 3. Vytvořit tabulku pro historii notifikací
    echo "3. Vytvářím tabulku wgs_notification_history...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wgs_notification_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            notification_id VARCHAR(50) DEFAULT NULL,
            recipient_email VARCHAR(255) NOT NULL,
            recipient_type ENUM('customer', 'admin', 'technician', 'seller') NOT NULL,
            notification_type ENUM('email', 'sms') NOT NULL,
            subject VARCHAR(255) DEFAULT NULL,
            message TEXT NOT NULL,
            status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
            error_message TEXT DEFAULT NULL,
            sent_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_recipient (recipient_email),
            INDEX idx_type (notification_type),
            FOREIGN KEY (notification_id) REFERENCES wgs_notifications(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ wgs_notification_history tabulka vytvořena\n\n";

    // 4. Ověření
    echo "4. Ověřování instalace...\n";

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_system_config WHERE config_key IN ('smtp_password', 'smtp_encryption')");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✓ SMTP konfigurace: {$result['count']}/2 klíčů\n";

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_notification_history'");
    $exists = $stmt->rowCount() > 0;
    echo "   ✓ Historie notifikací: " . ($exists ? "existuje" : "neexistuje") . "\n\n";

    echo "</pre>";
    echo "<h2 style='color: green;'>Instalace dokončena!</h2>";
    echo "<p>Nyní můžete pokračovat na <a href='admin.php?tab=notifications'>Správa Emailů & SMS</a></p>";

} catch (Exception $e) {
    echo "</pre>";
    echo "<h2 style='color: red;'>Chyba při instalaci</h2>";
    echo "<p>ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
