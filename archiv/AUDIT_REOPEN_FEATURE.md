# 🔍 AUDIT: Klonování zakázek - Dopad na stávající systém

**Datum:** 2025-11-24
**Branch:** `claude/review-page-architecture-01XTiXKwR8r4xo1QWUnp2hbg`
**Commit:** fa83306
**Autor:** Claude AI + Radek Zikmund

---

## 📊 PŘEHLED ZMĚN

| Soubor | Typ změny | Řádky +/- | Dopad |
|--------|-----------|-----------|-------|
| `app/controllers/save.php` | Modifikace + Přidání | +174 | ⚠️ **VYSOKÝ** |
| `assets/js/seznam.js` | Modifikace | +177 / -77 | ⚠️ **VYSOKÝ** |
| `api/get_original_documents.php` | Nový soubor | +93 | ✅ **NÍZKÝ** |
| `pridej_original_reklamace_id.php` | Nový soubor | +186 | ⚠️ **STŘEDNÍ** |

**Celkem:** 4 soubory, 553 řádků přidáno, 77 řádků odstraněno

---

## 🎯 DETAILNÍ ANALÝZA DOPADŮ

### 1. **app/controllers/save.php** - KRITICKÁ ZMĚNA ⚠️

#### **Změny:**
- ✅ **Přidána nová funkce** `handleReopen()` (174 řádků)
- ✅ **Přidána nová akce** `'reopen'` v hlavním routeru
- ✅ **ŽÁDNÉ změny** ve stávajících funkcích `handleUpdate()` nebo `handleCreate()`

#### **Dopad na stávající funkcionalitu:**

| Funkce | Změněna? | Dopad | Riziko |
|--------|----------|-------|--------|
| `handleUpdate()` | ❌ NE | Žádný - nezměněna | ✅ **ŽÁDNÉ** |
| `handleCreate()` | ❌ NE | Žádný - nezměněna | ✅ **ŽÁDNÉ** |
| `generateWorkflowId()` | ❌ NE | Žádný - pouze voláno z `handleReopen()` | ✅ **ŽÁDNÉ** |
| Router (akce) | ✅ ANO | Přidána nová akce `'reopen'` | ⚠️ **NÍZKÉ** |

#### **Zpětná kompatibilita:**
✅ **100% ZACHOVÁNA**
- Stávající volání `action: 'update'` fungují **beze změny**
- Stávající volání `action: 'create'` fungují **beze změny**
- Nová akce `'reopen'` je **samostatná** a neovlivňuje ostatní

#### **Bezpečnostní kontroly v `handleReopen()`:**
- ✅ Autentizace: vyžaduje `is_admin` NEBO `user_id`
- ✅ CSRF validace: povinná (kontrola v hlavním bloku před routerem)
- ✅ Kontrola stavu: lze klonovat pouze zakázky se stavem `'done'`
- ✅ Transakční bezpečnost: `beginTransaction()` + `commit()` / `rollBack()`
- ✅ SQL injection ochrana: PDO prepared statements

#### **Možná rizika:**
⚠️ **RIZIKO 1: Databázový sloupec neexistuje**
- **Popis:** `handleReopen()` vkládá `original_reklamace_id`, ale sloupec ještě neexistuje v DB
- **Dopad:** `PDOException` při pokusu o klonování zakázky
- **Řešení:** Spustit migraci `pridej_original_reklamace_id.php` **PŘED** merge/deploy
- **Závažnost:** 🔴 **KRITICKÉ** (blokující)

⚠️ **RIZIKO 2: Chybějící sloupce v DB**
- **Popis:** Kód předpokládá existenci sloupců: `created_by`, `created_by_role`, `zpracoval_id`, `created_at`, `updated_at`
- **Dopad:** Pokud sloupce neexistují, INSERT selže
- **Řešení:** Tyto sloupce již existují (standardní struktura), ale doporučuji **kontrolu** před mergem
- **Závažnost:** 🟡 **STŘEDNÍ**

⚠️ **RIZIKO 3: Generování ID v transakci**
- **Popis:** `generateWorkflowId()` používá `FOR UPDATE` lock v transakci
- **Dopad:** V případě souběžného klonování může dojít k deadlocku
- **Pravděpodobnost:** Nízká (klonování je vzácné)
- **Závažnost:** 🟢 **NÍZKÉ**

---

### 2. **assets/js/seznam.js** - KRITICKÁ ZMĚNA ⚠️

#### **Změny:**
- ✅ **Upravena funkce** `reopenOrder(id)` - **KOMPLETNĚ PŘEPSÁNA**
- ✅ **Upravena funkce** `showDetail(recordOrId)` - **LOGIKA PDF TLAČÍTEK**
- ✅ **Přidána nová funkce** `showHistoryPDF(originalReklamaceId)`
- ✅ **Přidán event handler** pro `'showHistoryPDF'` akci

#### **Dopad na stávající funkcionalitu:**

| Funkce | Změněna? | Původní chování | Nové chování | Zpětná kompatibilita |
|--------|----------|-----------------|--------------|---------------------|
| `reopenOrder()` | ✅ **ANO** | Volá `action: 'update'`, přepíše stav na ČEKÁ | Volá `action: 'reopen'`, vytvoří klon | ❌ **NEKOMPATIBILNÍ** |
| `showDetail()` | ✅ **ANO** | Zobrazí 1 PDF tlačítko | Zobrazí 1 nebo 2 PDF tlačítka podle `original_reklamace_id` | ✅ **KOMPATIBILNÍ** |
| `loadAll()` | ❌ NE | Žádná změna | Žádná změna | ✅ **KOMPATIBILNÍ** |
| `renderOrders()` | ❌ NE | Žádná změna | Žádná změna | ✅ **KOMPATIBILNÍ** |
| Event delegation | ✅ ANO | Přidán nový handler | Přidán case `'showHistoryPDF'` | ✅ **KOMPATIBILNÍ** |

#### **Zpětná kompatibilita - KRITICKÁ ANALÝZA:**

##### ❌ **NEKOMPATIBILNÍ ZMĚNA: `reopenOrder()`**

**Staré chování (před změnou):**
```javascript
// Volalo action: 'update'
formData.append('action', 'update');
formData.append('stav', 'ČEKÁ');

// Přepsalo stav původní zakázky
// Výsledek: zakázka změněna z HOTOVO → ČEKÁ
```

**Nové chování (po změně):**
```javascript
// Volá action: 'reopen'
formData.append('action', 'reopen');

// Vytvoří KLON zakázky
// Výsledek: původní HOTOVO, nová zakázka ČEKÁ
```

**Dopad:**
- ⚠️ **ZMĚNA BUSINESS LOGIKY** - toto je **ZÁMĚRNÁ** změna
- ✅ **Lepší chování** pro statistiky (2 zakázky místo 1 přepsané)
- ⚠️ **Uživatelé si musí zvyknout** - zakázka se NEKLONUJE, ale VYTVOŘÍ se nová

##### ✅ **KOMPATIBILNÍ ZMĚNA: `showDetail()`**

**Logika:**
```javascript
if (record.original_reklamace_id) {
  // Zakázka je KLON - zobrazit 2 tlačítka
  // 1. Historie zákazníka (PDF z původní)
  // 2. PDF REPORT (PDF z aktuální)
} else {
  // Původní zakázka - zobrazit 1 tlačítko
  // PDF REPORT
}
```

**Zpětná kompatibilita:**
- ✅ **Zachována** - zakázky BEZ `original_reklamace_id` zobrazí standardní 1 tlačítko
- ✅ **Progresivní vylepšení** - zakázky S `original_reklamace_id` zobrazí 2 tlačítka

#### **Možná rizika:**

⚠️ **RIZIKO 1: Data v cache neobsahují `original_reklamace_id`**
- **Popis:** `WGS_DATA_CACHE` nemusí obsahovat nový sloupec po reloadu
- **Dopad:** Tlačítko "Historie PDF" se nezobrazí, i když by mělo
- **Řešení:** Po merge **vždy provést hard reload** (Ctrl+Shift+R) nebo vyčistit cache
- **Závažnost:** 🟡 **STŘEDNÍ**

⚠️ **RIZIKO 2: API `load.php` nevrací `original_reklamace_id`**
- **Popis:** Pokud `load.php` neobsahuje `original_reklamace_id` v SELECT dotazu
- **Dopad:** Frontend nikdy neobdrží tento sloupec → tlačítko Historie se nikdy nezobrazí
- **Řešení:** **KONTROLA NUTNÁ** - ověřit že `load.php` vrací tento sloupec
- **Závažnost:** 🔴 **KRITICKÉ**

⚠️ **RIZIKO 3: Uživatelská zkušenost se změní**
- **Popis:** Uživatelé zvyklí na "Znovu otevřít = změna stavu" uvidí nové chování
- **Dopad:** Zmatenost, možné stížnosti
- **Řešení:** **Dokumentace + školení** uživatelů po deploy
- **Závažnost:** 🟡 **STŘEDNÍ**

---

### 3. **api/get_original_documents.php** - NOVÝ SOUBOR ✅

#### **Změny:**
- ✅ Nový API endpoint pro načítání PDF dokumentů z původní zakázky

#### **Dopad na stávající funkcionalitu:**
- ✅ **ŽÁDNÝ** - zcela nový soubor, neovlivňuje existující API

#### **Zpětná kompatibilita:**
- ✅ **100% ZACHOVÁNA** - nový endpoint, žádné změny ve stávajících

#### **Možná rizika:**
⚠️ **RIZIKO: Tabulka `wgs_documents` neexistuje**
- **Popis:** Endpoint předpokládá existenci tabulky `wgs_documents`
- **Dopad:** 500 Error při volání API
- **Řešení:** **KONTROLA** před mergem - ověřit existenci tabulky
- **Závažnost:** 🟡 **STŘEDNÍ**

---

### 4. **pridej_original_reklamace_id.php** - NOVÝ MIGRAČNÍ SKRIPT ⚠️

#### **Změny:**
- ✅ Nový SQL migrační skript pro přidání sloupce `original_reklamace_id`

#### **Dopad na stávající funkcionalitu:**
- ⚠️ **VYSOKÝ** - pokud se nespustí, celá feature nefunguje

#### **Co dělá:**
```sql
ALTER TABLE wgs_reklamace
ADD COLUMN original_reklamace_id VARCHAR(50) NULL
COMMENT 'ID původní zakázky při znovuotevření (klonování)'
AFTER reklamace_id;

ALTER TABLE wgs_reklamace
ADD INDEX idx_original_reklamace_id (original_reklamace_id);
```

#### **Zpětná kompatibilita:**
- ✅ **ZACHOVÁNA** - sloupec je `NULL` (volitelný)
- ✅ Existující zakázky **NEZMĚNĚNY** - sloupec zůstane `NULL`
- ✅ Aplikace funguje i **před migrací** (kromě klonování)

#### **Možná rizika:**
⚠️ **RIZIKO: Migrace se nespustí před deploy**
- **Popis:** Uživatel zkusí "Znovu otevřít" PŘED spuštěním migrace
- **Dopad:** `PDOException: Unknown column 'original_reklamace_id'`
- **Řešení:** **SPUSTIT MIGRACI PŘED MERGE/DEPLOY**
- **Závažnost:** 🔴 **KRITICKÉ** (blokující)

---

## 🚨 KRITICKÁ RIZIKA - KONTROLNÍ SEZNAM

### ✅ **PŘED MERGE POVINNÉ KONTROLY:**

| # | Kontrola | Status | Závažnost |
|---|----------|--------|-----------|
| 1 | **Spustit SQL migraci** `pridej_original_reklamace_id.php` | ⏳ **ČEKÁ** | 🔴 **KRITICKÉ** |
| 2 | **Ověřit existenci tabulky** `wgs_documents` | ⏳ **ČEKÁ** | 🟡 **STŘEDNÍ** |
| 3 | **Kontrola `load.php`** - vrací `original_reklamace_id`? | ⏳ **ČEKÁ** | 🔴 **KRITICKÉ** |
| 4 | **Kontrola sloupců** v `wgs_reklamace`: `created_by`, `zpracoval_id`, atd. | ⏳ **ČEKÁ** | 🟡 **STŘEDNÍ** |
| 5 | **Testování klonování** na testovací zakázce | ⏳ **ČEKÁ** | 🔴 **KRITICKÉ** |
| 6 | **Hard reload frontendu** po deploy (vyčistit cache) | ⏳ **ČEKÁ** | 🟡 **STŘEDNÍ** |

---

## 📋 TESTOVACÍ SCÉNÁŘE - PŘED MERGEM

### **SCÉNÁŘ 1: Klonování dokončené zakázky**

**Prerekvizity:** Existuje zakázka ve stavu HOTOVO s PDF dokumentem

**Kroky:**
1. Přihlásit se jako admin/technik
2. Otevřít seznam zakázek (`seznam.php`)
3. Najít dokončenou zakázku (zelená karta)
4. Kliknout na kartu → Detail zakázky
5. Kliknout "Znovu otevřít"
6. Potvrdit dialog

**Očekávaný výsledek:**
- ✅ Alert: "✓ NOVÁ ZAKÁZKA VYTVOŘENA"
- ✅ Nová žlutá karta se objeví v seznamu (nové číslo WGS/...)
- ✅ Původní zelená karta zůstává HOTOVO
- ✅ Otevře se detail nové zakázky
- ✅ Tlačítko "📚 Historie PDF" viditelné
- ✅ Kliknutí na Historie PDF → otevře PDF z původní zakázky

**Co testovat:**
- [ ] Původní zakázka **NEZMĚNILA STAV** (stále HOTOVO)
- [ ] Nová zakázka má **NOVÉ ID**
- [ ] Nová zakázka má **STAV ČEKÁ** (žlutá)
- [ ] Nová zakázka má **všechny údaje zkopírované**
- [ ] Původní zakázka má **poznámku** "🔗 Založena nová zakázka..."
- [ ] Nová zakázka má **poznámku** "🔄 Zakázka otevřena jako klon..."

---

### **SCÉNÁŘ 2: Zobrazení historie PDF**

**Prerekvizity:** Existuje klonovaná zakázka (má `original_reklamace_id`)

**Kroky:**
1. Otevřít detail klonované zakázky
2. Kliknout "📚 Historie PDF"

**Očekávaný výsledek:**
- ✅ Otevře se PDF z původní zakázky v novém okně
- ✅ Žádná chyba

**Co testovat:**
- [ ] PDF se **otevře** (ne 404)
- [ ] PDF je **z původní zakázky** (zkontrolovat datum/údaje)

---

### **SCÉNÁŘ 3: Dokončení klonované zakázky**

**Prerekvizity:** Existuje klonovaná zakázka (nová, ČEKÁ)

**Kroky:**
1. Naplánovat termín
2. Zahájit návštěvu → photocustomer.php
3. Nahrát fotky
4. Přejít na protokol.php
5. Vyplnit protokol, podpis, cena
6. Export PDF a odeslat zákazníkovi

**Očekávaný výsledek:**
- ✅ Zakázka změněna na HOTOVO
- ✅ PDF vytvořeno
- ✅ V detailu viditelná **DVĚ** tlačítka:
  - 📚 Historie zákazníka (PDF z první opravy)
  - 📄 PDF REPORT (PDF z druhé opravy)

**Co testovat:**
- [ ] **Obě PDF tlačítka** viditelná
- [ ] **Historie** otevře staré PDF
- [ ] **PDF REPORT** otevře nové PDF
- [ ] **Statistiky** zobrazují **2 dokončené zakázky** (ne 1)

---

### **SCÉNÁŘ 4: Zpětná kompatibilita - původní zakázky**

**Prerekvizity:** Existuje zakázka BEZ `original_reklamace_id` (stará zakázka před změnou)

**Kroky:**
1. Otevřít detail staré zakázky

**Očekávaný výsledek:**
- ✅ Zobrazí se **JEDNO** PDF tlačítko (standardní chování)
- ✅ Tlačítko "📚 Historie PDF" **NENÍ** viditelné
- ✅ Vše funguje jako předtím

**Co testovat:**
- [ ] Žádná chyba
- [ ] Standardní chování zachováno

---

### **SCÉNÁŘ 5: Pokus o klonování nedokončené zakázky**

**Prerekvizity:** Existuje zakázka ve stavu ČEKÁ nebo DOMLUVENÁ

**Kroky:**
1. Otevřít detail nedokončené zakázky
2. Zkusit najít tlačítko "Znovu otevřít"

**Očekávaný výsledek:**
- ✅ Tlačítko "Znovu otevřít" **NENÍ viditelné** (zobrazuje se pouze pro HOTOVO)

**Alternativní test (pokud by se někdo dostal k API):**
- Volat `action: 'reopen'` s ID nedokončené zakázky
- ✅ Backend vrátí chybu: "Lze klonovat pouze dokončené zakázky"

---

## 🔧 OVLIVNĚNÉ KOMPONENTY

### **Backend:**
| Komponenta | Ovlivněna? | Typ změny |
|------------|------------|-----------|
| `save.php` - `handleUpdate()` | ❌ NE | - |
| `save.php` - `handleCreate()` | ❌ NE | - |
| `save.php` - Router | ✅ ANO | Přidána akce `'reopen'` |
| `load.php` | ⚠️ MOŽNÁ | Musí vracet `original_reklamace_id` |
| `notification_sender.php` | ❌ NE | - |

### **Frontend:**
| Komponenta | Ovlivněna? | Typ změny |
|------------|------------|-----------|
| `seznam.js` - `reopenOrder()` | ✅ ANO | Kompletně přepsána |
| `seznam.js` - `showDetail()` | ✅ ANO | Přidána logika 2 PDF tlačítek |
| `seznam.js` - `loadAll()` | ❌ NE | - |
| `seznam.js` - `renderOrders()` | ❌ NE | - |
| `novareklamace.js` | ❌ NE | - |
| `protokol.js` | ❌ NE | - |
| `photocustomer.js` | ❌ NE | - |

### **Databáze:**
| Tabulka | Ovlivněna? | Typ změny |
|---------|------------|-----------|
| `wgs_reklamace` | ✅ ANO | Nový sloupec `original_reklamace_id` |
| `wgs_notes` | ✅ ANO | Nové záznamy (poznámky o klonování) |
| `wgs_documents` | ⚠️ MOŽNÁ | Čte se z ní (endpoint `get_original_documents.php`) |

---

## 📈 DOPAD NA STATISTIKY

### **PŘED změnou (špatné):**
```
Zákazník A měl rozbitou pohovku 2×:
• Zakázka #1: HOTOVO → přepsáno na ČEKÁ → znovu HOTOVO
• Statistika: 1 zakázka, 1× dokončeno (špatně - ve skutečnosti 2×)
```

### **PO změně (správné):**
```
Zákazník A měl rozbitou pohovku 2×:
• Zakázka #1: HOTOVO (zůstává nedotčená)
• Zakázka #2: HOTOVO (klon zakázky #1)
• Statistika: 2 zakázky, 2× dokončeno ✅ SPRÁVNĚ
```

### **Dopad na reporting:**
- ✅ **Zvýšení počtu zakázek** - správné číslo
- ✅ **Správné dokončené opravy** - každá oprava = samostatná zakázka
- ✅ **Historie zachována** - viditelné všechny opravy pro zákazníka
- ✅ **Propojení přes `original_reklamace_id`** - možnost filtrovat recidivy

---

## ⚡ PERFORMANCE DOPAD

### **Změny v dotazech:**

**Nový SELECT dotaz:**
```sql
-- get_original_documents.php
SELECT * FROM wgs_documents WHERE reklamace_id = :id
```
- ⚠️ Potenciální N+1 problém pokud se volá opakovaně
- ✅ Řešení: Endpoint se volá pouze při kliknutí na "Historie PDF" (vzácné)

**Nový INSERT dotaz:**
```sql
-- handleReopen()
INSERT INTO wgs_reklamace (25 sloupců) VALUES (...)
INSERT INTO wgs_notes (2× - do obou zakázek)
```
- ✅ Transakční bezpečnost
- ⚠️ Potenciálně pomalejší než UPDATE (ale akceptovatelné)

### **Frontend cache:**
- ⚠️ `WGS_DATA_CACHE` bude obsahovat více zakázek (klony)
- ✅ Dopad: Zanedbatelný (cache je stejně v paměti)

---

## 🔐 BEZPEČNOSTNÍ AUDIT

### **Nové bezpečnostní kontroly:**
- ✅ CSRF validace (děděná z hlavního bloku `save.php`)
- ✅ Autentizace: `is_admin` OR `user_id`
- ✅ Kontrola stavu: pouze `'done'` zakázky
- ✅ SQL injection: PDO prepared statements
- ✅ XSS ochrana: `htmlspecialchars()` v poznámkách

### **Nová útočná plocha:**
⚠️ **Možný útok: Spam klonování**
- **Scénář:** Útočník opakovaně volá `action: 'reopen'` na stejnou zakázku
- **Dopad:** Vytvoření desítek/stovek klonů
- **Řešení:** **CHYBÍ** rate limiting na `handleReopen()`
- **Doporučení:** Přidat rate limiting 5 pokusů/hodinu
- **Závažnost:** 🟡 **STŘEDNÍ**

---

## ✅ DOPORUČENÍ PŘED MERGEM

### **POVINNÉ (blokující):**
1. 🔴 **SPUSTIT SQL MIGRACI** `pridej_original_reklamace_id.php`
2. 🔴 **KONTROLA `load.php`** - přidat `original_reklamace_id` do SELECT dotazu
3. 🔴 **TESTOVÁNÍ** - projít všechny testovací scénáře
4. 🔴 **BACKUP DATABÁZE** před deploy

### **DOPORUČENÉ (neblokující):**
1. 🟡 **Přidat rate limiting** na `handleReopen()` (5 pokusů/hod)
2. 🟡 **Dokumentace** pro uživatele - vysvětlit nové chování "Znovu otevřít"
3. 🟡 **Monitoring** - sledovat počet klonovaných zakázek první týden
4. 🟡 **Kontrola tabulky `wgs_documents`** - existuje?

### **VOLITELNÉ (optimalizace):**
1. 🟢 Přidat `original_reklamace_id` do indexu `load.php` WHERE podmínky
2. 🟢 Cache PDF dokumentů pro rychlejší zobrazení historie
3. 🟢 Přidat analytiku: kolik zakázek je klonů?

---

## 📞 KONTAKT V PŘÍPADĚ PROBLÉMŮ

**Pokud po merge dojde k chybě:**

1. **Chyba:** "Unknown column 'original_reklamace_id'"
   - **Řešení:** Spustit `pridej_original_reklamace_id.php`

2. **Chyba:** Tlačítko "Historie PDF" se nezobrazuje
   - **Řešení:** Zkontrolovat `load.php` - vrací `original_reklamace_id`?

3. **Chyba:** PDF dokumenty nenalezeny
   - **Řešení:** Zkontrolovat existenci tabulky `wgs_documents`

4. **Chyba:** Klonování selže
   - **Řešení:** Zkontrolovat logy `/logs/php_errors.log`

---

## 📊 SHRNUTÍ AUDITU

| Aspekt | Hodnocení | Poznámka |
|--------|-----------|----------|
| **Zpětná kompatibilita** | ⚠️ **ČÁSTEČNÁ** | Funkce `reopenOrder()` změnila chování (záměrně) |
| **Bezpečnost** | ✅ **DOBRÁ** | CSRF, autentizace, SQL injection OK. Chybí rate limiting. |
| **Performance** | ✅ **DOBRÁ** | Zanedbatelný dopad |
| **Databázové změny** | ⚠️ **STŘEDNÍ RIZIKO** | Vyžaduje migraci PŘED deploy |
| **Testovatelnost** | ✅ **DOBRÁ** | Jasné testovací scénáře |
| **Dokumentace** | ✅ **VÝBORNÁ** | Kompletní dokumentace změn |

---

## ✅ ZÁVĚR

**DOPORUČENÍ:**
✅ **BEZPEČNÉ K MERGE** - **PO SPLNĚNÍ PODMÍNEK:**

1. ✅ Spustit SQL migraci
2. ✅ Zkontrolovat `load.php`
3. ✅ Provést testování (minimálně scénáře 1, 2, 3, 4)
4. ✅ Vytvořit backup DB

**Celkový dopad:** ⚠️ **STŘEDNÍ až VYSOKÝ** (významná změna business logiky)

**Benefit:** ✅ **VYSOKÝ** (správné statistiky, zachovaná historie)

---

**Vypracoval:** Claude AI
**Schválil:** _Radek Zikmund_
**Datum auditu:** 2025-11-24
