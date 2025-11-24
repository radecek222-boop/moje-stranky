-- ==========================================
-- MIGRACE: Odstranění redundantních indexů
-- ==========================================
-- Datum: 2025-11-24
-- Autor: Claude AI Technical Audit
-- Popis: Odstraňuje duplicitní indexy pro lepší INSERT/UPDATE výkon
-- Očekávaný přínos: 5-15% rychlejší INSERT/UPDATE, úspora ~150 KB
-- ==========================================

-- ⚠️ DŮLEŽITÉ: Před spuštěním vytvořte BACKUP!
-- mysqldump wgs-servicecz01 > backup_before_index_removal_$(date +%Y%m%d).sql

-- BEZPEČNOSTNÍ KONTROLA
-- SELECT DATABASE();

-- ==========================================
-- 1. wgs_users - Odstranit redundantní email indexy
-- ==========================================

-- Aktuální stav: 3 indexy na stejný sloupec!
-- - UNIQUE KEY email (email)      ✅ Tento zachovat
-- - INDEX idx_email (email)        ❌ Redundantní
-- - INDEX idx_user_email (email)   ❌ Redundantní

-- Ověření že existují:
SHOW INDEX FROM wgs_users WHERE Column_name = 'email';

-- Odstranění redundantních indexů:
ALTER TABLE `wgs_users` DROP INDEX IF EXISTS `idx_email`;
ALTER TABLE `wgs_users` DROP INDEX IF EXISTS `idx_user_email`;

-- Zachován pouze: UNIQUE KEY email (email)

-- ==========================================
-- 2. wgs_email_queue - Odstranit duplicitní created_at index
-- ==========================================

-- Aktuální stav: 2 indexy na stejný sloupec
-- - INDEX idx_created_at (created_at)       ✅ Tento zachovat
-- - INDEX idx_created_at_ts (created_at)    ❌ Redundantní

-- Ověření že existují:
SHOW INDEX FROM wgs_email_queue WHERE Column_name = 'created_at';

-- Odstranění redundantního indexu:
ALTER TABLE `wgs_email_queue` DROP INDEX IF EXISTS `idx_created_at_ts`;

-- Zachován pouze: INDEX idx_created_at (created_at)

-- ==========================================
-- 3. wgs_reklamace - Možná duplicita reklamace_id
-- ==========================================

-- Aktuální stav:
-- - UNIQUE KEY reklamace_id (reklamace_id)  ✅ Tento zachovat
-- - INDEX idx_reklamace_id (reklamace_id)   ❌ Možná redundantní

-- ⚠️ POZNÁMKA: UNIQUE KEY už zajišťuje rychlé vyhledávání!
-- Index idx_reklamace_id je redundantní, ale neškodí (MariaDB ho může použít)

-- Pokud chcete optimalizovat:
-- ALTER TABLE `wgs_reklamace` DROP INDEX IF EXISTS `idx_reklamace_id`;

-- ↑ ZAKOMENTOVÁNO - ponechat pro zpětnou kompatibilitu
-- Odstranění by ušetřilo ~20 KB, ale může ovlivnit starší dotazy

-- ==========================================
-- HOTOVO!
-- ==========================================

-- Ověření zbývajících indexů:
SELECT
    TABLE_NAME,
    COUNT(*) AS index_count
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('wgs_users', 'wgs_email_queue', 'wgs_reklamace')
GROUP BY TABLE_NAME;

-- Kontrola velikosti tabulek po optimalizaci:
SELECT
    TABLE_NAME,
    ROUND(DATA_LENGTH / 1024 / 1024, 2) AS 'Data (MB)',
    ROUND(INDEX_LENGTH / 1024 / 1024, 2) AS 'Index (MB)',
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS 'Total (MB)'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('wgs_users', 'wgs_email_queue')
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;

-- Optimalizace tabulek (volitelné - může trvat několik minut):
-- OPTIMIZE TABLE `wgs_users`;
-- OPTIMIZE TABLE `wgs_email_queue`;
