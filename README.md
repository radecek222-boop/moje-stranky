# WGS SERVICE - Kompletní dokumentace projektu

**White Glove Service - Natuzzi Servis Management System**

**Verze:** 3.0.0
**Poslední aktualizace:** 2025-11-28
**Stav:** Produkce

---

## Obsah

1. [Prehled projektu](#1-prehled-projektu)
2. [Technicky stack](#2-technicky-stack)
3. [Struktura adresaru](#3-struktura-adresaru)
4. [Databaze](#4-databaze)
5. [API vrstva](#5-api-vrstva)
6. [Bezpecnost](#6-bezpecnost)
7. [Enterprise Analytics System](#7-enterprise-analytics-system)
8. [Cenik - Vicejazykova podpora](#8-cenik-vicejazykova-podpora)
9. [Klonovani zakazek](#9-klonovani-zakazek)
10. [Vykonnostni optimalizace](#10-vykonnostni-optimalizace)
11. [Cron joby](#11-cron-joby)
12. [Produkcni konfigurace](#12-produkcni-konfigurace)
13. [Dokoncene migrace a opravy](#13-dokoncene-migrace-a-opravy)
14. [Kontakt](#14-kontakt)

---

## 1. Prehled projektu

### Ucel

System pro spravu reklamaci, servisnich pozadavku a instalaci luxusniho nabytku Natuzzi. Umoznuje:

- Evidenci reklamaci, instalaci a servisu
- Planovani navstev techniku
- Generovani servisnich protokolu (PDF)
- Sprava fotografii a dokumentu
- Statistiky a analyzy
- Enterprise analytics (Google Analytics-like)

### Hlavni stranky

| Stranka | URL | Ucel |
|---------|-----|------|
| `index.php` | `/` | Uvodni stranka |
| `novareklamace.php` | `/novareklamace.php` | Novy pozadavek |
| `seznam.php` | `/seznam.php` | Seznam zakazek (vyzaduje prihlaseni) |
| `statistiky.php` | `/statistiky.php` | Statistiky (pouze admin) |
| `protokol.php` | `/protokol.php` | Servisni protokol |
| `admin.php` | `/admin.php` | Control Centre |
| `cenik.php` | `/cenik.php` | Cenik sluzeb (CS/EN/IT) |

---

## 2. Technicky stack

| Komponenta | Technologie | Verze |
|------------|-------------|-------|
| **Backend** | PHP s PDO | 8.4+ |
| **Databaze** | MariaDB | 10.11+ |
| **Frontend** | Vanilla JavaScript (ES6+) | - |
| **Server** | Nginx | 1.26+ |
| **Mapy** | Leaflet.js + Geoapify API | - |
| **Email** | PHPMailer pres SMTP | - |
| **Deploy** | GitHub Actions + SFTP | - |

---

## 3. Struktura adresaru

```
/home/user/moje-stranky/
|
+-- config/                          # Konfigurace a DB pripojeni
|   +-- config.php                   # Hlavni konfigurace
|   +-- database.php                 # Database singleton
|
+-- app/                             # Jadro aplikace
|   +-- controllers/                 # Business logika
|   |   +-- save.php                 # Ukladani/aktualizace zakazek (KRITICKE)
|   |   +-- login_controller.php     # Autentizace
|   |   +-- get_distance.php         # Vypocet vzdalenosti
|   +-- save_photos.php              # Upload fotek
|   +-- notification_sender.php      # Odesilani emailu
|
+-- includes/                        # Sdilene utility
|   +-- security_headers.php         # CSP, HSTS, X-Frame-Options
|   +-- csrf_helper.php              # CSRF tokeny
|   +-- error_handler.php            # Logovani chyb
|   +-- EmailQueue.php               # Fronta emailu
|   +-- FingerprintEngine.php        # Analytics - device fingerprinting
|   +-- BotDetector.php              # Analytics - detekce botu
|   +-- GeolocationService.php       # Analytics - geolokace
|   +-- SessionMerger.php            # Analytics - session tracking
|   +-- UserScoreCalculator.php      # Analytics - engagement scoring
|   +-- CampaignAttribution.php      # Analytics - UTM tracking
|   +-- ConversionFunnel.php         # Analytics - konverze
|   +-- AIReportGenerator.php        # Analytics - AI reporty
|   +-- GDPRManager.php              # Analytics - GDPR compliance
|
+-- api/                             # API endpointy
|   +-- control_center_api.php       # Admin panel operace
|   +-- protokol_api.php             # Protokol CRUD
|   +-- statistiky_api.php           # Statistiky
|   +-- notes_api.php                # Poznamky
|   +-- pricing_api.php              # Cenik
|   +-- track_v2.php                 # Analytics tracking
|   +-- fingerprint_store.php        # Analytics fingerprinting
|   +-- analytics_*.php              # Analytics API endpointy
|
+-- assets/                          # Frontend resources
|   +-- js/                          # JavaScript soubory
|   |   +-- logger.js                # MUSI SE NACIST PRVNI
|   |   +-- tracker-v2.js            # Analytics tracker
|   |   +-- fingerprint-module.js    # Device fingerprinting
|   |   +-- event-tracker.js         # Event tracking
|   |   +-- heatmap-renderer.js      # Heatmapy
|   |   +-- replay-recorder.js       # Session replay
|   +-- css/                         # CSS soubory
|
+-- migrations/                      # Databazove migrace
+-- scripts/                         # Cron joby a udrzba
+-- logs/                            # Logy aplikace
+-- uploads/                         # Nahrane soubory
+-- archiv/                          # Archivovane soubory
|
+-- .env                             # Promenne prostredi (GITIGNORED)
+-- init.php                         # Bootstrap soubor
+-- CLAUDE.md                        # Pravidla pro AI asistenta
+-- README.md                        # Tento soubor
```

---

## 4. Databaze

### 4.1 Hlavni tabulky

| Tabulka | Ucel | Klicove sloupce |
|---------|------|-----------------|
| `wgs_reklamace` | Hlavni tabulka zakazek | `reklamace_id`, `jmeno`, `stav`, `termin` |
| `wgs_users` | Uzivatele | `user_id`, `email`, `role`, `is_active` |
| `wgs_registration_keys` | Registracni klice | `key_code`, `key_type`, `max_usage` |
| `wgs_photos` | Fotografie | `photo_id`, `reklamace_id`, `section_name` |
| `wgs_documents` | Dokumenty/PDF | `claim_id`, `document_type` |
| `wgs_notes` | Poznamky | `claim_id`, `note_text`, `created_by` |
| `wgs_email_queue` | Fronta emailu | `to_email`, `status`, `scheduled_at` |
| `wgs_pricing` | Cenik | `service_name`, `price_from`, `price_to` |

### 4.2 Analytics tabulky (Enterprise Analytics System)

| Tabulka | Ucel | TTL |
|---------|------|-----|
| `wgs_analytics_fingerprints` | Device fingerprints | Zadny |
| `wgs_analytics_sessions` | Session tracking | Zadny |
| `wgs_analytics_events` | User events (click, scroll) | 90 dni |
| `wgs_analytics_heatmap_clicks` | Click heatmaps | Zadny |
| `wgs_analytics_heatmap_scroll` | Scroll heatmaps | Zadny |
| `wgs_analytics_replay_frames` | Session replay | 30 dni |
| `wgs_analytics_utm_campaigns` | UTM kampane | Zadny |
| `wgs_analytics_conversions` | Konverze | Zadny |
| `wgs_analytics_bot_detections` | Detekce botu | Zadny |
| `wgs_analytics_geolocation_cache` | IP geolokace | 3 dny |
| `wgs_analytics_user_scores` | Engagement skore | Zadny |
| `wgs_analytics_realtime` | Real-time sessions | 5 minut |
| `wgs_analytics_reports` | AI reporty | Zadny |

### 4.3 ENUM mapovani (KRITICKE!)

**Frontend (JavaScript)** pouziva **CESKA VELKA** pismena:
- `'CEKA'`, `'DOMLUVENA'`, `'HOTOVO'`
- `'CZ'`, `'SK'`

**Databaze (SQL)** pouziva **ANGLICKA MALA** pismena:
- `'wait'`, `'open'`, `'done'`
- `'cz'`, `'sk'`

Mapovani probiha automaticky v `app/controllers/save.php`:

```php
$stavMapping = [
    'CEKA' => 'wait',
    'DOMLUVENA' => 'open',
    'HOTOVO' => 'done'
];
```

### 4.4 Struktura tabulky wgs_reklamace

Celkem 48 sloupcu vcetne:

```sql
-- Primarni klice
id INT AUTO_INCREMENT PRIMARY KEY
reklamace_id VARCHAR(50) UNIQUE

-- Typ pozadavku
typ ENUM('REKLAMACE', 'INSTALACE', 'SERVIS')

-- Kontaktni udaje
jmeno VARCHAR(255) NOT NULL
email VARCHAR(255) NOT NULL
telefon VARCHAR(50) NOT NULL

-- Adresa
adresa VARCHAR(500)
ulice VARCHAR(255)
mesto VARCHAR(255)
psc VARCHAR(20)

-- Produkt
model VARCHAR(255)
seriove_cislo VARCHAR(255)
popis_problemu TEXT

-- Stav
stav ENUM('wait', 'open', 'done') DEFAULT 'wait'
termin DATE
cas_navstevy VARCHAR(50)

-- Zpracovani
technik VARCHAR(255)
prodejce VARCHAR(255)
zpracoval_id INT

-- Fakturace
cena DECIMAL(10,2)
fakturace_firma ENUM('cz', 'sk') DEFAULT 'cz'

-- Klonovani
original_reklamace_id VARCHAR(50) NULL

-- Casova razitka
created_at TIMESTAMP
updated_at TIMESTAMP
```

### 4.5 Sprava databaze pres Control Centre

Vsechny zmeny SQL databaze se provadeji pres kartu **"SQL"** v Admin panelu:

1. Otevrit: `https://www.wgs-service.cz/admin.php`
2. Kliknout na kartu "SQL"
3. Zobrazuje aktualni strukturu vsech tabulek
4. Moznost stahnout DDL, kopirovat, tisknout

---

## 5. API vrstva

### 5.1 Standardni format odpovedi

```json
{
  "status": "success | error",
  "message": "Zprava v cestine",
  "data": {}
}
```

### 5.2 Hlavni API endpointy

| Endpoint | Metoda | Ucel |
|----------|--------|------|
| `/api/control_center_api.php` | POST | Admin operace |
| `/api/protokol_api.php` | POST | Protokol CRUD |
| `/api/statistiky_api.php` | GET | Statistiky |
| `/api/notes_api.php` | POST | Poznamky CRUD |
| `/api/pricing_api.php` | GET/POST | Cenik |
| `/api/delete_reklamace.php` | POST | Smazani zakazky |
| `/app/controllers/save.php` | POST | Ulozeni zakazky |

### 5.3 Analytics API

| Endpoint | Metoda | Ucel |
|----------|--------|------|
| `/api/track_v2.php` | POST | Pageview + session tracking |
| `/api/fingerprint_store.php` | POST | Device fingerprint |
| `/api/track_event.php` | POST | Event tracking |
| `/api/track_heatmap.php` | POST | Heatmap data |
| `/api/track_replay.php` | POST | Session replay frames |
| `/api/track_conversion.php` | POST | Conversion tracking |
| `/api/analytics_dashboard.php` | GET | Real-time dashboard |
| `/api/analytics_heatmap.php` | GET | Heatmap vizualizace |
| `/api/analytics_replay.php` | GET | Session replay prehravani |

---

## 6. Bezpecnost

### 6.1 CSRF ochrana (POVINNA)

Vsechny POST pozadavky VYZADUJI CSRF tokeny:

```javascript
// Frontend - automaticky injektovano
formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
```

```php
// Backend - validace v kazdem POST handleru
require_once __DIR__ . '/../includes/csrf_helper.php';
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    sendJsonError('Neplatny CSRF token', 403);
}
```

### 6.2 Session bezpecnost

```php
session_set_cookie_params([
    'lifetime' => 3600,      // 1 hodina
    'path' => '/',
    'secure' => true,        // Pouze HTTPS
    'httponly' => true,      // Zadny JavaScript pristup
    'samesite' => 'Lax'      // CSRF ochrana
]);
```

### 6.3 Autentizacni metody

| Metoda | Pouziti | Validace |
|--------|---------|----------|
| **Admin Key** | Admin prihlaseni | SHA256 hash v `.env` |
| **User Login** | Bezni uzivatele | `password_verify()` |
| **Registration Keys** | Kontrola registrace | DB tabulka s limity |
| **Remember Me** | Zapamatovat prihlaseni | Secure tokeny |

### 6.4 Rate Limiting

```php
$rateLimiter = new RateLimiter($pdo);
if (!$rateLimiter->checkLimit('login', $_SERVER['REMOTE_ADDR'], 5, 900)) {
    die(json_encode(['status' => 'error', 'message' => 'Prilis mnoho pokusu']));
}
```

### 6.5 Produkcni bezpecnostni audit (DOKONCENO)

**Provedeno 2025-11-21:**

- [x] Smazano: `api/statistiky_api.php.backup`
- [x] Presunuto: `pridej_remember_tokens.php` do `setup/`
- [x] Zkontrolovano: `setup/.htaccess` (Deny from all)
- [x] Overeny vsechny install skripty
- [x] Odstraneny duplicitni cron soubory

---

## 7. Enterprise Analytics System

### 7.1 Prehled

Kompletni web analytics platforma srovnatelna s Google Analytics 4, Matomo, Hotjar:

| Funkce | Status | Modul |
|--------|--------|-------|
| Device Fingerprinting | DOKONCENO | #1 |
| Advanced Session Tracking | DOKONCENO | #2 |
| Bot Detection | DOKONCENO | #3 |
| Geolocation Engine | DOKONCENO | #4 |
| Event Tracking | DOKONCENO | #5 |
| Heatmaps | DOKONCENO | #6 |
| Session Replay | DOKONCENO | #7 |
| UTM Campaign Tracking | DOKONCENO | #8 |
| Conversion Funnels | DOKONCENO | #9 |
| User Interest AI Scoring | DOKONCENO | #10 |
| Real-time Dashboard | DOKONCENO (s issues) | #11 |
| AI Reports Engine | DOKONCENO | #12 |
| GDPR Compliance | DOKONCENO | #13 |

**Celkem:** 13/13 modulu dokonceno

### 7.2 Architektura

```
+-- ADMIN UI LAYER (Ceske popisky) --+
|  - Real-time Dashboard             |
|  - Heatmap Viewer                  |
|  - Session Replay Player           |
|  - GDPR Compliance Panel           |
+------------------------------------+
              |
+-- API LAYER (RESTful PHP) ---------+
|  - Track V2 API                    |
|  - Event API                       |
|  - Replay API                      |
|  - Analytics API                   |
+------------------------------------+
              |
+-- BUSINESS LOGIC LAYER ------------+
|  - FingerprintEngine               |
|  - BotDetector                     |
|  - GeolocationService              |
|  - UserScoreCalculator             |
|  - AIReportGenerator               |
+------------------------------------+
              |
+-- DATA LAYER (MariaDB) ------------+
|  - 14 databazovych tabulek         |
|  - JSON sloupce                    |
|  - 50+ indexu                      |
+------------------------------------+
              |
+-- CLIENT TRACKING LAYER (JS) ------+
|  - tracker-v2.js                   |
|  - fingerprint-module.js           |
|  - event-tracker.js                |
|  - replay-recorder.js              |
+------------------------------------+
```

### 7.3 Bot Detection

Detekce botu s AI heuristikou:

- User agent patterns (bot, crawler, spider, selenium, puppeteer)
- Webdriver detekce
- Headless browser detekce
- VPN/Proxy/TOR detekce
- Datacenter IP detekce
- Mouse movement entropy
- Bot score 0-100

**Threat Level klasifikace:**
- `none`: 0-20 (pravdepodobne clovek)
- `low`: 21-40 (mozny bot)
- `medium`: 41-60 (pravdepodobny bot)
- `high`: 61-80 (skoro jiste bot)
- `critical`: 81-100 (100% bot)

### 7.4 User Scoring

**Engagement score (0-100):**
- clicks (20%), scroll (20%), duration (20%), mouse (15%), pageviews (15%), diversity (10%)

**Frustration score (0-100):**
- rage clicks (30%), erratic scroll (25%), hesitation (20%), quick exit (15%), errors (10%)

**Interest score (0-100):**
- reading time (30%), scroll quality (25%), return visits (20%), focus (15%), content (10%)

### 7.5 GDPR Compliance

- Cookie consent banner
- Data export (JSON)
- Data deletion
- IP anonymization
- Data retention policies
- Audit logging

---

## 8. Cenik - Vicejazykova podpora

Stranka `cenik.php` podporuje 3 jazyky: Cestina (CS), Anglictina (EN), Italstina (IT)

### 8.1 Databazove sloupce

| Datovy typ | CS (vychozi) | EN | IT |
|------------|--------------|----|----|
| Kategorie | `category` | `category_en` | `category_it` |
| Nazev sluzby | `service_name` | `service_name_en` | `service_name_it` |
| Popis | `description` | `description_en` | `description_it` |

### 8.2 Predpona cen

JavaScript (`cenik.js`) kontroluje jazyk a pridava spravnou predponu:

```javascript
const odPrefix = {
    cs: 'Od',
    en: 'From',
    it: 'Da'
};
```

### 8.3 Detekce jazyka

```javascript
const jazyk = window.ziskejAktualniJazyk ? window.ziskejAktualniJazyk() : 'cs';
```

---

## 9. Klonovani zakazek

### 9.1 Funkcionalita

Dokoncene zakazky (HOTOVO) lze znovu otevrit - system vytvori NOVOU zakazku (klon) a puvodniprepisovani zachova:

1. Najit zakazku ve stavu HOTOVO
2. Kliknout "Znovu otevrit"
3. System vytvori novou zakazku s odkazem na original
4. Puvodni zakazka zustava ve stavu HOTOVO

### 9.2 Databazovy sloupec

```sql
original_reklamace_id VARCHAR(50) NULL
```

Index: `idx_original_reklamace_id`

### 9.3 Souvisejici funkce

- Tlacitko "Historie PDF" - zobrazeni PDF z puvodnich zakazek
- Sledovani historie oprav
- Zachovani kompletni evidence

### 9.4 Kompatibilita s load.php

**OVERENO:** `load.php` pouziva `SELECT r.*` - automaticky vraci `original_reklamace_id`

---

## 10. Vykonnostni optimalizace

### 10.1 Kriticke problemy (identifikovano v auditu)

| Problem | Zavaznost | Stav |
|---------|-----------|------|
| 82 SELECT * queries | CRITICAL | K reseni |
| Session locking (1x session_write_close) | CRITICAL | K reseni |
| Chybejici transakce (47+ operaci) | HIGH | K reseni |
| File-based sessions | HIGH | K reseni |
| Chybejici DB timeout | MEDIUM | K reseni |

### 10.2 Celkove skore systemu: 64/100

| Kategorie | Skore | Status |
|-----------|-------|--------|
| SQL Performance | 52/100 | CRITICAL |
| Session Management | 35/100 | CRITICAL |
| API Integrity | 68/100 | MEDIUM |
| Database Indexing | 78/100 | GOOD |
| Transaction Safety | 45/100 | HIGH |
| Architecture | 72/100 | ACCEPTABLE |

### 10.3 Predikce vykonu

| Concurrent Users | Response Time | Success Rate |
|------------------|---------------|--------------|
| 50 users | 1.2-2.5s | 95% |
| 80 users | 3.5-8s | 75% |
| 100 users | 8-15s | 45% |
| 150+ users | >30s | <20% |

**Breaking Point:** ~85 concurrent users

### 10.4 Doporucene opravy

**Faze 1 (0-7 dni) - CRITICAL:**
1. Pridat `session_write_close()` do vsech API
2. Pridat transakce do kritickych operaci
3. Optimalizovat SELECT * v hot path

**Faze 2 (7-30 dni) - HIGH:**
4. Implementovat Redis sessions
5. Optimalizovat zbyvajici SELECT * queries
6. Pridat chybejici indexy

**Faze 3 (30-90 dni) - MEDIUM:**
7. Nasadit produkcni konfigurace
8. Implementovat zbyvajici transakce
9. Load testing a monitoring

### 10.5 Ocekavany vysledek po optimalizaci

- Breaking point: 85 -> 250-300 users (+250%)
- Response time @ 50 users: 2.5s -> 0.5s (-80%)
- Success rate @ 200 users: <20% -> 95% (+75%)

---

## 11. Cron joby

### 11.1 Limit hostingu

**DULEZITE:** Hosting ma **LIMIT 5 WEBCRON** jobu!

### 11.2 Finalni konfigurace (5/5 jobu)

| # | Job | Soubor | Perioda | System |
|---|-----|--------|---------|--------|
| 1 | Ultra Master Cron | `scripts/ultra_master_cron.php` | Denne 02:00 | Analytics |
| 2 | Realtime Cleanup | `scripts/cleanup_realtime_sessions.php` | Kazdych 15 min | Analytics |
| 3 | Email Queue | `cron/process-email-queue.php` | Kazdych 15 min | WGS |
| 4 | Appointment Reminders | `webcron-send-reminders.php` | Denne 10:00 | WGS |
| 5 | SEO Actuality | `generuj_aktuality.php` | Denne 06:00 | SEO |

### 11.3 Ultra Master Cron

Kombinuje 6 Analytics operaci do jednoho jobu:

1. Cleanup replay frames (30 dni TTL)
2. Cleanup geo cache (3 dny TTL)
3. Recalculate user scores (krome nedele)
4. Campaign stats aggregation
5. Generate scheduled reports
6. GDPR retention policy (pouze v nedeli)

---

## 12. Produkcni konfigurace

### 12.1 PHP-FPM Pool

```ini
pm = dynamic
pm.max_children = 80
pm.start_servers = 20
pm.min_spare_servers = 12
pm.max_spare_servers = 28
pm.max_requests = 1000
request_terminate_timeout = 60s
memory_limit = 256M
opcache.enable = on
opcache.memory_consumption = 256M
opcache.jit = tracing
```

### 12.2 MySQL/MariaDB

```ini
max_connections = 200
innodb_buffer_pool_size = 2G
innodb_buffer_pool_instances = 2
innodb_log_file_size = 512M
innodb_flush_method = O_DIRECT
innodb_io_capacity = 2000
slow_query_log = ON
long_query_time = 2s
```

### 12.3 Redis Sessions (doporuceno)

```ini
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379?database=1"
```

**Prinos:** 10-30x rychlejsi session operace, zadny session locking

---

## 13. Dokoncene migrace a opravy

### 13.1 Databazove migrace (DOKONCENO)

| Migrace | Datum | Stav |
|---------|-------|------|
| `pridej_original_reklamace_id.php` | 2025-11 | DOKONCENO |
| `migrace_module1_fingerprinting.php` | 2025-11-23 | DOKONCENO |
| `migrace_module2_sessions.php` | 2025-11-23 | DOKONCENO |
| `migrace_module3_bot_detection.php` | 2025-11-23 | DOKONCENO |
| `migrace_module4_geolocation.php` | 2025-11-23 | DOKONCENO |
| `migrace_module5_events.php` | 2025-11-23 | DOKONCENO |
| `migrace_module6_heatmaps.php` | 2025-11-23 | DOKONCENO |
| `migrace_module7_session_replay.php` | 2025-11-23 | DOKONCENO |
| `migrace_module8_utm_campaigns.php` | 2025-11-23 | DOKONCENO |
| `migrace_module9_conversions.php` | 2025-11-23 | DOKONCENO |
| `migrace_module10_user_scores.php` | 2025-11-23 | DOKONCENO |
| `migrace_module11_realtime.php` | 2025-11-23 | DOKONCENO |
| `migrace_module12_ai_reports.php` | 2025-11-23 | DOKONCENO |
| `migrace_module13_gdpr.php` | 2025-11-23 | DOKONCENO |
| `migrace_email_worker.sql` | 2025-11 | DOKONCENO |

#### Jak spustit migraci `2025_12_01_pridej_tabulku_wgs_videos.php`

> **Tip:** Na produkci už tabulka `wgs_videos` podle přehledu ve `vsechny_tabulky.php` existuje. Skript spouštějte pouze pokud by chyběla (např. v jiné instanci), nebo pokud potřebujete znovu vytvořit složku `uploads/videos`.
>
> Aktuální struktura v DB: `id`, `claim_id`, `video_name`, `video_path`, `file_size`, `duration`, `thumbnail_path`, `uploaded_at` (NULLable), `uploaded_by`.

1. Přihlašte se do administrace jako uživatel s příznakem `is_admin = true` (skript kontroluje session).
2. Otevřete v prohlížeči adresu `https://<domena>/migrations/2025_12_01_pridej_tabulku_wgs_videos.php`.
3. Pokud tabulka neexistuje, zobrazí se tlačítko **SPUSTIT MIGRACI** – kliknutím spustíte vytvoření tabulky a založení složky `uploads/videos`.
4. Migraci lze spustit opakovaně; pokud tabulka už existuje, skript pouze vypíše její aktuální strukturu.

### 13.2 Soubory presunuty do /archiv/ (2025-11-28)

89 souboru presunuto do slozky `/archiv/`:
- 16 migracnich skriptu
- 23 testovacich souboru
- 9 diagnostickych souboru
- 6 fix skriptu
- 13 "pridej_*" skriptu
- 12 control/import/cleanup skriptu
- 10 dalsich nepotrebnych souboru

### 13.3 Dokumentace konsolidovana (2025-11-28)

Nasledujici soubory byly zkonsolidovany do tohoto README.md:
- AUDIT_QUICK_START.md
- AUDIT_REOPEN_FEATURE.md
- DOKUMENTACE.md
- KRITICKA_KONTROLA_LOAD.md
- PRE_MERGE_CHECKLIST.md
- PRODUCTION_AUDIT_REPORT.md
- NEWANAL.md
- WGS_COMPLETE_TECHNICAL_AUDIT_2025.md
- PERFORMANCE_ANALYSIS.json
- FINAL_DDL_wgs_reklamace.sql
- SPRAVNY_INSERT_wgs_reklamace.sql
- migrace_email_worker.sql

### 13.4 Opravy aktuality stránky (2025-11-28)

- Odstranena sekce "Archiv aktualit"
- Zmeneno razeni clanku z delky na nahodne (shuffle) pro SEO

### 13.5 Opravy admin.php (2025-11-28)

Odstraneny karty:
- "Domu" (Hlavni stranka aplikace)
- "Novy protokol" (Vytvorit novy servisni protokol)

### 13.6 Drag & drop ve videotece (2025-11-28)

Pridana funkcionalita drag & drop pro upload videi v `seznam.js`:
- Drop overlay s vizualni zpetnou vazbou
- Event handlers (dragenter, dragleave, dragover, drop)
- Nova funkce `nahratVideoDragDrop()`

---

## 14. Kontakt

**Projekt:** White Glove Service
**Vlastnik:** Radek Zikmund
**Email:** radek@wgs-service.cz
**Repository:** https://github.com/radecek222-boop/moje-stranky

---

## Changelog

| Datum | Verze | Zmeny |
|-------|-------|-------|
| 2025-11-28 | 3.0.0 | Konsolidace dokumentace do README.md |
| 2025-11-23 | 2.0.0 | Enterprise Analytics System dokoncen (13/13 modulu) |
| 2025-11-24 | 1.5.0 | Kompletni technicky audit |
| 2025-11-21 | 1.2.0 | Produkcni bezpecnostni audit |
| 2025-11-17 | 1.0.0 | Zakladni dokumentace |

---

(c) 2025 White Glove Service - Vse v ceskem jazyce
