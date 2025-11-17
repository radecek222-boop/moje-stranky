-- ========================================
-- Migrace: Email Worker Sloupce
-- Přidání chybějících sloupců do wgs_email_queue
-- ========================================

-- 1. Přidat sloupec 'attempts' (počet pokusů o odeslání)
ALTER TABLE wgs_email_queue
ADD COLUMN IF NOT EXISTS attempts INT DEFAULT 0 COMMENT 'Počet pokusů o odeslání';

-- 2. Přidat sloupec 'max_attempts' (maximální počet pokusů)
ALTER TABLE wgs_email_queue
ADD COLUMN IF NOT EXISTS max_attempts INT DEFAULT 3 COMMENT 'Maximální počet pokusů';

-- 3. Přidat sloupec 'scheduled_at' (plánovaný čas odeslání)
ALTER TABLE wgs_email_queue
ADD COLUMN IF NOT EXISTS scheduled_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Plánovaný čas odeslání';

-- 4. Přidat sloupec 'priority' (priorita emailu)
ALTER TABLE wgs_email_queue
ADD COLUMN IF NOT EXISTS priority INT DEFAULT 0 COMMENT 'Priorita emailu (vyšší = dřív)';

-- 5. Přidat sloupec 'recipient_email' (kompatibilita s email workerem)
ALTER TABLE wgs_email_queue
ADD COLUMN IF NOT EXISTS recipient_email VARCHAR(255) COMMENT 'Email příjemce (kopie to_email)';

-- ========================================
-- Kopírování dat do nových sloupců
-- ========================================

-- 6. Zkopírovat hodnoty z retry_count do attempts
UPDATE wgs_email_queue
SET attempts = COALESCE(retry_count, 0)
WHERE attempts = 0;

-- 7. Zkopírovat hodnoty z to_email do recipient_email
UPDATE wgs_email_queue
SET recipient_email = to_email
WHERE recipient_email IS NULL OR recipient_email = '';

-- ========================================
-- Kontrola výsledku
-- ========================================

-- Zobrazit strukturu tabulky
SHOW COLUMNS FROM wgs_email_queue;

-- Zobrazit několik záznamů pro kontrolu
SELECT id, to_email, recipient_email, retry_count, attempts, max_attempts, status, scheduled_at
FROM wgs_email_queue
LIMIT 5;
