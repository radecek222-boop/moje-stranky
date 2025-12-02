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
    // Step 40: Migrace na Alpine.js - close/overlay click handlery přesunuty do calculatorModal komponenty
    // ========================================
    document.addEventListener('DOMContentLoaded', () => {
        console.log('[Protokol-Kalkulačka] DOM loaded');

        modalOverlay = document.getElementById('calculatorModalOverlay');
        modalBody = document.getElementById('calculatorModalBody');
        const priceTotalInput = document.getElementById('price-total');

        if (!modalOverlay || !modalBody) {
            console.error('[Protokol-Kalkulačka] Modal elementy nenalezeny!');
            return;
        }

        // Event listener pro otevření kalkulačky (kliknutí na pole)
        if (priceTotalInput) {
            priceTotalInput.addEventListener('click', otevritKalkulacku);
            // Přidat vizuální indikátor že pole je klikatelné
            priceTotalInput.style.cursor = 'pointer';
        }

        // Step 40: Zavírání modalu nyní řeší Alpine.js (closeBtn, overlay click, ESC)
        // Vanilla JS event listenery odstraněny

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
                                border-top: 4px solid #333333; border-radius: 50%;
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

            // Step 40: Zobrazit modal přes Alpine.js API
            if (window.calculatorModal && window.calculatorModal.open) {
                window.calculatorModal.open();
            } else {
                // Fallback pro zpětnou kompatibilitu
                modalOverlay.style.display = 'flex';
            }

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

            // Reinicializovat kalkulačku - připojit event listenery k novému DOMu
            if (typeof window.initKalkulacka === 'function') {
                window.initKalkulacka();
                console.log('[Protokol-Kalkulačka] Kalkulačka reinicializována');
            } else {
                console.warn('[Protokol-Kalkulačka] Funkce initKalkulacka není dostupná');
            }

            // Nastavit kalkulačku do protokol režimu
            if (typeof window.nastavitKalkulackuRezim === 'function') {
                window.nastavitKalkulackuRezim('protokol');
                console.log('[Protokol-Kalkulačka] Režim nastaven na "protokol"');
            }

            // Načíst data zákazníka z protokolu
            const customerAddress = document.getElementById('address')?.value;
            const customerName = document.getElementById('customer')?.value;
            const claimNumber = document.getElementById('claim-number')?.value;

            console.log('[Protokol-Kalkulačka] Data zákazníka:', {
                address: customerAddress,
                name: customerName,
                claim: claimNumber
            });

            // Předvyplnit adresu do kalkulačky
            if (customerAddress && customerAddress.trim() !== '') {
                const calcAddressInput = document.getElementById('calc-address');
                if (calcAddressInput) {
                    calcAddressInput.value = customerAddress;
                    console.log('[Protokol-Kalkulačka] Adresa předvyplněna:', customerAddress);

                    // Automaticky spustit vyhledávání adresy po krátké prodlevě
                    setTimeout(() => {
                        // Simulovat input event pro spuštění autocomplete
                        const event = new Event('input', { bubbles: true });
                        calcAddressInput.dispatchEvent(event);
                    }, 300);
                }
            }

            console.log('[Protokol-Kalkulačka] Kalkulačka načtena');

        } catch (error) {
            console.error('[Protokol-Kalkulačka] Chyba při načítání kalkulačky:', error);
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <h2>Chyba</h2>
                    <p style="color: #d32f2f;">Nepodařilo se načíst kalkulačku.</p>
                    <p>${error.message}</p>
                    <button class="btn-primary" data-action="zavritProtokolModal">Zavřít</button>
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
    // Step 40: Migrace na Alpine.js
    // ========================================
    function zavritModal() {
        console.log('[Protokol-Kalkulačka] Zavírám modal...');

        // Step 40: Zavřít modal přes Alpine.js API
        if (window.calculatorModal && window.calculatorModal.close) {
            window.calculatorModal.close();
        } else {
            // Fallback pro zpětnou kompatibilitu
            modalOverlay.style.display = 'none';
        }

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

            // Získat CSRF token z meta tagu nebo přes API
            const csrfToken = await window.fetchCsrfToken();

            if (!csrfToken) {
                console.error('[Protokol-Kalkulačka] Nepodařilo se získat CSRF token');
                return;
            }

            console.log('[Protokol-Kalkulačka] CSRF token získán');

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

                // KRITICKÁ OPRAVA: Aktualizovat globální proměnnou
                if (typeof window.kalkulaceData !== 'undefined') {
                    window.kalkulaceData = data;
                    console.log('[Protokol-Kalkulačka] Globální kalkulaceData aktualizována');
                    console.log('[Protokol-Kalkulačka] Celková cena:', data.celkovaCena, '€');
                } else {
                    console.warn('[Protokol-Kalkulačka] window.kalkulaceData není definována');
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
        zavritModal: zavritModal
    };

    // ========================================
    // ACTION REGISTRY - Step 115
    // ========================================
    if (typeof Utils !== 'undefined' && Utils.registerAction) {
        Utils.registerAction('zavritProtokolModal', () => {
            if (window.protokolKalkulacka && typeof window.protokolKalkulacka.zavritModal === 'function') {
                window.protokolKalkulacka.zavritModal();
            }
        });
    }

})();
