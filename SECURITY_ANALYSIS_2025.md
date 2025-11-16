# BEZPEČNOSTNÍ ANALÝZA AUTENTIZACE A SESSIONS
## White Glove Service - Detailní Report
**Datum analýzy:** 2025-11-16  
**Analyzátor:** Security Code Review

---

## TOP 10 NEJKRITIČTĚJŠÍCH SECURITY ISSUES

### 1. **KRITICKÉ: Chybí brute-force protection pro user login**
**Severity:** CRITICAL (9.8/10)  
**File:** `/home/user/moje-stranky/app/controllers/login_controller.php` (řádky 105-129)

**Problém:**
- User login v `handleUserLogin()` nemá `checkRateLimit()` call
- Pouze admin login má rate limiting (5 pokusů/900 sekund)
- Útočník může provádět neomezené přihlašovací pokusy
- Je možné brute-forcit hesla uživatelů (238 miliard kombinací za sekundu)

**Impact:** Vysoké - Přímý útok na všechny uživatelské účty  
**Exploitability:** Triviální - Jednoduchý script

**Quick Fix:**
```php
// V handleUserLogin(), před SQL query:
$identifier = $_SERVER['REMOTE_ADDR'] . ':' . $email;
$rate = checkRateLimit('user_login_' . $identifier, 5, 900);
if (!$rate['allowed']) {
    recordLoginAttempt('user_login_' . $identifier);
    respondError('Příliš mnoho pokusů. Zkuste to znovu za ' . 
        ceil($rate['retry_after'] / 60) . ' minut.', 429);
}
```

---

### 2. **VYSOKÉ RIZIKO: Client-side admin_login_attempts tracking**
**Severity:** HIGH (8.5/10)  
**File:** `/home/user/moje-stranky/assets/js/login.js` (řádky 98-134)

**Problém:**
- Admin login pokusy se počítají v `localStorage` (client-side)
- Útočník může localStorage smazat: `localStorage.removeItem('admin_login_attempts')`
- Recovery mód není chráněn rate limitingem
- Stejný problém v `login.js` řádka 100

**Exploit nástroj:**
```javascript
// V DevTools Console:
localStorage.removeItem('admin_login_attempts')
// Pokusit se znovu - counter je resetován!
```

**Impact:** Vysoké - Obejití rate limitingu  
**Exploitability:** Velmi snadné - Jen JavaScript

---

### 3. **VYSOKÉ RIZIKO: Slabé frontend password requirements**
**Severity:** HIGH (8.0/10)  
**Files:**
- Frontend: `/home/user/moje-stranky/assets/js/registration.js` (řádka 46)
- Backend: `/home/user/moje-stranky/config/config.php` (řádky 200-241)

**Mismatch:**
- Frontend: Přijímá 8+ znaků
- Backend: Vyžaduje 12+ znaků + velké/malé písmeno + číslo + speciální znak

**Problem Code:**
```javascript
// registration.js řádka 46
if (password.length < 8) {
    showNotification('Heslo musí mít alespoň 8 znaků', 'error');
}
// Chybí: kontrola na velké/malé písmeno, čísla, speciální znaky!
```

**Impact:** Střední - UX problém, ale backend je chráněný  
**Exploitability:** Střední - Uživatel vidí slabší požadavek

---

### 4. **VYSOKÉ RIZIKO: Session cookie SameSite=Lax**
**Severity:** HIGH (7.8/10)  
**File:** `/home/user/moje-stranky/init.php` (řádka 62)

**Problém:**
```php
ini_set('session.cookie_samesite', 'Lax');  // ✗ Mělo by být 'Strict'!
```

**Risk:** `Lax` umožňuje cookie v cross-site TOP-level navigaci  
**Fix:**
```php
ini_set('session.cookie_samesite', 'Strict');  // ✓ Lepší CSRF ochrana
```

---

### 5. **VYSOKÉ RIZIKO: CSRF token se neobnovuje**
**Severity:** HIGH (7.5/10)  
**File:** `/home/user/moje-stranky/includes/csrf_helper.php` (řádky 17-22)

**Problém:**
```php
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];  // ← Stejný token po dobu session!
}
```

Token by se měl obnovit:
- Po přihlášení
- Po odhlášení
- Pravidelně (každých 15 minut)
- Po důležitých akcích

---

### 6. **VYSOKÉ RIZIKO: Absence inactivity timeout**
**Severity:** HIGH (7.2/10)  
**File:** `/home/user/moje-stranky/init.php` (řádky 63-64)

**Problém:**
```php
ini_set('session.gc_maxlifetime', 3600);      // Jen absolutní timeout (1 hodina)
ini_set('session.cookie_lifetime', 3600);
// Chybí: inactivity timeout - session trvá bez ohledu na aktivitu
```

**Risk:** Neopuštěná okna = aktivní session 60 minut  
**Fix:** Přidat `last_activity` tracking v každém controlleru

---

### 7. **STŘEDNĚ VYSOKÉ RIZIKO: Login bez CSRF token refresh**
**Severity:** MEDIUM-HIGH (7.0/10)  
**File:** `/home/user/moje-stranky/app/controllers/login_controller.php` (řádka 136)

**Problém:**
Po `session_regenerate_id(true)` se CSRF token neobnovuje:
```php
// ✓ Session ID se regeneruje
session_regenerate_id(true);

// ✗ CSRF token zůstává stejný
// Mělo by: regenerateCSRFToken()
```

---

### 8. **STŘEDNĚ VYSOKÉ RIZIKO: Logout bez CSRF ochrany**
**Severity:** MEDIUM-HIGH (6.8/10)  
**File:** `/home/user/moje-stranky/logout.php` (celý soubor)

**Problém:**
```php
<?php
session_start();
$_SESSION = [];
// ✗ Bez validateCSRFToken() 
// ✗ Bez require_once 'init.php'
session_destroy();
header('Location: login.php');
```

**Risk:** Logout CSRF - `<img src="logout.php">` na cizí stránce  
**Fix:** Vyžadovat POST s CSRF tokenem

---

### 9. **STŘEDNĚ VYSOKÉ RIZIKO: Slabý admin rate limiting**
**Severity:** MEDIUM-HIGH (6.5/10)  
**File:** `/home/user/moje-stranky/app/controllers/login_controller.php` (řádka 62)

**Problém:**
```php
$rate = checkRateLimit('admin_login_' . $identifier, 5, 900);
// 5 pokusů za 15 minut = poměrně vysoké
```

**Doporučení:** 3 pokusy za 10 minut + 2FA/MFA

---

### 10. **NÍZKÉ-STŘEDNÍ RIZIKO: Absence session fingerprinting**
**Severity:** MEDIUM (6.0/10)  
**File:** `/home/user/moje-stranky/includes/user_session_check.php`

**Problém:**
Session se nevaliduje na User-Agent nebo IP:
```php
// ✗ Bez kontroly na User-Agent změnu
// ✗ Bez kontroly na IP adresu
// Session hijacking je možný!
```

**Risk:** Pokud je cookie украden, útočník se může přihlásit  
**Fix:** Validovat User-Agent a IP při každém requestu

---

## REFERENČNÍ TABULKA SOUBORŮ

| Soubor | Řádky | Problém |
|--------|-------|---------|
| `app/controllers/login_controller.php` | 105-129 | Chybí brute-force pro user login |
| `assets/js/login.js` | 98-134 | Client-side login attempts |
| `assets/js/registration.js` | 46 | Slabé password validace |
| `init.php` | 62 | SameSite=Lax |
| `init.php` | 63-64 | Absence inactivity timeout |
| `includes/csrf_helper.php` | 17-22 | CSRF token se neobnovuje |
| `app/controllers/login_controller.php` | 136 | Nový CSRF token po login |
| `logout.php` | 1-31 | Logout bez CSRF ochrany |
| `config/config.php` | 200-241 | isStrongPassword() funkce |
| `includes/user_session_check.php` | 12-26 | Session fingerprinting |

---

## ACTION ITEMS - PRIORITY ORDER

### IMMEDIATE (24 hodin)
- [ ] Přidat rate limiting do user login (login_controller.php)
- [ ] Přesunout admin_login_attempts do session/cache (ne localStorage)
- [ ] Synchronizovat frontend password validation

### HIGH (1 týden)
- [ ] Změnit SameSite na 'Strict'
- [ ] Implementovat CSRF token regeneraci
- [ ] Přidat inactivity timeout
- [ ] Obnovit CSRF token po login

### MEDIUM (2 týdny)
- [ ] Chraňte logout CSRF tokenem
- [ ] Zvyšte rate limiting pro admin
- [ ] Přidejte session fingerprinting

### NICE-TO-HAVE (1 měsíc)
- [ ] Přidat 2FA/MFA
- [ ] Email notifications na login
- [ ] Account lockout
- [ ] Aktualizovat password reset

