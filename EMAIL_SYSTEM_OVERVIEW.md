# 📧 KOMPLETNÍ PŘEHLED EMAIL SYSTÉMU - WGS Service

**Datum:** 2025-11-19
**Autor:** Claude AI Assistant

---

## 🎯 JEDNODUCHÝ ZPŮSOB ODESLÁNÍ EMAILU

**V systému WGS existuje JEDEN centralizovaný způsob odesílání emailů:**

```
Frontend (protokol.php, seznam.js)
    ↓
app/notification_sender.php
    ↓
wgs_email_queue (databáze)
    ↓
scripts/process_email_queue.php (cron)
    ↓
includes/EmailQueue.php
    ↓
PHPMailer → SMTP server (websmtp.cesky-hosting.cz:25)
```

**To je vše!** Neexistují žádné alternativní cesty.

---

## 📁 KLÍČOVÉ SOUBORY (3 soubory)

### 1. `app/notification_sender.php` (8.2 KB)
**Účel:** Přijímá požadavek na odeslání emailu z frontendu.

**Co dělá:**
1. Ověří CSRF token
2. Ověří přihlášení
3. Načte šablonu notifikace z `wgs_notifications`
4. Nahradí proměnné ({{customer_name}}, {{date}}, ...)
5. **Přidá email do fronty** (`wgs_email_queue`)
6. Vrátí okamžitou odpověď (~100ms)

**Neposílá emaily přímo!** Pouze přidává do fronty.

---

### 2. `scripts/process_email_queue.php` (1.3 KB)
**Účel:** Cron worker - zpracovává frontu emailů.

**Jak funguje:**
- Spouští se každou minutu (nebo na vyžádání)
- Použije lock file, aby se nespustily 2 instance současně
- Zavolá `EmailQueue->processQueue(50)` - zpracuje max 50 emailů
- Loguje výsledky

**Cron nastavení:**
```bash
* * * * * php /path/to/scripts/process_email_queue.php
```

---

### 3. `includes/EmailQueue.php` (17 KB)
**Účel:** Knihovna pro správu email fronty.

**Hlavní metody:**

#### `enqueue($data)` - Přidá email do fronty
```php
$emailQueue = new EmailQueue();
$emailQueue->enqueue([
    'to' => 'customer@example.com',
    'subject' => 'Potvrzení termínu',
    'body' => 'Email text...',
    'cc' => [],
    'bcc' => []
]);
```

#### `processQueue($limit)` - Zpracuje pending emaily
```php
$results = $emailQueue->processQueue(50);
// Returns: ['processed' => 10, 'sent' => 9, 'failed' => 1]
```

#### `sendEmail($queueItem)` - Odešle JEDEN email
```php
// Automaticky vybere metodu:
if (PHPMailer exists) {
    sendWithPHPMailer();  // ← TOTO CHCEME
} else {
    sendWithPHPMail();    // ← FALLBACK (nefunguje dobře)
}
```

#### `getSMTPSettings()` - Načte SMTP konfiguraci
```php
// 1. Primárně: wgs_smtp_settings (databáze)
// 2. Fallback: .env proměnné
```

---

## 🗂️ KOMPLETNÍ SEZNAM SOUBORŮ (30 souborů)

### 📋 Produkční soubory (AKTIVNÍ)

| Soubor | Velikost | Účel |
|--------|----------|------|
| `app/notification_sender.php` | 8.2 KB | Frontend API - přidává emaily do fronty |
| `includes/EmailQueue.php` | 17 KB | Core knihovna email systému |
| `scripts/process_email_queue.php` | 1.3 KB | Cron worker |
| `api/notification_api.php` | ? KB | API pro správu notifikačních šablon |
| `api/notification_list_direct.php` | ? KB | Seznam notifikací (admin) |
| `api/email_resend_api.php` | ? KB | Re-send selhavších emailů |
| `admin/email_queue.php` | ? KB | Admin UI - zobrazení fronty |
| `includes/email_domain_validator.php` | ? KB | Validace email domén |

---

### 🛠️ Migrační a setup skripty

| Soubor | Účel |
|--------|------|
| `migrations/create_email_queue.sql` | Vytvoření tabulek wgs_email_queue + wgs_smtp_settings |
| `setup/migration_create_notifications_table.sql` | Vytvoření tabulky wgs_notifications |
| `migrations/add_phpmailer_installation_task.sql` | Migrace pro PHPMailer instalaci |
| `scripts/install_email_queue.php` | Instalátor email queue systému |
| `scripts/install_phpmailer.php` | Instalátor PHPMailer |
| `scripts/download_phpmailer.sh` | Bash skript pro stažení PHPMailer |
| `admin/install_email_system.php` | Web UI instalátor |

---

### 🔧 Nástroje a utility

| Soubor | Účel |
|--------|------|
| `vycisti_emailovou_frontu.php` | Vyčištění selhavších emailů z fronty |
| `cleanup_failed_emails.php` | Duplicate? Stejná funkce |
| `scripts/cleanup_failed_emails.php` | Worker verze cleanupu |
| `oprav_email_worker.php` | Oprava email worker procesu |
| `pridej_sloupce_email_queue.php` | Migrace - přidání sloupců do wgs_email_queue |
| `pridej_sloupce_pro_email_worker.php` | Migrace - přidání worker sloupců |
| `migrace_email_worker.sql` | SQL migrace pro worker |
| `email_management.php` | Admin UI - správa emailů |

---

### 🎨 Frontend (Admin panel)

| Soubor | Typ | Účel |
|--------|-----|------|
| `assets/js/admin-notifications.js` | JavaScript | UI pro správu notifikací |
| `assets/css/admin-notifications.css` | CSS | Styly pro notifikace |

---

### 🗄️ Zálohy a legacy soubory

| Soubor | Status |
|--------|--------|
| `backups/control_center/includes_backup/control_center_email_sms.php` | Legacy backup |
| `backups/removed_test_files/test-phpmailer.php` | Test soubor (odstraněn) |
| `includes/admin_email_sms.php` | Legacy? Kontrolovat |

---

### 🆕 Nově vytvořené (dnes)

| Soubor | Účel |
|--------|------|
| `oprav_smtp_ihned.php` | Okamžitá oprava SMTP konfigurace |
| `sjednotit_email_konfiguraci.php` | Sjednocení duplicitní konfigurace |
| `AUDIT_SMTP_KONFIGURACE.md` | Kompletní audit SMTP |
| `EMAIL_SYSTEM_OVERVIEW.md` | Tento dokument |

---

## 📊 DATABÁZOVÉ TABULKY (5 tabulek)

### 1. `wgs_email_queue` (17 záznamů)
**Účel:** Fronta emailů k odeslání

**Sloupce:**
- `id` - Primární klíč
- `notification_id` - ID šablony
- `recipient_email` - Příjemce
- `subject` - Předmět
- `body` - Tělo emailu
- `cc_emails` - JSON array CC adres
- `bcc_emails` - JSON array BCC adres
- `priority` - low/normal/high
- `status` - pending/sending/sent/failed
- `attempts` - Počet pokusů (max 3)
- `error_message` - Chybová zpráva
- `scheduled_at` - Kdy odeslat
- `sent_at` - Kdy odesláno

**Aktuální stav:**
- 17 emailů ve frontě
- Všechny ve stavu `pending` s 3/3 pokusy
- Chyba: "SMTP Error: Could not connect to SMTP host"

---

### 2. `wgs_smtp_settings` (1 záznam)
**Účel:** SMTP konfigurace (primární zdroj)

**Sloupce:**
- `smtp_host` - SMTP server
- `smtp_port` - Port
- `smtp_encryption` - none/ssl/tls
- `smtp_username` - Username
- `smtp_password` - Heslo
- `smtp_from_email` - From adresa
- `smtp_from_name` - From jméno
- `is_active` - Aktivní?

**Aktuální hodnoty:**
```sql
smtp_host:       smtp.ceskyhosting.cz ❌ ŠPATNĚ!
smtp_port:       587 ❌ ŠPATNĚ!
smtp_encryption: tls ❌ ŠPATNĚ!
smtp_username:   reklamace@wgs-service.cz ❌ ŠPATNĚ!
```

**Správné hodnoty by měly být:**
```sql
smtp_host:       websmtp.cesky-hosting.cz ✅
smtp_port:       25 ✅
smtp_encryption: none ✅
smtp_username:   wgs-service.cz ✅
```

---

### 3. `wgs_notifications` (6 záznamů)
**Účel:** Šablony notifikací

**Sloupce:**
- `id` - ID šablony (např. "appointment_confirmed")
- `name` - Lidský název
- `trigger_event` - Kdy se spustí
- `recipient_type` - customer/admin/technician/seller
- `subject` - Předmět emailu (s proměnnými)
- `template` - Tělo emailu (s proměnnými)
- `variables` - JSON array povolených proměnných
- `cc_emails` - CC adresy
- `bcc_emails` - BCC adresy
- `active` - Aktivní?

**Příklad šablony:**
```json
{
  "id": "appointment_confirmed",
  "subject": "Potvrzení termínu návštěvy - WGS",
  "template": "Dobrý den {{customer_name}},\npotvrzujeme termín návštěvy:\nDatum: {{date}}\nČas: {{time}}",
  "variables": ["{{customer_name}}", "{{date}}", "{{time}}"]
}
```

---

### 4. `notification_templates` (5 záznamů)
**Účel:** ❓ Duplicitní? Podobné jako wgs_notifications

**⚠️ MOŽNÁ KOLIZE!** Zjistit, jestli se používá nebo je to legacy.

---

### 5. `wgs_system_config` (3 SMTP záznamy)
**Účel:** ❌ DUPLICITNÍ! Obsahuje SMTP konfiguraci

**Záznamy:**
```sql
config_key: smtp_host       value: smtp.ceskyhosting.cz
config_key: smtp_port       value: 587
config_key: smtp_username   value: reklamace@wgs-service.cz
```

**⚠️ PROBLÉM:** Toto jsou duplicity z `wgs_smtp_settings`!

---

## 🚨 NALEZENÉ PROBLÉMY

### 1. ❌ Duplicitní SMTP konfigurace
**DVĚ tabulky obsahují SMTP nastavení:**
- `wgs_smtp_settings` (primární)
- `wgs_system_config` (duplicita!)

**Řešení:** Odstranit SMTP záznamy z `wgs_system_config`.

---

### 2. ❌ Duplicitní notifikační tabulky?
**DVĚ tabulky pro notifikace:**
- `wgs_notifications` (6 záznamů)
- `notification_templates` (5 záznamů)

**Akce:** Zjistit, která se používá a druhou odstranit.

---

### 3. ❌ Špatná SMTP konfigurace
**Používáte:** `smtp.cesky-hosting.cz:587` (pro poštovní klienty)
**Měli byste:** `websmtp.cesky-hosting.cz:25` (pro PHP skripty)

---

### 4. ❌ 17 emailů selhalo ve frontě
**Všechny mají chybu:** "SMTP Error: Could not connect to SMTP host"

**Řešení:** Opravit SMTP konfiguraci a resetovat `attempts`.

---

### 5. ❓ PHPMailer status nejasný
**Chyby naznačují, že PHPMailer JE nainstalován**, ale vendor složka neexistuje.

**Akce:** Ověřit instalaci PHPMailer.

---

## ✅ SPRÁVNÝ POSTUP PRO ODESLÁNÍ EMAILU

### Frontend (JavaScript)
```javascript
// Odeslat notifikaci
async function odeslat Notifikaci(notificationId, data) {
    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('notification_id', notificationId);
    formData.append('data', JSON.stringify(data));

    const response = await fetch('/app/notification_sender.php', {
        method: 'POST',
        body: formData
    });

    return await response.json();
}
```

### Backend - notification_sender.php
```php
// 1. Ověří CSRF + přihlášení
// 2. Načte šablonu z wgs_notifications
// 3. Nahradí proměnné
// 4. Přidá do fronty:

$emailQueue = new EmailQueue();
$emailQueue->enqueue([
    'to' => 'customer@example.com',
    'subject' => 'Potvrzení termínu',
    'body' => $message,
    'priority' => 'normal'
]);

// Okamžitě vrátí odpověď (není nutné čekat na odeslání!)
```

### Cron worker - process_email_queue.php
```php
// Zpracuje frontu (spouští se každou minutu)
$queue = new EmailQueue();
$results = $queue->processQueue(50);

// Odešle max 50 emailů
// Retry mechanika pro selhavší
```

---

## 🎯 AKČNÍ PLÁN - CO UDĚLAT TEĎ

### Krok 1: Sjednotit konfiguraci ⚡
**Spustit:**
```
https://www.wgs-service.cz/sjednotit_email_konfiguraci.php
```

**Co to udělá:**
1. ✅ Odstraní duplicity z `wgs_system_config`
2. ✅ Nastaví správnou konfiguraci (websmtp.cesky-hosting.cz:25)
3. ✅ Resetuje email frontu (attempts=0)

---

### Krok 2: Ověřit PHPMailer
**Test:**
```php
<?php
require_once __DIR__ . '/vendor/autoload.php';
var_dump(class_exists('PHPMailer\\PHPMailer\\PHPMailer'));
// Mělo by vrátit: bool(true)
?>
```

**Pokud vrátí `false`:**
- Nainstalovat PHPMailer: `composer require phpmailer/phpmailer`
- Nebo manuálně viz `INSTALACE_PHPMAILER.md`

---

### Krok 3: Spustit cron worker
**Manuálně:**
```bash
php /path/to/scripts/process_email_queue.php
```

**Nastavit cron:**
```bash
* * * * * php /www/wgs-service.cz/scripts/process_email_queue.php >> /www/wgs-service.cz/logs/email_queue.log 2>&1
```

---

### Krok 4: Otestovat odeslání
1. Otevřít protokol: `/protokol.php?id=WGS-2025-18-11-00001`
2. Kliknout "ODESLAT ZÁKAZNÍKOVI"
3. Zkontrolovat frontu: `/admin/email_queue.php`
4. Zkontrolovat logy: `/logs/php_errors.log`

---

## 📚 SOUVISEJÍCÍ DOKUMENTY

- `AUDIT_SMTP_KONFIGURACE.md` - Kompletní audit SMTP konfigurace
- `EMAIL_QUEUE_README.md` - Dokumentace email queue systému
- `INSTALACE_PHPMAILER.md` - Návod na instalaci PHPMailer
- `DATA_FLOW_INTEGRATION_ANALYSIS.md` - Analýza toku dat

---

## 🔒 BEZPEČNOST

### ✅ Co JE implementováno:

1. **CSRF ochrana** - Všechny POST requesty validují CSRF token
2. **Rate limiting** - Max 30 notifikací/hodinu z jedné IP
3. **Session ověření** - Pouze přihlášení uživatelé
4. **Email validace** - `filter_var($email, FILTER_VALIDATE_EMAIL)`
5. **SQL injection ochrana** - PDO prepared statements
6. **XSS ochrana** - `htmlspecialchars()` na výstup

### ⚠️ Co by se MĚLO zlepšit:

1. **DKIM podepisování** - Už je v DNS, ale zkontrolovat, že PHPMailer ho používá
2. **SPF kontrola** - Ověřit, že odesílání z websmtp.cesky-hosting.cz projde SPF
3. **Hesla v databázi** - SMTP heslo není šifrované (ukládá se plain text)

---

## 📞 SUPPORT

**Pokud emaily stále nefungují:**

1. Zkontrolovat logy:
   ```bash
   tail -f /www/wgs-service.cz/logs/php_errors.log
   ```

2. Zkontrolovat frontu:
   ```sql
   SELECT * FROM wgs_email_queue WHERE status = 'failed' ORDER BY created_at DESC LIMIT 10;
   ```

3. Test SMTP připojení:
   ```bash
   telnet websmtp.cesky-hosting.cz 25
   ```

---

**© 2025 WGS Service - White Glove Service**
