# CONTROL CENTER → ADMIN MIGRATION PLAN

**Datum zahájení:** 2025-11-17
**Engine:** Autonomní Refactoring & Security Engine
**Audit ID:** ATE-v5-20251117

---

## 📋 INVENTORY - SOUBORY K PŘEJMENOVÁNÍ

### Includes (13 souborů):
```
includes/control_center_actions.php              → includes/admin_actions.php
includes/control_center_appearance.php           → includes/admin_appearance.php
includes/control_center_configuration.php        → includes/admin_configuration.php
includes/control_center_console.php              → includes/admin_console.php
includes/control_center_content.php              → includes/admin_content.php
includes/control_center_diagnostics.php          → includes/admin_diagnostics.php
includes/control_center_email_sms.php            → includes/admin_email_sms.php
includes/control_center_main.php                 → includes/admin_main.php
includes/control_center_security.php             → includes/admin_security.php
includes/control_center_testing.php              → includes/admin_testing.php
includes/control_center_testing_interactive.php  → includes/admin_testing_interactive.php
includes/control_center_testing_simulator.php    → includes/admin_testing_simulator.php
includes/control_center_tools.php                → includes/admin_tools.php
```

### JavaScript (1 soubor k odstranění):
```
assets/js/control-center-modal.js  → PŘESUNOUT do backups/ (nepoužitý)
```

### Setup soubory:
```
setup/install_admin_control_center.php    → setup/install_admin_dashboard.php
setup/migration_admin_control_center.sql  → setup/migration_admin_dashboard.sql
```

### Legacy (již archivované):
```
api/legacy/control_center_api.php.archive  → PONECHAT (již v archivu)
```

---

## 🔄 SOUBORY S REFERENCEMI (k aktualizaci)

Soubory obsahující odkazy na Control Center v kódu:
- admin.php
- index.php
- assets/js/admin.js
- assets/css/admin.css
- assets/css/admin.min.css

---

## 🎯 KRITICKÉ OPRAVY (z auditu)

### ❌ CRITICAL:
1. Chybějící API akce: list_keys, list_users, list_reklamace
2. 3 modaly bez sandbox atributů (XSS riziko)
3. 45× innerHTML bez sanitizace

### ⚠️ HIGH:
4. Inline onclick handlers (CSP porušení)
5. Nepoužitý control-center-modal.js
6. Rate limiter bez fallback
7. Chybějící client error logging

---

## ✅ POSTUP

### Fáze 1: Přejmenování souborů
- Vytvořit zálohy
- Přejmenovat includes/
- Přejmenovat setup/
- Přesunout nepoužité soubory

### Fáze 2: Refaktoring kódu
- Aktualizovat include/require cesty
- Změnit CSS třídy (.cc-* → .admin-*)
- Změnit JS funkce (loadControlCenter → loadAdmin)
- Aktualizovat HTML atributy

### Fáze 3: Bezpečnostní opravy
- Přidat chybějící API akce
- Přidat sandbox atributy
- Refaktorovat innerHTML
- Odstranit inline onclick

### Fáze 4: Testování
- E2E test (10 scénářů)
- CSP audit
- XSS audit
- API audit

### Fáze 5: Dokumentace
- SECURITY_REPORT.md
- CHANGELOG.md
- Updated CLAUDE.md

---

**Status:** 🟢 READY TO START
