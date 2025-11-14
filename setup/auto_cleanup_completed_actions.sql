-- ========================================
-- AUTOMATICKÝ CLEANUP DOKONČENÝCH ÚKOLŮ
-- ========================================
-- Tento SQL vytvoří MySQL EVENT, který automaticky
-- maže staré dokončené úkoly z Control Center
-- ========================================

-- KROK 1: Povolit eventy (pokud nejsou povoleny)
SET GLOBAL event_scheduler = ON;

-- KROK 2: Vytvořit event pro automatický cleanup
DROP EVENT IF EXISTS cleanup_old_pending_actions;

CREATE EVENT cleanup_old_pending_actions
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
  DELETE FROM wgs_pending_actions
  WHERE status IN ('completed', 'failed', 'cancelled')
    AND completed_at IS NOT NULL
    AND completed_at < DATE_SUB(NOW(), INTERVAL 7 DAY);

-- ========================================
-- CO TO DĚLÁ?
-- ========================================
-- - Běží KAŽDÝ DEN automaticky
-- - Maže dokončené/selhavší úkoly starší než 7 dní
-- - Control Center zůstává čistý
-- - Pending úkoly se nemažou (status = 'pending')
-- ========================================

-- ========================================
-- JAK TO OVĚŘIT?
-- ========================================
-- Zkontroluj, že event běží:
SHOW EVENTS WHERE Name = 'cleanup_old_pending_actions';

-- Zkontroluj event_scheduler:
SHOW VARIABLES LIKE 'event_scheduler';
-- Mělo by být: ON

-- ========================================
-- JAK TO VYPNOUT? (pokud bys potřeboval)
-- ========================================
-- DROP EVENT cleanup_old_pending_actions;
-- ========================================

-- ========================================
-- OKAMŽITÝ CLEANUP (jednorázové spuštění)
-- ========================================
-- Pokud chceš vyčistit staré úkoly HNED (nemusíš čekat na event):
-- DELETE FROM wgs_pending_actions
-- WHERE status IN ('completed', 'failed', 'cancelled')
--   AND completed_at IS NOT NULL
--   AND completed_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
-- ========================================
