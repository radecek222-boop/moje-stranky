/**
 * Integrace kalkulačky do protokolu - ROZŠÍŘENÁ VERZE
 * Pro POZ zakázky nabízí volbu: Načíst z CN nebo Kalkulačka
 */

(function() {
    'use strict';

    let modalOverlay = null;
    let wgsDialogOverlay = null;

    // Helper pro zobrazení notifikace (s fallbackem na alert)
    function zobrazitZpravu(text, typ = 'info') {
        if (typeof wgsToast !== 'undefined') {
            if (typ === 'error') wgsToast.error(text);
            else if (typ === 'warning') wgsToast.warning(text);
            else if (typ === 'success') wgsToast.success(text);
            else wgsToast.info(text);
        } else {
            alert(text);
        }
    }

    // Inicializace při načtení stránky
    document.addEventListener('DOMContentLoaded', () => {
        console.log('[Protokol-CN] === INICIALIZACE ZAHÁJENA ===');

        modalOverlay = document.getElementById('calculatorModalOverlay');
        const priceTotalInput = document.getElementById('price-total');

        if (!modalOverlay) {
            console.warn('[Protokol-CN] Modal overlay nenalezen - kalkulačka nebude dostupná');
        }

        // Vytvořit WGS dialog pro výběr (funguje i bez kalkulačky)
        vytvorWgsDialog();
        console.log('[Protokol-CN] WGS dialog vytvořen');

        // Kliknutí na pole ceny - MUSÍ být první handler!
        if (priceTotalInput) {
            // Odstranit případné existující handlery přidáním nového elementu
            priceTotalInput.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                console.log('[Protokol-CN] Kliknuto na price-total');
                zpracovatKliknutiNaCenu();
            }, true); // capture phase - spustí se jako první

            priceTotalInput.style.cursor = 'pointer';
            console.log('[Protokol-CN] Event listener přidán na price-total (capture phase)');

            // Debug: vypsat hodnotu claim-number (číslo reklamace - POZ zakázky začínají na POZ)
            const claimNum = document.getElementById('claim-number');
            console.log('[Protokol-CN] Claim number element:', claimNum);
            console.log('[Protokol-CN] Claim number value:', claimNum?.value);
        } else {
            console.error('[Protokol-CN] price-total element NENALEZEN!');
        }

        console.log('[Protokol-CN] === INICIALIZACE DOKONČENA ===');
    });

    // Zkontrolovat zda jde o POZ zakázku (kontroluje claim-number = Číslo reklamace)
    function jePozZakazka() {
        // claim-number obsahuje číslo reklamace (např. POZ/2025/08-12/01 pro mimozáruční)
        const claimNumber = document.getElementById('claim-number');
        console.log('[Protokol-CN] jePozZakazka() - claim-number element:', claimNumber);

        if (!claimNumber) {
            console.log('[Protokol-CN] jePozZakazka() - claim-number NENALEZEN -> false');
            return false;
        }

        const hodnota = claimNumber.value.trim().toUpperCase();
        console.log('[Protokol-CN] jePozZakazka() - claim-number hodnota:', hodnota);

        const jePoz = hodnota.startsWith('POZ');
        console.log('[Protokol-CN] jePozZakazka() - je POZ zakázka:', jePoz);

        return jePoz;
    }

    // Zpracovat kliknutí na pole ceny
    function zpracovatKliknutiNaCenu() {
        const jePoz = jePozZakazka();

        if (jePoz) {
            // POZ zakázka - zobrazit dialog s volbou
            zobrazitWgsDialog();
        } else {
            // Běžná zakázka - rovnou kalkulačka
            otevritKalkulacku();
        }
    }

    // Vytvořit WGS dialog
    function vytvorWgsDialog() {
        // Overlay
        wgsDialogOverlay = document.createElement('div');
        wgsDialogOverlay.id = 'wgsDialogOverlay';
        wgsDialogOverlay.style.cssText = `
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        `;

        // Dialog box
        const dialogBox = document.createElement('div');
        dialogBox.style.cssText = `
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            text-align: center;
        `;

        dialogBox.innerHTML = `
            <h2 style="margin: 0 0 10px 0; font-size: 20px; color: #333; font-weight: 600;">WGS</h2>
            <p style="margin: 0 0 25px 0; font-size: 14px; color: #666;">Mimozáruční oprava - vyberte způsob zadání ceny</p>

            <div style="display: flex; flex-direction: column; gap: 12px;">
                <button id="btnNacistZCn" style="
                    background: #333;
                    color: #fff;
                    border: none;
                    padding: 15px 30px;
                    border-radius: 8px;
                    font-size: 15px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: background 0.2s;
                ">Načíst z CN</button>

                <button id="btnKalkulacka" style="
                    background: #f5f5f5;
                    color: #333;
                    border: 1px solid #ddd;
                    padding: 15px 30px;
                    border-radius: 8px;
                    font-size: 15px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: background 0.2s;
                ">Kalkulačka</button>

                <button id="btnZrusitDialog" style="
                    background: transparent;
                    color: #999;
                    border: none;
                    padding: 10px;
                    font-size: 13px;
                    cursor: pointer;
                    margin-top: 5px;
                ">Zrušit</button>
            </div>
        `;

        wgsDialogOverlay.appendChild(dialogBox);
        document.body.appendChild(wgsDialogOverlay);

        // Event listenery
        document.getElementById('btnNacistZCn').addEventListener('click', () => {
            zavritWgsDialog();
            zobrazitVyberCenovychNabidek();
        });

        document.getElementById('btnKalkulacka').addEventListener('click', () => {
            zavritWgsDialog();
            otevritKalkulacku();
        });

        document.getElementById('btnZrusitDialog').addEventListener('click', zavritWgsDialog);

        // Zavřít kliknutím mimo
        wgsDialogOverlay.addEventListener('click', (e) => {
            if (e.target === wgsDialogOverlay) {
                zavritWgsDialog();
            }
        });
    }

    // Zobrazit WGS dialog
    function zobrazitWgsDialog() {
        console.log('[Protokol-CN] zobrazitWgsDialog() - overlay:', wgsDialogOverlay);

        if (wgsDialogOverlay) {
            wgsDialogOverlay.style.display = 'flex';
            console.log('[Protokol-CN] WGS dialog ZOBRAZEN');
        } else {
            console.error('[Protokol-CN] WGS dialog overlay NENALEZEN!');
        }
    }

    // Zavřít WGS dialog
    function zavritWgsDialog() {
        if (wgsDialogOverlay) {
            wgsDialogOverlay.style.display = 'none';
        }
    }

    // Zobrazit výběr cenových nabídek
    async function zobrazitVyberCenovychNabidek() {
        const emailInput = document.getElementById('email');
        const email = emailInput ? emailInput.value.trim() : '';

        if (!email) {
            zobrazitZpravu('Email zákazníka není vyplněn', 'error');
            return;
        }

        // Načíst cenové nabídky pro email
        try {
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

            const formData = new FormData();
            formData.append('action', 'seznam_pro_email');
            formData.append('email', email);
            formData.append('csrf_token', csrfToken);

            const response = await fetch('/api/nabidka_api.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.status !== 'success' || !result.data || result.data.length === 0) {
                zobrazitZpravu('Pro tento email nebyly nalezeny žádné cenové nabídky', 'warning');
                // Nabídnout kalkulačku jako alternativu
                otevritKalkulacku();
                return;
            }

            // Zobrazit dialog s výběrem nabídek
            zobrazitSeznamNabidek(result.data);

        } catch (error) {
            console.error('[Protokol-CN] Chyba při načítání nabídek:', error);
            zobrazitZpravu('Chyba při načítání cenových nabídek', 'error');
        }
    }

    // Zobrazit seznam nabídek pro výběr
    function zobrazitSeznamNabidek(nabidky) {
        // Vytvořit overlay pro seznam
        const overlay = document.createElement('div');
        overlay.id = 'cnSeznamOverlay';
        overlay.style.cssText = `
            display: flex;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 10001;
            justify-content: center;
            align-items: center;
        `;

        // Sestavit HTML seznam
        let seznamHtml = nabidky.map(n => {
            const stav = n.stav === 'potvrzena' ? 'Potvrzena' : (n.stav === 'odeslana' ? 'Odeslána' : n.stav);
            const datum = n.vytvoreno_at ? new Date(n.vytvoreno_at).toLocaleDateString('cs-CZ') : '';
            const cena = parseFloat(n.celkova_cena).toFixed(2);

            return `
                <div class="cn-polozka" data-id="${n.id}" data-cena="${n.celkova_cena}" data-cislo="${n.cislo_nabidky || 'CN-' + n.id}" style="
                    padding: 15px;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    margin-bottom: 10px;
                    cursor: pointer;
                    transition: all 0.2s;
                    background: #fff;
                ">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="font-size: 15px; color: #333;">${n.cislo_nabidky || 'CN-' + n.id}</strong>
                            <span style="font-size: 12px; color: #888; margin-left: 10px;">${datum}</span>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 18px; font-weight: 600; color: #333;">${cena} €</span>
                            <div style="font-size: 11px; color: ${n.stav === 'potvrzena' ? '#28a745' : '#666'};">${stav}</div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        const dialogBox = document.createElement('div');
        dialogBox.style.cssText = `
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        `;

        dialogBox.innerHTML = `
            <h2 style="margin: 0 0 5px 0; font-size: 18px; color: #333; font-weight: 600;">Cenové nabídky</h2>
            <p style="margin: 0 0 20px 0; font-size: 13px; color: #666;">Vyberte nabídku pro načtení ceny</p>

            <div id="cnSeznam" style="margin-bottom: 15px;">
                ${seznamHtml}
            </div>

            <button id="btnZavritCnSeznam" style="
                width: 100%;
                background: #f5f5f5;
                color: #666;
                border: 1px solid #ddd;
                padding: 12px;
                border-radius: 8px;
                font-size: 14px;
                cursor: pointer;
            ">Zrušit</button>
        `;

        overlay.appendChild(dialogBox);
        document.body.appendChild(overlay);

        // Event listenery pro položky
        dialogBox.querySelectorAll('.cn-polozka').forEach(polozka => {
            polozka.addEventListener('mouseenter', () => {
                polozka.style.borderColor = '#333';
                polozka.style.background = '#f9f9f9';
            });
            polozka.addEventListener('mouseleave', () => {
                polozka.style.borderColor = '#ddd';
                polozka.style.background = '#fff';
            });
            polozka.addEventListener('click', () => {
                const cena = parseFloat(polozka.dataset.cena);
                const cislo = polozka.dataset.cislo;
                aplikovatCenuZCn(cena, cislo);
                document.body.removeChild(overlay);
            });
        });

        // Zavřít tlačítko
        document.getElementById('btnZavritCnSeznam').addEventListener('click', () => {
            document.body.removeChild(overlay);
        });

        // Zavřít kliknutím mimo
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                document.body.removeChild(overlay);
            }
        });
    }

    // Aplikovat cenu z cenové nabídky
    function aplikovatCenuZCn(cena, cisloNabidky) {
        const priceTotalInput = document.getElementById('price-total');

        if (priceTotalInput) {
            priceTotalInput.value = cena.toFixed(2) + ' €';

            // Uložit data pro PDF
            window.kalkulaceData = {
                celkovaCena: cena,
                zdrojCeny: 'cn',
                cisloNabidky: cisloNabidky
            };

            zobrazitZpravu(`Cena ${cena.toFixed(2)} € načtena z ${cisloNabidky}`, 'success');
        }
    }

    // Otevření kalkulačky (původní funkce)
    function otevritKalkulacku() {
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
    async function zpracovatVysledek(data) {
        // Uložit data kalkulace do globální proměnné pro PDF export
        window.kalkulaceData = data;

        // Přenést celkovou cenu do pole
        const priceTotalInput = document.getElementById('price-total');
        if (priceTotalInput && data && data.celkovaCena !== undefined) {
            priceTotalInput.value = data.celkovaCena.toFixed(2) + ' €';
            zobrazitZpravu('Cena ' + data.celkovaCena.toFixed(2) + ' € byla započítána', 'success');
        } else {
            console.error('[Protokol-Kalkulačka] Chyba: data nebo celkovaCena chybí');
        }

        // KRITICKÉ: Uložit kalkulaci do databáze
        await ulozitKalkulaciDoDb(data);

        // Zavřít modal
        zavritModal();
    }

    // Uložit kalkulaci do databáze
    async function ulozitKalkulaciDoDb(kalkulaceData) {
        try {
            // Získat ID reklamace
            const claimNumber = document.getElementById('claim-number');
            if (!claimNumber || !claimNumber.value.trim()) {
                console.warn('[Protokol-Kalkulačka] Číslo reklamace není vyplněno - kalkulace nebude uložena do DB');
                return;
            }

            const reklamaceId = claimNumber.value.trim();
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

            if (!csrfToken) {
                console.error('[Protokol-Kalkulačka] CSRF token nenalezen!');
                return;
            }

            // Připravit data
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('reklamace_id', reklamaceId);
            formData.append('kalkulace_data', JSON.stringify(kalkulaceData));

            console.log('[Protokol-Kalkulačka] Ukládám kalkulaci do DB:', reklamaceId);

            // Odeslat na server
            const response = await fetch('/api/save_kalkulace_api.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.status === 'success') {
                console.log('[Protokol-Kalkulačka] ✅ Kalkulace uložena do databáze');
            } else {
                console.error('[Protokol-Kalkulačka] ❌ Chyba při ukládání kalkulace:', result.message);
            }

        } catch (error) {
            console.error('[Protokol-Kalkulačka] ❌ Síťová chyba při ukládání kalkulace:', error);
        }
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
                druhaOsoba: window.stav.druhaOsoba || false,
                material: window.stav.material || false,
                // KRITICKÉ: Přidat rozpis objekt pro transformaci v sendToCustomer()
                rozpis: {
                    diagnostika: window.stav.diagnostika || 0,
                    calouneni: window.stav.calouneni || null,
                    mechanika: window.stav.mechanika || null,
                    doplnky: window.stav.doplnky || null
                },
                dilyPrace: [],
                sluzby: []
            };

            console.log('[Protokol-Kalkulačka] ✅ Kalkulace data vytvořena s rozpis objektem');
            console.log('[Protokol-Kalkulačka] Rozpis:', window.kalkulaceData.rozpis);
        }

        // Přenést cenu do protokolu
        const priceTotalInput = document.getElementById('price-total');
        if (priceTotalInput) {
            priceTotalInput.value = cenaCislo.toFixed(2) + ' €';
            zobrazitZpravu('Cena ' + cenaCislo.toFixed(2) + ' € byla započítána', 'success');
        }

        // Zavřít modal
        zavritModal();
    }

    // Export pro globální použití
    window.protokolKalkulacka = {
        zavritModal: zavritModal,
        zapocitatDoProtokolu: zapocitatDoProtokolu,
        zpracovatVysledek: zpracovatVysledek,
        otevritKalkulacku: otevritKalkulacku,
        zobrazitVyberCenovychNabidek: zobrazitVyberCenovychNabidek
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
