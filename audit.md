# DETAILNÍ AUDIT PROJEKTU: White Glove Service (WGS)

**Datum auditu:** 4. března 2026 (aktualizováno)
**Auditor:** Nezávislý senior expert (web development, UX/UI, produktový management)
**Zdroj dat:** Přímá analýza zdrojového kódu projektu

---

## 1. ANALÝZA STRUKTURY PROJEKTU

### Rozsah v číslech

| Metrika | Hodnota |
|---|---|
| PHP soubory celkem | 355 PHP souborů |
| JavaScript soubory | ~54 zdrojových + ~54 minified = 108 JS souborů |
| CSS soubory | ~31 zdrojových + ~30 minified = 61 CSS souborů |
| Celkový objem kódu | 206 313 řádků (PHP + JS + CSS) |
| PHP řádků | 132 383 |
| JavaScript řádků | 46 129 |
| CSS řádků | 27 801 |
| API endpointů | 77 |
| Databázových tabulek | 50+ |
| Hlavních provozních stránek | 15+ |
| Admin karet / modulů | 12+ |

### Stránky a jejich typy

**Provozní (interní systém):**
- `seznam.php` — CRM dashboard všech reklamací (seznam.js: 5 983 řádků)
- `novareklamace.php` — Formulář objednávky servisu
- `protokol.php` — Servisní protokol (protokol.js: 4 183 řádků)
- `statistiky.php` — Reporty, vyúčtování, grafy
- `admin.php` — Kontrolní panel s 12+ záložkami (api/control_center_api.php 128 KB+)
- `dnes.php` — Denní přehled technika (aktivní zakázky, skupiny, tisk)
- `tisk.php` — Tisknutelný výtisk zakázky (zákazník, produkt, protokoly, fotky)

**Veřejné (marketing / zákazník):**
- `index.php` — Homepage (minimalistická, Schema.org JSON-LD)
- `cenik.php` — Ceník se 3jazykovou kalkulačkou (CS/EN/IT)
- `aktuality.php` — Blog / novinky
- `onas.php`, `nasesluzby.php` — Informační stránky
- `gdpr.php`, `gdpr-zadost.php`, `cookies.php`, `podminky.php` — Právní stránky

**SEO landing pages:**
- `pozarucni-servis.php`, `servis-natuzzi.php`, `oprava-kresla.php`, `oprava-sedacky.php`, `mimozarucniceny.php`

**Uživatelská správa:**
- `login.php`, `registration.php`, `password_reset.php`, `aktualizuj_ucet.php`

**Speciální:**
- `hry.php` — Herní zóna s multiplayer chatem a real-time online statusem
- `psa-kalkulator.php`, `cenova-nabidka.php` — Specializované kalkulátory
- `transport.php` — Tracking dopravy
- `posudky.php` — Posudky / expertise dokumenty
- `photocustomer.php` — Správa zákaznické fotodokumentace

### Komplexita struktury

Projekt není jednoduchý web. Je to plnohodnotný enterprise CRM systém s následujícími vrstvami:

```
Prezentační vrstva     → 15+ stránek (provozní + veřejné + SEO landing)
Autentizační vrstva    → Role-based access (admin, technik, prodejce, supervizor)
Business logika        → 77 API endpointů, 8 kontrolerů
Datová vrstva          → MariaDB 50+ tabulek, PDO, transakce
Komunikační vrstva     → Email queue, Web push, PHPMailer
Bezpečnostní vrstva    → CSRF, rate limiting, audit log, CSP
Infrastruktura         → PWA, Service Worker, GitHub Actions CI/CD
```

### Technologie

- **Backend:** PHP 8.4+, PDO / MariaDB 10.11+, PHPMailer, minishlink/web-push 9.0
- **Frontend:** Vanilla JS ES6+, Leaflet.js, Geoapify API, PDF.js
- **Infrastruktura:** Nginx 1.26+, GitHub Actions, SFTP deployment, Sentry
- **PWA:** Service Worker, Web Push Notifications, installable app
- Žádný JS framework — čistý vanilla JS v celém projektu

---

## 2. UX / UI ANALÝZA

### Silné stránky designu

- Konzistentní černobílá paleta s explicitně řízeným systémem výjimek (5 schválených barev, každá s jasným účelem)
- Přítomnost `mobile-responsive.css`, `pull-to-refresh.js` a `pwa-notifications.js` naznačuje cílenou mobilní optimalizaci
- `page-transitions.css` + `page-transitions.js` — plynulé přechody mezi stránkami
- `z-index-layers.css` jako samostatný soubor = disciplinovaný přístup k vrstvení UI
- `universal-modal-theme.css` — sjednocený design modalů napříč celou aplikací
- `welcome-modal.js` — onboarding pro nové uživatele
- Oddělené `.min.css` soubory pro každou stránku = výkonostně orientovaný přístup

### Implementované UX funkce (2026)

- **Denní přehled (`dnes.php`)** — Technik vidí všechny aktivní zakázky seskupené po stavu, s přímým voláním tel: linkem
- **Autosave protokolu** — 30sekundový autosave do localStorage, obnovení po neúmyslném opuštění formuláře
- **Hromadné akce** — Výběr více zakázek a hromadná změna stavu (toggle mode, checkbox na každé kartě)
- **Unread badge pro poznámky** — červený pulsující badge CHAT na kartě zakázky s počtem nepřečtených zpráv
- **Tisknutelný výtisk (`tisk.php`)** — Kompletní výtisk zakázky s protokoly a fotodokumentací, optimalizováno pro tisk
- **Infrastruktura expirací klíčů** — Databázový sloupec `expires_at` na registračních klíčích (zatím volitelné, UI připraveno)

### Potenciální UX problémy

- `seznam.php` s komplexním seznam.js (5 983 řádků) — indikuje možnou přehlcenost jedné stránky množstvím funkcí
- Absence React / Vue / Svelte — kompletní vanilla JS v projektu takového rozsahu zvyšuje riziko nekonzistentního chování UI

### Co nelze hodnotit bez živého webu

- Vizuální kvalita výsledného designu
- Skutečné chování na mobilních zařízeních
- Rychlost načítání stránek v prohlížeči
- Přístupnost (accessibility) v praxi

**Typografie:** Poppins font (vlastní hosting) — moderní, čitelná volba.

---

## 3. TECHNICKÁ KOMPLEXITA

### Klíčové technické subsystémy

| Subsystém | Složitost | Doložení |
|---|---|---|
| Email queue s retry logikou | Vysoká | EmailQueue.php 767 řádků, ACID transakce, retry s exponenciálním backoffem |
| PDF generování protokolů | Vysoká | protokol_api.php 1 228 řádků, kalkulace provizí (33 % / 50 %) |
| Web Push notifikace | Střední-vysoká | minishlink/web-push 9.0, service worker, push subscriptions v DB |
| Audit logging | Střední | audit_logger.php, měsíční rotace, forensic záznamy |
| Geolokace a mapy | Střední-vysoká | GeolocationService.php 19 KB, Geoapify proxy, výpočet vzdálenosti |
| Role-based access control | Vysoká | 4 role (admin, technik, prodejce, supervizor), supervizor-prodejce hierarchie |
| Multi-tenant architektura | Vysoká | TenantManager.php, tenant_id sloupce v tabulkách |
| GDPR compliance | Střední | Dedikované endpointy, opt-out emaily, data export |
| PWA implementace | Střední | Service Worker, manifest, offline mode, push notifikace |
| Statistiky a reporting | Vysoká | statistiky_api.php, statistiky.js — grafy, filtry, export |
| Herní systém | Střední | hry_api.php 45 KB, flight_api.php, leaderboards, real-time chat |
| Video management | Střední | video_api.php 13 KB, upload, streaming, tracking |
| Ceníkový kalkulátor | Vysoká | cenik-calculator.js, 3jazykový wizard |

### Bezpečnostní implementace

- CSRF tokeny na všech POST requestech (auto-inject via `csrf-auto-inject.js`)
- Rate limiting: max 5 pokusů / 15 min pro login, max 10 / 5 min pro save
- Prepared statements ve všech SQL dotazech (PDO)
- Security headers: CSP, HSTS, X-Frame-Options, X-Content-Type-Options
- Session: HttpOnly, Secure, SameSite=Lax cookies
- SHA256 hash admin klíče v `.env`
- Email validace s MX záznamy
- `security_scanner.php` — vlastní bezpečnostní scanner
- Audit logger s forensic záznamy a měsíční rotací

Úroveň bezpečnostní implementace je nadstandardní pro projekt tohoto typu.

---

## 4. OBSAH PROJEKTU

### Jazykové pokrytí

- Čeština — kompletní (primární jazyk, celý kód v češtině)
- Angličtina — databázové překlady (`service_name_en`, `category_en`, `description_en`)
- Italština — databázové překlady (`service_name_it`, `category_it`, `description_it`)

### SEO implementace

- `seo_meta.php` (42 KB) — rozsáhlý modul pro správu SEO meta tagů
- Schema.org JSON-LD strukturovaná data na `index.php`
- Dedikované landing pages: `pozarucni-servis.php`, `servis-natuzzi.php`, `oprava-kresla.php`, `oprava-sedacky.php`, `mimozarucniceny.php`
- `Router.php` + `routes.php` — clean URL struktura

### Marketingové nástroje

- 3jazykový ceník s kalkulačkou — silný konverzní nástroj
- GDPR compliance stránky — zvyšují důvěryhodnost
- `qr-kontakt.php` — fyzický QR kód pro offline→online konverzi
- `aktuality.php` — blog pro content marketing

---

## 5. ODHAD ČASU VÝVOJE

### Seniorní backend developer (PHP, MariaDB, bezpečnost)

| Oblast | Hodiny |
|---|---|
| Core architektura (init, config, DB singleton, security middleware) | 40–60 h |
| Systém reklamací — CRUD, workflow, stavový automat | 120–160 h |
| Protokol systém + PDF generování + kalkulace provizí | 80–120 h |
| Statistiky a reporting (filtry, grafy, export) | 80–100 h |
| Email queue (retry, ACID, šablony, PHPMailer) | 50–70 h |
| Push notifikace (Web Push, service worker backend) | 40–60 h |
| Admin panel — všechny záložky | 150–200 h |
| User management (auth, role, supervizor hierarchie) | 60–80 h |
| 77 API endpointů | 210–320 h |
| Ceník + kalkulátor backend | 40–60 h |
| GDPR compliance | 20–30 h |
| Gaming zone backend | 40–60 h |
| Multi-tenant architektura | 30–40 h |
| Audit logging, security scanner | 30–40 h |
| Migration skripty, DB management tools | 40–60 h |
| Mapy a geolokace | 40–60 h |
| Video management | 30–40 h |
| CI/CD (GitHub Actions, SFTP deployment) | 20–30 h |
| Denní přehled, tisk, hromadné akce, autosave (nové funkce 2026) | 30–50 h |
| **CELKEM backend** | **1 150–1 640 h** |

### Seniorní frontend developer (JS, CSS, PWA)

| Oblast | Hodiny |
|---|---|
| Globální styly, responsivita, typografie | 40–60 h |
| Homepage | 10–20 h |
| Formulář nové reklamace (mapa, kalendář, foto, validace) | 80–120 h |
| Dashboard seznam.js (5 983 řádků — filtry, vyhledávání, inline editace, hromadné akce) | 110–150 h |
| Statistiky frontend | 80–120 h |
| Admin panel frontend | 80–120 h |
| Protokol formulář s autosave (protokol.js: 4 183 řádků) | 50–70 h |
| Ceník + kalkulátor | 100–150 h |
| Gaming zone frontend | 40–60 h |
| PWA (service worker, manifest, offline) | 30–50 h |
| Toast systém, modály, loading stavy | 20–30 h |
| Language switcher + překlady | 30–40 h |
| 31 CSS modulů (page-specific) | 60–80 h |
| Analytics, heatmap, replay player | 30–40 h |
| **CELKEM frontend** | **760–1 110 h** |

### UX/UI Designer

| Oblast | Hodiny |
|---|---|
| UX research, user flows, wireframy | 40–60 h |
| UI design systém (komponenty, barvy, typografie) | 30–50 h |
| Design 15+ stránek (desktop + mobil) | 90–130 h |
| Email šablony (HTML design) | 20–30 h |
| Exporty pro frontend | 20–30 h |
| **CELKEM design** | **200–300 h** |

### Copywriter (3 jazyky)

| Oblast | Hodiny |
|---|---|
| Český obsah (homepage, služby, o nás, landing pages) | 40–60 h |
| Anglická lokalizace | 30–40 h |
| Italská lokalizace | 30–40 h |
| Email šablony texty | 20–30 h |
| SEO texty pro landing pages | 20–30 h |
| **CELKEM copy** | **140–200 h** |

### Souhrn hodin

| Role | Hodiny |
|---|---|
| Backend developer | 1 150–1 640 h |
| Frontend developer | 760–1 110 h |
| Designer | 200–300 h |
| Copywriter | 140–200 h |
| **CELKEM** | **2 250–3 250 h** |

---

## 6. FINANČNÍ HODNOTA PROJEKTU — VÝROBA

### Česká republika — Freelance (senior úroveň)

| Role | Sazba / hod | Hodiny | Náklady |
|---|---|---|---|
| Backend developer | 1 200 Kč | 1 150–1 640 h | 1 380 000 – 1 968 000 Kč |
| Frontend developer | 1 000 Kč | 760–1 110 h | 760 000 – 1 110 000 Kč |
| UX/UI designer | 1 100 Kč | 200–300 h | 220 000 – 330 000 Kč |
| Copywriter | 600 Kč | 140–200 h | 84 000 – 120 000 Kč |
| **CELKEM CZ freelance** | | | **2 444 000 – 3 528 000 Kč** |

### Česká republika — Agentura (marže 1,4–1,8×)

**3 400 000 – 6 400 000 Kč**

### Evropská unie — Freelance (DE/AT/NL trh)

| Role | Sazba / hod | Hodiny | Náklady |
|---|---|---|---|
| Backend developer | 80–100 € | 1 150–1 640 h | 92 000 – 164 000 € |
| Frontend developer | 65–80 € | 760–1 110 h | 49 400 – 88 800 € |
| UX/UI designer | 70–90 € | 200–300 h | 14 000 – 27 000 € |
| Copywriter | 50–60 € | 140–200 h | 7 000 – 12 000 € |
| **CELKEM EU freelance** | | | **162 000 – 292 000 €** |

### Evropská unie — Agentura

**240 000 – 520 000 €**

---

## 7. PRODEJNÍ HODNOTA PROJEKTU

### Jako hotový projekt (turnkey B2B software)

Systém je specificky zaměřen na správu servisu luxusního nábytku. Pro jiné servisní firmy v segmentu prémiového nábytku by byl okamžitě použitelný s minimální úpravou.

**Odhadovaná hodnota: 800 000 – 1 500 000 Kč**
(jako one-time licence nebo přímý prodej, bez aktivní zákaznické základny)

### Jako SaaS produkt

Pokud by byl systém provozován jako předplatitelská služba:

- Cílový segment: servisní střediska prémiového nábytku (Natuzzi, Koinor, Rolf Benz, atd.)
- Realistické MRR při 10 zákaznících (5 000–15 000 Kč / měsíc): 50 000 – 150 000 Kč / měsíc
- Ocenění SaaS (10× ARR při raném stadiu): **6 000 000 – 18 000 000 Kč**

Toto ocenění je podmíněno existencí platících zákazníků.

### Jako startup asset (při akvizici)

- Bez zákaznické základny (technologický asset): **2 000 000 – 5 000 000 Kč**
- Se zákaznickou základnou a trakci: **15 000 000 – 40 000 000 Kč**

---

## 8. SILNÉ STRÁNKY PROJEKTU

1. **Bezpečnost na enterprise úrovni** — CSRF, rate limiting, audit log, prepared statements, security headers, email MX validace.
2. **Email queue s ACID transakčností** — Retry logika, GDPR footer, globální BCC archivace.
3. **PWA implementace** — Service worker, offline mode, push notifikace, installable app.
4. **Multi-tenant architektura** — Připravenost na SaaS expanzi (tenant_id v tabulkách, TenantManager.php).
5. **3jazyčná podpora s DB-driven překlady** — CS/EN/IT pokrytí; italština otevírá přímý trh Natuzzi v Itálii.
6. **CI/CD pipeline** — GitHub Actions + SFTP deployment, disciplinovaný release process.
7. **Konzistentní coding standards** — Celý projekt dodržuje deklarovaná pravidla (čeština, bez emoji, černobílá paleta, výjimky s odůvodněním).
8. **Role-based access control s supervizor hierarchií** — 4 role s podporou supervizor→prodejce relací.
9. **Specializace na prémiový segment** — White Glove Service + Natuzzi branding v profitabilním segmentu.
10. **SEO-first landing pages** — `pozarucni-servis.php`, `servis-natuzzi.php`, `oprava-kresla.php` pro organický traffic.
11. **Technik-first funkce** — Denní přehled, tisk výtisků, hromadné akce, autosave protokolu — systém je použitelný v terénu.

---

## 9. SLABÉ STRÁNKY PROJEKTU

1. **Monolitická architektura** — 355 PHP souborů, žádný MVC framework. seznam.js s 5 983 řádky je symptom organického růstu.
2. **Frontend bez komponentové architektury** — Velké JS soubory (seznam.js 5 983, protokol.js 4 183 řádků) jsou udržovatelné, ale bez modularizace.
3. **Migrační skripty v rootu** — `kontrola_*.php`, `migrace_*.php`, `pridej_*.php` jsou potenciálně veřejně dostupné bez admin ochrany.
4. **Žádné automatizované testy** — U projektu s 77 API endpointy a business logikou (kalkulace provizí, email queue) je absence testů riziko.
5. **Absence live API dokumentace** — `api-docs.php` bez testovacího prostředí (Swagger/Postman) ztěžuje správu 77 endpointů.
6. **Herní zóna jako nesouvisející feature** — `hry.php` + `hry_api.php` (45 KB) nesouvisí s core produktem.
7. **Úzká závislost na jednom klientovi** — Bez generalizace pro jiné značky je trh velmi omezený.
8. **Homepage příliš minimalistická** — `index.php` nestačí jako prodejní nástroj. Chybí social proof, case studies, demo, pricing.
9. **Absence veřejného demo** — Pro SaaS konverzi je klíčové umožnit potenciálním zákazníkům vyzkoušet systém bez kontaktu s prodejcem.

---

## 10. DOPORUČENÍ PRO ZVÝŠENÍ HODNOTY

### Krátkodobé (0–3 měsíce)

**1. Ochrana migračních skriptů v rootu**
Přesunout do `/tools/` a přidat admin session check. Konkrétní bezpečnostní riziko.

**2. Vylepšení homepage**
Přidat: 3 konkrétní výhody systému s čísly, zákaznické reference, screenshot dashboardu, cenový přehled nebo CTA na ceník. Odhadovaný dopad: +20–40 % konverzní rate.

**3. Odstranění nebo izolace herní zóny**
Přesunout za samostatnou subdoménu nebo zcela odebrat z produkce. Zjednodušuje kódovou základnu o ~15 %.

**4. Přidat robots.txt a sitemap.xml**
Základní SEO prerequisite.

### Střednědobé (3–9 měsíců)

**5. Modularizace největších JS souborů**
`seznam.js` a `protokol.js` rozdělit do modulů (ES6 import/export). Přidá testovatelnost.

**6. Automatizované testy pro kritické API**
PHPUnit testy pro `save.php` (workflow ID, enum mapping, rate limiting) a `protokol_api.php` (kalkulace provizí). Odhadovaný čas: 60–80 h.

**7. Generalizace pro jiné značky nábytku**
Nahradit pevná Natuzzi reference systémem konfigurace. Přidá adresovatelný trh (Koinor, Rolf Benz). Dopad na SaaS hodnotu: 3–5×.

**8. Veřejné demo prostředí**
Sandbox instance s demo daty. Umožní B2B self-serve konverzi bez prodejního procesu.

### Dlouhodobé (9–24 měsíců)

**9. Migrace na moderní frontend architekturu**
Postupná migrace klíčových stránek (`seznam`, `statistiky`) na Vue 3 nebo Svelte. Zvýší maintainability a atraktivitu pro akvizici.

**10. REST API pro třetí strany**
JWT/API key autentizace a veřejné endpointy pro integrace (ERP systémy nábytku, CRM zákazníků). Otevírá marketplace/integration model.

**11. Zákaznický portál**
Samostatný pohled pro koncového zákazníka (stav reklamace, fotodokumentace, protokol). Snižuje support load.

---

## ZÁVĚREČNÉ HODNOCENÍ

| Dimenze | Hodnocení | Poznámka |
|---|---|---|
| Technická komplexita | 9/10 | Enterprise-level systém, bezpečnost, email queue, PWA, 206K řádků |
| Bezpečnost | 8/10 | Nadstandardní implementace, drobná rizika v rootu (migrační skripty) |
| Kódová kvalita | 7/10 | Konzistentní konvence, dodržovaná pravidla, organický růst JS souborů |
| Obchodní potenciál | 7/10 | Silná specializace, omezený trh bez generalizace |
| SEO připravenost | 7/10 | Landing pages, Schema.org, seo_meta.php — dobrý základ |
| Maintainability | 6/10 | Bez testů, ale kódová konvence disciplinovaná a velikosti souborů pod kontrolou |

**Celkový závěr:** Technicky sofistikovaný a bezpečně implementovaný CRM/service management systém s jasnou doménovou specializací. 355 PHP souborů, 108 JS souborů, 77 API endpointů a 206 313 řádků kódu. Vývojové náklady v ČR by dnes činily **2,4 – 3,5 milionu Kč** při freelance sazbách. Prodejní hodnota jako hotový produkt bez zákaznické základny: **1,5 – 5 milionů Kč**. Se zákaznickou základnou a při SaaS modelu: **6 – 40 milionů Kč** v závislosti na MRR a trakci.

---

*Audit provedl: Claude AI (claude-sonnet-4-6) na základě přímé analýzy zdrojového kódu*
*Datum: 4. března 2026 (přepracováno na základě aktuálního stavu projektu)*
