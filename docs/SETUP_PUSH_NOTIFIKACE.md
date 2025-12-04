# Setup Web Push Notifikací

**Problém:** Push notifikace nefungují na PWA a desktop.

**Příčina:**
1. ❌ VAPID klíče nejsou nakonfigurovány v `.env`
2. ❌ Tabulka `wgs_push_subscriptions` neexistuje
3. ❌ Composer vendor složka neexistuje (potřeba pro knihovnu `minishlink/web-push`)

---

## ✅ Řešení - Krok za krokem

### 1. Vygenerovat VAPID klíče

1. Otevřít: **https://www.wgs-service.cz/setup_web_push.php**
2. Zkontrolovat stav VAPID klíčů
3. Pokud **nejsou nakonfigurovány**, kliknout na tlačítko **"Vygenerovat VAPID klíče"**
4. Klíče se automaticky přidají do `.env` souboru:
   ```env
   VAPID_PUBLIC_KEY=BFw...
   VAPID_PRIVATE_KEY=aG8...
   VAPID_SUBJECT=mailto:reklamace@wgs-service.cz
   ```

### 2. Vytvořit databázovou tabulku

1. Otevřít: **https://www.wgs-service.cz/pridej_push_subscriptions_tabulku.php**
2. Kliknout na **"Spustit Migraci"**
3. Vytvoří se 2 tabulky:
   - `wgs_push_subscriptions` - subscriptions uživatelů
   - `wgs_push_log` - log odeslaných notifikací

### 3. Nainstalovat Composer balíčky

**Na serveru spusť:**
```bash
cd /path/to/wgs-service.cz
composer install
```

Tím se nainstaluje knihovna:
- `minishlink/web-push` - pro odesílání push notifikací

### 4. Ověřit funkčnost

1. Otevřít PWA aplikaci (nainstalovanou na ploše)
2. Povolit notifikace (pokud se zobrazí dialog)
3. Zkontrolovat v Admin panelu → Push Notifikace
4. Odeslat testovací notifikaci

---

## 📋 Kontrolní seznam

- [ ] VAPID klíče vygenerovány (setup_web_push.php)
- [ ] Tabulka `wgs_push_subscriptions` vytvořena (pridej_push_subscriptions_tabulku.php)
- [ ] Composer vendor existuje (`composer install`)
- [ ] Push notifikace fungují v PWA

---

## 🔧 Technické detaily

### Podpora platform:
- ✅ **iOS 16.4+** (pouze v PWA režimu)
- ✅ **Android** (Chrome, Firefox)
- ✅ **Desktop** (Chrome, Firefox, Edge)

### Jak to funguje:
1. **Frontend** (`pwa-notifications.js`):
   - Registruje Service Worker
   - Žádá o povolení notifikací
   - Vytvoří Push Subscription pomocí VAPID public key
   - Odesílá subscription na server

2. **Backend** (`push_subscription_api.php`):
   - Ukládá subscriptions do databáze
   - Poskytuje VAPID public key pro frontend

3. **Service Worker** (`sw.js`):
   - Poslouchá push eventy
   - Zobrazuje notifikace
   - Reaguje na kliknutí (otevře stránku)

4. **WebPush třída** (`includes/WebPush.php`):
   - Šifruje a odesílá push zprávy
   - Používá knihovnu `minishlink/web-push`
   - Podporuje VAPID autentizaci

---

## ⚠️ Důležité poznámky

1. **VAPID klíče jsou tajné** - nikdy je necommituj do Gitu!
   - Jsou uloženy v `.env` (gitignored)

2. **iOS vyžaduje PWA režim** - push nefunguje v Safari prohlížeči
   - Uživatel musí nainstalovat aplikaci na plochu

3. **Hosting může blokovat SSL** - WebPush.php má vypnutou SSL verifikaci
   ```php
   'verify' => false,  // Pro hosting bez Apple certifikátů
   ```

4. **Re-generování klíčů** - pokud vygeneruješ nové VAPID klíče:
   - Všichni uživatelé se musí znovu přihlásit k odběru
   - Staré subscriptions přestanou fungovat

---

## 🐛 Řešení problémů

### Push notifikace nefungují
1. **Zkontroluj prohlížeč console:**
   ```javascript
   window.WGSNotifikace.isIOS
   window.WGSNotifikace.isPWA
   window.WGSNotifikace.iosSupportsWebPush
   ```

2. **Zkontroluj VAPID klíče:**
   - Otevři: https://www.wgs-service.cz/setup_web_push.php

3. **Zkontroluj databázi:**
   ```sql
   SELECT * FROM wgs_push_subscriptions WHERE aktivni = 1;
   ```

4. **Zkontroluj error log:**
   ```bash
   tail -f logs/php_errors.log
   ```

### Permission denied
- Uživatel musí povolit notifikace v nastavení prohlížeče
- Na iOS: Nastavení → Safari → Notifikace

### "VAPID klíče nejsou nakonfigurovány"
- Spusť: https://www.wgs-service.cz/setup_web_push.php
- Vygeneruj nové klíče

---

## 📞 Kontakt

Pokud máš problémy, kontaktuj:
- **Email:** radek@wgs-service.cz
- **GitHub Issues:** https://github.com/radecek222-boop/moje-stranky/issues
