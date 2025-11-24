-- ==========================================
-- MIGRACE: Přidání chybějících indexů
-- ==========================================
-- Datum: 2025-11-24
-- Autor: Claude AI Technical Audit
-- Popis: Přidává chybějící indexy identifikované v auditu
-- Očekávaný přínos: 10-30% zrychlení dotazů
-- ==========================================

-- BEZPEČNOSTNÍ KONTROLA
-- SELECT DATABASE();

-- ==========================================
-- 1. wgs_notes - Přidat index na created_by
-- ==========================================

-- Důvod: Filtrování poznámek podle autora
-- Dotaz: SELECT * FROM wgs_notes WHERE created_by = 'user@example.com'
ALTER TABLE `wgs_notes`
ADD INDEX IF NOT EXISTS `idx_created_by` (`created_by`);

-- ==========================================
-- 2. wgs_notes - Composite index pro claim + datum
-- ==========================================

-- Důvod: Častý dotaz - poznámky k reklamaci řazené podle data
-- Dotaz: SELECT * FROM wgs_notes WHERE claim_id = X ORDER BY created_at DESC
ALTER TABLE `wgs_notes`
ADD INDEX IF NOT EXISTS `idx_claim_created` (`claim_id`, `created_at` DESC);

-- ==========================================
-- 3. wgs_notes - Index na created_at pro notifikace
-- ==========================================

-- Důvod: Dotazy na staré nepřečtené poznámky
-- Dotaz: WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
ALTER TABLE `wgs_notes`
ADD INDEX IF NOT EXISTS `idx_created_at_desc` (`created_at` DESC);

-- ==========================================
-- 4. wgs_documents - Index na reklamace_id (pokud se používá)
-- ==========================================

-- Důvod: Některé části kódu používají reklamace_id místo claim_id
-- Poznámka: Zkontrolujte zda se sloupec skutečně používá!
-- ALTER TABLE `wgs_documents`
-- ADD INDEX IF NOT EXISTS `idx_reklamace_id` (`reklamace_id`);
-- ↑ ZAKOMENTOVÁNO - aktivovat pouze pokud se reklamace_id používá

-- ==========================================
-- 5. wgs_notes_read - Composite index pro rychlé dotazy
-- ==========================================

-- Důvod: LEFT JOIN dotazy na nepřečtené poznámky
-- Dotaz: LEFT JOIN wgs_notes_read ON note_id = X AND user_email = Y
-- Poznámka: Již existuje unique_read (note_id, user_email) - STAČÍ!
-- Žádná změna není potřeba

-- ==========================================
-- HOTOVO!
-- ==========================================

-- Ověření vytvořených indexů:
SHOW INDEX FROM `wgs_notes`;

-- Statistiky velikosti indexů:
SELECT
    TABLE_NAME,
    INDEX_NAME,
    ROUND(STAT_VALUE * @@innodb_page_size / 1024 / 1024, 2) AS 'Size (MB)'
FROM mysql.innodb_index_stats
WHERE TABLE_NAME = 'wgs_notes'
  AND DATABASE_NAME = DATABASE()
  AND STAT_NAME = 'size'
ORDER BY STAT_VALUE DESC;
