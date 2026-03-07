# SYSTÉMOVÁ ARCHITEKTONICKÁ ZPRÁVA
## White Glove Service (WGS) - Natuzzi Furniture Service Management System

**Datum analýzy:** 2026-03-07
**Analyzoval:** Claude Code (Principal Software Architect)
**Verze:** 1.0

---

## EXECUTIVE SUMMARY

WGS Service je enterprise-level PHP aplikace pro správu reklamací, servisu a zakázek pro Natuzzi (luxusní italský nábytek). Systém byl postaven rychle, je funkční a produkčně nasazený. Obsahuje přibližně 196 000 řádků kódu v 860 souborech.

**Silné stránky:**
- Solidní bezpečnostní infrastruktura (CSRF, rate limiting, security headers)
- Konzistentní použití PDO prepared statements
- Dobře strukturované API vrstva
- PWA podpora s offline schopnostmi
- Existující audit logging
- Automatické minifikace assets

**Hlavní rizika:**
- 2 místa s přímou SQL interpolací (exec() bez prepared statement) v hry_api.php
- 3 admin POST handlery bez CSRF validace
- Monolitické soubory (admin_email_sms.php 115KB, admin_security.php 100KB)
- Chybějící indexy na klíčových sloupcích
- Nekonzistentní formát API odpovědí (mix sendJsonSuccess vs echo json_encode)
- Analytics tabulky mohou neomezeně růst bez retenční politiky

---

## 1. PŘEHLED ORGANIZACE SYSTÉMU

### 1.1 Adresářová struktura

```
/home/user/moje-stranky/
│
├── init.php                    # Bootstrap - načítá se na KAŽDÉ stránce
├── config/
│   ├── config.php              # Hlavní konfigurace, helper funkce, DB singleton
│   └── database.php            # Database class (deleguje na config.php)
│
├── app/                        # Aplikační jádro
│   ├── controllers/            # Business logika (10 kontrolérů)
│   │   ├── save.php            # KRITICKÝ - ukládání reklamací
│   │   ├── login_controller.php
│   │   ├── registration_controller.php
│   │   └── ...
│   └── notification_sender.php
│
├── api/                        # API endpoints (84 PHP souborů, ~1.2 MB)
│   ├── admin/                  # Admin-specifické API
│   └── [domain-specific files] # Jednotlivé endpointy
│
├── includes/                   # Sdílené utility (60 souborů, ~1.3 MB)
│   ├── [admin_*.php]           # Admin komponenty (~700 KB)
│   ├── EmailQueue.php          # Email queue systém
│   ├── csrf_helper.php         # CSRF ochrana
│   ├── security_headers.php    # HTTP security headers
│   ├── rate_limiter.php        # Rate limiting
│   └── ...
│
├── assets/
│   ├── js/                     # 139 JavaScript souborů
│   └── css/                    # 63 CSS souborů
│
├── migrations/                 # Databázové migrace
├── cron/                       # Cron joby (4 soubory)
├── hry/                        # Herní zóna (8 her)
└── [Root PHP pages]            # 89 PHP stránek
```

### 1.2 Request flow

```
HTTP Request
    │
    ▼
.htaccess (Apache) / Nginx config
    │ URL rewriting + security headers
    ▼
init.php (Bootstrap)
    │ Načítá .env → config.php → security_headers.php
    │ Inicializuje session (PWA-aware, secure cookies)
    │ Remember Me token handling
    │ TenantManager inicializace
    │ Session timeout (30 min)
    ▼
Page Controller (*.php v root)
nebo
API Endpoint (/api/*.php)
    │ Auth check (session, admin key)
    │ CSRF validace (pro POST)
    │ Rate limiting (pro citlivé operace)
    │ Input validace
    │ Business logika
    │ PDO prepared statements
    ▼
Response (HTML nebo JSON)
```

---

## 2. HLAVNÍ MODULY

### 2.1 Reklamace / Service Management (CORE)
- **Vstup:** `novareklamace.php`, `app/controllers/save.php`
- **Seznam:** `seznam.php` + `api/seznam_html.php` (57 KB)
- **Protokol:** `protokol.php` + `api/protokol_api.php` (45 KB)
- **Mazání:** `api/delete_reklamace.php`
- **Stav:** `api/zmenit_stav.php`, `api/odloz_reklamaci.php`
- **DB tabulka:** `wgs_reklamace`

### 2.2 Uživatelé a autentizace
- **Login:** `login.php`, `app/controllers/login_controller.php`
- **Registrace:** `registration.php`, `app/controllers/registration_controller.php`
- **Reset hesla:** `password_reset.php`
- **Admin přístup:** SHA256 hash klíče (ADMIN_KEY_HASH v .env)
- **Remember Me:** `includes/remember_me_handler.php`
- **DB tabulky:** `wgs_users`, `wgs_registration_keys`, `wgs_remember_tokens`

### 2.3 Admin Control Center
- **Hlavní:** `admin.php` (71 KB)
- **Komponenty:** `includes/admin_console.php` (97 KB), `includes/admin_email_sms.php` (115 KB)
- **Bezpečnost:** `includes/admin_security.php` (100 KB)
- **Audit:** `includes/admin_audit.php` (61 KB)
- **Soubory:** `includes/admin_soubory.php` (63 KB)
- **Transport:** `includes/admin_transport.php` (52 KB)
- **API:** `api/admin_api.php` (31 KB), `api/admin_users_api.php` (24 KB)

### 2.4 Email / Notifikace / Push
- **Email Queue:** `includes/EmailQueue.php` (27 KB)
- **SMTP nastavení:** `admin/smtp_settings.php`
- **Push notifikace:** `includes/WebPush.php` (21 KB)
- **Šablony:** `includes/email_sablony_nabidka.php` (54 KB)
- **Cron zpracování:** `cron/process-email-queue.php`
- **DB tabulky:** `wgs_email_queue`, `wgs_notifications`, `wgs_push_subscriptions`

### 2.5 Analytics a Heatmaps
- **Statistiky:** `statistiky.php`, `api/statistiky_api.php` (24 KB)
- **Tracking:** `api/track_pageview.php`, `api/track_heatmap.php`
- **Vizualizace:** `api/analytics_heatmap.php`
- **DB tabulky:** `wgs_pageviews`, `wgs_heatmap`, `wgs_analytics_*`
- **RIZIKO:** Tyto tabulky mohou neomezeně růst

### 2.6 Cenové nabídky (Pricing/Offers)
- **Ceník:** `cenik.php`, `api/pricing_api.php` (12 KB)
- **Nabídky:** `cenova-nabidka.php` (114 KB!), `api/nabidka_api.php` (35 KB)
- **Kalkulace:** `api/get_kalkulace_api.php`, `api/save_kalkulace_api.php`
- **DB tabulky:** `wgs_nabidky`, `wgs_kalkulace`, `wgs_pricing`

### 2.7 Dokumenty a Média
- **Fotografie:** `api/documents_api.php`, upload handling
- **Videa:** `api/video_api.php` (14 KB), `api/video_download.php`
- **Soubory:** `api/soubory_api.php` (28 KB), `includes/soubory_helpers.php`
- **DB tabulky:** `wgs_photos`, `wgs_documents`, `wgs_videos`, `wgs_video_tokens`

### 2.8 Transport
- **Frontend:** `transport.php` (123 KB!)
- **API:** `api/transport_events_api.php` (17 KB), `api/transport_sync.php`
- **Admin:** `includes/admin_transport.php` (52 KB)
- **DB tabulky:** `wgs_transport_*`

### 2.9 PWA / Service Worker
- **SW:** `sw.js`, `sw.php`
- **Manifest:** `manifest.json`
- **Notifikace:** `assets/js/pwa-notifications.js`
- **Heartbeat:** `assets/js/online-heartbeat.js`

### 2.10 Herní zóna
- **Hub:** `hry.php` (49 KB), `hry/` (8 her)
- **API:** `api/hry_api.php` (45 KB)
- **DB tabulky:** `wgs_hry_*` (7 tabulek)

### 2.11 Lokalizace (Multi-language)
- **Translator:** `includes/translator.php` (14 KB)
- **Switcher:** `assets/js/language-switcher.js`
- **Jazyky:** CS (výchozí), EN, IT
- **DB tabulka:** `wgs_translation_cache`, `wgs_content_texts`

### 2.12 Multi-tenant
- **Manager:** `includes/TenantManager.php` (7.7 KB)
- **Stav:** Připraveno, ale DEAKTIVOVÁNO v defaultní konfiguraci
- **DB sloupec:** `tenant_id` v hlavních tabulkách

---

## 3. DATOVÝ TOK

```
Uživatel odešle formulář
    │
    ▼
CSRF validace (csrf_helper.php)
    │
    ▼
Auth check ($_SESSION['user_id'] nebo is_admin)
    │
    ▼
Rate limiting (rate_limiter.php → wgs_rate_limits)
    │
    ▼
Input sanitizace (sanitizeInput() → htmlspecialchars)
    │
    ▼
Business logika (controller nebo API endpoint)
    │
    ▼
PDO prepared statement → MariaDB
    │
    ▼
JSON odpověď (sendJsonSuccess/sendJsonError nebo přímý echo)
    │
    ▼
Frontend zpracuje odpověď → WGSToast notifikace
```

---

## 4. BEZPEČNOSTNÍ ARCHITEKTURA

### 4.1 Vrstvená bezpečnost

| Vrstva | Implementace | Soubor |
|--------|-------------|--------|
| HTTP headers | CSP, HSTS, X-Frame-Options | `security_headers.php` |
| HTTPS | Vynucení v init.php | `init.php` |
| CSRF | Token v každém POST | `csrf_helper.php` |
| Auth | Session + admin key | `init.php`, `login_controller.php` |
| Rate limiting | DB-backed per-IP | `rate_limiter.php` |
| SQL injection | PDO prepared statements | Všechny DB operace |
| XSS | htmlspecialchars() | Input/output sanitizace |
| Audit log | Audit logger | `audit_logger.php` |

### 4.2 Session konfigurace (z init.php)
- `lifetime`: 0 pro browser, 7 dní pro PWA
- `secure`: true v produkci
- `httponly`: true
- `samesite`: Lax
- Inactivity timeout: 30 minut
- Session regenerace: po každém timeoutu

### 4.3 Enum mapování (Frontend ↔ DB)

```
Frontend (JS) → save.php → Database
'ČEKÁ'        →           'wait'
'DOMLUVENÁ'   →           'open'
'HOTOVO'      →           'done'
'CZ'          →           'cz'
'SK'          →           'sk'
```

---

## 5. NEJVĚTŠÍ SILNÉ STRÁNKY

1. **CSRF ochrana je důsledná** - pokrývá téměř všechny POST handlery
2. **PDO prepared statements** - 150+ míst, SQL injection dobře pokryt
3. **Audit logging** - sledování admin akcí
4. **Rate limiting** - ochrana proti brute force
5. **Security headers** - CSP, HSTS, X-Frame-Options jsou správně nastaveny
6. **Email Queue** - asynchronní zpracování emailů s retry logikou
7. **PWA support** - offline schopnosti, push notifikace
8. **Minifikace assets** - CSS a JS jsou minifikovány

---

## 6. NEJVĚTŠÍ RIZIKA A TECHNICKÝ DLUH

### Kritická rizika
1. **SQL interpolace v hry_api.php** - exec() s proměnnou (řádky 249, 258) — viz oprava níže
2. **Chybějící CSRF v admin/** - 3 POST handlery bez validace

### Architektonická rizika
3. **Monolitické soubory** - admin_email_sms.php (115 KB), transport.php (123 KB), cenova-nabidka.php (114 KB)
4. **Nekonzistentní API odpovědi** - mix sendJsonSuccess vs echo json_encode (50+ míst)
5. **Chybějící service vrstva** - business logika je v page kontrolérech, ne ve sdílených službách
6. **Analytics bez retence** - wgs_pageviews, wgs_heatmap mohou neomezeně růst

### Výkonová rizika
7. **N+1 query problém** - fotografie a dokumenty se načítají bez agregace v některých místech
8. **Chybějící indexy** - wgs_users.user_id, wgs_email_queue.notification_id
9. **Velké page-controller soubory** - seznam.js (142 KB), admin.css (77 KB)

---

## 7. DOPORUČENÝ PLÁN ZLEPŠENÍ

### Fáze 1 - Bezpečnostní opravy (ihned)
- Opravit SQL interpolaci v hry_api.php
- Přidat CSRF do admin/ POST handlerů
- Přidat file upload validaci (MIME whitelist, path traversal)

### Fáze 2 - Výkonová optimalizace (měsíc)
- Přidat chybějící DB indexy
- Implementovat retenční politiku pro analytics
- Session cache pro opakované dotazy

### Fáze 3 - Architektonická stabilizace (čtvrtletí)
- Extrahovat business logiku do service tříd
- Sjednotit API response formát
- Rozdělit monolitické soubory na menší moduly

### Fáze 4 - Dokumentace a testy (průběžně)
- Rozšířit unit testy
- Zdokumentovat všechny API endpointy
- Vytvořit databázový ER diagram

---

*Zpráva vygenerována automaticky architektonickým auditem. Následující soubory jsou součástí analýzy.*
