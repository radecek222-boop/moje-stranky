-- =============================================
-- FINÁLNÍ OPRAVA: Patterns podle skutečného PDF
-- =============================================
--
-- PROBLÉM: Text z PDF je na jednom řádku s mezerami,
-- ne na více řádcích. Patterns musí hledat \s+ místo \n
--
-- Navíc: "Čislo" je bez háčku, data se opakují
-- =============================================

-- 1. NATUZZI - Nové patterns podle skutečného formátu
UPDATE wgs_pdf_parser_configs
SET regex_patterns = '{
  "cislo_reklamace": "/(?:Č[ií]slo|[CčČ]islo)\\\\s+reklamace:\\\\s+([A-Z0-9\\\\-\\\\/]+)/ui",
  "datum_podani": "/Datum\\\\s+podání:\\\\s+(\\\\d{1,2}\\\\.\\\\d{1,2}\\\\.\\\\d{4})/ui",
  "cislo_objednavky": "/Č[ií]slo\\\\s+objednávky:\\\\s+(\\\\d+)/ui",
  "cislo_faktury": "/Č[ií]slo\\\\s+faktury:\\\\s+(\\\\d+)/ui",
  "datum_vyhotoveni": "/Datum\\\\s+vyhotovení:\\\\s+(\\\\d{1,2}\\\\.\\\\d{1,2}\\\\.\\\\d{4})/ui",
  "jmeno": "/Jméno\\\\s+a\\\\s+příjmení:\\\\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+\\\\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)(?=\\\\s+Poschodí|\\\\s+Místo)/ui",
  "email": "/Email:\\\\s+([\\\\w._%+-]+@[\\\\w.-]+\\\\.[a-zA-Z]{2,})/ui",
  "telefon": "/Telefon:\\\\s+([\\\\d\\\\s]+?)(?=\\\\s+(?:Česko|Stát|Email))/ui",
  "adresa": "/Místo\\\\s+reklamace.*?Adresa:\\\\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][^,]+\\\\d+[a-z]?)/uis",
  "mesto": "/Místo\\\\s+reklamace.*?Město:\\\\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)(?=\\\\s+Adresa)/uis",
  "psc": "/Místo\\\\s+reklamace.*?PSČ:\\\\s+(\\\\d{3}\\\\s?\\\\d{2})/uis",
  "model": "/Model:\\\\s+([^\\\\n]+?)(?=\\\\s+Složení:)/ui",
  "slozeni": "/Složení:\\\\s+([^\\\\n]+?)(?=\\\\s+Látka:)/ui",
  "latka": "/Látka:\\\\s+([^\\\\n]+?)(?=\\\\s+Nohy:)/ui",
  "latka_barva": "/Látka:\\\\s+([^\\\\n]+?)(?=\\\\s+Nohy:)/ui",
  "zavada": "/Závada:\\\\s+([^\\\\n]+?)(?=\\\\s+Model:)/ui",
  "typ_objektu": "/(Rodinný\\\\s+dům|Panelový\\\\s+dům)/ui",
  "poschodie": "/Poschodí:\\\\s+(\\\\d+)/ui"
}',
pole_mapping = '{
  "cislo_reklamace": "cislo",
  "datum_vyhotoveni": "datum_prodeje",
  "datum_podani": "datum_reklamace",
  "jmeno": "jmeno",
  "email": "email",
  "telefon": "telefon",
  "adresa": "ulice",
  "mesto": "mesto",
  "psc": "psc",
  "model": "model",
  "latka": "provedeni",
  "latka_barva": "barva",
  "zavada": "popis_problemu"
}'
WHERE zdroj = 'natuzzi';

-- 2. PHASE - Nové patterns (slovenština)
UPDATE wgs_pdf_parser_configs
SET regex_patterns = '{
  "cislo_reklamace": "/Č[ií]slo\\\\s+reklamácie:\\\\s+([A-Z0-9\\\\-\\\\/]+)/ui",
  "datum_podania": "/Dátum\\\\s+podania:\\\\s+(\\\\d{1,2}\\\\.\\\\d{1,2}\\\\.\\\\d{4})/ui",
  "cislo_objednavky": "/Č[ií]slo\\\\s+objednávky:\\\\s+(\\\\d+)/ui",
  "cislo_faktury": "/Č[ií]slo\\\\s+faktúry:\\\\s+(\\\\d+)/ui",
  "datum_vyhotovenia": "/Dátum\\\\s+vyhotovenia:\\\\s+(\\\\d{1,2}\\\\.\\\\d{1,2}\\\\.\\\\d{4})/ui",
  "jmeno": "/Miesto\\\\s+reklamácie.*?Meno\\\\s+a\\\\s+priezvisko:\\\\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+\\\\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)/uis",
  "email": "/Email:\\\\s+([\\\\w._%+-]+@[\\\\w.-]+\\\\.[a-zA-Z]{2,})/ui",
  "telefon": "/Telefón:\\\\s+([\\\\d\\\\s]+?)(?=\\\\s+(?:Krajina|Email))/ui",
  "adresa": "/Miesto\\\\s+reklamácie.*?Adresa:\\\\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][^,]+\\\\d+[a-z]?)/uis",
  "mesto": "/Miesto\\\\s+reklamácie.*?Mesto:\\\\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)(?=\\\\s+Adresa)/uis",
  "psc": "/Miesto\\\\s+reklamácie.*?PSČ:\\\\s+(\\\\d{3}\\\\s?\\\\d{2})/uis",
  "krajina": "/Krajina:\\\\s+([^\\\\n]+?)(?=\\\\s)/ui",
  "model": "/Model:\\\\s+([^\\\\n]+?)(?=\\\\s+Zloženie:|\\\\s+Látka:)/ui",
  "zlozenie": "/Zloženie:\\\\s+([^\\\\n]+?)(?=\\\\s+Látka:)/ui",
  "latka": "/Látka:\\\\s+([^\\\\n]+?)(?=\\\\s+(?:Nohy:|Kategória:))/ui",
  "latka_barva": "/Látka:\\\\s+([^\\\\n]+?)(?=\\\\s+(?:Nohy:|Kategória:))/ui",
  "kategoria": "/Kategória:\\\\s+([^\\\\n]+?)(?=\\\\s)/ui",
  "zavada": "/Závada:\\\\s+([^\\\\n]+?)(?=\\\\s+Vyjadrenie)/ui",
  "typ_objektu": "/(Rodinný\\\\s+dom|Panelák)/ui",
  "poschodie": "/Poschodie:\\\\s+(\\\\d+)/ui"
}',
pole_mapping = '{
  "cislo_reklamace": "cislo",
  "datum_vyhotovenia": "datum_prodeje",
  "datum_podania": "datum_reklamace",
  "jmeno": "jmeno",
  "email": "email",
  "telefon": "telefon",
  "adresa": "ulice",
  "mesto": "mesto",
  "psc": "psc",
  "model": "model",
  "latka": "provedeni",
  "latka_barva": "barva",
  "zavada": "popis_problemu"
}'
WHERE zdroj = 'phase';

-- ✅ Kontrola
SELECT
  config_id,
  nazev,
  zdroj,
  'Patterns opraveny podle skutecneho formatu PDF' AS status
FROM wgs_pdf_parser_configs
WHERE zdroj IN ('natuzzi', 'phase');
