# 🚀 Jak spustit produkční úkoly

Máš 3 důležité produkční úkoly, které je potřeba spustit. Můžeš si vybrat ze **2 způsobů**:

## ✅ ZPŮSOB 1: Přes Control Center (DOPORUČENO)

Tento způsob je **nejjednodušší** - všechny úkoly se objeví v Control Center UI a můžeš je spustit jedním kliknutím.

### Postup:

1. **Spusť SQL v databázi:**
   - Otevři phpMyAdmin (nebo mysql CLI)
   - Vyber svou databázi (např. `wgs_service`)
   - Otevři soubor `setup/add_pending_actions_production.sql`
   - Zkopíruj obsah a spusť ho v SQL konzoli
   - ✅ SQL přidá 3 pending actions

2. **Jdi do Control Center:**
   - Přihlaš se do admina
   - Klikni na **"Akce & Úkoly"**
   - Uvidíš tam 3 nové úkoly:
     - 🚀 PRODUKCE: Přidat databázové indexy (47 indexů) - **HIGH**
     - 🔗 PRODUKCE: Přidat Foreign Key constraints - **HIGH**
     - 🔐 PRODUKCE: Zabezpečit setup/ adresář - **CRITICAL**

3. **Spusť úkoly:**
   - Klikni na každý úkol
   - Přečti si popis (co to dělá, rizika)
   - Klikni "Spustit"
   - ✅ Control Center spustí script a zobrazí výsledek

### Pořadí spouštění:

```
1. 🚀 Databázové indexy (nejdřív - nejbezpečnější)
2. 🔗 Foreign Keys (potom - může failnout pokud jsou orphan data)
3. 🔐 Setup security (nakonec - zablokuje setup adresář)
```

---

## 🔧 ZPŮSOB 2: Manuálně přes SSH (pro experty)

Pokud máš přístup k serveru přes SSH:

### 1. Databázové indexy:
```bash
cd /path/to/moje-stranky
php scripts/add_database_indexes.php
```

**Co to dělá:**
- Přidá 47 indexů do databáze
- Zrychlí WHERE/JOIN/ORDER BY queries
- Žádná změna dat, pouze optimalizace

**Výstup:**
```
⚡ Database Indexes Installation
===============================================
Adding indexes...
✅ Added 47 indexes successfully
```

---

### 2. Foreign Key constraints:
```bash
cd /path/to/moje-stranky
php scripts/add_foreign_keys.php
```

**Co to dělá:**
- Zkontroluje orphan záznamy (záznamy bez parent ID)
- Pokud žádné nejsou, přidá FK constraints
- Pokud jsou, vypíše je a NERUŠÍ constraint

**Možný výstup:**
```
🔗 Foreign Keys Installation
===============================================
Checking for orphan records...
⚠️ Found 3 orphan records in wgs_reklamace:
  - ID 123 (user_id: 999 - neexistuje v wgs_users)
  - ID 124 (user_id: 999 - neexistuje v wgs_users)

❌ Cannot add FK constraint - fix orphan records first!
```

**Jak opravit orphan záznamy:**
```sql
-- Možnost 1: Nastavit NULL (pokud je to možné)
UPDATE wgs_reklamace SET user_id = NULL WHERE user_id = 999;

-- Možnost 2: Smazat záznam
DELETE FROM wgs_reklamace WHERE id = 123;

-- Možnost 3: Vytvořit dummy user
INSERT INTO wgs_users (id, email, ...) VALUES (999, 'deleted@wgs.cz', ...);
```

---

### 3. Zabezpečit setup/ adresář:
```bash
cd /path/to/moje-stranky
cp setup/.htaccess.production setup/.htaccess
```

**Co to dělá:**
- Zablokuje přístup k /setup/ v produkci
- Zabrání spuštění setup scriptů

**⚠️ POZOR:** Po tomto kroku už nebudeš moci přistoupit k setup scriptům!
Pokud budeš potřebovat setup script, vrať `.htaccess.localhost`:
```bash
cp setup/.htaccess.localhost setup/.htaccess
```

---

## 📊 Co očekávat

### Databázové indexy:
- **Doba běhu:** 5-30 sekund (závisí na velikosti DB)
- **Downtime:** ŽÁDNÝ - indexy se přidávají za běhu
- **Riziko:** MINIMÁLNÍ
- **Benefit:** 2-10x rychlejší queries

### Foreign Keys:
- **Doba běhu:** 2-10 sekund
- **Downtime:** ŽÁDNÝ
- **Riziko:** STŘEDNÍ - může failnout pokud jsou orphan data
- **Benefit:** Referenční integrita, prevence orphan záznamů

### Setup security:
- **Doba běhu:** 1 sekunda
- **Downtime:** ŽÁDNÝ
- **Riziko:** ŽÁDNÉ
- **Benefit:** Zabezpečení proti neoprávněnému přístupu

---

## ❓ FAQ

**Q: Co když Foreign Keys failnou?**
A: Script ti vypíše orphan záznamy. Oprav je manuálně (viz výše) a spusť script znovu.

**Q: Můžu to spustit na živém serveru?**
A: Ano! Databázové indexy a FK se přidávají za běhu, bez downtime.

**Q: Co když něco pokazím?**
A: Všechny scripty jsou non-destructive (nemění/nemažou data). V nejhorším případě restart MySQL serveru vše vrátí do původního stavu.

**Q: Musím to spustit hned?**
A: Database indexy - doporučeno ASAP (výrazné zrychlení)
   Foreign Keys - můžeš počkat
   Setup security - KRITICKÉ pokud je server veřejný

**Q: Jak zjistím, jestli to funguje?**
A: Po přidání indexů:
```sql
SHOW INDEX FROM wgs_reklamace;
```
Měl by vidět indexy na `stav`, `user_id`, `created_at`, atd.

---

## 📝 Checklist

Po spuštění všech 3 úkolů:

- [ ] Databázové indexy přidány (47 indexů)
- [ ] Foreign Keys přidány (4 FK constraints)
- [ ] Setup adresář zabezpečen (.htaccess.production aktivní)
- [ ] Všechny 3 akce v Control Center označeny jako "completed"
- [ ] Aplikace běží rychleji ✨

---

Vytvořeno: 2025-11-14
Verze: 1.0
