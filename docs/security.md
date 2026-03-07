# BEZPEČNOSTNÍ AUDIT
## White Glove Service (WGS) - Bezpečnostní analýza

**Datum auditu:** 2026-03-07
**Stav:** Průběžná aktualizace

---

## EXECUTIVE SUMMARY

Aplikace má **vyspělou bezpečnostní infrastrukturu** (CSRF, rate limiting, CSP, security headers, audit logging). Po provedených opravách v 2026-03-07 je stav bezpečnosti:

| Oblast | Stav |
|--------|------|
| CSRF ochrana | Pokryta po opravách |
| SQL injection | Opraveno v hry_api.php |
| Security headers | Velmi dobré |
| Rate limiting | Funkční |
| Session bezpečnost | Velmi dobré |
| Auth logika | Solidní |
| Upload bezpečnost | Střední riziko |
| Audit logging | Funkční |

**Celkový verdikt:** PODMÍNĚNÉ GO — Kritické problémy opraveny, zbývají střední a nízká rizika.

---

## 1. OPRAVENÉ BEZPEČNOSTNÍ PROBLÉMY (2026-03-07)

### 1.1 SQL Injection v /api/hry_api.php (OPRAVENO)
- **Závažnost:** Vysoká (ale mitigovaná int castem)
- **Problém:** `$pdo->exec("... WHERE id = $zpravaId")` — přímá interpolace
- **Oprava:** Nahrazeno prepared statement s bound parameters
- **Soubor:** `/api/hry_api.php`, řádky 249, 258

### 1.2 Chybějící CSRF v /admin/email_queue.php (OPRAVENO)
- **Závažnost:** Střední (admin-only, ale CSRF útok byl možný)
- **Problém:** POST handler bez `validateCSRFToken()`
- **Oprava:** Přidán CSRF token do form + validace v POST handleru
- **Soubor:** `/admin/email_queue.php`

### 1.3 Chybějící CSRF v /admin/smtp_settings.php (OPRAVENO)
- **Závažnost:** Střední (SMTP konfigurace mohla být změněna CSRF útokem)
- **Problém:** POST handler bez `validateCSRFToken()`
- **Oprava:** Přidán CSRF token do obou form + validace
- **Soubor:** `/admin/smtp_settings.php`

### 1.4 Chybějící CSRF v /admin/install_email_system.php (OPRAVENO)
- **Závažnost:** Střední (instalace systému mohla být spuštěna CSRF útokem)
- **Problém:** POST handler bez CSRF validace
- **Oprava:** Přidán CSRF token do form + validace
- **Soubor:** `/admin/install_email_system.php`

### 1.5 GET-modifying endpoint /api/preloz_aktualitu.php (OPRAVENO)
- **Závažnost:** Střední (data-modifying endpoint přijímal GET požadavky)
- **Problém:** `$_REQUEST` - přijímal GET i POST, chyběla CSRF validace
- **Oprava:** Omezeno pouze na POST + přidána CSRF validace
- **Soubor:** `/api/preloz_aktualitu.php`

---

## 2. BEZPEČNOSTNÍ INFRASTRUKTURA

### 2.1 CSRF Protection (`/includes/csrf_helper.php`)
- **Stav:** Vynikající
- `bin2hex(random_bytes(32))` - kryptograficky bezpečný token
- `hash_equals()` - prevence timing attacks
- Ochrana proti array injection (line 56-59)
- `requireCSRF()` helper pro snadné použití
- **POUŽÍVAT:** Ve všech POST handlerech bez výjimky

### 2.2 Security Headers (`/includes/security_headers.php`)
- **Stav:** Vynikající
- `X-Frame-Options: SAMEORIGIN` - prevence clickjacking
- `X-Content-Type-Options: nosniff` - prevence MIME sniffing
- `X-XSS-Protection: 1; mode=block`
- `Strict-Transport-Security` - HSTS v produkci
- `Content-Security-Policy` - přísné CSP s whitelistem
- `Permissions-Policy` - omezení camera/microphone

### 2.3 Rate Limiting (`/includes/rate_limiter.php`)
- **Stav:** Dobrý
- DB-backed rate limiter s `FOR UPDATE` zámky (prevence race condition)
- Transakce s commit/rollback
- Cleanup starých záznamů
- Používán v: login, API endpointech, citlivých operacích

### 2.4 Session Security (`/init.php`)
- **Stav:** Vynikající
- `secure`: true v produkci
- `httponly`: true
- `samesite`: Lax
- Inactivity timeout: 30 minut
- Session ID regenerace po timeoutu
- PWA-aware (7 dní pro PWA, 0 pro browser)

### 2.5 Audit Logging
- **Stav:** Dobrý
- Admin akce jsou logovány
- Security eventy jsou logovány do `/logs/security.log`
- Strukturovaný audit log

---

## 3. ZBÝVAJÍCÍ BEZPEČNOSTNÍ RIZIKA

### 3.1 Upload Validace — Střední riziko
- **Soubory:** `api/documents_api.php`, `api/video_api.php`, `api/notes_api.php`
- **Problém:** Chybí explicitní MIME type whitelist a path traversal check
- **Dopad:** Potenciální nahrání nebezpečných souborů
- **Doporučení:**
  ```php
  // Přidat MIME whitelist
  $povolene = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
  $skutecnyTyp = mime_content_type($_FILES['soubor']['tmp_name']);
  if (!in_array($skutecnyTyp, $povolene)) {
      sendJsonError('Nepodporovaný typ souboru');
  }
  // Path traversal protection
  $cesta = realpath($uploadDir . '/' . basename($nazevSouboru));
  if (!str_starts_with($cesta, realpath($uploadDir))) {
      sendJsonError('Neplatná cesta souboru');
  }
  ```

### 3.2 Dynamické SQL v Analytics — Nízké riziko
- **Soubory:** `analytics.php`, `api/statistiky_api.php`
- **Problém:** Dynamic `WHERE $whereObdobi` a `ORDER BY {$datumSloupec}`
- **Mitigace:** Proměnné jsou sestavovány z whitelisted hodnot v kódu
- **Doporučení:** Explicitně whitelist validovat s `in_array($sloupec, $povoleneSloupce)`

### 3.3 MD5/SHA1 pro cache klíče — Nízké riziko
- **Soubory:** `includes/translator.php` a další
- **Problém:** MD5/SHA1 jsou slabé hashovací funkce
- **Kontext:** Používány pouze pro cache klíče, ne pro hesla (hesla mají password_hash)
- **Doporučení:** Nahradit `sha256()` pro konzistenci, i když není nutné

### 3.4 Diagnostika a debug endpointy
- **Soubory:** `api/advanced_diagnostics_api.php`, `tools/` složka
- **Stav:** Ověřit zda jsou dostupné bez admin session
- **Doporučení:** Zajistit admin-only přístup ke všem diagnostickým nástrojům

---

## 4. BEZPEČNOSTNÍ DOPORUČENÍ

### Okamžité (HOTOVO)
- [x] SQL injection oprava v hry_api.php
- [x] CSRF v admin/email_queue.php
- [x] CSRF v admin/smtp_settings.php
- [x] CSRF v admin/install_email_system.php
- [x] CSRF a GET→POST v api/preloz_aktualitu.php

### Krátkodobé (tento měsíc)
- [ ] Přidat MIME type whitelist do upload handlerů
- [ ] Přidat path traversal check do upload handlerů
- [ ] Explicitní whitelist validace pro dynamické SQL sloupce

### Dlouhodobé (čtvrtletí)
- [ ] Centralizovaný upload handler class
- [ ] Automatický security scan v CI/CD pipeline
- [ ] Pravidelné penetrační testy
- [ ] Security log monitoring a alerting

---

## 5. AUTENTIZAČNÍ METODY

| Metoda | Použití | Validace |
|--------|---------|----------|
| Admin Key | Admin login | SHA256 hash v .env (ADMIN_KEY_HASH) |
| User Login | Běžní uživatelé | `password_verify()` + PASSWORD_DEFAULT |
| Registration Keys | Kontrola registrace | DB tabulka s usage limity |
| High Key | Rotace admin klíče | ADMIN_HIGH_KEY_HASH |
| Remember Me | Persistentní login | Bezpečný token v DB |

---

*Zpráva vygenerována bezpečnostním auditem 2026-03-07.*
