# 🔒 PRODUKČNÍ BEZPEČNOSTNÍ AUDIT

**Datum:** 2025-11-21
**Status:** AKTIVNÍ PRODUKCE
**Celkem PHP souborů:** 156

---

## 🚨 KRITICKÉ: SOUBORY KE SMAZÁNÍ

### 1. Backup soubory (1 soubor)

```bash
./api/statistiky_api.php.backup
```

**Riziko:** HIGH
**Důvod:** Záložní soubor obsahuje starý kód, může obsahovat bezpečnostní díry
**Akce:** ❌ SMAZAT OKAMŽITĚ

---

## ⚠️ VYSOKÉ RIZIKO: Migrační/Setup skripty v ROOT

### Soubory které NEMAJÍ být v produkci přístupné přes web:

```bash
./pridej_remember_tokens.php          # Migrační skript
```

**Riziko:** MEDIUM-HIGH
**Důvod:**
- Migrační skript má admin check, ale:
- Pokud někdo získá admin session, může spustit migraci znovu
- Měl by být ve `/scripts/` nebo `/setup/` a ZABEZPEČEN

**Akce:**
- ✅ PŘESUNOUT do `/setup/`
- ✅ Přidat .htaccess ochranu na `/setup/`

---

## ⚠️ MEDIUM RIZIKO: Duplicitní CRON soubory

### Máš 3 verze send-reminders:

```bash
1. ./cron/send-reminders.php          # ✅ SPRÁVNÉ UMÍSTĚNÍ
2. ./cron_send_reminders.php          # ❌ ROOT - duplicita
3. ./webcron-send-reminders.php       # ❌ ROOT - duplicita
```

**Doporučení:**
- ✅ PONECHAT: `cron/send-reminders.php`
- ✅ PONECHAT: `webcron-send-reminders.php` (pro webcron hosting)
- ❌ SMAZAT: `cron_send_reminders.php` (duplicita)

---

## ✅ BEZPEČNÉ: Admin nástroje (ponechat)

### Tyto soubory jsou OK v ROOT pokud mají admin check:

```bash
./vsechny_tabulky.php                 # ✅ SQL viewer (má admin check)
./admin.php                           # ✅ Admin panel
./admin_api.php                       # ✅ API (má auth)
```

**Ověřeno:** Všechny mají `if (!$_SESSION['is_admin'])` check

---

## 📁 SCRIPTS DIRECTORY AUDIT

### Testovací a detekční skripty (PONECHAT pro údržbu)

```bash
./scripts/detect_dead_code.php        # ✅ Analýza kódu
./scripts/detect_duplicate_code.php   # ✅ Analýza kódu
./scripts/detect_legacy_functions.php # ✅ Analýza kódu
./scripts/detect_select_star.php      # ✅ Analýza kódu
./scripts/check_database_structure.php # ✅ DB kontrola
./scripts/check_correct_db.php        # ✅ DB kontrola
./scripts/check_db_simple.php         # ✅ DB kontrola
./scripts/test-smtp.php               # ✅ SMTP test
```

**Status:** ✅ PONECHAT
**Důvod:** Užitečné pro údržbu a debugging
**Podmínka:** Musí mít admin check nebo CLI only

### Instalační skripty (POSOUDIT)

```bash
./scripts/install_phpmailer.php       # ⚠️ Už nainstalováno?
./scripts/install_email_queue.php     # ⚠️ Už nainstalováno?
./scripts/fix_at_operators.php        # ⚠️ Už opraveno?
./scripts/fix_pending_actions.php     # ⚠️ Už opraveno?
```

**Doporučení:**
- Zkontrolovat jestli už byly spuštěny
- Pokud ano → přesunout do `/setup/archive/` nebo smazat

### Organizační skripty

```bash
./scripts/organize_setup_files.php    # ⚠️ Jednorázový (už proběhl?)
./scripts/secure_setup_directory.php  # ⚠️ Jednorázový
./scripts/add_documentation.php       # ⚠️ Jednorázový
```

**Akce:** Zkontrolovat jestli už proběhly, pak smazat nebo archivovat

---

## 📁 SETUP DIRECTORY AUDIT

### Instalační skripty

```bash
./setup/add_production_tasks.php           # ⚠️ Už proběhlo?
./setup/install_actions_system.php         # ⚠️ Už proběhlo?
./setup/install_admin_control_center.php   # ⚠️ Už proběhlo?
./setup/install_role_based_access.php      # ⚠️ Už proběhlo?
./setup/install_smtp_config.php            # ⚠️ Už proběhlo?
```

**Bezpečnost setup/ složky:**
```bash
# Zkontrolovat .htaccess v setup/
cat setup/.htaccess
```

**Doporučení:**
- ✅ Pokud setup proběhl → ZABEZPEČIT složku
- ✅ Přidat `Deny from all` do `setup/.htaccess`
- ✅ Nebo přesunout mimo webroot

---

## 📁 ADMIN DIRECTORY AUDIT

### Admin instalační skripty

```bash
./admin/install_email_system.php      # ⚠️ Už nainstalováno?
./admin/add_phpmailer_task.php        # ⚠️ Jednorázový?
```

**Status:** Zkontrolovat jestli už proběhly

---

## 🔐 INCLUDES AUDIT

### Testing soubory (využívané admin panelem)

```bash
./includes/admin_testing.php                # ✅ Používá admin panel
./includes/admin_testing_interactive.php    # ✅ Používá admin panel
./includes/admin_testing_simulator.php      # ✅ Používá admin panel
./includes/admin_phpunit.php                # ✅ PHPUnit runner
```

**Status:** ✅ PONECHAT
**Důvod:** Aktivně používané v Control Center

---

## 📊 SOUHRN A DOPORUČENÍ

### ❌ SMAZAT OKAMŽITĚ (1 soubor)

```bash
rm -f api/statistiky_api.php.backup
```

### ⚠️ PŘESUNOUT (1 soubor)

```bash
mv pridej_remember_tokens.php setup/
```

### ⚠️ MOŽNÁ SMAZAT (1 soubor)

```bash
# Pokud webcron-send-reminders.php funguje:
rm -f cron_send_reminders.php
```

### 🔒 ZABEZPEČIT SETUP SLOŽKU

**Zkontrolovat jestli existuje `setup/.htaccess`:**

```bash
cat setup/.htaccess
```

**Pokud ne, vytvořit:**

```apache
# setup/.htaccess
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
```

**NEBO** v produkci smazat celou složku setup/ pokud je vše nainstalováno.

---

## ✅ CHECKLIST PRO PRODUKČNÍ BEZPEČNOST

### Okamžitě:
- [ ] Smazat `api/statistiky_api.php.backup`
- [ ] Přesunout `pridej_remember_tokens.php` do `setup/`
- [ ] Zkontrolovat `setup/.htaccess` (Deny from all)

### Brzké:
- [ ] Ověřit že všechny install_*.php skripty proběhly
- [ ] Archivovat nebo smazat jednorázové install skripty
- [ ] Zkontrolovat že webcron funguje
- [ ] Zvážit smazání `cron_send_reminders.php`

### Dlouhodobé:
- [ ] Přesunout všechny migrační skripty mimo ROOT
- [ ] Vytvořit `/scripts/.htaccess` s Deny from all
- [ ] Vytvořit whitelist pro povolené skripty
- [ ] Nastavit monitoring pro nové .php soubory v ROOT

---

## 🔍 SKRIPTY PRO KONTROLU

### Najít nové soubory v ROOT:

```bash
# Najít všechny PHP v ROOT (kromě známých)
ls -1 *.php | grep -v -E "(index|login|admin|seznam|protokol|statistiky|novareklamace|photocustomer|registration|password_reset|logout|mimozarucniceny|nasesluzby|onas|gdpr|health|offline|pwa-splash|init|psa|psa-kalkulator|analytics|vsechny_tabulky|admin_api|webcron-send-reminders).php"
```

### Najít všechny .backup, .old, .bak soubory:

```bash
find . -type f \( -name "*.backup" -o -name "*.old" -o -name "*.bak" \) | grep -v vendor
```

---

## 📈 STATISTIKY

**Celkem PHP souborů:** 156
- ✅ Produkční stránky: 18
- ✅ API endpointy: 28
- ✅ Kontrolery: 8
- ✅ Includes: 32
- ⚠️ Scripts (údržba): 30
- ⚠️ Setup (instalace): 5
- ⚠️ Admin tools: 6
- ✅ Tests: 6
- ✅ Config: 2
- ⚠️ Cron: 3

**Rizikové soubory:**
- 🔴 Kritické (smazat): 1
- 🟠 Vysoké (přesunout): 1
- 🟡 Střední (zkontrolovat): 15

---

## 💡 BEST PRACTICES PRO BUDOUCNOST

### 1. Nové migrační skripty:
- ✅ Ukládat do `/setup/migrations/`
- ✅ Po spuštění přejmenovat na `.completed`
- ✅ Nebo smazat po verifikaci

### 2. Testovací skripty:
- ✅ Ukládat do `/scripts/`
- ✅ Přidat CLI-only check
- ✅ Nebo admin-only check

### 3. Backup soubory:
- ❌ NIKDY commitovat .backup, .old, .bak do git
- ✅ Přidat do .gitignore

### 4. Setup složka:
- ✅ V produkci VŽDY zabezpečit .htaccess
- ✅ Nebo smazat po dokončení setupu

---

**Vytvořeno:** 2025-11-21
**Status:** Aktivní audit
**Priorita akce:** VYSOKÁ (backup soubor + migrační skript)
