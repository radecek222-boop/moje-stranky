# SECURITY AUDIT - KOMPLETNÍ REPORT
## WGS Service - 66/66 API Endpointů (100% Pokrytí)

**Datum:** 2025-12-04
**Auditor:** Claude Security Audit
**Verze:** FINAL - Kompletní pokrytí

---

## EXECUTIVE SUMMARY

| Metrika | Hodnota |
|---------|---------|
| **Celkem API souborů** | 66 |
| **READ_FULLY potvrzeno** | 66 (100%) |
| **P0 (Critical)** | 7 |
| **P1 (High)** | 9 |
| **P2 (Medium)** | 12 |
| **Verdikt** | 🔴 **NO-GO** |

---

## ČÁST 1: DETAILNÍ ANALÝZA ZBÝVAJÍCÍCH 25 SOUBORŮ

### 1. api/admin_api.php (1104 řádků)
**Účel:** Správa registračních klíčů, uživatelů, reklamací, API klíčů a email šablon.

**Auth:** ✅ Admin only (řádky 18-25)
```php
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    // ...
}
```

**CSRF:** ✅ Pro POST (řádky 75-82)
**Rate Limiting:** ✅ 100 req/10min (řádky 39-54)
**Validace:**
- key_type: whitelist ['technik', 'prodejce'] (řádek 213-217)
- max_usage: int cast (řádek 221)
- status: whitelist ['wait', 'open', 'done'] (řádky 619-623)
- email: filter_var FILTER_VALIDATE_EMAIL (řádky 921-929)

**DB:** ✅ Prepared statements všude
**Severity:** ✅ P2 - OK

---

### 2. api/admin_users_api.php (571 řádků)
**Účel:** CRUD operace pro správu uživatelů.

**Auth:** ✅ Admin only (řádky 15-23)
**CSRF:** ✅ Pro POST (řádky 42-55)
**Validace:**
- email: validateEmailStrong() (řádky 154-159, 381-387)
- phone: regex CZ/SK formát (řádky 166-173, 389-394)
- role: whitelist ['prodejce', 'technik', 'admin'] (řádky 184-187, 403-406)
- password: min 8 znaků (řádky 161-163, 471-472)

**DB:** ✅ Prepared statements
**Severity:** ✅ P2 - OK

---

### 3. api/advanced_diagnostics_api.php (946 řádků)
**Účel:** Pokročilá diagnostika projektu (SQL analýza, code quality, security scan).

**Auth:** ✅ Admin only (řádky 16-25)
**CSRF:** ✅ Vyžaduje (řádky 73-85)
**Rate Limiting:** ✅ 50 req/10min (řádky 32-46)

**⚠️ P2 NÁLEZ:** Expozice citlivých informací
```php
// Řádek 939 - vrací sample kódu z kritických souborů
'sample' => substr(file_get_contents($fullPath), 0, 1000)
```

**Severity:** P2 - Diagnostika exponuje zdrojový kód

---

### 4. api/analytics_api.php (319 řádků)
**Účel:** Webové analytické metriky (návštěvy, bounce rate, konverze).

**Auth:** ✅ Admin only (řádky 14-22)
**CSRF:** ❌ Chybí pro GET
**Rate Limiting:** ❌ Chybí

**DB:** ✅ Prepared statements
**Severity:** P2 - Chybí rate limiting

---

### 5. api/analytics_realtime.php (250 řádků)
**Účel:** Real-time dashboard s aktivními návštěvníky.

**Auth:** ✅ Admin only (řádky 36-39)
**CSRF:** ✅ Vyžaduje (řádky 44-47)

**🚨 P0 NÁLEZ - DEBUG LOGOVÁNÍ:**
```php
// Řádky 25-30 - loguje session data a CSRF tokeny!
error_log("=== REALTIME API DEBUG ===");
error_log("Action: " . ($_GET['action'] ?? 'none'));
error_log("Session ID: " . session_id());
error_log("Is Admin: " . (isset($_SESSION['is_admin']) ? 'yes' : 'no'));
error_log("CSRF Token received: " . ($_GET['csrf_token'] ?? 'none'));
error_log("CSRF Token session: " . ($_SESSION['csrf_token'] ?? 'none'));
```

**Severity:** 🔴 **P0** - Citlivá data v logách

**Oprava:**
```php
// ODSTRANIT řádky 25-30 (debug logování)
```

---

### 6. api/analytics_replay.php (203 řádků)
**Účel:** Načtení session replay dat pro přehrání.

**Auth:** ✅ Admin only (řádky 31-34)
**CSRF:** ✅ Vyžaduje (řádky 38-42)
**Validace:**
- session_id: sanitizeInput() (řádek 56)
- page_index: is_numeric() + int cast (řádky 57-59)

**DB:** ✅ Prepared statements
**Severity:** ✅ P2 - OK

---

### 7. api/backup_api.php (307 řádků)
**Účel:** Automatická záloha databáze.

**Auth:** ✅ Admin only (řádky 12-16)
**CSRF:** ✅ Pro POST akce (řádky 24-38)

**Path Traversal ochrana:**
```php
// Řádky 217-218 - kontrola filename
if (strpos($filename, '..') !== false || strpos($filename, '/') !== false) {
    throw new Exception('Invalid filename');
}
```

**DB:** ✅ Prepared statements (vlastní PDO instance)
**Severity:** ✅ P2 - OK

---

### 8. api/debug_request.php (19 řádků)
**Účel:** Diagnostika HTTP requestu.

**🚨 P0 NÁLEZ - VEŘEJNÝ DEBUG ENDPOINT BEZ AUTH:**
```php
<?php
header('Content-Type: application/json');

echo json_encode([
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'UNDEFINED',
    'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'UNDEFINED',
    'POST' => $_POST,
    'GET' => $_GET,
    'php_input' => file_get_contents('php://input'),
    // ...
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

**Chybí:**
- ❌ Auth kontrola
- ❌ CSRF ochrana
- ❌ Rate limiting

**Severity:** 🔴 **P0** - Veřejně přístupný debug endpoint

**Oprava:** SMAZAT SOUBOR nebo přidat admin auth:
```php
require_once __DIR__ . '/../init.php';
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('Unauthorized');
}
```

---

### 9. api/delete_photo.php (145 řádků)
**Účel:** Mazání jednotlivé fotky z reklamace.

**Auth:** ✅ Logged in user nebo admin (řádky 24-28)
**CSRF:** ✅ Vyžaduje (řádky 19-21)
**Rate Limiting:** ✅ 30 req/hod (řádky 37-41)

**Path Traversal ochrana:**
```php
// Řádky 87-100
$uploadsRoot = realpath(__DIR__ . '/../uploads');
$normalized = str_replace(['\\', '..'], ['/', ''], $filePath);
// ...
if ($realPath && strpos($realPath, $uploadsRoot) === 0 && is_file($realPath)) {
```

**DB:** ✅ Prepared statements + transakce
**Severity:** ✅ P2 - OK

---

### 10. api/delete_reklamace.php (325 řádků)
**Účel:** Mazání celé reklamace včetně souvisejících dat.

**Auth:** ✅ Admin only (řádky 35-43)
**CSRF:** ✅ requireCSRF() (řádek 33)
**Rate Limiting:** ✅ 20 req/10min (řádky 46-62)

**Path Traversal ochrana:** ✅ (řádky 260-278)
**SQL Injection ochrana:**
```php
// Řádky 99-103 - whitelist sloupců
$allowedColumns = ['id', 'reklamace_id', 'cislo'];
if (!in_array($identifierColumn, $allowedColumns, true)) {
    throw new Exception('Neplatný identifikátor sloupce.');
}
```

**DB:** ✅ Prepared statements + transakce
**Severity:** ✅ P2 - OK

---

### 11. api/email_resend_api.php (91 řádků)
**Účel:** Znovu odeslání failnutých emailů.

**Auth:** ✅ Admin only (řádky 14-18)
**CSRF:** ✅ Vyžaduje (řádky 33-36)
**Validace:**
- email_ids: array_filter is_numeric (řádek 44)
- max 100 emailů najednou (řádky 51-55)

**DB:** ✅ Prepared statements
**Severity:** ✅ P2 - OK

---

### 12. api/log_js_error.php (135 řádků)
**Účel:** Přijímá JS chyby z frontendu a loguje na server.

**Auth:** ❌ Veřejný (pro logging z frontendu)
**CSRF:** ❌ Chybí
**Rate Limiting:** ✅ 20 req/hod (řádky 14-32)

**DoS ochrana:**
```php
// Řádky 107-113 - max 10MB log
$maxLogSize = 10 * 1024 * 1024;
if (file_exists($logFile) && filesize($logFile) > $maxLogSize) {
    @rename($logFile, $archiveFile);
}
```

**Severity:** P2 - Rate limit OK, ale chybí CSRF

---

### 13. api/migration_executor.php (219 řádků)
**Účel:** Bezpečné spouštění SQL migrací.

**Auth:** ✅ Admin only (řádky 12-16)
**CSRF:** ✅ Pro run_migration (řádky 24-36)

**Migration whitelist:**
```php
// Řádky 59-64
$allowedMigrations = [
    'migration_admin_control_center.sql'
];
if (!in_array($migrationFile, $allowedMigrations)) {
    throw new Exception('Migration file not allowed');
}
```

**DB:** ✅ Transakce pro atomicitu
**Severity:** ✅ P2 - OK

---

### 14. api/notes_api.php (644 řádků)
**Účel:** API pro práci s poznámkami k reklamacím.

**Auth:** ✅ Logged in (řádky 21-30)
**CSRF:** ✅ Pro POST (řádek 67)

**🚨 P0 NÁLEZ - DEBUG LOGOVÁNÍ:**
```php
// Řádky 15-18 - loguje POST data včetně CSRF tokenů!
error_log('[Notes API DEBUG] REQUEST_METHOD=' . ($_SERVER['REQUEST_METHOD'] ?? 'UNDEFINED'));
error_log('[Notes API DEBUG] POST=' . json_encode($_POST));
error_log('[Notes API DEBUG] GET=' . json_encode($_GET));
error_log('[Notes API DEBUG] php://input=' . file_get_contents('php://input'));
```

**Validace audio:**
```php
// Řádky 159-165 - MIME whitelist
$allowedMimes = ['audio/webm', 'audio/mp3', 'audio/mpeg', 'audio/ogg', 
                 'audio/wav', 'audio/mp4', 'audio/x-m4a', 'video/webm', 'video/mp4'];
```

**Severity:** 🔴 **P0** - DEBUG logování citlivých dat

**Oprava:** Odstranit řádky 15-18

---

### 15. api/notification_api.php (237 řádků)
**Účel:** Správa emailových a SMS notifikací.

**Auth:** ✅ Admin only (řádky 20-29), ping bez auth (řádky 13-18)
**CSRF:** ✅ Pro POST (řádky 115-128)
**Validace:**
- notification_id: preg_replace alfanumerické (řádky 143-144, 179-180)
- recipient: whitelist (řádky 183-186)
- emails: filter_var FILTER_VALIDATE_EMAIL (řádky 189-198)

**DB:** ✅ Prepared statements
**Severity:** ✅ P2 - OK

---

### 16. api/notification_list_direct.php (85 řádků)
**Účel:** Načtení seznamu notifikací pro admin panel.

**Auth:** ✅ Admin only (řádky 12-21)
**CSRF:** ❌ Chybí (GET only)
**Rate Limiting:** ❌ Chybí

**DB:** ✅ Prepared statements
**Severity:** P2 - Chybí rate limiting

---

### 17. api/notification_list_html.php (158 řádků)
**Účel:** HTMX endpoint - vrací HTML fragment.

**Auth:** ✅ Admin only (řádky 20-26)
**CSRF:** ❌ Chybí (GET)

**XSS ochrana:**
```php
// Řádky 15-17
function e($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}
```

**Severity:** ✅ P2 - OK

---

### 18. api/protokol_api.php (903 řádků)
**Účel:** Ukládání PDF protokolů a práce s protokoly.

**Auth:** ✅ Logged in (řádky 18-27)
**CSRF:** ✅ Pro POST (řádky 49-62)
**Rate Limiting:** ✅ Pro PDF upload 10 req/hod (řádky 73-96)

**Validace:**
- reklamace_id: sanitizeReklamaceId() (řádky 173, 316, 852)
- PDF size: max 30MB base64 (řádky 180-185)

**DB:** ✅ Prepared statements
**Severity:** ✅ P2 - OK

---

### 19. api/push_subscription_api.php (197 řádků)
**Účel:** Správa Web Push subscriptions.

**Auth:** vapid-key bez auth (řádky 24-33), ostatní POST (řádky 36-38)
**CSRF:** ✅ Pro POST (řádky 40-43)
**Admin kontrola:** Pro test a stats (řádky 122-124, 166-168)

**DB:** ✅ Prepared statements
**Severity:** ✅ P2 - OK

---

### 20. api/supervisor_api.php (350 řádků)
**Účel:** Správa přiřazení prodejců pod supervizory.

**Auth:** ✅ Logged in, admin pro změny (řádky 16-27, 53-55)
**CSRF:** ✅ Pro POST (řádek 50)

**Validace:**
- supervisor_id: intval() (řádky 87-88, 222-223)
- salesperson_ids: array_map intval + array_filter (řádky 230-234)

**DB:** ✅ Prepared statements + transakce
**Severity:** ✅ P2 - OK

---

### 21. api/track_event.php (287 řádků)
**Účel:** Sledování uživatelských událostí (kliky, scroll, rage clicks).

**Auth:** ❌ Veřejný (tracking endpoint)
**CSRF:** ✅ Vyžaduje (řádky 69-72)
**Rate Limiting:** ✅ 2000 req/hod (řádky 46-58)

**Validace:**
- session_id/fingerprint_id: max 64 znaků (řádky 102-108)
- event_type: whitelist (řádky 111, 121)
- max 50 eventů/batch (řádky 89-92)

**DB:** ✅ Prepared statements
**Severity:** ✅ P2 - OK

---

### 22. api/track_replay.php (302 řádků)
**Účel:** Příjem session replay framů.

**Auth:** ❌ Veřejný (tracking endpoint)
**CSRF:** ✅ Vyžaduje (řádky 70-74)
**Rate Limiting:** ✅ 1000 req/hod (řádky 49-61)

**Validace:**
- device_type: whitelist (řádky 116-119)
- viewport: 0-10000 (řádky 125-131)
- max 50 framů/batch (řádky 134-136)
- event_type: whitelist (řádky 141, 157)

**⚠️ P2 NÁLEZ - Lokální redefinice sanitizeInput:**
```php
// Řádky 293-300 - může způsobit konflikt
function sanitizeInput($input): ?string
```

**DB:** ✅ Prepared statements
**Severity:** P2 - Potenciální konflikt funkcí

---

### 23. api/track_v2.php (631 řádků)
**Účel:** Pokročilé sledování pageviews s relacemi.

**Auth:** ❌ Veřejný (tracking endpoint)
**CSRF:** ✅ Vyžaduje (řádky 46-49)
**Rate Limiting:** ✅ 1000 req/hod (řádky 59-71)

**IP Blacklist:**
```php
// Řádky 77-85, 88-102 - DB + hardcoded blacklist
$blacklistedIPs = [
    '2a00:11b1:10a2:5773:a4d3:7603:899e:d2f3',
    // ...
];
```

**Validace:**
- page_url: FILTER_VALIDATE_URL (řádek 228)
- session_id/fingerprint_id: max 64 znaků (řádky 235-236)

**DB:** ✅ Prepared statements
**Severity:** ✅ P2 - OK

---

### 24. api/video_download.php (498 řádků)
**Účel:** Stahování videí pomocí tokenu.

**Auth:** ✅ Token-based (řádky 29-53)
```php
$stmt = $pdo->prepare("SELECT ... WHERE t.token = :token");
// Kontrola expirace, is_active
```

**⚠️ P1 NÁLEZ - Path Traversal riziko:**
```php
// Řádek 87 - video_path přímo z DB bez validace
$filePath = __DIR__ . '/../' . $video['video_path'];
```

Pokud útočník může vložit do DB cestu jako `../../etc/passwd`, může číst soubory.

**Rate Limiting:** ❌ Chybí
**CSRF:** N/A (token-based)

**Severity:** ⚠️ **P1** - Path traversal při čtení video_path z DB

**Oprava:**
```php
$uploadsRoot = realpath(__DIR__ . '/../uploads');
$filePath = realpath(__DIR__ . '/../' . $video['video_path']);
if (!$filePath || strpos($filePath, $uploadsRoot) !== 0) {
    zobrazChybu('Neplatná cesta', 'Soubor není dostupný.');
    exit;
}
```

---

### 25. api/zakaznici_api.php (106 řádků)
**Účel:** Správa seznamu zákazníků.

**Auth:** ✅ Admin only (řádky 17-25)
**CSRF:** ❌ Chybí pro GET
**Rate Limiting:** ❌ Chybí

**SQL Injection ochrana:**
```php
// Řádky 53-61 - prepared statement s LIKE
$sql .= " AND (jmeno LIKE :search OR email LIKE :search ...)";
$params['search'] = '%' . $search . '%';
```

**DB:** ✅ Prepared statements
**Severity:** P2 - Chybí rate limiting

---

## ČÁST 2: KOMPLETNÍ SEZNAM 66 ENDPOINTŮ

### ✅ PŘEČTENO (66/66 = 100%)

| # | Soubor | Auth | CSRF | Rate Limit | Severity |
|---|--------|------|------|------------|----------|
| 1 | api/admin.php | ✅ Admin | ✅ | ✅ | P2 |
| 2 | api/admin/actions.php | ✅ Admin | ✅ | ✅ | P2 |
| 3 | api/admin/config.php | ✅ Admin | ✅ | ✅ | P2 |
| 4 | api/admin/data.php | ✅ Admin | ✅ | ❌ | P2 |
| 5 | api/admin/diagnostics.php | ✅ Admin | ✅ | ✅ | P2 |
| 6 | api/admin/maintenance.php | ✅ Admin | ✅ | ✅ | P2 |
| 7 | api/admin/security_api.php | ✅ Admin | ✅ | ✅ | P2 |
| 8 | api/admin/theme.php | ✅ Admin | ✅ | ❌ | P2 |
| 9 | api/admin_api.php | ✅ Admin | ✅ | ✅ | P2 |
| 10 | api/admin_bot_whitelist.php | ✅ Admin | ✅ | ✅ | P2 |
| 11 | api/admin_stats_api.php | ✅ Admin | ✅ | ✅ | P2 |
| 12 | api/admin_users_api.php | ✅ Admin | ✅ | ❌ | P2 |
| 13 | api/advanced_diagnostics_api.php | ✅ Admin | ✅ | ✅ | P2 |
| 14 | api/analytics_api.php | ✅ Admin | ❌ | ❌ | P2 |
| 15 | api/analytics_bot_activity.php | ✅ Admin | ✅ | ✅ | P2 |
| 16 | api/analytics_campaigns.php | ✅ Admin | ✅ | ✅ | P2 |
| 17 | api/analytics_conversions.php | ✅ Admin | ✅ | ✅ | P2 |
| 18 | api/analytics_heatmap.php | ✅ Admin | ✅ | ✅ | P2 |
| 19 | api/analytics_realtime.php | ✅ Admin | ✅ | ❌ | **P0** |
| 20 | api/analytics_replay.php | ✅ Admin | ✅ | ❌ | P2 |
| 21 | api/analytics_reports.php | ✅ Admin | ✅ | ✅ | **P0** |
| 22 | api/analytics_user_scores.php | ✅ Admin | ✅ | ✅ | P2 |
| 23 | api/auto_assign_technician.php | ✅ User | ✅ | ✅ | P2 |
| 24 | api/backup_api.php | ✅ Admin | ✅ | ❌ | P2 |
| 25 | api/debug_request.php | ❌ NONE | ❌ | ❌ | **P0** |
| 26 | api/delete_photo.php | ✅ User | ✅ | ✅ | P2 |
| 27 | api/delete_reklamace.php | ✅ Admin | ✅ | ✅ | P2 |
| 28 | api/email_resend_api.php | ✅ Admin | ✅ | ❌ | P2 |
| 29 | api/fingerprint_store.php | Public | ✅ | ✅ | P2 |
| 30 | api/gdpr_api.php | Mixed | ✅ | ❌ | P2 |
| 31 | api/generuj_aktuality.php | ✅ Admin | ✅ | ❌ | P2 |
| 32 | api/generuj_aktuality_nove.php | ✅ Admin | ✅ | ❌ | P2 |
| 33 | api/geocode_proxy.php | Public | ❌ | ❌ | P2 |
| 34 | api/get_kalkulace_api.php | ✅ User | ✅ | ❌ | **P1** |
| 35 | api/get_original_documents.php | ✅ User | ❌ | ❌ | **P1** |
| 36 | api/get_photos_api.php | ✅ User | ❌ | ❌ | P2 |
| 37 | api/get_user_stats.php | ✅ User | ❌ | ❌ | P2 |
| 38 | api/github_webhook.php | HMAC | N/A | ❌ | P2 |
| 39 | api/log_js_error.php | Public | ❌ | ✅ | P2 |
| 40 | api/migration_executor.php | ✅ Admin | ✅ | ❌ | P2 |
| 41 | api/nacti_aktualitu.php | ✅ Admin | ❌ | ❌ | P2 |
| 42 | api/notes_api.php | ✅ User | ✅ | ❌ | **P0** |
| 43 | api/notification_api.php | ✅ Admin | ✅ | ❌ | P2 |
| 44 | api/notification_list_direct.php | ✅ Admin | ❌ | ❌ | P2 |
| 45 | api/notification_list_html.php | ✅ Admin | ❌ | ❌ | P2 |
| 46 | api/parse_povereni_pdf.php | ✅ User | ✅ | ✅ | P2 |
| 47 | api/pricing_api.php | ✅ Admin | ✅ | ❌ | **P0** |
| 48 | api/protokol_api.php | ✅ User | ✅ | ✅ | P2 |
| 49 | api/push_subscription_api.php | Mixed | ✅ | ❌ | P2 |
| 50 | api/send_contact_attempt_email.php | Public | ✅ | ✅ | P2 |
| 51 | api/statistiky_api.php | ✅ Admin | ✅ | ❌ | P2 |
| 52 | api/supervisor_api.php | ✅ User/Admin | ✅ | ❌ | P2 |
| 53 | api/tech_provize_api.php | ✅ Admin | ✅ | ❌ | P2 |
| 54 | api/track_conversion.php | Public | ✅ | ✅ | P2 |
| 55 | api/track_event.php | Public | ✅ | ✅ | P2 |
| 56 | api/track_heatmap.php | Public | ✅ | ✅ | P2 |
| 57 | api/track_pageview.php | Public | ❌ | ❌ | **P1** |
| 58 | api/track_replay.php | Public | ✅ | ✅ | P2 |
| 59 | api/track_v2.php | Public | ✅ | ✅ | P2 |
| 60 | api/uloz_pdf_mapping.php | ✅ User | ✅ | ❌ | P2 |
| 61 | api/uprav_celou_aktualitu.php | ✅ Admin | ✅ | ❌ | P2 |
| 62 | api/uprav_odkaz_aktuality.php | ✅ Admin | ✅ | ❌ | P2 |
| 63 | api/video_api.php | ✅ User | ✅ | ❌ | **P0** |
| 64 | api/video_download.php | Token | N/A | ❌ | **P1** |
| 65 | api/vytvor_aktualitu.php | ✅ Admin | ✅ | ❌ | **P1** |
| 66 | api/zakaznici_api.php | ✅ Admin | ❌ | ❌ | P2 |

---

## ČÁST 3: KONSOLIDOVANÝ SEZNAM P0/P1 NÁLEZŮ

### 🔴 P0 - KRITICKÉ (7 nálezů)

| # | Lokace | Popis | Oprava |
|---|--------|-------|--------|
| **P0-1** | api/pricing_api.php:126-128 | SQL Injection přes `edit_lang` parametr | Přidat whitelist validaci |
| **P0-2** | api/debug_request.php | Veřejný debug endpoint bez auth | SMAZAT SOUBOR |
| **P0-3** | api/notes_api.php:15-18 | DEBUG logování POST dat včetně CSRF | Odstranit debug logy |
| **P0-4** | api/analytics_realtime.php:25-30 | DEBUG logování session/CSRF tokenů | Odstranit debug logy |
| **P0-5** | api/video_api.php:118 | `application/octet-stream` v MIME whitelist | Odstranit z whitelist |
| **P0-6** | assets/js/admin-notifications.js | innerHTML XSS (8 míst) | Použít textContent |
| **P0-7** | api/analytics_reports.php:216 | `$this->` runtime error (mimo třídu) | Opravit syntaxi |

### ⚠️ P1 - VYSOKÉ (9 nálezů)

| # | Lokace | Popis | Oprava |
|---|--------|-------|--------|
| **P1-1** | api/track_pageview.php | Chybí CSRF a rate limiting | Přidat obojí |
| **P1-2** | api/get_kalkulace_api.php | Slabá IDOR ochrana | Přidat ownership check |
| **P1-3** | api/get_original_documents.php | Slabá IDOR ochrana | Přidat ownership check |
| **P1-4** | api/vytvor_aktualitu.php:89-102 | Nespolehlivá MIME validace | Použít finfo |
| **P1-5** | api/video_download.php:87 | Path traversal při čtení z DB | Přidat realpath validaci |
| **P1-6** | composer.lock | Chybí verzování závislostí | Vytvořit composer.lock |
| **P1-7** | PHPMailer | Bundled místo Composer | Migrovat na Composer |
| **P1-8** | CI/CD | Chybí smoke testy | Přidat základní testy |
| **P1-9** | api/track_replay.php:293-300 | Lokální sanitizeInput() konflikt | Odstranit lokální definici |

---

## ČÁST 4: ČASOVÝ ODHAD OPRAV

### P0 Opravy (BLOKUJÍCÍ)
| Nález | Čas | Priorita |
|-------|-----|----------|
| P0-1 SQL Injection | 15 min | OKAMŽITĚ |
| P0-2 Debug endpoint | 1 min | OKAMŽITĚ |
| P0-3 Notes debug log | 5 min | OKAMŽITĚ |
| P0-4 Realtime debug log | 5 min | OKAMŽITĚ |
| P0-5 MIME whitelist | 5 min | OKAMŽITĚ |
| P0-6 XSS innerHTML | 30 min | OKAMŽITĚ |
| P0-7 $this error | 10 min | OKAMŽITĚ |
| **CELKEM P0** | **~1.5 hod** | |

### P1 Opravy (DŮLEŽITÉ)
| Nález | Čas | Priorita |
|-------|-----|----------|
| P1-1 track_pageview | 30 min | Vysoká |
| P1-2+3 IDOR opravy | 1 hod | Vysoká |
| P1-4 MIME validace | 20 min | Střední |
| P1-5 Path traversal | 20 min | Vysoká |
| P1-6+7 Composer | 2 hod | Střední |
| P1-8 CI/CD testy | 3 hod | Nízká |
| P1-9 sanitizeInput | 5 min | Nízká |
| **CELKEM P1** | **~7 hod** | |

---

## FINÁLNÍ VERDIKT

```
╔═══════════════════════════════════════════════════════════════╗
║                                                               ║
║   🔴 NO-GO PRO PRODUKCI                                      ║
║                                                               ║
║   7 kritických P0 problémů musí být opraveno před deployem   ║
║                                                               ║
║   Odhadovaný čas do GO: 1-2 pracovní dny                     ║
║                                                               ║
╚═══════════════════════════════════════════════════════════════╝
```

### Před dalším auditem:
1. ✅ Opravit všech 7 P0 nálezů
2. ✅ Opravit P1-1, P1-2, P1-3, P1-5 (security-critical)
3. ⬜ Spustit automatizované testy
4. ⬜ Provést retest opravených nálezů

---

**Podpis:** Claude Security Audit
**Datum:** 2025-12-04
**Revize:** 3.0 - KOMPLETNÍ (66/66 endpointů)




P0 (MUST-FIX před ostrým provozem) — 7 bodů
P0-1: Smazat / uzamknout debug endpoint
Akce: odstranit ./api/debug_request.php z produkce (nebo striktně omezit jen na admin + IP allowlist + vypnout v prod).
Ověření hotovo: request na endpoint musí vracet 404 nebo 403 bez leaků. 
SECURITY_AUDIT_COMPLETE_66_ENDP…
P0-2: Opravit SQL injection v pricing_api.php (dynamic column)
Akce: zrušit dynamické skládání názvu sloupce z inputu; použít mapu/whitelist povolených lang → konkrétní sloupec; dotaz sestavit pevně.
Ověření hotovo: fuzz lang (např. en'--, en,xyz, ../) nesmí změnit výsledky ani vyhodit SQL error; logy bez SQL chyb. 
SECURITY_AUDIT_COMPLETE_66_ENDP…
P0-3: Zpřísnit upload whitelist (odebrat application/octet-stream)
Akce: v ./api/video_api.php odstranit application/octet-stream; validovat i reálný typ obsahu (magic bytes / finfo), ne jen Content-Type.
Ověření hotovo: upload “maskovaného” souboru (např. .php přejmenovaný) musí být odmítnut. 
SECURITY_AUDIT_COMPLETE_66_ENDP…
P0-4: Opravit XSS v admin notifikacích (innerHTML)
Akce: v assets/js/admin-notifications.js nahradit innerHTML za bezpečné renderování (textContent / sanitizace).
Ověření hotovo: vložený payload <img src=x onerror=alert(1)> se nesmí vykonat. 
SECURITY_AUDIT_COMPLETE_66_ENDP…
P0-5: Vypnout / odstranit debug logy citlivých dat
Akce: odstranit nebo podmínit error_log(...) v:
./api/notes_api.php
./api/analytics_realtime.php
./api/protokol_api.php
Ověření hotovo: v produkčních logách se nesmí objevovat request payloady, tokeny, PII. 
SECURITY_AUDIT_COMPLETE_66_ENDP…
P0-6: Zavést CSRF pro state-changing endpoints (kde chybí)
Akce: doplnit CSRF ochranu (nebo přejít na token-based API) u endpointů, které mění stav a používají cookie/session auth.
Ověření hotovo: cross-site POST bez tokenu musí skončit 403. 
SECURITY_AUDIT_COMPLETE_66_ENDP…
P0-7: Projít a uzamknout admin-only endpoints (authZ/IDOR)
Akce: potvrdit, že všechny admin/privileged akce mají role check a ownership kontrolu.
Ověření hotovo: user bez role admin nesmí provést admin akce ani přes přímé volání endpointu. 
SECURITY_AUDIT_COMPLETE_66_ENDP…
P1 (doporučeno před provozem / hned po P0)
P1-1: Přidat test krok do deploy pipeline
Akce: v CI přidat aspoň lint + smoke test (nebo mini integrační testy kritických endpointů).
Ověření: CI failne při chybě; deploy se nespustí bez green. 
SECURITY_AUDIT_COMPLETE_66_ENDP…
P1-2: Zpřísnit CSP (omezit unsafe-inline, nonce/hash)
Akce: postupně omezit inline skripty, zavést nonce/hashes hlavně pro admin části.
Ověření: report-only nejdřív, pak enforce; žádné funkční regresní chyby. 
SECURITY_AUDIT_COMPLETE_66_ENDP…
P1-3: Deterministické závislosti (composer.lock)
Akce: doplnit composer.lock, zamknout verze a mít reprodukovatelný build.
Ověření: čistý build na CI dává stejné dependency verze. 
SECURITY_AUDIT_COMPLETE_66_ENDP…
P1-4: Rate limiting strategie – fail-closed u citlivých endpointů
Akce: u login/token/abuse endpointů zvaž fail-closed, nebo aspoň degradační mód.
Ověření: při DB výpadku nelze bruteforce bez limitu. 
SECURITY_AUDIT_COMPLETE_66_ENDP…
P1-5: Privacy/GDPR “runtime” kontrola (analytics/replay/fingerprint)
Akce: ověřit consent gating + retention enforcement (TTL cleanup job).
Ověření: bez souhlasu se neukládá replay/fingerprint; po expiraci se data mažou. 
SECURITY_AUDIT_COMPLETE_66_ENDP…
Doporučený pořadník prací (nejrychlejší riziko dolů)
P0-1, P0-2, P0-3, P0-4
P0-5
P0-6, P0-7
P1-1, P1-3
P1-2, P1-5, P1-4
