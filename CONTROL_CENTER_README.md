# 🎯 Admin Control Center v3.0 - UNIFIED INTERFACE

## 📋 Přehled

**Admin Control Center je nyní KOMPLETNÍ mozek celé WGS aplikace.** Všechny admin funkce jsou integrovány do jediného unified řídicího panelu s minimalistickým accordion designem. Admin stránka (`admin.php`) nyní přímo zobrazuje Control Center - bez navigačního menu, jen s logem a tlačítkem "Odhlásit".

## 🎨 Design Filozofie

**WGS Minimalistický Styl:**
- **Accordion interface** - expandující sekce místo karet
- **Lazy loading** - data se načítají až po otevření sekce
- **Bez ikon** - čistý, profesionální vzhled
- **Badge notifikace** - vizuální indikátory počtů
- **Černá/Bílá/Zelená** - firemní barevná schéma
- **Jednoduchý header** - pouze logo "WGS CONTROL CENTER" a "Odhlásit"

## ✨ Všechny funkce v jednom místě

### 12 sekcí Control Center:

#### 📊 1. STATISTIKY & ANALYTICS
- **Nahrazuje:** Dashboard, statistiky.php, analytics.php
- **Funkce:**
  - Přehled všech reklamací
  - Počet uživatelů a online uživatelů
  - Aktivní registrační klíče
  - Grafy a statistiky systému
  - Export dat

#### 🔑 2. REGISTRAČNÍ KLÍČE
- **Nahrazuje:** admin.php?tab=keys
- **Funkce:**
  - Vytváření nových klíčů
  - Správa existujících klíčů (aktivovat/deaktivovat)
  - Sledování využití klíčů
  - Přiřazení rolí (technik, prodejce)
  - Bulk operace

#### 👥 3. UŽIVATELÉ
- **Nahrazuje:** admin.php?tab=users
- **Funkce:**
  - Správa všech uživatelů
  - Editace rolí a oprávnění
  - Aktivace/deaktivace účtů
  - Resetování hesel
  - Filtrování podle role

#### 🟢 4. ONLINE UŽIVATELÉ
- **Nahrazuje:** admin.php?tab=online
- **Funkce:**
  - Real-time přehled online uživatelů
  - Poslední aktivita
  - IP adresy a lokace
  - Automatická aktualizace každých 15s
  - Kick uživatele (force logout)

#### 📧 5. EMAIL & SMS NOTIFIKACE
- **Nahrazuje:** admin.php?tab=notifications
- **Funkce:**
  - Správa email šablon
  - SMS šablony a nastavení
  - Automatické notifikace
  - Hromadné rozesílky
  - Historie odeslaných zpráv

#### 📝 6. REKLAMACE
- **Nahrazuje:** seznam.php pro admin správu
- **Funkce:**
  - Přehled všech reklamací
  - Hromadné operace
  - Export do CSV/PDF
  - Pokročilé filtrování
  - Rychlá editace stavů

#### 🎨 7. VZHLED & DESIGN
- **Funkce:**
  - Editace všech barev aplikace
  - Výběr fontů (Poppins, Inter, Roboto, Montserrat...)
  - Border-radius nastavení
  - Live preview změn
  - Reset na výchozí

#### 📄 8. OBSAH & TEXTY
- **Funkce:**
  - Multi-jazyčnost (CZ/EN/SK)
  - Editace textů pro všechny stránky
  - Auto-translate připraveno (DeepL API)
  - Bulk uložení všech změn
  - Export/import

#### ⚙️ 9. KONFIGURACE SYSTÉMU
- **Funkce:**
  - **Email (SMTP):** Host, port, credentials, test email
  - **API klíče:** Geoapify, DeepL, GitHub Webhook Secret
  - **Bezpečnost:** Rate limiting, session timeout, CSRF
  - **Systém:** Maintenance mode, debug settings
  - **Maskování hesel:** Citlivá data jako ••••••••

#### 🏥 10. DIAGNOSTIKA
- **Nahrazuje:** admin.php?tab=tools
- **Funkce:**
  - **System Health:** Real-time monitoring
    - 🗄️ Databáze (připojení, ping time)
    - 🐘 PHP verze a extensions
    - 📁 File permissions (logs/, uploads/, temp/)
    - 💾 Diskový prostor
  - **Logy:** PHP errors, security, audit
  - **Údržba:** Clear cache, archive logs, optimize DB

#### 🚀 11. AKCE & ÚKOLY
- **Funkce:**
  - **Pending Actions** s prioritami (🔴 kritické, 🟠 vysoká, 🟡 střední, 🟢 nízká)
  - **GitHub Webhooks** integrace
    - Push do main → deploy úkol
    - Pull Request → review úkol
    - Issue opened → review úkol
    - Release → kritický deploy
    - Workflow failed → debug úkol
  - **Scheduled Tasks:** Přehled cron úloh
  - **Badge notifikace:** Počet nevyřešených úkolů

#### 🧪 12. TESTOVACÍ PROSTŘEDÍ
- **Funkce:**
  - **E2E Testing:** End-to-end testování celého workflow
  - **Vizuální simulace:** 9-krokový test workflow
    1. DB připojení
    2. Registrace uživatele
    3. Vytvoření reklamace
    4. Nahrání fotky
    5. Seznam reklamací
    6. Aktualizace datumů
    7. Kontrola protokolu
    8. Kontrola emailu
    9. Kompletní detail
  - **Reálné testy:** Připojení k SQL, skutečné parametry
  - **Role testing:** Admin, Prodejce, Technik, Guest
  - **Cleanup:** Potvrzení a smazání test dat po úspěšném testu
  - **Error reporting:** Copy button pro odeslání chyb do Claude Code

## 🚀 Instalace

### Automatická instalace (doporučeno)
1. Otevřít **admin.php** (automaticky se zobrazí Control Center)
2. Pokud tabulky neexistují, zobrazí se instalační link
3. Kliknout na link nebo přejít na `/install_admin_control_center.php`
4. Kliknout "Spustit instalaci"
5. Počkat na dokončení (vytvoří 6 tabulek)

### Verifikace instalace
- Všechny sekce by měly být funkční
- Spustit **Testovací prostředí** pro ověření systému

## 📊 Databázové tabulky

### `wgs_theme_settings`
- Barvy, fonty, layout
- Používá Control Center → Vzhled & Design

### `wgs_content_texts`
- Multi-jazyčné texty (CZ/EN/SK)
- Organizace: page → section → text_key

### `wgs_system_config`
- Systémová konfigurace
- Skupiny: email, api_keys, security, system
- Citlivé hodnoty označeny `is_sensitive`

### `wgs_pending_actions`
- Nevyřešené úkoly s prioritami
- Automaticky z GitHub webhooks
- Statusy: pending, in_progress, completed, failed, dismissed

### `wgs_action_history`
- Historie dokončených akcí
- Audit trail

### `wgs_github_webhooks`
- Log všech GitHub událostí
- JSON payload
- Propojení s pending_actions

## 🎨 Design System

### Hlavní barvy
```css
--c-black: #000000
--c-white: #FFFFFF
--c-success: #2D5016 (tmavě zelená)
--c-error: #8B0000 (tmavě červená)
--c-warning: #FFC107 (žlutá)
--c-grey: #666666
--c-border: #DDDDDD
--c-bg: #F8F8F8
```

### Accordion Design
- **Collapsed:** Zobrazuje title, subtitle, badge
- **Expanded:** Načte a zobrazí obsah sekce
- **Smooth transitions:** 0.3s ease
- **Hover efekty:** Jemné stíny
- **Chevron rotace:** 180° při rozbalení

### Responsive Design
- **Desktop:** 1400px max-width
- **Tablet:** Responsive grid
- **Mobile:** Single column

## 🔒 Bezpečnost

### Admin Only
- Všechny Control Center funkce vyžadují: `$_SESSION['is_admin'] === true`
- Redirect na login.php pokud nejste přihlášeni

### CSRF Protection
- Všechny mutační operace vyžadují CSRF token
- Auto-inject pomocí `csrf-auto-inject.js`
- Backend validace: `validateCSRFToken()`

### Citlivá data
- Hesla a API klíče maskované: ••••••••
- Toggle visibility (👁️ ikona)
- Never logováno

### GitHub Webhook Security
- HMAC SHA256 signature validation
- Secret v databázi (`github_webhook_secret`)
- Reject nevalidní požadavky (403)

## 📁 Struktura souborů

```
/
├── admin.php                              # Hlavní admin (výchozí = Control Center)
├── install_admin_control_center.php      # Web-based installer
├── migration_admin_control_center.sql    # SQL migrace (6 tabulek)
├── CONTROL_CENTER_README.md              # Tato dokumentace
├── ERROR_HANDLING_README.md              # Dokumentace error handlingu
├── /includes/
│   ├── admin_header.php                  # Minimalistický header (logo + odhlásit)
│   ├── control_center_unified.php        # HLAVNÍ UNIFIED INTERFACE (12 sekcí)
│   ├── control_center_testing.php        # E2E testovací prostředí
│   └── error_handler.php                 # Advanced error handler
├── /api/
│   ├── control_center_api.php            # Backend API pro všechny operace
│   ├── test_environment_simple.php       # Real testy workflow
│   ├── test_cleanup.php                  # Cleanup test dat
│   ├── github_webhook.php                # GitHub webhook handler
│   └── log_js_error.php                  # JavaScript error logging
└── /assets/
    ├── /css/
    │   └── control-center.css            # Accordion styles
    └── /js/
        └── error-handler.js              # JS error catching

```

## 🛠️ API Endpoints

### `/api/control_center_api.php`

**Theme:**
- `?action=save_theme` - Uložit theme settings

**Stats:**
- `?action=get_statistics` - Načíst statistiky
- `?action=get_online_users` - Online uživatelé

**Keys:**
- `?action=get_keys` - Načíst registrační klíče
- `?action=create_key` - Vytvořit nový klíč
- `?action=toggle_key` - Aktivovat/deaktivovat

**Users:**
- `?action=get_users` - Načíst uživatele
- `?action=update_user` - Upravit uživatele

**Actions:**
- `?action=get_pending_actions` - Načíst úkoly
- `?action=complete_action` - Dokončit úkol
- `?action=dismiss_action` - Zrušit úkol

**Diagnostics:**
- `?action=clear_cache` - Vymazat cache
- `?action=archive_logs` - Archivovat logy
- `?action=optimize_database` - Optimalizovat DB

**Content:**
- `?action=get_content_texts` - Načíst texty
- `?action=save_content_text` - Uložit text

**Configuration:**
- `?action=get_system_config` - Načíst konfiguraci
- `?action=save_system_config` - Uložit konfiguraci
- `?action=send_test_email` - Testovací email

### `/api/test_environment_simple.php`
- POST `role=admin` - Spustit E2E test jako admin
- Vrací průběžné výsledky všech 9 kroků

### `/api/test_cleanup.php`
- POST - Vymazat test data po potvrzení

### `/api/github_webhook.php`
- POST - Přijímá GitHub webhooks
- Vytváří pending actions

## 🧪 Testování

### Test 1: Admin přístup
1. Přihlásit se jako admin
2. Otevřít `admin.php`
3. ✅ Mělo by se zobrazit Control Center (ne navigace)
4. ✅ Header obsahuje jen "WGS CONTROL CENTER" a "Odhlásit"

### Test 2: Accordion funkčnost
1. Otevřít Control Center
2. Kliknout na libovolnou sekci
3. ✅ Sekce se rozbalí a načte data
4. ✅ Badge notifikace se zobrazují správně
5. Kliknout znovu → sekce se sbalí

### Test 3: E2E Testing Environment
1. Otevřít Control Center → Sekce 12: Testovací prostředí
2. Vybrat roli (Admin)
3. Kliknout "Spustit test"
4. ✅ Všech 9 kroků by mělo proběhnout úspěšně
5. ✅ Zobrazí se "Potvrdit a smazat test data"
6. Kliknout na potvrzení
7. ✅ Test data jsou vymazána

### Test 4: Error Handling
1. V prohlížeči otevřít konzoli
2. Vyvolat chybu (např. neexistující API endpoint)
3. ✅ Zobrazí se detailní chybové hlášení
4. ✅ Tlačítko "Kopírovat pro Claude Code nebo Codex" funguje

### Test 5: GitHub Webhook
1. Konfigurace → Nastavit github_webhook_secret
2. V GitHub: Settings → Webhooks → Add webhook
3. URL: `https://vase-domena.cz/api/github_webhook.php`
4. Push commit do main
5. ✅ V sekci "Akce & Úkoly" by měl být nový deploy úkol

## 🐛 Řešení problémů

### "Tabulky neexistují"
**Řešení:** Spustit `/install_admin_control_center.php`

### "Sekce se nenačítá"
**Řešení:**
1. Otevřít browser console (F12)
2. Zkontrolovat network tab
3. Hledat 404 nebo 500 errors
4. Použít error handler "Copy for Claude Code" button

### "CSRF token error"
**Řešení:** Ujistit se, že je zahrnut `/assets/js/csrf-auto-inject.js`

### "GitHub webhook 403 Forbidden"
**Řešení:**
1. Zkontrolovat `github_webhook_secret` v Konfigurace
2. Ujistit se, že secret v GitHub odpovídá

### "Test selhává v kroku X"
**Řešení:**
1. Nečistit test data (nechat fail)
2. Zkontrolovat databázi pro test_xxx záznamy
3. Použít "Copy error" button
4. Poslat error report do Claude Code

## 📝 Changelog

### v3.0 (2025-11-11) - **UNIFIED INTERFACE**
- ✅ **MAJOR:** Unified accordion design místo iOS cards
- ✅ Všechny admin funkce v jednom Control Center
- ✅ Odstranění navigačního menu (pouze logo + odhlásit)
- ✅ 12 sekcí místo rozptýlených stránek
- ✅ Lazy loading pro optimalizaci
- ✅ Badge notifikace na všech sekcích
- ✅ E2E Testing Environment s real testy
- ✅ Advanced Error Handling s "Copy for Claude Code"
- ✅ Minimalistický WGS design (bez ikon)
- ✅ admin.php výchozí tab = control_center

### v2.0 (2025-11-11)
- iOS-style design (deprecated)
- 8 hlavních sekcí
- Database-driven konfigurace

### v1.0 (2025-11-11)
- Základní sekce (Appearance, Diagnostics, Actions)

## 🚀 Klíčové výhody v3.0

✅ **Jediné místo pro všechno** - Žádné přepínání mezi stránkami
✅ **Rychlé** - Lazy loading, data se načítají jen když je potřeba
✅ **Přehledné** - Accordion design, vše pod sebou
✅ **Minimalistické** - Bez zbytečných ikon a dekorací
✅ **Real testing** - E2E testy s cleanup funkcí
✅ **Error friendly** - Detailní chyby s copy button
✅ **Admin-first** - admin.php = Control Center (ne dashboard)

## 🔮 Budoucí vylepšení

- [ ] Real-time WebSocket notifikace
- [ ] Dark mode
- [ ] Custom CSS editor
- [ ] File manager v Control Center
- [ ] Backup & restore funkce
- [ ] AI asistent integrace (Claude Code commands)
- [ ] Mobile app (React Native)
- [ ] Multi-tenant support

## 👤 Autor
White Glove Service Team
Powered by Claude Code

## 📄 Licence
Proprietary - Internal use only
