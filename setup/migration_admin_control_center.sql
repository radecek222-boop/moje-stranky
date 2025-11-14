-- ============================================
-- Migration: Admin Control Center Database
-- Datum: 2025-11-11
-- Účel: Databázové tabulky pro iOS-style admin panel
-- ============================================

-- 1. Theme Settings (Barvy, fonty, logo)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Výchozí theme nastavení
INSERT INTO wgs_theme_settings (setting_key, setting_value, setting_type, setting_group) VALUES
('primary_color', '#000000', 'color', 'colors'),
('secondary_color', '#FFFFFF', 'color', 'colors'),
('success_color', '#28A745', 'color', 'colors'),
('warning_color', '#FFC107', 'color', 'colors'),
('danger_color', '#DC3545', 'color', 'colors'),
('grey_color', '#555555', 'color', 'colors'),
('light_grey_color', '#999999', 'color', 'colors'),
('border_color', '#E0E0E0', 'color', 'colors'),
('font_family', 'Poppins', 'font', 'typography'),
('font_size_base', '16px', 'size', 'typography'),
('logo_path', '/assets/images/logo.png', 'file', 'branding'),
('border_radius', '8px', 'size', 'layout'),
('button_style', 'rounded', 'text', 'layout')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- ============================================

-- 2. Content Texts (Editovatelné texty stránek)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Výchozí texty (Hero sekce)
INSERT INTO wgs_content_texts (page, section, text_key, value_cz, value_en) VALUES
('index', 'hero', 'title', 'Servis spotřebičů všech značek', 'Appliance Service - All Brands'),
('index', 'hero', 'subtitle', 'Rychle, kvalitně, profesionálně', 'Fast, Quality, Professional'),
('index', 'services', 'title', 'Naše služby', 'Our Services'),
('index', 'contact', 'title', 'Kontaktujte nás', 'Contact Us'),
('novareklamace', 'form', 'title', 'Nová reklamace', 'New Service Request'),
('novareklamace', 'form', 'submit_button', 'Odeslat reklamaci', 'Submit Request'),
('email', 'signature', 'company_name', 'White Glove Service', 'White Glove Service'),
('email', 'signature', 'phone', '+420 725 965 826', '+420 725 965 826'),
('email', 'signature', 'email', 'reklamace@wgs-service.cz', 'reklamace@wgs-service.cz')
ON DUPLICATE KEY UPDATE value_cz=VALUES(value_cz), value_en=VALUES(value_en);

-- ============================================

-- 3. System Configuration (Konfigurace systému)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Výchozí konfigurace
INSERT INTO wgs_system_config (config_key, config_value, config_group, is_sensitive, requires_restart, description) VALUES
('smtp_host', '', 'email', TRUE, TRUE, 'SMTP server hostname'),
('smtp_port', '587', 'email', FALSE, TRUE, 'SMTP port (usually 587 or 465)'),
('smtp_username', '', 'email', TRUE, TRUE, 'SMTP authentication username'),
('smtp_from', 'reklamace@wgs-service.cz', 'email', FALSE, TRUE, 'Default FROM email address'),
('smtp_from_name', 'White Glove Service', 'email', FALSE, FALSE, 'FROM name for emails'),
('geoapify_api_key', '', 'api_keys', TRUE, FALSE, 'Geoapify API key for maps'),
('deepl_api_key', '', 'api_keys', TRUE, FALSE, 'DeepL API key for translations'),
('github_webhook_secret', '', 'api_keys', TRUE, FALSE, 'GitHub webhook secret for signature validation'),
('rate_limit_login', '5', 'security', FALSE, TRUE, 'Max login attempts per 15 minutes'),
('rate_limit_upload', '20', 'security', FALSE, TRUE, 'Max photo uploads per hour'),
('session_timeout', '86400', 'security', FALSE, TRUE, 'Session timeout in seconds (24 hours)'),
('maintenance_mode', '0', 'system', FALSE, FALSE, 'Enable maintenance mode (0=off, 1=on)')
ON DUPLICATE KEY UPDATE config_value=VALUES(config_value);

-- ============================================

-- 4. Pending Actions (Nevyřešené úkoly)
CREATE TABLE IF NOT EXISTS wgs_pending_actions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    action_type VARCHAR(50) NOT NULL,
    action_title VARCHAR(255) NOT NULL,
    action_description TEXT,
    action_url VARCHAR(255),
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed', 'failed', 'dismissed') DEFAULT 'pending',
    requires_admin BOOLEAN DEFAULT TRUE,
    source_type VARCHAR(50) DEFAULT NULL COMMENT 'github_webhook, manual, system',
    source_id INT DEFAULT NULL COMMENT 'ID záznamu ve zdrojové tabulce',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    completed_by INT DEFAULT NULL,
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_type (action_type),
    INDEX idx_source (source_type, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Příklad pending actions
INSERT INTO wgs_pending_actions (action_type, action_title, action_description, action_url, priority, status) VALUES
('migration', 'Spustit Admin Control Center migraci', 'Vytvořit databázové tabulky pro nový admin panel', '/install_admin_control_center.php', 'high', 'pending'),
('config', 'Nastavit SMTP email', 'Vyplnit SMTP credentials pro odesílání emailů', '/admin.php?tab=control_center&section=config', 'medium', 'pending'),
('cleanup', 'Vymazat staré logy', 'Smazat logy starší než 90 dní', '/admin.php?tab=tools', 'low', 'pending')
ON DUPLICATE KEY UPDATE action_title=VALUES(action_title);

-- ============================================

-- 5. Action History (Historie akcí)
CREATE TABLE IF NOT EXISTS wgs_action_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    action_id INT,
    action_type VARCHAR(50) NOT NULL,
    action_title VARCHAR(255) NOT NULL,
    status ENUM('completed', 'failed') NOT NULL,
    executed_by INT NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    execution_time INT DEFAULT NULL COMMENT 'Execution time in milliseconds',
    error_message TEXT,
    INDEX idx_type (action_type),
    INDEX idx_executed_by (executed_by),
    INDEX idx_executed_at (executed_at),
    FOREIGN KEY (action_id) REFERENCES wgs_pending_actions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================

-- 6. GitHub Webhooks (Pro GitHub Actions integrace)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration completed
