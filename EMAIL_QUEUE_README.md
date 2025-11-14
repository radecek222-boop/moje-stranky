# 📧 Email Queue System - Instalační příručka

## ✨ Co je nového

Byl implementován **kompletní email queue systém** pro asynchronní odesílání emailů pomocí PHPMailer.

### Výhody:
- ⚡ **Rychlost**: Ukládání termínu z 15s na **~3s**
- 📧 **PHPMailer**: Spolehlivé odesílání přes SMTP
- 🔄 **Automatické opakování**: Při selhání se email zkusí znovu
- 📊 **Přehled**: Admin rozhraní pro sledování všech emailů
- ⚙️ **Flexibilní SMTP**: Snadná konfigurace přes web

---

## 🚀 Rychlá instalace (3 kroky)

### Krok 1: Instalace tabulek

**Otevřete v prohlížeči:**
```
https://www.wgs-service.cz/admin/install_email_system.php
```

**Klikněte na:**
```
🚀 Nainstalovat Email Queue
```

### Krok 2: Nastavení SMTP

**Otevřete:**
```
https://www.wgs-service.cz/admin/smtp_settings.php
```

**Vyplňte:**
- SMTP Server: `smtp.gmail.com` (nebo jiný)
- Port: `587`
- Šifrování: `TLS`
- Username: `vás-email@gmail.com`
- Password: `vaše-heslo` (pro Gmail použijte App Password)
- Odesílatel Email: `noreply@wgs-service.cz`
- Odesílatel Jméno: `White Glove Service`

**Klikněte:**
```
💾 Uložit nastavení
```

### Krok 3: Nastavení Cron Jobu

Přidejte do crontab (nebo cPanel Cron Jobs):

```bash
* * * * * php /cesta/k/projektu/scripts/process_email_queue.php >> /cesta/k/projektu/logs/email_queue.log 2>&1
```

**Nebo jednoduše v cPanel:**
- Minute: `*` (každou minutu)
- Hour: `*`
- Day: `*`
- Month: `*`
- Weekday: `*`
- Command: `php /home/username/public_html/scripts/process_email_queue.php`

---

## 📋 Správa Email Fronty

**Admin rozhraní:**
```
https://www.wgs-service.cz/admin/email_queue.php
```

### Funkce:
- ✅ Zobrazení všech emailů (pending, sent, failed)
- 🔄 Ruční opakování selhavších emailů
- 🗑️ Mazání emailů z fronty
- 🚀 Manuální zpracování fronty
- 📊 Statistiky (čekající, odesláno, selhalo)

---

## 🗂️ Struktura souborů

```
vendor/
  phpmailer/              # PHPMailer knihovna
  autoload.php            # Autoloader

includes/
  EmailQueue.php          # Email queue manager

scripts/
  process_email_queue.php # Cron worker
  install_email_queue.php # CLI instalátor

admin/
  install_email_system.php # Web instalátor
  smtp_settings.php        # SMTP konfigurace
  email_queue.php          # Správa fronty

migrations/
  create_email_queue.sql  # SQL migrace

app/
  notification_sender.php # Upraveno pro queue
```

---

## 🔧 Databázové tabulky

### `wgs_email_queue`
Fronta emailů k odeslání.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| id | INT | Primary key |
| notification_id | VARCHAR | Typ notifikace |
| recipient_email | VARCHAR | Příjemce |
| subject | VARCHAR | Předmět |
| body | TEXT | Tělo emailu |
| status | ENUM | pending/sending/sent/failed |
| attempts | INT | Počet pokusů |
| created_at | TIMESTAMP | Vytvořeno |
| sent_at | TIMESTAMP | Odesláno |

### `wgs_smtp_settings`
Konfigurace SMTP serveru.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| smtp_host | VARCHAR | SMTP server |
| smtp_port | INT | Port (587/465) |
| smtp_encryption | ENUM | tls/ssl/none |
| smtp_username | VARCHAR | Uživatel |
| smtp_password | VARCHAR | Heslo |
| smtp_from_email | VARCHAR | Odesílatel |
| is_active | TINYINT | Aktivní? |

---

## 🧪 Testování

### Test SMTP spojení:
1. Otevřete `/admin/smtp_settings.php`
2. Zadejte testovací email
3. Klikněte "📧 Odeslat testovací email"
4. Email se přidá do fronty
5. Zkontrolujte doručenou poštu

### Ruční zpracování fronty:
```bash
php /cesta/k/projektu/scripts/process_email_queue.php
```

---

## ❓ FAQ

**Q: Proč se emaily neodesílají?**
A: Zkontrolujte:
1. SMTP nastavení v `/admin/smtp_settings.php`
2. Cron job běží každou minutu
3. Logy: `/logs/email_queue.log`

**Q: Jak změnit SMTP server?**
A: Jděte do `/admin/smtp_settings.php` a upravte nastavení

**Q: Jak ručně zpracovat frontu?**
A: V `/admin/email_queue.php` klikněte "🚀 Zpracovat frontu nyní"

**Q: Kde vidím selhavší emaily?**
A: V `/admin/email_queue.php` → filtr "Selhalo"

---

## 🎯 Výsledek

**Před:**
- ❌ Ukládání termínu: 15 sekund
- ❌ PHP mail() timeout
- ❌ Žádný přehled emailů

**Po:**
- ✅ Ukládání termínu: 3 sekundy
- ✅ PHPMailer + SMTP
- ✅ Admin rozhraní pro správu
- ✅ Automatické opakování
- ✅ Statistiky a monitoring

---

## 📞 Podpora

Pro problémy nebo dotazy kontaktujte vývojáře.

**Vytvořeno:** 2025-11-14
**Verze:** 1.0.0
**Status:** ✅ Production Ready
