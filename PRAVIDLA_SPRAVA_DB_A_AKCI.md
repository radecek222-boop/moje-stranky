# 📋 PRAVIDLA PRO SPRÁVU DATABÁZE A AKCÍ

**Datum vytvoření:** 2025-11-17
**Autor:** Claude AI Assistant
**Projekt:** WGS Service - White Glove Service

---

## 🎯 DŮLEŽITÉ: Dva systémy správy

V admin panelu existují **DVA oddělené systémy** pro správu projektu:

### 1. ⚡ Karta "SQL" - Pro VŠECHNY změny databáze

**URL:** `https://www.wgs-service.cz/admin.php` → karta "SQL"

**✅ VŽDY použijte pro:**
- Přidání nových sloupců do tabulek
- Odstranění zastaralých sloupců
- Vytvoření nových tabulek
- Změna datových typů
- Přidání/odstranění indexů
- Optimalizace databáze
- Oprava VIEW
- Jakékoliv SQL DDL/DML operace

**❌ NIKDY:**
- Neměňte strukturu ručně přes phpMyAdmin
- Nevytvářejte SQL skripty mimo tento systém
- Neodstraňujte sloupce bez kontroly závislostí

---

### 2. 🔧 Karta "Akce & Úkoly" - Pro instalace a úkoly

**URL:** `https://www.wgs-service.cz/admin.php` → karta "Akce & Úkoly"

**✅ VŽDY použijte pro:**
- Instalace PHPMailer
- Instalace Composer balíčků
- Konfigurace SMTP
- Vytvoření záloh systému
- Aktualizace závislostí
- Migrace dat (ne struktury!)
- Úkoly pro admina
- Scheduled tasks

**❌ NIKDY:**
- Nepřidávejte sem SQL operace
- Nepřidávejte zastaralé úkoly
- Nenechávejte dokončené úkoly jako "pending"

---

## 📐 STRUKTURA MIGRAČNÍCH SKRIPTŮ

### SQL migrační skripty

**Umístění:** `/home/user/moje-stranky/` (ROOT složka)

**Formát názvu:**
```
pridej_nazev_sloupce.php          # Pro přidání sloupců
kontrola_nazev.php                 # Pro kontrolu a validaci
migrace_nazev.php                  # Pro komplexní migrace
vycisti_nazev.php                  # Pro cleanup operace
```

**Automatické zobrazení:**
- Všechny SQL skripty v root složce se **automaticky** zobrazí v kartě "SQL"
- Uživatel vidí seznam nástrojů s popisem
- Kliknutím otevře nástroj v novém okně

**Template:**
```php
<?php
/**
 * Migrace: [Popis co skript dělá]
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

// HTML výstup s tlačítkem "SPUSTIT MIGRACI"
// Pokud ?execute=1, provést změny
// Vždy použít transakce!

$pdo->beginTransaction();
try {
    // SQL operace
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    throw $e;
}
?>
```

---

### Instalační skripty (Akce)

**Umístění:** `/home/user/moje-stranky/scripts/`

**Příklady:**
- `install_phpmailer.php` - Instalace PHPMailer
- `install_composer.php` - Instalace Composer balíčků
- `configure_smtp.php` - Konfigurace SMTP

**Záznam do databáze:**
```php
// V aktualizuj_akce_ukoly.php nebo podobném skriptu:
INSERT INTO wgs_pending_actions (
    action_type,
    title,
    description,
    priority,
    status,
    created_at
) VALUES (
    'install_phpmailer',
    'Instalace PHPMailer pro odesílání emailů',
    'Detailní instrukce...',
    'high',
    'pending',
    NOW()
);
```

---

## 🔄 WORKFLOW

### Pro AI Asistenta (Claude)

#### Při vytváření SQL změn:

1. **Vytvoř migrační skript** v root složce
   ```bash
   pridej_novy_sloupec.php
   ```

2. **Dodej uživateli URL:**
   ```
   https://www.wgs-service.cz/pridej_novy_sloupec.php
   ```

3. **Upozorni:**
   ```
   Skript se automaticky zobrazí v kartě "SQL" v admin panelu.
   ```

4. **Commitni do Gitu:**
   ```bash
   git add pridej_novy_sloupec.php
   git commit -m "MIGRATION: Přidání sloupce XYZ do tabulky ABC"
   git push
   ```

#### Při vytváření instalačních úkolů:

1. **Vytvoř instalační skript** v `/scripts/`
   ```bash
   scripts/install_xyz.php
   ```

2. **Aktualizuj kartu "Akce & Úkoly":**
   - Spusť `aktualizuj_akce_ukoly.php`
   - NEBO přidej úkol ručně do `wgs_pending_actions`

3. **Commitni:**
   ```bash
   git add scripts/install_xyz.php aktualizuj_akce_ukoly.php
   git commit -m "FEATURE: Instalátor pro XYZ"
   git push
   ```

---

### Pro Administrátora

#### SQL změny:

1. Přihlásit se do admin panelu
2. Otevřít kartu **"SQL"**
3. Zobrazí se seznam všech migračních nástrojů
4. Kliknout na požadovaný nástroj
5. Zkontrolovat náhled změn
6. Kliknout **"SPUSTIT MIGRACI"**
7. Ověřit úspěch

#### Instalace/Úkoly:

1. Přihlásit se do admin panelu
2. Otevřít kartu **"Akce & Úkoly"**
3. Zobrazí se nevyřešené úkoly seřazené podle priority
4. Kliknout na úkol
5. Postupovat podle instrukcí
6. Označit jako dokončený

---

## 📊 DATABÁZOVÉ TABULKY

### `wgs_pending_actions`

Ukládá úkoly pro kartu "Akce & Úkoly".

**Struktura:**
```sql
CREATE TABLE wgs_pending_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action_type VARCHAR(50),           -- install_phpmailer, create_backup
    title VARCHAR(255),                 -- Zobrazovaný název
    description TEXT,                   -- Detailní instrukce
    priority ENUM('critical','high','medium','low'),
    status ENUM('pending','in_progress','completed','failed','dismissed'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    completed_by INT NULL
);
```

**Stavy:**
- `pending` - Čeká na vyřízení
- `in_progress` - Právě se zpracovává
- `completed` - Dokončeno
- `failed` - Selhalo
- `dismissed` - Odmítnuto (již nepotřebné)

**Priority:**
- `critical` 🔴 - Vyřešit okamžitě
- `high` 🟠 - Vysoká priorita
- `medium` 🟡 - Střední priorita
- `low` 🟢 - Nízká priorita

---

## 🚨 KRITICKÁ PRAVIDLA

### ❌ NIKDY

1. **Neměňte SQL strukturu ručně přes phpMyAdmin**
   - Vždy používejte migrační skripty
   - Důvod: Ztráta auditní stopy, riziko konfliktů

2. **Nepřidávejte SQL operace do karty "Akce & Úkoly"**
   - SQL patří do karty "SQL"
   - Důvod: Oddělení concerns, lepší přehled

3. **Neodstraňujte sloupce bez kontroly**
   - Vždy zkontrolujte závislosti (foreign keys, views, aplikační kód)
   - Použijte `kontrola_zastaralych_sloupcu.php`

4. **Nenechávejte staré úkoly v kartě "Akce & Úkoly"**
   - Pravidelně spouštějte `aktualizuj_akce_ukoly.php`
   - Odstraňte dokončené/deprecated úkoly

5. **Necommitujte `.env` soubor**
   - Obsahuje citlivé údaje (hesla, API klíče)
   - Použijte `.env.example` pro dokumentaci

### ✅ VŽDY

1. **Používejte transakce pro SQL migrace**
   ```php
   $pdo->beginTransaction();
   try {
       // Změny
       $pdo->commit();
   } catch (PDOException $e) {
       $pdo->rollBack();
       throw $e;
   }
   ```

2. **Exportujte DDL před změnami**
   - V kartě "SQL" klikněte "Stáhnout všechny DDL"
   - Uložte jako zálohu

3. **Testujte migrace lokálně**
   - Vždy otestujte na dev prostředí
   - Ověřte rollback

4. **Dokumentujte změny v commit messages**
   ```bash
   MIGRATION: Přidání sloupce `datum_platby` do wgs_reklamace
   FEATURE: Instalátor PHPMailer s SMTP konfigurací
   FIX: Oprava indexu na wgs_reklamace.stav
   ```

5. **Aktualizujte CLAUDE.md při změnách**
   - Přidejte nové tabulky
   - Aktualizujte sloupce
   - Dokumentujte enum hodnoty

---

## 📚 PŘÍKLADY

### Příklad 1: Přidání nového sloupce

**Úkol:** Přidat sloupec `platba_provedena` do tabulky `wgs_reklamace`

**Postup:**

1. **Claude vytvoří:**
   ```bash
   /home/user/moje-stranky/pridej_sloupec_platba_provedena.php
   ```

2. **Obsah:**
   ```php
   <?php
   require_once __DIR__ . '/init.php';

   if (!isset($_SESSION['is_admin'])) die("PŘÍSTUP ODEPŘEN");

   // ... HTML + logika s tlačítkem SPUSTIT MIGRACI

   if ($_GET['execute'] === '1') {
       $pdo->beginTransaction();
       try {
           $pdo->exec("
               ALTER TABLE wgs_reklamace
               ADD COLUMN platba_provedena TINYINT(1) DEFAULT 0
               AFTER castka
           ");
           $pdo->commit();
           echo "✅ Sloupec přidán";
       } catch (PDOException $e) {
           $pdo->rollBack();
           echo "❌ Chyba: " . $e->getMessage();
       }
   }
   ?>
   ```

3. **Admin spustí:**
   - Otevře admin panel → karta "SQL"
   - Klikne na "Přidat sloupec platba_provedena"
   - Klikne "SPUSTIT MIGRACI"

---

### Příklad 2: Instalace PHPMailer

**Úkol:** Nainstalovat PHPMailer pro odesílání emailů

**Postup:**

1. **Claude aktualizuje kartu "Akce & Úkoly":**
   ```bash
   php aktualizuj_akce_ukoly.php
   ```

2. **Přidá úkol:**
   ```sql
   INSERT INTO wgs_pending_actions (
       action_type, title, description, priority, status
   ) VALUES (
       'install_phpmailer',
       'Instalace PHPMailer pro odesílání emailů',
       'Spusťte: https://www.wgs-service.cz/scripts/install_phpmailer.php',
       'high',
       'pending'
   );
   ```

3. **Admin vyřeší:**
   - Otevře admin panel → karta "Akce & Úkoly"
   - Vidí úkol [high] "Instalace PHPMailer"
   - Klikne na úkol → zobrazí se instrukce
   - Otevře URL a spustí instalaci
   - Označí úkol jako dokončený

---

## 🔍 KONTROLNÍ SEZNAM (Checklist)

### Před commitem SQL migrace:

- [ ] Skript je v root složce s názvem `pridej_*`, `kontrola_*`, `migrace_*`
- [ ] Obsahuje bezpečnostní kontrolu `$_SESSION['is_admin']`
- [ ] Používá transakce (`BEGIN` → `COMMIT`/`ROLLBACK`)
- [ ] Má náhled změn před spuštěním
- [ ] Testováno lokálně
- [ ] Aktualizována dokumentace v `CLAUDE.md`
- [ ] Commit message začíná `MIGRATION:`

### Před přidáním úkolu do "Akce & Úkoly":

- [ ] Instalační skript je ve `/scripts/`
- [ ] Obsahuje detailní instrukce v `description`
- [ ] Priorita je správně nastavena (`critical`, `high`, `medium`, `low`)
- [ ] Status je `pending`
- [ ] Staré/deprecated úkoly jsou smazány
- [ ] Commit message začíná `FEATURE:` nebo `UPDATE:`

---

## 📞 KONTAKT

**Máte dotazy?**
- Zkontrolujte tento dokument
- Přečtěte `CLAUDE.md`
- Zkontrolujte kartu "SQL" v admin panelu

**Projekt:** WGS Service
**Dokumentace:** `/home/user/moje-stranky/CLAUDE.md`
**Admin panel:** `https://www.wgs-service.cz/admin.php`

---

© 2025 White Glove Service - Všechny SQL operace v češtině
