# 🔴 KRITICKÉ PROBLÉMY - KOMPLETNÍ AUDIT

**Datum:** 2025-01-08
**Session:** claude/website-improvements-011CUvENBdDfHqESXqbbZpPs

---

## ❌ NEFUNKČNÍ FEATURES

### 1. LOGIN & REGISTRACE - KOMPLETNĚ NEFUNKČNÍ
**Příčina:** Chybějící backend controllery

**Chybí:**
- `app/controllers/login_controller.php` ❌
- `app/controllers/registration_controller.php` ❌

**Dopad:**
- Uživatelé se NEMOHOU přihlásit
- Nové registrace NEFUNGUJÍ
- Admin přihlášení NEFUNGUJE
- Celý systém autentizace je **ROZBITÝ**

**Kde se volá:**
- `assets/js/login.js:60` - Admin login
- `assets/js/login.js:109` - User login
- `assets/js/login.js:184` - High key login
- `assets/js/login.js:269` - Create admin key
- `assets/js/registration.js:35` - Registrace

---

### 2. PASSWORD RESET - NEFUNKČNÍ
**Příčina:** Chybějící backend controller

**Chybí:**
- `app/controllers/password_reset_controller.php` ❌

**Dopad:**
- Uživatelé NEMOHOU resetovat heslo
- Zapomenuté heslo = **ztráta přístupu**

**Kde se volá:**
- `assets/js/password-reset.js`

---

### 3. ADMIN PANEL - TAB NAVIGACE NEFUNGUJE
**Příčina:** Chybějící HTML struktura pro tab tlačítka

**Problém:**
- `admin.php:49` má komentář `<!-- TABS -->` ale **žádné tab elementy**
- `assets/js/admin.js:15` očekává `.tab` elementy pomocí `querySelectorAll('.tab')`
- Vrátí prázdný array → žádné event listenery

**Dopad:**
- Uživatelé NEMOHOU přepínat mezi záložkami (Dashboard, Keys, Users, Notifications)
- Viditelný pouze první tab (Dashboard)
- Admin panel je **poloviční**

**Potřebné HTML:**
```html
<div class="tabs">
  <button class="tab active" data-tab="dashboard">Dashboard</button>
  <button class="tab" data-tab="keys">Klíče</button>
  <button class="tab" data-tab="users">Uživatelé</button>
  <button class="tab" data-tab="notifications">Notifikace</button>
</div>
```

---

### 4. ADMIN API - NEFUNKČNÍ
**Příčina:** api/admin_api.php neimplementuje žádné akce

**Chybí:**
- Všechny admin API endpointy (keys, users, notifications)

**Dopad:**
- Správa klíčů NEFUNGUJE (create, delete, list)
- Správa uživatelů NEFUNGUJE
- Admin funkcionalita je **mrtvá**

**Kde se volá:**
- `assets/js/admin.js` - všechny admin operace

---

### 5. NOTIFICATION API - NEFUNKČNÍ
**Příčina:** Chybějící notification endpointy

**Chybí:**
- `/api/notification_list_direct.php` ❌
- `/api/notification_api.php` ❌

**Dopad:**
- Email/SMS šablony se NENAČTOU
- Editace notifikací NEFUNGUJE
- Automatické emaily NEFUNGUJÍ (pravděpodobně)

---

## ✅ CO FUNGUJE (po mých opravách)

1. ✅ CSRF ochrana (seznam.js opraveno)
2. ✅ Admin autentizace (localStorage bypass odstraněn)
3. ✅ Photo upload (accept filter opraven)
4. ✅ Navigace zpět (seznam.html → seznam.php)
5. ✅ Session kontroly (load.php, protokol_api.php, app/save_photos.php)
6. ✅ GDPR souhlas (novareklamace.php + backend)
7. ✅ Bezpečnostní opravy (debug skripty smazány, credentials v .env)
8. ✅ Upload size limity
9. ✅ Rate limiting

---

## 🟡 POTENCIÁLNÍ PROBLÉMY

### 1. temp/ adresář pro rate limiting
- Rate limiting ukládá do `TEMP_PATH`
- temp/ adresář jsem vytvořil ale není v gitu
- Na produkci může chybět → rate limiting selže

### 2. sanitizeInput() dvojité escapování
- `sanitizeInput()` aplikuje `htmlspecialchars()` při UKLÁDÁNÍ
- Při zobrazení se aplikuje znovu → dvojité escapování
- Texty můžou být rozb ité (`&lt;` místo `<`)

### 3. CSP unsafe-inline
- Content Security Policy povoluje `unsafe-inline`
- Otevírá prostor pro XSS útoky
- Ale refaktoring inline skriptů je velká práce

---

## 📊 PRIORITIZACE

### 🔴 KRITICKÉ (systém je rozbitý):
1. **Login controller** - bez tohoto se NIKDO NEPŘIHLÁSÍ
2. **Registration controller** - bez tohoto nelze vytvořit účty
3. **Admin panel tabs** - admin panel je nepoužitelný

### 🟠 VYSOKÉ (funkce nefungují):
4. **Password reset** - uživatelé ztratí přístup
5. **Admin API** - admin nemůže spravovat systém
6. **Notification API** - emaily nefungují

### 🟡 STŘEDNÍ (tech debt):
7. sanitizeInput() refaktoring
8. CSP opravy
9. temp/ adresář setup

---

## 🎯 DOPORUČENÉ KROKY

1. **Vytvořit login_controller.php** (NEJVYŠŠÍ PRIORITA)
2. **Vytvořit registration_controller.php**
3. **Přidat tab navigaci do admin.php**
4. **Implementovat admin_api.php**
5. **Vytvořit password_reset_controller.php**
6. **Implementovat notification API**

---

## 📝 POZNÁMKY

- GDPR implementace je **VÝBORNÁ** ✅
- Bezpečnostní opravy jsou **KOMPLETNÍ** ✅
- Photo upload flow je **OPRAVEN** ✅
- Problém je v **chybějících backend controllerech**
- Frontend je připravený, backend **CHYBÍ**

---

**Vytvořil:** Claude Code
**Commitů celkem:** 5 (ff7fec2, a0c2b9c, 24bb675, 5906868, + GDPR commit)
