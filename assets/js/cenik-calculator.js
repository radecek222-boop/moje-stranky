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

    // Režim kalkulačky: 'standalone' nebo 'protokol'
    let kalkulackaRezim = 'standalone';

    // Ceník služeb (aktualizováno 2025-11-24)
    const CENY = {
        diagnostika: 110, // Inspekce/diagnostika
        prvniDil: 205, // První díl čalounění
        dalsiDil: 70, // Každý další díl
        rohovyDil: 220, // Rohový díl
        ottoman: 260, // Ottoman s terminálem
        zakladniSazba: 165, // Základní servisní sazba (mechanické opravy)
        mechanismusPriplatek: 45, // Příplatek za mechanismus (relax, výsuv)
        druhaOsoba: 95, // Druhá osoba pro těžký nábytek nad 50kg
        material: 50 // Materiál (alternativní výplně)
    };

    // Stav kalkulačky
    let stav = {
        krok: 1,
        adresa: null,
        vzdalenost: 0,
        dopravne: 0,
        reklamaceBezDopravy: false,
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

    // Export pro použití z protokolu
    window.initKalkulacka = initKalkulacka;

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
            item.textContent = feature.properties.formatted || feature.properties.address_line1 || window.t('summary.unknownAddress');

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

                // Zkontrolovat checkbox "reklamace bez dopravy"
                const reklamaceBezDopravyCheckbox = document.getElementById('reklamace-bez-dopravy');
                const jeReklamace = reklamaceBezDopravyCheckbox && reklamaceBezDopravyCheckbox.checked;

                if (jeReklamace) {
                    stav.dopravne = 0;
                    stav.reklamaceBezDopravy = true;
                } else {
                    stav.dopravne = Math.round(distanceKm * 2 * TRANSPORT_RATE * 100) / 100;
                    stav.reklamaceBezDopravy = false;
                }

                document.getElementById('distance-value').textContent = distanceKm;
                document.getElementById('transport-cost').textContent = stav.dopravne.toFixed(2) + (jeReklamace ? ' (' + window.t('summary.claim') + ')' : '');
                document.getElementById('distance-result').style.display = 'block';

                // Uživatel pokračuje ručně tlačítkem "Pokračovat"
            } else {
                alert(window.t('alert.distanceError'));
            }
        } catch (error) {
            console.error('[Kalkulačka] Chyba výpočtu vzdálenosti:', error);
            alert(window.t('alert.distanceCalculationError'));
        }
    }

    // ========================================
    // WIZARD NAVIGACE
    // ========================================
    window.nextStep = function() {
        // Validace před pokračováním
        if (stav.krok === 1 && !stav.adresa) {
            // Zkontrolovat jestli je zaškrtnutý checkbox "reklamace bez dopravy"
            const reklamaceBezDopravyCheckbox = document.getElementById('reklamace-bez-dopravy');
            const jeReklamace = reklamaceBezDopravyCheckbox && reklamaceBezDopravyCheckbox.checked;

            if (!jeReklamace) {
                // Pokud není reklamace, adresa je povinná
                alert(window.t('alert.selectAddress'));
                return;
            } else {
                // Pokud je reklamace, nastavit dopravné a vzdálenost na 0
                stav.dopravne = 0;
                stav.vzdalenost = 0;
                stav.reklamaceBezDopravy = true;
                stav.adresa = window.t('summary.claimNoTransport');
                console.log('[Kalkulačka] Reklamace bez dopravy - pokračuji bez adresy');
            }
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
            const firstPartText = window.t('summary.firstPart').toLowerCase();
            if (celkemDilu === 1) {
                cena = CENY.prvniDil;
                priceBreakdownEl.textContent = `(1× ${firstPartText} = ${CENY.prvniDil}€)`;
            } else {
                cena = CENY.prvniDil + (celkemDilu - 1) * CENY.dalsiDil;
                priceBreakdownEl.textContent = `(1× ${CENY.prvniDil}€ + ${celkemDilu - 1}× ${CENY.dalsiDil}€ = ${cena}€)`;
            }

            // Přidat rohový díl a ottoman
            if (stav.rohovyDil) {
                cena += CENY.rohovyDil;
                priceBreakdownEl.textContent += ` + ${window.t('summary.cornerPiece').toLowerCase()} (${CENY.rohovyDil}€)`;
            }
            if (stav.ottoman) {
                cena += CENY.ottoman;
                priceBreakdownEl.textContent += ` + ${window.t('summary.ottoman').toLowerCase()} (${CENY.ottoman}€)`;
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
            <span>${window.t('summary.transportation')} (${stav.vzdalenost} km × 2 × ${TRANSPORT_RATE}€):</span>
            <span class="summary-price">${stav.dopravne.toFixed(2)} €</span>
        </div>`;
        celkem += stav.dopravne;

        // Diagnostika
        if (stav.typServisu === 'diagnostika') {
            html += `<div class="summary-line">
                <span>${window.t('summary.inspection')}:</span>
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

                const partsWord = celkemDilu === 1 ? window.t('summary.part') : window.t('summary.parts');
                html += `<div class="summary-line">
                    <span>${window.t('summary.upholsteryWork')} (${celkemDilu} ${partsWord}):</span>
                    <span class="summary-price">${cenaDilu.toFixed(2)} €</span>
                </div>`;

                if (celkemDilu > 1) {
                    html += `<div class="summary-subline">
                        ↳ ${window.t('summary.firstPart')}: ${CENY.prvniDil}€, ${window.t('summary.additionalParts')}: ${celkemDilu - 1}× ${CENY.dalsiDil}€
                    </div>`;
                }

                celkem += cenaDilu;
            }

            // Rohový díl
            if (stav.rohovyDil) {
                html += `<div class="summary-line">
                    <span>${window.t('summary.cornerPiece')}:</span>
                    <span class="summary-price">${CENY.rohovyDil.toFixed(2)} €</span>
                </div>`;
                celkem += CENY.rohovyDil;
            }

            // Ottoman
            if (stav.ottoman) {
                html += `<div class="summary-line">
                    <span>${window.t('summary.ottoman')}:</span>
                    <span class="summary-price">${CENY.ottoman.toFixed(2)} €</span>
                </div>`;
                celkem += CENY.ottoman;
            }
        }

        // Mechanické práce
        if (stav.typServisu === 'mechanika' || stav.typServisu === 'kombinace') {
            const celkemMechanismu = stav.relax + stav.vysuv;

            // Pokud je POUZE mechanika, přidat základní sazbu
            if (stav.typServisu === 'mechanika') {
                html += `<div class="summary-line">
                    <span>${window.t('summary.basicServiceRate') || 'Základní servisní sazba'}:</span>
                    <span class="summary-price">${CENY.zakladniSazba.toFixed(2)} €</span>
                </div>`;
                celkem += CENY.zakladniSazba;
            }

            if (celkemMechanismu > 0) {
                const cenaMechanismu = celkemMechanismu * CENY.mechanismusPriplatek;

                html += `<div class="summary-line">
                    <span>${window.t('summary.mechanicalParts')} (${celkemMechanismu}× ${window.t('summary.mechanism')}):</span>
                    <span class="summary-price">${cenaMechanismu.toFixed(2)} €</span>
                </div>`;

                if (stav.relax > 0) {
                    html += `<div class="summary-subline">
                        ↳ ${window.t('summary.relaxMechanisms')}: ${stav.relax}× ${CENY.mechanismusPriplatek}€
                    </div>`;
                }
                if (stav.vysuv > 0) {
                    html += `<div class="summary-subline">
                        ↳ ${window.t('summary.slidingMechanisms')}: ${stav.vysuv}× ${CENY.mechanismusPriplatek}€
                    </div>`;
                }

                celkem += cenaMechanismu;
            }
        }

        // Druhá osoba
        if (stav.tezkyNabytek) {
            html += `<div class="summary-line">
                <span>${window.t('summary.secondPerson')}:</span>
                <span class="summary-price">${CENY.druhaOsoba.toFixed(2)} €</span>
            </div>`;
            celkem += CENY.druhaOsoba;
        }

        // Materiál
        if (stav.material) {
            html += `<div class="summary-line">
                <span>${window.t('summary.materialSupplied')}:</span>
                <span class="summary-price">${CENY.material.toFixed(2)} €</span>
            </div>`;
            celkem += CENY.material;
        }

        summaryDetails.innerHTML = html;
        grandTotal.innerHTML = `<strong>${celkem.toFixed(2)} €</strong>`;

        // Upravit tlačítka podle režimu kalkulačky
        upravitTlacitkaProRezim();
    }

    function upravitTlacitkaProRezim() {
        const wizardButtons = document.querySelector('#step-summary .wizard-buttons');
        if (!wizardButtons) return;

        if (kalkulackaRezim === 'protokol') {
            // Protokol režim - zobrazit Zpět a Započítat
            wizardButtons.innerHTML = `
                <button class="btn-secondary" onclick="previousStep()">${window.t('btn.back')}</button>
                <button class="btn-primary" onclick="zapocitatDoProtokolu()">${window.t('btn.addToProtocol')}</button>
            `;
        } else {
            // Standalone režim - zobrazit Zpět, Export PDF a Nová kalkulace
            wizardButtons.innerHTML = `
                <button class="btn-secondary" onclick="previousStep()">${window.t('btn.back')}</button>
                <button class="btn-primary" onclick="exportovatCenikPDF()">${window.t('btn.exportPDF')}</button>
                <button class="btn-primary" onclick="resetovatKalkulacku()">${window.t('btn.newCalculation')}</button>
            `;
        }
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
    // EXPORT DO PDF (pomocí html2canvas - stejně jako protokol.php)
    // ========================================
    window.exportovatCenikPDF = async function() {
        try {
            // Kontrola jestli jsou knihovny načteny
            if (typeof window.jspdf === 'undefined' || typeof html2canvas === 'undefined') {
                alert(window.t('alert.pdfLoading'));
                return;
            }

            console.log('[Kalkulačka] Generuji PDF pomocí html2canvas...');

            // Vypočítat celkovou cenu
            let celkem = stav.dopravne;

            // Diagnostika
            if (stav.typServisu === 'diagnostika') {
                celkem += CENY.diagnostika;
            }

            // Čalounické práce
            if (stav.typServisu === 'calouneni' || stav.typServisu === 'kombinace') {
                const celkemDilu = stav.sedaky + stav.operky + stav.podrucky + stav.panely;
                if (celkemDilu > 0) {
                    const cenaDilu = celkemDilu === 1 ? CENY.prvniDil : CENY.prvniDil + (celkemDilu - 1) * CENY.dalsiDil;
                    celkem += cenaDilu;
                }
                if (stav.rohovyDil) celkem += CENY.rohovyDil;
                if (stav.ottoman) celkem += CENY.ottoman;
            }

            // Mechanické práce
            if (stav.typServisu === 'mechanika' || stav.typServisu === 'kombinace') {
                // Pokud je POUZE mechanika, přidat základní sazbu
                if (stav.typServisu === 'mechanika') {
                    celkem += CENY.zakladniSazba;
                }

                const celkemMechanismu = stav.relax + stav.vysuv;
                if (celkemMechanismu > 0) {
                    celkem += celkemMechanismu * CENY.mechanismusPriplatek;
                }
            }

            // Druhá osoba
            if (stav.tezkyNabytek) celkem += CENY.druhaOsoba;

            // Materiál
            if (stav.material) celkem += CENY.material;

            // Vytvořit HTML strukturu pro PDF (vždy desktop šířka, i na mobilu)
            const pdfContent = document.createElement('div');
            pdfContent.id = 'pdf-kalkulace-temp';
            pdfContent.style.cssText = `
                width: 794px !important;
                min-width: 794px !important;
                max-width: 794px !important;
                padding: 40px;
                background: white;
                font-family: Arial, sans-serif;
                position: fixed;
                left: -9999px;
                top: 0;
                box-sizing: border-box;
            `;

            const datum = new Date().toLocaleDateString('cs-CZ');

            let htmlContent = `
                <div style="text-align: center; margin-bottom: 20px;">
                    <h1 style="font-size: 28px; color: #4a4a4a; margin: 0 0 10px 0; font-weight: bold;">
                        KALKULACE CENY SERVISU
                    </h1>
                    <p style="font-size: 14px; color: #666; margin: 0;">
                        Datum: ${datum}
                    </p>
                </div>

                <hr style="border: none; border-top: 2px solid #4a4a4a; margin: 20px 0;">

                <div style="margin: 20px 0;">
                    <h3 style="font-size: 16px; color: #2a2a2a; margin: 0 0 8px 0; font-weight: bold;">
                        Adresa zákazníka:
                    </h3>
                    <p style="font-size: 14px; margin: 0 0 5px 0;">${stav.adresa || 'Neuvedeno'}</p>
                    <p style="font-size: 14px; margin: 0;">Vzdálenost z dílny: ${stav.vzdalenost} km</p>
                </div>

                <div style="margin: 30px 0; background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <h3 style="font-size: 18px; color: #2a2a2a; margin: 0 0 15px 0; font-weight: bold;">
                        Specifikace zakázky:
                    </h3>
            `;

            // Typ servisu
            const typyServisu = {
                'diagnostika': 'Inspekce / Diagnostika',
                'calouneni': 'Čalounické práce',
                'mechanika': 'Mechanické práce',
                'kombinace': 'Kombinace čalounění a mechaniky'
            };
            htmlContent += `
                    <p style="font-size: 14px; margin: 8px 0;">
                        <strong>Typ servisu:</strong> ${typyServisu[stav.typServisu] || 'Neuveden'}
                    </p>
            `;

            // Čalounické práce - detaily
            if (stav.typServisu === 'calouneni' || stav.typServisu === 'kombinace') {
                htmlContent += `<p style="font-size: 14px; margin: 8px 0;"><strong>Čalounické práce:</strong></p>`;
                htmlContent += `<ul style="margin: 5px 0 5px 20px; font-size: 14px; line-height: 1.8;">`;

                if (stav.sedaky > 0) htmlContent += `<li>Sedáky: ${stav.sedaky}×</li>`;
                if (stav.operky > 0) htmlContent += `<li>Opěrky: ${stav.operky}×</li>`;
                if (stav.podrucky > 0) htmlContent += `<li>Područky: ${stav.podrucky}×</li>`;
                if (stav.panely > 0) htmlContent += `<li>Panely: ${stav.panely}×</li>`;
                if (stav.rohovyDil) htmlContent += `<li>Rohový díl: Ano</li>`;
                if (stav.ottoman) htmlContent += `<li>Ottoman / Lehátko: Ano</li>`;

                const celkemDilu = stav.sedaky + stav.operky + stav.podrucky + stav.panely;
                if (celkemDilu === 0 && !stav.rohovyDil && !stav.ottoman) {
                    htmlContent += `<li style="color: #999;">Nebyly vybrány žádné díly</li>`;
                }

                htmlContent += `</ul>`;
            }

            // Mechanické práce - detaily
            if (stav.typServisu === 'mechanika' || stav.typServisu === 'kombinace') {
                htmlContent += `<p style="font-size: 14px; margin: 8px 0;"><strong>Mechanické práce:</strong></p>`;
                htmlContent += `<ul style="margin: 5px 0 5px 20px; font-size: 14px; line-height: 1.8;">`;

                if (stav.relax > 0) htmlContent += `<li>Relax mechanismy: ${stav.relax}×</li>`;
                if (stav.vysuv > 0) htmlContent += `<li>Elektrické díly: ${stav.vysuv}×</li>`;

                const celkemMechanismu = stav.relax + stav.vysuv;
                if (celkemMechanismu === 0) {
                    htmlContent += `<li style="color: #999;">Nebyly vybrány žádné mechanismy</li>`;
                }

                htmlContent += `</ul>`;
            }

            // Doplňkové služby
            htmlContent += `<p style="font-size: 14px; margin: 8px 0;"><strong>Doplňkové služby:</strong></p>`;
            htmlContent += `<ul style="margin: 5px 0 5px 20px; font-size: 14px; line-height: 1.8;">`;

            if (stav.tezkyNabytek) {
                htmlContent += `<li>Druhá osoba (těžký nábytek >50kg): Ano</li>`;
            }
            if (stav.material) {
                htmlContent += `<li>Materiál dodán od WGS: Ano</li>`;
            }
            if (!stav.tezkyNabytek && !stav.material) {
                htmlContent += `<li style="color: #999;">Žádné doplňkové služby</li>`;
            }

            htmlContent += `</ul>`;
            htmlContent += `</div>`;

            // CENOVÝ SOUHRN
            htmlContent += `
                <div style="margin: 30px 0;">
                    <h3 style="font-size: 18px; color: #2a2a2a; margin: 0 0 15px 0; font-weight: bold;">
                        Cenový souhrn:
                    </h3>

                    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 8px 0;">Dopravné (${stav.vzdalenost} km × 2 × ${TRANSPORT_RATE}€):</td>
                            <td style="padding: 8px 0; text-align: right; font-weight: bold;">${stav.dopravne.toFixed(2)} €</td>
                        </tr>
            `;

            // Diagnostika
            if (stav.typServisu === 'diagnostika') {
                htmlContent += `
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 8px 0;">Inspekce / Diagnostika:</td>
                        <td style="padding: 8px 0; text-align: right; font-weight: bold;">${CENY.diagnostika.toFixed(2)} €</td>
                    </tr>
                `;
            }

            // Čalounické práce
            if (stav.typServisu === 'calouneni' || stav.typServisu === 'kombinace') {
                const celkemDilu = stav.sedaky + stav.operky + stav.podrucky + stav.panely;
                if (celkemDilu > 0) {
                    const cenaDilu = celkemDilu === 1 ? CENY.prvniDil : CENY.prvniDil + (celkemDilu - 1) * CENY.dalsiDil;
                    htmlContent += `
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 8px 0;">Čalounické práce (${celkemDilu} ${celkemDilu === 1 ? 'díl' : 'dílů'}):</td>
                            <td style="padding: 8px 0; text-align: right; font-weight: bold;">${cenaDilu.toFixed(2)} €</td>
                        </tr>
                    `;
                    if (celkemDilu > 1) {
                        htmlContent += `
                            <tr>
                                <td colspan="2" style="padding: 4px 0 8px 20px; font-size: 12px; color: #666;">
                                    ↳ První díl: ${CENY.prvniDil}€, další díly: ${celkemDilu - 1}× ${CENY.dalsiDil}€
                                </td>
                            </tr>
                        `;
                    }
                }

                if (stav.rohovyDil) {
                    htmlContent += `
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 8px 0;">Rohový díl:</td>
                            <td style="padding: 8px 0; text-align: right; font-weight: bold;">${CENY.rohovyDil.toFixed(2)} €</td>
                        </tr>
                    `;
                }

                if (stav.ottoman) {
                    htmlContent += `
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 8px 0;">Ottoman / Lehátko:</td>
                            <td style="padding: 8px 0; text-align: right; font-weight: bold;">${CENY.ottoman.toFixed(2)} €</td>
                        </tr>
                    `;
                }
            }

            // Mechanické práce
            if (stav.typServisu === 'mechanika' || stav.typServisu === 'kombinace') {
                // Pokud je POUZE mechanika, přidat základní sazbu
                if (stav.typServisu === 'mechanika') {
                    htmlContent += `
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 8px 0;">Základní servisní sazba:</td>
                            <td style="padding: 8px 0; text-align: right; font-weight: bold;">${CENY.zakladniSazba.toFixed(2)} €</td>
                        </tr>
                    `;
                }

                const celkemMechanismu = stav.relax + stav.vysuv;
                if (celkemMechanismu > 0) {
                    const cenaMechanismu = celkemMechanismu * CENY.mechanismusPriplatek;
                    htmlContent += `
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 8px 0;">Mechanické části (${celkemMechanismu}× mechanismus):</td>
                            <td style="padding: 8px 0; text-align: right; font-weight: bold;">${cenaMechanismu.toFixed(2)} €</td>
                        </tr>
                    `;
                    if (stav.relax > 0 || stav.vysuv > 0) {
                        let detaily = '';
                        if (stav.relax > 0) detaily += `Relax mechanismy: ${stav.relax}× ${CENY.mechanismusPriplatek}€`;
                        if (stav.vysuv > 0) {
                            if (detaily) detaily += ', ';
                            detaily += `Elektrické díly: ${stav.vysuv}× ${CENY.mechanismusPriplatek}€`;
                        }
                        htmlContent += `
                            <tr>
                                <td colspan="2" style="padding: 4px 0 8px 20px; font-size: 12px; color: #666;">
                                    ↳ ${detaily}
                                </td>
                            </tr>
                        `;
                    }
                }
            }

            // Druhá osoba
            if (stav.tezkyNabytek) {
                htmlContent += `
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 8px 0;">Druhá osoba (těžký nábytek >50kg):</td>
                        <td style="padding: 8px 0; text-align: right; font-weight: bold;">${CENY.druhaOsoba.toFixed(2)} €</td>
                    </tr>
                `;
            }

            // Materiál
            if (stav.material) {
                htmlContent += `
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 8px 0;">Materiál dodán od WGS:</td>
                        <td style="padding: 8px 0; text-align: right; font-weight: bold;">${CENY.material.toFixed(2)} €</td>
                    </tr>
                `;
            }

            htmlContent += `
                        <tr style="border-top: 3px solid #4a4a4a;">
                            <td style="padding: 15px 0; font-size: 18px; font-weight: bold;">CELKOVÁ CENA:</td>
                            <td style="padding: 15px 0; text-align: right; font-size: 18px; font-weight: bold; color: #2a2a2a;">
                                ${celkem.toFixed(2)} €
                            </td>
                        </tr>
                    </table>
                </div>

                <div style="background: #fff9f0; border-left: 4px solid #ff9900; padding: 15px; margin: 30px 0; font-size: 12px; color: #666;">
                    <strong>Upozornění:</strong> Ceny jsou orientační a vztahují se pouze na práci.
                    Originální materiál z továrny Natuzzi a náhradní mechanické díly se účtují zvlášť podle skutečné spotřeby.
                </div>

                <div style="text-align: center; margin-top: 50px; font-size: 11px; color: #999;">
                    <p style="margin: 5px 0;"><strong>White Glove Service s.r.o.</strong></p>
                    <p style="margin: 5px 0;">Do Dubče 364, Běchovice 190 11</p>
                    <p style="margin: 5px 0;">Tel: +420 725 965 826 | Email: reklamace@wgs-service.cz</p>
                </div>
            `;

            pdfContent.innerHTML = htmlContent;
            document.body.appendChild(pdfContent);

            // Počkat na reflow
            await new Promise(resolve => setTimeout(resolve, 100));

            // Převést HTML na canvas (stejné nastavení jako protokol.php)
            console.log('[Kalkulačka] Renderuji HTML pomocí html2canvas...');
            const canvas = await html2canvas(pdfContent, {
                scale: 3,
                backgroundColor: '#ffffff',
                useCORS: true,
                logging: false,
                imageTimeout: 0,
                allowTaint: true,
                letterRendering: true
            });

            // Vytvořit PDF
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
            const nazevSouboru = `kalkulace_${new Date().getTime()}.pdf`;
            doc.save(nazevSouboru);

            console.log('[Kalkulačka] PDF staženo:', nazevSouboru);

        } catch (error) {
            console.error('[Kalkulačka] Chyba při exportu PDF:', error);
            alert(window.t('alert.pdfError'));
        }
    };

    // ========================================
    // NASTAVENÍ REŽIMU KALKULAČKY
    // ========================================
    window.nastavitKalkulackuRezim = function(rezim) {
        kalkulackaRezim = rezim;
        console.log('[Kalkulačka] Režim nastaven na:', rezim);
    };

    // ========================================
    // ZAPOČÍTAT DO PROTOKOLU
    // ========================================
    window.zapocitatDoProtokolu = function() {
        console.log('[Kalkulačka] Započítávám do protokolu...');

        // Vypočítat celkovou cenu
        let celkovaCena = stav.dopravne;

        // Diagnostika
        if (stav.typServisu === 'diagnostika') {
            celkovaCena += CENY.diagnostika;
        }

        // Čalounické práce
        if (stav.typServisu === 'calouneni' || stav.typServisu === 'kombinace') {
            const celkemDilu = stav.sedaky + stav.operky + stav.podrucky + stav.panely;
            if (celkemDilu > 0) {
                const cenaDilu = celkemDilu === 1 ?
                    CENY.prvniDil :
                    CENY.prvniDil + (celkemDilu - 1) * CENY.dalsiDil;
                celkovaCena += cenaDilu;
            }
            if (stav.rohovyDil) celkovaCena += CENY.rohovyDil;
            if (stav.ottoman) celkovaCena += CENY.ottoman;
        }

        // Mechanické práce
        if (stav.typServisu === 'mechanika' || stav.typServisu === 'kombinace') {
            // Pokud je POUZE mechanika, přidat základní sazbu
            if (stav.typServisu === 'mechanika') {
                celkovaCena += CENY.zakladniSazba;
            }

            const celkemMechanismu = stav.relax + stav.vysuv;
            if (celkemMechanismu > 0) {
                celkovaCena += celkemMechanismu * CENY.mechanismusPriplatek;
            }
        }

        // Druhá osoba
        if (stav.tezkyNabytek) {
            celkovaCena += CENY.druhaOsoba;
        }

        // Materiál
        if (stav.material) {
            celkovaCena += CENY.material;
        }

        // Sestavit data pro protokol
        const kalkulaceData = {
            celkovaCena: celkovaCena,
            adresa: stav.adresa,
            vzdalenost: stav.vzdalenost,
            dopravne: stav.dopravne,
            reklamaceBezDopravy: stav.reklamaceBezDopravy,
            typServisu: stav.typServisu,
            rozpis: {
                diagnostika: stav.typServisu === 'diagnostika' ? CENY.diagnostika : 0,
                calouneni: {
                    sedaky: stav.sedaky,
                    operky: stav.operky,
                    podrucky: stav.podrucky,
                    panely: stav.panely,
                    rohovyDil: stav.rohovyDil,
                    ottoman: stav.ottoman
                },
                mechanika: {
                    relax: stav.relax,
                    vysuv: stav.vysuv
                },
                doplnky: {
                    tezkyNabytek: stav.tezkyNabytek,
                    material: stav.material
                }
            }
        };

        // Zavolat callback do protokolu
        if (typeof window.protokolKalkulacka !== 'undefined' &&
            typeof window.protokolKalkulacka.zpracovatVysledek === 'function') {
            window.protokolKalkulacka.zpracovatVysledek(kalkulaceData);
        } else {
            console.error('[Kalkulačka] Funkce protokolKalkulacka.zpracovatVysledek není dostupná!');
            alert('Chyba: Nelze přenést data do protokolu.');
        }
    };

})();
