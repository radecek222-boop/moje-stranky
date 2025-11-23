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
    async function otevritKalkulacku() {
        console.log('[Protokol-Kalkulačka] Otevírám modal...');

        try {
            // Zobrazit loading
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div style="width: 50px; height: 50px; border: 4px solid #ddd;
                                border-top: 4px solid #2D5016; border-radius: 50%;
                                animation: spin 1s linear infinite; margin: 0 auto 15px;"></div>
                    <p style="color: #666;">Načítám kalkulačku...</p>
                </div>
            `;

            // Přidat CSS animaci pro spinner pokud ještě neexistuje
            if (!document.getElementById('calc-spinner-style')) {
                const style = document.createElement('style');
                style.id = 'calc-spinner-style';
                style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
                document.head.appendChild(style);
            }

            // Zobrazit modal
            modalOverlay.style.display = 'flex';

            // Načíst HTML kalkulačky z cenik.php
            const response = await fetch('cenik.php');
            if (!response.ok) {
                throw new Error('Nepodařilo se načíst kalkulačku');
            }

            const html = await response.text();

            // Extrahovat pouze div s kalkulačkou
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const kalkulackaElement = doc.getElementById('kalkulacka');

            if (!kalkulackaElement) {
                throw new Error('Kalkulačka nebyla nalezena v HTML');
            }

            // Vložit kalkulačku do modalu
            modalBody.innerHTML = kalkulackaElement.outerHTML;

            // Nastavit kalkulačku do protokol režimu
            if (typeof window.nastavitKalkulackuRezim === 'function') {
                window.nastavitKalkulackuRezim('protokol');
                console.log('[Protokol-Kalkulačka] Režim nastaven na "protokol"');
            }

            // Reinicializovat kalkulačku (pokud je potřeba)
            // Některé event listenery by mohly potřebovat reinicializaci

            console.log('[Protokol-Kalkulačka] Kalkulačka načtena');

        } catch (error) {
            console.error('[Protokol-Kalkulačka] Chyba při načítání kalkulačky:', error);
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <h2>Chyba</h2>
                    <p style="color: #d32f2f;">Nepodařilo se načíst kalkulačku.</p>
                    <p>${error.message}</p>
                    <button class="btn-primary" onclick="window.protokolKalkulacka.zavritModal()">Zavřít</button>
                </div>
            `;
        }
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

        // Resetovat režim kalkulačky zpět na standalone
        if (typeof window.nastavitKalkulackuRezim === 'function') {
            window.nastavitKalkulackuRezim('standalone');
        }
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

            // Zobrazit notifikaci
            if (typeof showNotif === 'function') {
                showNotif('success', `Cena ${data.celkovaCena.toFixed(2)} € byla započítána`);
            }
        } else {
            console.error('[Protokol-Kalkulačka] Pole price-total nebo celková cena nenalezeny');
            if (typeof showNotif === 'function') {
                showNotif('error', 'Chyba při přenosu ceny');
            }
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
