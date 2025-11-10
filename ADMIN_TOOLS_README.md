# 🛠️ Admin Panel - Sekce "NÁSTROJE & MIGRACE"

## 📋 ÚČEL

Sekce **"NÁSTROJE"** v admin panelu je centrální místo pro:
- ✅ **Instalace a migrace databáze** - bez SQL znalostí
- ✅ **Debug nástroje** - diagnostika systému
- ✅ **Dokumentace** - technické návody

## 🎯 PRO AI ASISTENTY

**DŮLEŽITÉ:** Když provádíš změny v databázi nebo přidáváš nové funkce:

1. **Vytvoř webový instalátor** (viz `install_role_based_access.php`)
2. **Přidej kartu do admin.php** v sekci `<?php if ($activeTab === 'tools'): ?>`
3. **Commitni a pushni** - po merge na GitHubu se instalátor objeví v admin panelu

### **Workflow:**
```
1. AI vytvoří novou funkci/migraci
2. AI vytvoří webový instalátor (*.php)
3. AI přidá kartu do admin.php sekce "tools"
4. Uživatel merge na GitHubu
5. Uživatel otevře Admin → NÁSTROJE → klikne "Spustit instalaci"
6. Hotovo! Žádné SQL příkazy potřeba.
```

## 📍 UMÍSTĚNÍ

Admin panel → **NÁSTROJE** tab → Sekce "Nástroje & Migrace"

URL: `https://wgs-service.cz/admin.php?tab=tools`

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
```

### **Krok 2: Přidej kartu do admin.php**

V souboru `admin.php`, v sekci `<?php if ($activeTab === 'tools'): ?>`, přidej novou kartu:

```php
<!-- INSTALÁTOR: Název funkce -->
<div class="tool-card" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #667eea;">
  <div style="display: flex; align-items: start; gap: 1rem; margin-bottom: 1rem;">
    <div style="font-size: 2.5rem;">🎯</div>
    <div style="flex: 1;">
      <h3 style="margin: 0 0 0.5rem 0; font-size: 1.2rem; color: #333;">Název Funkce</h3>
      <p style="margin: 0; color: #666; font-size: 0.9rem;">Krátký popis co funkce dělá</p>
    </div>
  </div>

  <div style="margin-bottom: 1rem;">
    <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem;">
      <strong>Co se nainstaluje:</strong>
    </div>
    <ul style="margin: 0; padding-left: 1.5rem; font-size: 0.85rem; color: #666;">
      <li>Položka 1</li>
      <li>Položka 2</li>
      <li>Položka 3</li>
    </ul>
  </div>

  <button
    onclick="window.location.href='install_nova_funkce.php'"
    style="width: 100%; padding: 0.75rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;"
  >
    🚀 Spustit instalaci
  </button>
</div>
```

### **Krok 3: Commitni změny**

```bash
git add install_nova_funkce.php admin.php
git commit -m "Přidána instalace pro [název funkce]"
git push
```

### **Krok 4: Uživatel merge na GitHubu**

Po merge se nový instalátor automaticky objeví v admin panelu.

## 📦 PŘÍKLAD - Role-Based Access

Aktuálně dostupný instalátor:

**Název:** Role-Based Access Control
**Soubor:** `install_role_based_access.php`
**Popis:** Škálovatelný systém rolí pro neomezený počet prodejců a techniků

**Co dělá:**
- Přidá sloupce `created_by` a `created_by_role`
- Naplní existující data
- Vytvoří indexy
- Nastaví role pro uživatele

**Jak spustit:**
1. Admin → NÁSTROJE
2. Najdi kartu "Role-Based Access Control"
3. Klikni "🚀 Spustit instalaci"
4. Čekej ~5 sekund
5. Hotovo!

## 🔍 DEBUG NÁSTROJE

V sekci jsou také dostupné debug nástroje:

- **📊 Struktura** → `show_table_structure.php` - zobrazí strukturu tabulek
- **📸 Fotky** → `debug_photos.php` - debug fotek a propojení
- **🔍 Quick Debug** → `quick_debug.php` - rychlá diagnostika
- **🔌 Test DB** → `test_db_connection.php` - test připojení

Všechny vyžadují přihlášení (bezpečnost).

## 📚 DOKUMENTACE

Sekce obsahuje odkazy na:
- `ROLE_BASED_ACCESS_README.md`
- `PDF_PROTOKOL_SYSTEM.md`
- `SECURITY_REVIEW_FEEDBACK.md`
- `PSA_CALCULATOR_SPECIFICATION.md`

## 🎨 DESIGN GUIDELINES

### **Barvy pro karty:**

```php
// Instalace/Migrace
border-left: 4px solid #667eea;  // Fialová

// Debug nástroje
border-left: 4px solid #2196F3;  // Modrá

// Dokumentace
border-left: 4px solid #4CAF50;  // Zelená

// Varování/Critical
border-left: 4px solid #ff9800;  // Oranžová

// Error/Deprecated
border-left: 4px solid #f44336;  // Červená
```

### **Ikony (emoji):**

- 🔐 Security/Auth
- 🚀 Instalace
- 🔍 Debug/Diagnostika
- 📚 Dokumentace
- 🛠️ Nástroje
- ⚙️ Konfigurace
- 📊 Statistiky
- 🎯 Features

## ⚠️ BEZPEČNOST

**KRITICKÉ:**
- Všechny instalátory **MUSÍ** kontrolovat `$_SESSION['is_admin']`
- Všechny debug nástroje **MUSÍ** kontrolovat přihlášení
- SQL příkazy **MUSÍ** používat prepared statements
- **NIKDY** nepoužívat `eval()` nebo podobné nebezpečné funkce

Příklad bezpečné kontroly:

```php
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('Přístup odepřen');
}
```

## 📖 PRO UŽIVATELE

### **Jak použít:**

1. Přihlaš se jako **admin**
2. Jdi na **Admin panel**
3. Klikni na **NÁSTROJE** v menu
4. Vyber instalátor který chceš spustit
5. Klikni **"🚀 Spustit instalaci"**
6. Čekej na dokončení
7. Hotovo!

### **Co když něco selže?**

- Instalátor zobrazí chybovou zprávu
- Můžeš zkusit znovu
- Kontaktuj podporu s chybovou zprávou

## 🔄 AUTOMATIZACE

V budoucnu plánujeme:
- 🔄 Auto-update po GitHub merge
- 📬 Notifikace o dostupných instalacích
- 📊 Historie instalací
- ✅ Automatické rollback při chybě

## 💡 TIPY

- **Vždy** testuj instalátory na dev prostředí před produkcí
- **Vždy** commitni současně instalátor i kartu v admin.php
- **Vždy** přidej dokumentaci (README)
- **Vždy** použij progress bar a logování
- **Vždy** kontroluj bezpečnost (admin check)

---

## 📞 PODPORA

Pokud máš otázky:
1. Přečti tento README
2. Podívej se na existující instalátory (např. `install_role_based_access.php`)
3. Kontaktuj AI asistenta nebo tech support

---

*Vytvořeno: 2025-11-10*
*Autor: Claude AI*
*Verze: 1.0*
*Poslední update: 2025-11-10*
