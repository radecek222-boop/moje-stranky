/**
 * Integrace kalkulačky do protokolu - Step 116
 * - Kalkulačka je vložena přímo v HTML (ne dynamicky načítaná)
 * - Otevírá modal s kalkulačkou
 * - Zpracovává výsledek kalkulace
 * - Ukládá data kalkulace k reklamaci
 */

(function() {
    'use strict';

    let kalkulaceData = null; // Aktuální data z kalkulačky
    let modalOverlay = null;
    let kalkulackaInitialized = false;

    // ========================================
    // INICIALIZACE
    // ========================================
    document.addEventListener('DOMContentLoaded', () => {
        modalOverlay = document.getElementById('calculatorModalOverlay');
        const priceTotalInput = document.getElementById('price-total');

        if (!modalOverlay) {
            console.error('[Protokol-Kalkulačka] Modal overlay nenalezen!');
            return;
        }

        // Event listener pro otevření kalkulačky (kliknutí na pole)
        if (priceTotalInput) {
            priceTotalInput.addEventListener('click', otevritKalkulacku);
            priceTotalInput.style.cursor = 'pointer';
        }
    });

    // ========================================
    // OTEVŘENÍ MODALU S KALKULAČKOU
    // ========================================
    function otevritKalkulacku() {
        // Zobrazit modal přes Alpine.js API
        if (window.calculatorModal && window.calculatorModal.open) {
            window.calculatorModal.open();
        } else {
            // Fallback pro zpětnou kompatibilitu
            modalOverlay.style.display = 'flex';
        }

        // Inicializovat kalkulačku (pouze jednou nebo při resetu)
        if (!kalkulackaInitialized) {
            if (typeof window.initKalkulacka === 'function') {
                window.initKalkulacka();
                kalkulackaInitialized = true;
            } else {
                console.warn('[Protokol-Kalkulačka] Funkce initKalkulacka není dostupná');
            }
        }

        // Nastavit kalkulačku do protokol režimu
        if (typeof window.nastavitKalkulackuRezim === 'function') {
            window.nastavitKalkulackuRezim('protokol');
        }

        // Resetovat wizard na první krok
        resetovatWizardNaPrvniKrok();

        // Předvyplnit adresu z protokolu
        predvyplnitAdresu();
    }

    // ========================================
    // RESET WIZARDU NA PRVNÍ KROK
    // ========================================
    function resetovatWizardNaPrvniKrok() {
        const kalkulackaContainer = document.getElementById('kalkulacka');
        if (!kalkulackaContainer) return;

        // Schovat všechny kroky
        const vsechnyKroky = kalkulackaContainer.querySelectorAll('.wizard-step');
        vsechnyKroky.forEach(krok => {
            krok.classList.add('hidden');
        });

        // Zobrazit první krok
        const prvniKrok = kalkulackaContainer.querySelector('#step-address');
        if (prvniKrok) {
            prvniKrok.classList.remove('hidden');
        }

        // Resetovat progress indikátor
        const progressSteps = kalkulackaContainer.querySelectorAll('.progress-step');
        progressSteps.forEach((step, index) => {
            step.classList.remove('active', 'completed');
            if (index === 0) {
                step.classList.add('active');
            }
        });
    }

    // ========================================
    // PŘEDVYPLNĚNÍ ADRESY Z PROTOKOLU
    // ========================================
    function predvyplnitAdresu() {
        const customerAddress = document.getElementById('address')?.value;

        if (customerAddress && customerAddress.trim() !== '') {
            const calcAddressInput = document.getElementById('calc-address');
            if (calcAddressInput) {
                calcAddressInput.value = customerAddress;

                // Automaticky spustit vyhledávání adresy po krátké prodlevě
                setTimeout(() => {
                    const event = new Event('input', { bubbles: true });
                    calcAddressInput.dispatchEvent(event);
                }, 300);
            }
        }
    }

    // ========================================
    // ZAVŘENÍ MODALU
    // ========================================
    function zavritModal() {
        if (window.calculatorModal && window.calculatorModal.close) {
            window.calculatorModal.close();
        } else {
            modalOverlay.style.display = 'none';
        }

        // Resetovat režim kalkulačky zpět na standalone
        if (typeof window.nastavitKalkulackuRezim === 'function') {
            window.nastavitKalkulackuRezim('standalone');
        }
    }

    // ========================================
    // ZAPOČÍTÁNÍ DO PROTOKOLU
    // ========================================
    function zapocitatDoProtokolu() {
        // Získat celkovou cenu z kalkulačky
        const grandTotalElement = document.getElementById('grand-total');

        if (!grandTotalElement) {
            console.error('[Protokol-Kalkulačka] Element grand-total nenalezen');
            if (typeof showNotif === 'function') {
                showNotif('error', 'Chyba: Nepodařilo se získat celkovou cenu');
            }
            return;
        }

        // Extrahovat číselnou hodnotu z textu (např. "350 €" -> 350)
        const textCeny = grandTotalElement.textContent || grandTotalElement.innerText;
        const cenaCislo = parseFloat(textCeny.replace(/[^\d.,]/g, '').replace(',', '.'));

        if (isNaN(cenaCislo)) {
            console.error('[Protokol-Kalkulačka] Nepodařilo se parsovat cenu:', textCeny);
            if (typeof showNotif === 'function') {
                showNotif('error', 'Chyba: Neplatná hodnota ceny');
            }
            return;
        }

        // Přenést cenu do pole price-total
        const priceTotalInput = document.getElementById('price-total');
        if (priceTotalInput) {
            priceTotalInput.value = cenaCislo.toFixed(2) + ' €';

            // Zobrazit notifikaci
            if (typeof showNotif === 'function') {
                showNotif('success', `Cena ${cenaCislo.toFixed(2)} € byla započítána do protokolu`);
            }
        }

        // Zavřít modal
        zavritModal();

        // Uložit kalkulaci do databáze (pokud je známo ID reklamace)
        const kalkulaceDataObjekt = {
            celkovaCena: cenaCislo,
            datum: new Date().toISOString()
        };

        ulozitKalkulaciDoDB(kalkulaceDataObjekt);
    }

    // ========================================
    // ZPRACOVÁNÍ VÝSLEDKU KALKULACE (legacy)
    // ========================================
    function zpracovatVysledekKalkulace(data) {
        kalkulaceData = data;

        const priceTotalInput = document.getElementById('price-total');
        if (priceTotalInput && data.celkovaCena) {
            priceTotalInput.value = data.celkovaCena.toFixed(2) + ' €';

            if (typeof showNotif === 'function') {
                showNotif('success', `Cena ${data.celkovaCena.toFixed(2)} € byla započítána`);
            }
        }

        zavritModal();
        ulozitKalkulaciDoDB(data);
    }

    // ========================================
    // ULOŽENÍ KALKULACE DO DATABÁZE
    // ========================================
    async function ulozitKalkulaciDoDB(data) {
        try {
            const reklamaceId = window.currentReklamaceId || new URLSearchParams(window.location.search).get('id');

            if (!reklamaceId) {
                console.warn('[Protokol-Kalkulačka] Není známo ID reklamace, kalkulace nebude uložena');
                return;
            }

            const csrfToken = typeof window.fetchCsrfToken === 'function'
                ? await window.fetchCsrfToken()
                : document.querySelector('meta[name="csrf-token"]')?.content;

            if (!csrfToken) {
                console.error('[Protokol-Kalkulačka] Nepodařilo se získat CSRF token');
                return;
            }

            const response = await fetch('/api/protokol_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'save_kalkulace',
                    reklamace_id: reklamaceId,
                    kalkulace_data: data,
                    csrf_token: csrfToken
                })
            });

            const result = await response.json();

            if (result.status === 'success') {
                if (typeof window.kalkulaceData !== 'undefined') {
                    window.kalkulaceData = data;
                }
            } else {
                console.error('[Protokol-Kalkulačka] Chyba při ukládání:', result.message);
            }

        } catch (error) {
            console.error('[Protokol-Kalkulačka] Chyba při ukládání kalkulace:', error);
        }
    }

    // ========================================
    // EXPORT PRO GLOBÁLNÍ POUŽITÍ
    // ========================================
    window.protokolKalkulacka = {
        zpracovatVysledek: zpracovatVysledekKalkulace,
        zavritModal: zavritModal,
        zapocitatDoProtokolu: zapocitatDoProtokolu
    };

    // ========================================
    // ACTION REGISTRY - Step 116
    // ========================================
    function registrovatAkce() {
        if (typeof Utils !== 'undefined' && Utils.registerAction) {
            Utils.registerAction('zavritProtokolModal', zavritModal);
            Utils.registerAction('zapocitatDoProtokolu', zapocitatDoProtokolu);
        } else {
            // Zkusit znovu za chvíli pokud Utils ještě není načtené
            setTimeout(registrovatAkce, 100);
        }
    }

    // Spustit registraci akcí
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', registrovatAkce);
    } else {
        registrovatAkce();
    }

})();
