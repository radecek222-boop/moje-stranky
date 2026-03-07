# REFAKTORING LOG
## White Glove Service (WGS) - Historie změn a technického dluhu

---

## 2026-03-07 — Bezpečnostní opravy (Batch 1)

### Provedené změny

#### 1. SQL Injection oprava — `/api/hry_api.php`
- **Problém:** `$pdo->exec()` s přímou interpolací `$zpravaId` na řádcích 249, 258
- **Oprava:** Nahrazeno prepared statements s `$stmtLikes->execute(['zprava_id' => ..., 'id' => ...])`
- **Riziko opravy:** Velmi nízké — logika identická, pouze bezpečný vzor
- **Přínos:** Eliminace bad practice SQL interpolace, konzistentní vzor

#### 2. CSRF ochrana — `/admin/email_queue.php`
- **Problém:** POST handler bez CSRF validace (admin-only ale CSRF útok byl možný)
- **Oprava:** Přidán `require_once csrf_helper.php`, validace v POST handleru, token do všech formulářů
- **Bonus:** Odstraněny emoji z tlačítek (soulad s CLAUDE.md no-emoji policy)
- **Riziko opravy:** Nízké — formuláře fungují identicky, jen s CSRF ochranou

#### 3. CSRF ochrana — `/admin/smtp_settings.php`
- **Problém:** Oba POST formuláře bez CSRF validace
- **Oprava:** Přidán CSRF token do obou formulářů + validace v POST handleru
- **Riziko opravy:** Nízké

#### 4. CSRF ochrana — `/admin/install_email_system.php`
- **Problém:** Instalační POST formulář bez CSRF validace
- **Oprava:** Přidán CSRF token + validace, odstraněn emoji z tlačítka
- **Riziko opravy:** Nízké

#### 5. GET→POST + CSRF — `/api/preloz_aktualitu.php`
- **Problém:** Data-modifying endpoint přijímal GET požadavky (`$_REQUEST`), bez CSRF
- **Oprava:** Vynuceno `$_SERVER['REQUEST_METHOD'] === 'POST'`, přidána CSRF validace
- **Riziko opravy:** Střední — frontend volající tento endpoint MUSÍ použít POST + CSRF token
- **Poznámka:** Ověřit zda frontend správně volá POST (nikoliv GET)

#### 6. Nový soubor — `/pridej_chybejici_indexy_2026.php`
- Bezpečný migrační skript pro přidání 4 chybějících DB indexů
- Bezpečný — kontroluje existenci před přidáním, idempotentní

#### 7. Nová dokumentace — `/docs/`
- `architecture.md` — Systémová architektonická zpráva
- `security.md` — Bezpečnostní audit a doporučení
- `database.md` — DB analýza a optimalizační plán
- `refactoring-log.md` — Tento soubor

### Git commit
- Branch: `claude/complete-assigned-task-QdX64`
- Commit message: `BEZPECNOST: Oprava SQL interpolace a CSRF validace + architektonicka dokumentace`

---

## PLÁNOVANÉ ZMĚNY — Batch 2

### Výkonová optimalizace (priorita: střední)
- [ ] Přidat MIME type whitelist do upload handlerů
- [ ] Přidat path traversal check do upload handlerů
- [ ] Retenční politika pro wgs_pageviews (DELETE starší 90 dní)
- [ ] Cleanup expired remember tokens v cron_denni.php

### API konzistence (priorita: nízká)
- [ ] Sjednotit API response formát (sendJsonSuccess/Error vs echo json_encode)
- [ ] Identifikovat a eliminovat duplicitní action handlery

### Architekturální zlepšení (priorita: nízká, dlouhodobé)
- [ ] Extrahovat upload logiku do centrální třídy `SouborManager`
- [ ] Extrahovat email logiku do `EmailService` (místo přímého volání EmailQueue)
- [ ] Rozdělit monolitický admin_email_sms.php (115 KB) na menší moduly

---

## TECHNICKÝ DLUH — Inventura

### Kritický (musí být opraven)
- [x] SQL interpolace v hry_api.php (OPRAVENO)
- [x] Chybějící CSRF v admin/ (OPRAVENO)

### Vysoký (opravit brzy)
- [ ] Upload bez MIME whitelist (documents_api.php, video_api.php)
- [ ] Upload bez path traversal check
- [ ] Chybějící indexy (viz pridej_chybejici_indexy_2026.php)

### Střední (plánovat)
- [ ] Nekonzistentní API response formát (50+ míst s echo json_encode)
- [ ] Analytics bez retenční politiky (wgs_pageviews roste neomezeně)
- [ ] Monolitické soubory (admin_email_sms.php 115 KB, transport.php 123 KB)

### Nízký (zlepšení)
- [ ] MD5/SHA1 pro cache klíče → SHA256
- [ ] SELECT * → explicitní sloupce v produkčních dotazech
- [ ] Dokumentace API endpointů (aktualizovat docs/api.md)

---

## SOUBORY NEUPRAVOVAT BEZ HLUBOKÉHO POROZUMĚNÍ

| Soubor | Důvod |
|--------|-------|
| `/init.php` | Bootstrap — jakákoliv chyba zruší celou aplikaci |
| `/config/config.php` | Konfigurace — chyba = žádné DB spojení |
| `/includes/csrf_helper.php` | Security-critical — neupravovat |
| `/includes/security_headers.php` | Security-critical |
| `/app/controllers/save.php` | Core business logika s transakcemi |
| `/assets/js/seznam.js` | 142 KB — komplexní, mnoho závislostí |
| `/includes/admin_email_sms.php` | 115 KB — mnoho závislostí |
