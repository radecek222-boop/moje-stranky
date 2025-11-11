# 🎯 Admin Control Center v2.0

## 📋 Přehled

Admin Control Center je kompletní iOS-style řídicí panel pro správu celé WGS aplikace. Umožňuje jednoduchým a intuitivním způsobem spravovat vzhled, obsah, uživatele, notifikace, konfiguraci a systémovou diagnostiku.

## ✨ Hlavní funkce

### 🎨 1. Vzhled & Design
- **Barvy**: Editace všech barev aplikace (primární, sekundární, success, warning, danger, atd.)
- **Fonty**: Výběr z profesionálních fontů (Poppins, Inter, Roboto, Montserrat, ...)
- **Layout**: Nastavení zaoblení rohů (border-radius)
- **Live Preview**: Okamžitý náhled změn před uložením
- **Reset**: Možnost vrátit výchozí nastavení

### 📝 2. Obsah & Texty
- **Multi-jazyčnost**: Editace textů v CZ, EN, SK
- **Stránky**: Správa obsahu pro Index, Nová reklamace, O nás, Kontakt, Email šablony
- **Sekce**: Organizace podle sekcí (hero, form, signature, atd.)
- **Auto-translate**: Připraveno pro integraci s DeepL API
- **Bulk akce**: Uložit vše, přeložit vše, export/import

### 👥 3. Správa uživatelů
- Redirect na existující sekci uživatelů
- Správa techniků, prodejců, zákazníků
- Role a oprávnění

### 🔔 4. Notifikace
- Redirect na existující systém notifikací
- Database-driven notifikace
- Real-time události

### ⚙️ 5. Konfigurace
- **Email (SMTP)**: Nastavení SMTP serveru pro odesílání emailů
  - Host, port, username, password
  - Test email pro ověření funkčnosti
- **API klíče**: Správa všech API klíčů
  - Geoapify (mapy)
  - DeepL (překlady)
  - GitHub Webhook Secret
- **Bezpečnost**: Rate limiting, session timeout
- **Systém**: Maintenance mode, debug settings
- **Maskování hesel**: Citlivé hodnoty zobrazeny jako ••••••••

### 🏥 6. Diagnostika systému
- **System Health**: Real-time monitoring komponent
  - 🗄️ Databáze (připojení, ping)
  - 🐘 PHP verze
  - 🧩 PHP Extensions (pdo, pdo_mysql, mbstring, json, gd)
  - 📁 Oprávnění souborů (logs, uploads, temp)
  - 💾 Diskový prostor
- **Logy**: Prohlížení logů
  - PHP Error Log
  - Security Log
  - Audit Log
- **Údržba**: Maintenance akce
  - Vymazat cache
  - Archivovat staré logy
  - Optimalizovat databázi

### 🚀 7. Akce & Úkoly
- **Pending Actions**: Nevyřešené úkoly s prioritami
  - 🔴 Kritické
  - 🟠 Vysoká priorita
  - 🟡 Střední priorita
  - 🟢 Nízká priorita
- **GitHub Webhooks**: Historie GitHub událostí
  - Push do main/master → vytvoří deploy úkol
  - Pull Request opened → vytvoří review úkol
  - Issue opened → vytvoří review úkol
  - Release published → vytvoří deploy úkol (kritický)
  - Workflow failed → vytvoří debug úkol
- **Scheduled Tasks**: Přehled cron úloh
  - Session cleanup (24h)
  - Email reminders (denně 8:00)
  - Statistics generation (týdně)
- **Badge notifikace**: Počet nevyřešených úkolů na kartách

### 📊 8. Statistiky
- Redirect na existující statistiky

## 🚀 Instalace

### Krok 1: Spuštění migrace
1. Otevřít **admin.php** → **Control Center**
2. Pokud tabulky neexistují, zobrazí se upozornění
3. Kliknout na odkaz pro instalaci nebo přejít na `/install_admin_control_center.php`
4. Kliknout "Spustit instalaci"
5. Počkat na dokončení (vytvoří 6 tabulek)

### Krok 2: Základní konfigurace
1. Otevřít **Configuration** sekci
2. Vyplnit SMTP nastavení (pokud chcete odesílat emaily)
3. Přidat API klíče (Geoapify, DeepL)
4. Nastavit bezpečnostní limity

### Krok 3: Přizpůsobení vzhledu
1. Otevřít **Appearance** sekci
2. Upravit barvy podle firemních barev
3. Vybrat font
4. Nastavit border-radius
5. Kliknout "Uložit vše"

### Krok 4: GitHub Webhooks (volitelné)
1. Zkopírovat webhook URL: `https://vase-domena.cz/api/github_webhook.php`
2. Otevřít GitHub repozitář → Settings → Webhooks → Add webhook
3. Vložit URL
4. Content type: `application/json`
5. Vytvořit secret a vložit ho do Configuration → github_webhook_secret
6. Vybrat události: Push, Pull requests, Issues, Releases, Workflow runs
7. Uložit

## 📊 Databázové tabulky

### `wgs_theme_settings`
- Uložení barev, fontů, layoutu
- Používá Control Center Appearance

### `wgs_content_texts`
- Multi-jazyčné texty (CZ/EN/SK)
- Organizace: page → section → text_key

### `wgs_system_config`
- Systémová konfigurace
- Skupiny: email, api_keys, security, system
- Citlivé hodnoty označeny `is_sensitive`

### `wgs_pending_actions`
- Nevyřešené úkoly s prioritami
- Automaticky vytvářené z GitHub webhooks
- Statusy: pending, in_progress, completed, failed, dismissed

### `wgs_action_history`
- Historie dokončených akcí
- Audit trail s časovými údaji

### `wgs_github_webhooks`
- Log všech GitHub událostí
- Payload v JSON formátu
- Propojení s pending_actions přes source_id

## 🎨 Design System

### Barvy
```css
--cc-primary: #667eea (fialová)
--cc-secondary: #764ba2 (purpurová)
--cc-success: #28A745 (zelená)
--cc-warning: #FFC107 (žlutá)
--cc-danger: #DC3545 (červená)
```

### Komponenty
- **Cards**: iOS-style karty s hover efekty
- **Buttons**: Zaoblená tlačítka s gradientem
- **Inputs**: Clean inputy s focus stavy
- **Toggles**: iOS-style toggle switche
- **Alerts**: Barevné alert boxy s ikonami
- **Badges**: Notifikační badges (iOS style)

### Responsive Design
- **Desktop**: 1200px+ (full layout)
- **Tablet**: 768px-1199px (2 columns)
- **Mobile**: <768px (single column, stacked)

## 🔒 Bezpečnost

### CSRF Protection
- Všechny POST/PUT/DELETE operace vyžadují CSRF token
- Token generován pomocí `csrf-auto-inject.js`
- Validace na backend pomocí `validateCSRFToken()`

### Admin Only
- Všechny sekce Control Center vyžadují admin přístup
- Check: `$_SESSION['is_admin'] === true`

### Maskování citlivých dat
- Hesla a API klíče zobrazeny jako ••••••••
- Toggle visibility pomocí 👁️ tlačítka
- Hodnoty never logované

### GitHub Webhook Signature
- HMAC SHA256 signature validation
- Secret uložený v databázi (`github_webhook_secret`)
- Reject nevalidní požadavky (403)

## 📁 Struktura souborů

```
/
├── admin.php                           # Main admin s routingem
├── install_admin_control_center.php   # Web-based installer
├── migration_admin_control_center.sql # SQL migrace
├── CONTROL_CENTER_README.md           # Tato dokumentace
├── /includes/
│   ├── control_center_main.php        # Dashboard s kartami
│   ├── control_center_appearance.php  # Vzhled & Design
│   ├── control_center_content.php     # Obsah & Texty
│   ├── control_center_configuration.php # Konfigurace
│   ├── control_center_diagnostics.php # Diagnostika
│   └── control_center_actions.php     # Akce & Úkoly
├── /api/
│   ├── control_center_api.php         # Backend API
│   └── github_webhook.php             # GitHub webhook handler
└── /assets/css/
    └── control-center.css             # iOS-style CSS framework
```

## 🛠️ API Endpoints

### `/api/control_center_api.php`

#### Theme
- `?action=save_theme` - Uložit theme settings

#### Actions
- `?action=complete_action` - Označit akci jako dokončenou
- `?action=dismiss_action` - Zrušit akci

#### Diagnostics
- `?action=clear_cache` - Vymazat cache soubory
- `?action=archive_logs` - Archivovat staré logy
- `?action=optimize_database` - Optimalizovat DB tabulky

#### Content
- `?action=get_content_texts` - Načíst texty
- `?action=save_content_text` - Uložit text

#### Configuration
- `?action=get_system_config` - Načíst konfiguraci
- `?action=save_system_config` - Uložit konfiguraci
- `?action=send_test_email` - Odeslat testovací email

### `/api/github_webhook.php`
- Přijímá webhooks z GitHub
- Validuje HMAC signature
- Parsuje události (push, PR, issues, release, workflow)
- Vytváří pending actions podle priority

## 🧪 Testování

### Test 1: Instalace
1. Otevřít `/install_admin_control_center.php`
2. Ověřit, že všech 6 tabulek je vytvořeno
3. Ověřit, že jsou vložena defaultní data

### Test 2: Appearance
1. Změnit primary_color na #FF0000
2. Kliknout "Preview změn"
3. Ověřit, že se barvy změní
4. Kliknout "Uložit vše"
5. Reload stránky → barvy zůstanou

### Test 3: Configuration
1. Vyplnit testovací email
2. Kliknout "Odeslat test"
3. Ověřit, že email dorazil

### Test 4: Diagnostics
1. Zkontrolovat System Health
2. Všechny komponenty by měly být zelené
3. Kliknout "Vymazat cache"
4. Ověřit úspěšné vymazání

### Test 5: GitHub Webhook
1. Nastavit webhook v GitHub
2. Push commit do testu
3. Ověřit, že se objevil v Actions sekci
4. Pokud push do main → měl by vytvořit pending action

## 🐛 Řešení problémů

### Tabulky neexistují
→ Spustit `/install_admin_control_center.php`

### CSRF token error
→ Ujistit se, že je zahrnut `/assets/js/csrf-auto-inject.js`

### GitHub webhook 403 Forbidden
→ Zkontrolovat `github_webhook_secret` v Configuration
→ Ujistit se, že secret v GitHub odpovídá

### Email se neposílá
→ Zkontrolovat SMTP nastavení
→ Použít "Test email" funkci
→ Zkontrolovat PHP error log

### Styly se nenačítají
→ Ujistit se, že existuje `/assets/css/control-center.css`
→ Vyčistit browser cache

## 📝 Changelog

### v2.0 (2025-11-11)
- ✅ Kompletní iOS-style design
- ✅ 8 hlavních sekcí
- ✅ Database-driven konfigurace
- ✅ Multi-jazyčnost (CZ/EN/SK)
- ✅ GitHub webhooks integrace
- ✅ System health monitoring
- ✅ CSRF protection
- ✅ Responsive design
- ✅ Badge notifikace
- ✅ Password masking

### v1.0 (2025-11-11)
- 🎨 Appearance sekce
- 🏥 Diagnostics sekce
- 🚀 Actions sekce

## 🚀 Budoucí vylepšení

- [ ] Auto-translate integrace s DeepL API
- [ ] Export/import content textů
- [ ] Dark mode toggle
- [ ] Real-time WebSocket notifikace
- [ ] Advanced statistics v Control Center
- [ ] Bulk user management
- [ ] Email template editor
- [ ] Custom CSS editor
- [ ] File manager
- [ ] Backup & restore funkce

## 👤 Autor
White Glove Service Team

## 📄 Licence
Proprietary - Internal use only
