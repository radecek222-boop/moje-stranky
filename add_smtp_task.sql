-- ============================================
-- Migration: Přidat SMTP instalační úlohu do akcí
-- Datum: 2025-11-12
-- Účel: Přidat úlohu pro instalaci SMTP konfigurace do systému akcí
-- ============================================

INSERT INTO wgs_pending_actions (
    action_type,
    action_title,
    action_description,
    priority,
    status,
    created_at
)
VALUES (
    'install_smtp',
    'Instalovat SMTP konfiguraci',
    'Přidá smtp_password a smtp_encryption klíče do system_config a vytvoří tabulku wgs_notification_history pro sledování odeslaných emailů a SMS.',
    'high',
    'pending',
    CURRENT_TIMESTAMP
)
ON DUPLICATE KEY UPDATE
    action_description = VALUES(action_description),
    priority = VALUES(priority);

SELECT 'SMTP instalační úloha byla přidána do systému akcí!' AS status;
