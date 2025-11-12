-- ============================================
-- KOMPLETNÍ MIGRACE: Systém akcí a úkolů
-- Datum: 2025-11-12
-- Účel: Vytvoření tabulek pro systém akcí v Control Center
-- ============================================

-- ============================================
-- Tabulka: wgs_pending_actions
-- Uchovává nevyřešené úlohy pro administrátory
-- ============================================
CREATE TABLE IF NOT EXISTS wgs_pending_actions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    action_type VARCHAR(50) NOT NULL COMMENT 'Typ akce: install_smtp, migration, update, etc.',
    action_title VARCHAR(255) NOT NULL COMMENT 'Název úlohy zobrazený v UI',
    action_description TEXT DEFAULT NULL COMMENT 'Detailní popis úlohy',
    action_url VARCHAR(255) DEFAULT NULL COMMENT 'URL scriptu k vykonání (pro migrations)',
    priority ENUM('critical', 'high', 'medium', 'low') DEFAULT 'medium' COMMENT 'Priorita úlohy',
    status ENUM('pending', 'in_progress', 'completed', 'failed', 'dismissed') DEFAULT 'pending' COMMENT 'Aktuální stav úlohy',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    completed_by INT DEFAULT NULL COMMENT 'ID uživatele, který úlohu dokončil',
    dismissed_at TIMESTAMP NULL DEFAULT NULL,
    dismissed_by INT DEFAULT NULL COMMENT 'ID uživatele, který úlohu zrušil',

    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_created_at (created_at),
    INDEX idx_action_type (action_type),

    FOREIGN KEY (completed_by) REFERENCES wgs_users(id) ON DELETE SET NULL,
    FOREIGN KEY (dismissed_by) REFERENCES wgs_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Nevyřešené úlohy a plánované akce pro administrátory';

-- ============================================
-- Tabulka: wgs_action_history
-- Historie všech vykonaných akcí (audit trail)
-- ============================================
CREATE TABLE IF NOT EXISTS wgs_action_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    action_id INT DEFAULT NULL COMMENT 'Reference na původní akci (pokud existovala)',
    action_type VARCHAR(50) NOT NULL,
    action_title VARCHAR(255) NOT NULL,
    status ENUM('completed', 'failed') NOT NULL,
    executed_by INT DEFAULT NULL COMMENT 'ID uživatele, který akci spustil',
    execution_time INT DEFAULT NULL COMMENT 'Čas vykonávání v milisekundách',
    error_message TEXT DEFAULT NULL COMMENT 'Chybová zpráva (pokud failed)',
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_action_id (action_id),
    INDEX idx_status (status),
    INDEX idx_executed_at (executed_at),
    INDEX idx_action_type (action_type),

    FOREIGN KEY (action_id) REFERENCES wgs_pending_actions(id) ON DELETE SET NULL,
    FOREIGN KEY (executed_by) REFERENCES wgs_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historie všech vykonaných akcí (audit trail)';

-- ============================================
-- Iniciální data: SMTP instalační úloha
-- ============================================
INSERT INTO wgs_pending_actions (
    action_type,
    action_title,
    action_description,
    priority,
    status
)
VALUES (
    'install_smtp',
    'Instalovat SMTP konfiguraci',
    'Přidá smtp_password a smtp_encryption klíče do system_config a vytvoří tabulku wgs_notification_history pro sledování odeslaných emailů a SMS.',
    'high',
    'pending'
)
ON DUPLICATE KEY UPDATE
    action_description = VALUES(action_description);

-- ============================================
-- Konec migrace
-- ============================================
SELECT 'Migration completed successfully!' AS status;
