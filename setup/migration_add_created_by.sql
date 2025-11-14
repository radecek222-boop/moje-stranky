-- MIGRACE: Přidání created_by sloupce pro správné tracking vytvoření reklamací
-- Datum: 2025-11-10
-- Důvod: Škálovatelné řešení pro více prodejců a techniků

-- 1. Přidat created_by a created_by_role sloupce
ALTER TABLE wgs_reklamace
ADD COLUMN created_by INT NULL COMMENT 'ID uživatele který vytvořil reklamaci' AFTER zpracoval_id,
ADD COLUMN created_by_role VARCHAR(20) NULL DEFAULT 'user' COMMENT 'Role uživatele (admin, user, prodejce, technik, guest)' AFTER created_by;

-- 2. Naplnit existující data
-- Všechny reklamace které mají zpracoval_id už nastavené
UPDATE wgs_reklamace
SET created_by = zpracoval_id,
    created_by_role = 'user'
WHERE zpracoval_id IS NOT NULL;

-- 3. Pro reklamace bez zpracoval_id (vytvořené hostem) - nastavit guest
UPDATE wgs_reklamace
SET created_by_role = 'guest'
WHERE created_by IS NULL;

-- 4. Přidat index pro rychlejší vyhledávání
CREATE INDEX idx_created_by ON wgs_reklamace(created_by);
CREATE INDEX idx_created_by_role ON wgs_reklamace(created_by_role);

-- 5. Ověření
SELECT
    id,
    reklamace_id,
    jmeno,
    email,
    created_by,
    created_by_role,
    zpracoval_id,
    created_at
FROM wgs_reklamace
ORDER BY created_at DESC;

-- Očekávaný výsledek:
-- - Všechny reklamace by měly mít created_by nebo created_by_role nastavené
-- - Gustav a Jiří budou mít created_by=7, created_by_role='user'
