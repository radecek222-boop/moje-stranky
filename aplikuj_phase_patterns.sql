-- =====================================================
-- AKTUALIZACE PHASE PATTERNS - Finální verze
-- =====================================================
--
-- Patterns pro slovenský PHASE protokol
-- Podle analýzy ANALYZA_PHASE_PDF.md
-- =====================================================

UPDATE wgs_pdf_parser_configs
SET regex_patterns = JSON_OBJECT(
    'cislo_reklamace', '/Číslo reklamácie:\\\\s+([A-Z0-9\\\\-\\\\/]+)/ui',
    'datum_vyhotovenia', '/Dátum vyhotovenia:\\\\s+(\\\\d{1,2}\\\\.\\\\d{1,2}\\\\.\\\\d{4})/ui',
    'datum_podania', '/Dátum podania:\\\\s+(\\\\d{1,2}\\\\.\\\\d{1,2}\\\\.\\\\d{4})/ui',
    'jmeno', '/Meno a priezvisko:\\\\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+\\\\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)/ui',
    'email', '/Email:\\\\s+([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\\\.[a-zA-Z]{2,})/ui',
    'telefon', '/Telefón:\\\\s+([\\\\d\\\\s]+)/ui',
    'ulice', '/Adresa:\\\\s+([^\\\\n]+?)(?:\\\\s+Meno|$)/ui',
    'mesto', '/Mesto:\\\\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)/ui',
    'psc', '/PSČ:\\\\s+(\\\\d{3}\\\\s?\\\\d{2}|\\\\d{5})/ui',
    'model', '/Model:\\\\s+([^\\\\n]+?)(?:\\\\s+Zloženie|$)/ui',
    'latka', '/Látka:\\\\s+([^\\\\n]+?)(?:\\\\s+Kategória|Nohy|$)/ui',
    'latka_barva', '/Látka:\\\\s+([^\\\\n]+?)(?:\\\\s+Kategória|Nohy|$)/ui',
    'zavada', '/Závada:\\\\s+([^\\\\n]+?)(?:\\\\s+Vyjadrenie|$)/ui'
),
pole_mapping = JSON_OBJECT(
    'cislo_reklamace', 'cislo',
    'datum_vyhotovenia', 'datum_prodeje',
    'datum_podania', 'datum_reklamace',
    'jmeno', 'jmeno',
    'email', 'email',
    'telefon', 'telefon',
    'ulice', 'ulice',
    'mesto', 'mesto',
    'psc', 'psc',
    'model', 'model',
    'latka', 'provedeni',
    'latka_barva', 'barva',
    'zavada', 'popis_problemu'
),
detekce_pattern = 'Dátum podania|Miesto reklamácie|Telefón|Krajina',
aktivni = 1,
priorita = 10
WHERE zdroj = 'phase';

-- Kontrola
SELECT
    config_id,
    nazev,
    zdroj,
    detekce_pattern,
    priorita,
    aktivni,
    JSON_PRETTY(regex_patterns) AS patterns,
    JSON_PRETTY(pole_mapping) AS mapping
FROM wgs_pdf_parser_configs
WHERE zdroj = 'phase';
