# ✅ PRE-MERGE CHECKLIST - Klonování zakázek

**Branch:** `claude/review-page-architecture-01XTiXKwR8r4xo1QWUnp2hbg`
**Feature:** Klonování dokončených zakázek místo přepisování

---

## 🔴 KRITICKÉ - BLOKUJÍCÍ MERGE

### 1. ⚠️ SPUSTIT SQL MIGRACI (POVINNÉ!)

```bash
URL: https://www.wgs-service.cz/pridej_original_reklamace_id.php

Kroky:
1. Přihlásit se jako admin
2. Otevřít URL výše
3. Zkontrolovat náhled změn
4. Kliknout "SPUSTIT MIGRACI"
5. Ověřit: "✓ MIGRACE ÚSPĚŠNĚ DOKONČENA"
```

**Co migrace dělá:**
- Přidá sloupec `original_reklamace_id VARCHAR(50) NULL`
- Přidá index `idx_original_reklamace_id`
- Neovlivní existující data (všechny hodnoty budou NULL)

**Proč je to kritické:**
- ❌ Bez migrace: `PDOException: Unknown column 'original_reklamace_id'`
- ❌ Klonování nefunguje
- ✅ S migrací: Vše funguje

---

### 2. 🧪 TESTOVÁNÍ (POVINNÉ!)

**Minimální testovací scénář:**

```
1. Najít zakázku ve stavu HOTOVO (zelená karta)
2. Kliknout na kartu → Detail
3. Kliknout "Znovu otevřít"
4. Potvrdit dialog

Očekávaný výsledek:
✅ Alert: "✓ NOVÁ ZAKÁZKA VYTVOŘENA"
✅ Nová žlutá karta v seznamu (nové číslo)
✅ Původní zelená karta ZŮSTÁVÁ (stav HOTOVO)
✅ Kliknutí na novou kartu → tlačítko "📚 Historie PDF"
```

**Pokud selže:**
- Zkontrolovat: Byla spuštěna migrace?
- Zkontrolovat: `/logs/php_errors.log`

---

### 3. 💾 BACKUP DATABÁZE (POVINNÉ!)

```bash
# Před merge vytvořit backup
mysqldump -u [user] -p wgs-servicecz01 > backup_before_reopen_$(date +%Y%m%d).sql
```

**Důvod:** Přidání nového sloupce + změna logiky

---

## 🟡 DOPORUČENÉ - NEBLOKUJÍCÍ

### 4. 📊 Kontrola load.php

✅ **OVĚŘENO:** `load.php` používá `SELECT r.*` → automaticky vrátí `original_reklamace_id`

**Nemusíte měnit**, ale doporučuji zkontrolovat konzoli prohlížeče:
```javascript
console.log(WGS_DATA_CACHE[0]);
// Mělo by obsahovat: original_reklamace_id: null
```

---

### 5. 🔄 Hard reload po deploy

**Proč:** Vyčistit JavaScript cache

**Jak:**
- Chrome/Firefox: `Ctrl + Shift + R`
- Nebo: F12 → Network → Disable cache → F5

---

### 6. 📈 Monitoring prvních 7 dní

**Co sledovat:**
- Počet klonovaných zakázek (kolik má `original_reklamace_id != NULL`)
- Chybové logy `/logs/php_errors.log`
- Uživatelská zpětná vazba

---

## ⚠️ ZNÁMÁ RIZIKA

| Riziko | Pravděpodobnost | Dopad | Řešení |
|--------|-----------------|-------|--------|
| Migrace nespuštěna před merge | Vysoká | 🔴 Kritická chyba | Spustit migraci PŘED merge |
| Uživatelé zmateni novým chováním | Střední | 🟡 Stížnosti | Školení/dokumentace |
| Cache prohlížeče stará data | Střední | 🟡 Tlačítko Historie nefunguje | Hard reload (Ctrl+Shift+R) |

---

## 📊 OČEKÁVANÝ DOPAD

### Před změnou (špatně):
```
Zákazník A: 1 zakázka, 0× dokončeno (přepsána)
```

### Po změně (správně):
```
Zákazník A:
  - Zakázka #1: HOTOVO
  - Zakázka #2: HOTOVO (klon #1)

Celkem: 2 zakázky, 2× dokončeno ✅
```

---

## ✅ MERGE READY PODMÍNKY

- [ ] SQL migrace spuštěna a úspěšná
- [ ] Test klonování prošel (nová karta vytvořena)
- [ ] Původní zakázka zůstala HOTOVO (nezměnila stav)
- [ ] Tlačítko "Historie PDF" viditelné v nové zakázce
- [ ] Backup databáze vytvořen
- [ ] Logy zkontrolovány (žádné chyby)

**Pokud všechny checkboxy ✅ → SAFE TO MERGE**

---

## 📞 V PŘÍPADĚ PROBLÉMŮ

### Chyba: "Unknown column 'original_reklamace_id'"
**Řešení:** Spustit `pridej_original_reklamace_id.php`

### Chyba: Tlačítko "Historie PDF" se nezobrazuje
**Řešení:** Hard reload prohlížeče (Ctrl+Shift+R)

### Chyba: Klonování selže bez chybové hlášky
**Řešení:** Zkontrolovat `/logs/php_errors.log`

---

**Vypracoval:** Claude AI
**Datum:** 2025-11-24
**Status:** ⏳ ČEKÁ NA MERGE
