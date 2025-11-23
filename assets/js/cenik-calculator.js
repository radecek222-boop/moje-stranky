/**
 * Kalkulačka ceny - Ceník služeb (WIZARD)
 * @version 2.0.0
 */

(function() {
    'use strict';

    // ========================================
    // GLOBÁLNÍ PROMĚNNÉ
    // ========================================
    const WORKSHOP_COORDS = { lat: 50.056725, lon: 14.577261 }; // Běchovice
    const TRANSPORT_RATE = 0.28; // €/km

    // Ceník služeb
    const CENY = {
        diagnostika: 155,
        prvniDil: 190,
        dalsiDil: 70,
        rohovyDil: 330,
        ottoman: 260,
        mechanismus: 70, // relax nebo výsuv
        druhaOsoba: 40,
        material: 40
    };

    // Stav kalkulačky
    let stav = {
        krok: 1,
        adresa: null,
        vzdalenost: 0,
        dopravne: 0,
        typServisu: 'calouneni', // diagnostika, calouneni, mechanika, kombinace

        // Čalounické práce
        sedaky: 0,
        operky: 0,
        podrucky: 0,
        panely: 0,
        rohovyDil: false,
        ottoman: false,

        // Mechanické práce
        relax: 0,
        vysuv: 0,

        // Další
        tezkyNabytek: false,
        material: false
    };

    // ========================================
    // INIT KALKULAČKY
    // ========================================
    window.addEventListener('DOMContentLoaded', () => {
        initKalkulacka();
    });

    function initKalkulacka() {
        console.log('[Kalkulačka] Inicializace wizardu');
        initAddressAutocomplete();
        initEventListeners();
        aktualizovatProgress();
    }

    function initEventListeners() {
        // Radio buttony pro typ servisu
        document.querySelectorAll('input[name="service-type"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                stav.typServisu = e.target.value;
            });
        });

        // Checkboxy
        const checkboxy = ['rohovy-dil', 'ottoman', 'tezky-nabytek', 'material'];
        checkboxy.forEach(id => {
            const checkbox = document.getElementById(id);
            if (checkbox) {
                checkbox.addEventListener('change', (e) => {
                    const key = id.replace(/-/g, '');
                    stav[key === 'rohovydil' ? 'rohovyDil' :
                          key === 'tezkynabytek' ? 'tezkyNabytek' : key] = e.target.checked;

                    // Aktualizovat souhrn dílů
                    if (id === 'rohovy-dil' || id === 'ottoman') {
                        aktualizovatSouhrnDilu();
                    }
                });
            }
        });

        // Countery - live update souhrnu
        ['sedaky', 'operky', 'podrucky', 'panely', 'relax', 'vysuv'].forEach(id => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('change', () => {
                    stav[id] = parseInt(input.value) || 0;
                    aktualizovatSouhrnDilu();
                });
            }
        });
    }

    // ========================================
    // AUTOCOMPLETE ADRES
    // ========================================
    function initAddressAutocomplete() {
        const input = document.getElementById('calc-address');
        const dropdown = document.getElementById('address-suggestions');

        if (!input) return;

        let debounceTimer;

        input.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            const query = e.target.value.trim();

            if (query.length < 3) {
                dropdown.style.display = 'none';
                return;
            }

            debounceTimer = setTimeout(() => hledatAdresy(query, dropdown), 300);
        });
    }

    async function hledatAdresy(query, dropdown) {
        try {
            const data = await WGSMap.autocomplete(query, {
                type: 'street',
                limit: 5,
                country: 'CZ,SK'
            });

            if (data && data.features && data.features.length > 0) {
                zobrazitNavrhy(data.features, dropdown);
            } else {
                dropdown.style.display = 'none';
            }
        } catch (error) {
            console.error('[Kalkulačka] Chyba autocomplete:', error);
            dropdown.style.display = 'none';
        }
    }

    function zobrazitNavrhy(features, dropdown) {
        dropdown.innerHTML = '';

        features.slice(0, 5).forEach(feature => {
            const item = document.createElement('div');
            item.className = 'suggestion-item';
            item.textContent = feature.properties.formatted || feature.properties.address_line1 || 'Neznámá adresa';

            item.addEventListener('click', () => {
                const coords = feature.geometry.coordinates;
                vybratAdresu(feature.properties.formatted || feature.properties.address_line1, coords[1], coords[0]);
                dropdown.style.display = 'none';
            });

            dropdown.appendChild(item);
        });

        dropdown.style.display = 'block';
    }

    // ========================================
    // VÝBĚR ADRESY & VÝPOČET VZDÁLENOSTI
    // ========================================
    async function vybratAdresu(formatted, lat, lon) {
        document.getElementById('calc-address').value = formatted;
        stav.adresa = formatted;

        try {
            const url = '/api/geocode_proxy.php?action=route&start_lat=' + WORKSHOP_COORDS.lat +
                        '&start_lon=' + WORKSHOP_COORDS.lon + '&end_lat=' + lat + '&end_lon=' + lon;
            const response = await fetch(url);
            const data = await response.json();

            if (data.routes && data.routes.length > 0) {
                const distanceMeters = data.routes[0].distance;
                const distanceKm = Math.round(distanceMeters / 1000);

                stav.vzdalenost = distanceKm;
                stav.dopravne = Math.round(distanceKm * 2 * TRANSPORT_RATE * 100) / 100;

                document.getElementById('distance-value').textContent = distanceKm;
                document.getElementById('transport-cost').textContent = stav.dopravne.toFixed(2);
                document.getElementById('distance-result').style.display = 'block';

                // Automaticky pokračovat na další krok po 1 sekundě
                setTimeout(() => {
                    nextStep();
                }, 1000);
            } else {
                alert('Nepodařilo se vypočítat vzdálenost. Zkuste jinou adresu.');
            }
        } catch (error) {
            console.error('[Kalkulačka] Chyba výpočtu vzdálenosti:', error);
            alert('Chyba při výpočtu vzdálenosti');
        }
    }

    // ========================================
    // WIZARD NAVIGACE
    // ========================================
    window.nextStep = function() {
        // Validace před pokračováním
        if (stav.krok === 1 && !stav.adresa) {
            alert('Nejprve vyberte adresu ze seznamu návrhů.');
            return;
        }

        // Skrýt aktuální krok
        const currentStep = document.querySelector('.wizard-step[style*="display: block"]');
        if (currentStep) {
            currentStep.style.display = 'none';
        }

        stav.krok++;

        // Určit, který krok zobrazit
        let nextStepId;

        if (stav.krok === 2) {
            nextStepId = 'step-service-type';
        } else if (stav.krok === 3) {
            // Podle typu servisu
            if (stav.typServisu === 'diagnostika') {
                // Přeskočit na krok 4 (extras)
                stav.krok = 4;
                nextStepId = 'step-extras';
            } else if (stav.typServisu === 'calouneni') {
                nextStepId = 'step-upholstery';
            } else if (stav.typServisu === 'mechanika') {
                nextStepId = 'step-mechanics';
            } else if (stav.typServisu === 'kombinace') {
                nextStepId = 'step-upholstery'; // Nejdřív čalounění
            }
        } else if (stav.krok === 4) {
            // Pokud je kombinace a právě jsme byli na čalounění, jdi na mechaniku
            if (stav.typServisu === 'kombinace' && currentStep && currentStep.id === 'step-upholstery') {
                nextStepId = 'step-mechanics';
                stav.krok = 3; // Zůstat na kroku 3
            } else {
                nextStepId = 'step-extras';
            }
        } else if (stav.krok === 5) {
            // Souhrn
            zobrazitSouhrn();
            nextStepId = 'step-summary';
        }

        // Zobrazit další krok
        const nextStep = document.getElementById(nextStepId);
        if (nextStep) {
            nextStep.style.display = 'block';
        }

        aktualizovatProgress();
        scrollToTop();
    };

    window.previousStep = function() {
        // Skrýt aktuální krok
        const currentStep = document.querySelector('.wizard-step[style*="display: block"]');
        if (currentStep) {
            currentStep.style.display = 'none';
        }

        stav.krok--;

        // Určit, který krok zobrazit
        let prevStepId;

        if (stav.krok === 1) {
            prevStepId = 'step-address';
        } else if (stav.krok === 2) {
            prevStepId = 'step-service-type';
        } else if (stav.krok === 3) {
            // Podle typu servisu
            if (stav.typServisu === 'calouneni' || stav.typServisu === 'kombinace') {
                prevStepId = 'step-upholstery';
            } else if (stav.typServisu === 'mechanika') {
                prevStepId = 'step-mechanics';
            }
        } else if (stav.krok === 4) {
            // Pokud je kombinace a jsme na mechanice, jdi zpět na čalounění
            if (stav.typServisu === 'kombinace' && currentStep && currentStep.id === 'step-mechanics') {
                prevStepId = 'step-upholstery';
                stav.krok = 3;
            } else {
                prevStepId = 'step-extras';
            }
        }

        // Zobrazit předchozí krok
        const prevStep = document.getElementById(prevStepId);
        if (prevStep) {
            prevStep.style.display = 'block';
        }

        aktualizovatProgress();
        scrollToTop();
    };

    function aktualizovatProgress() {
        const progressSteps = document.querySelectorAll('.progress-step');
        progressSteps.forEach((step, index) => {
            if (index < stav.krok) {
                step.classList.add('active');
            } else {
                step.classList.remove('active');
            }
        });
    }

    function scrollToTop() {
        const kalkulacka = document.getElementById('kalkulacka');
        if (kalkulacka) {
            kalkulacka.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // ========================================
    // COUNTER CONTROLS
    // ========================================
    window.incrementCounter = function(id) {
        const input = document.getElementById(id);
        if (input) {
            const max = parseInt(input.max) || 20;
            let value = parseInt(input.value) || 0;
            if (value < max) {
                input.value = value + 1;
                stav[id] = value + 1;
                aktualizovatSouhrnDilu();
            }
        }
    };

    window.decrementCounter = function(id) {
        const input = document.getElementById(id);
        if (input) {
            let value = parseInt(input.value) || 0;
            if (value > 0) {
                input.value = value - 1;
                stav[id] = value - 1;
                aktualizovatSouhrnDilu();
            }
        }
    };

    // ========================================
    // SOUHRN DÍLŮ (živý update)
    // ========================================
    function aktualizovatSouhrnDilu() {
        const celkemDilu = stav.sedaky + stav.operky + stav.podrucky + stav.panely;
        const totalPartsEl = document.getElementById('total-parts');
        const priceBreakdownEl = document.getElementById('parts-price-breakdown');

        if (totalPartsEl) {
            totalPartsEl.textContent = celkemDilu;
        }

        if (priceBreakdownEl && celkemDilu > 0) {
            let cena = 0;
            if (celkemDilu === 1) {
                cena = CENY.prvniDil;
                priceBreakdownEl.textContent = `(1× první díl = ${CENY.prvniDil}€)`;
            } else {
                cena = CENY.prvniDil + (celkemDilu - 1) * CENY.dalsiDil;
                priceBreakdownEl.textContent = `(1× ${CENY.prvniDil}€ + ${celkemDilu - 1}× ${CENY.dalsiDil}€ = ${cena}€)`;
            }

            // Přidat rohový díl a ottoman
            if (stav.rohovyDil) {
                cena += CENY.rohovyDil;
                priceBreakdownEl.textContent += ` + rohový díl (${CENY.rohovyDil}€)`;
            }
            if (stav.ottoman) {
                cena += CENY.ottoman;
                priceBreakdownEl.textContent += ` + ottoman (${CENY.ottoman}€)`;
            }
        } else if (priceBreakdownEl) {
            priceBreakdownEl.textContent = '';
        }
    }

    // ========================================
    // ZOBRAZIT CENOVÝ SOUHRN
    // ========================================
    function zobrazitSouhrn() {
        const summaryDetails = document.getElementById('summary-details');
        const grandTotal = document.getElementById('grand-total');

        let html = '';
        let celkem = 0;

        // Dopravné
        html += `<div class="summary-line">
            <span>Dopravné (${stav.vzdalenost} km × 2 × ${TRANSPORT_RATE}€):</span>
            <span class="summary-price">${stav.dopravne.toFixed(2)} €</span>
        </div>`;
        celkem += stav.dopravne;

        // Diagnostika
        if (stav.typServisu === 'diagnostika') {
            html += `<div class="summary-line">
                <span>Inspekce / Diagnostika:</span>
                <span class="summary-price">${CENY.diagnostika.toFixed(2)} €</span>
            </div>`;
            celkem += CENY.diagnostika;
        }

        // Čalounické práce
        if (stav.typServisu === 'calouneni' || stav.typServisu === 'kombinace') {
            const celkemDilu = stav.sedaky + stav.operky + stav.podrucky + stav.panely;

            if (celkemDilu > 0) {
                const cenaDilu = celkemDilu === 1 ?
                    CENY.prvniDil :
                    CENY.prvniDil + (celkemDilu - 1) * CENY.dalsiDil;

                html += `<div class="summary-line">
                    <span>Čalounické práce (${celkemDilu} dílů):</span>
                    <span class="summary-price">${cenaDilu.toFixed(2)} €</span>
                </div>`;

                if (celkemDilu > 1) {
                    html += `<div class="summary-subline">
                        ↳ První díl: ${CENY.prvniDil}€, další díly: ${celkemDilu - 1}× ${CENY.dalsiDil}€
                    </div>`;
                }

                celkem += cenaDilu;
            }

            // Rohový díl
            if (stav.rohovyDil) {
                html += `<div class="summary-line">
                    <span>Rohový díl:</span>
                    <span class="summary-price">${CENY.rohovyDil.toFixed(2)} €</span>
                </div>`;
                celkem += CENY.rohovyDil;
            }

            // Ottoman
            if (stav.ottoman) {
                html += `<div class="summary-line">
                    <span>Ottoman / Lehátko:</span>
                    <span class="summary-price">${CENY.ottoman.toFixed(2)} €</span>
                </div>`;
                celkem += CENY.ottoman;
            }
        }

        // Mechanické práce
        if (stav.typServisu === 'mechanika' || stav.typServisu === 'kombinace') {
            const celkemMechanismu = stav.relax + stav.vysuv;

            if (celkemMechanismu > 0) {
                const cenaMechanismu = celkemMechanismu * CENY.mechanismus;

                html += `<div class="summary-line">
                    <span>Mechanické části (${celkemMechanismu}× mechanismus):</span>
                    <span class="summary-price">${cenaMechanismu.toFixed(2)} €</span>
                </div>`;

                if (stav.relax > 0) {
                    html += `<div class="summary-subline">
                        ↳ Relax mechanismy: ${stav.relax}× ${CENY.mechanismus}€
                    </div>`;
                }
                if (stav.vysuv > 0) {
                    html += `<div class="summary-subline">
                        ↳ Výsuvné mechanismy: ${stav.vysuv}× ${CENY.mechanismus}€
                    </div>`;
                }

                celkem += cenaMechanismu;
            }
        }

        // Druhá osoba
        if (stav.tezkyNabytek) {
            html += `<div class="summary-line">
                <span>Druhá osoba (těžký nábytek >50kg):</span>
                <span class="summary-price">${CENY.druhaOsoba.toFixed(2)} €</span>
            </div>`;
            celkem += CENY.druhaOsoba;
        }

        // Materiál
        if (stav.material) {
            html += `<div class="summary-line">
                <span>Materiál dodán od WGS:</span>
                <span class="summary-price">${CENY.material.toFixed(2)} €</span>
            </div>`;
            celkem += CENY.material;
        }

        summaryDetails.innerHTML = html;
        grandTotal.innerHTML = `<strong>${celkem.toFixed(2)} €</strong>`;
    }

    // ========================================
    // RESETOVAT KALKULAČKU
    // ========================================
    window.resetovatKalkulacku = function() {
        // Reset stavu
        stav = {
            krok: 1,
            adresa: null,
            vzdalenost: 0,
            dopravne: 0,
            typServisu: 'calouneni',
            sedaky: 0,
            operky: 0,
            podrucky: 0,
            panely: 0,
            rohovyDil: false,
            ottoman: false,
            relax: 0,
            vysuv: 0,
            tezkyNabytek: false,
            material: false
        };

        // Reset formuláře
        document.getElementById('calc-address').value = '';
        document.getElementById('distance-result').style.display = 'none';
        document.getElementById('address-suggestions').style.display = 'none';

        // Reset radio buttons
        document.querySelector('input[name="service-type"][value="calouneni"]').checked = true;

        // Reset counterů
        ['sedaky', 'operky', 'podrucky', 'panely', 'relax', 'vysuv'].forEach(id => {
            const input = document.getElementById(id);
            if (input) input.value = 0;
        });

        // Reset checkboxů
        ['rohovy-dil', 'ottoman', 'tezky-nabytek', 'material'].forEach(id => {
            const checkbox = document.getElementById(id);
            if (checkbox) checkbox.checked = false;
        });

        // Skrýt všechny kroky
        document.querySelectorAll('.wizard-step').forEach(step => {
            step.style.display = 'none';
        });

        // Zobrazit první krok
        document.getElementById('step-address').style.display = 'block';

        aktualizovatProgress();
        scrollToTop();
    };

    // ========================================
    // EXPORT DO PDF
    // ========================================
    window.exportovatCenikPDF = async function() {
        try {
            // Kontrola jestli jsou knihovny načteny
            if (typeof window.jspdf === 'undefined') {
                alert('PDF knihovna se načítá, zkuste to prosím za chvíli...');
                return;
            }

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');

            const pageWidth = doc.internal.pageSize.getWidth();
            const margin = 15;
            let yPos = 20;

            // HLAVIČKA
            doc.setFontSize(20);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(74, 74, 74); // #4a4a4a
            doc.text('KALKULACE CENY SERVISU', pageWidth / 2, yPos, { align: 'center' });

            yPos += 10;
            doc.setFontSize(10);
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(102, 102, 102); // #666
            const datum = new Date().toLocaleDateString('cs-CZ');
            doc.text(`Datum: ${datum}`, pageWidth / 2, yPos, { align: 'center' });

            yPos += 15;

            // LINKA
            doc.setDrawColor(74, 74, 74);
            doc.setLineWidth(0.5);
            doc.line(margin, yPos, pageWidth - margin, yPos);

            yPos += 10;

            // ADRESA
            doc.setFontSize(11);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(42, 42, 42);
            doc.text('Adresa zákazníka:', margin, yPos);
            yPos += 6;
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(0, 0, 0);
            doc.text(stav.adresa || 'Neuvedeno', margin, yPos);
            yPos += 6;
            doc.text(`Vzdálenost z dílny: ${stav.vzdalenost} km`, margin, yPos);

            yPos += 12;

            // CENOVÝ SOUHRN
            doc.setFontSize(14);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(42, 42, 42);
            doc.text('Cenový souhrn:', margin, yPos);

            yPos += 8;

            // Položky
            doc.setFontSize(10);
            doc.setFont('helvetica', 'normal');

            let celkem = 0;

            // Dopravné
            doc.text(`Dopravné (${stav.vzdalenost} km × 2 × ${TRANSPORT_RATE}€):`, margin, yPos);
            doc.text(`${stav.dopravne.toFixed(2)} €`, pageWidth - margin, yPos, { align: 'right' });
            celkem += stav.dopravne;
            yPos += 6;

            // Diagnostika
            if (stav.typServisu === 'diagnostika') {
                doc.text('Inspekce / Diagnostika:', margin, yPos);
                doc.text(`${CENY.diagnostika.toFixed(2)} €`, pageWidth - margin, yPos, { align: 'right' });
                celkem += CENY.diagnostika;
                yPos += 6;
            }

            // Čalounické práce
            if (stav.typServisu === 'calouneni' || stav.typServisu === 'kombinace') {
                const celkemDilu = stav.sedaky + stav.operky + stav.podrucky + stav.panely;

                if (celkemDilu > 0) {
                    const cenaDilu = celkemDilu === 1 ?
                        CENY.prvniDil :
                        CENY.prvniDil + (celkemDilu - 1) * CENY.dalsiDil;

                    doc.text(`Čalounické práce (${celkemDilu} dílů):`, margin, yPos);
                    doc.text(`${cenaDilu.toFixed(2)} €`, pageWidth - margin, yPos, { align: 'right' });
                    celkem += cenaDilu;
                    yPos += 6;

                    if (celkemDilu > 1) {
                        doc.setFontSize(9);
                        doc.setTextColor(102, 102, 102);
                        doc.text(`  ↳ První díl: ${CENY.prvniDil}€, další díly: ${celkemDilu - 1}× ${CENY.dalsiDil}€`, margin, yPos);
                        yPos += 5;
                        doc.setFontSize(10);
                        doc.setTextColor(0, 0, 0);
                    }
                }

                // Rohový díl
                if (stav.rohovyDil) {
                    doc.text('Rohový díl:', margin, yPos);
                    doc.text(`${CENY.rohovyDil.toFixed(2)} €`, pageWidth - margin, yPos, { align: 'right' });
                    celkem += CENY.rohovyDil;
                    yPos += 6;
                }

                // Ottoman
                if (stav.ottoman) {
                    doc.text('Ottoman / Lehátko:', margin, yPos);
                    doc.text(`${CENY.ottoman.toFixed(2)} €`, pageWidth - margin, yPos, { align: 'right' });
                    celkem += CENY.ottoman;
                    yPos += 6;
                }
            }

            // Mechanické práce
            if (stav.typServisu === 'mechanika' || stav.typServisu === 'kombinace') {
                const celkemMechanismu = stav.relax + stav.vysuv;

                if (celkemMechanismu > 0) {
                    const cenaMechanismu = celkemMechanismu * CENY.mechanismus;

                    doc.text(`Mechanické části (${celkemMechanismu}× mechanismus):`, margin, yPos);
                    doc.text(`${cenaMechanismu.toFixed(2)} €`, pageWidth - margin, yPos, { align: 'right' });
                    celkem += cenaMechanismu;
                    yPos += 6;

                    if (stav.relax > 0 || stav.vysuv > 0) {
                        doc.setFontSize(9);
                        doc.setTextColor(102, 102, 102);
                        if (stav.relax > 0) {
                            doc.text(`  ↳ Relax mechanismy: ${stav.relax}× ${CENY.mechanismus}€`, margin, yPos);
                            yPos += 5;
                        }
                        if (stav.vysuv > 0) {
                            doc.text(`  ↳ Výsuvné mechanismy: ${stav.vysuv}× ${CENY.mechanismus}€`, margin, yPos);
                            yPos += 5;
                        }
                        doc.setFontSize(10);
                        doc.setTextColor(0, 0, 0);
                    }
                }
            }

            // Druhá osoba
            if (stav.tezkyNabytek) {
                doc.text('Druhá osoba (těžký nábytek >50kg):', margin, yPos);
                doc.text(`${CENY.druhaOsoba.toFixed(2)} €`, pageWidth - margin, yPos, { align: 'right' });
                celkem += CENY.druhaOsoba;
                yPos += 6;
            }

            // Materiál
            if (stav.material) {
                doc.text('Materiál dodán od WGS:', margin, yPos);
                doc.text(`${CENY.material.toFixed(2)} €`, pageWidth - margin, yPos, { align: 'right' });
                celkem += CENY.material;
                yPos += 6;
            }

            yPos += 5;

            // CELKOVÁ ČÁRA
            doc.setDrawColor(74, 74, 74);
            doc.setLineWidth(1);
            doc.line(margin, yPos, pageWidth - margin, yPos);

            yPos += 8;

            // CELKOVÁ CENA
            doc.setFontSize(14);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(42, 42, 42);
            doc.text('CELKOVÁ CENA:', margin, yPos);
            doc.text(`${celkem.toFixed(2)} €`, pageWidth - margin, yPos, { align: 'right' });

            yPos += 15;

            // UPOZORNĚNÍ
            doc.setFontSize(9);
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(102, 102, 102);
            doc.setDrawColor(255, 153, 0);
            doc.setFillColor(255, 249, 240);
            doc.rect(margin, yPos, pageWidth - 2 * margin, 15, 'FD');

            yPos += 5;
            const upozorneni = doc.splitTextToSize(
                'Upozornění: Ceny jsou orientační a vztahují se pouze na práci. ' +
                'Originální materiál z továrny Natuzzi a náhradní mechanické díly se účtují zvlášť podle skutečné spotřeby.',
                pageWidth - 2 * margin - 4
            );
            doc.text(upozorneni, margin + 2, yPos);

            yPos += 20;

            // FOOTER
            doc.setFontSize(8);
            doc.setTextColor(150, 150, 150);
            const footerY = doc.internal.pageSize.getHeight() - 15;
            doc.text('White Glove Service s.r.o.', pageWidth / 2, footerY, { align: 'center' });
            doc.text('Do Dubče 364, Běchovice 190 11', pageWidth / 2, footerY + 4, { align: 'center' });
            doc.text('Tel: +420 725 965 826 | Email: reklamace@wgs-service.cz', pageWidth / 2, footerY + 8, { align: 'center' });

            // STÁHNOUT PDF
            const nazevSouboru = `kalkulace_${new Date().getTime()}.pdf`;
            doc.save(nazevSouboru);

            console.log('[Kalkulačka] PDF staženo:', nazevSouboru);

        } catch (error) {
            console.error('[Kalkulačka] Chyba při exportu PDF:', error);
            alert('Nepodařilo se vytvořit PDF. Zkuste to prosím znovu.');
        }
    };

})();
