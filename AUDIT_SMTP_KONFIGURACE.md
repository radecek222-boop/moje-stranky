# 🔍 AUDIT SMTP KONFIGURACE - WGS Service

**Datum:** 2025-11-19
**Problém:** Emaily se neodesílají

---

## 📊 SHRNUTÍ PROBLÉMU

### ❌ HLAVNÍ PŘÍČINA: PHPMailer není nainstalován!

**Důsledek:**
Systém používá fallback metodu `PHP mail()`, která **IGNORUJE všechna SMTP nastavení** z databáze a používá lokální sendmail. To na Českém hostingu nefunguje.

---

## 🗂️ KONFIGURACE EMAILŮ - 2 ÚROVNĚ

### 1️⃣ Databáze `wgs_smtp_settings` (PRIMÁRNÍ)

**Struktura tabulky:**
```sql
CREATE TABLE wgs_smtp_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port INT DEFAULT 587,
    smtp_encryption ENUM('none', 'ssl', 'tls') DEFAULT 'tls',  ← DŮLEŽITÉ: ENUM!
    smtp_username VARCHAR(255) NOT NULL,
    smtp_password VARCHAR(500) NOT NULL,
    smtp_from_email VARCHAR(255) NOT NULL,
    smtp_from_name VARCHAR(255) DEFAULT 'WGS Service',
    is_active TINYINT(1) DEFAULT 1,
    ...
)
```

**Aktuální hodnoty v DB:**
```
Host:       smtp.ceskyhosting.cz
Port:       587
Encryption: tls
Username:   reklamace@wgs-service.cz
```

**⚠️ PROBLÉM:** Tato konfigurace nefunguje (timeout na portu 587).

---

### 2️⃣ `.env` soubor (FALLBACK)

**Proměnné:**
```bash
SMTP_HOST=your_smtp_host
SMTP_PORT=587
SMTP_USER=your_smtp_user
SMTP_PASS=your_smtp_password
SMTP_FROM=your_email@example.com
```

**Kdy se použije:**
Pouze když v databázi `wgs_smtp_settings` není žádný záznam s `is_active = 1`.

---

## 🔄 JAK TO FUNGUJE (EmailQueue.php)

```php
class EmailQueue {
    // 1. ZÍSKÁ KONFIGURACI
    private function getSMTPSettings() {
        // Primárně z databáze
        $stmt = $this->pdo->query("
            SELECT * FROM wgs_smtp_settings
            WHERE is_active = 1
            ORDER BY id DESC
            LIMIT 1
        ");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fallback na .env pokud není v DB
        if (!$settings) {
            return [
                'smtp_host' => getenv('SMTP_HOST') ?: 'smtp.example.com',
                'smtp_port' => getenv('SMTP_PORT') ?: 587,
                // ...
            ];
        }

        return $settings;
    }

    // 2. ODEŠLE EMAIL
    public function sendEmail($queueItem) {
        $settings = $this->getSMTPSettings();

        // ⚠️ TADY JE PROBLÉM!
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return $this->sendWithPHPMailer($queueItem, $settings);  // ← NIKDY se nevolá!
        }

        // ❌ FALLBACK: PHP mail() - IGNORUJE $settings!
        return $this->sendWithPHPMail($queueItem, $settings);  // ← TOHLE SE VOLÁ!
    }

    // 3. PHP mail() FALLBACK (AKTUÁLNĚ POUŽÍVANÁ METODA)
    private function sendWithPHPMail($queueItem, $settings) {
        $to = $queueItem['recipient_email'];
        $subject = $queueItem['subject'];
        $message = $queueItem['body'];

        $headers = "From: {$settings['smtp_from_name']} <{$settings['smtp_from_email']}>\r\n";
        // ...

        // ❌ PROBLÉM: mail() používá LOKÁLNÍ SENDMAIL, ne SMTP!
        $success = @mail($to, $subject, $message, $headers);
        // Lokální sendmail na Českém hostingu často nefunguje nebo končí ve spamu
    }
}
```

---

## 🚨 KRITICKÉ NÁLEZY

### 1. ❌ PHPMailer není nainstalován

**Důkaz:**
```bash
$ ls -la /home/user/moje-stranky/vendor/
Vendor složka neexistuje
```

**Důsledek:**
- `class_exists('PHPMailer\\PHPMailer\\PHPMailer')` vrací `false`
- Systém používá fallback `PHP mail()` funkci
- **PHP mail() NEMŮŽE používat SMTP** - používá lokální sendmail

---

### 2. ❌ Nesprávná konfigurace v databázi

**Aktuální:**
```
smtp.ceskyhosting.cz:587 (TLS)
```

**Diagnostika ukázala:**
```
Port 25:  ✅ FUNGUJE (websmtp.cesky-hosting.cz)
Port 587: ✅ FUNGUJE (websmtp.cesky-hosting.cz)
Port 465: ❌ NEFUNGUJE
```

**Správná konfigurace by měla být:**
```
websmtp.cesky-hosting.cz:25 (none)
```

---

### 3. ❌ ENUM hodnota pro encryption

**Problém:**
Sloupec `smtp_encryption` je `ENUM('none', 'ssl', 'tls')`.

**Chyba ve skriptu:**
```php
// ❌ ŠPATNĚ - prázdný řetězec není v ENUM
':encryption' => '',

// ✅ SPRÁVNĚ
':encryption' => 'none',
```

**Stav:**
✅ OPRAVENO v `oprav_smtp_na_websmtp.php` (commit 7375d54)

---

## 🎯 ŘEŠENÍ - 3 KROKY

### Krok 1: Nainstalovat PHPMailer (PRIORITA #1) ⚡

**Proč je to kritické:**
Bez PHPMaileru systém **NEMŮŽE** používat SMTP! PHP `mail()` používá sendmail, což na hostingu nefunguje.

**Jak nainstalovat:**

#### Varianta A: Composer (doporučeno)
```bash
composer require phpmailer/phpmailer
```

#### Varianta B: Manuální instalace (bez SSH)
Viz soubor `INSTALACE_PHPMAILER.md` v root složce.

---

### Krok 2: Opravit databázovou konfiguraci

**Spustit skript:**
```
https://www.wgs-service.cz/oprav_smtp_ihned.php
```

**Nebo použít existující:**
```
https://www.wgs-service.cz/oprav_smtp_na_websmtp.php
```

**Nová konfigurace:**
```
Host:       websmtp.cesky-hosting.cz
Port:       25
Encryption: none
Username:   wgs-service.cz
```

---

### Krok 3: Otestovat odeslání emailu

**Test endpointy:**
1. Protokol: `/protokol.php?id=CCC-test00001` → "ODESLAT ZÁKAZNÍKOVI"
2. Email queue: `/scripts/process_email_queue.php` (cron worker)
3. Admin panel: Odeslat testovací notifikaci

---

## 📁 SOUBORY S SMTP KONFIGURACÍ (30 souborů)

### Kritické soubory:

| Soubor | Účel | Poznámka |
|--------|------|----------|
| `includes/EmailQueue.php` | Email queue manager | ✅ Funguje správně, ale chybí PHPMailer |
| `oprav_smtp_na_websmtp.php` | Oprava SMTP na WebSMTP | ✅ OPRAVENO (ENUM hodnota) |
| `oprav_smtp_ihned.php` | Okamžitá oprava | ✅ NOVĚ VYTVOŘENO |
| `test_websmtp.php` | Test WebSMTP portů | ✅ Funguje |
| `diagnoza_smtp.php` | SMTP diagnostika | ✅ Funguje |

### Nastavovací skripty:

| Skript | Co dělá |
|--------|---------|
| `nastav_smtp_cesky_hosting.php` | Nastaví Český hosting SMTP |
| `nastav_websmtp.php` | Nastaví WebSMTP |
| `vycisti_smtp.php` | Vyčistí SMTP nastavení |
| `oprav_smtp_konfiguraci.php` | Obecná oprava SMTP |

---

## 🔄 DATA FLOW - Jak se posílají emaily

```
1. Frontend (např. protokol.php)
   ↓
2. app/notification_sender.php
   ↓ přidá do fronty
3. wgs_email_queue (DB tabulka)
   ↓ zpracovává
4. includes/EmailQueue.php
   ↓
5a. sendWithPHPMailer() ← TOTO CHCEME (SMTP)
    ↓ používá wgs_smtp_settings z DB
    SMTP server (websmtp.cesky-hosting.cz:25)

5b. sendWithPHPMail() ← TOTO SE TEĎ VOLÁ (BAD!)
    ↓ používá lokální sendmail
    ❌ Selže nebo skončí ve spamu
```

---

## ⚠️ KOLIZE A KONFLIKTY

### 1. Databáze vs .env

**Priorita:**
1. Primárně se používá `wgs_smtp_settings` (databáze)
2. `.env` je pouze fallback (když DB je prázdná)

**Důsledek:**
Pokud máte nesprávnou konfiguraci v DB, `.env` se **NEPOUŽIJE**.

---

### 2. PHPMailer vs PHP mail()

**Priorita:**
1. Pokud existuje PHPMailer → použije se SMTP (✅ CHCEME)
2. Pokud neexistuje PHPMailer → použije se `mail()` (❌ AKTUÁLNÍ STAV)

**Důsledek:**
Bez PHPMaileru jsou všechna SMTP nastavení v DB **IGNOROVÁNA**.

---

## 🎯 DOPORUČENÍ

### Priorita 1: Nainstalujte PHPMailer

**Bez tohoto kroku NIC nefunguje!**

```bash
cd /home/user/moje-stranky
composer require phpmailer/phpmailer
```

Nebo manuálně viz `INSTALACE_PHPMAILER.md`.

---

### Priorita 2: Opravte databázovou konfiguraci

Spusťte:
```
https://www.wgs-service.cz/oprav_smtp_ihned.php
```

---

### Priorita 3: Otestujte

1. Zkontrolujte, že PHPMailer je načtený:
   ```php
   var_dump(class_exists('PHPMailer\\PHPMailer\\PHPMailer'));
   // Mělo by vrátit: bool(true)
   ```

2. Zkontrolujte logy:
   ```bash
   tail -f /home/user/moje-stranky/logs/php_errors.log
   ```

3. Odešlete testovací email přes protokol.

---

## 📚 SOUVISEJÍCÍ DOKUMENTY

- `INSTALACE_PHPMAILER.md` - Návod na instalaci PHPMailer
- `EMAIL_QUEUE_README.md` - Dokumentace email queue systému
- `DATA_FLOW_INTEGRATION_ANALYSIS.md` - Analýza toku dat

---

## ✅ CHECKLIST

- [ ] 1. Nainstalovat PHPMailer (composer nebo manuálně)
- [ ] 2. Ověřit instalaci: `class_exists('PHPMailer\\PHPMailer\\PHPMailer')`
- [ ] 3. Spustit `oprav_smtp_ihned.php`
- [ ] 4. Zkontrolovat databázi: `SELECT * FROM wgs_smtp_settings WHERE is_active=1`
- [ ] 5. Otestovat odeslání emailu přes protokol
- [ ] 6. Zkontrolovat logy: `/logs/php_errors.log`
- [ ] 7. Ověřit, že email dorazil

---

---

## 🔥 AKTUALIZACE AUDITU (2025-11-19 13:30)

### KRITICKÉ NÁLEZY Z SQL ANALÝZY:

#### 1. ❌ DUPLICITNÍ SMTP KONFIGURACE!

**DVĚ tabulky obsahují SMTP nastavení:**

**Tabulka 1: `wgs_smtp_settings` (id=4)**
```sql
smtp_host:       smtp.ceskyhosting.cz
smtp_port:       587
smtp_encryption: tls
smtp_username:   reklamace@wgs-service.cz
smtp_password:   O7cw+hkbKSrg/Eew
is_active:       1
```

**Tabulka 2: `wgs_system_config` (3 řádky)**
```sql
config_key: smtp_host       value: smtp.ceskyhosting.cz
config_key: smtp_port       value: 587
config_key: smtp_username   value: reklamace@wgs-service.cz
```

**⚠️ PROBLÉM:** Dvě místa = riziko konfliktu a nekonzistence!

---

#### 2. ❌ 17 EMAILŮ SELHALO VE FRONTĚ!

**Tabulka: `wgs_email_queue`**

| ID | To | Status | Error Message | Attempts |
|----|-----|--------|---------------|----------|
| 1 | zikmund.radek@seznam.cz | pending | SMTP Error: Could not connect to SMTP host | 3/3 |
| 2 | marie@kolacna.cz | pending | SMTP Error: Could not connect to SMTP host | 3/3 |
| 3 | jitka@krupickova.cz | pending | SMTP Error: Could not connect to SMTP host | 3/3 |
| ... | ... | ... | ... | ... |

**Všech 17 emailů má stejnou chybu:**
```
SMTP Error: Could not connect to SMTP host. Failed to connect to server
SMTP server error: Failed to connect...
```

**✅ DOBRÁ ZPRÁVA:** Chyba "SMTP Error" znamená, že **PHPMailer JE nainstalován** a snaží se připojit!

---

#### 3. ❌ POUŽÍVÁTE ŠPATNÝ SMTP SERVER!

**Z hostingového panelu:**

```
Český hosting nabízí:

1. smtp.cesky-hosting.cz (port 587, TLS)
   → Pro poštovní klienty (Outlook, Thunderbird)
   → Vyžaduje autentizaci

2. websmtp.cesky-hosting.cz (port 25, žádné šifrování)
   → Pro PHP skripty
   → Vyžaduje doménovou autentizaci (username: wgs-service.cz)
```

**VY POUŽÍVÁTE:** `smtp.cesky-hosting.cz:587` (pro poštovní klienty)
**MĚLI BYSTE POUŽÍVAT:** `websmtp.cesky-hosting.cz:25` (pro PHP skripty)

---

#### 4. ✅ DKIM JE SPRÁVNĚ NASTAVEN

**Aktivní DKIM záznamy v DNS:**
- ✅ Webserver (PHP, WebSMTP)
- ✅ Odesílací server (smtp.cesky-hosting.cz)
- ✅ Webmail
- ✅ **WebSMTP** ← KLÍČOVÉ!

**SPF záznam:**
```
v=spf1 include:mx.cesky-hosting.cz include:websmtp.cesky-hosting.cz ~all
```

✅ Správně zahrnuje `websmtp.cesky-hosting.cz`!

---

## 🎯 KONEČNÉ ŘEŠENÍ (KROK ZA KROKEM)

### Krok 1: Vyčistit duplicitní konfiguraci

**Problém:** Máte 2 tabulky s SMTP nastavením.

**Řešení:** Používat POUZE `wgs_smtp_settings`, odstranit z `wgs_system_config`.

**Skript:** Vytvořím migrační skript `sjednotit_smtp_konfiguraci.php`

---

### Krok 2: Opravit SMTP nastavení

**Aktuální (nefunguje):**
```
Host:       smtp.ceskyhosting.cz
Port:       587
Encryption: tls
Username:   reklamace@wgs-service.cz
```

**Správné (bude fungovat):**
```
Host:       websmtp.cesky-hosting.cz
Port:       25
Encryption: none
Username:   wgs-service.cz
```

**Spustit:**
```
https://www.wgs-service.cz/oprav_smtp_ihned.php
```

---

### Krok 3: Vyčistit frontu selhavších emailů

**Problém:** 17 emailů ve stavu `pending` s 3/3 pokusy.

**Řešení:**
1. Opravit SMTP konfiguraci (Krok 2)
2. Resetovat `attempts` na 0 pro všechny `pending` emaily
3. Spustit email queue worker

**Skript:** Vytvořím `reset_email_queue.php`

---

### Krok 4: Otestovat

1. ✅ Zkontrolovat PHPMailer: `var_dump(class_exists('PHPMailer\\PHPMailer\\PHPMailer'));`
2. ✅ Odeslat testovací email
3. ✅ Zkontrolovat logy: `/logs/php_errors.log`
4. ✅ Ověřit doručení

---

## 📊 POROVNÁNÍ SMTP SERVERŮ

| Vlastnost | smtp.cesky-hosting.cz | websmtp.cesky-hosting.cz |
|-----------|----------------------|--------------------------|
| **Účel** | Poštovní klienty | PHP skripty |
| **Port** | 587 | 25 |
| **Šifrování** | TLS | žádné |
| **Username** | celá adresa (reklamace@wgs-service.cz) | doména (wgs-service.cz) |
| **Autentizace** | heslo schránky | doménová |
| **Pro WGS?** | ❌ NE | ✅ ANO |

---

## 🔄 MIGRACE - SJEDNOCENÍ KONFIGURACE

Vytvořím migrační skript, který:

1. ✅ Sjednotí SMTP konfiguraci (odstraní duplicity)
2. ✅ Nastaví správný server (websmtp.cesky-hosting.cz:25)
3. ✅ Vyčistí email frontu
4. ✅ Otestuje připojení

**Skript:** `sjednotit_email_konfiguraci.php`

---

**© 2025 WGS Service - White Glove Service**
