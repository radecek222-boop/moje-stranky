# 📸 WGS SERVICE - Oprava systému fotek

**Datum:** 7. listopadu 2025  
**Problém:** Fotky se nezobrazovaly v detailu reklamace

---

## 🐛 IDENTIFIKOVANÝ PROBLÉM

### Symptomy:
- Fotky se nahrávaly v novareklamace.php
- Nebyly vidět v detailu v seznam.php

### Příčiny:
1. ❌ Chyběla složka `/uploads`
2. ❌ `save_photos.php` ukládal pouze `photo_path`
3. ❌ `load.php` načítal `file_path` a `file_name` (jiné sloupce!)
4. ❌ Neshoda mezi ukládáním a čtením dat

---

## ✅ PROVEDENÉ OPRAVY

### 1. Vytvoření uploads složky
```bash
mkdir -p uploads
chmod 755 uploads
```

### 2. Oprava save_photos.php

**PŘED:**
```php
INSERT INTO wgs_photos (
    reklamace_id, section_name, photo_path, photo_type, photo_order, created_at
) VALUES (
    :reklamace_id, :section_name, :photo_path, :photo_type, :photo_order, NOW()
)
```

**PO:**
```php
INSERT INTO wgs_photos (
    reklamace_id, section_name, photo_path, file_path, file_name, photo_type, photo_order, created_at
) VALUES (
    :reklamace_id, :section_name, :photo_path, :photo_path, :file_name, :photo_type, :photo_order, NOW()
)

// + přidáno před execute():
$file_name = basename($relative_path);
```

### 3. Oprava starých fotek
```sql
UPDATE wgs_photos 
SET file_path = photo_path, 
    file_name = SUBSTRING_INDEX(photo_path, '/', -1)
WHERE file_path IS NULL
```

### 4. Vyčištění osiřelých fotek
```sql
DELETE p FROM wgs_photos p
LEFT JOIN wgs_reklamace r ON p.reklamace_id = r.reklamace_id
WHERE r.reklamace_id IS NULL
```

---

## 📊 STRUKTURA DATABÁZE

### Tabulka: wgs_photos
```sql
CREATE TABLE wgs_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    photo_id VARCHAR(50),
    reklamace_id VARCHAR(50),
    section_name VARCHAR(50),
    photo_path VARCHAR(500),      -- Celá cesta
    file_path VARCHAR(500),        -- Stejné jako photo_path ✅
    file_name VARCHAR(255),        -- Jen název souboru ✅
    photo_order INT,
    photo_type VARCHAR(20),
    file_size INT,
    uploaded_at DATETIME,
    created_at DATETIME,
    updated_at DATETIME,
    photo_description TEXT,
    photo_category VARCHAR(50)
);
```

---

## 🔄 TOK DAT - FOTKY

### 1. Nahrání fotky (novareklamace.php)
```
Uživatel vybere fotky
    ↓
JavaScript převede na Base64
    ↓
POST na save_photos.php
    ↓
save_photos.php:
    - Dekóduje Base64
    - Uloží do /uploads/photos/[reklamace_id]/
    - INSERT do wgs_photos s file_path a file_name ✅
```

### 2. Načtení fotek (seznam.php)
```
seznam.js volá load.php
    ↓
load.php:
    - SELECT file_path, file_name FROM wgs_photos ✅
    - Připojí k reklamaci jako array
    ↓
JavaScript zobrazí v detailu
```

---

## 🧪 TESTOVÁNÍ

### Test 1: Vytvoření reklamace s fotkami
1. ✅ Otevřít novareklamace.php
2. ✅ Vyplnit formulář
3. ✅ Přidat fotky (přetáhnout nebo vybrat)
4. ✅ Odeslat

### Test 2: Zobrazení fotek
1. ✅ Otevřít seznam.php
2. ✅ Kliknout na reklamaci
3. ✅ Zkontrolovat že se fotky zobrazují

### Test 3: Kontrola v DB
```bash
php -r "
require_once 'config/config.php';
\$db = getDbConnection();
\$photos = \$db->query('SELECT * FROM wgs_photos LIMIT 1')->fetch();
print_r(\$photos);
"
```

Mělo by obsahovat:
- ✅ `photo_path` - plná cesta
- ✅ `file_path` - plná cesta
- ✅ `file_name` - jen název souboru

---

## 📁 UPRAVENÉ SOUBORY

1. `app/controllers/save_photos.php` - přidány sloupce file_path, file_name
2. `/uploads/` - vytvořena složka
3. Databáze - opraveny staré záznamy

---

## 🎯 VÝSLEDEK

✅ Systém fotek plně funkční  
✅ Fotky se ukládají správně  
✅ Fotky se zobrazují v detailu  
✅ Kompatibilita s load.php zachována

---

**Opraveno:** 7. listopadu 2025  
**Status:** ✅ VYŘEŠENO
