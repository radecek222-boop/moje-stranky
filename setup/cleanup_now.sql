-- ========================================
-- OKAMŽITÉ VYČIŠTĚNÍ DOKONČENÝCH ÚKOLŮ
-- ========================================
-- Použití: Spusť tento SQL kdykoli chceš vyčistit Control Center
-- ========================================

-- Smaže VŠECHNY dokončené/selhavší/zrušené úkoly
DELETE FROM wgs_pending_actions
WHERE status IN ('completed', 'failed', 'cancelled');

-- ========================================
-- HOTOVO!
-- ========================================
-- Po spuštění:
-- - Control Center -> Akce & Úkoly bude čistý
-- - Zůstanou pouze pending úkoly
-- ========================================
