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

    // Započítat cenu do protokolu
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
        zapocitatDoProtokolu: zapocitatDoProtokolu
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
