# Data Integrity Audit - Souhrn Problémů

**Provedeno:** 2025-11-14  
**Projekt:** /home/user/moje-stranky  
**Typ:** Kompletní databázová analýza integrity

---

## 📊 Statistika Problémů

| Kategorie | Počet | Severity |
|-----------|-------|----------|
| Chybějící transakce | 9 | 🔴 KRITICKÉ |
| Race conditions | 1 | 🟠 VYSOKÁ |
| Orphan files riziko | 2 | 🔴 KRITICKÉ |
| Chybějící FK | 4 | 🟡 STŘEDNÍ |
| Loop bez transakce | 2 | 🟠 VYSOKÁ |
| **CELKEM** | **18** | |

---

## 🔴 KRITICKÉ PROBLÉMY (Musí se opravit)

### 1. **CREATE Reklamace bez transakce**
- **Soubor:** `app/controllers/save.php:429`
- **Risk:** Data corruption - orphan workflow ID
- **Oprava:** Přidej `beginTransaction()` před INSERT

### 2. **Fotky - File-first approach**
- **Soubor:** `app/controllers/save_photos.php:168`
- **Risk:** Orphan files na disku pokud DB INSERT selže
- **Oprava:** Reorder - DB INSERT první, pak file write

### 3. **PDF - File-first approach**
- **Soubor:** `api/protokol_api.php:177`
- **Risk:** Orphan PDF pokud DB INSERT/UPDATE selže
- **Oprava:** Reorder - DB INSERT/UPDATE první, pak file write

### 4. **Email queue - Status transitions**
- **Soubor:** `includes/EmailQueue.php:258`
- **Risk:** Emaily se zamrznou v stavu 'sending'
- **Oprava:** Přidej transakci kolem status changes

### 5. **GitHub webhook - Orphaned records**
- **Soubor:** `api/github_webhook.php:168`
- **Risk:** Webhook bez action reference v DB
- **Oprava:** Přidej transakci kolem 2 INSERTs

---

## 🟠 VYSOKÁ PRIORITA (Fix soon)

### 6. **Race condition - Email registration**
- **Soubor:** `app/controllers/registration_controller.php:62`
- **Risk:** Duplicate key error s 2 paralelními requesty
- **Oprava:** `SELECT ... FOR UPDATE` místo `SELECT COUNT(*)`

### 7. **Loop update bez transakce**
- **Soubor:** `includes/control_center_tools.php:38`
- **Risk:** Partial state persistence
- **Oprava:** Transakce kolem loopa

### 8. **Email cron bez transakce**
- **Soubor:** `cron/process-email-queue.php:102`
- **Risk:** Emaily v nekonzistentním stavu
- **Oprava:** Transakce kolem status updates

### 9. **Notes API bez transakce**
- **Soubor:** `api/notes_api.php:119`
- **Risk:** INSERT/DELETE bez atomicity
- **Oprava:** Přidej `beginTransaction()`

### 10. **Admin API - Create key bez transakce**
- **Soubor:** `api/admin_api.php:149`
- **Risk:** Generovaný klíč ale nebyl uložen
- **Oprava:** Přidej `beginTransaction()`

---

## 🟡 STŘEDNÍ PRIORITA

### 11. **Theme update loop bez transakce**
- **Soubor:** `api/control_center_api.php:141`
- **Risk:** Partial theme configuration
- **Oprava:** Transakce kolem loopa

### 12. **Chybějící FK constraints**
- **Tabulky:** `wgs_photos`, `wgs_documents`, `wgs_notes`, `wgs_notifications`
- **Risk:** Orphan records bez referenčního kontrolu
- **Oprava:** Přidat FK `ON DELETE CASCADE`

---

## ✅ Co je správně implementováno

- ✅ `registration_controller.php` - Správné transactions
- ✅ `delete_reklamace.php` - Cascading deletes (ale bez FK constraints)
- ✅ `save.php` UPDATE - Transactionized
- ✅ Email validation - FILTER_VALIDATE_EMAIL
- ✅ Date validation - checkdate()
- ✅ GDPR consent tracking
- ✅ FK constraints na action tabulkách
- ✅ UNIQUE constraints v DB

---

## 📋 Soubory k opravě (v pořadí priority)

### Kritické (Today):
1. `app/controllers/save.php` - Add transaction CREATE
2. `app/controllers/save_photos.php` - Reorder file ops
3. `api/protokol_api.php` - Reorder file ops
4. `api/github_webhook.php` - Add transaction
5. `includes/EmailQueue.php` - Add transaction

### Vysoká (This week):
6. `app/controllers/registration_controller.php` - SELECT FOR UPDATE
7. `includes/control_center_tools.php` - Add transaction loop
8. `cron/process-email-queue.php` - Add transaction
9. `api/notes_api.php` - Add transaction
10. `api/admin_api.php` - Add transaction

### Střední (This sprint):
11. `api/control_center_api.php` - Add transaction loop
12. CREATE migrations - Add FK constraints

---

## 🔧 Doporučené Řešení

### Krátko (Next 24 hodin)
- [ ] Oprav `save.php` CREATE - add transaction
- [ ] Oprav `save_photos.php` - reorder operations
- [ ] Oprav `protokol_api.php` - reorder operations
- [ ] Oprav `github_webhook.php` - add transaction
- [ ] Oprav `EmailQueue` - add transaction

### Středně-dlouhý (Next week)
- [ ] Oprav `registration_controller.php` - SELECT FOR UPDATE
- [ ] Oprav `control_center_tools.php` - transaction loop
- [ ] Oprav `process-email-queue.php` - transaction
- [ ] Oprav `notes_api.php` - transaction
- [ ] Oprav `admin_api.php` - transaction

### Dlouhý (Refactoring sprint)
- [ ] Přidej FK constraints na orphan-prone tabulky
- [ ] Odstraň manuální cascades (nahraď FK constraints)
- [ ] Vytvoj file atomicity helper function
- [ ] Audit všech ostatních file operations

---

## 📖 Detailní Dokumentace

Podrobné informace jsou v:
- `DATA_INTEGRITY_AUDIT_CRITICAL.txt` - Detailní analýza s kódovými příklady
- Tento soubor - Souhrn a prioritizace

---

**Status:** 🔴 CRITICAL - Vyžaduje okamžitou pozornost  
**Doporučená Akce:** Opravit kritické problémy v příštích 24 hodinách
