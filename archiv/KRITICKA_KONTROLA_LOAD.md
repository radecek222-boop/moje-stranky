# ⚠️ KRITICKÁ KONTROLA: load.php

## 🔍 ANALÝZA SELECT DOTAZU

**Soubor:** `/home/user/moje-stranky/app/controllers/load.php`
**Řádky:** 119-129

### SELECT dotaz:
```sql
SELECT
    r.*,
    r.id as claim_id,
    u.name as created_by_name
FROM wgs_reklamace r
LEFT JOIN wgs_users u ON r.created_by = u.id
$whereClause
ORDER BY r.created_at DESC
LIMIT :limit OFFSET :offset
```

---

## ✅ VÝSLEDEK: **KOMPATIBILNÍ**

### Důvod:
- Dotaz používá **`r.*`** - což znamená vrací **VŠECHNY sloupce** z tabulky `wgs_reklamace`
- Pokud bude sloupec `original_reklamace_id` existovat v tabulce, **automaticky se vrátí**
- **NENÍ potřeba** měnit `load.php`

---

## ⚠️ PODMÍNKA:

**Aby feature fungovala:**
1. ✅ **SQL migrace MUSÍ být spuštěna PŘED testem**
   - Spustit: `pridej_original_reklamace_id.php`
   - Výsledek: Sloupec `original_reklamace_id` přidán do `wgs_reklamace`

2. ✅ **Hard reload frontendu po deploy**
   - Vyčistit cache prohlížeče (Ctrl+Shift+R)
   - Důvod: Načíst nová data včetně `original_reklamace_id`

---

## 📋 TESTOVACÍ KONTROLA:

### Před merge - ověřit:
```javascript
// V konzoli prohlížeče po načtení seznam.php:
console.log(WGS_DATA_CACHE[0]);

// Očekávaný výstup (po migraci):
{
  id: 1,
  reklamace_id: "WGS/2025/24-11/00001",
  original_reklamace_id: null,  // ← Toto by mělo být viditelné!
  jmeno: "Jan Novák",
  // ... další sloupce
}
```

### Pokud `original_reklamace_id` chybí:
- ⚠️ **PROBLÉM:** SQL migrace nebyla spuštěna
- ✅ **ŘEŠENÍ:** Spustit `pridej_original_reklamace_id.php`

---

## 🎯 ZÁVĚR:

✅ **`load.php` JE KOMPATIBILNÍ - žádné změny nejsou potřeba**

⚠️ **KRITICKÁ PODMÍNKA:** SQL migrace MUSÍ být spuštěna PŘED testem

---

**Status:** ✅ **OVĚŘENO**
**Datum:** 2025-11-24
