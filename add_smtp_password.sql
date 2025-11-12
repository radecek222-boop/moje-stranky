-- ============================================
-- Migration: Přidání SMTP password do system config
-- Datum: 2025-11-12
-- Účel: Doplnit chybějící SMTP password pro kompletní konfiguraci
-- ============================================

INSERT INTO wgs_system_config (config_key, config_value, config_group, is_sensitive, requires_restart, description)
VALUES ('smtp_password', '', 'email', TRUE, TRUE, 'SMTP authentication password')
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- Také přidáme smtp_encryption pro TLS/SSL
INSERT INTO wgs_system_config (config_key, config_value, config_group, is_sensitive, requires_restart, description)
VALUES ('smtp_encryption', 'tls', 'email', FALSE, TRUE, 'SMTP encryption method (tls, ssl, none)')
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- A tabulku pro historii notifikací
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'SMTP password and notification history table added successfully!' AS status;
