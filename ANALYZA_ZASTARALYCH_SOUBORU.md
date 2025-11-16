# 🧹 ANALÝZA ZASTARALÝCH SOUBORŮ - WGS PROJECT

**Datum:** 2025-11-16
**Celkem PHP souborů v root:** 64
**Doporučení:** Smazat 35+ zastaralých/duplicitních souborů

---

## ✅ PRODUCTION SOUBORY - PONECHAT (18 souborů)

**Hlavní stránky:**
- `index.php` - Homepage
- `onas.php` - O nás
- `nasesluzby.php` - Naše služby
- `mimozarucniceny.php` - Mimozáruční ceny
- `psa.php` - PSA
- `psa-kalkulator.php` - PSA kalkulačka
- `gdpr.php` - GDPR stránka
- `offline.php` - Offline fallback

**Core funkcionalita:**
- `init.php` - Bootstrap (KRITICKÝ!)
- `login.php` - Přihlášení
- `logout.php` - Odhlášení
- `registration.php` - Registrace
- `password_reset.php` - Reset hesla

**Aplikační stránky:**
- `admin.php` - Admin panel
- `novareklamace.php` - Nová reklamace
- `seznam.php` - Seznam reklamací
- `statistiky.php` - Statistiky
- `protokol.php` - Servisní protokol
- `photocustomer.php` - Fotky od zákazníků

**Monitoring:**
- `health.php` - Health check endpoint
- `analytics.php` - Analytika

---

## 🗑️ DIAGNOSTIC/DEBUG SOUBORY - SMAZAT (20 souborů)

**Diagnostické skripty (vytvořené během debugování):**
```
❌ check_admin_hash.php - Test admin hash
❌ check_all_control_files.php - Kontrola souborů
❌ check_hotfix_status.php - Kontrola hotfixů
❌ diagnose_geoapify.php - Diagnostika Geoapify API
❌ diagnose_system.php - Systémová diagnostika
❌ find_geoapify_key.php - Hledání API klíče
❌ find_syntax_error.php - Hledání syntax chyb
❌ system_check.php - Systémová kontrola
❌ validate_tools.php - Validace nástrojů
```

**Test skripty:**
```
❌ test_db_connection.php - Test DB připojení (zabezpečený, ale nepotřebný)
❌ test_tile_response.php - Test map tiles
❌ test_tile_simple.php - Test map tiles (simplified)
❌ pure_db_test.php - Pure DB test (zabezpečený, ale nepotřebný)
```

**Zjišťovací skripty:**
```
❌ zjisti_constants.php - Zobrazení PHP konstant
❌ zjisti_databazi.php - Zjištění DB info
❌ zjisti_env.php - Zobrazení .env
❌ zjisti_php_config.php - PHP konfigurace
❌ zjisti_strukturu.php - Struktura databáze
```

**Zobrazovací skripty:**
```
❌ show_file_content.php - Zobrazení obsahu souboru
⚠️ show_table_structure.php - Struktura tabulky (může zůstat, je zabezpečený)
⚠️ show_env.php - Zobrazení .env (může zůstat, je zabezpečený)
⚠️ db_struktura.php - Web interface pro DB strukturu (NOVÝ, ponechat)
❌ zobraz_skutecnou_strukturu.php - CLI verze (duplikát db_struktura.php)
```

**DOPORUČENÍ:** Smazat všechny ❌, ponechat ⚠️

---

## 🔧 SETUP/MIGRATION SOUBORY - SMAZAT PO POUŽITÍ (11 souborů)

**Database migrations (jednorázové):**
```
❌ add_indexes.php - Přidání indexů (už provedeno)
❌ oprav_chybejici_sloupce.php - Oprava sloupců (už provedeno)
❌ oprav_vse.php - One-click oprava (už provedeno)
❌ oprava_databaze_2025_11_16.php - Migrace z 16.11. (už provedeno)
❌ run_migration_simple.php - Spuštění migrace
❌ smaz_lock.php - Smazání lock souboru (pomocný, nepotřebný)
```

**Setup skripty:**
```
❌ create_env.php - Vytvoření .env (už provedeno)
❌ setup_env.php - Setup .env (už provedeno)
❌ aktualizuj_databazi.php - Aktualizace DB credentials (už provedeno)
❌ setup_actions_system.php - Setup systému akcí (už provedeno?)
```

**Optimization:**
```
❌ add_optimization_tasks.php - Přidání optimalizačních tasků (jednorázové)
```

**DOPORUČENÍ:** Tyto soubory byly potřeba jen **jednou při migraci**. Můžeš je **bezpečně smazat** protože změny jsou už v databázi.

---

## 🧹 CLEANUP SOUBORY - SMAZAT (5 souborů)

**Jednorázové cleanup skripty:**
```
❌ cleanup_failed_emails.php - Cleanup neúspěšných emailů
❌ cleanup_history_record.php - Cleanup historie
❌ cleanup_logs_and_backup.php - Cleanup logů a backupů
❌ quick_cleanup.php - Rychlý cleanup
❌ verify_and_cleanup.php - Verifikace a cleanup
```

**DOPORUČENÍ:** Tyto skripty byly potřeba **jednou**. Po použití je můžeš **smazat**.

---

## 🔨 HOTFIX SOUBORY - SMAZAT (2 soubory)

**Jednorázové hotfixy:**
```
❌ hotfix_csrf.php - CSRF hotfix (už opraveno v kódu)
❌ fix_visibility.php - Oprava viditelnosti (už opraveno)
```

**DOPORUČENÍ:** Hotfixy byly aplikovány, **můžeš smazat**.

---

## ⚙️ UTILITY SOUBORY - PONECHAT/ZVÁŽIT (8 souborů)

**Správa systému:**
```
✅ admin_key_manager.php - Správa admin klíčů (PONECHAT - užitečné)
✅ backup_system.php - Zálohování (PONECHAT - důležité)
⚠️ minify_assets.php - Minifikace CSS/JS (PONECHAT pokud používáš, jinak smazat)
⚠️ git_update.php - Git update (ZVÁŽIT - pokud nepoužíváš, smazat)
⚠️ update_and_install.php - Update a instalace (ZVÁŽIT)
```

**API soubory:**
```
❓ admin_api.php - Admin API (ZKONTROLOVAT - možný duplikát api/control_center_api.php)
```

**DOPORUČENÍ:**
- `admin_key_manager.php`, `backup_system.php` - **PONECHAT**
- `admin_api.php` - **ZKONTROLOVAT** jestli není duplikát
- Ostatní - **SMAZAT pokud nepoužíváš**

---

## 📊 CELKOVÉ SHRNUTÍ

| Kategorie | Počet | Doporučení |
|-----------|-------|------------|
| Production soubory | 18 | ✅ PONECHAT |
| Diagnostic/Debug | 20 | ❌ SMAZAT |
| Setup/Migration | 11 | ❌ SMAZAT (už provedeno) |
| Cleanup | 5 | ❌ SMAZAT (už provedeno) |
| Hotfix | 2 | ❌ SMAZAT (už aplikováno) |
| Utility | 8 | ⚠️ ZVÁŽIT |
| **CELKEM** | **64** | **38+ k smazání** |

---

## 🎯 DOPORUČENÝ POSTUP

### Krok 1: Smazat jednoznačně zbytečné (38 souborů)

```bash
# Diagnostic/Debug soubory
rm check_admin_hash.php check_all_control_files.php check_hotfix_status.php
rm diagnose_geoapify.php diagnose_system.php find_geoapify_key.php
rm find_syntax_error.php system_check.php validate_tools.php
rm test_db_connection.php test_tile_response.php test_tile_simple.php
rm pure_db_test.php
rm zjisti_constants.php zjisti_databazi.php zjisti_env.php
rm zjisti_php_config.php zjisti_strukturu.php
rm show_file_content.php zobraz_skutecnou_strukturu.php

# Setup/Migration soubory (už provedeno)
rm add_indexes.php oprav_chybejici_sloupce.php oprav_vse.php
rm oprava_databaze_2025_11_16.php run_migration_simple.php smaz_lock.php
rm create_env.php setup_env.php aktualizuj_databazi.php
rm setup_actions_system.php add_optimization_tasks.php

# Cleanup soubory
rm cleanup_failed_emails.php cleanup_history_record.php
rm cleanup_logs_and_backup.php quick_cleanup.php verify_and_cleanup.php

# Hotfix soubory
rm hotfix_csrf.php fix_visibility.php
```

### Krok 2: Zvážit utility soubory

**Zkontroluj jestli používáš:**
- `minify_assets.php` - Pokud ne, smazat
- `git_update.php` - Pokud ne, smazat
- `update_and_install.php` - Pokud ne, smazat

**Zkontroluj duplikát:**
- Je `admin_api.php` duplikát `api/control_center_api.php`? Pokud ano, smazat jeden.

### Krok 3: Ponechat diagnostic soubory (volitelné)

Pokud chceš mít diagnostic nástroje po ruce, **ponechat**:
- `show_env.php` - Zobrazení .env (zabezpečený)
- `show_table_structure.php` - Struktura tabulky (zabezpečený)
- `db_struktura.php` - Web interface pro DB (NOVÝ, užitečný)

Ostatní diagnostic soubory **smazat**.

---

## ⚠️ DŮLEŽITÉ UPOZORNĚNÍ

**PŘED SMAZÁNÍM:**
1. ✅ Udělej **git commit** aktuálního stavu
2. ✅ Vytvoř **backup** na production serveru
3. ✅ Zkontroluj že migrace byly úspěšně provedeny
4. ✅ Po smazání udělej **test** že vše funguje

**Po smazání můžeš vždy vrátit soubory z git historie!**

```bash
# Pokud něco potřebuješ vrátit
git checkout HEAD~1 -- nazev_souboru.php
```

---

## 📁 DOPORUČENÁ STRUKTURA PO CLEANUP

**Root adresář by měl obsahovat POUZE:**
- Production stránky (18 souborů)
- `init.php` (bootstrap)
- Max 2-3 utility soubory (admin_key_manager.php, backup_system.php)
- Max 2-3 diagnostic soubory (show_env.php, db_struktura.php)

**Celkem: ~23 souborů místo 64!**

---

**🎯 VÝSLEDEK: Čistší, přehlednější a profesionálnější projekt!**
