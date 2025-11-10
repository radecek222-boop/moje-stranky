# 🔐 Role-Based Access Control - Škálovatelné řešení pro WGS Service

## 📋 PŘEHLED

Systém nyní používá **role-based přístup** který správně funguje pro:
- ✅ Stovky prodejců
- ✅ Stovky techniků
- ✅ Neomezený počet zákazníků
- ✅ Adminy

## 👥 ROLE A OPRÁVNĚNÍ

### 🔵 **ADMIN** (`is_admin = true`)
- **Vidí:** VŠECHNY reklamace
- **Může:** Vše (upravovat, mazat, přiřazovat)

### 🟢 **PRODEJCE** (`role = 'prodejce'` nebo `'user'`)
- **Vidí:** VŠECHNY reklamace
- **Důvod:** Prodejci vytvářejí reklamace PRO zákazníky
- **Příklad:** naty@naty.cz vytváří reklamaci pro Jiřího Nováčka
- **Může:** Vytvářet nové reklamace, editovat svoje

### 🟡 **TECHNIK** (`role = 'technik'` nebo `'technician'`)
- **Vidí:** Pouze PŘIŘAZENÉ reklamace
- **Filtr:** `zpracoval_id = user_id` OR `assigned_to = user_id`
- **Může:** Upravovat přiřazené reklamace, psát poznámky

### 🔴 **GUEST** (nepřihlášený nebo `role = 'guest'`)
- **Vidí:** Pouze SVÉ reklamace
- **Filtr:** `email = user_email` OR `created_by = user_id`
- **Může:** Vytvářet nové reklamace, sledovat stav

## 🚀 INSTALACE

### **KROK 1: Spusť migraci databáze**

Otevři **phpMyAdmin** a spusť:

```sql
-- Soubor: migration_add_created_by.sql
```

To přidá:
- `created_by` - ID uživatele který vytvořil reklamaci
- `created_by_role` - Role uživatele při vytvoření
- Indexy pro rychlé vyhledávání

### **KROK 2: Nahraj aktualizované soubory**

```bash
git pull origin claude/help-needed-011CUyanETPwSfKovDxzsvPv
```

Nebo ručně nahraj:
- `app/controllers/load.php` ⭐ (nová logika)
- `app/controllers/save.php` (nastavuje created_by a zpracoval_id)
- `migration_add_created_by.sql` (databázová migrace)

### **KROK 3: Nastav role uživatelů**

V tabulce `wgs_users` nastav správnou roli:

```sql
-- Prodejci (vidí všechny reklamace)
UPDATE wgs_users SET role = 'prodejce' WHERE email = 'naty@naty.cz';
UPDATE wgs_users SET role = 'user' WHERE email = 'prodejce@firma.cz';

-- Technici (vidí pouze přiřazené)
UPDATE wgs_users SET role = 'technik' WHERE email = 'milan@technik.cz';
UPDATE wgs_users SET role = 'technik' WHERE email = 'radek@technik.cz';

-- Admini
UPDATE wgs_users SET is_admin = 1 WHERE email = 'admin@wgs-service.cz';
```

## 🧪 TESTOVÁNÍ

### **Test 1: Prodejce vidí všechny reklamace**
1. Přihlaš se jako prodejce (např. naty@naty.cz)
2. Jdi na `/seznam.php`
3. **Očekáváno:** Vidíš VŠECHNY reklamace (Jiří + Gustav + další)

### **Test 2: Technik vidí pouze přiřazené**
1. Přihlaš se jako technik
2. Jdi na `/seznam.php`
3. **Očekáváno:** Vidíš pouze reklamace kde `zpracoval_id = tvé user_id`

### **Test 3: Vytvoření nové reklamace**
1. Přihlaš se jako prodejce
2. Vytvoř reklamaci pro zákazníka (např. "Karel Novák")
3. **Očekáváno:**
   - `created_by = tvé user_id`
   - `zpracoval_id = tvé user_id` (pokud jsi i technik)
   - Reklamace se ti okamžitě zobrazí v seznamu

## 📊 JAK TO FUNGUJE

### **save.php (vytváření reklamace)**
```php
// Automaticky nastaví:
$columns['created_by'] = $_SESSION['user_id'];        // Kdo vytvořil
$columns['created_by_role'] = $_SESSION['role'];     // Jaká role
$columns['zpracoval_id'] = $_SESSION['user_id'];     // Přiřazeno komu
```

### **load.php (načítání reklamací)**
```php
if ($isProdejce) {
    // Žádný filtr - vidí VŠE
} elseif ($isTechnik) {
    // WHERE zpracoval_id = user_id
} else {
    // WHERE email = user_email
}
```

## 🔄 WORKFLOW PŘÍKLAD

### **Scénář: 100 prodejců, 50 techniků**

1. **Prodejce Naty** vytvoří reklamaci pro zákazníka "Jiří"
   - `created_by = 7` (Naty)
   - `zpracoval_id = NULL` (zatím nepřiřazeno)

2. **Admin** přiřadí technika Milana
   - `zpracoval_id = 15` (Milan)

3. **Kdo co vidí:**
   - ✅ **Naty** (prodejce) → Vidí reklamaci (vidí všechny)
   - ✅ **Milan** (technik) → Vidí reklamaci (je mu přiřazená)
   - ✅ **Radek** (jiný technik) → NEVIDÍ (není mu přiřazená)
   - ✅ **Admin** → Vidí vše

## 🐛 TROUBLESHOOTING

### **Problém: Prodejce nevidí reklamace**
```sql
-- Zkontroluj roli
SELECT id, email, role FROM wgs_users WHERE email = 'naty@naty.cz';

-- Mělo by být: role = 'prodejce' nebo 'user'
-- Oprav:
UPDATE wgs_users SET role = 'prodejce' WHERE email = 'naty@naty.cz';
```

### **Problém: Technik vidí všechny reklamace**
```sql
-- Role je špatně nastavená
-- Oprav:
UPDATE wgs_users SET role = 'technik' WHERE email = 'technik@firma.cz';
```

### **Problém: Existující reklamace se nezobrazují**
```sql
-- Spusť migraci která naplní created_by
-- Viz migration_add_created_by.sql
```

## 📝 POZNÁMKY

- **Prodejci** = ti, co vytvářejí reklamace pro zákazníky → potřebují vidět všechny
- **Technici** = ti, co opravují → potřebují vidět jen své
- **Nová role** prodejce má smysl jen pokud je odlišná od user
- **Škálování**: Funguje i pro 10,000 uživatelů

## ✅ HOTOVO

Po instalaci máš:
- ✅ Škálovatelný role-based systém
- ✅ Prodejci vidí všechny reklamace
- ✅ Technici vidí pouze přiřazené
- ✅ Žádné hardcodované user_id
- ✅ Funguje pro neomezený počet uživatelů

---

*Vytvořeno: 2025-11-10*
*Autor: Claude AI*
*Verze: 2.0 - Škálovatelné řešení*
