# 📅 Nastavení automatických připomínek - WEBCRON

## Co tento systém dělá?

Každý den v **10:00 ráno** automaticky odešle **připomínky zákazníkům**, kteří mají domluvenou návštěvu technika **ZÍTRA**.

---

## 🚀 Nastavení na hostingu (Český hosting)

### Krok 1: Přihlásit se do klientské sekce
- URL: https://www.cesky-hosting.cz (nebo váš přihlašovací panel)
- Přejít na: **Správa domény → wgs-service.cz → záložka CRON**

### Krok 2: Přidat nový WEBCRON

V sekci **"Webcron"** najděte tlačítko **"Přidat webcron"**.

#### Vyplňte následující údaje:

**URL adresa:**
```
https://www.wgs-service.cz/cron/send-reminders.php?key=wgs2025reminder
```

⚠️ **DŮLEŽITÉ:**
- Parametr `?key=wgs2025reminder` je **TAJNÝ KLÍČ** - bez něj skript nefunguje!
- URL zadávejte přesně stejně, jako byste ji zadávali do prohlížeče

**Čas spouštění:**

Český hosting nabízí **formulář pro nastavení času**. Vyberte:

**Možnost A - Přednastavený čas:**
- V rozbalovacím menu vyberte: **"Každý den v 10:00"** (pokud je k dispozici)

**Možnost B - Vlastní nastavení:**
```
Minuta: 0
Hodina: 10
Den v měsíci: *  (každý den)
Měsíc: *  (každý měsíc)
Den v týdnu: *  (každý den)
```

**Možnost C - Pokročilé (cron formát):**
```
0 10 * * *
```

**Výsledek:** Skript se spustí každý den přesně v **10:00:00**

---

### 📋 Omezení na sdíleném hostingu:
- **Maximální počet webcronů:** 5
- **Minimální perioda spouštění:** 15 minut
- **Logování chyb:** Automaticky do `data/webcron.log`

---

## 🔐 Bezpečnost

### Tajný klíč
Výchozí tajný klíč je: `wgs2025reminder`

**Pro zvýšení bezpečnosti můžete změnit klíč:**

1. Otevřete soubor `.env` na serveru
2. Přidejte řádek:
   ```
   CRON_SECRET_KEY=vase_vlastni_tajny_klic_2025
   ```
3. Změňte URL ve webcronu na:
   ```
   https://www.wgs-service.cz/cron/send-reminders.php?key=vase_vlastni_tajny_klic_2025
   ```

### Ochrana proti neoprávněnému přístupu
- Bez správného klíče vrátí skript **403 Forbidden**
- Pokus o přístup se zaloguje do `/logs/cron_reminders.log`

---

## 🧪 Testování PŘED spuštěním

### Test 1: Manuální spuštění (prohlížeč)
Otevřete v prohlížeči:
```
https://www.wgs-service.cz/cron/send-reminders.php?key=wgs2025reminder
```

**Očekávaný výstup:**
```json
{
  "status": "success",
  "message": "Připomínky odeslány",
  "found": 3,
  "sent": 3,
  "errors": 0
}
```

Nebo pokud nejsou žádné návštěvy na zítřek:
```json
{
  "status": "success",
  "message": "Žádné návštěvy na zítřek",
  "found": 0,
  "sent": 0
}
```

### Test 2: Kontrola logu
Zkontrolujte soubor:
```
/logs/cron_reminders.log
```

Měl by obsahovat:
```
[2025-11-19 10:00:15] === START: Kontrola návštěv pro připomenutí (webcron) ===
[2025-11-19 10:00:15] Hledám návštěvy na datum: 2025-11-20
[2025-11-19 10:00:15] Nalezeno návštěv: 2
[2025-11-19 10:00:15] Zpracovávám: REK-2025-001 - Jan Novák (jan@email.cz)
[2025-11-19 10:00:15] ✓ Email přidán do fronty pro: jan@email.cz
[2025-11-19 10:00:16] ---
[2025-11-19 10:00:16] SOUHRN:
[2025-11-19 10:00:16]   Nalezeno návštěv: 2
[2025-11-19 10:00:16]   Úspěšně přidáno do fronty: 2
[2025-11-19 10:00:16]   Chyby: 0
[2025-11-19 10:00:16] === KONEC ===
```

---

## 📊 Monitorování

### Jak zkontrolovat, že cron běží?

1. **Zkontrolovat log:**
   ```
   /logs/cron_reminders.log
   ```
   Měl by obsahovat záznamy každý den v 10:00

2. **Zkontrolovat emailovou frontu:**
   ```sql
   SELECT * FROM wgs_email_queue
   WHERE email_type = 'appointment_reminder'
   ORDER BY created_at DESC
   LIMIT 10;
   ```

3. **Kontrola logů českého hostingu:**
   - **Logy webcron chyb:** Český hosting automaticky loguje chybná volání do:
     ```
     /data/webcron.log
     ```
   - Přístup přes SFTP/FTP klienta
   - Pokud je soubor prázdný = vše funguje správně!

4. **Sledovat dashboard hostingu:**
   - V klientské sekci → Správa domény → CRON → Webcron
   - Zobrazí se seznam všech nastavených webcronů

---

## 🛠️ Troubleshooting

### Problém: 403 Forbidden
**Příčina:** Špatný tajný klíč v URL
**Řešení:**
1. Zkontrolujte, že URL obsahuje správný parametr `?key=wgs2025reminder`
2. Zkontrolujte log `/data/webcron.log` na českém hostingu
3. Ověřte, že soubor `/cron/send-reminders.php` existuje na serveru

### Problém: Webcron se nespustil
**Kontrola na českém hostingu:**
1. Zkontrolujte `/data/webcron.log` - obsahuje chybová hlášení
2. Ověřte v klientské sekci, že webcron je aktivní
3. Zkontrolujte, že URL je správně zadaná (včetně `https://`)

### Problém: Emaily se neodesílají
**Možné příčiny:**
1. SMTP není nakonfigurovaný → Zkontrolujte `.env` (SMTP_HOST, SMTP_USER, SMTP_PASS)
2. Šablona neexistuje → Spusťte `instaluj_email_sablony.php`
3. Fronta není zpracovávaná → Zkontrolujte `process-email-queue.php` cron

### Problém: Žádné návštěvy nalezeny (ale měly by být)
**Kontrola:**
```sql
SELECT * FROM wgs_reklamace
WHERE stav = 'open'
  AND termin = DATE_ADD(CURDATE(), INTERVAL 1 DAY);
```

**Možné příčiny:**
- Stav není `'open'` (mělo by být `'DOMLUVENÁ'` v UI, ale v DB je `'open'`)
- Datum termínu není přesně zítřek
- Email zákazníka chybí nebo je prázdný

---

## 📋 Checklist před spuštěním

- [ ] ✅ Šablony jsou nainstalovány (`instaluj_email_sablony.php`)
- [ ] ✅ SMTP je nakonfigurovaný (`.env`)
- [ ] ✅ Email fronta funguje (`process-email-queue.php` cron běží)
- [ ] ✅ Test manuálního spuštění proběhl úspěšně
- [ ] ✅ Log obsahuje správné záznamy
- [ ] ✅ Webcron je přidaný v hostingovém panelu
- [ ] ✅ Čas je nastaven na 10:00
- [ ] ✅ URL obsahuje tajný klíč

---

## 📞 Kontakt

Pokud máte problémy s nastavením, kontaktujte:
- **Email:** radek@wgs-service.cz
- **Telefon:** +420 725 965 826

---

**Autor:** WGS Service Team
**Poslední aktualizace:** 2025-11-19
**Verze:** 2.0 (webcron)
