# 🎯 Návod: Vizuální Mapping PDF → Formulář

**Pro děti i dospělé!** 😊

---

## 📋 Co to dělá?

Tento nástroj ti umožní **vizuálně spojit** data z PDF protokolu s poli ve formuláři novareklamace.php.

Je to jako **spojovačka** - vidíš co parser našel vlevo, vidíš pole ve formuláři vpravo, a **prostě napíšeš čísla** která k sobě patří!

---

## 🚀 Jak to použít?

### KROK 1: Otevři nástroj

```
https://www.wgs-service.cz/vizualni_mapping_pdf.html
```

### KROK 2: Nahraj PDF

1. Klikni na **"VYBER PDF SOUBOR"**
2. Vyber jeden z testovacích PDF:
   - NATUZZI PROTOKOL.pdf
   - NCM-NATUZZI.pdf
   - PHASE CZ.pdf
   - PHASE PROTOKOL SK.pdf

### KROK 3: Počkej na načtení

Parser:
- ✅ Načte PDF pomocí PDF.js
- ✅ Extrahuje text
- ✅ Pošle na API endpoint
- ✅ Zobrazí co našel

### KROK 4: Spoj data čísly

**Uvidíš 3 sloupce:**

```
┌─────────────────────┐     ┌───┐     ┌─────────────────────┐
│ CO NAŠEL PARSER     │     │ → │     │ POLE VE FORMULÁŘI   │
├─────────────────────┤     ├───┤     ├─────────────────────┤
│ cislo_reklamace:    │     │ 1 │ →   │ 1. Číslo reklamace  │
│ "NCE25-00002444"    │     │   │     │    (cislo)          │
├─────────────────────┤     ├───┤     ├─────────────────────┤
│ email:              │     │ 3 │ →   │ 2. Jméno a příjmení │
│ "jan@email.cz"      │     │   │     │    (jmeno)          │
├─────────────────────┤     ├───┤     ├─────────────────────┤
│ telefon:            │     │ 4 │ →   │ 3. Email            │
│ "777 123 456"       │     │   │     │    (email)          │
└─────────────────────┘     └───┘     └─────────────────────┘
```

**Co dělat:**
- Podívej se co je **vlevo** (data z PDF)
- Podívej se co je **vpravo** (pole formuláře)
- Do **kruhového inputu uprostřed** napiš číslo pole kam to patří

**Příklad:**
```
Parser našel "email: jan@email.cz"
→ Napíšu číslo 3 (protože Email je na 3. pozici vpravo)

Parser našel "telefon: 777 123 456"
→ Napíšu číslo 4 (protože Telefon je na 4. pozici vpravo)
```

### KROK 5: Klikni "ULOŽIT MAPPING"

1. Zkontroluj v potvrzovacím okně jestli je vše správně
2. Klikni **OK**
3. Nástroj ti ukáže:
   - ✅ SQL příkaz pro update databáze
   - 📋 Tlačítko "ZKOPÍROVAT SQL"

### KROK 6: Spusť SQL příkaz

**Varianta A - Přímo v databázi:**
1. Zkopíruj SQL příkaz
2. Jdi do phpMyAdmin
3. Vlož SQL příkaz
4. Spusť ho

**Varianta B - Přes migrační skript:**
1. Zkopíruj SQL příkaz
2. Vytvoř nový `.php` soubor (např. `aplikuj_mapping.php`)
3. Vlož SQL do `$pdo->exec("...");`
4. Spusť skript

---

## 📊 Příklad Výstupu

Po kliknutí na "ULOŽIT MAPPING" dostaneš něco takového:

```sql
UPDATE wgs_pdf_parser_configs
SET pole_mapping = '{
    "cislo_reklamace": "cislo",
    "jmeno": "jmeno",
    "email": "email",
    "telefon": "telefon",
    "ulice": "ulice",
    "mesto": "mesto",
    "psc": "psc",
    "model": "model",
    "barva": "barva",
    "popis_problemu": "popis_problemu"
}'
WHERE nazev = 'NATUZZI Protokol';
```

**To znamená:**
- Parser key `cislo_reklamace` → půjde do pole `cislo`
- Parser key `email` → půjde do pole `email`
- atd...

---

## 🎓 Důležité Poznámky

### ✅ CO DĚLAT:
1. **Spoj všechny** důležité položky (číslo, jméno, email, telefon, adresa, PSČ, město)
2. **Zkontroluj** že čísla odpovídají správným polím
3. **Zkopíruj SQL** a spusť ho

### ❌ CO NEDĚLAT:
1. **Nespojuj** prázdné hodnoty (přeskoč je)
2. **Nepoužívej** stejné číslo vícekrát (každé číslo jen jednou!)
3. **Nespouštěj SQL** dokud si nejsi jistý že je správný

---

## 🔧 Technické Detaily

### Jak to funguje:

1. **PDF.js** načte PDF a extrahuje text
2. **API endpoint** `/api/parse_povereni_pdf.php` parsuje text podle aktuální konfigurace
3. **Vizuální nástroj** zobrazí extrahovaná data a pole formuláře
4. **Uživatel** spojí data čísly
5. **API endpoint** `/api/uloz_pdf_mapping.php` vygeneruje SQL příkaz
6. **Administrátor** spustí SQL příkaz v databázi

### Struktura Mappingu:

```json
{
  "pdf_parser_key": "formular_field_name"
}
```

**Příklad:**
```json
{
  "cislo_reklamace": "cislo",
  "email": "email",
  "telefon": "telefon"
}
```

---

## 📁 Soubory

| Soubor | Účel |
|--------|------|
| `vizualni_mapping_pdf.html` | Vizuální nástroj (frontend) |
| `api/uloz_pdf_mapping.php` | API endpoint pro uložení mappingu |
| `api/parse_povereni_pdf.php` | API endpoint pro parsování PDF |
| `NAVOD_VIZUALNI_MAPPING.md` | Tento návod |

---

## 🐛 Řešení Problémů

### Problem: Parser nenašel žádná data
**Řešení:**
- Zkontroluj že PDF je NATUZZI nebo PHASE protokol
- Podívej se do konzole prohlížeče (F12) na chybové hlášky
- Zkus jiné PDF

### Problem: SQL příkaz nefunguje
**Řešení:**
- Zkontroluj že jsi zkopíroval celý SQL příkaz
- Zkontroluj že konfigurace s daným názvem existuje v databázi
- Spusť SQL příkaz v phpMyAdmin

### Problem: Mapping se neuložil
**Řešení:**
- Zkontroluj že jsi přihlášen jako admin
- Zkontroluj CSRF token (reload stránku)
- Podívej se do `/logs/php_errors.log`

---

## 💡 Tips & Tricks

### Tip 1: Začni s jedním PDF
Nejdřív udělej mapping pro **jeden typ** PDF (např. NATUZZI), otestuj ho, a pak teprve pokračuj na další.

### Tip 2: Používej konzoli
Otevři Developer Console (F12) a sleduj co se děje - vidíš tam všechny API požadavky a odpovědi.

### Tip 3: Backup před změnou
Před spuštěním SQL příkazu si **zálohuj databázi** (nebo aspoň tabulku `wgs_pdf_parser_configs`).

### Tip 4: Testuj po každé změně
Po aplikaci mappingu **vždy otestuj** na `test_pdf_parsing.php` s reálným PDF.

---

## 📞 Další Pomoc

Pokud něco nefunguje:
1. Podívej se do konzole (F12)
2. Podívej se do `/logs/php_errors.log`
3. Spusť `diagnostika_pdf_parseru.php`
4. Kontaktuj vývojáře

---

**Happy Mapping!** 🎯🚀

**Vytvořeno:** 2025-11-20
**Autor:** Claude
**Session:** claude/test-pdf-parsing-01M1zjcPLu3Jbtby8AdCfTNa
