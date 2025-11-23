/**
 * Integrace kalkulačky do protokolu
 * - Otevírá modal s kalkulačkou
 * - Zpracovává výsledek kalkulace
 * - Ukládá data kalkulace k reklamaci
 */

(function() {
    'use strict';

    console.log('[Protokol-Kalkulačka] Inicializace integrace...');

    let kalkulaceData = null; // Aktuální data z kalkulačky
    let modalOverlay = null;
    let modalBody = null;

    // ========================================
    // INICIALIZACE
    // ========================================
    document.addEventListener('DOMContentLoaded', () => {
        console.log('[Protokol-Kalkulačka] DOM loaded');

        modalOverlay = document.getElementById('calculatorModalOverlay');
        modalBody = document.getElementById('calculatorModalBody');
        const openBtn = document.getElementById('open-calculator-btn');
        const closeBtn = document.getElementById('calculatorModalClose');

        if (!modalOverlay || !modalBody) {
            console.error('[Protokol-Kalkulačka] Modal elementy nenalezeny!');
            return;
        }

        // Event listener pro otevření kalkulačky
        if (openBtn) {
            openBtn.addEventListener('click', otevritKalkulacku);
        }

        // Event listener pro zavření modalu
        if (closeBtn) {
            closeBtn.addEventListener('click', zavritModal);
        }

        // Zavřít při kliknutí mimo modal
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) {
                zavritModal();
            }
        });

        console.log('[Protokol-Kalkulačka] Inicializace dokončena');
    });

    // ========================================
    // OTEVŘENÍ MODALU S KALKULAČKOU
    // ========================================
    function otevritKalkulacku() {
        console.log('[Protokol-Kalkulačka] Otevírám modal...');

        // Načíst HTML strukturu kalkulačky
        fetch('/cenik.php')
            .then(response => response.text())
            .then(html => {
                // Extrahovat pouze sekci kalkulačky z HTML
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                // Najít calculator-section
                const calculatorSection = doc.querySelector('#kalkulacka');

                if (!calculatorSection) {
                    console.error('[Protokol-Kalkulačka] Sekce kalkulačky nenalezena v HTML!');
                    alert('Chyba při načítání kalkulačky');
                    return;
                }

                // Vložit HTML do modalu
                modalBody.innerHTML = calculatorSection.outerHTML;

                // Zobrazit modal
                modalOverlay.style.display = 'flex';

                // Inicializovat kalkulačku (pokud má vlastní init funkci)
                setTimeout(() => {
                    inicializovatKalkulackuVModalu();
                }, 100);

                console.log('[Protokol-Kalkulačka] Modal otevřen');
            })
            .catch(error => {
                console.error('[Protokol-Kalkulačka] Chyba při načítání kalkulačky:', error);
                alert('Chyba při načítání kalkulačky');
            });
    }

    // ========================================
    // INICIALIZACE KALKULAČKY V MODALU
    // ========================================
    function inicializovatKalkulackuVModalu() {
        console.log('[Protokol-Kalkulačka] Inicializuji kalkulačku v modalu...');

        // Zkontrolovat jestli existuje globální funkce z cenik-calculator.js
        // (Kalkulačka by měla být již načtená přes defer)

        // TODO: Potřebujeme upravit cenik-calculator.js aby:
        // 1. Neexportoval PDF přímo
        // 2. Místo toho zobrazil souhrn s tlačítky Zpět/Započítat

        console.log('[Protokol-Kalkulačka] Kalkulačka inicializována');
    }

    // ========================================
    // ZAVŘENÍ MODALU
    // ========================================
    function zavritModal() {
        console.log('[Protokol-Kalkulačka] Zavírám modal...');
        modalOverlay.style.display = 'none';
        modalBody.innerHTML = '';
        kalkulaceData = null;
    }

    // ========================================
    // ZPRACOVÁNÍ VÝSLEDKU KALKULACE
    // ========================================
    function zpracovatVysledekKalkulace(data) {
        console.log('[Protokol-Kalkulačka] Zpracovávám výsledek:', data);

        // Uložit data kalkulace
        kalkulaceData = data;

        // Přenést celkovou cenu do pole
        const priceTotalInput = document.getElementById('price-total');
        if (priceTotalInput && data.celkovaCena) {
            priceTotalInput.value = data.celkovaCena.toFixed(2);
        }

        // Zavřít modal
        zavritModal();

        // Uložit kalkulaci do databáze
        ulozitKalkulaciDoDB(data);

        console.log('[Protokol-Kalkulačka] Výsledek zpracován');
    }

    // ========================================
    // ULOŽENÍ KALKULACE DO DATABÁZE
    // ========================================
    async function ulozitKalkulaciDoDB(data) {
        try {
            console.log('[Protokol-Kalkulačka] Ukládám kalkulaci do DB...');

            // Získat ID reklamace
            const reklamaceId = currentReklamaceId || new URLSearchParams(window.location.search).get('id');

            if (!reklamaceId) {
                console.warn('[Protokol-Kalkulačka] Není známo ID reklamace, kalkulace nebude uložena');
                return;
            }

            // Získat CSRF token
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;

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
                console.log('[Protokol-Kalkulačka] Kalkulace uložena do DB');
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
        zavritModal: zavritModal
    };

})();
