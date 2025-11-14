# Databázová struktura WGS Service

## 📊 Přehled databáze

Aplikace používá MariaDB/MySQL databázi s následujícími hlavními tabulkami:

- `wgs_reklamace` - Hlavní tabulka pro reklamace a servisní zakázky
- `wgs_users` - Uživatelé systému
- `wgs_photos` - Fotodokumentace reklamací
- `wgs_documents` - PDF protokoly a dokumenty
- `wgs_notification_history` - Historie odeslaných notifikací
- `wgs_admin_settings` - Nastavení administrace
- `wgs_pending_actions` - Čekající úlohy
- `wgs_action_history` - Historie provedených akcí
- `wgs_notes` - Poznámky k reklamacím

---

## 🔴 KRITICKÉ: ENUM Hodnoty a Mapping

### Pravidlo číslo 1: DB používá ANGLICKÉ lowercase hodnoty

```sql
-- ✅ SPRÁVNĚ - jak to je v DB
stav ENUM('wait', 'open', 'done')
fakturace_firma ENUM('cz', 'sk')
typ ENUM('reklamace', 'servis')
```

### Pravidlo číslo 2: Frontend posílá ČESKÉ uppercase hodnoty

```javascript
// Frontend JavaScript
formData.append('stav', 'DOMLUVENÁ');  // ← České uppercase
formData.append('fakturace_firma', 'CZ');  // ← Uppercase
```

### Pravidlo číslo 3: Backend automaticky mapuje

```php
// save.php - automatický mapping
$stavMapping = [
    'ČEKÁ' => 'wait',        // České → Anglické
    'DOMLUVENÁ' => 'open',
    'HOTOVO' => 'done'
];

$fakturaceFirma = strtolower($value);  // CZ → cz
```

### ⚠️ DŮLEŽITÉ: Když píšeš SQL dotazy

```sql
-- ✅ SPRÁVNĚ - používej anglické hodnoty
SELECT * FROM wgs_reklamace WHERE stav = 'wait';
SELECT * FROM wgs_reklamace WHERE stav = 'open';
SELECT * FROM wgs_reklamace WHERE stav = 'done';

-- ❌ ŠPATNĚ - české hodnoty v DB NEEXISTUJÍ!
SELECT * FROM wgs_reklamace WHERE stav = 'ČEKÁ';  -- NENAJDE NIC!
SELECT * FROM wgs_reklamace WHERE stav = 'DOMLUVENÁ';  -- NENAJDE NIC!
```

---

## 📋 Tabulka: `wgs_reklamace`

Hlavní tabulka pro správu reklamací a servisních zakázek.

### Struktura

```sql
CREATE TABLE wgs_reklamace (
    -- Primární klíč
    id INT(11) AUTO_INCREMENT PRIMARY KEY,

    -- Identifikace reklamace
    reklamace_id VARCHAR(50) NOT NULL UNIQUE,  -- WGS251113-CD5F6A
    typ ENUM('reklamace', 'servis') DEFAULT 'reklamace',
    cislo VARCHAR(100),  -- NBR-555999

    -- Datum
    datum_prodeje DATE,
    datum_reklamace DATE,

    -- Zákazník
    jmeno VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    telefon VARCHAR(50),
    adresa TEXT,

    -- Produkt
    model VARCHAR(255),
    seriove_cislo VARCHAR(255),
    provedeni VARCHAR(100),  -- Kůže/Látka
    barva VARCHAR(100),       -- BF12
    popis_problemu TEXT,

    -- Stav a termín
    stav ENUM('wait', 'open', 'done') DEFAULT 'wait',  -- ← ANGLICKY!
    termin VARCHAR(50),       -- 15.11.2025
    cas_navstevy VARCHAR(50), -- 14:30

    -- Zpracování
    zpracoval VARCHAR(255),
    zpracoval_id VARCHAR(50),
    created_by INT(11),  -- ID uživatele který vytvořil
    created_by_role VARCHAR(20) DEFAULT 'user',
    email_zadavatele VARCHAR(255),

    -- Servis
    popis_opravy TEXT,
    vyreseno VARCHAR(10),
    datum_protokolu DATETIME,
    datum_dokonceni DATETIME,
    poznamky TEXT,

    -- Fakturace
    fakturace_firma ENUM('cz', 'sk'),  -- ← lowercase!
    cena DECIMAL(10,2),
    technik_milan_kolin DECIMAL(10,2),
    technik_radek_zikmund DECIMAL(10,2),

    -- Doplňující info
    doplnujici_info TEXT,

    -- Metadata
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexy
    INDEX idx_stav (stav),
    INDEX idx_termin (termin),
    INDEX idx_created_by (created_by),
    INDEX idx_zpracoval_id (zpracoval_id),
    INDEX idx_created_by_role (created_by_role),
    INDEX idx_typ (typ)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### ENUM hodnoty a jejich význam

#### stav (Stav reklamace)

| DB hodnota | České zobrazení | Význam |
|------------|----------------|--------|
| `'wait'` | ČEKÁ / NOVÁ | Čeká na naplánování termínu |
| `'open'` | DOMLUVENÁ | Termín je domluvený |
| `'done'` | HOTOVO | Reklamace dokončena |

#### fakturace_firma (Fakturace)

| DB hodnota | Zobrazení | Význam |
|------------|-----------|--------|
| `'cz'` | 🇨🇿 Česká republika (CZ) | Fakturuje se na CZ firmu |
| `'sk'` | 🇸🇰 Slovensko (SK) | Fakturuje se na SK firmu |

#### typ (Typ zakázky)

| DB hodnota | Význam |
|------------|--------|
| `'reklamace'` | Reklamace produktu |
| `'servis'` | Servisní zakázka |

### Příklad použití v kódu

```php
// ✅ SPRÁVNĚ - mapping českých hodnot
$stav = $_POST['stav'];  // 'DOMLUVENÁ' z frontendu

$stavMapping = [
    'ČEKÁ' => 'wait',
    'DOMLUVENÁ' => 'open',
    'HOTOVO' => 'done'
];

$dbStav = $stavMapping[$stav];  // → 'open'

// SQL s anglickou hodnotou
$stmt = $pdo->prepare("UPDATE wgs_reklamace SET stav = :stav WHERE id = :id");
$stmt->execute([':stav' => $dbStav, ':id' => $id]);
```

---

## 👥 Tabulka: `wgs_users`

Správa uživatelů systému.

### Struktura

```sql
CREATE TABLE wgs_users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,

    -- Přihlašovací údaje
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,

    -- Osobní údaje
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50),

    -- Role a oprávnění
    role ENUM('admin', 'technik', 'prodejce', 'user') DEFAULT 'user',
    is_active TINYINT(1) DEFAULT 1,

    -- Metadata
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME,

    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Role a oprávnění

| Role | Oprávnění |
|------|-----------|
| `admin` | Plný přístup ke všem funkcím |
| `technik` | Vidí všechny reklamace, může upravovat servisní záznamy |
| `prodejce` | Vidí pouze vlastní vytvořené reklamace |
| `user` | Vidí pouze vlastní vytvořené reklamace |

---

## 📸 Tabulka: `wgs_photos`

Fotodokumentace k reklamacím.

### Struktura

```sql
CREATE TABLE wgs_photos (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,

    -- Identifikace
    photo_id VARCHAR(50) UNIQUE,
    reklamace_id VARCHAR(50) NOT NULL,

    -- Kategorie fotky
    section_name ENUM('before', 'id', 'problem', 'repair', 'after') NOT NULL,

    -- Cesta k souboru
    photo_path VARCHAR(500),
    file_path VARCHAR(500),
    file_name VARCHAR(255),

    -- Metadata
    photo_order INT DEFAULT 0,
    photo_type VARCHAR(50) DEFAULT 'image',  -- image/video
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_reklamace (reklamace_id),
    INDEX idx_section (section_name),

    FOREIGN KEY (reklamace_id) REFERENCES wgs_reklamace(reklamace_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Sekce fotek (section_name)

| Hodnota | Význam |
|---------|--------|
| `'before'` | Stav před opravou |
| `'id'` | ID štítek produktu |
| `'problem'` | Detail problému |
| `'repair'` | Průběh opravy |
| `'after'` | Stav po opravě |

---

## 📄 Tabulka: `wgs_documents`

PDF protokoly a dokumenty.

### Struktura

```sql
CREATE TABLE wgs_documents (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,

    -- Vazba na reklamaci
    claim_id INT(11) NOT NULL,

    -- Dokument
    document_name VARCHAR(255) NOT NULL,
    document_path VARCHAR(500) NOT NULL,
    document_type VARCHAR(50) DEFAULT 'pdf',
    file_size INT,

    -- Metadata
    uploaded_by INT(11),
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_claim (claim_id),

    FOREIGN KEY (claim_id) REFERENCES wgs_reklamace(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 📧 Tabulka: `wgs_notification_history`

Historie odeslaných notifikací (email/SMS).

### Struktura

```sql
CREATE TABLE wgs_notification_history (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,

    -- Příjemce
    recipient_type ENUM('customer', 'admin', 'technician', 'seller') NOT NULL,
    recipient_email VARCHAR(255),
    recipient_phone VARCHAR(50),

    -- Notifikace
    type ENUM('email', 'sms', 'both') NOT NULL,
    subject VARCHAR(500),
    message TEXT,

    -- Status
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at DATETIME,
    error_message TEXT,

    -- Vazba
    claim_id INT(11),

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_claim (claim_id),
    INDEX idx_recipient_type (recipient_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### ENUM hodnoty

#### recipient_type (Typ příjemce)

| Hodnota | Význam |
|---------|--------|
| `'customer'` | Zákazník |
| `'admin'` | Administrátor |
| `'technician'` | Technik |
| `'seller'` | Prodejce |

#### type (Typ notifikace)

| Hodnota | Význam |
|---------|--------|
| `'email'` | Pouze email |
| `'sms'` | Pouze SMS |
| `'both'` | Email i SMS |

#### status (Stav odeslání)

| Hodnota | Význam |
|---------|--------|
| `'pending'` | Čeká na odeslání |
| `'sent'` | Odesláno |
| `'failed'` | Selhalo |

---

## 🔧 Tabulka: `wgs_pending_actions`

Čekající úlohy systému.

### Struktura

```sql
CREATE TABLE wgs_pending_actions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,

    -- Úloha
    action_type VARCHAR(100) NOT NULL,
    action_data JSON,

    -- Priorita
    priority ENUM('critical', 'high', 'medium', 'low') DEFAULT 'medium',

    -- Status
    status ENUM('pending', 'in_progress', 'completed', 'failed', 'dismissed') DEFAULT 'pending',

    -- Metadata
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    scheduled_for DATETIME,
    completed_at DATETIME,

    INDEX idx_status (status),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 📝 Tabulka: `wgs_notes`

Interní poznámky k reklamacím.

### Struktura

```sql
CREATE TABLE wgs_notes (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,

    -- Vazba
    claim_id INT(11) NOT NULL,

    -- Poznámka
    note_text TEXT NOT NULL,

    -- Autor
    author_id INT(11),
    author_name VARCHAR(255),

    -- Viditelnost
    is_read TINYINT(1) DEFAULT 0,

    -- Metadata
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_claim (claim_id),

    FOREIGN KEY (claim_id) REFERENCES wgs_reklamace(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🔗 Vztahy mezi tabulkami

```
wgs_reklamace (1) ──────< (N) wgs_photos
     │                          (reklamace_id)
     │
     ├─────────────────< (N) wgs_documents
     │                          (claim_id)
     │
     ├─────────────────< (N) wgs_notes
     │                          (claim_id)
     │
     └─────────────────< (N) wgs_notification_history
                                (claim_id)

wgs_users (1) ──────────< (N) wgs_reklamace
                                (created_by)
```

---

## 📊 Indexy a výkon

### Primární indexy

- `PRIMARY KEY` na `id` ve všech tabulkách
- `UNIQUE` index na `reklamace_id` v `wgs_reklamace`
- `UNIQUE` index na `email` v `wgs_users`

### Sekundární indexy

```sql
-- wgs_reklamace - nejčastější filtry
INDEX idx_stav (stav)                -- Filtrování podle stavu
INDEX idx_termin (termin)            -- Vyhledávání podle termínu
INDEX idx_created_by (created_by)    -- Filtr podle autora
INDEX idx_typ (typ)                  -- Filtr reklamace/servis

-- wgs_photos - spojování s reklamací
INDEX idx_reklamace (reklamace_id)   -- JOIN s wgs_reklamace
INDEX idx_section (section_name)     -- Filtr podle sekce

-- wgs_notification_history - monitoring
INDEX idx_status (status)            -- Pending notifikace
INDEX idx_claim (claim_id)           -- Notifikace k reklamaci
```

---

## 🔄 Migrace a aktualizace

### Přidání nového sloupce

```sql
ALTER TABLE wgs_reklamace
ADD COLUMN novy_sloupec VARCHAR(255) AFTER existujici_sloupec;
```

### Změna ENUM hodnot

```sql
-- POZOR: Nejdřív zkontroluj že nové hodnoty nevyžadují změny v kódu!
ALTER TABLE wgs_reklamace
MODIFY COLUMN stav ENUM('wait', 'open', 'done', 'nova_hodnota');
```

### Přidání indexu

```sql
ALTER TABLE wgs_reklamace
ADD INDEX idx_novy_index (sloupec_name);
```

---

## 🛡️ Bezpečnost

### SQL Injection prevence

```php
// ✅ SPRÁVNĚ - PDO prepared statements
$stmt = $pdo->prepare("SELECT * FROM wgs_reklamace WHERE id = :id");
$stmt->execute([':id' => $id]);

// ❌ ŠPATNĚ - nekoncatenuj SQL!
$sql = "SELECT * FROM wgs_reklamace WHERE id = " . $id;  // NEBEZPEČNÉ!
```

### Sanitizace vstupů

```php
// Pro INSERT/UPDATE vždy sanitizuj
$jmeno = sanitizeInput($_POST['jmeno']);
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
```

---

## 📈 Příklady dotazů

### Načtení reklamací podle stavu

```sql
-- ✅ SPRÁVNĚ - anglické hodnoty
SELECT *
FROM wgs_reklamace
WHERE stav = 'wait'
ORDER BY created_at DESC;
```

### Reklamace s fotkami

```sql
SELECT
    r.*,
    COUNT(p.id) as pocet_fotek
FROM wgs_reklamace r
LEFT JOIN wgs_photos p ON r.reklamace_id = p.reklamace_id
GROUP BY r.id;
```

### Reklamace podle uživatele

```sql
SELECT r.*
FROM wgs_reklamace r
WHERE r.created_by = :user_id
  AND r.stav != 'done'
ORDER BY r.termin ASC;
```

### Statistiky

```sql
SELECT
    stav,
    COUNT(*) as pocet,
    AVG(cena) as prumerna_cena
FROM wgs_reklamace
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY stav;
```

---

## ⚠️ ČASTÉ CHYBY

### ❌ Chyba 1: Používání českých hodnot v SQL

```sql
-- ❌ ŠPATNĚ
WHERE stav = 'DOMLUVENÁ'  -- DB má 'open'!

-- ✅ SPRÁVNĚ
WHERE stav = 'open'
```

### ❌ Chyba 2: Case-sensitive porovnání

```sql
-- ❌ ŠPATNĚ
WHERE fakturace_firma = 'CZ'  -- DB má 'cz'!

-- ✅ SPRÁVNĚ
WHERE fakturace_firma = 'cz'
-- NEBO
WHERE UPPER(fakturace_firma) = 'CZ'
```

### ❌ Chyba 3: Zapomenutý mapping v PHP

```php
// ❌ ŠPATNĚ - ukládá české hodnoty přímo
$stmt->execute([':stav' => 'DOMLUVENÁ']);

// ✅ SPRÁVNĚ - mapping
$stavMapping = ['DOMLUVENÁ' => 'open'];
$stmt->execute([':stav' => $stavMapping['DOMLUVENÁ']]);
```

---

## 📞 Podpora

Při problémech s databází:

1. Zkontroluj ENUM hodnoty: `SHOW COLUMNS FROM wgs_reklamace LIKE 'stav';`
2. Zkontroluj indexy: `SHOW INDEX FROM wgs_reklamace;`
3. Analyzuj pomalé dotazy: `EXPLAIN SELECT ...`
4. Zkontroluj chybové logy: `/logs/php_errors.log`

---

© 2025 White Glove Service - Databázová dokumentace
