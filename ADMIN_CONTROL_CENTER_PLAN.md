# Admin Control Center - iOS-Style Design Plan

**Datum:** 2025-11-11
**Cíl:** Vytvořit centrální řídicí panel pro kompletní správu WGS aplikace

---

## 🎯 Design filozofie

**Inspirace:** iOS Settings - jednoduché, intuitivní, přehledné
**Uživatel:** I laik musí pochopit, co co dělá
**Struktura:** Logické kategorie s ikonami, badge notifikacemi, toggle přepínači

---

## 📐 Struktura Admin Control Center

### 1. 🎨 **Vzhled & Design** (Appearance)
```
┌─────────────────────────────────────────┐
│ 🎨 Vzhled & Design                  →   │
│ Barvy, fonty, logo                      │
│ ────────────────────────────────────    │
│ • Barevná paleta systému                │
│ • Fonty a velikosti                     │
│ • Logo a branding                       │
│ • Tlačítka a komponenty                 │
└─────────────────────────────────────────┘
```

**Co lze upravit:**
- Primární barvy: `--wgs-black`, `--wgs-white`, `--wgs-grey`
- Barvy stavů: success (green), warning (yellow), danger (red)
- Font rodina: Poppins → možnost změnit
- Font váhy: 300, 400, 500, 600, 700
- Logo: upload nového loga
- Barvy tlačítek (primární, sekundární, disabled)
- Border radius: 0px (sharp) vs 8px (rounded)

**Implementace:**
- DB tabulka: `wgs_theme_settings`
- CSS generování: Dynamic CSS file nebo CSS variables override
- Live preview: Real-time náhled změn

---

### 2. 📝 **Obsah & Texty** (Content)
```
┌─────────────────────────────────────────┐
│ 📝 Obsah & Texty                    →   │
│ Upravit texty na stránkách              │
│ ────────────────────────────────────    │
│ • Hlavní stránka (Hero text)            │
│ • O nás (popis firmy)                   │
│ • Služby (popis služeb)                 │
│ • Email footery                         │
│ • Formulářové labely                    │
└─────────────────────────────────────────┘
```

**Co lze upravit:**
- Hero nadpis: "Servis spotřebičů všech značek"
- Hero podnadpis: "Rychle, kvalitně, profesionálně"
- Popis služeb: O nás, Naše služby
- Footer text: kontakty, copyright
- Email signatury: "S pozdravem, White Glove Service"
- Formulářové placeholdery a labely

**Implementace:**
- DB tabulka: `wgs_content_texts`
- Struktura: `page`, `section`, `key`, `value_cz`, `value_en`
- DeepL integrace pro překlady

---

### 3. 👥 **Uživatelé & Oprávnění** (Users)
```
┌─────────────────────────────────────────┐
│ 👥 Uživatelé & Oprávnění           →   │
│ Správa technici, prodejci, admini       │
│ ────────────────────────────────────    │
│ • Technici (12)                  [+]    │
│ • Prodejci (5)                   [+]    │
│ • Administrátoři (2)             [+]    │
│ • Registrační klíče (4 aktivní)  [+]    │
└─────────────────────────────────────────┘
```

**Co lze upravit:**
- **Technici:**
  - Přidat/odebrat technika
  - Změnit jméno, email, telefon
  - Deaktivovat účet
  - Resetovat heslo
  - Zobrazit přiřazené zakázky

- **Prodejci:**
  - Stejné funkce jako technici
  - Zobrazit vytvořené zakázky

- **Administrátoři:**
  - Povýšit/degradovat role
  - Full admin vs Limited admin

- **Registrační klíče:**
  - Vytořit nový klíč (admin/user/technik)
  - Nastavit limit použití
  - Deaktivovat klíč
  - Zobrazit historii použití

**Implementace:**
- Existuje: `wgs_users`, `wgs_registration_keys`
- API: `api/admin_users_api.php`, `api/admin_api.php`
- UI: Card-based layout s filtry a search

---

### 4. 📧 **Notifikace** (Notifications)
```
┌─────────────────────────────────────────┐
│ 📧 Notifikace                       →   │
│ Email & SMS šablony                [✓]  │
│ ────────────────────────────────────    │
│ • Email šablony (6 aktivních)       →   │
│ • SMS brána (nastavení)             →   │
│ • Push notifikace (připraveno)      →   │
└─────────────────────────────────────────┘
```

**Co lze upravit:**
- ✅ **Email šablony** (už implementováno!)
  - 6 typů: potvrzení, připomenutí, dokončení, atd.
  - Editace předmětu, textu, CC/BCC
  - Zapnout/vypnout jednotlivé notifikace

- 🆕 **SMS brána:**
  - API klíč (Twilio, SMS.cz)
  - Výchozí odesílatel
  - Cenové limity

- 🆕 **Push notifikace:**
  - OneSignal integrace
  - Testovací odeslání

**Implementace:**
- Existuje: `wgs_notifications`, `api/notification_api.php`
- Přidat: SMS config do DB tabulky

---

### 5. ⚙️ **Konfigurace** (Configuration)
```
┌─────────────────────────────────────────┐
│ ⚙️ Konfigurace                      →   │
│ SMTP, API klíče, databáze         [⚠️]  │
│ ────────────────────────────────────    │
│ • SMTP nastavení                    →   │
│ • API klíče (Geoapify, DeepL)       →   │
│ • Databáze (pouze zobrazení)        →   │
│ • Bezpečnost (rate limity)          →   │
└─────────────────────────────────────────┘
```

**Co lze upravit:**
- **SMTP:**
  - Host, Port, Username, Password
  - Test email funkce
  - ⚠️ Vyžaduje restart aplikace

- **API klíče:**
  - Geoapify (mapy)
  - DeepL (překlady)
  - SMS brána
  - Maskování citlivých hodnot: `sk_••••••••••1234`

- **Databáze:**
  - Pouze READ-ONLY zobrazení
  - Host, název, uživatel
  - Connection status

- **Bezpečnost:**
  - Rate limity: login (5/15min), upload (20/hod)
  - Session timeout: 24h
  - CSRF token lifetime

**Implementace:**
- .env soubor (některé hodnoty)
- DB tabulka: `wgs_system_config`
- Restart alert při změně

---

### 6. 🏥 **Diagnostika** (System Health)
```
┌─────────────────────────────────────────┐
│ 🏥 Diagnostika systému              →   │
│ Logy, chyby, výkon                 [🟢]  │
│ ────────────────────────────────────    │
│ • Stav systému (Healthy)           [🟢]  │
│ • PHP Error Log (12 záznamů)       [⚠️]  │
│ • Security Log (45 událostí)       [🟢]  │
│ • Audit Log (234 akcí)             [🟢]  │
│ • Database Health                  [🟢]  │
│ • Disk Space (35% použito)         [🟢]  │
└─────────────────────────────────────────┘
```

**Co lze zobrazit:**
- **Health Check:**
  - Database: ✅ Connected
  - Session: ✅ Active
  - File Permissions: ✅ OK
  - PHP Version: 8.2.x
  - Extensions: PDO, GD, mbstring

- **Error Logs:**
  - PHP chyby: `/logs/php_errors.log`
  - Security události: `/logs/security.log`
  - Audit trail: `/logs/audit_YYYY-MM.log`
  - Real-time tail zobrazení

- **Performance:**
  - Průměrná response time
  - Počet reklamací v DB
  - Velikost uploadovaných fotek
  - Disk space usage

**Implementace:**
- Existuje: `health.php`, `includes/audit_logger.php`
- UI: Dashboard s kartami a status indicators
- Real-time: WebSocket nebo polling

---

### 7. 🚀 **Akce & Úkoly** (Actions & Tasks)
```
┌─────────────────────────────────────────┐
│ 🚀 Akce & Úkoly                     →   │
│ GitHub, migrace, pending tasks     [3]  │
│ ────────────────────────────────────    │
│ • Nevyřešené akce (3)              [!]  │
│   - Instalovat notifikace          [→]  │
│   - Aktualizovat DB schema         [→]  │
│   - Vymazat staré logy             [→]  │
│                                          │
│ • GitHub Actions                    →   │
│ • Migrace databáze                  →   │
│ • Scheduled Tasks (Cron)            →   │
└─────────────────────────────────────────┘
```

**Co lze dělat:**
- **Nevyřešené akce:**
  - Seznam pending úkolů s prioritou
  - Badge s počtem [3]
  - Tlačítko "Vyřešit" nebo "Spustit"
  - Historie dokončených akcí

- **GitHub Actions:**
  - Webhook endpoint pro GitHub
  - Notifikace o nových commitech
  - Deployment trigger
  - Rollback funkce

- **Migrace:**
  - Seznam dostupných migrací
  - Spustit migraci s progress barem
  - Rollback migrace

- **Cron Jobs:**
  - Session cleanup (24h)
  - Email remindery (denně)
  - Statistiky generování (týdně)
  - Status: Last run, Next run

**Implementace:**
- DB tabulka: `wgs_pending_actions`, `wgs_action_history`
- GitHub: Webhook handler
- Cron: Seznam registrovaných jobů

---

### 8. 📊 **Statistiky & Reporty** (Analytics)
```
┌─────────────────────────────────────────┐
│ 📊 Statistiky & Reporty             →   │
│ Dashboard, grafy, exporty               │
│ ────────────────────────────────────    │
│ • Dashboard (přehled)                   │
│ • Detailní statistiky                   │
│ • Export dat (CSV, PDF)                 │
└─────────────────────────────────────────┘
```

**Co lze zobrazit:**
- Dashboard widgets
- Grafy (Chart.js)
- Filtry: země, stav, technik, datum
- Export do CSV/PDF

**Implementace:**
- Existuje: `statistiky.php`, `analytics.php`
- Integrace do Control Center

---

## 🎨 UI/UX Design Pattern

### Card-based Layout (iOS-style):

```
┌──────────────────────────────────────────────┐
│  Admin Control Center                        │
│  ──────────────────────────────────────────  │
│                                               │
│  [Search: Hledat nastavení...]               │
│                                               │
│  ┌────────────────────────────────────────┐  │
│  │ 🎨 Vzhled & Design                  → │  │
│  │ Barvy, fonty, logo                      │  │
│  └────────────────────────────────────────┘  │
│                                               │
│  ┌────────────────────────────────────────┐  │
│  │ 📝 Obsah & Texty                    → │  │
│  │ Upravit texty na stránkách              │  │
│  └────────────────────────────────────────┘  │
│                                               │
│  ┌────────────────────────────────────────┐  │
│  │ 👥 Uživatelé & Oprávnění       [12] → │  │
│  │ Technici, prodejci, admini              │  │
│  └────────────────────────────────────────┘  │
│                                               │
│  ┌────────────────────────────────────────┐  │
│  │ 📧 Notifikace                  [✓]  → │  │
│  │ Email & SMS šablony                     │  │
│  └────────────────────────────────────────┘  │
│                                               │
│  ┌────────────────────────────────────────┐  │
│  │ ⚙️ Konfigurace                 [⚠️] → │  │
│  │ SMTP, API klíče, databáze               │  │
│  └────────────────────────────────────────┘  │
│                                               │
│  ┌────────────────────────────────────────┐  │
│  │ 🏥 Diagnostika                 [🟢] → │  │
│  │ Logy, chyby, výkon                      │  │
│  └────────────────────────────────────────┘  │
│                                               │
│  ┌────────────────────────────────────────┐  │
│  │ 🚀 Akce & Úkoly                [3]  → │  │
│  │ GitHub, migrace, pending tasks          │  │
│  └────────────────────────────────────────┘  │
│                                               │
│  ┌────────────────────────────────────────┐  │
│  │ 📊 Statistiky & Reporty             → │  │
│  │ Dashboard, grafy, exporty               │  │
│  └────────────────────────────────────────┘  │
│                                               │
└──────────────────────────────────────────────┘
```

### Detailní view (příklad: Vzhled & Design):

```
┌──────────────────────────────────────────────┐
│  ← Zpět    Vzhled & Design                   │
│  ──────────────────────────────────────────  │
│                                               │
│  Barevná paleta                               │
│  ┌────────────────────────────────────────┐  │
│  │ Primární barva                          │  │
│  │ [████████] #000000          [Změnit]   │  │
│  └────────────────────────────────────────┘  │
│                                               │
│  ┌────────────────────────────────────────┐  │
│  │ Sekundární barva                        │  │
│  │ [████████] #FFFFFF          [Změnit]   │  │
│  └────────────────────────────────────────┘  │
│                                               │
│  ┌────────────────────────────────────────┐  │
│  │ Barva úspěchu                           │  │
│  │ [████████] #28A745          [Změnit]   │  │
│  └────────────────────────────────────────┘  │
│                                               │
│  Fonty                                        │
│  ┌────────────────────────────────────────┐  │
│  │ Font rodina                             │  │
│  │ [Poppins ▼]                 [Změnit]   │  │
│  └────────────────────────────────────────┘  │
│                                               │
│  Logo                                         │
│  ┌────────────────────────────────────────┐  │
│  │ [📷 Current Logo Preview]              │  │
│  │                                         │  │
│  │ [Nahrát nové logo]                      │  │
│  └────────────────────────────────────────┘  │
│                                               │
│  [Náhled změn]  [Uložit změny]              │
│                                               │
└──────────────────────────────────────────────┘
```

---

## 🗄️ Databázové tabulky (nové):

### `wgs_theme_settings`
```sql
CREATE TABLE wgs_theme_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('color', 'font', 'size', 'file') NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Výchozí hodnoty:
INSERT INTO wgs_theme_settings VALUES
('primary_color', '#000000', 'color'),
('secondary_color', '#FFFFFF', 'color'),
('success_color', '#28A745', 'color'),
('warning_color', '#FFC107', 'color'),
('danger_color', '#DC3545', 'color'),
('font_family', 'Poppins', 'font'),
('logo_path', '/assets/images/logo.png', 'file');
```

### `wgs_content_texts`
```sql
CREATE TABLE wgs_content_texts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    page VARCHAR(50) NOT NULL,
    section VARCHAR(50) NOT NULL,
    text_key VARCHAR(100) NOT NULL,
    value_cz TEXT,
    value_en TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_text (page, section, text_key)
);
```

### `wgs_system_config`
```sql
CREATE TABLE wgs_system_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT,
    is_sensitive BOOLEAN DEFAULT FALSE,
    requires_restart BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### `wgs_pending_actions`
```sql
CREATE TABLE wgs_pending_actions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    action_type VARCHAR(50) NOT NULL,
    action_title VARCHAR(255) NOT NULL,
    action_description TEXT,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL
);
```

---

## 📱 Responsive Design

- **Desktop:** Card grid (2-3 columns)
- **Tablet:** Card grid (2 columns)
- **Mobile:** Stacked cards (1 column)

---

## 🔔 Badge Notifikace

Přidání počítadel ke kartám:

```css
.control-card {
  position: relative;
}

.control-card-badge {
  position: absolute;
  top: 1rem;
  right: 1rem;
  background: #DC3545;
  color: white;
  border-radius: 50%;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 600;
}
```

Počítadla:
- **Uživatelé:** Počet aktivních uživatelů
- **Notifikace:** Počet aktivních šablon
- **Akce:** Počet pending úkolů
- **Diagnostika:** Status (🟢/🟡/🔴)

---

## 🚀 Implementační fáze

### Fáze 1: Struktura (1-2 hodiny)
1. Vytvořit DB tabulky
2. Vytvořit základní layout Control Center
3. Card-based UI s ikonami

### Fáze 2: Základní sekce (2-3 hodiny)
1. Vzhled & Design - color picker
2. Uživatelé - integrace existujícího API
3. Diagnostika - health dashboard

### Fáze 3: Pokročilé sekce (3-4 hodiny)
1. Obsah & Texty - WYSIWYG editor
2. Konfigurace - secure config editor
3. Akce & Úkoly - pending actions systém

### Fáze 4: GitHub integrace (2 hodiny)
1. Webhook endpoint
2. Action notifikace
3. Deployment trigger

---

**Celkový odhad:** 8-11 hodin čisté práce

**Výsledek:** Plnohodnotný, intuitivní Admin Control Center jako iOS Settings 🎯
