# Scripts & Utilities

Utility skripty pro údržbu, monitoring a optimalizaci WGS Service.

## 📁 Kategorie

### 🔍 Detection & Analysis Tools
Skripty které **pouze detekují** problémy, NIKDY automaticky nemažou:

#### Code Quality
- `detect_dead_code.php` - Najde potenciálně nepoužívaný kód (25 funkcí)
- `detect_duplicate_code.php` - Najde duplicitní funkce
- `detect_legacy_functions.php` - Najde deprecated PHP funkce (195 issues)
- `detect_select_star.php` - Najde SELECT * dotazy (34 výskytů)

#### Documentation
- `improve_documentation.php` - Kontrola doc coverage (12.5% coverage)
- Identifikuje funkce bez PHPDoc komentářů
- Najde adresáře bez README

#### Performance
- `minor_optimizations.php` - Najde optimization opportunities (45 issues)
- count() v loops, array_merge v loops, atd.

#### Cleanup
- `cleanup_commented_code.php` - Najde zakomentovaný kód
- Rozlišuje kód vs. dokumentační komentáře

### 🗄️ Database Tools
Skripty pro databázové operace:

- `add_database_indexes.php` - Přidá 47 performance indexů
- `add_foreign_keys.php` - Přidá 4 FK constraints s orphan detection
- `create_db_backup.php` - Vytvoří GZIP backup databáze

### 📁 Organization
- `organize_setup_files.php` - Organizuje install/migration soubory (PROBĚHLO)

### 📊 Generované Reporty
Skripty automaticky generují tyto reporty:

```
scripts/
├── dead_code_report.txt              # Dead code findings
├── duplicate_code_report.txt         # Duplicitní funkce
├── legacy_functions_report.txt       # Legacy PHP funkce
├── documentation_report.txt          # Doc quality metrics
├── optimizations_report.txt          # Performance opportunities
├── commented_code_report.txt         # Zakomentovaný kód
└── select_star_optimization.txt      # SELECT * checklist
```

## 🚀 Jak Používat

### Detection Tools (Bezpečné)
```bash
# Spustit detection (read-only, bezpečné)
php scripts/detect_dead_code.php
php scripts/detect_legacy_functions.php
php scripts/improve_documentation.php
php scripts/minor_optimizations.php

# Výsledky jsou v scripts/*_report.txt
```

### Database Tools (POZOR - mění DB!)
```bash
# DEVELOPMENT: Testovat nejdřív!
php scripts/add_database_indexes.php

# PRODUCTION: Po ověření v dev
php scripts/add_database_indexes.php

# Foreign Keys: VYČISTIT ORPHANS NEJDŘÍV!
php scripts/add_foreign_keys.php
```

### Backup
```bash
# Vytvořit DB backup
php scripts/create_db_backup.php

# Backup se uloží do backups/ jako .sql.gz
```

## ⚠️ Důležité Varování

### Detection Tools
- ✅ **BEZPEČNÉ** - pouze čtou, nikdy nemažou
- ✅ Lze spustit opakovaně
- ✅ Generují reporty pro manuální review
- ⚠️ Mohou mít false positives (např. dead code volaný z JS)

### Database Tools
- ⚠️ **POZOR** - mění databázi!
- ⚠️ Vždy testovat v development nejdřív
- ⚠️ Vytvořit backup před spuštěním
- ⚠️ Nekterý skripty vyžadují .env soubor

### Cleanup Tools
- ⚠️ **NIKDY automaticky nemazat!**
- ✅ Vždy manuálně ověřit každý finding
- ✅ Pro dead code zkontrolovat JS/frontend
- ✅ Pro zakomentovaný kód zkontrolovat git history

## 📋 Priority Doporučení

### Ihned
1. Spustit `add_database_indexes.php` (50-90% rychlejší queries)
2. Review `dead_code_report.txt` - vyčistit nepoužité funkce
3. Review `legacy_functions_report.txt` - opravit CRITICAL issues

### Týden 1-2
1. Opravit TOP 10 legacy @ issues
2. Dokumentovat největší funkce (PHPDoc)
3. Optimalizovat count() v loops

### Měsíc 1
1. Zvýšit doc coverage z 12.5% na 30%
2. Vyčistit zakomentovaný kód (po review)
3. Postupná migrace na `ApiResponse` helper

## 🛠️ Vytvoření Nového Scriptu

```php
<?php
/**
 * Popis scriptu
 *
 * Použití: php scripts/my_script.php
 */

// Pouze pro CLI nebo s admin checkem
if (php_sapi_name() !== 'cli') {
    session_start();
    if (!isset($_SESSION['is_admin'])) {
        die('Admin access required');
    }
}

// Business logic
echo "🚀 Můj Script\n";
echo str_repeat("=", 70) . "\n\n";

// ... práce ...

// Uložit report
$reportFile = __DIR__ . '/my_report.txt';
file_put_contents($reportFile, $results);
echo "📝 Report uložen: scripts/my_report.txt\n";
```

## 📚 Související Dokumentace

- `/FINAL_AUDIT_SUMMARY.md` - Kompletní přehled všech změn
- `/docs/API_STANDARDIZATION_GUIDE.md` - API standardy
- `/REFACTORING_REPORT.md` - Refactoring report (fáze 1)
- `/setup/README.md` - Setup dokumentace

## 🔗 Dependencies

Většina skriptů vyžaduje:
- PHP 7.4+
- PDO extension (pro DB skripty)
- .env soubor (pro DB připojení)

Detection skripty fungují bez DB připojení.
