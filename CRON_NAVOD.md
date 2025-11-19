# 📅 Automatické odesílání připomínek - Návod na nastavení

## Co tento systém dělá?

Automaticky odesílá **připomenutí termínu zákazníkům** den před návštěvou technika v **10:00 ráno**.

---

## 🔧 Nastavení na hostingu (production)

### 1. Přihlaste se na hosting panel
- URL: https://www.forpsi.com (nebo váš hosting provider)
- Přihlašovací údaje máte uložené

### 2. Najděte sekci "CRON úlohy" nebo "Cron Jobs"
- Obvykle v sekci: **Pokročilé nastavení** nebo **Advanced**

### 3. Přidejte novou CRON úlohu s těmito parametry:

#### Frekvence spuštění:
```
0 10 * * *
```

**Vysvětlení:**
- `0` = minuta (00)
- `10` = hodina (10:00)
- `*` = každý den v měsíci
- `*` = každý měsíc
- `*` = každý den v týdnu

**→ Spustí se každý den v 10:00 ráno**

#### Příkaz ke spuštění:
```bash
/usr/bin/php /home/wgs-service.cz/public_html/cron_send_reminders.php
```

**POZOR:** Upravte cestu podle vašeho hostingu!
- Forpsi obvykle používá: `/home/[vase-domena]/public_html/`
- Jiné hostingy mohou používat: `/var/www/html/` nebo `/home/[username]/www/`

---

## 🧪 Testování lokálně (před nasazením)

### Manuální spuštění z příkazové řádky:
```bash
cd /home/user/moje-stranky
php cron_send_reminders.php
```

### Co se stane:
1. Skript najde všechny návštěvy na **zítřek** (datum = zítra)
2. Pro každou návštěvu vytvoří email s připomenutím
3. Přidá emaily do fronty `wgs_email_queue`
4. Zapíše log do `/logs/cron_reminders.log`

### Výstup z testování:
```
[2025-11-19 10:00:15] === START: Kontrola návštěv pro připomenutí ===
[2025-11-19 10:00:15] Hledám návštěvy na datum: 2025-11-20
[2025-11-19 10:00:15] Nalezeno návštěv: 3
[2025-11-19 10:00:15] Zpracovávám: REK-2025-001 - Jan Novák (jan.novak@email.cz)
[2025-11-19 10:00:15] ✓ Email přidán do fronty pro: jan.novak@email.cz
[2025-11-19 10:00:16] Zpracovávám: REK-2025-002 - Petra Svobodová (petra@email.cz)
[2025-11-19 10:00:16] ✓ Email přidán do fronty pro: petra@email.cz
[2025-11-19 10:00:16] Zpracovávám: REK-2025-003 - Martin Dvořák (martin@email.cz)
[2025-11-19 10:00:16] ✓ Email přidán do fronty pro: martin@email.cz
[2025-11-19 10:00:16] ---
[2025-11-19 10:00:16] SOUHRN:
[2025-11-19 10:00:16]   Nalezeno návštěv: 3
[2025-11-19 10:00:16]   Úspěšně přidáno do fronty: 3
[2025-11-19 10:00:16]   Chyby: 0
[2025-11-19 10:00:16] === KONEC ===
```

---

## 🔍 Kontrola funkčnosti

### 1. Zkontrolujte logy:
```bash
tail -f /home/user/moje-stranky/logs/cron_reminders.log
```

### 2. Zkontrolujte emailovou frontu:
```sql
SELECT * FROM wgs_email_queue WHERE email_type = 'appointment_reminder' ORDER BY created_at DESC LIMIT 10;
```

### 3. Zkontrolujte odeslaní:
- Emaily se skutečně odesílají přes `process_email_queue.php` (pokud máte nastavený další CRON pro zpracování fronty)
- Nebo se odesílají automaticky při načtení stránky (pokud používáte automatické zpracování)

---

## ⚙️ Pokročilé nastavení

### Změna času odesílání:
Upravte CRON výraz:
- **9:00 ráno:** `0 9 * * *`
- **14:00 odpoledne:** `0 14 * * *`
- **Každé 2 hodiny:** `0 */2 * * *`

### Zaslání notifikace administrátorovi:
Upravte soubor `cron_send_reminders.php` a přidejte na konec:

```php
// Na konci try bloku, před exit(0):
if ($uspesneOdeslano > 0) {
    mail(
        'admin@wgs-service.cz',
        'CRON Report: Odesláno ' . $uspesneOdeslano . ' připomínek',
        "Dnes v 10:00 bylo odesláno {$uspesneOdeslano} připomínek zákazníkům na zítřejší návštěvy.",
        "From: system@wgs-service.cz"
    );
}
```

---

## 🚨 Troubleshooting

### Problém: CRON se nespustí
**Řešení:**
1. Zkontrolujte cestu k PHP: `which php` (obvykle `/usr/bin/php`)
2. Zkontrolujte cestu ke skriptu (absolutní cesta!)
3. Zkontrolujte oprávnění: `chmod +x cron_send_reminders.php`

### Problém: Emaily se neodesílají
**Řešení:**
1. Zkontrolujte SMTP nastavení v `.env`
2. Zkontrolujte frontu: `SELECT * FROM wgs_email_queue WHERE status = 'pending'`
3. Zkontrolujte logy: `tail -f logs/email_errors.log`

### Problém: Zákazník nedostane email
**Možné příčiny:**
1. Email je ve SPAM složce
2. Email adresa zákazníka je neplatná
3. SMTP server odmítl email (zkontrolujte logy)

---

## 📊 Statistiky

Po nasazení můžete sledovat:
- **Počet odeslaných připomínek:** v `logs/cron_reminders.log`
- **Úspěšnost doručení:** v `wgs_email_queue.status`
- **Chybovost:** počet záznamů se `status = 'failed'`

---

## ✅ Checklist před nasazením do produkce

- [ ] Šablona `appointment_reminder_customer` je správně nastavená v databázi
- [ ] SMTP nastavení funguje (testováno odesláním testovacího emailu)
- [ ] Cesta k PHP a skriptu je správná pro váš hosting
- [ ] CRON je nastaven na **0 10 * * *** (každý den v 10:00)
- [ ] Logy existují a jsou zapisovatelné (`/logs/cron_reminders.log`)
- [ ] Testováno manuálním spuštěním: `php cron_send_reminders.php`
- [ ] První email doručen zákazníkovi a zkontrolován

---

**Autor:** WGS Service Team
**Poslední aktualizace:** 2025-11-19
**Kontakt:** radek@wgs-service.cz
