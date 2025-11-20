-- =====================================================
-- OPRAVA: Pattern pro ulici
-- =====================================================
--
-- PROBLÉM: Ulice se nenachází kde původní pattern očekával
--
-- V PDF vizuálně:
--   Město: Osnice
--   Adresa: Na Blatech 396
--
-- V RAW textu (PDF.js extrakce):
--   "Osnice Město: Na Blatech 396 Adresa:"
--
-- Ve formuláři novareklamace.php: "ULICE A ČÍSLO POPISNÉ"
-- V SQL: pole `ulice` VARCHAR(255)
--
-- ŘEŠENÍ: Hledat ulici PŘED "Adresa:" (s velkým A!)
-- Pattern zachytí text od prvního velkého písmene až po číslo
-- =====================================================

UPDATE wgs_pdf_parser_configs
SET regex_patterns = JSON_SET(
    regex_patterns,
    '$.ulice',
    '/([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][\\\\w\\\\s]+\\\\d+)\\\\s+Adresa:/ui'
)
WHERE zdroj = 'natuzzi';

-- Kontrola
SELECT
    config_id,
    nazev,
    JSON_EXTRACT(regex_patterns, '$.ulice') AS ulice_pattern,
    'Pattern pro ulici opraven!' AS status
FROM wgs_pdf_parser_configs
WHERE zdroj = 'natuzzi';
