# Kontrola bezpečnostních hlaviček (15. ledna 2025)

## Zjištěný problém
- `Content-Security-Policy` v `config/config.php` nepovoluje `connect-src` k `https://fonts.googleapis.com` a `https://fonts.gstatic.com`, přestože přihlašovací a registrační stránky využívají `<link rel="preconnect">` na tyto domény. Prohlížeč proto hlásí porušení CSP a optimalizace preconnect je blokována.

## Doporučení
- Rozšířit direktivu `connect-src` o domény Google Fonts, například:
  ```php
  "connect-src 'self' https://api.geoapify.com https://maps.geoapify.com https://fonts.googleapis.com https://fonts.gstatic.com;"
  ```

---

# Kontrola stránky PSA (15. ledna 2025)

## Zjištěné problémy
- Soubor `psa.php` postrádá většinu očekávaného HTML rozvržení (výběr měsíce/roku, kontejnery atd.) a obsahuje nezačleněné uzavírací značky. JavaScript (`assets/js/psa-kalkulator.js`) proto při inicializaci spadne na `document.getElementById('monthSelect')`, protože tento element na stránce není.
- V `<main>` je chybně vložen další `<link rel="stylesheet">`, který by měl být v `<head>`. To může způsobovat nenačtení stylů v některých prohlížečích.

## Doporučení
- Sladit `psa.php` se strukturovanou verzí v `psa-kalkulator.php`, aby stránka obsahovala všechny prvky, které skript vyžaduje.
- Přesunout vnořený `<link rel="stylesheet">` zpět do `<head>`, případně sjednotit načítání stylů jen na jedno místo.
