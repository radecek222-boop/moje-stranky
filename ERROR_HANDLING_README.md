# 🔴 WGS Advanced Error Handling System

## 📋 Přehled

Tento systém zachytává **všechny chyby** (PHP i JavaScript) a zobrazuje je s **detailními informacemi** pro rychlé debugging. Když se vyskytne chyba, můžete ji jedním kliknutím zkopírovat a poslat vývojáři.

**Integrace s Control Center v3.0:**
- Error handler je automaticky aktivní v celém **Admin Control Center**
- Všechny chyby v Control Center sekcích jsou zachyceny
- Testovací prostředí (Sekce 12) také reportuje chyby s copy buttonem
- Diagnostika (Sekce 10) zobrazuje error logy

## ✨ Co systém dělá

### PHP Chyby (Backend)
- ✅ Zachytává všechny PHP errors, warnings, notices
- ✅ Zachytává exceptions a fatal errors
- ✅ Zobrazuje **přesné umístění** (soubor + řádek)
- ✅ Ukazuje **stack trace** (posloupnost volání funkcí)
- ✅ Loguje do `logs/php_errors.log`
- ✅ Pro AJAX requesty vrací JSON s detaily

### JavaScript Chyby (Frontend)
- ✅ Zachytává všechny JS errors
- ✅ Zachytává unhandled promise rejections
- ✅ Zobrazuje chyby v pravém dolním rohu
- ✅ Loguje do `logs/js_errors.log`
- ✅ Zobrazuje stack trace

## 🎯 Jak to vypadá

### PHP Chyba:
```
┌─────────────────────────────────────────┐
│ 🔴 WARNING                              │
│ WGS Debug Mode - Detailní informace    │
└─────────────────────────────────────────┘

📋 CHYBOVÁ ZPRÁVA:
Undefined variable $userId

📍 UMÍSTĚNÍ:
Soubor: /path/to/file.php
Řádek: 123

📚 STACK TRACE:
#0 loadUsers() at file.php:123
#1 handleRequest() at controller.php:45
...

🌐 REQUEST INFO:
URL: /admin.php?tab=users
Method: GET
IP: 192.168.1.100

[Kopírovat pro Claude Code nebo Codex]
```

### JavaScript Chyba:
```
┌─────────────────────────┐
│ 🔴 JavaScript Error  [×]│
├─────────────────────────┤
│ 📋 ZPRÁVA:              │
│ Cannot read property    │
│ 'length' of undefined   │
│                         │
│ 📍 SOUBOR:              │
│ main.js                 │
│                         │
│ 📍 ŘÁDEK:               │
│ 256:12                  │
│                         │
│ [Kopírovat pro Claude]  │
└─────────────────────────┘
```

## 🚀 Jak použít

### 1. Když se vyskytne chyba:

1. **PHP chyba** → Zobrazí se celostránková obrazovka s detaily
2. **JS chyba** → Zobrazí se box v pravém dolním rohu

### 2. Zkopírování chyby pro Claude:

1. Klikněte na tlačítko **"📋 Kopírovat pro Claude Code nebo Codex"**
2. Otevřete chat s Claude/Codex
3. Napište: "Mám tuto chybu:"
4. Stiskněte **CTRL+V** (vloží se kompletní error report)
5. Claude/Codex přesně ví kde je problém a co opravit

### 3. Příklad zkopírované chyby:

```
🔴 WGS ERROR REPORT
================================================================================
Type: WARNING

Message: Undefined variable $userId

File: /home/user/moje-stranky/includes/control_center_unified.php

Line: 245

Stack Trace:
--------------------------------------------------------------------------------
#0 loadKeys() at control_center_unified.php:245
#1 loadSectionData() at control_center_unified.php:189
#2 {main} at control_center_unified.php:1

Request Info:
--------------------------------------------------------------------------------
URL: /admin.php?tab=control_center
Method: GET
Time: 2025-11-11 14:30:45
================================================================================
```

## 📂 Struktura souborů

### Backend (PHP):
- **`includes/error_handler.php`** - Hlavní error handler
  - `set_error_handler()` - Zachytává PHP errors
  - `set_exception_handler()` - Zachytává exceptions
  - `register_shutdown_function()` - Zachytává fatal errors
  - `displayErrorHTML()` - Zobrazuje chybu v HTML
  - `logErrorToFile()` - Loguje do souboru

### Frontend (JavaScript):
- **`assets/js/error-handler.js`** - JS error handler
  - `window.onerror` - Zachytává JS errors
  - `window.onunhandledrejection` - Zachytává promise rejections
  - `displayJSError()` - Zobrazuje chybu v UI
  - `copyJSError()` - Kopíruje do schránky
  - Enhanced `fetch()` wrapper - Zachytává API chyby

### API:
- **`api/log_js_error.php`** - Endpoint pro logování JS chyb na server

### Logy:
- **`logs/php_errors.log`** - PHP chyby
- **`logs/js_errors.log`** - JavaScript chyby

## 🔧 Integrace

### Automatická integrace:
```php
// init.php
require_once INCLUDES_PATH . '/error_handler.php';
```

### Pro všechny admin stránky:
```html
<!-- admin.php -->
<script src="assets/js/error-handler.js"></script>
```

### Pro jednotlivé stránky (volitelné):
```html
<script src="assets/js/error-handler.js"></script>
```

## 🎨 Formát error reportu

### Pro Claude Code / Codex:

```
🔴 WGS [TYPE] ERROR REPORT
================================================================================
Type: [ERROR/WARNING/EXCEPTION/etc]
Message: [Chybová zpráva]
File: [/úplná/cesta/k/souboru.php]
Line: [123]

Stack Trace:
--------------------------------------------------------------------------------
#0 funkce1() at soubor1.php:123
#1 funkce2() at soubor2.php:456
#2 funkce3() at soubor3.php:789

Request Info:
--------------------------------------------------------------------------------
URL: /path/to/page
Method: GET/POST
Time: 2025-11-11 14:30:45
User Agent: Mozilla/5.0 ...
================================================================================
```

## 💡 Pro vývojáře

### Test PHP chyby:
```php
<?php
// Vyvolá warning
echo $neexistujiciPromenna;

// Vyvolá fatal error
call_to_undefined_function();

// Vyvolá exception
throw new Exception('Test exception');
?>
```

### Test JS chyby:
```javascript
// Vyvolá error
undefinedFunction();

// Vyvolá promise rejection
Promise.reject(new Error('Test rejection'));

// Vyvolá TypeError
const obj = null;
obj.length; // Cannot read property 'length' of null
```

## 🔒 Bezpečnost

- ❌ **NIKDY** nepoužívejte v produkci s `display_errors = 1`
- ✅ V produkci nastavte `ENVIRONMENT = 'production'` v `.env`
- ✅ Chyby se logují do souborů, ne na obrazovku
- ✅ Stack trace obsahuje pouze potřebné informace
- ✅ Citlivá data (hesla, tokeny) nejsou logována

## 📊 Statistiky chyb

### Zobrazení posledních chyb:
```bash
# PHP chyby
tail -n 50 logs/php_errors.log

# JS chyby
tail -n 50 logs/js_errors.log

# Sledování v reálném čase
tail -f logs/php_errors.log
```

### Čištění starých logů:
```bash
# Smazat logy starší než 30 dní
find logs/ -name "*.log" -mtime +30 -delete

# Archivovat logy
tar -czf logs_backup_$(date +%Y%m%d).tar.gz logs/*.log
```

## 🎯 Výhody pro debugging

1. **Přesné umístění** - Nemusíte hledat, kde je chyba
2. **Stack trace** - Vidíte celou posloupnost volání
3. **Copy-paste ready** - Jedním klikem zkopírujete vše pro Claude
4. **Dual logging** - Chyby v UI i v log souborech
5. **AJAX-friendly** - API vrací JSON s detaily
6. **Real-time alerts** - JS chyby zobrazeny okamžitě

## 🆘 Troubleshooting

### Chyby se nezobrazují:
```bash
# Zkontrolujte, zda je error handler načten
grep "error_handler.php" init.php

# Zkontrolujte oprávnění logs/
chmod 755 logs/
chmod 644 logs/*.log
```

### JS chyby se nezobrazují:
```html
<!-- Zkontrolujte, zda je načten error-handler.js -->
<script src="assets/js/error-handler.js"></script>
```

### Logy se nevytvářejí:
```bash
# Vytvořte logs složku
mkdir -p logs
chmod 755 logs
```

## 📝 Changelog

### v1.1 (2025-11-11) - **Control Center Integration**
- ✅ Integrace s Admin Control Center v3.0
- ✅ Error logy dostupné v Diagnostika sekci
- ✅ Testovací prostředí podporuje error reporting
- ✅ Aktualizovaná dokumentace pro unified interface

### v1.0 (2025-11-11)
- ✅ PHP error handler s detailním reportingem
- ✅ JavaScript error handler
- ✅ Kopírování pro Claude Code/Codex
- ✅ Dual logging (UI + file)
- ✅ Stack trace formatting
- ✅ AJAX error handling
- ✅ Enhanced fetch() wrapper

## 👤 Autor
WGS Development Team

## 📄 Licence
Proprietary - Internal use only
