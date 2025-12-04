# E2E Test Checklist

Manuální testovací scénáře pro WGS Service aplikaci.

**Step 156** - Master Prompt System Phase 10

---

## 1. Autentizace

### 1.1 Login
- [ ] Přihlášení s validními údaji funguje
- [ ] Nesprávné heslo zobrazí chybovou hlášku
- [ ] Neexistující uživatel zobrazí chybovou hlášku
- [ ] Po úspěšném přihlášení redirect na požadovanou stránku
- [ ] Session timeout po 30 minutách nečinnosti
- [ ] "Zapamatuj si mě" funguje správně

### 1.2 Logout
- [ ] Odhlášení vyčistí session
- [ ] Po odhlášení nelze přistoupit k chráněným stránkám
- [ ] Redirect na login page po odhlášení

### 1.3 Registrace
- [ ] Registrace s platným klíčem funguje
- [ ] Neplatný registrační klíč je odmítnut
- [ ] Validace emailu funguje
- [ ] Heslo má minimální požadavky (8 znaků)
- [ ] Potvrzovací email je odeslán

---

## 2. Reklamace (Stížnosti)

### 2.1 Nová reklamace
- [ ] Formulář se správně načte
- [ ] Všechna povinná pole jsou validována
- [ ] Fotografie lze nahrát (max 5MB, JPG/PNG)
- [ ] Adresa se autocomplete z mapy
- [ ] Po uložení redirect na detail
- [ ] Email notifikace je odeslána

### 2.2 Seznam reklamací
- [ ] Seznam se správně načte
- [ ] Filtrování podle stavu funguje (ČEKÁ/DOMLUVENÁ/HOTOVO)
- [ ] Vyhledávání funguje (jméno, email, telefon)
- [ ] Řazení funguje (datum, stav)
- [ ] Paginace funguje správně
- [ ] Klik na řádek otevře detail

### 2.3 Detail reklamace
- [ ] Všechna data se správně zobrazí
- [ ] Editace polí funguje
- [ ] Změna stavu funguje
- [ ] Poznámky lze přidat/editovat/smazat
- [ ] Fotografie se správně zobrazí
- [ ] Historie změn je viditelná

### 2.4 Protokol
- [ ] Generování PDF funguje
- [ ] PDF obsahuje všechna data
- [ ] Sdílení/stažení PDF funguje
- [ ] Odeslání zákazníkovi funguje
- [ ] Kalkulačka se správně integruje

---

## 3. Administrace

### 3.1 Control Center
- [ ] Dashboard se správně načte
- [ ] Statistiky jsou aktuální
- [ ] Grafy se vykreslí správně

### 3.2 Správa uživatelů
- [ ] Seznam uživatelů se zobrazí
- [ ] Vytvoření uživatele funguje
- [ ] Editace uživatele funguje
- [ ] Deaktivace uživatele funguje
- [ ] Změna role funguje

### 3.3 Registrační klíče
- [ ] Generování nového klíče funguje
- [ ] Deaktivace klíče funguje
- [ ] Počítadlo použití se aktualizuje

### 3.4 Systémová nastavení
- [ ] Theme nastavení funguje
- [ ] SMTP konfigurace funguje
- [ ] Backup databáze funguje
- [ ] Export dat funguje

---

## 4. Ceník

### 4.1 Zobrazení ceníku
- [ ] Ceník se správně načte
- [ ] Kategorie jsou přeloženy (CZ/EN/IT)
- [ ] Ceny se správně zobrazují
- [ ] Vyhledávání funguje
- [ ] Filtrování podle kategorie funguje

### 4.2 Kalkulačka
- [ ] Výběr služeb funguje
- [ ] Výpočet vzdálenosti funguje
- [ ] Celková cena se správně počítá
- [ ] PDF cenové nabídky se generuje

### 4.3 Jazykové přepínání
- [ ] 🇨🇿 Čeština funguje
- [ ] 🇬🇧 English funguje
- [ ] 🇮🇹 Italiano funguje
- [ ] Preference jazyka se ukládá

---

## 5. Analytics

### 5.1 Dashboard
- [ ] Statistiky návštěv se zobrazí
- [ ] Grafy se vykreslí správně
- [ ] Filtry období fungují
- [ ] Export dat funguje

### 5.2 Heatmapy
- [ ] Heatmapa kliknutí se zobrazí
- [ ] Scroll heatmapa funguje
- [ ] Filtrování podle stránky funguje

### 5.3 Session Replay
- [ ] Seznam sessions se zobrazí
- [ ] Přehrání session funguje
- [ ] Filtry fungují

---

## 6. Bezpečnost

### 6.1 CSRF
- [ ] Formuláře mají CSRF token
- [ ] Neplatný token je odmítnut
- [ ] Token se regeneruje při novém přihlášení

### 6.2 XSS
- [ ] HTML je escapován ve výstupech
- [ ] JavaScript není spuštěn z user inputu
- [ ] URL parametry jsou sanitizovány

### 6.3 SQL Injection
- [ ] Prepared statements jsou použity
- [ ] Speciální znaky neproniknou do SQL

### 6.4 Rate Limiting
- [ ] Přihlášení je omezeno (5 pokusů/15min)
- [ ] API volání jsou omezena
- [ ] Blokace IP po překročení limitu

---

## 7. Responzivita

### 7.1 Desktop (1920x1080)
- [ ] Všechny stránky se správně zobrazují
- [ ] Navigace funguje
- [ ] Modaly se správně pozicují

### 7.2 Tablet (768x1024)
- [ ] Layout se přizpůsobí
- [ ] Touch gesta fungují
- [ ] Sidebar se skryje/zobrazí

### 7.3 Mobile (375x812)
- [ ] Layout je použitelný
- [ ] Navigace je přístupná
- [ ] Formuláře jsou vyplnitelné
- [ ] PDF se dá zobrazit/stáhnout

---

## 8. Přístupnost (A11y)

### 8.1 Klávesnice
- [ ] Tab navigace funguje správně
- [ ] Focus indikátor je viditelný
- [ ] Escape zavírá modaly
- [ ] Enter potvrzuje akce

### 8.2 Screen Reader
- [ ] ARIA labels jsou přítomny
- [ ] Formuláře mají labels
- [ ] Chybové hlášky jsou přečitatelné
- [ ] Modaly mají správné role

### 8.3 Kontrast
- [ ] Text má dostatečný kontrast
- [ ] Tlačítka jsou čitelná
- [ ] Chybové stavy jsou zřetelné

---

## 9. Performance

### 9.1 Načítání
- [ ] Hlavní stránky se načtou do 3s
- [ ] API volání odpovídají do 1s
- [ ] Obrázky se lazy loadují
- [ ] CSS/JS jsou minifikovány

### 9.2 Cache
- [ ] Statické soubory jsou cachovány
- [ ] API responses mají správné cache headers
- [ ] Service worker funguje offline

---

## 10. Notifikace

### 10.1 Email
- [ ] Registrační email přijde
- [ ] Notifikace o nové reklamaci přijde
- [ ] Protokol se odešle zákazníkovi
- [ ] Email queue zpracovává frontu

### 10.2 Toast notifikace
- [ ] Success toast se zobrazí
- [ ] Error toast se zobrazí
- [ ] Warning toast se zobrazí
- [ ] Info toast se zobrazí
- [ ] Toasty automaticky zmizí

### 10.3 Confirm dialogy
- [ ] wgsConfirm funguje
- [ ] Escape zavírá dialog
- [ ] Enter potvrzuje
- [ ] Overlay click zavírá

---

## Postup testování

1. **Před testováním:**
   - Vyčistit browser cache
   - Odhlásit se ze všech sessions
   - Zkontrolovat že běží na HTTPS

2. **Během testování:**
   - Zaznamenat všechny chyby
   - Screenshot problémů
   - Poznamenat kroky k reprodukci

3. **Po testování:**
   - Vytvořit issue pro nalezené bugy
   - Aktualizovat tento checklist
   - Informovat vývojový tým

---

**Poslední aktualizace:** 2025-12-02
**Verze:** 1.0.0
