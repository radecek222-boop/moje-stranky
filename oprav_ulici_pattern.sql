-- =====================================================
-- OPRAVA: Pattern pro ulici
-- =====================================================
--
-- PROBLÉM: Ulice se nenachází kde původní pattern očekával
--
-- RAW text z PDF:
-- "Osnice Město:  Na Blatech 396 Adresa:  Jméno společnosti:"
--
-- Ulice je MEZI "Město:" a "Adresa:"
-- =====================================================

UPDATE wgs_pdf_parser_configs
SET regex_patterns = JSON_SET(
    regex_patterns,
    '$.ulice',
    '/Město:\\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+\\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+\\s+\\d+)\\s+Adresa:/ui'
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
