# 📅 Nastavení Cronu pro Český hosting

## 🎯 Postup nastavení Webcronu

### Krok 1: Přihlásit se do administrace

```
https://admin.cesky-hosting.cz
→ Přihlásit se
→ Domény a hosting
→ wgs-service.cz
→ Cron
→ Webcron
```

### Krok 2: Přidat nový Webcron

Klikni na **"Přidat webcron"**

**Vyplň:**

```
URL: https://www.wgs-service.cz/cron/process-email-queue.php

Čas spouštění:
┌─ Minuta: */15     (každých 15 minut)
├─ Hodina: *        (každou hodinu)
├─ Den: *           (každý den)
├─ Měsíc: *         (každý měsíc)
└─ Den v týdnu: *   (každý den v týdnu)
```

**Nebo zkopíruj celý cron výraz:**
```
*/15 * * * *
```

### Krok 3: Uložit

Klikni **"Přidat"** nebo **"Uložit"**

---

## ✅ Ověření že funguje

### 1. Zkontroluj log soubor (po 15 minutách):

V administraci hostingu:
```
File Manager → logs → email_queue_cron.log
```

Měl by obsahovat:
```
[2025-11-14 08:00:01] ======================================
[2025-11-14 08:00:01] Email Queue Processor - START
[2025-11-14 08:00:01] Čekající emaily: 5
[2025-11-14 08:00:02] ✓ Email #1 úspěšně odeslán
...
```

### 2. Nebo zkontroluj admin rozhraní:

```
https://www.wgs-service.cz/admin/email_queue.php
```

Emaily by měly měnit status z "pending" → "sent"

---

## 🔧 Manuální test (před nastavením cronu)

Můžeš script otestovat ručně v prohlížeči:

```
https://www.wgs-service.cz/cron/process-email-queue.php
```

Měl by zobrazit:
```
[2025-11-14 08:00:01] ======================================
[2025-11-14 08:00:01] Email Queue Processor - START
[2025-11-14 08:00:01] Čekající emaily: 0
[2025-11-14 08:00:01] Žádné emaily ke zpracování
[2025-11-14 08:00:01] ======================================
```

---

## ⚙️ Nastavení

### Změna periody (pokud hosting dovolí kratší interval):

V administraci Český hosting změň:
```
*/15 * * * *  →  */5 * * * *   (každých 5 minut)
*/15 * * * *  →  */1 * * * *   (každou minutu - pokud povoleno)
```

### Limit emailů najednou:

V souboru `cron/process-email-queue.php` na řádku ~71:
```php
$limit = 50;  // Změň na vyšší/nižší číslo
```

---

## 📊 Výhody oproti původnímu řešení

| Předtím | Nyní |
|---------|------|
| ❌ 15 sekund čekání | ✅ 3 sekundy + email každých 15 min |
| ❌ Timeout při odesílání | ✅ Asynchronní fronta |
| ❌ Žádný retry | ✅ Automatické opakování |
| ❌ Žádný monitoring | ✅ Log + Admin rozhraní |

I s 15minutovým intervalem je to **mnohem lepší** než původní řešení!

---

## 🆘 Řešení problémů

### Webcron se nespustil:

1. Zkontroluj URL (musí být přesně): `https://www.wgs-service.cz/cron/process-email-queue.php`
2. Zkontroluj že soubor existuje přes FTP/File Manager
3. Otestuj URL ručně v prohlížeči

### Log soubor se nevytvořil:

1. Vytvořit adresář `logs/` v rootu webu
2. Nastavit práva 755: `chmod 755 logs/`

### Emaily se neodesílají:

1. Zkontroluj SMTP nastavení: `/admin/smtp_settings.php`
2. Otestuj SMTP spojení
3. Zkontroluj log: `logs/email_queue_cron.log`

---

## 💡 Tip: Adresářový cron (alternativa)

Pokud nechceš používat Webcron, můžeš použít **Adresářový cron**:

1. Nahraj `process-email-queue.php` do adresáře `CRON.2hodiny/`
2. Script se bude spouštět každé 2 hodiny automaticky
3. Žádná další konfigurace není potřeba

**Nevýhoda:** Pouze každé 2 hodiny (místo 15 minut)

---

Máš-li jakékoliv dotazy, napiš mi!
