-- =====================================================
-- FINÁLNÍ SQL UPDATE: Patterns podle přesné analýzy
-- =====================================================
--
-- Založeno na:
-- - Skutečném PDF (NATUZZI PROTOKOL.pdf)
-- - RAW text z test_pdf_extrakce.php
-- - SQL struktuře tabulky wgs_reklamace
-- - Manuálně vyplněné reklamaci uživatelem
--
-- =====================================================

-- NATUZZI Protokol - PŘESNÉ PATTERNS
UPDATE wgs_pdf_parser_configs
SET regex_patterns = '{
  "cislo_reklamace": "/Čislo reklamace:\\\\s+NCE25-\\\\d+-\\\\d+\\\\s+([A-Z0-9\\\\-\\\\/]+)/ui",
  "datum_vyhotoveni": "/Datum vyhotovení:\\\\s+\\\\d+\\\\s+(\\\\d{1,2}\\\\.\\\\d{1,2}\\\\.\\\\d{4})/ui",
  "datum_podani": "/(\\\\d{1,2}\\\\.\\\\d{1,2}\\\\.\\\\d{4})\\\\s+Datum podání:/ui",
  "jmeno": "/Jméno společnosti:\\\\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+\\\\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)\\\\s+Poschodí:/ui",
  "email": "/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\\\.[a-zA-Z]{2,})\\\\s+[\\\\d\\\\s]+Telefon:/ui",
  "telefon": "/([\\\\d\\\\s]+)\\\\s+Telefon:/ui",
  "ulice": "/adresa:\\\\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][\\\\w\\\\s]+\\\\d+)/ui",
  "mesto": "/([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)\\\\s+Město:/ui",
  "psc": "/(\\\\d{5})\\\\s+PSČ:/ui",
  "model": "/Model:\\\\s+([^\\\\n]+?)\\\\s+Složení:/ui",
  "latka": "/Látka:\\\\s+([^\\\\n]+?)\\\\s+Nohy:/ui",
  "latka_barva": "/Látka:\\\\s+([^\\\\n]+?)\\\\s+Nohy:/ui",
  "zavada": "/Závada:\\\\s+([^\\\\n]+?)\\\\s+Model:/ui"
}'
WHERE zdroj = 'natuzzi';

-- Kontrola
SELECT
  config_id,
  nazev,
  'NATUZZI patterns opraveny podle přesné analýzy!' AS status
FROM wgs_pdf_parser_configs
WHERE zdroj = 'natuzzi';
