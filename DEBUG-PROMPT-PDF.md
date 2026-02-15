# PROMPT PRO AI - DEBUGGING PDF PRICELIST

Prosím analyzuj tento problém a najdi řešení.

## PROBLÉM

Generuji PDF pomocí jsPDF 2.5.1 a mám **DVA problémy**:

### 1. České znaky se nezobrazují správně
- **Očekávám:** "Číslo reklamace", "Příplatek: Těžký nábytek"
- **Dostávám:** "íslo reklamace", "PYíplatek: T žký nábytek"

### 2. Chybí kompletní rozpis položek
- **Očekávám:** Dopravné + Čalounické práce (485€) + Materiál (50€) + Vyzvednutí (10€) + Příplatek (95€)
- **Dostávám:** Pouze Dopravné + Příplatek

---

## KÓD

### 1. Kalkulátor vytváří data (cenik-calculator.js, řádek 1468-1496)

```javascript
const kalkulaceData = {
    celkovaCena: 642.80,
    adresa: "Do Dubče 364, Praha, Česko",
    vzdalenost: 5,
    dopravne: 2.80,
    reklamaceBezDopravy: false,
    vyzvednutiSklad: true,
    typServisu: 'calouneni',
    rozpis: {
        diagnostika: 0,
        calouneni: {
            pocetProduktu: 1,
            sedaky: 1,
            operky: 2,
            podrucky: 1,
            panely: 1
        },
        mechanika: {
            relax: 0,
            vysuv: 0
        },
        doplnky: {
            tezkyNabytek: true,
            material: true,
            vyzvednutiSklad: true
        }
    }
};

// Zavolá:
window.protokolKalkulacka.zpracovatVysledek(kalkulaceData);
```

### 2. PDF Generator - Transformace dat (protokol.js, řádek 1715-1810)

```javascript
async function generatePricelistPDF() {
  if (!kalkulaceData) {
    return null;
  }

  logger.log('📊 DEBUG: kalkulaceData =', JSON.stringify(kalkulaceData, null, 2));

  // TRANSFORMACE: Převést rozpis do pole služeb a dílů
  if (kalkulaceData.rozpis && (!kalkulaceData.sluzby || !kalkulaceData.dilyPrace)) {
    logger.log('✅ Převádím rozpis data...');
    kalkulaceData.sluzby = [];
    kalkulaceData.dilyPrace = [];

    const rozpis = kalkulaceData.rozpis;
    const CENY = {
      prvniDil: 205,
      dalsiDil: 70,
      material: 50,
      vyzvednutiSklad: 10
    };

    // Čalounické práce
    if (rozpis.calouneni) {
      const { sedaky, operky, podrucky, panely } = rozpis.calouneni;
      const celkemDilu = (sedaky || 0) + (operky || 0) + (podrucky || 0) + (panely || 0);

      if (celkemDilu > 0) {
        const cenaDilu = CENY.prvniDil + (celkemDilu - 1) * CENY.dalsiDil;
        kalkulaceData.dilyPrace.push({
          nazev: `Čalounické práce (${celkemDilu} dílů)`,
          cena: cenaDilu,
          pocet: celkemDilu
        });
      }
    }

    // Doplňky
    if (rozpis.doplnky) {
      if (rozpis.doplnky.material) {
        kalkulaceData.sluzby.push({
          nazev: 'Materiál dodán od WGS',
          cena: CENY.material,
          pocet: 1
        });
      }
      if (rozpis.doplnky.vyzvednutiSklad) {
        kalkulaceData.sluzby.push({
          nazev: 'Vyzvednutí dílu na skladě',
          cena: CENY.vyzvednutiSklad,
          pocet: 1
        });
      }
    }
  }

  // PDF generování
  const { jsPDF } = window.jspdf;
  const pdf = new jsPDF("p", "mm", "a4");

  // Pokus o nastavení custom fontu
  try {
    if (window.vfs && window.vfs.Roboto_Regular_normal) {
      pdf.addFileToVFS("Roboto-Regular.ttf", window.vfs.Roboto_Regular_normal);
      pdf.addFont("Roboto-Regular.ttf", "Roboto", "normal");
      pdf.setFont("Roboto");
    } else {
      pdf.setFont("courier");
    }
  } catch (e) {
    pdf.setFont("courier");
  }

  // ... další kód ...
}
```

### 3. Vykreslení služeb do PDF (protokol.js, řádek 1860-1919)

```javascript
// Služby - DETAILNÍ ROZPIS
if (kalkulaceData.sluzby && kalkulaceData.sluzby.length > 0) {
  yPos += 3;
  pdf.setFont('helvetica', 'bold');
  pdfText('Služby:', margin, yPos);
  yPos += 7;

  pdf.setFont('helvetica', 'normal');
  kalkulaceData.sluzby.forEach(sluzba => {
    pdfText(`  ${sluzba.nazev}`, margin, yPos);
    yPos += 6;

    const cena = sluzba.cena.toFixed(2);
    pdfText(`${cena} EUR`, pageWidth - margin - 30, yPos - 6);
    yPos += 1;
  });
}

// Díly a práce - DETAILNÍ ROZPIS
if (kalkulaceData.dilyPrace && kalkulaceData.dilyPrace.length > 0) {
  yPos += 3;
  pdf.setFont('helvetica', 'bold');
  pdfText('Díly a práce:', margin, yPos);
  yPos += 7;

  pdf.setFont('helvetica', 'normal');
  kalkulaceData.dilyPrace.forEach(polozka => {
    pdfText(`  ${polozka.nazev}`, margin, yPos);
    yPos += 6;

    const detail = `    ${polozka.pocet} ks × ${polozka.cena.toFixed(2)} EUR`;
    pdfText(detail, margin + 5, yPos);
    yPos += 7;
  });
}
```

### 4. HTML - načítání custom fontů (protokol.php, řádek 886-888)

```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
<script src="https://unpkg.com/jspdf-customfonts@latest/dist/default_vfs.js" defer></script>
```

---

## OTÁZKY

1. **Proč se transformace nespustí?**
   - Podmínka `if (kalkulaceData.rozpis && (!kalkulaceData.sluzby || !kalkulaceData.dilyPrace))` možná není splněna
   - Možná `kalkulaceData.sluzby` už existuje jako prázdné pole `[]`?

2. **Proč nefungují české znaky?**
   - jsPDF 2.5.1 nepodporuje UTF-8 bez custom fontu
   - Je `window.vfs.Roboto_Regular_normal` dostupný?
   - Je CDN `unpkg.com/jspdf-customfonts` blokovaný?

3. **Alternativní řešení?**
   - Použít jsPDF `html()` metodu místo `text()`?
   - Upgradu na jsPDF 3.x?
   - Použít jiný font nebo encoding?

---

## CO POTŘEBUJI

1. **Opravit zobrazení českých znaků** (háčky, čárky)
2. **Zajistit aby se zobrazily VŠECHNY položky rozpisu** (čalounické práce, materiál, vyzvednutí)

---

## KONTEXT

- **jsPDF verze:** 2.5.1
- **Browser:** Chrome/Firefox/Safari (produkční web)
- **Custom fonts plugin:** `https://unpkg.com/jspdf-customfonts@latest/dist/default_vfs.js`
- **Jazyk:** Čeština (Č, č, Ř, ř, Ž, ž, Á, á, É, é, atd.)

---

## DEBUG INFO

Když otevřu browser console, měl bych vidět:
```
📊 DEBUG: kalkulaceData = { ... }
✅ Převádím rozpis data...
```

Pokud druhý log CHYBÍ - transformace se NESPUSTILA!

---

**PROSÍM NAJDI CHYBU A NAVRHNI ŘEŠENÍ.**
