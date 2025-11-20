-- =====================================================
-- OPRAVA: Pattern pro ulici - FINÁLNÍ VERZE
-- =====================================================
--
-- PROBLÉM: Pattern nenašel ulici
--
-- V PDF vizuálně: "Adresa: Na Blatech 396"
-- V SQL: pole `ulice` VARCHAR(255) - "Ulice a číslo popisné"
--
-- ŘEŠENÍ: Hledat text PO "adresa:" (case-insensitive)
-- =====================================================

-- NATUZZI (český protokol)
UPDATE wgs_pdf_parser_configs
SET regex_patterns = JSON_SET(
    regex_patterns,
    '$.ulice',
    '/adresa:\\\\s+([^\\n]+?)(?:\\\\s+(?:Meno|Jméno)|$)/ui'
)
WHERE zdroj = 'natuzzi';

-- PHASE (slovenský protokol)
UPDATE wgs_pdf_parser_configs
SET regex_patterns = JSON_SET(
    regex_patterns,
    '$.ulice',
    '/adresa:\\\\s+([^\\n]+?)(?:\\\\s+(?:Meno|Jméno)|$)/ui'
)
WHERE zdroj = 'phase';

-- Kontrola
SELECT
    config_id,
    nazev,
    zdroj,
    JSON_EXTRACT(regex_patterns, '$.ulice') AS ulice_pattern,
    'Pattern pro ulici opraven!' AS status
FROM wgs_pdf_parser_configs
WHERE zdroj IN ('natuzzi', 'phase');
