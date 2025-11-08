# 🗑️ WGS SERVICE - Mazání reklamací (pouze admin)

**Datum:** 7. listopadu 2025  
**Bezpečnost:** VYSOKÁ - pouze administrátoři

---

## 🎯 FUNKCE

Administrátoři mohou kompletně smazat reklamaci ze systému včetně:
- ✅ Záznamu v databázi
- ✅ Všech fotek
- ✅ PDF dokumentů
- ✅ Poznámek
- ✅ Notifikací
- ✅ Souborů z disku

---

## 🔒 BEZPEČNOSTNÍ OPATŘENÍ

### 1. Oprávnění
- ❌ Prodejce - NEMŮŽE mazat
- ❌ Technik - NEMŮŽE mazat
- ✅ Admin - MŮŽE mazat

### 2. Dvojité potvrzení
```
Krok 1: Confirm dialog s popisem co se smaže
Krok 2: Prompt - uživatel musí napsat číslo reklamace
```

### 3. Audit log
Každé smazání se loguje:
- Kdo smazal
- Kdy smazal
- Jakou reklamaci
- Kolik souborů bylo smazáno

---

## 🔄 TOK MAZÁNÍ
```
1. Admin otevře seznam.php
   ↓
2. Klikne na detail reklamace
   ↓
3. Vidí tlačítko "🗑️ Smazat reklamaci"
   ↓
4. Klikne na tlačítko
   ↓
5. Potvrzení 1: Confirm dialog
   ↓
6. Potvrzení 2: Napsat číslo reklamace
   ↓
7. POST na api/delete_reklamace.php
   ↓
8. Backend:
   - Kontrola oprávnění
   - Smazání z DB (transakce)
   - Smazání souborů
   - Zápis do audit logu
   ↓
9. Úspěch: Obnovit seznam
```

---

## 📁 SOUBORY

### Backend
- `api/delete_reklamace.php` - API endpoint

### Frontend
- `assets/js/seznam.js` - Funkce deleteReklamace()
- `assets/js/seznam-delete-patch.js` - Patch pro tlačítko
- `seznam.php` - Načítá patch

---

## 🔧 API

### POST api/delete_reklamace.php

**Request:**
```json
{
  "reklamace_id": "NBU25-555288-58"
}
```

**Response (success):**
```json
{
  "success": true,
  "message": "Reklamace úspěšně smazána",
  "deleted_files": 5
}
```

**Response (error):**
```json
{
  "success": false,
  "error": "Nedostatečná oprávnění"
}
```

---

## 🗄️ DATABÁZOVÉ OPERACE
```sql
-- 1. Získat ID reklamace
SELECT id FROM wgs_reklamace WHERE reklamace_id = ?

-- 2. Smazat fotky
DELETE FROM wgs_photos WHERE reklamace_id = ?

-- 3. Smazat dokumenty
DELETE FROM wgs_documents WHERE claim_id = ?

-- 4. Smazat poznámky
DELETE FROM wgs_notes WHERE claim_id = ?

-- 5. Smazat notifikace
DELETE FROM wgs_notifications WHERE claim_id = ?

-- 6. Smazat reklamaci
DELETE FROM wgs_reklamace WHERE reklamace_id = ?

-- 7. Logovat do audit logu
INSERT INTO wgs_audit_log (user_id, action, details, created_at)
VALUES (?, 'delete_reklamace', ?, NOW())
```

---

## 🧪 TESTOVÁNÍ

### Test 1: Pokus o smazání jako technik
```
Výsledek: ❌ Tlačítko se nezobrazí
```

### Test 2: Pokus o smazání jako admin
```
1. Vidět tlačítko "🗑️ Smazat reklamaci"
2. Kliknout
3. Potvrdit 1. dialog
4. Napsat číslo reklamace
5. Výsledek: ✅ Reklamace smazána
```

### Test 3: Zrušení mazání
```
1. Kliknout na "Smazat"
2. Kliknout "Zrušit" v dialogu
   NEBO
3. Napsat špatné číslo
Výsledek: ✅ Akce zrušena, nic se nesmaže
```

---

## 📊 CO SE SMAŽE

### Databáze
- ✅ wgs_reklamace (1 záznam)
- ✅ wgs_photos (N záznamů)
- ✅ wgs_documents (N záznamů)
- ✅ wgs_notes (N záznamů)
- ✅ wgs_notifications (N záznamů)

### Soubory
- ✅ `/uploads/photos/{reklamace_id}/*` (všechny fotky)
- ✅ `/uploads/photos/{reklamace_id}/` (prázdná složka)
- ✅ `/uploads/protokoly/{reklamace_id}.pdf`

### Audit Log
- ✅ Zápis do wgs_audit_log (zůstává pro historii)

---

## ⚠️ DŮLEŽITÉ

### CO SE NESMAŽE:
- ❌ Audit log - zůstává pro kontrolu
- ❌ Session data - zůstává
- ❌ User účty - zůstávají

### NELZE VRÁTIT ZPĚT!
Smazání je **PERMANENTNÍ**. Neexistuje žádný způsob jak obnovit smazanou reklamaci.

---

## 🐛 ŘEŠENÍ PROBLÉMŮ

### Tlačítko se nezobrazuje
```bash
# Zkontrolovat konzoli (F12)
# Mělo by být: ✅ Mazací tlačítko patch načten

# Zkontrolovat seznam.php
grep "seznam-delete-patch.js" seznam.php
```

### API vrací chybu 403
```bash
# Zkontrolovat session
# Uživatel musí být přihlášený jako admin

# Zkontrolovat v DB
mysql> SELECT user_id, role FROM wgs_users WHERE role='admin';
```

### Soubory se nesmazaly
```bash
# Zkontrolovat oprávnění
ls -la uploads/photos/
ls -la uploads/protokoly/

# Mělo by být: drwxr-xr-x
chmod -R 755 uploads/
```

---

**Status:** ✅ PLNĚ FUNKČNÍ  
**Bezpečnost:** ✅ VYSOKÁ  
**Testováno:** 7. listopadu 2025
