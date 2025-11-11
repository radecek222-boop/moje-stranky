# 🛠️ Admin Control Center - Sekce "DIAGNOSTIKA"

## 📋 ÚČEL

**Sekce Diagnostika** v Admin Control Center je centrální místo pro:
- ✅ **Instalace a migrace databáze** - bez SQL znalostí
- ✅ **System Health Monitoring** - real-time diagnostika
- ✅ **Údržba** - cache, logy, optimalizace DB
- ✅ **Debug nástroje** - pokročilé ladění

## 🎯 PRO AI ASISTENTY

**DŮLEŽITÉ:** Když provádíš změny v databázi nebo přidáváš nové funkce:

1. **Vytvoř webový instalátor** (viz `install_admin_control_center.php`)
2. **Přidej kartu do Control Center → Sekce 10: Diagnostika**
3. **Commitni a pushni** - po merge na GitHubu se instalátor objeví v Control Center

### **Workflow:**
```
1. AI vytvoří novou funkci/migraci
2. AI vytvoří webový instalátor (install_*.php)
3. AI přidá kartu do control_center_unified.php → Sekce 10
4. Uživatel merge na GitHubu
5. Uživatel otevře Admin Control Center → Diagnostika → klikne "Spustit instalaci"
6. Hotovo! Žádné SQL příkazy potřeba.
```

## 📍 UMÍSTĚNÍ

**Admin Control Center → Sekce 10: DIAGNOSTIKA**

### ⚠️ ZMĚNA V V3.0:
- **Před v3.0:** admin.php?tab=tools
- **V3.0:** admin.php (výchozí) → rozkliknout "DIAGNOSTIKA" sekci
- **Důvod:** Unified interface - všechny admin funkce v jednom Control Center

URL: `https://wgs-service.cz/admin.php` (výchozí stránka)

## 🧭 NAVIGACE V CONTROL CENTER

### Admin stránka je nyní přímo Control Center:
```
admin.php (bez ?tab=)
  └── Control Center (12 sekcí)
      ├── 1. Statistiky & Analytics
      ├── 2. Registrační klíče
      ├── 3. Uživatelé
      ├── 4. Online uživatelé
      ├── 5. Email & SMS notifikace
      ├── 6. Reklamace
      ├── 7. Vzhled & Design
      ├── 8. Obsah & Texty
      ├── 9. Konfigurace systému
      ├── 10. Diagnostika ← NÁSTROJE & MIGRACE JSOU TADY
      ├── 11. Akce & Úkoly
      └── 12. Testovací prostředí
```

**Header obsahuje pouze:**
- Logo: "WGS CONTROL CENTER"
- Tlačítko: "Odhlásit"

## 🔧 JAK PŘIDAT NOVÝ INSTALÁTOR

### **Krok 1: Vytvoř webový instalátor**

Příklad: `install_nova_funkce.php`

```php
<?php
require_once __DIR__ . '/init.php';

// BEZPEČNOST: Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('Pouze admin může spustit instalaci.');
}

// Instalační logika zde...
// - ALTER TABLE příkazy
// - UPDATE existujících dat
// - CREATE INDEX
// - atd.
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Instalace - Název funkce</title>
    <style>
        /* WGS minimalistický styl */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .container { max-width: 800px; margin: 2rem auto; padding: 2rem; }
        .status { padding: 1rem; margin: 1rem 0; border-left: 4px solid; }
        .success { background: #d4edda; border-color: #28a745; }
        .error { background: #f8d7da; border-color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Instalace - Název funkce</h1>
        <!-- Instalační výstup zde -->
    </div>
</body>
</html>
```

### **Krok 2: Přidej kartu do Control Center**

V souboru `includes/control_center_unified.php`, v sekci **SEKCE 10: DIAGNOSTIKA**, přidej novou kartu:

```php
<!-- Instalátory a migrace -->
<div class="mini-stats" style="margin-top: 1.5rem;">
    <!-- EXISTUJÍCÍ INSTALÁTORY -->

    <!-- NOVÝ INSTALÁTOR -->
    <div class="mini-stat" style="padding: 1.5rem; cursor: pointer; transition: all 0.3s;"
         onclick="window.open('install_nova_funkce.php', '_blank')">
        <div style="font-size: 2rem; margin-bottom: 0.5rem;">🎯</div>
        <div style="font-weight: 600; margin-bottom: 0.5rem;">Název Funkce</div>
        <div style="font-size: 0.8rem; color: var(--c-grey); margin-bottom: 1rem;">
            Krátký popis co instalátor dělá
        </div>
        <button class="btn btn-sm btn-success" style="width: 100%;">
            🚀 Instalovat
        </button>
    </div>
</div>
```

### **Krok 3: Commitni změny**

```bash
git add install_nova_funkce.php includes/control_center_unified.php
git commit -m "FEAT: Instalace pro [název funkce]"
git push
```

### **Krok 4: Uživatel merge na GitHubu**

Po merge se nový instalátor automaticky objeví v Control Center → Diagnostika.

## 📦 PŘÍKLADY

### Aktuálně dostupné instalátory:

#### 1. **Admin Control Center**
- **Soubor:** `install_admin_control_center.php`
- **Popis:** Instalace 6 tabulek pro Control Center
- **Co dělá:**
  - `wgs_theme_settings` - Barvy, fonty
  - `wgs_content_texts` - Multi-jazyčnost
  - `wgs_system_config` - Konfigurace
  - `wgs_pending_actions` - Úkoly
  - `wgs_action_history` - Historie
  - `wgs_github_webhooks` - GitHub integrace

#### 2. **Role-Based Access Control**
- **Soubor:** `install_role_based_access.php`
- **Popis:** Škálovatelný systém rolí
- **Co dělá:**
  - Přidá sloupce `created_by` a `created_by_role`
  - Naplní existující data
  - Vytvoří indexy

**Jak spustit:**
1. Otevřít **admin.php** (automaticky Control Center)
2. Rozkliknout **Sekce 10: DIAGNOSTIKA**
3. Najít požadovaný instalátor
4. Kliknout "🚀 Instalovat"
5. Čekat na dokončení
6. Hotovo!

## 🏥 SYSTEM HEALTH MONITORING

Sekce Diagnostika také obsahuje **real-time monitoring**:

### Monitorované komponenty:
- **🗄️ Databáze** - Připojení, ping time, status
- **🐘 PHP** - Verze, konfigurace, extensions
- **🧩 Extensions** - pdo, pdo_mysql, mbstring, json, gd
- **📁 Oprávnění** - logs/, uploads/, temp/ writeable?
- **💾 Disk** - Volné místo, celková kapacita

### Status indikátory:
- **✅ Zelená** - Vše funguje
- **⚠️ Žlutá** - Varování (např. málo místa)
- **❌ Červená** - Kritická chyba

## 🔍 DEBUG NÁSTROJE

V sekci Diagnostika jsou dostupné:

### **Údržba:**
- **Vymazat cache** - Smaže dočasné soubory
- **Archivovat logy** - Zazipuje staré logy
- **Optimalizovat databázi** - `OPTIMIZE TABLE` všech tabulek

### **Logy:**
- **PHP Error Log** - `logs/php_errors.log`
- **JavaScript Error Log** - `logs/js_errors.log`
- **Security Log** - `logs/security.log`
- **Audit Log** - `logs/audit.log`

### **Debug skripty:**
- `show_table_structure.php` - Struktura tabulek
- `debug_photos.php` - Debug fotek
- `quick_debug.php` - Rychlá diagnostika
- `test_db_connection.php` - Test připojení

Všechny vyžadují admin přihlášení.

## 🎨 DESIGN GUIDELINES

### **Barvy pro karty (WGS styl):**

```css
/* Instalace/Migrace */
background: var(--c-white);
border: 1px solid var(--c-border);
border-left: 4px solid var(--c-success);

/* Varování */
border-left: 4px solid var(--c-warning);

/* Error/Critical */
border-left: 4px solid var(--c-error);
```

### **Status indikátory:**

```php
✅ - Success / OK
⚠️ - Warning / Attention
❌ - Error / Failed
⏳ - In Progress
🔄 - Reloading
```

## ⚠️ BEZPEČNOST

**KRITICKÉ:**
- Všechny instalátory **MUSÍ** kontrolovat `$_SESSION['is_admin']`
- Všechny debug nástroje **MUSÍ** kontrolovat přihlášení
- SQL příkazy **MUSÍ** používat prepared statements
- **NIKDY** nepoužívat `eval()` nebo podobné nebezpečné funkce
- **VŽDY** validovat vstupy

Příklad bezpečné kontroly:

```php
// Na začátku každého instalátoru
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('Přístup odepřen. Pouze admin.');
}

// Pro SQL operace - VŽDY prepared statements
$stmt = $pdo->prepare("INSERT INTO table (column) VALUES (?)");
$stmt->execute([$value]);
```

## 📖 PRO UŽIVATELE

### **Jak použít Control Center:**

1. Přihlaš se jako **admin**
2. Automaticky se zobrazí **Control Center**
3. Najdi a rozklikni **Sekce 10: DIAGNOSTIKA**
4. Vyber instalátor nebo nástroj
5. Klikni **"🚀 Instalovat"** nebo jiné tlačítko
6. Čekej na dokončení
7. Hotovo!

### **Co když něco selže?**

1. **Zobrazí se detailní chybová zpráva** s file:line
2. **Klikni "📋 Kopírovat pro Claude Code nebo Codex"**
3. **CTRL+V** do zprávy pro AI asistenta
4. AI ti pomůže opravit problém

## 🧪 TESTOVACÍ PROSTŘEDÍ

**NOVÉ v3.0:** Sekce 12 obsahuje E2E Testing Environment

- **Spustit před instalací** - Ověřit že systém funguje
- **Spustit po instalaci** - Ověřit že instalace proběhla OK
- **9-krokový test workflow** - Kompletní validace
- **Real testy** - Skutečné připojení k databázi
- **Cleanup** - Potvrzení a smazání test dat

**URL:** admin.php → Sekce 12: Testovací prostředí

## 🔄 AUTOMATIZACE

Plánované vylepšení:
- 🔄 Auto-update po GitHub merge
- 📬 Notifikace o dostupných instalacích
- 📊 Historie instalací
- ✅ Automatické rollback při chybě
- 🤖 AI-assisted installation troubleshooting

## 💡 TIPY PRO AI ASISTENTY

- **Vždy** testuj instalátory na dev prostředí
- **Vždy** používej WGS minimalistický design (černá/bílá/zelená)
- **Vždy** přidej progress indikátor
- **Vždy** loguj každý krok
- **Vždy** kontroluj bezpečnost (admin check)
- **Vždy** použij error handler s "Copy for Claude Code" funkcí
- **Vždy** přidej dokumentaci (README)
- **Vždy** commitni instalátor + Control Center update společně

## 📚 SOUVISEJÍCÍ DOKUMENTACE

- `CONTROL_CENTER_README.md` - Kompletní Control Center dokumentace
- `ERROR_HANDLING_README.md` - Error handling systém
- `ROLE_BASED_ACCESS_README.md` - RBAC systém
- `PDF_PROTOKOL_SYSTEM.md` - PDF protokoly
- `SECURITY_REVIEW_FEEDBACK.md` - Bezpečnostní review

## 🎯 PRIORITY PRO NOVÉ INSTALÁTORY

### High Priority:
- Database schema changes
- Security patches
- Critical bug fixes

### Medium Priority:
- New features
- Performance optimizations
- UI/UX improvements

### Low Priority:
- Optional enhancements
- Experimental features
- Debug tools

---

## 📞 PODPORA

Pokud máš otázky:
1. Přečti `CONTROL_CENTER_README.md` (hlavní dokumentace)
2. Přečti tento soubor (instalátory)
3. Použij **Testovací prostředí** (Sekce 12) pro validaci
4. Použij **Error Handler** "Copy for Claude Code" button
5. Kontaktuj AI asistenta s error reportem

---

*Vytvořeno: 2025-11-10*
*Aktualizováno: 2025-11-11 (v3.0 - Unified Interface)*
*Autor: Claude AI*
*Verze: 2.0*
