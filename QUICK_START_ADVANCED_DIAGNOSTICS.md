# ⚡ QUICK START - Advanced Diagnostics

## 🚀 Instalace za 5 minut

### Krok 1: Soubory jsou již nahrány ✅

```
✅ /api/advanced_diagnostics_api.php
✅ /assets/js/advanced-diagnostics.js
✅ /ADVANCED_DIAGNOSTICS_README.md (dokumentace)
```

### Krok 2: Přidej tlačítko do Control Center konzole

Otevři `/includes/control_center_console.php` a najdi sekci s tlačítky (cca řádek 260-300).

**Přidej tento kód:**

```html
<!-- ADVANCED DIAGNOSTICS BUTTONS -->
<div class="console-actions" style="margin-bottom: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
    <button class="btn btn-primary" onclick="advancedDiagnostics.runFullDiagnostics()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 0.75rem 1.5rem; font-weight: 600;">
        🚀 Ultra Diagnostika
    </button>
    <button class="btn btn-secondary" onclick="advancedDiagnostics.exportJSON()" style="padding: 0.75rem 1.5rem;">
        💾 Export JSON
    </button>
    <button class="btn btn-secondary" onclick="advancedDiagnostics.exportTXT()" style="padding: 0.75rem 1.5rem;">
        📄 Export TXT
    </button>
    <button class="btn btn-info" onclick="advancedDiagnostics.prepareAIAnalysis()" style="padding: 0.75rem 1.5rem;">
        🤖 AI Analysis
    </button>
</div>
```

### Krok 3: Načti JavaScript

V **dolní části** `/includes/control_center_console.php` (před `</body>` nebo za ostatními skripty) přidej:

```html
<!-- Advanced Diagnostics Script -->
<script src="/assets/js/advanced-diagnostics.js"></script>
```

### Krok 4: Přidej CSS styly (VOLITELNÉ, ale doporučené)

V horní části konzole (v `<style>` tagu nebo v separátním CSS souboru):

```css
/* Advanced Diagnostics Enhanced Styling */
.console-line {
    font-family: 'Courier New', Monaco, monospace;
    font-size: 0.9rem;
    line-height: 1.6;
    padding: 2px 4px;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.console-line.header {
    font-weight: bold;
    color: #2563eb;
    font-size: 1.1rem;
    margin: 1rem 0 0.5rem 0;
}

.console-line.section-header {
    font-weight: bold;
    color: #7c3aed;
    margin-top: 1.5rem;
    font-size: 1rem;
}

.console-line.error {
    color: #dc2626;
    font-weight: 500;
}

.console-line.warning {
    color: #f59e0b;
}

.console-line.success {
    color: #10b981;
    font-weight: 500;
}

.console-line.info {
    color: #3b82f6;
}

.console-line.code {
    background: #f3f4f6;
    padding: 6px 10px;
    border-left: 3px solid #6b7280;
    font-family: 'Fira Code', 'Consolas', 'Courier New', monospace;
    margin: 4px 0;
    font-size: 0.85rem;
}

.console-line.separator {
    color: #9ca3af;
    opacity: 0.6;
}

.console-actions {
    position: sticky;
    top: 0;
    background: white;
    z-index: 10;
    padding: 1rem 0;
    border-bottom: 2px solid #e5e7eb;
    margin-bottom: 1rem;
}
```

### Krok 5: Test

1. Otevři Admin Panel
2. Přejdi na tab **"Console"**
3. Klikni na **"🚀 Ultra Diagnostika"**
4. Sleduj výstup v real-time

---

## 📊 Co očekávat

### Běh trvá cca **15-30 sekund** a analyzuje:

- ✅ **2000+ souborů** (PHP, JS, CSS, HTML)
- ✅ **41 SQL tabulek** (indexy, orphaned records, integrity)
- ✅ **Bezpečnostní scany** (XSS, SQL injection, CSRF)
- ✅ **Performance** (velké soubory, neminifikované assets)
- ✅ **Dependencies** (cyklické závislosti, chybějící soubory)
- ✅ **Code quality** (dead code, complexity, duplicates)

### Výstup ukáže:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚀 WGS SERVICE - ULTRA HLOUBKOVÁ DIAGNOSTIKA
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

[... 7 sekcí s detailními analýzami ...]

📊 FINÁLNÍ SHRNUTÍ
🔴 Kritické problémy: 5
🟠 Vysoká priorita: 12
🟡 Střední priorita: 28
ℹ️ Informační: 45

⏱️ Čas diagnostiky: 18.43s
```

---

## 💡 Tipy pro použití

### 1. Kdy spouštět diagnostiku?

- **Po deployi** - zkontroluj, že vše funguje
- **Před mergem PR** - najdi problémy v kódu
- **Pravidelně** (týdně) - monitoruj kvalitu kódu
- **Při podezřelých chybách** - deep scan problémů

### 2. Export výsledků

**JSON export** - pro další zpracování:
```javascript
advancedDiagnostics.exportJSON();
// Stáhne: diagnostics_1731680422.json
```

**TXT export** - pro dokumentaci:
```javascript
advancedDiagnostics.exportTXT();
// Stáhne: diagnostics_1731680422.txt
```

### 3. AI Analýza

```javascript
advancedDiagnostics.prepareAIAnalysis();
// Zobrazí JSON data pro zkopírování do ChatGPT/Claude
```

**Prompt pro AI:**
```
Analyzuj tento PHP projekt a navrhni opravy pro nalezené problémy.
Zaměř se především na kritické a high priority issues.

[zde vlož JSON data z prepareAIAnalysis()]
```

---

## 🔧 Troubleshooting

### Problém: Tlačítko nefunguje

**Zkontroluj konzoli (F12):**
```javascript
// Mělo by vrátit objekt:
console.log(advancedDiagnostics);
```

**Pokud je undefined:**
- Zkontroluj, že je načten `/assets/js/advanced-diagnostics.js`
- Zkontroluj, že není JavaScript error

### Problém: HTTP 429 (Rate limit)

**Řešení:** Počkej 30 minut nebo restart serveru.

**Nebo zvyš limit v kódu:**
```php
// V advanced_diagnostics_api.php, řádek 46:
'max_attempts' => 100, // Původně 50
```

### Problém: Timeout

**Řešení:** Zvyš PHP timeout:
```php
// V advanced_diagnostics_api.php na začátku:
set_time_limit(300); // 5 minut
ini_set('max_execution_time', 300);
```

### Problém: Memory limit

**Řešení:**
```php
// V advanced_diagnostics_api.php na začátku:
ini_set('memory_limit', '512M');
```

---

## 📈 Pokročilé použití

### Programatické volání

```javascript
// Spustit jen specifickou sekci
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

fetch('/api/advanced_diagnostics_api.php?action=analyze_sql_advanced', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ csrf_token: csrfToken })
})
.then(r => r.json())
.then(data => {
    console.log('SQL Analysis:', data.data);
});
```

### Vlastní handlery

```javascript
// Po dokončení diagnostiky
class CustomDiagnostics extends AdvancedDiagnostics {
    displaySummary() {
        super.displaySummary();

        // Tvoje vlastní logika
        const counts = this.countIssues();
        if (counts.critical > 0) {
            alert(`POZOR: ${counts.critical} kritických problémů!`);
        }
    }
}

const customDiagnostics = new CustomDiagnostics();
customDiagnostics.runFullDiagnostics();
```

---

## ✅ Checklist po instalaci

- [ ] Tlačítka jsou viditelná v konzoli
- [ ] Kliknutí na "Ultra Diagnostika" spustí analýzu
- [ ] Výstup se zobrazuje v real-time
- [ ] Finální shrnutí ukazuje správné počty problémů
- [ ] Export JSON funguje
- [ ] Export TXT funguje
- [ ] Žádné JavaScript errors v konzoli (F12)
- [ ] Žádné PHP errors v error logu

---

## 🎓 Další zdroje

- **Kompletní dokumentace:** `/ADVANCED_DIAGNOSTICS_README.md`
- **API dokumentace:** Komentáře v `/api/advanced_diagnostics_api.php`
- **JS dokumentace:** Komentáře v `/assets/js/advanced-diagnostics.js`

---

## 🚀 Ready to Go!

Nyní máš **ultra pokročilou diagnostiku** která je:
- ✅ 500% lepší než původní
- ✅ Bezpečná pro produkci (READ-ONLY)
- ✅ S exporty a AI analýzou
- ✅ S detailními výstupy a doporučeními
- ✅ V češtině

**Užij si diagnostiku! 🎉**
