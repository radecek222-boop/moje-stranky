# Security & Infrastructure Improvements

Tento dokument popisuje nedávná bezpečnostní a infrastrukturní vylepšení projektu.

## 📅 Datum implementace
**2025-11-11**

---

## 🔒 Implementovaná vylepšení

### 1. **Security Headers** ✅
**Soubor:** `/includes/security_headers.php`

Přidány HTTP bezpečnostní hlavičky pro ochranu proti běžným útokům:

- **X-Frame-Options:** Ochrana proti clickjackingu
- **X-Content-Type-Options:** Prevence MIME type sniffing
- **X-XSS-Protection:** XSS ochrana pro starší prohlížeče
- **Referrer-Policy:** Kontrola posílání referrer informací
- **Permissions-Policy:** Zakázání nepotřebných browser features
- **Strict-Transport-Security:** HSTS pro HTTPS
- **Content-Security-Policy:** Komplexní ochrana proti XSS a injection útokům

**Použití:**
Headers se načítají automaticky v `init.php`. Není potřeba žádná další konfigurace.

---

### 2. **Upload MIME Validation** ✅
**Soubor:** `/app/controllers/save_photos.php`

Přidána validace MIME typu uploadovaných souborů pomocí `finfo_buffer()`.

**Povolené typy:**
- `image/jpeg`, `image/png`, `image/gif`, `image/webp`
- `video/mp4`, `video/quicktime` (iPhone videa)

**Benefit:**
- Prevence uploadu PHP souborů nebo jiných škodlivých typů
- Ochrana proti RCE (Remote Code Execution) útokům

---

### 3. **Audit Logging** ✅
**Soubor:** `/includes/audit_logger.php`

Strukturované logování kritických operací pro forensic analýzu a compliance.

**Použití:**
```php
require_once __DIR__ . '/includes/audit_logger.php';

// Zalogovat událost
auditLog('admin_login', ['method' => 'admin_key']);
auditLog('user_deleted', ['user_id' => 123], $adminId);
auditLog('key_rotated', ['new_hash' => '...']);
```

**Logované události:**
- `admin_login` - Admin přihlášení
- `user_login` - Uživatelské přihlášení
- `high_key_verified` - Ověření high key
- `admin_key_rotated` - Rotace admin klíče

**Formát logů:**
```json
{
  "timestamp": "2025-11-11 14:23:45",
  "action": "admin_login",
  "user_id": "WGS_ADMIN",
  "user_name": "Administrátor",
  "is_admin": true,
  "ip": "192.168.1.1",
  "user_agent": "Mozilla/5.0...",
  "details": {"method": "admin_key"}
}
```

**Logy se ukládají:**
- `/logs/audit_YYYY-MM.log` (po měsících)

**Pomocné funkce:**
```php
// Získat audit logy
$logs = getAuditLogs('2025-11-01', '2025-11-30', 'admin_login');

// Smazat staré logy (starší než 365 dní)
cleanOldAuditLogs(365);
```

---

### 4. **Environment-Based Configuration** ✅
**Soubor:** `/config/config.php`

Přidána podpora pro různá prostředí (development, staging, production).

**Konfigurace v `.env`:**
```env
ENVIRONMENT=production
# nebo
ENVIRONMENT=development
# nebo
ENVIRONMENT=staging
```

**PHP konstanty:**
```php
APP_ENV          // 'production', 'development', 'staging'
IS_PRODUCTION    // true/false
IS_DEVELOPMENT   // true/false
IS_STAGING       // true/false
```

**Automatické nastavení:**
- **Development:** `display_errors = 1`, plné error reporting
- **Production:** `display_errors = 0`, logy do `/logs/php_errors.log`

**Použití:**
```php
if (IS_DEVELOPMENT) {
    // Debug kód
    var_dump($data);
}

if (IS_PRODUCTION) {
    // Produkční logika
    error_log('Production error');
}
```

---

### 5. **Health Check Endpoint** ✅
**Soubor:** `/health.php`

Endpoint pro monitoring stavu aplikace.

**URL:**
```
GET /health.php
```

**Response (200 OK):**
```json
{
  "status": "healthy",
  "timestamp": "2025-11-11T14:30:00+01:00",
  "environment": "production",
  "checks": {
    "session": {"status": "ok"},
    "database": {"status": "ok", "connected": true},
    "uploads": {"status": "ok", "writable": true},
    "logs": {"status": "ok", "writable": true},
    "temp": {"status": "ok", "writable": true},
    "php": {"status": "ok", "version": "8.1.0"},
    "extensions": {"status": "ok", "missing": []},
    "disk_space": {"status": "ok", "used_percent": 45.2}
  }
}
```

**Response (503 Service Unavailable):**
```json
{
  "status": "unhealthy",
  "checks": {
    "database": {
      "status": "fail",
      "connected": false,
      "error": "Database connection failed"
    }
  }
}
```

**Použití pro monitoring:**
```bash
# Curl check
curl -f http://localhost/health.php || echo "Health check failed"

# Monitoring tools (Nagios, Zabbix, etc.)
# Nastavit endpoint na /health.php
# Alert při HTTP != 200
```

---

## 📊 Souhrn bezpečnostních vylepšení

| Oblast | Před | Po | Status |
|--------|------|-----|--------|
| **HTTP Headers** | ❌ Žádné | ✅ CSP, X-Frame-Options, atd. | ✅ |
| **Upload Validace** | ⚠️ Pouze extension | ✅ MIME + extension | ✅ |
| **Audit Logging** | ❌ Žádné | ✅ Strukturované JSON logy | ✅ |
| **Environment Config** | ⚠️ Částečné | ✅ Dev/Staging/Prod | ✅ |
| **Health Check** | ❌ Žádný | ✅ /health.php endpoint | ✅ |

---

## 🔐 Již existující bezpečnostní opatření

Projekt již měl tyto bezpečnostní mechanismy:

✅ **PDO Prepared Statements** - všude
✅ **Password Hashing** - `password_hash()` + `password_verify()`
✅ **CSRF Protection** - `requireCSRF()` na všech POST endpointech
✅ **Rate Limiting** - na admin login (5 pokusů / 15 minut)
✅ **Session Security** - httponly cookies, secure flags
✅ **Input Validation** - email, hesla, SQL injection prevence

---

## 📝 Doporučení pro budoucnost

### Nízká priorita (nice-to-have):

1. **Backup systém**
   - Automatické DB zálohy (cron job)
   - Retention policy (30 dní)

2. **Databázové indexy**
   - Zkontrolovat indexy na `email`, `reklamace_id`, `termin`
   - Optimalizovat pomalé dotazy

3. **Performance monitoring**
   - New Relic / Sentry integrace
   - Sledování response times

4. **IP Whitelisting pro diagnostic_tool.php**
   - Přidat IP kontrolu pro extra bezpečnost

---

## 🚀 Upgrade Guide

Žádné změny v konfiguraci nejsou potřeba. Všechna vylepšení jsou zpětně kompatibilní.

**Volitelné:**
1. Přidat do `.env`:
   ```env
   ENVIRONMENT=production
   ```

2. Nastavit monitoring na `/health.php`

---

**Implementováno:** Claude AI Assistant
**Datum:** 2025-11-11
**Verze:** 1.0
