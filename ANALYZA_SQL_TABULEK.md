# 🗄️ ANALÝZA SQL TABULEK - WGS DATABASE

**Datum:** 2025-11-16
**Databáze:** wgs-servicecz01
**Celkem tabulek:** 41
**Celkem záznamů:** 942
**Velikost:** 3.38 MB

---

## ✅ PRODUCTION TABULKY - PONECHAT (16 tabulek)

**Hlavní aplikační tabulky:**
```
wgs_reklamace (3 záznamy, 304 KB) - HLAVNÍ TABULKA
wgs_photos (62 záznamy, 128 KB) - Fotky k reklamacím
wgs_users (2 záznamy, 144 KB) - Uživatelé systému
wgs_registration_keys (4 záznamy, 80 KB) - Registrační klíče
wgs_technici (2 záznamy, 16 KB) - Technici
```

**Notifikace a emailing:**
```
wgs_notifications (6 záznamy, 48 KB) - Notifikační šablony
wgs_email_queue (6 záznamy, 128 KB) - Fronta emailů
wgs_smtp_settings (3 záznamy, 16 KB) - SMTP nastavení
```

**Systémové tabulky:**
```
wgs_settings (6 záznamy, 48 KB) - Obecná nastavení
wgs_system_config (7 záznamy, 64 KB) - Systémová konfigurace
wgs_theme_settings (4 záznamy, 80 KB) - Nastavení vzhledu
wgs_rate_limits (19 záznamy, 48 KB) - Rate limiting
wgs_tokens (33 záznamy, 64 KB) - Autentizační tokeny
```

**Správa akcí:**
```
wgs_pending_actions (16 záznamy, 80 KB) - Čekající akce
wgs_action_history (37 záznamy, 80 KB) - Historie akcí
```

**Analytika (používaná):**
```
wgs_analytics_events (1 záznam, 64 KB) - Analytické eventy
```

**CELKEM: 16 aktivních tabulek | 209 záznamů | ~1.4 MB**

---

## ❌ DUPLICITNÍ TABULKY - SMAZAT (2 tabulky)

### 1. `registration_keys` (2 záznamy, 64 KB)
**Důvod smazání:** Duplicita tabulky `wgs_registration_keys`

**Porovnání:**
- `registration_keys`: 2 záznamy (PRT2025BF2A19EF, TCH2025BFDA9E2C)
- `wgs_registration_keys`: 4 záznamy (obsahuje stejné + nové)

**Akce:** `DROP TABLE registration_keys;`

---

### 2. `users` (2 záznamy, 48 KB)
**Důvod smazání:** Duplicita tabulky `wgs_users`

**Porovnání:**
- `users`: 2 záznamy (admin, admin@wgs-service.cz)
- `wgs_users`: 2 záznamy (ADMIN001, PRT20250001) - nová struktura

**Akce:** `DROP TABLE users;`

---

## 🗑️ PRÁZDNÉ NEPOUŽÍVANÉ WGS TABULKY - SMAZAT (9 tabulek)

### 1. `wgs_analytics_visits` (0 záznamů, 64 KB)
**Důvod:** Návštěvy se nesledují, tabulka není používána
```sql
DROP TABLE wgs_analytics_visits;
```

### 2. `wgs_audit_log` (0 záznamů, 64 KB)
**Důvod:** Audit log není aktivní, nepoužívá se
```sql
DROP TABLE wgs_audit_log;
```

### 3. `wgs_claims` (0 záznamů, 128 KB)
**Důvod:** Celý "claims" system není implementovaný (používá se wgs_reklamace)
```sql
DROP TABLE wgs_claims;
```

### 4. `wgs_content_texts` (0 záznamů, 64 KB)
**Důvod:** Editovatelné texty stránek nejsou používány
```sql
DROP TABLE wgs_content_texts;
```

### 5. `wgs_documents` (0 záznamů, 32 KB)
**Důvod:** Upload dokumentů není implementován (používá se wgs_photos)
```sql
DROP TABLE wgs_documents;
```

### 6. `wgs_github_webhooks` (0 záznamů, 64 KB)
**Důvod:** GitHub webhooks nejsou používány
```sql
DROP TABLE wgs_github_webhooks;
```

### 7. `wgs_notes` (0 záznamů, 48 KB)
**Důvod:** Poznámky k reklamacím nejsou implementovány
```sql
DROP TABLE wgs_notes;
```

### 8. `wgs_provize_technici` (0 záznamů, 0 KB)
**Důvod:** Provize techniků se nepočítají (tabulka je prázdná a špatně strukturovaná)
```sql
DROP TABLE wgs_provize_technici;
```

### 9. `wgs_sessions` (0 záznamů, 64 KB)
**Důvod:** Custom session storage není použitý (používají se PHP sessions)
```sql
DROP TABLE wgs_sessions;
```

**CELKEM: 9 prázdných tabulek | 0 záznamů | ~592 KB**

---

## 🌐 WORDPRESS TABULKY - SMAZAT VŠE (13 tabulek)

**Důvod:** Starý WordPress web už neběží, tabulky jsou nepoužité zbytky.

### Tabulky k smazání:

1. `wp_commentmeta` (0 záznamů, 48 KB)
2. `wp_comments` (0 záznamů, 96 KB)
3. `wp_e_events` (7 záznamů, 32 KB) - Elementor eventy
4. `wp_links` (0 záznamů, 32 KB)
5. `wp_options` (327 záznamů, 336 KB) - WordPress nastavení
6. `wp_postmeta` (308 záznamů, 400 KB) - Metadata postů
7. `wp_posts` (45 záznamů, 144 KB) - Staré Elementor stránky
8. `wp_term_relationships` (14 záznamů, 32 KB)
9. `wp_term_taxonomy` (6 záznamů, 48 KB)
10. `wp_termmeta` (0 záznamů, 48 KB)
11. `wp_terms` (6 záznamů, 48 KB)
12. `wp_usermeta` (7 záznamů, 48 KB)
13. `wp_users` (2 záznamy, 64 KB) - Staří WP uživatelé

**CELKEM: 13 WordPress tabulek | 722 záznamů | ~1.4 MB**

```sql
DROP TABLE wp_commentmeta;
DROP TABLE wp_comments;
DROP TABLE wp_e_events;
DROP TABLE wp_links;
DROP TABLE wp_options;
DROP TABLE wp_postmeta;
DROP TABLE wp_posts;
DROP TABLE wp_term_relationships;
DROP TABLE wp_term_taxonomy;
DROP TABLE wp_termmeta;
DROP TABLE wp_terms;
DROP TABLE wp_usermeta;
DROP TABLE wp_users;
```

---

## ⚠️ ZVÁŽIT - MOŽNÁ DUPLICITA (1 tabulka)

### `notification_templates` (5 záznamů, 48 KB)

**Porovnání s `wgs_notifications`:**
- Obě tabulky mají **stejnou strukturu**
- Obě obsahují notifikační šablony
- `notification_templates`: 5 záznamů
- `wgs_notifications`: 6 záznamů

**Struktura je téměř identická:**
```
notification_templates: id, name, description, trigger_event, type, recipient_type...
wgs_notifications:      id, name, description, trigger_event, type, recipient_type...
```

**DOPORUČENÍ:**
1. Zkontrolovat jestli aplikace používá `notification_templates` nebo `wgs_notifications`
2. Pokud jen jednu, druhou SMAZAT
3. Pokud obě, **sloučit** do jedné

**Dočasně: PONECHAT** dokud se neověří použití v kódu

---

## 📊 CELKOVÉ SHRNUTÍ

| Kategorie | Počet tabulek | Záznamů | Velikost | Doporučení |
|-----------|---------------|---------|----------|------------|
| **Production WGS** | 16 | 209 | ~1.4 MB | ✅ PONECHAT |
| **Duplicitní** | 2 | 4 | 112 KB | ❌ SMAZAT |
| **Prázdné WGS** | 9 | 0 | 592 KB | ❌ SMAZAT |
| **WordPress** | 13 | 722 | ~1.4 MB | ❌ SMAZAT |
| **Zvážit** | 1 | 5 | 48 KB | ⚠️ ZKONTROLOVAT |
| **CELKEM** | **41** | **940** | **~3.5 MB** | - |

**→ K SMAZÁNÍ: 24 tabulek (59% databáze!)**

---

## 🎯 VÝSLEDEK PO CLEANUP

**Před:**
- 41 tabulek
- 942 záznamů
- 3.38 MB

**Po:**
- **17 tabulek** (pokud sloučíme notification_templates)
- **214 záznamů** (active data only)
- **~1.5 MB** (56% úspora místa!)

**Výhody:**
- ✅ Rychlejší zálohy
- ✅ Rychlejší queries
- ✅ Přehlednější databáze
- ✅ Méně confusion s duplicitami
- ✅ Snadnější maintenance

---

## 🛠️ SQL SKRIPTY PRO CLEANUP

### Krok 1: BACKUP PŘED SMAZÁNÍM
```sql
-- Vytvoř backup celé databáze PŘED jakýmkoliv smazáním!
-- Přes phpMyAdmin: Export > Custom > All tables > Go
```

### Krok 2: Smazat duplicitní tabulky
```sql
-- DUPLICITNÍ TABULKY
DROP TABLE IF EXISTS registration_keys;
DROP TABLE IF EXISTS users;
```

### Krok 3: Smazat prázdné WGS tabulky
```sql
-- PRÁZDNÉ NEPOUŽÍVANÉ WGS TABULKY
DROP TABLE IF EXISTS wgs_analytics_visits;
DROP TABLE IF EXISTS wgs_audit_log;
DROP TABLE IF EXISTS wgs_claims;
DROP TABLE IF EXISTS wgs_content_texts;
DROP TABLE IF EXISTS wgs_documents;
DROP TABLE IF EXISTS wgs_github_webhooks;
DROP TABLE IF EXISTS wgs_notes;
DROP TABLE IF EXISTS wgs_provize_technici;
DROP TABLE IF EXISTS wgs_sessions;
```

### Krok 4: Smazat WordPress tabulky
```sql
-- VŠECHNY WORDPRESS TABULKY (13 tabulek)
DROP TABLE IF EXISTS wp_commentmeta;
DROP TABLE IF EXISTS wp_comments;
DROP TABLE IF EXISTS wp_e_events;
DROP TABLE IF EXISTS wp_links;
DROP TABLE IF EXISTS wp_options;
DROP TABLE IF EXISTS wp_postmeta;
DROP TABLE IF EXISTS wp_posts;
DROP TABLE IF EXISTS wp_term_relationships;
DROP TABLE IF EXISTS wp_term_taxonomy;
DROP TABLE IF EXISTS wp_termmeta;
DROP TABLE IF EXISTS wp_terms;
DROP TABLE IF EXISTS wp_usermeta;
DROP TABLE IF EXISTS wp_users;
```

### Krok 5: Zkontrolovat notification_templates
```sql
-- ZKONTROLOVAT POUŽITÍ V KÓDU
-- Pokud se používá jen wgs_notifications, pak:
DROP TABLE IF EXISTS notification_templates;
```

---

## 🔍 JAK ZKONTROLOVAT notification_templates

**V kódu hledej:**
```bash
# Hledej použití notification_templates
grep -r "notification_templates" /home/user/moje-stranky/*.php
grep -r "notification_templates" /home/user/moje-stranky/api/*.php
grep -r "notification_templates" /home/user/moje-stranky/app/*.php

# Hledej použití wgs_notifications
grep -r "wgs_notifications" /home/user/moje-stranky/*.php
```

**Pokud:**
- Jen `notification_templates` se používá → přejmenuj na `wgs_notifications`
- Jen `wgs_notifications` se používá → smaž `notification_templates`
- Obě se používají → sloučit data, ponechat `wgs_notifications`

---

## ⚠️ DŮLEŽITÁ BEZPEČNOSTNÍ OPATŘENÍ

**PŘED SMAZÁNÍM:**

1. ✅ **BACKUP!** Stáhni celou databázi přes phpMyAdmin
2. ✅ **Test lokálně** - Pokud máš local copy, otestuj tam
3. ✅ **Večerní čas** - Udělej to v době minimálního provozu
4. ✅ **Postupně** - Smaž po částech, ne všechno najednou
5. ✅ **Monitoruj** - Po smazání sleduj logy jestli se něco nerozbilo

**PO SMAZÁNÍ:**

1. ✅ **Test aplikace** - Projdi všechny hlavní funkce
2. ✅ **Zkontroluj logy** - Sleduj errory
3. ✅ **Ponechej backup** alespoň týden

**RECOVERY (pokud se něco pokazí):**
```sql
-- Vrátit z backupu přes phpMyAdmin:
-- Import > Choose file > backup.sql > Go
```

---

## 📝 DOPORUČENÝ POSTUP

### Fáze 1: Bezpečné tabulky (5 min)
1. Udělej **úplný backup** databáze
2. Smaž **WordPress tabulky** (13 tabulek)
3. **Test** že web funguje

### Fáze 2: Duplicity (2 min)
1. Smaž **duplicitní tabulky** (registration_keys, users)
2. **Test** registrace a přihlášení

### Fáze 3: Prázdné tabulky (2 min)
1. Smaž **prázdné WGS tabulky** (9 tabulek)
2. **Test** všechny hlavní funkce

### Fáze 4: Kontrola (10 min)
1. **Zkontroluj** notification_templates použití
2. **Rozhodni** jestli smazat nebo sloučit

**CELKOVÝ ČAS: ~20 minut**

---

## 🎯 VÝSLEDEK

**Z 41 tabulek na 17 tabulek**
**Z 3.38 MB na ~1.5 MB**
**Smazáno 24 zbytečných tabulek**

**Čistší, rychlejší, profesionálnější databáze!** 🎉
