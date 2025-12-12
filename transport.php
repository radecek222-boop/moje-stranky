<?php
/**
 * TRANSPORT - Techmission Festival / United Music Events
 * Přehled transportů pro řidiče
 * Dočasná stránka - bude odstraněna po akci
 */

// Bez přihlášení - přístup má kdokoli s odkazem
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transport - Techmission</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #111;
            color: #fff;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Loga */
        .loga {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 60px;
            padding: 30px 0;
            border-bottom: 1px solid #333;
            margin-bottom: 30px;
        }

        .loga img {
            max-height: 80px;
            max-width: 200px;
            object-fit: contain;
        }

        .logo-placeholder {
            background: #222;
            padding: 20px 40px;
            border-radius: 8px;
            color: #666;
            font-size: 14px;
            text-align: center;
        }

        /* Řidiči */
        .ridici {
            display: flex;
            justify-content: center;
            gap: 60px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .ridic {
            text-align: center;
            padding: 20px 30px;
            background: #1a1a1a;
            border-radius: 12px;
            border: 1px solid #333;
        }

        .ridic-jmeno {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #fff;
        }

        .ridic-telefon {
            font-size: 22px;
            color: #0099ff;
            text-decoration: none;
            display: block;
        }

        .ridic-telefon:hover {
            text-decoration: underline;
        }

        /* Dny */
        .den {
            margin-bottom: 40px;
        }

        .den-nazev {
            font-size: 24px;
            font-weight: 700;
            padding: 15px 20px;
            background: #222;
            border-radius: 8px 8px 0 0;
            border-bottom: 2px solid #444;
        }

        .den-nazev.vikend {
            background: #2a2a2a;
        }

        /* Transporty */
        .transporty {
            border: 1px solid #333;
            border-top: none;
            border-radius: 0 0 8px 8px;
            overflow: hidden;
        }

        .transport {
            display: grid;
            grid-template-columns: 100px 200px 200px 180px;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #333;
            transition: background 0.2s;
        }

        .transport:last-child {
            border-bottom: none;
        }

        .transport:hover {
            background: #1a1a1a;
        }

        /* Víkendové dny - lehce jiný odstín */
        .sobota .transport {
            background: #161616;
        }

        .sobota .transport:hover {
            background: #1c1c1c;
        }

        .nedele .transport {
            background: #1a1a1a;
        }

        .nedele .transport:hover {
            background: #202020;
        }

        .transport-cas {
            font-size: 20px;
            font-weight: 700;
            color: #fff;
        }

        .transport-odkud,
        .transport-kam {
            font-size: 16px;
            color: #ccc;
        }

        .transport-odkud::before {
            content: 'Z: ';
            color: #666;
        }

        .transport-kam::before {
            content: 'DO: ';
            color: #666;
        }

        /* Stav */
        .transport-stav {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .stav-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 140px;
            text-transform: uppercase;
        }

        .stav-wait {
            background: #333;
            color: #fff;
        }

        .stav-wait:hover {
            background: #444;
        }

        .stav-ontheway {
            background: #0099ff;
            color: #fff;
            animation: pulse 2s infinite;
        }

        .stav-dropoff {
            background: #28a745;
            color: #fff;
        }

        .stav-cas {
            font-size: 12px;
            color: #888;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Header tabulky */
        .transport-header {
            display: grid;
            grid-template-columns: 100px 200px 200px 180px;
            padding: 12px 20px;
            background: #333;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            color: #999;
            letter-spacing: 1px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .transport,
            .transport-header {
                grid-template-columns: 80px 1fr 1fr 120px;
                gap: 10px;
            }

            .transport-cas {
                font-size: 16px;
            }

            .transport-odkud,
            .transport-kam {
                font-size: 14px;
            }

            .stav-btn {
                padding: 8px 12px;
                font-size: 12px;
                min-width: 100px;
            }

            .ridici {
                gap: 20px;
            }

            .ridic-jmeno {
                font-size: 22px;
            }

            .ridic-telefon {
                font-size: 18px;
            }

            .loga {
                gap: 30px;
            }

            .loga img {
                max-height: 50px;
            }
        }

        /* Poznámka */
        .poznamka {
            text-align: center;
            padding: 20px;
            background: #1a1a1a;
            border-radius: 8px;
            margin-top: 30px;
            color: #888;
            font-size: 14px;
        }

        .poznamka strong {
            color: #0099ff;
        }
    </style>
</head>
<body>
    <div class="container">

        <!-- Loga -->
        <div class="loga">
            <!-- Nahraďte src vlastními logy -->
            <div class="logo-placeholder">
                <img src="uploads/logo-united-music.png" alt="United Music"
                     onerror="this.style.display='none'; this.parentElement.innerHTML='UNITED MUSIC';">
            </div>
            <div class="logo-placeholder">
                <img src="uploads/logo-techmission.png" alt="Techmission"
                     onerror="this.style.display='none'; this.parentElement.innerHTML='TECHMISSION';">
            </div>
        </div>

        <!-- Řidiči -->
        <div class="ridici">
            <div class="ridic">
                <div class="ridic-jmeno">MIREK</div>
                <a href="tel:+420736611777" class="ridic-telefon">+420 736 611 777</a>
            </div>
            <div class="ridic">
                <div class="ridic-jmeno">MILAN</div>
                <a href="tel:+420735084519" class="ridic-telefon">+420 735 084 519</a>
            </div>
        </div>

        <!-- SOBOTA 13.12. -->
        <div class="den sobota">
            <div class="den-nazev vikend">SOBOTA 13.12.</div>
            <div class="transport-header">
                <div>PICK UP</div>
                <div>FROM</div>
                <div>TO</div>
                <div>STATUS</div>
            </div>
            <div class="transporty">
                <div class="transport" data-id="1">
                    <div class="transport-cas">21:30</div>
                    <div class="transport-odkud">Marriott Airport</div>
                    <div class="transport-kam">venue</div>
                    <div class="transport-stav">
                        <button class="stav-btn stav-wait" onclick="zmenStav(this, 1)">WAIT</button>
                        <span class="stav-cas"></span>
                    </div>
                </div>
                <div class="transport" data-id="2">
                    <div class="transport-cas">22:30</div>
                    <div class="transport-odkud">Marriott Airport</div>
                    <div class="transport-kam">venue</div>
                    <div class="transport-stav">
                        <button class="stav-btn stav-wait" onclick="zmenStav(this, 2)">WAIT</button>
                        <span class="stav-cas"></span>
                    </div>
                </div>
                <div class="transport" data-id="3">
                    <div class="transport-cas">22:30</div>
                    <div class="transport-odkud">Marriott Airport</div>
                    <div class="transport-kam">venue</div>
                    <div class="transport-stav">
                        <button class="stav-btn stav-wait" onclick="zmenStav(this, 3)">WAIT</button>
                        <span class="stav-cas"></span>
                    </div>
                </div>
                <div class="transport" data-id="4">
                    <div class="transport-cas">23:30</div>
                    <div class="transport-odkud">Marriott Airport</div>
                    <div class="transport-kam">venue</div>
                    <div class="transport-stav">
                        <button class="stav-btn stav-wait" onclick="zmenStav(this, 4)">WAIT</button>
                        <span class="stav-cas"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- NEDĚLE 14.12. -->
        <div class="den nedele">
            <div class="den-nazev vikend">NEDĚLE 14.12.</div>
            <div class="transport-header">
                <div>PICK UP</div>
                <div>FROM</div>
                <div>TO</div>
                <div>STATUS</div>
            </div>
            <div class="transporty">
                <div class="transport" data-id="5">
                    <div class="transport-cas">01:50</div>
                    <div class="transport-odkud">T3</div>
                    <div class="transport-kam">venue</div>
                    <div class="transport-stav">
                        <button class="stav-btn stav-wait" onclick="zmenStav(this, 5)">WAIT</button>
                        <span class="stav-cas"></span>
                    </div>
                </div>
                <div class="transport" data-id="6">
                    <div class="transport-cas">03:00</div>
                    <div class="transport-odkud">T3</div>
                    <div class="transport-kam">venue</div>
                    <div class="transport-stav">
                        <button class="stav-btn stav-wait" onclick="zmenStav(this, 6)">WAIT</button>
                        <span class="stav-cas"></span>
                    </div>
                </div>
                <div class="transport" data-id="7">
                    <div class="transport-cas">17:30</div>
                    <div class="transport-odkud">Hotel Expo</div>
                    <div class="transport-kam">T2</div>
                    <div class="transport-stav">
                        <button class="stav-btn stav-wait" onclick="zmenStav(this, 7)">WAIT</button>
                        <span class="stav-cas"></span>
                    </div>
                </div>
                <div class="transport" data-id="8">
                    <div class="transport-cas">17:30</div>
                    <div class="transport-odkud">Hotel Expo</div>
                    <div class="transport-kam">T2</div>
                    <div class="transport-stav">
                        <button class="stav-btn stav-wait" onclick="zmenStav(this, 8)">WAIT</button>
                        <span class="stav-cas"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Poznámka -->
        <div class="poznamka">
            <strong>SHOW 21:00 - 06:00</strong> - CAR ON STAND BY<br>
            Kliknutím na tlačítko změníte stav transportu. Ostatní uvidí změnu okamžitě.
        </div>

    </div>

    <script>
        // Stavy transportů - uložené v localStorage pro synchronizaci
        const STAVY = {
            WAIT: 'WAIT',
            ON_THE_WAY: 'ON THE WAY',
            DROP_OFF: 'DROP OFF'
        };

        // Klíč pro localStorage
        const STORAGE_KEY = 'techmission_transporty_stavy';

        // Načíst stavy z localStorage
        function nactiStavy() {
            try {
                const data = localStorage.getItem(STORAGE_KEY);
                return data ? JSON.parse(data) : {};
            } catch (e) {
                return {};
            }
        }

        // Uložit stavy do localStorage
        function ulozStavy(stavy) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(stavy));
        }

        // Změnit stav transportu
        function zmenStav(btn, id) {
            const stavy = nactiStavy();
            const aktualniStav = stavy[id]?.stav || STAVY.WAIT;

            let novyStav;
            let cas = null;

            // Přepínat stavy: WAIT -> ON THE WAY -> DROP OFF -> WAIT
            switch (aktualniStav) {
                case STAVY.WAIT:
                    novyStav = STAVY.ON_THE_WAY;
                    cas = new Date().toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' });
                    break;
                case STAVY.ON_THE_WAY:
                    novyStav = STAVY.DROP_OFF;
                    cas = new Date().toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' });
                    break;
                case STAVY.DROP_OFF:
                    novyStav = STAVY.WAIT;
                    cas = null;
                    break;
            }

            stavy[id] = { stav: novyStav, cas: cas };
            ulozStavy(stavy);

            // Aktualizovat UI
            aktualizujUI();

            // Odeslat na server pro synchronizaci (pokud existuje API)
            synchronizujServer(id, novyStav, cas);
        }

        // Aktualizovat UI podle stavů
        function aktualizujUI() {
            const stavy = nactiStavy();

            document.querySelectorAll('.transport').forEach(transport => {
                const id = transport.dataset.id;
                const btn = transport.querySelector('.stav-btn');
                const casSpan = transport.querySelector('.stav-cas');
                const data = stavy[id] || { stav: STAVY.WAIT, cas: null };

                // Reset tříd
                btn.className = 'stav-btn';

                switch (data.stav) {
                    case STAVY.WAIT:
                        btn.classList.add('stav-wait');
                        btn.textContent = 'WAIT';
                        casSpan.textContent = '';
                        break;
                    case STAVY.ON_THE_WAY:
                        btn.classList.add('stav-ontheway');
                        btn.textContent = 'ON THE WAY';
                        casSpan.textContent = data.cas ? `Vyjel: ${data.cas}` : '';
                        break;
                    case STAVY.DROP_OFF:
                        btn.classList.add('stav-dropoff');
                        btn.textContent = 'DROP OFF';
                        casSpan.textContent = data.cas ? `Doručeno: ${data.cas}` : '';
                        break;
                }
            });
        }

        // Synchronizace se serverem (pro sdílení mezi více zařízeními)
        async function synchronizujServer(id, stav, cas) {
            try {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('stav', stav);
                formData.append('cas', cas || '');

                await fetch('api/transport_sync.php', {
                    method: 'POST',
                    body: formData
                });
            } catch (e) {
                // Ignorovat chyby - localStorage funguje jako záloha
                console.log('Sync nedostupný, používám localStorage');
            }
        }

        // Načíst stavy ze serveru
        async function nactiZeServeru() {
            try {
                const response = await fetch('api/transport_sync.php');
                if (response.ok) {
                    const data = await response.json();
                    if (data.stavy) {
                        localStorage.setItem(STORAGE_KEY, JSON.stringify(data.stavy));
                        aktualizujUI();
                    }
                }
            } catch (e) {
                console.log('Server nedostupný, používám localStorage');
            }
        }

        // Automatická aktualizace každých 10 sekund
        setInterval(() => {
            nactiZeServeru();
        }, 10000);

        // Inicializace při načtení stránky
        document.addEventListener('DOMContentLoaded', () => {
            nactiZeServeru();
            aktualizujUI();
        });
    </script>
</body>
</html>
