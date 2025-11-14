-- ============================================
-- Migration: Email Queue System
-- Datum: 2025-11-14
-- Účel: Asynchronní odeslání emailů přes frontu
-- ============================================

-- Email Queue Table
CREATE TABLE IF NOT EXISTS wgs_email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id VARCHAR(50) NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255) DEFAULT NULL,
    subject VARCHAR(500) NOT NULL,
    body TEXT NOT NULL,
    cc_emails JSON DEFAULT NULL,
    bcc_emails JSON DEFAULT NULL,
    priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
    status ENUM('pending', 'sending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL DEFAULT NULL,

    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SMTP Settings Table
CREATE TABLE IF NOT EXISTS wgs_smtp_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port INT DEFAULT 587,
    smtp_encryption ENUM('none', 'ssl', 'tls') DEFAULT 'tls',
    smtp_username VARCHAR(255) NOT NULL,
    smtp_password VARCHAR(500) NOT NULL,
    smtp_from_email VARCHAR(255) NOT NULL,
    smtp_from_name VARCHAR(255) DEFAULT 'WGS Service',
    is_active TINYINT(1) DEFAULT 1,
    last_test_at TIMESTAMP NULL DEFAULT NULL,
    last_test_status ENUM('success', 'failed') DEFAULT NULL,
    last_test_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrace úspěšně dokončena
-- SMTP settings budou vloženy instalátorem
