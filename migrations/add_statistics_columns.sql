-- =======================================================================
-- MIGRACE: Přidání sloupců pro statistiky reklamací
-- =======================================================================
-- Datum: 2025-11-15
-- Účel: Přidat sloupce prodejce, technik, castka, zeme, mesto pro statistiky
-- Důvod: API statistiky očekává tyto sloupce ale neexistují v tabulce
-- =======================================================================

-- 1. Přidat nové sloupce
ALTER TABLE wgs_reklamace
ADD COLUMN IF NOT EXISTS prodejce VARCHAR(255) NULL COMMENT 'Jméno prodejce' AFTER zpracoval,
ADD COLUMN IF NOT EXISTS technik VARCHAR(255) NULL COMMENT 'Jméno technika' AFTER prodejce,
ADD COLUMN IF NOT EXISTS castka DECIMAL(10,2) NULL COMMENT 'Částka za opravu (kopie z cena)' AFTER technik,
ADD COLUMN IF NOT EXISTS zeme VARCHAR(2) NULL COMMENT 'Země (kopie z fakturace_firma)' AFTER castka,
ADD COLUMN IF NOT EXISTS mesto VARCHAR(255) NULL COMMENT 'Město zákazníka' AFTER zeme;

-- 2. Vytvořit indexy pro rychlejší filtrování a GROUP BY
CREATE INDEX IF NOT EXISTS idx_prodejce ON wgs_reklamace(prodejce);
CREATE INDEX IF NOT EXISTS idx_technik ON wgs_reklamace(technik);
CREATE INDEX IF NOT EXISTS idx_zeme ON wgs_reklamace(zeme);
CREATE INDEX IF NOT EXISTS idx_mesto ON wgs_reklamace(mesto);

-- 3. Naplnit data z existujících sloupců
-- castka = cena
UPDATE wgs_reklamace
SET castka = cena
WHERE castka IS NULL OR castka = 0;

-- zeme = fakturace_firma
UPDATE wgs_reklamace
SET zeme = fakturace_firma
WHERE (zeme IS NULL OR zeme = '') AND fakturace_firma IS NOT NULL;

-- prodejce = zpracoval (pokud není NULL)
UPDATE wgs_reklamace
SET prodejce = zpracoval
WHERE (prodejce IS NULL OR prodejce = '') AND zpracoval IS NOT NULL AND zpracoval != '';

-- mesto = extrahovat z adresa (první řádek nebo části adresy)
-- Toto je volitelné - můžeme ponechat NULL nebo parsovat z adresy
UPDATE wgs_reklamace
SET mesto = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(adresa, ',', -1), '\n', 1))
WHERE (mesto IS NULL OR mesto = '')
  AND adresa IS NOT NULL
  AND adresa != ''
  AND CHAR_LENGTH(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(adresa, ',', -1), '\n', 1))) > 0
  AND CHAR_LENGTH(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(adresa, ',', -1), '\n', 1))) < 100;

-- =======================================================================
-- POZNÁMKY:
-- =======================================================================
-- - Sloupec 'castka' je duplikát 'cena' pro zpětnou kompatibilitu s API
-- - Sloupec 'zeme' je duplikát 'fakturace_firma' pro zpětnou kompatibilitu
-- - Sloupec 'prodejce' může být naplněn ze 'zpracoval' nebo manuálně
-- - Sloupec 'technik' může být naplněn manuálně nebo z jiného systému
-- - Sloupec 'mesto' je extrahován z 'adresa' (poslední část oddělená čárkou)
--
-- Po aplikaci migrace by měly statistiky fungovat bez dalších úprav.
-- =======================================================================

-- Ověření - ukázat prvních 10 záznamů
SELECT
    id,
    reklamace_id,
    jmeno,
    prodejce,
    technik,
    castka,
    cena,
    zeme,
    fakturace_firma,
    mesto,
    adresa
FROM wgs_reklamace
ORDER BY created_at DESC
LIMIT 10;
