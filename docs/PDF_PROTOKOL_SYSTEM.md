# 📄 WGS SERVICE - Systém PDF protokolů

**Datum:** 7. listopadu 2025  
**Implementováno:** Kompletní tok PDF od vytvoření po zobrazení

---

## 🎯 FUNKCE SYSTÉMU

### Co systém umí:
1. ✅ Technik vytvoří protokol v `protokol.php`
2. ✅ Přidá fotky z `photocustomer.php`
3. ✅ Vygeneruje PDF s protokolem + fotkami
4. ✅ PDF se automaticky uloží na server
5. ✅ PDF se uloží do databáze `wgs_documents`
6. ✅ PDF se zobrazí v detailu zákazníka v `seznam.php`
7. ✅ Kliknutím na tlačítko se PDF otevře

---

## 🔄 TOK DAT

### 1. Vytvoření reklamace (novareklamace.php)
```
Uživatel vyplní formulář + přidá fotky
    ↓
POST na save_photos.php
    ↓
Uložení do:
  - /uploads/photos/{reklamace_id}/
  - wgs_photos tabulka (s file_path, file_name)
```

### 2. Vytvoření protokolu (protokol.php)
```
Technik vyplní protokol
    ↓
Načtou se fotky z wgs_photos
    ↓
Klikne "Vygenerovat PDF"
    ↓
JavaScript:
  - Vygeneruje PDF (jsPDF)
  - Převede na base64
  - Pošle na api/protokol_api.php
    ↓
PHP:
  - Dekóduje base64
  - Uloží do /uploads/protokoly/{reklamace_id}.pdf
  - INSERT do wgs_documents
    ↓
PDF uloženo na serveru ✅
```

### 3. Zobrazení v seznamu (seznam.php)
```
seznam.js volá load.php
    ↓
load.php:
  - SELECT z wgs_reklamace
  - LEFT JOIN wgs_photos (fotky)
  - LEFT JOIN wgs_documents (PDF)
    ↓
Vrátí data včetně:
  - fotky: ['path1.jpg', 'path2.jpg']
  - dokument: 'uploads/protokoly/NBU25-xxx.pdf'
    ↓
seznam.js zobrazí detail:
  - [📄 Otevřít PDF] tlačítko
  - Fotografie
```

---

## 📁 STRUKTURA DATABÁZE

### wgs_photos
```sql
CREATE TABLE wgs_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reklamace_id VARCHAR(50),
    section_name VARCHAR(50),
    photo_path VARCHAR(500),    -- Plná cesta k fotce
    file_path VARCHAR(500),     -- Stejné jako photo_path
    file_name VARCHAR(255),     -- Jen název souboru
    photo_order INT,
    uploaded_at DATETIME
);
```

### wgs_documents
```sql
CREATE TABLE wgs_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    claim_id INT,               -- FK na wgs_reklamace.id
    document_name VARCHAR(255), -- 'Protokol_NBU25-xxx.pdf'
    document_path VARCHAR(500), -- 'uploads/protokoly/NBU25-xxx.pdf'
    document_type VARCHAR(50),  -- 'protokol_pdf'
    file_size INT,
    uploaded_by VARCHAR(100),   -- Kdo nahrál
    uploaded_at DATETIME
);
```

---

## 📂 SLOŽKY
```
/uploads/
├── photos/              ← Fotky z reklamací
│   └── {reklamace_id}/
│       ├── before_xxx_0.jpg
│       ├── after_xxx_0.jpg
│       └── ...
└── protokoly/           ← PDF protokoly
    ├── NBU25-xxx-xx.pdf
    └── ...
```

---

## 💻 SOUBORY

### Backend (PHP)
- `api/protokol_api.php` - API pro protokoly
  - Action: `save_pdf_document` - Ukládá PDF
- `app/controllers/save_photos.php` - Ukládání fotek
- `app/controllers/load.php` - Načítání dat (+ dokumenty)

### Frontend (JavaScript)
- `assets/js/protokol.min.js` - Původní protokol
- `assets/js/protokol-pdf-upload.js` - **PATCH** pro upload PDF
- `assets/js/seznam.js` - Seznam reklamací + detail

### Stránky
- `novareklamace.php` - Vytvoření reklamace
- `photocustomer.php` - Fotodokumentace
- `protokol.php` - Vytvoření protokolu + PDF
- `seznam.php` - Seznam + detail

---

## 🔧 API ENDPOINTY

### POST api/protokol_api.php

#### Action: save_pdf_document
```json
{
  "action": "save_pdf_document",
  "reklamace_id": "NBU25-555288-58",
  "pdf_base64": "JVBERi0xLjQK..."
}
```

**Response:**
```json
{
  "success": true,
  "message": "PDF uloženo",
  "path": "uploads/protokoly/NBU25-555288-58.pdf"
}
```

---

## 🧪 TESTOVÁNÍ

### Test 1: Vytvoření reklamace s fotkami
1. Otevřít `novareklamace.php`
2. Vyplnit formulář
3. Přidat fotky (min. 2-3)
4. Odeslat
5. ✅ Ověřit v DB: `SELECT * FROM wgs_photos WHERE reklamace_id = 'XXX'`

### Test 2: Vytvoření protokolu
1. Otevřít `seznam.php`
2. Kliknout na reklamaci → Přejít na protokol
3. Vyplnit údaje
4. Kliknout "Vygenerovat PDF"
5. ✅ PDF se otevře v novém okně
6. ✅ Ověřit v DB: `SELECT * FROM wgs_documents WHERE claim_id = XXX`
7. ✅ Ověřit soubor: `ls -lh uploads/protokoly/`

### Test 3: Zobrazení v detailu
1. Otevřít `seznam.php`
2. Kliknout na reklamaci s protokolem
3. ✅ Vidět tlačítko "[📄 Otevřít PDF]"
4. ✅ Kliknout → PDF se otevře
5. ✅ Vidět fotky níže

---

## 🐛 ŘEŠENÍ PROBLÉMŮ

### PDF se nevygeneruje
- Zkontrolovat console v prohlížeči (F12)
- Hledat chyby v `logger.log()`

### PDF se neuloží na server
- Zkontrolovat oprávnění: `ls -la uploads/protokoly/`
- Mělo by být: `drwxr-xr-x`
- Opravit: `chmod 755 uploads/protokoly`

### PDF není v detailu
- Zkontrolovat load.php: `grep -A 5 "wgs_documents" app/controllers/load.php`
- Zkontrolovat DB: `SELECT * FROM wgs_documents`
- Zkontrolovat seznam.js: Hledat `record.dokument`

### Fotky se nezobrazují
- Zkontrolovat wgs_photos: `SELECT * FROM wgs_photos`
- Musí mít `file_path` a `file_name`
- Opravit staré: `UPDATE wgs_photos SET file_path = photo_path, file_name = SUBSTRING_INDEX(photo_path, '/', -1) WHERE file_path IS NULL`

---

## 📊 STATISTIKY

**Upravené soubory:** 6
- api/protokol_api.php
- app/controllers/load.php
- app/controllers/save_photos.php
- assets/js/seznam.js
- assets/js/protokol-pdf-upload.js (nový)
- protokol.php

**Vytvořené složky:** 2
- /uploads/photos/
- /uploads/protokoly/

**Databázové tabulky:** 2
- wgs_photos (upraveno)
- wgs_documents (používá se)

---

**Status:** ✅ PLNĚ FUNKČNÍ  
**Testováno:** 7. listopadu 2025
