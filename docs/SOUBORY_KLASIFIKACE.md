# Klasifikace souborů – dokumentace

**Modul:** Přehled souborů (admin panel → ROOT Soubory)
**Soubory:** `api/soubory_api.php`, `includes/admin_soubory.php`

---

## Přehled

Klasifikační engine analyzuje každý soubor v projektu a přiřazuje mu jeden ze 4 statusů:

| Status | Kód | Význam |
|--------|-----|--------|
| Aktivní | `USED` | Nalezeny důkazy využívání (statické reference, config, runtime) |
| Bez referencí | `NO_REFS_STATIC` | Žádné statické reference, runtime audit chybí |
| Nejisté | `UNCERTAIN` | Nelze potvrdit bezpečnost (chráněný adresář, asset, root PHP) |
| Bezpečné smazat | `SAFE_TO_DELETE` | Prošly VŠECHNY kontroly včetně runtime auditu |

**Výchozí stav nejistoty:** Pokud engine nemá dostatek dat, vrátí `UNCERTAIN` nebo `NO_REFS_STATIC`. Nikdy neoznačí soubor jako `SAFE_TO_DELETE` bez runtime auditu.

---

## Pravidla (R01–R09)

### R01: Statické reference
- **Kontroluje:** `include`, `require`, `src=`, `href=`, `import` ve všech PHP/JS/CSS/HTML souborech
- **Metoda:** Regex scan celého kódu projektu
- **Výsledek:** Pokud nalezeny reference → `USED` (okamžitě, bez dalších kontrol)

### R02: Kritický/systémový soubor
- **Kontroluje:** Soubor na allowlistu: `robots.txt`, `sitemap.xml`, `manifest.json`, `CNAME`, `sw.js`, `sw.php`, `.htaccess`, `init.php`, `index.php`, `health.php`, ikony
- **Výsledek:** Pokud soubor je na seznamu → `USED`

### R03: Config/build reference
- **Kontroluje:** `composer.json` – PSR-4 autoload mapy a `autoload.files` pole
- **Metoda:** Parsování JSON a porovnání cest
- **Výsledek:** Pokud nalezena reference → `USED`

### R04: Chráněný adresář
- **Kontroluje:** Adresáře 1. úrovně: `includes/`, `config/`, `app/`, `api/`, `cron/`, `scripts/`, `setup/`, `migrations/`, `lib/`, `temp/`, `data/`
- **Důvod:** Dynamické includy, autoload, cron – statická analýza nemusí zachytit
- **Výsledek:** Pokud soubor v chráněném adresáři → `UNCERTAIN`

### R05: Veřejný URL entrypoint
- **Kontroluje:** PHP soubor v kořenu projektu (přístupný přes `.htaccess` RewriteRule)
- **Důvod:** Každý PHP v rootu je potenciálně přístupný jako URL stránka
- **Výsledek:** Pokud root PHP → `UNCERTAIN`

### R06: Veřejný asset
- **Kontroluje:** Cesty začínající `assets/`, `uploads/`, `screen/`
- **Důvod:** Statické assety – `.htaccess: RewriteRule ^assets/ - [L]`. Mohou být cachovány klientem, lazy-loadovány, referencovány z SW cache
- **Výsledek:** Pokud asset → `UNCERTAIN`

### R07: Artefakt
- **Kontroluje:** Vzory: `*.bak`, `*.old`, `*.tmp`, `*.archive`, `*.backup`, `*.orig`, `*~`, `*.bak_*`, `*_old.*`
- **Výsledek:** Příznak artefaktu – kandidát na smazání (kombinuje se s dalšími pravidly)

### R08: Runtime audit
- **Kontroluje:** HTTP requesty zaznamenané v `logs/runtime_audit.jsonl` za posledních 14 dní
- **Metoda:** Párování cesty souboru s přístupovými záznamy
- **Výsledek:**
  - Pokud runtime hity > 0 → `USED`
  - Pokud runtime audit není aktivní → `NO_REFS_STATIC` (nelze potvrdit)
  - Pokud runtime aktivní a 0 hitů → pokračuje na R09

### R09: Stáří souboru
- **Kontroluje:** Minimální stáří 30 dní od poslední změny (`mtime`)
- **Výjimka:** Artefakty (R07) – stáří prominuto
- **Výsledek:** Pokud příliš čerstvý → `UNCERTAIN`

---

## Finální rozhodnutí

`SAFE_TO_DELETE` pouze pokud **VŠECHNA** tato kritéria platí:
1. Žádné statické reference (R01)
2. Není kritický soubor (R02)
3. Není v config/build (R03)
4. Není v chráněném adresáři (R04)
5. Není root PHP entrypoint (R05)
6. Není veřejný asset (R06)
7. Runtime audit **je** aktivní (R08)
8. 0 runtime requestů v posledních 14 dnech (R08)
9. Soubor starší 30 dní NEBO jde o artefakt (R07, R09)

---

## Runtime audit

### Aktivace

Runtime audit zaznamenává HTTP requesty do `logs/runtime_audit.jsonl`.

Pro aktivaci přidejte do `init.php` nebo `.htaccess`:

```php
// init.php – na konec souboru
$runtimeLogSoubor = __DIR__ . '/logs/runtime_audit.jsonl';
if (is_writable(dirname($runtimeLogSoubor))) {
    $zaznam = json_encode([
        'ts'     => time(),
        'cesta'  => $_SERVER['REQUEST_URI'] ?? '',
        'status' => http_response_code() ?: 200,
    ]) . "\n";
    @file_put_contents($runtimeLogSoubor, $zaznam, FILE_APPEND | LOCK_EX);
}
```

### Formát záznamu (JSONL)

```jsonl
{"ts":1709500000,"cesta":"/assets/js/utils.js","status":200}
{"ts":1709500001,"cesta":"/api/control_center_api.php","status":200}
```

| Pole | Typ | Popis |
|------|-----|-------|
| `ts` | int | Unix timestamp |
| `cesta` | string | Požadovaná URL cesta |
| `status` | int | HTTP status kód |

### Bezpečnost a ochrana soukromí

- **Nikdy** se nelogují payloady (POST data, cookies, hlavičky)
- **Nikdy** se neloguje IP adresa ani user-agent
- Loguje se pouze cesta, čas a status kód
- Maximální zpracování: 50 000 řádků
- Okno zpětného pohledu: 14 dní

### Retence a rotace

Soubor `logs/runtime_audit.jsonl` se zpracovává při každém skenu souborů. Záznamy starší než 14 dní jsou automaticky ignorovány.

Pro rotaci logu nastavte cron:
```bash
# Rotace jednou týdně – zachovat poslední 14 dní
0 3 * * 0 find /home/user/moje-stranky/logs/runtime_audit.jsonl -mtime +14 -exec truncate -s 0 {} \;
```

---

## API odpověď

Každý soubor v odpovědi `?akce=seznam` obsahuje:

```json
{
  "klasifikace": {
    "status": "USED",
    "reasons": [
      {
        "rule_id": "R01",
        "nazev": "Statické reference (include/require/src/href/import)",
        "passed": false,
        "details": "Nalezeno 3 souborů odkazujících na tento soubor: ...",
        "zdroj": "statická analýza kódu"
      }
    ],
    "evidence": {
      "staticke_reference_pocet": 3,
      "staticke_reference": ["soubor1.php", "soubor2.php", "soubor3.php"],
      "runtime_dostupny": false,
      "runtime_hity": 0,
      "runtime_okno_dni": 14,
      "stari_dni": 45
    }
  }
}
```

---

## Statistiky

Odpověď obsahuje nové statistiky:

| Pole | Popis |
|------|-------|
| `pocetUsed` | Počet souborů se statusem `USED` |
| `pocetBezRef` | Počet souborů se statusem `NO_REFS_STATIC` |
| `pocetNejiste` | Počet souborů se statusem `UNCERTAIN` |
| `pocetBezpecne` | Počet souborů se statusem `SAFE_TO_DELETE` |
| `runtimeAktivni` | Boolean – je runtime audit aktivní? |

---

## Archivace souborů

Označené soubory lze archivovat (místo fyzického smazání):

1. Označit soubory ke smazání (tlačítko "Označit")
2. Kliknout na "Archivovat označené"
3. Soubory se přesunou do `_archiv/YYYY-MM-DD_HH-MM-SS/`
4. Adresářová struktura je zachována v archivu

Archivace je **reverzibilní** – soubory lze ručně vrátit z archivu.

---

## Omezení

1. **Statická analýza ≠ pravda:** Regex scan nemusí zachytit dynamické cesty (`$var = 'soubor'; include $var;`)
2. **Bez runtime auditu:** Engine nemůže potvrdit `SAFE_TO_DELETE` – vrátí maximálně `NO_REFS_STATIC`
3. **Cache 5 minut:** Výsledky skenu se cachují. Pro čerstvá data klikněte "Znovu skenovat"
4. **Vyloučené adresáře:** `.git`, `node_modules`, `vendor`, `logs`, `backups`, `uploads`, `.github`, `cache`
