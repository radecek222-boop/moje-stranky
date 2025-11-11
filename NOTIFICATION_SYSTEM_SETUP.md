# Notifikační Systém - Instalace a Použití

**Datum:** 2025-11-11
**Verze:** 2.0 (Database-Driven)

---

## 📋 Přehled změn

### Co bylo změněno?

**PŘED:**
- Email šablony byly natvrdo zakódované v `notification_sender.php` (switch statement)
- Admin nemohl šablony upravovat - nebyly v databázi
- Žádná možnost přidávat CC/BCC emailyBEFORE (HARDCODED):**AFTER (DATABASE-DRIVEN):**
- Email šablony jsou v databázové tabulce `wgs_notifications`
- Admin může šablony upravovat přes UI v admin panelu (tab **Notifications**)
- Možnost přidávat CC/BCC emaily k notifikacím
- Možnost zapínat/vypínat jednotlivé notifikace
- Podpora proměnných v šablonách (např. `{{customer_name}}`, `{{order_id}}`)

---

## 🚀 Jak nainstalovat notifikační systém

### Krok 1: Spusťte instalátor

1. Přihlaste se do **admin panelu** jako administrátor
2. Přejděte na tab **"NÁSTROJE"** (Admin Tools)
3. V sekci **"Testuj v novém okně"** klikněte na tlačítko:
   **🔧 INSTALOVAT NOTIFIKACE** (červené tlačítko)
4. Otevře se instalační stránka `install_notifications.php`

### Krok 2: Spuštění instalace

Na instalační stránce se automaticky:
- ✅ Vytvoří tabulka `wgs_notifications`
- ✅ Naimportují se **6 výchozích email šablon**:
  1. **Potvrzení termínu návštěvy** (zákazníkovi)
  2. **Zakázka znovu otevřena** (adminovi)
  3. **Nová reklamace vytvořena** (adminovi)
  4. **Připomenutí termínu** (zákazníkovi)
  5. **Přiřazení termínu** (technikovi)
  6. **Zakázka dokončena** (zákazníkovi)

### Krok 3: Ověření

Po úspěšné instalaci:
1. Klikněte na **"Otevřít notifikace"** v instalátoru
2. Ověřte, že vidíte seznam všech 6 šablon v admin panelu
3. **DŮLEŽITÉ:** Smažte soubor `install_notifications.php` z webu (bezpečnostní důvod)

---

## 🎨 Jak upravit email šablony

### V admin panelu:

1. Přihlaste se jako **admin**
2. Klikněte na tab **"Notifications"** v horním menu
3. Uvidíte seznam všech email šablon
4. Klikněte na **kartu šablony** kterou chcete upravit
5. Otevře se **editační modal** s následujícími poli:

#### Editovatelné položky:

- **Příjemce:** Zákazník / Admin / Technik / Prodejce
- **Předmět:** Předmět emailu (pouze pro email typ)
- **Šablona zprávy:** Text zprávy s podporou proměnných
- **CC emaily:** Dodatečné kopie (viditelné pro všechny)
- **BCC emaily:** Skryté kopie (viditelné pouze pro BCC příjemce)

#### Proměnné v šablonách:

V poli "Šablona zprávy" můžete používat následující proměnné:

```
{{customer_name}}    - Jméno zákazníka
{{customer_email}}   - Email zákazníka
{{customer_phone}}   - Telefon zákazníka
{{date}}             - Datum termínu
{{time}}             - Čas termínu
{{order_id}}         - Číslo zakázky
{{address}}          - Adresa zákazníka
{{product}}          - Název produktu
{{description}}      - Popis problému
{{technician_name}}  - Jméno technika
{{seller_name}}      - Jméno prodejce
{{created_at}}       - Datum vytvoření
{{completed_at}}     - Datum dokončení
{{reopened_by}}      - Kdo znovu otevřel
{{reopened_at}}      - Kdy znovu otevřeno
```

**Příklad:**
```
Dobrý den {{customer_name}},

potvrzujeme termín návštěvy technika:

Datum: {{date}}
Čas: {{time}}
Adresa: {{address}}

S pozdravem,
White Glove Service
```

Po odeslání emailu se proměnné automaticky nahradí skutečnými hodnotami.

---

## 🔧 Technické detaily

### Databázová tabulka

```sql
wgs_notifications (
    id VARCHAR(50) PRIMARY KEY,           -- např. 'appointment_confirmed'
    name VARCHAR(255),                    -- Název notifikace
    description TEXT,                     -- Popis účelu
    trigger_event VARCHAR(100),           -- Kdy se spustí
    recipient_type ENUM(...),             -- Kdo dostane email
    type ENUM('email', 'sms', 'both'),    -- Typ notifikace
    subject VARCHAR(255),                 -- Předmět emailu
    template TEXT,                        -- Šablona s {{variables}}
    variables JSON,                       -- Seznam dostupných proměnných
    cc_emails JSON,                       -- CC emailové adresy
    bcc_emails JSON,                      -- BCC emailové adresy
    active TINYINT(1),                    -- Zapnuto/Vypnuto
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)
```

### API Endpointy

#### 1. Seznam notifikací
```
GET /api/notification_list_direct.php
```
Vrací všechny notifikační šablony pro admin UI.

#### 2. Zapnutí/Vypnutí notifikace
```
POST /api/notification_api.php?action=toggle
Body: {
  "notification_id": "appointment_confirmed",
  "active": true,
  "csrf_token": "..."
}
```

#### 3. Aktualizace šablony
```
POST /api/notification_api.php?action=update
Body: {
  "id": "appointment_confirmed",
  "recipient": "customer",
  "subject": "Nový předmět",
  "template": "Nová šablona s {{variables}}",
  "cc_emails": ["cc@example.com"],
  "bcc_emails": ["bcc@example.com"],
  "csrf_token": "..."
}
```

#### 4. Odeslání notifikace
```
POST /app/notification_sender.php
Body: {
  "notification_id": "appointment_confirmed",
  "data": {
    "customer_name": "Jan Novák",
    "customer_email": "jan@example.com",
    "appointment_date": "15.11.2025",
    "appointment_time": "14:00",
    "order_id": "WGS-12345"
  }
}
```

---

## 📊 Migrace z hardcoded na database-driven

### Změny v kódu:

#### `/app/notification_sender.php`
**PŘED:**
```php
switch ($notificationId) {
    case 'appointment_confirmed':
        $subject = "Potvrzení termínu návštěvy - WGS Servis";
        $message = "Dobrý den {$customerName},\n\n...";
        $to = $notificationData['customer_email'];
        break;
    // ... další případy
}
```

**PO:**
```php
// Načtení šablony z databáze
$stmt = $pdo->prepare("SELECT * FROM wgs_notifications WHERE id = :id AND active = 1");
$stmt->execute(['id' => $notificationId]);
$notification = $stmt->fetch();

// Náhrada proměnných
$variableMap = [
    '{{customer_name}}' => $notificationData['customer_name'],
    '{{date}}' => $notificationData['appointment_date'],
    // ...
];

$subject = str_replace(array_keys($variableMap), array_values($variableMap), $notification['subject']);
$message = str_replace(array_keys($variableMap), array_values($variableMap), $notification['template']);
```

---

## ✅ Výhody nového systému

| Vlastnost | Hardcoded | Database-Driven |
|-----------|-----------|-----------------|
| **Editace šablon** | ❌ Nutný přístup ke kódu | ✅ Admin UI |
| **CC/BCC emaily** | ❌ Není | ✅ Plně konfigurovatelné |
| **Zapnutí/Vypnutí** | ❌ Nutná změna kódu | ✅ Přepínač v UI |
| **Přidání nové šablony** | ❌ Programátor | ✅ INSERT do DB |
| **Versioning** | ❌ Git | ✅ DB `updated_at` |
| **Audit log** | ❌ Není | ✅ Timestamp změn |

---

## 🔒 Bezpečnost

- ✅ **Pouze admin** může spustit instalátor (`install_notifications.php`)
- ✅ **CSRF ochrana** na všech API endpointech
- ✅ **Rate limiting** - max 30 notifikací/hod na IP adresu
- ✅ **Email validace** - kontrola platnosti emailových adres
- ✅ **SQL injection prevence** - PDO prepared statements
- ✅ **XSS prevence** - escapování výstupu v admin UI

---

## 🐛 Řešení problémů

### Problém: "Žádné notifikace k zobrazení"
**Řešení:**
1. Zkontrolujte, zda jste spustili `install_notifications.php`
2. Ověřte v DB, zda existuje tabulka `wgs_notifications`:
   ```sql
   SHOW TABLES LIKE 'wgs_notifications';
   SELECT * FROM wgs_notifications;
   ```

### Problém: "Notification system not initialized"
**Řešení:**
- Tabulka `wgs_notifications` neexistuje
- Spusťte instalátor: `/install_notifications.php`

### Problém: Modal se neotevírá
**Řešení:**
- Zkontrolujte, že je načten `admin-notifications.js` v admin.php
- Otevřete konzoli prohlížeče (F12) a hledejte chyby

### Problém: Notifikace se neposílá
**Řešení:**
1. Zkontrolujte, zda je notifikace **aktivní** (zelený přepínač)
2. Ověřte, že `notification_sender.php` má přístup k DB
3. Zkontrolujte logy: `/logs/php_errors.log`

---

## 📝 Soubory vytvořené/upravené

### Nové soubory:
- ✅ `/install_notifications.php` - Instalační skript (SMAZAT po instalaci!)
- ✅ `/migration_create_notifications_table.sql` - SQL migrace
- ✅ `/NOTIFICATION_SYSTEM_SETUP.md` - Tento dokument

### Upravené soubory:
- ✅ `/app/notification_sender.php` - Přepsáno na database-driven
- ✅ `/admin.php` - Přidán link na instalátor (řádek 759)

### Existující soubory (nezměněny):
- ✅ `/api/notification_list_direct.php` - Načítání šablon
- ✅ `/api/notification_api.php` - Toggle & Update API
- ✅ `/assets/js/admin-notifications.js` - Frontend JS
- ✅ `/admin.php` (modal HTML, řádky 900-956)

---

## 🎯 Další kroky (volitelné)

1. **SMS podpora** - Implementovat odesílání SMS (typ: 'sms' nebo 'both')
2. **Plánované notifikace** - Cron job pro automatické připomínky
3. **História odeslaných emailů** - Tabulka `wgs_notification_history`
4. **Templates versioning** - Ukládání starých verzí šablon
5. **A/B testing** - Testování různých variant šablon

---

**Vytvořeno:** Claude AI Assistant
**Datum:** 2025-11-11
**Branch:** `claude/fix-autocomplete-placeholder-overlap-011CV1QG7NWLg6A9PMjTYTW9`
