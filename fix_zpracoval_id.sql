-- FIX: Nastavení zpracoval_id pro existující reklamace
-- Důvod: Reklamace bez zpracoval_id se nezobrazují v load.php
-- Datum: 2025-11-10

-- 1. Nastav zpracoval_id=7 pro všechny reklamace které nemají nastaveno
--    (user_id 7 = naty@naty.cz)
UPDATE wgs_reklamace
SET zpracoval_id = 7
WHERE zpracoval_id IS NULL OR zpracoval_id = '';

-- 2. Ověř výsledek
SELECT
    id,
    reklamace_id,
    jmeno,
    email,
    zpracoval_id,
    created_at
FROM wgs_reklamace
ORDER BY created_at DESC;

-- Očekávaný výsledek:
-- Všechny reklamace by měly mít zpracoval_id=7
-- Jiří Nováček (id=17) by měl mít zpracoval_id=7
-- Gustav Sechter (id=2) už má zpracoval_id=7
