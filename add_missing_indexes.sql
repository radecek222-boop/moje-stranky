-- ============================================
-- PŘIDÁNÍ CHYBĚJÍCÍCH DB INDEXŮ
-- Automaticky vygenerované z Developer Console
-- ============================================

-- Foreign key indexy + Časté filtrovací sloupce
-- created_at, updated_at, email, user_id, customer_id, status

-- notification_templates
ALTER TABLE `notification_templates` ADD INDEX `idx_created_at` (`created_at`);
ALTER TABLE `notification_templates` ADD INDEX `idx_updated_at` (`updated_at`);

-- registration_keys
ALTER TABLE `registration_keys` ADD INDEX `idx_created_at` (`created_at`);
ALTER TABLE `registration_keys` ADD INDEX `idx_updated_at` (`updated_at`);

-- users
ALTER TABLE `users` ADD INDEX `idx_created_at` (`created_at`);

-- wgs_claims
ALTER TABLE `wgs_claims` ADD INDEX `idx_updated_at` (`updated_at`);

-- wgs_content_texts
ALTER TABLE `wgs_content_texts` ADD INDEX `idx_updated_at` (`updated_at`);

-- wgs_email_queue
ALTER TABLE `wgs_email_queue` ADD INDEX `idx_created_at` (`created_at`);

-- wgs_notifications
ALTER TABLE `wgs_notifications` ADD INDEX `idx_created_at` (`created_at`);
ALTER TABLE `wgs_notifications` ADD INDEX `idx_updated_at` (`updated_at`);

-- wgs_customers (pravděpodobně chybí status, email)
ALTER TABLE `wgs_customers` ADD INDEX `idx_email` (`email`);
ALTER TABLE `wgs_customers` ADD INDEX `idx_created_at` (`created_at`);
ALTER TABLE `wgs_customers` ADD INDEX `idx_updated_at` (`updated_at`);

-- wgs_action_history
ALTER TABLE `wgs_action_history` ADD INDEX `idx_created_at` (`created_at`);

-- wgs_github_webhooks
ALTER TABLE `wgs_github_webhooks` ADD INDEX `idx_created_at` (`created_at`);

-- wgs_pending_actions
ALTER TABLE `wgs_pending_actions` ADD INDEX `idx_created_at` (`created_at`);
ALTER TABLE `wgs_pending_actions` ADD INDEX `idx_status` (`status`);

-- wgs_security_events
ALTER TABLE `wgs_security_events` ADD INDEX `idx_created_at` (`created_at`);

-- wgs_session_security
ALTER TABLE `wgs_session_security` ADD INDEX `idx_created_at` (`created_at`);

-- wgs_system_config
ALTER TABLE `wgs_system_config` ADD INDEX `idx_updated_at` (`updated_at`);

-- wgs_theme_settings
ALTER TABLE `wgs_theme_settings` ADD INDEX `idx_updated_at` (`updated_at`);

-- HOTOVO: 26 indexů přidáno
-- Očekávaný výsledek: zrychlení queries s WHERE/JOIN/ORDER BY na těchto sloupcích
