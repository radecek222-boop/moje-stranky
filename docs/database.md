# DB ANALÝZA A OPTIMALIZAČNÍ PLÁN
## White Glove Service (WGS) - Databázová dokumentace

**Datum analýzy:** 2026-03-07
**DB:** MariaDB 10.11+

---

## 1. PŘEHLED TABULEK

### Core tabulky (obchodně kritické)

| Tabulka | Účel | Riziko růstu |
|---------|------|-------------|
| `wgs_reklamace` | Hlavní reklamace/objednávky | Nízké (lineární s businessem) |
| `wgs_users` | Uživatelské účty | Velmi nízké |
| `wgs_registration_keys` | Přístup k registraci | Velmi nízké |
| `wgs_nabidky` | Cenové nabídky | Nízké |
| `wgs_kalkulace` | Kalkulace nabídek | Nízké |
| `wgs_pricing` | Ceník služeb | Velmi nízké |

### Queue tabulky (operačně kritické)

| Tabulka | Účel | Riziko růstu |
|---------|------|-------------|
| `wgs_email_queue` | Asynchronní email fronta | Střední (archivovat sent záznamy) |
| `wgs_pending_actions` | Asynchronní akce | Nízké (cleanup po provedení) |

### Analytics tabulky (RIZIKO NEKONTROLOVANÉHO RŮSTU)

| Tabulka | Účel | Riziko růstu |
|---------|------|-------------|
| `wgs_pageviews` | Page view tracking | **VYSOKÉ** — každý view = záznam |
| `wgs_heatmap` | Heatmap kliknutí | **STŘEDNÍ** |
| `wgs_analytics_geolocation_cache` | Geo cache | Střední |
| `wgs_analytics_ignored_ips` | IP blacklist | Nízké |
| `wgs_analytics_bot_whitelist` | Bot whitelist | Nízké |

### Auth a session tabulky

| Tabulka | Účel | Riziko růstu |
|---------|------|-------------|
| `wgs_remember_tokens` | Remember Me cookies | Střední (cleanup expired) |

### Notifikace

| Tabulka | Účel | Riziko růstu |
|---------|------|-------------|
| `wgs_notifications` | Šablony notifikací | Nízké |
| `wgs_push_subscriptions` | Web Push subskripce | Střední |

### Média

| Tabulka | Účel | Riziko růstu |
|---------|------|-------------|
| `wgs_photos` | Fotografie k reklamacím | Nízké (omezeno na reklamace) |
| `wgs_documents` | Dokumenty | Nízké |
| `wgs_videos` | Video záznamy | Střední |
| `wgs_video_tokens` | Video access tokeny | Nízké (cleanup expired) |

### Herní zóna

| Tabulka | Účel | Riziko růstu |
|---------|------|-------------|
| `wgs_hry_chat` | Chat herní zóny | Střední (chat roste) |
| `wgs_hry_chat_likes` | Likes zpráv | Střední |
| `wgs_hry_online` | Online hráči | Nízké (cleanup 5min timeout) |
| `wgs_hry_skore` | Výsledky her | Nízké |

---

## 2. EXISTUJÍCÍ INDEXY (z migrace add_performance_indexes.sql)

### wgs_reklamace
```sql
idx_reklamace_id        (reklamace_id)
idx_cislo               (cislo)
idx_stav                (stav)
idx_created_by          (created_by)
idx_created_at_desc     (created_at DESC)
idx_assigned_to         (assigned_to)
idx_stav_created        (stav, created_at)  -- composite
```

### wgs_photos
```sql
idx_reklamace_id        (reklamace_id)
idx_section_name        (section_name)
idx_reklamace_section_order  (reklamace_id, section_name, sort_order)  -- composite
```

### wgs_documents
```sql
idx_claim_id            (claim_id)
idx_reklamace_id        (reklamace_id)
idx_created_at          (created_at)
```

### wgs_users
```sql
idx_email               (email)
idx_role                (role)
```

### wgs_email_queue
```sql
idx_status              (status)
idx_scheduled           (scheduled_at)
idx_priority            (priority, status, scheduled_at)
```

### wgs_notes
```sql
idx_created_by          (created_by)
idx_claim_created       (claim_id, created_at)  -- composite
idx_created_at_desc     (created_at DESC)
```

---

## 3. CHYBĚJÍCÍ INDEXY (opravit pomocí pridej_chybejici_indexy_2026.php)

| Tabulka | Index | Sloupec | Důvod |
|---------|-------|---------|-------|
| `wgs_users` | `idx_user_id_varchar` | `user_id` (VARCHAR) | JOIN: `r.created_by = prodejce.user_id` |
| `wgs_email_queue` | `idx_notification_id` | `notification_id` | JOIN na `wgs_notifications` |
| `wgs_reklamace` | `idx_updated_at` | `updated_at` | ORDER BY v admin pohledech |
| `wgs_push_subscriptions` | `idx_push_user_id` | `user_id` | WHERE při vyhledávání subscriptions |

**Spustit:** `https://www.wgs-service.cz/pridej_chybejici_indexy_2026.php`

---

## 4. ENUM MAPOVÁNÍ (KRITICKÉ)

Databáze používá anglické lowercase ENUM hodnoty, frontend používá české uppercase:

```
Frontend (JS)     ↓ save.php ↓     Databáze
'ČEKÁ'        →               'wait'
'DOMLUVENÁ'   →               'open'
'HOTOVO'      →               'done'
'ČEKÁME NA DÍLY' →            'cekame_na_dily'
'CZ'          →               'cz'
'SK'          →               'sk'
```

Toto mapování probíhá v `app/controllers/save.php` a musí být udržováno konzistentně.

---

## 5. TRANSAKČNÍ BEZPEČNOST

Atomicita je zajištěna v kritických místech:
- `app/controllers/save.php` — CREATE a UPDATE reklamace
- `api/statistiky_api.php` — UPDATE reklamace
- `api/delete_reklamace.php` — Kaskádové mazání

Vzor:
```php
$pdo->beginTransaction();
try {
    // operace
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}
```

---

## 6. RETENČNÍ POLITIKA (DOPORUČENÍ)

### Analytics tabulky — NEJVYŠŠÍ PRIORITA

`wgs_pageviews` roste při každém page view a nemá žádnou retenční politiku. Doporučení:

```sql
-- Archivovat záznamy starší 90 dní (spouštět měsíčně)
DELETE FROM wgs_pageviews
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Alternativa: Archivovat do souhrnné tabulky
INSERT INTO wgs_pageviews_mesicni_souhrn (rok, mesic, stranka, pocet_navstev)
SELECT YEAR(created_at), MONTH(created_at), page_url, COUNT(*)
FROM wgs_pageviews
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
GROUP BY YEAR(created_at), MONTH(created_at), page_url;
-- Pak DELETE
```

### Email Queue
```sql
-- Automaticky čištěno v cron/process-email-queue.php
-- Sent záznamy: archivovány po 30 dnech
-- Failed záznamy: po vyčerpání pokusů
```

### Remember Tokens
```sql
-- Čistit expired tokeny
DELETE FROM wgs_remember_tokens WHERE expires_at < NOW();
-- Spouštět v cron_denni.php
```

---

## 7. VÝKONOVÁ DOPORUČENÍ

### Session write close
Pro API endpointy s dlouhými operacemi vždy používat:
```php
session_write_close(); // Uvolní session zámek pro paralelní požadavky
```

### LIMIT v dotazech
Vždy používat LIMIT pro list dotazy:
```php
// Pro paginaci
"LIMIT :offset, :limit"
// Pro single record
"LIMIT 1"
```

### N+1 query prevence
Fotografie a dokumenty načítat přes JOIN, ne v loopu:
```php
// Místo N+1 loopu:
// SELECT * FROM wgs_photos WHERE reklamace_id = ?  (pro každou reklamaci)

// Správně - jeden dotaz:
// SELECT r.*, GROUP_CONCAT(p.nazev) FROM wgs_reklamace r
// LEFT JOIN wgs_photos p ON r.id = p.reklamace_id
// GROUP BY r.id
```

---

*Zpráva vygenerována DB auditem 2026-03-07.*
