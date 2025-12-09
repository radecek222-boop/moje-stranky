<?php
/**
 * Testovací stránka pro PDF export ceníku
 * Obsahuje vymyšlené údaje pro ověření českých znaků
 */
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test PDF Export - Ceník</title>

    <!-- Google Fonts - Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- PDF knihovny -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        body {
            background: #1a1a1a;
            color: #fff;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            border-bottom: 2px solid #fff;
            padding-bottom: 10px;
        }
        .test-info {
            background: #333;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .test-info h3 {
            margin-top: 0;
        }
        .test-info p {
            margin: 8px 0;
        }
        .btn-test {
            background: #fff;
            color: #000;
            border: none;
            padding: 15px 30px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 8px;
            margin: 10px 10px 10px 0;
        }
        .btn-test:hover {
            background: #ddd;
        }
        .status {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            display: none;
        }
        .status.success {
            background: #2d5016;
            display: block;
        }
        .status.error {
            background: #501616;
            display: block;
        }
        .czech-chars {
            background: #222;
            padding: 15px;
            border-radius: 8px;
            font-size: 20px;
            letter-spacing: 2px;
        }
    </style>
</head>
<body>
    <h1>Test PDF Export - Ceník kalkulačka</h1>

    <div class="test-info">
        <h3>Testovací data (obsahují české znaky):</h3>
        <p><strong>Adresa:</strong> Jiráskova 1234/5, Praha 5 - Smíchov, 150 00</p>
        <p><strong>Vzdálenost:</strong> 45 km</p>
        <p><strong>Typ servisu:</strong> Čalounické práce + Mechanika</p>
        <p><strong>Díly:</strong> 2x sedáky, 1x opěrky, 1x područky</p>
        <p><strong>Mechanismy:</strong> 1x relax, 1x výsuv</p>
        <p><strong>Příplatky:</strong> Těžký nábytek, Materiál od WGS</p>
    </div>

    <div class="test-info">
        <h3>České znaky k ověření v PDF:</h3>
        <p class="czech-chars">ě š č ř ž ý á í é ú ů ď ť ň</p>
        <p class="czech-chars">Ě Š Č Ř Ž Ý Á Í É Ú Ů Ď Ť Ň</p>
    </div>

    <button class="btn-test" onclick="generujTestPDF()">Generovat testovací PDF</button>
    <button class="btn-test" onclick="zobrazNahled()">Zobrazit náhled HTML</button>

    <div id="status" class="status"></div>
    <div id="nahled" style="margin-top: 20px;"></div>

    <script>
        // Simulovaný stav kalkulačky s českými znaky
        const testStav = {
            krok: 5,
            adresa: 'Jiráskova 1234/5, Praha 5 - Smíchov, 150 00',
            vzdalenost: 45,
            dopravne: 90, // 45km * 2€
            typServisu: 'kombinace',
            reklamaceBezDopravy: false,
            vyzvednutiSklad: false,
            sedaky: 2,
            operky: 1,
            podrucky: 1,
            panely: 0,
            relax: 1,
            vysuv: 1,
            tezkyNabytek: true,
            material: true
        };

        const CENY = {
            dopravaSazba: 2,
            diagnostika: 50,
            prvniDil: 190,
            dalsiDil: 100,
            zakladniSazba: 130,
            mechanismusPriplatek: 30,
            druhaOsoba: 30,
            material: 30,
            vyzvednutiSklad: 15
        };

        async function generujTestPDF() {
            const statusEl = document.getElementById('status');
            statusEl.className = 'status';
            statusEl.style.display = 'none';

            try {
                // Počkat na font
                if (document.fonts && document.fonts.ready) {
                    await document.fonts.ready;
                    await document.fonts.load('400 16px Poppins');
                    await document.fonts.load('700 16px Poppins');
                    console.log('Font Poppins načten');
                }

                // Vypočítat cenu
                let celkem = testStav.dopravne;

                // Čalounické práce
                const celkemDilu = testStav.sedaky + testStav.operky + testStav.podrucky + testStav.panely;
                if (celkemDilu > 0) {
                    celkem += celkemDilu === 1 ? CENY.prvniDil : CENY.prvniDil + (celkemDilu - 1) * CENY.dalsiDil;
                }

                // Mechanické práce
                const celkemMechanismu = testStav.relax + testStav.vysuv;
                if (celkemMechanismu > 0) {
                    celkem += celkemMechanismu * CENY.mechanismusPriplatek;
                }

                // Příplatky
                if (testStav.tezkyNabytek) celkem += CENY.druhaOsoba;
                if (testStav.material) celkem += CENY.material;

                // Vytvořit HTML
                const pdfContent = document.createElement('div');
                pdfContent.id = 'pdf-test-temp';
                pdfContent.style.cssText = `
                    width: 794px !important;
                    min-width: 794px !important;
                    max-width: 794px !important;
                    padding: 40px;
                    background: white;
                    font-family: 'Poppins', sans-serif;
                    position: fixed;
                    left: -9999px;
                    top: 0;
                    box-sizing: border-box;
                    color: #000;
                `;

                const datum = new Date().toLocaleDateString('cs-CZ');
                const cenaDilu = celkemDilu === 1 ? CENY.prvniDil : CENY.prvniDil + (celkemDilu - 1) * CENY.dalsiDil;
                const cenaMechanismu = celkemMechanismu * CENY.mechanismusPriplatek;

                // Styl pro anglické překlady
                const enStyle = 'font-size: 0.75em; color: #888; font-style: italic; display: block; margin-top: 2px;';

                pdfContent.innerHTML = `
                    <div style="text-align: center; margin-bottom: 20px;">
                        <h1 style="font-size: 28px; color: #4a4a4a; margin: 0 0 5px 0; font-weight: bold;">
                            KALKULACE CENY SERVISU
                        </h1>
                        <span style="${enStyle}">SERVICE PRICE CALCULATION</span>
                        <p style="font-size: 14px; color: #666; margin: 10px 0 0 0;">
                            Datum / Date: ${datum}
                        </p>
                    </div>

                    <hr style="border: none; border-top: 2px solid #4a4a4a; margin: 20px 0;">

                    <div style="margin: 20px 0;">
                        <h3 style="font-size: 16px; color: #2a2a2a; margin: 0 0 3px 0; font-weight: bold;">
                            Adresa zákazníka
                        </h3>
                        <span style="${enStyle}">Customer Address</span>
                        <p style="font-size: 14px; margin: 8px 0 5px 0;">${testStav.adresa}</p>
                        <p style="font-size: 14px; margin: 0;">Vzdálenost z dílny / Distance from workshop: ${testStav.vzdalenost} km</p>
                    </div>

                    <div style="margin: 30px 0; background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h3 style="font-size: 18px; color: #2a2a2a; margin: 0 0 3px 0; font-weight: bold;">
                            Specifikace zakázky
                        </h3>
                        <span style="${enStyle}">Order Specification</span>

                        <p style="font-size: 14px; margin: 15px 0 8px 0;">
                            <strong>Typ servisu / Service type:</strong><br>
                            Kombinace čalounění a mechaniky <span style="color: #888; font-style: italic;">/ Combination of Upholstery and Mechanical</span>
                        </p>

                        <p style="font-size: 14px; margin: 8px 0;"><strong>Čalounické práce</strong> <span style="color: #888; font-style: italic;">/ Upholstery Work</span></p>
                        <ul style="margin: 5px 0 5px 20px; font-size: 14px; line-height: 1.8;">
                            <li>Sedáky / Seat cushions: ${testStav.sedaky}×</li>
                            <li>Opěrky / Backrests: ${testStav.operky}×</li>
                            <li>Područky / Armrests: ${testStav.podrucky}×</li>
                        </ul>

                        <p style="font-size: 14px; margin: 8px 0;"><strong>Mechanické práce</strong> <span style="color: #888; font-style: italic;">/ Mechanical Work</span></p>
                        <ul style="margin: 5px 0 5px 20px; font-size: 14px; line-height: 1.8;">
                            <li>Relax mechanismy / Recliner mechanisms: ${testStav.relax}×</li>
                            <li>Elektrické díly / Electric parts: ${testStav.vysuv}×</li>
                        </ul>

                        <p style="font-size: 14px; margin: 8px 0;"><strong>Doplňkové služby</strong> <span style="color: #888; font-style: italic;">/ Additional Services</span></p>
                        <ul style="margin: 5px 0 5px 20px; font-size: 14px; line-height: 1.8;">
                            <li>Druhá osoba / Second person (>50kg): Ano / Yes</li>
                            <li>Materiál od WGS / Material from WGS: Ano / Yes</li>
                        </ul>
                    </div>

                    <div style="margin: 30px 0; background: #f0f0f0; padding: 20px; border-radius: 8px;">
                        <h3 style="font-size: 18px; color: #2a2a2a; margin: 0 0 3px 0; font-weight: bold;">
                            Cenová kalkulace
                        </h3>
                        <span style="${enStyle}">Price Calculation</span>

                        <table style="width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 15px;">
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 8px 0;">Dopravné / Transport (${testStav.vzdalenost} km × 2€)</td>
                                <td style="padding: 8px 0; text-align: right; font-weight: bold;">${testStav.dopravne.toFixed(2)} €</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 8px 0;">Čalounické práce / Upholstery (${celkemDilu} dílů / parts)</td>
                                <td style="padding: 8px 0; text-align: right; font-weight: bold;">${cenaDilu.toFixed(2)} €</td>
                            </tr>
                            <tr>
                                <td colspan="2" style="padding: 4px 0 8px 20px; font-size: 12px; color: #666;">
                                    ↳ První díl / First part: ${CENY.prvniDil}€, další / additional: ${celkemDilu - 1}× ${CENY.dalsiDil}€
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 8px 0;">Mechanické části / Mechanical parts (${celkemMechanismu}× mechanism)</td>
                                <td style="padding: 8px 0; text-align: right; font-weight: bold;">${cenaMechanismu.toFixed(2)} €</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 8px 0;">Druhá osoba / Second person (>50kg)</td>
                                <td style="padding: 8px 0; text-align: right; font-weight: bold;">${CENY.druhaOsoba.toFixed(2)} €</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 8px 0;">Materiál od WGS / Material from WGS</td>
                                <td style="padding: 8px 0; text-align: right; font-weight: bold;">${CENY.material.toFixed(2)} €</td>
                            </tr>
                            <tr style="border-top: 3px solid #4a4a4a;">
                                <td style="padding: 15px 0; font-size: 18px; font-weight: bold;">CELKOVÁ CENA / TOTAL PRICE</td>
                                <td style="padding: 15px 0; text-align: right; font-size: 18px; font-weight: bold; color: #2a2a2a;">
                                    ${celkem.toFixed(2)} €
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div style="background: #f5f5f5; border-left: 4px solid #666; padding: 15px; margin: 30px 0; font-size: 12px; color: #666;">
                        Ceny jsou uvedeny bez DPH. / Prices are excluding VAT.
                    </div>

                    <div style="text-align: center; margin-top: 50px; font-size: 11px; color: #999;">
                        <p style="margin: 5px 0;"><strong>White Glove Service s.r.o.</strong> | www.wgs-service.cz</p>
                        <p style="margin: 5px 0;">Do Dubče 364, Běchovice 190 11 | Tel: +420 725 965 826</p>
                        <p style="margin: 5px 0;">Vygenerováno / Generated: ${new Date().toLocaleString('cs-CZ')}</p>
                    </div>
                `;

                document.body.appendChild(pdfContent);
                await new Promise(resolve => setTimeout(resolve, 100));

                // Generovat canvas
                const canvas = await html2canvas(pdfContent, {
                    scale: 3,
                    backgroundColor: '#ffffff',
                    useCORS: true,
                    logging: true,
                    imageTimeout: 0,
                    allowTaint: true,
                    letterRendering: true
                });

                // Generovat PDF
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');

                const imgData = canvas.toDataURL('image/jpeg', 0.98);

                const pageWidth = 210;
                const pageHeight = 297;
                const margin = 10;
                const availableWidth = pageWidth - (margin * 2);
                const availableHeight = pageHeight - (margin * 2);
                const canvasRatio = canvas.height / canvas.width;

                let imgWidth = availableWidth;
                let imgHeight = imgWidth * canvasRatio;

                if (imgHeight > availableHeight) {
                    imgHeight = availableHeight;
                    imgWidth = imgHeight / canvasRatio;
                }

                const xOffset = (pageWidth - imgWidth) / 2;
                const yOffset = margin;

                doc.addImage(imgData, 'JPEG', xOffset, yOffset, imgWidth, imgHeight);

                // Odstranit dočasný element
                document.body.removeChild(pdfContent);

                // Stáhnout PDF
                doc.save('test-kalkulace-ceske-znaky.pdf');

                statusEl.className = 'status success';
                statusEl.innerHTML = '<strong>PDF vygenerováno!</strong><br>Zkontrolujte stažený soubor - české znaky by měly být správně: ěščřžýáíéúů';
                statusEl.style.display = 'block';

            } catch (error) {
                console.error('Chyba:', error);
                statusEl.className = 'status error';
                statusEl.innerHTML = '<strong>Chyba:</strong> ' + error.message;
                statusEl.style.display = 'block';
            }
        }

        function zobrazNahled() {
            const nahledEl = document.getElementById('nahled');

            const datum = new Date().toLocaleDateString('cs-CZ');

            nahledEl.innerHTML = `
                <h3>Náhled HTML (jak bude vypadat v PDF):</h3>
                <div style="background: white; color: black; padding: 40px; font-family: 'Poppins', sans-serif; border-radius: 8px;">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <h1 style="font-size: 24px; color: #4a4a4a; margin: 0 0 10px 0;">KALKULACE CENY SERVISU</h1>
                        <p style="font-size: 14px; color: #666;">Datum: ${datum}</p>
                    </div>
                    <hr>
                    <p><strong>Adresa:</strong> Jiráskova 1234/5, Praha 5 - Smíchov, 150 00</p>
                    <p><strong>Vzdálenost:</strong> 45 km</p>
                    <hr>
                    <h4>České znaky v textu:</h4>
                    <ul>
                        <li>Čalounické práce - sedáky, opěrky, područky</li>
                        <li>Těžký nábytek (>50kg)</li>
                        <li>Relax mechanismy, výsuv</li>
                        <li>Příplatek za materiál</li>
                        <li>Vzdálenost z dílny</li>
                    </ul>
                    <h4>Všechny české znaky:</h4>
                    <p style="font-size: 20px; letter-spacing: 3px;">ě š č ř ž ý á í é ú ů ď ť ň ó</p>
                    <p style="font-size: 20px; letter-spacing: 3px;">Ě Š Č Ř Ž Ý Á Í É Ú Ů Ď Ť Ň Ó</p>
                </div>
            `;
        }
    </script>
</body>
</html>
