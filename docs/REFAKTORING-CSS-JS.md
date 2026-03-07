# TODO: Refaktoring CSS a JS — Jeden mozek pro CSS, jeden pro JS

**Vytvořeno:** 2026-03-07
**Projekt:** WGS Service (seznam.php a okolní stránky)
**Cíl:** Odstranit konflikty mezi CSS a JS soubory, zavést jasnou hierarchii a pravidla pro úpravy

---

## PROČ TO DĚLÁME

Aktuální stav je nebezpečný:
- `seznam.php` má **2037 řádků inline CSS** přímo v HTML
- **733 výskytů `!important`** jen v tom jednom souboru
- Pravidla pro `#detailOverlay` jsou rozeseta v **5 různých CSS souborech**
- Logika modalu je rozdělena do **4 různých JS souborů**
- Při ladění iOS scrollu nebylo jasné, které pravidlo přebíjí které
- Oprava v jednom souboru je okamžitě potlačena pravidlem z jiného

---

## PŘEHLED: CO EXISTUJE DNES

### CSS soubory načítané na seznam.php

| Soubor | `!important` | Poznámka |
|--------|-------------|----------|
| `styles.min.css` | ? | Globální základní styly, 166 CSS proměnných |
| `seznam.min.css` | 55 | Stránka-specifické styly, má pravidla pro #detailOverlay |
| `button-fixes-global.min.css` | ? | Opravy tlačítek |
| `mobile-responsive.min.css` | 35 | Responzivní styly, má pravidla pro #detailOverlay |
| `admin-header.min.css` | ? | Header admin |
| `universal-modal-theme.min.css` | 197 | Modal téma, má pravidla pro #detailOverlay |
| **seznam.php `<style>` inline** | **733** | **2037 řádků — největší problém** |

### JS soubory starající se o modal

| Soubor | Co dělá s modalem |
|--------|-------------------|
| `hamburger-menu.php` | Alpine.js `detailModal` — `openModal()`, `closeModal()` |
| `seznam.js` | `ModalManager.show()` — naplní obsah, zavolá openModal, iOS fix |
| `pull-to-refresh.js` | touch eventy na overlay — `touchstart`, `touchmove`, `touchend` |
| `scroll-lock.js/.min.js` | Zamknutí/odemknutí body scrollu |

### Kde jsou pravidla pro `#detailOverlay` (modal)

```
seznam.css / seznam.min.css         — #detailOverlay základní display, flex
mobile-responsive.css / .min.css    — #detailOverlay pro mobily
universal-modal-theme.css / .min.css — #detailOverlay téma, barvy, animace
seznam.php inline <style>           — přepis všeho výše pomocí !important
hamburger-menu.php inline <script>  — přepis CSS pomocí JS style.setProperty
seznam.js                           — přepis CSS pomocí JS style.setProperty (druhý pokus)
```

---

## FÁZE 1: PŘÍPRAVA — než se cokoliv mění

### 1.1 Zmapovat všechna CSS pravidla pro modal
- [ ] Vyhledat všechna pravidla `#detailOverlay` ve všech CSS souborech
- [ ] Vyhledat všechna pravidla `.modal-content` ve všech CSS souborech
- [ ] Vyhledat všechna pravidla `.modal-header`, `.modal-body`, `.modal-footer`
- [ ] Zapsat do tabulky: soubor → pravidlo → hodnota → důvod existence
- [ ] Zjistit, která pravidla jsou duplicitní (stejná vlastnost na stejném selektoru)

### 1.2 Zmapovat všechna JS místa, která mění styly modalu
- [ ] Vyhledat `style.setProperty` v celém projektu
- [ ] Vyhledat `style.overflow`, `style.position`, `style.display` v celém projektu
- [ ] Vyhledat `classList.add`, `classList.remove` na overlay/modal prvcích
- [ ] Zapsat do tabulky: soubor → řádek → co mění → proč

### 1.3 Vytvořit zálohu před refaktoringem
- [ ] `git checkout -b refaktoring-css-js-backup` — záložní větev
- [ ] Commitnout aktuální stav (i s chybami) jako baseline

---

## FÁZE 2: PRAVIDLA — co platí od teď

### CSS pravidla

```
PRAVIDLO 1: Jeden soubor = jedna odpovědnost
  styles.css       → pouze globální reset, typografie, CSS proměnné
  seznam.css       → pouze layout stránky seznam.php (NE modal)
  modal-detail.css → VEŠKERÝ modal CSS (nový soubor — viz Fáze 3)
  mobile-responsive.css → pouze responzivní layout (NE pravidla specifická pro modal)

PRAVIDLO 2: !important je zakázán kromě těchto výjimek:
  - scrollLock pravidla (body.scroll-locked)
  - print styly
  - přístupnostní styly (prefers-reduced-motion)
  Vše ostatní řeší specificitou selektoru, ne !important

PRAVIDLO 3: JS nesmí nastavovat inline styly pro design
  - JS smí nastavit: scrollTop, transform (animace), display:none/block pro show/hide
  - JS NESMÍ nastavit: overflow, position, border-radius, margin, padding
  - Tyto vlastnosti patří do CSS tříd (JS přidá/odebere třídu)

PRAVIDLO 4: Zdrojový soubor = pravda, .min.js je vždy generován
  - Editovat vždy .js nebo .css (ne .min.js nebo .min.css)
  - .min soubory se generují příkazem (viz Fáze 6)
  - V HTML načítat .min verze (nebo soubor s ?v= cache-buster)
```

### JS pravidla

```
PRAVIDLO 5: Jeden soubor = jedna odpovědnost
  scroll-lock.js    → pouze zamykání body scrollu (NE otevírání modalu)
  modal-detail.js   → veškerá logika otevření/zavření/scrollu modalu (nový soubor)
  seznam.js         → pouze seznam reklamací (NE modal logika)

PRAVIDLO 6: Žádná duplicitní logika
  openModal() existuje JEDNOU (v modal-detail.js)
  closeModal() existuje JEDNOU (v modal-detail.js)
  iOS detekce existuje JEDNOU (v modal-detail.js nebo utils.js)

PRAVIDLO 7: Alpine.js detailModal = jen datová vrstva
  Alpine.js spravuje: isOpen (boolean), activeId, data
  Alpine.js NEVOLÁ: style.setProperty, scroll logiku
  To vše patří do modal-detail.js
```

---

## FÁZE 3: NOVÝ SOUBOR — modal-detail.css

### 3.1 Vytvořit `/assets/css/modal-detail.css`
- [ ] Přesunout SEM všechna pravidla pro `#detailOverlay` z těchto souborů:
  - ze `seznam.css` (a .min)
  - z `mobile-responsive.css` (a .min)
  - z `universal-modal-theme.css` (a .min)
  - z inline `<style>` v seznam.php

- [ ] Struktura souboru:
```css
/* === MODAL DETAIL — modal-detail.css === */
/* Verze: 1.0.0 | Editovat POUZE tento soubor pro modal styly */

/* 1. Overlay (pozadí) */
#detailOverlay { ... }
#detailOverlay.active { ... }

/* 2. Modal container */
#detailOverlay .modal-content { ... }

/* 3. Header */
#detailOverlay .modal-header { ... }

/* 4. Body (scrollovatelná část) */
#detailOverlay .modal-body { ... }

/* 5. Textareas */
#detailOverlay textarea { ... }

/* 6. Footer / tlačítka */
#detailOverlay .modal-footer { ... }

/* 7. Responzivní — mobile first */
@media (max-width: 768px) {
  #detailOverlay .modal-content { ... }
}

/* 8. iOS specifické — POUZE zde, nikde jinde */
@media (max-width: 768px) {
  body.ios-device #detailOverlay .modal-content {
    /* iOS scroll pravidla */
  }
}
```

- [ ] Po přesunu smazat pravidla pro `#detailOverlay` z ostatních souborů
- [ ] Přidat `modal-detail.css` do HTML (seznam.php, případně další stránky)
- [ ] Smazat `universal-modal-theme.css/.min.css` — pokud po přesunu pravidel je prázdný

### 3.2 iOS třídy místo JS inline stylů
- [ ] Přidat CSS třídy:
```css
/* V modal-detail.css */
body.ios-device { /* iOS specifický reset */ }
#detailOverlay.ios-fullscreen .modal-content {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  overflow-y: scroll;
  -webkit-overflow-scrolling: touch;
}
```
- [ ] JS pak jen přidá třídy: `body.classList.add('ios-device')` a `overlay.classList.add('ios-fullscreen')`
- [ ] Smazat všechny `style.setProperty(...)` volání pro modal layout

---

## FÁZE 4: NOVÝ SOUBOR — modal-detail.js

### 4.1 Vytvořit `/assets/js/modal-detail.js`
- [ ] Přesunout SEM veškerou logiku modalu:
  - `openModal()` z `hamburger-menu.php` (Alpine detailModal)
  - `closeModal()` z `hamburger-menu.php`
  - iOS fix ze `seznam.js` (ModalManager.show setTimeout blok)
  - touch event handling z `pull-to-refresh.js` (jen část pro modal)

- [ ] Struktura souboru:
```javascript
/**
 * modal-detail.js — Správa detailního modalu reklamace
 * Verze: 1.0.0
 *
 * VEŠKERÁ logika otevření/zavření/scroll modalu patří SEM.
 * Žádný jiný soubor nesmí volat style.setProperty na #detailOverlay.
 */

const ModalDetail = (function() {
  'use strict';

  // Privátní stav
  let jeOtevreny = false;
  let ulozenyScroll = 0;
  const jeIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
  const jeMobil = () => window.innerWidth < 769;

  function otevrit(callback) {
    // 1. Scroll lock
    // 2. iOS třída na body
    // 3. Přidání třídy 'active' na overlay
    // 4. callback po animaci
  }

  function zavrit() {
    // 1. Odemknout scroll
    // 2. Odebrat iOS třídu
    // 3. Odebrat 'active' z overlay
  }

  function nastavitObsah(html) {
    // Nastavit innerHTML .modal-content
    // Zaregistrovat event listenery pro textareas
  }

  // Veřejné API
  return { otevrit, zavrit, nastavitObsah };
})();

window.ModalDetail = ModalDetail;
```

### 4.2 Alpine.js detailModal = tenká vrstva
- [ ] `hamburger-menu.php` Alpine komponenta bude jen:
```javascript
Alpine.data('detailModal', () => ({
  jeOtevreno: false,

  otevritModal(id) {
    this.jeOtevreno = true;
    ModalDetail.otevrit(); // delegovat na modal-detail.js
  },

  zavritModal() {
    this.jeOtevreno = false;
    ModalDetail.zavrit(); // delegovat na modal-detail.js
  }
}));
```

### 4.3 ModalManager v seznam.js = jen data
- [ ] `ModalManager.show()` bude jen:
```javascript
const ModalManager = {
  show(data) {
    const html = this.sestavitHtml(data); // generovat HTML
    ModalDetail.nastavitObsah(html);      // předat modal-detail.js
    ModalDetail.otevrit();                // modal-detail.js to otevře
  }
};
```

---

## FÁZE 5: SEZNAM.PHP INLINE STYLY — 2037 řádků pryč

### 5.1 Kategorizovat inline styly
- [ ] Projít celý `<style>` blok v seznam.php (2037 řádků)
- [ ] Každé pravidlo zařadit do kategorie:
  - `A` = globální (patří do styles.css)
  - `B` = stránka-specifické (patří do seznam.css)
  - `C` = modal pravidla (patří do modal-detail.css)
  - `D` = responzivní (patří do mobile-responsive.css)
  - `E` = dočasné opravy/hacky (smazat nebo opravit správně)

### 5.2 Přesunout kategorie
- [ ] Přesunout kategorie A do `styles.css`
- [ ] Přesunout kategorie B do `seznam.css`
- [ ] Přesunout kategorie C do `modal-detail.css`
- [ ] Přesunout kategorie D do `mobile-responsive.css`
- [ ] Kategorie E — opravit nebo smazat (ne přesunout)

### 5.3 Smazat inline `<style>` blok
- [ ] Po přesunu všech pravidel smazat celý `<style>` blok ze seznam.php
- [ ] Testovat, že nic nechybí

---

## FÁZE 6: MINIFIKACE — pravidla pro .min soubory

### 6.1 Zavést konzistentní postup
- [ ] Vybrat nástroj pro minifikaci (doporučení: `cleancss` pro CSS, `terser` pro JS)
- [ ] Zapsat příkazy do README nebo Makefile:
```bash
# CSS minifikace
npx cleancss -o assets/css/modal-detail.min.css assets/css/modal-detail.css
npx cleancss -o assets/css/seznam.min.css assets/css/seznam.css
npx cleancss -o assets/css/mobile-responsive.min.css assets/css/mobile-responsive.css

# JS minifikace
npx terser assets/js/modal-detail.js -o assets/js/modal-detail.min.js
npx terser assets/js/seznam.js -o assets/js/seznam.min.js
```

### 6.2 PRAVIDLO: nikdy ručně neupravovat .min soubory
- [ ] Přidat komentář do každého .min.css a .min.js souboru (na první řádek):
```
/* GENEROVANÝ SOUBOR — neupravovat ručně. Zdroj: assets/css/[nazev].css */
```
- [ ] Přidat `.min.js` a `.min.css` do `.gitignore`? Nebo ponechat v gitu?
  - **Doporučení:** ponechat v gitu (server nemusí mít node), ale přidat varování do CONTRIBUTING.md

---

## FÁZE 7: TESTOVÁNÍ

### 7.1 Funkční testy po každé fázi
- [ ] Desktop Chrome: otevřít modal, scrollovat, zavřít
- [ ] Desktop Firefox: stejné
- [ ] Android Chrome: otevřít modal, scrollovat, pinch-zoom
- [ ] iOS Safari PWA: otevřít modal, scrollovat celý obsah, pinch-zoom v textarea

### 7.2 Regresní kontrola
- [ ] Žádný JS konzole error
- [ ] Scroll lock funguje (body se nepohybuje při otevřeném modalu)
- [ ] Po zavření modalu scroll pozice obnovena
- [ ] Protokol modal (jiná stránka) stále funguje
- [ ] Hamburger menu stále funguje

---

## PRIORITNÍ POŘADÍ

```
1. [KRITICKÉ]  Fáze 3.1 — Vytvořit modal-detail.css (opravuje iOS scroll konflikt)
2. [KRITICKÉ]  Fáze 3.2 — iOS třídy místo JS inline stylů
3. [VYSOKÁ]    Fáze 4.1 — Vytvořit modal-detail.js
4. [VYSOKÁ]    Fáze 5   — Přesunout inline styly ze seznam.php
5. [STŘEDNÍ]   Fáze 4.2 — Ztenčit Alpine detailModal
6. [STŘEDNÍ]   Fáze 4.3 — Ztenčit ModalManager
7. [NÍZKÁ]     Fáze 6   — Minifikační pravidla
8. [PRŮBĚŽNĚ]  Fáze 1   — Mapování (dělat průběžně před každou fází)
```

---

## ODHADOVANÝ VÝSLEDEK

| Metrika | Před | Po |
|---------|------|-----|
| Inline CSS v seznam.php | 2037 řádků | 0 řádků |
| `!important` celkem | 1020+ | < 20 |
| CSS soubory s modal pravidly | 5 | 1 (modal-detail.css) |
| JS soubory s modal logikou | 4 | 1 (modal-detail.js) |
| Míst s `style.setProperty` pro modal | 2 | 0 |
| Jasnost "co kde hledat" | nízká | vysoká |

---

## POZNÁMKY K IMPLEMENTACI

- **Nedělat vše najednou** — každá fáze musí být otestována před další
- **Fáze 1 (mapování) je povinná** před jakoukoliv změnou — jinak nevíme co přesouváme
- **iOS fix (scroll v modalu)** bude automaticky vyřešen Fází 3 — čisté CSS třídy bez !important konfliktů
- **Nemazat soubory** dokud není 100% ověřeno, že pravidla jsou přesunuta a fungují

---

**Tento dokument aktualizovat** při každé splněné položce — odškrtnout `[x]` a přidat datum.
