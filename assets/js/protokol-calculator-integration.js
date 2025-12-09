/**
 * Integrace kalkulačky do protokolu - ZJEDNODUŠENÁ VERZE
 * Kalkulačka funguje STEJNĚ jako v ceníku, jen je v modalu
 */

(function() {
    'use strict';

    let modalOverlay = null;

    // Inicializace při načtení stránky
    document.addEventListener('DOMContentLoaded', () => {
        modalOverlay = document.getElementById('calculatorModalOverlay');
        const priceTotalInput = document.getElementById('price-total');

        if (!modalOverlay) {
            console.error('[Protokol-Kalkulačka] Modal overlay nenalezen!');
            return;
        }

        // Kliknutí na pole ceny otevře kalkulačku
        if (priceTotalInput) {
            priceTotalInput.addEventListener('click', otevritModal);
            priceTotalInput.style.cursor = 'pointer';
        }
    });

    // Otevření modalu
    function otevritModal() {
        // Otevřít modal
        if (window.calculatorModal && window.calculatorModal.open) {
            window.calculatorModal.open();
        } else {
            modalOverlay.style.display = 'flex';
        }

        // Nastavit režim protokolu (změní tlačítka v souhrnu)
        if (typeof window.nastavitKalkulackuRezim === 'function') {
            window.nastavitKalkulackuRezim('protokol');
        }

        // Resetovat wizard na první krok při každém otevření
        if (typeof window.resetovatKalkulacku === 'function') {
            window.resetovatKalkulacku();
        }

        // Předvyplnit adresu z protokolu do kalkulačky
        const adresaProtokol = document.getElementById('address');
        if (adresaProtokol && adresaProtokol.value.trim()) {
            // Počkat až se modal zobrazí, pak předvyplnit a vyhledat vzdálenost
            setTimeout(() => {
                if (typeof window.predvyplnitAdresu === 'function') {
                    window.predvyplnitAdresu(adresaProtokol.value.trim());
                }
            }, 200);
        }
    }

    // Zavření modalu
    function zavritModal() {
        if (window.calculatorModal && window.calculatorModal.close) {
            window.calculatorModal.close();
        } else if (modalOverlay) {
            modalOverlay.style.display = 'none';
        }

        // Vrátit režim zpět na standalone
        if (typeof window.nastavitKalkulackuRezim === 'function') {
            window.nastavitKalkulackuRezim('standalone');
        }
    }

    // Zpracovat výsledek kalkulace (voláno z cenik-calculator.js)
    function zpracovatVysledek(data) {
        // Uložit data kalkulace do globální proměnné pro PDF export
        window.kalkulaceData = data;

        // Přenést celkovou cenu do pole
        const priceTotalInput = document.getElementById('price-total');
        if (priceTotalInput && data && data.celkovaCena !== undefined) {
            priceTotalInput.value = data.celkovaCena.toFixed(2) + ' €';

            if (typeof wgsToast !== 'undefined' && wgsToast.success) {
                wgsToast.success('Cena ' + data.celkovaCena.toFixed(2) + ' € byla započítána');
            }
        } else {
            console.error('[Protokol-Kalkulačka] Chyba: data nebo celkovaCena chybí');
        }

        // Zavřít modal
        zavritModal();
    }

    // Jednoduchá verze započítání (čte přímo z DOM)
    function zapocitatDoProtokolu() {
        const grandTotalElement = document.getElementById('grand-total');

        if (!grandTotalElement) {
            console.error('[Protokol-Kalkulačka] Element grand-total nenalezen');
            return;
        }

        // Extrahovat cenu z textu (např. "350 €" -> 350)
        const textCeny = grandTotalElement.textContent || grandTotalElement.innerText;
        const cenaCislo = parseFloat(textCeny.replace(/[^\d.,]/g, '').replace(',', '.'));

        if (isNaN(cenaCislo)) {
            console.error('[Protokol-Kalkulačka] Neplatná cena:', textCeny);
            return;
        }

        // Vytvořit základní kalkulaceData ze stavu kalkulačky
        if (window.stav) {
            window.kalkulaceData = {
                celkovaCena: cenaCislo,
                adresa: window.stav.adresa || document.getElementById('address')?.value || '',
                vzdalenost: window.stav.vzdalenost || 0,
                dopravne: window.stav.dopravne || 0,
                reklamaceBezDopravy: window.stav.reklamaceBezDopravy || false,
                vyzvednutiSklad: window.stav.vyzvednutiSklad || false,
                typServisu: window.stav.typServisu || 'calouneni',
                tezkyNabytek: window.stav.tezkyNabytek || false,
                material: window.stav.material || false,
                dilyPrace: [],
                sluzby: []
            };
        }

        // Přenést cenu do protokolu
        const priceTotalInput = document.getElementById('price-total');
        if (priceTotalInput) {
            priceTotalInput.value = cenaCislo.toFixed(2) + ' €';

            if (typeof wgsToast !== 'undefined' && wgsToast.success) {
                wgsToast.success('Cena ' + cenaCislo.toFixed(2) + ' € byla započítána');
            }
        }

        // Zavřít modal
        zavritModal();
    }

    // Export pro globální použití
    window.protokolKalkulacka = {
        zavritModal: zavritModal,
        zapocitatDoProtokolu: zapocitatDoProtokolu,
        zpracovatVysledek: zpracovatVysledek
    };

    // Registrace akcí pro data-action atributy
    function registrovatAkce() {
        if (typeof Utils !== 'undefined' && Utils.registerAction) {
            Utils.registerAction('zavritProtokolModal', zavritModal);
            Utils.registerAction('zapocitatDoProtokolu', zapocitatDoProtokolu);
        } else {
            setTimeout(registrovatAkce, 100);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', registrovatAkce);
    } else {
        registrovatAkce();
    }

})();
