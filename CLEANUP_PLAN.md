# 🧹 PLÁN ÚKLIDU PROJEKTU

**Datum:** 2025-11-20
**Session:** claude/test-pdf-parsing-01M1zjcPLu3Jbtby8AdCfTNa

---

## ✅ PŘED ÚKLIDEM - POVINNÉ!

**NEJDŘÍV SPUSŤ MIGRACI:**
```
https://www.wgs-service.cz/finalni_kompletni_oprava.php?execute=1
```

**Pak otestuj:**
```
https://www.wgs-service.cz/test_pdf_parsing.php
```

**AŽ KDYŽ FUNGUJE → pak smaž soubory níže!**

---

## 🗑️ SOUBORY KE SMAZÁNÍ (Testovací/Zastaralé)

### Testovací Mapping Nástroje:
```bash
rm vizualni_mapping_pdf.php           # Starý pokus
rm vizualni_mapping_v2.php            # Starý pokus
rm jednodussi_mapping.php             # Testovací nástroj
rm analyzuj_pdf_strukturu.php         # Pouze pro analýzu
```

### Staré Migrační Skripty:
```bash
rm finalni_oprava_pdf_parseru.php     # Nahrazeno finalni_kompletni_oprava.php
rm finalni_oprava_mapovani.php        # Starý skript
rm finalni_oprava_ulice.php           # Starý skript
rm oprav_detekce_patterns.php        # Použito
rm oprav_natuzzi_mapping.php         # Použito
rm oprav_patterns_finalne.php        # Použito
rm oprav_phase_mapping.php           # Použito
rm oprav_prioritu_phase_sk.php       # Použito
rm oprav_ulici_pattern.php           # Použito
rm oprav_univerzalni_patterns.php    # Použito
rm rychla_oprava_mapovani.php        # Použito
rm pridej_pdf_parser_configs.php     # Použito
rm pridej_phase_cz.php                # Použito (pokud existuje)
```

### SQL Skripty (Použité):
```bash
rm aplikuj_phase_patterns.sql        # Použito v migraci
rm fix_patterns_podle_pdf.sql        # Použito
rm oprav_ulici_pattern.sql           # Použito
rm SQL_FINALNI_PATTERNS.sql          # Nahrazeno finalni_kompletni_oprava.php
```

### Testovací Skripty:
```bash
rm test_pdf_extrakce.php             # Testovací
```

### Zastaralá Dokumentace:
```bash
rm SHRNUTI_IMPLEMENTACE_PDF.md       # Zastaralé (máme nové řešení)
rm SHRNUTI_OPRAVY_PDF_PARSERU.md     # Zastaralé (máme finální)
rm NAVOD_VIZUALNI_MAPPING.md         # Pro vizuální tool (který mažeme)
```

---

## ✅ PONECHAT (Užitečné)

### Funkční Nástroje:
```
✅ pdf_kopiruj_vloz.php              # FINÁLNÍ FUNKČNÍ TOOL!
✅ test_pdf_parsing.php              # Pro budoucí testování
✅ diagnostika_pdf_parseru.php       # Diagnostický nástroj
```

### Systémové Soubory:
```
✅ automaticka_oprava_diagnostiky.php  # Může být užitečné
✅ automaticka_oprava_session.php      # Může být užitečné
✅ kontrola_a_oprava_claim_id.php      # Systémový nástroj
✅ vycisti_testovaci_emaily.php        # Systémový nástroj
```

### Dokumentace:
```
✅ CRON_NAVOD.md                     # Užitečný návod
✅ NAVOD_WEBCRON.md                  # Užitečný návod
✅ CLAUDE.md                         # Hlavní dokumentace projektu!
```

### SQL DDL:
```
✅ FINAL_DDL_wgs_reklamace.sql       # Struktura tabulky
✅ SPRAVNY_INSERT_wgs_reklamace.sql  # Referenční INSERT
✅ migrace_email_worker.sql          # Migrace
```

---

## ⚠️ POUŽÍT A PAK SMAZAT

### Migrační Skript:
```bash
# 1. POUŽIJ TENTO SKRIPT:
https://www.wgs-service.cz/finalni_kompletni_oprava.php?execute=1

# 2. OTESTUJ:
https://www.wgs-service.cz/test_pdf_parsing.php

# 3. AŽ FUNGUJE → SMAŽ:
rm finalni_kompletni_oprava.php
```

---

## 📝 PŘÍKAZY PRO ÚKLID

### Krok 1: Smazat Testovací Mapping Nástroje
```bash
cd /home/user/moje-stranky
rm vizualni_mapping_php.php vizualni_mapping_v2.php jednodussi_mapping.php analyzuj_pdf_strukturu.php
```

### Krok 2: Smazat Staré Migrační Skripty
```bash
rm finalni_oprava_pdf_parseru.php finalni_oprava_mapovani.php finalni_oprava_ulice.php \
   oprav_detekce_patterns.php oprav_natuzzi_mapping.php oprav_patterns_finalne.php \
   oprav_phase_mapping.php oprav_prioritu_phase_sk.php oprav_ulici_pattern.php \
   oprav_univerzalni_patterns.php rychla_oprava_mapovani.php pridej_pdf_parser_configs.php
```

### Krok 3: Smazat SQL Skripty
```bash
rm aplikuj_phase_patterns.sql fix_patterns_podle_pdf.sql oprav_ulici_pattern.sql SQL_FINALNI_PATTERNS.sql
```

### Krok 4: Smazat Testovací a Zastaralou Dokumentaci
```bash
rm test_pdf_extrakce.php SHRNUTI_IMPLEMENTACE_PDF.md SHRNUTI_OPRAVY_PDF_PARSERU.md NAVOD_VIZUALNI_MAPPING.md
```

### Krok 5: Po Úspěšné Migraci - Smazat Migrační Skript
```bash
# AŽ KDYŽ PATTERNS FUNGUJÍ V DATABÁZI!
rm finalni_kompletni_oprava.php
```

---

## ✅ PO ÚKLIDU ZŮSTANOU:

**Funkční Nástroje:**
- `pdf_kopiruj_vloz.php` - Hlavní nástroj pro mapping
- `test_pdf_parsing.php` - Pro testování parsování
- `diagnostika_pdf_parseru.php` - Diagnostika

**API:**
- `api/parse_povereni_pdf.php` - Hlavní parser API
- `api/uloz_pdf_mapping.php` - API pro uložení mappingu

**Dokumentace:**
- `CLAUDE.md` - Hlavní dokumentace
- `CRON_NAVOD.md`, `NAVOD_WEBCRON.md` - Užitečné návody

**Systémové:**
- Různé systémové utility a SQL DDL soubory

---

## 🎯 CELKOVÝ PŘÍKAZ (ALL-IN-ONE)

**⚠️ POUŽIJ AŽ PO ÚSPĚŠNÉ MIGRACI A TESTOVÁNÍ!**

```bash
cd /home/user/moje-stranky

# Smazat vše najednou
rm -f \
  vizualni_mapping_pdf.php \
  vizualni_mapping_v2.php \
  jednodussi_mapping.php \
  analyzuj_pdf_strukturu.php \
  finalni_oprava_pdf_parseru.php \
  finalni_oprava_mapovani.php \
  finalni_oprava_ulice.php \
  oprav_detekce_patterns.php \
  oprav_natuzzi_mapping.php \
  oprav_patterns_finalne.php \
  oprav_phase_mapping.php \
  oprav_prioritu_phase_sk.php \
  oprav_ulici_pattern.php \
  oprav_univerzalni_patterns.php \
  rychla_oprava_mapovani.php \
  pridej_pdf_parser_configs.php \
  aplikuj_phase_patterns.sql \
  fix_patterns_podle_pdf.sql \
  oprav_ulici_pattern.sql \
  SQL_FINALNI_PATTERNS.sql \
  test_pdf_extrakce.php \
  SHRNUTI_IMPLEMENTACE_PDF.md \
  SHRNUTI_OPRAVY_PDF_PARSERU.md \
  NAVOD_VIZUALNI_MAPPING.md \
  finalni_kompletni_oprava.php

echo "✅ Úklid dokončen! Zkontroluj že vše funguje."
```

---

## 📊 STATISTIKY

**Před úklidem:**
- ~40+ testovacích/migračních souborů

**Po úklidu:**
- ~15-20 užitečných souborů
- Úspora: ~20-25 souborů

**Velikost:** Uvolní se několik MB místa

---

**⚠️ DŮLEŽITÉ:**
1. **NEJDŘÍV** spusť migraci!
2. **OTESTUJ** že patterns fungují!
3. **AŽ PAK** smaž soubory!

---

**Vytvořeno:** 2025-11-20
**Session:** claude/test-pdf-parsing-01M1zjcPLu3Jbtby8AdCfTNa
