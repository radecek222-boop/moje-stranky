<?php
/**
 * Hra Lodě (Battleship) - Multiplayer
 * Námořní bitva pro 2 hráče online
 */
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'Hráč';
$csrfToken = generateCSRFToken();

// Aktualizovat online status
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        INSERT INTO wgs_hry_online (user_id, username, posledni_aktivita, aktualni_hra)
        VALUES (:user_id, :username, NOW(), 'Lodě')
        ON DUPLICATE KEY UPDATE
            username = VALUES(username),
            posledni_aktivita = NOW(),
            aktualni_hra = 'Lodě'
    ");
    $stmt->execute(['user_id' => $userId, 'username' => $username]);
} catch (PDOException $e) {
    error_log("Lodě error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lodě | Herní zóna</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/wgs-base.min.css">
    <style>
        :root {
            --lode-bg: #0a0a0a;
            --lode-card: #1a1a1a;
            --lode-border: #333;
            --lode-text: #fff;
            --lode-muted: #888;
            --lode-accent: #0099ff;
            --lode-hit: #ff4444;
            --lode-miss: #666;
            --lode-ship: #0099ff;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--lode-bg);
            color: var(--lode-text);
            min-height: 100vh;
            margin: 0;
        }

        .lode-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }

        .lode-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .lode-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--lode-accent);
        }

        .lode-header p { color: var(--lode-muted); }

        /* Lobby */
        .lode-lobby {
            background: var(--lode-card);
            border: 1px solid var(--lode-border);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }

        .lode-lobby h2 {
            margin-bottom: 1.5rem;
            color: var(--lode-accent);
        }

        .lode-loader {
            width: 50px;
            height: 50px;
            border: 4px solid var(--lode-border);
            border-top-color: var(--lode-accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 1rem auto;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Hráči panel */
        .lode-hraci {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .lode-hrac {
            background: var(--lode-card);
            border: 2px solid var(--lode-border);
            border-radius: 10px;
            padding: 1rem 2rem;
            min-width: 150px;
            text-align: center;
        }

        .lode-hrac.aktivni {
            border-color: var(--lode-accent);
            box-shadow: 0 0 15px rgba(0, 153, 255, 0.3);
        }

        .lode-hrac.ja { background: rgba(0, 153, 255, 0.1); }

        .lode-hrac-stav {
            font-size: 0.8rem;
            color: var(--lode-muted);
            margin-top: 0.5rem;
        }

        .lode-hrac-stav.pripraveny { color: #4CAF50; }

        /* Status */
        .lode-status {
            text-align: center;
            padding: 1rem;
            background: var(--lode-card);
            border: 1px solid var(--lode-border);
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }

        .lode-status.vyhral {
            border-color: var(--lode-accent);
            color: var(--lode-accent);
        }

        .lode-status.prohral {
            border-color: var(--lode-hit);
            color: var(--lode-hit);
        }

        /* Boards */
        .lode-boards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            justify-items: center;
        }

        .lode-board-wrapper { text-align: center; }

        .lode-board-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--lode-muted);
        }

        .lode-board {
            display: grid;
            grid-template-columns: repeat(10, 32px);
            grid-template-rows: repeat(10, 32px);
            gap: 2px;
            background: var(--lode-border);
            padding: 2px;
            border-radius: 4px;
        }

        .lode-cell {
            width: 32px;
            height: 32px;
            background: var(--lode-card);
            border: 1px solid var(--lode-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            cursor: default;
            transition: all 0.2s;
        }

        .lode-board.aktivni .lode-cell:not(.hit):not(.miss) {
            cursor: crosshair;
        }

        .lode-board.aktivni .lode-cell:not(.hit):not(.miss):hover {
            background: rgba(0, 153, 255, 0.3);
        }

        .lode-cell.ship { background: var(--lode-ship); }
        .lode-cell.hit { background: var(--lode-hit); color: #fff; }
        .lode-cell.miss { background: var(--lode-miss); }
        .lode-cell.preview { background: rgba(0, 153, 255, 0.4); }

        /* Placement */
        .lode-placement {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .lode-placement-info {
            background: var(--lode-card);
            border: 1px solid var(--lode-border);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .lode-placement-ship {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--lode-accent);
        }

        /* Buttons */
        .lode-btn {
            background: var(--lode-accent);
            color: #000;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 0.5rem;
            font-family: 'Poppins', sans-serif;
        }

        .lode-btn:hover { background: #33bbff; }
        .lode-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .lode-btn.secondary { background: var(--lode-border); color: var(--lode-text); }
        .lode-btn.secondary:hover { background: #444; }
        .lode-btn.velke { padding: 1rem 3rem; font-size: 1.2rem; }

        .lode-actions { text-align: center; margin-top: 2rem; }

        /* Legend */
        .lode-legend {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .lode-legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--lode-muted);
        }

        .lode-legend-color {
            width: 20px;
            height: 20px;
            border-radius: 3px;
        }

        /* Ships list */
        .lode-ships-remaining {
            display: flex;
            gap: 2rem;
            justify-content: center;
            margin-top: 1rem;
        }

        .lode-ships-col { text-align: center; }
        .lode-ships-col h4 { font-size: 0.9rem; color: var(--lode-muted); margin-bottom: 0.5rem; }
        .lode-ships-list { font-size: 0.85rem; }
        .lode-ships-list span { display: block; margin: 0.25rem 0; }
        .lode-ships-list span.sunk { text-decoration: line-through; color: var(--lode-hit); }

        @media (max-width: 800px) {
            .lode-boards { grid-template-columns: 1fr; }
            .lode-board {
                grid-template-columns: repeat(10, 28px);
                grid-template-rows: repeat(10, 28px);
            }
            .lode-cell { width: 28px; height: 28px; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/hamburger-menu.php'; ?>

    <main id="main-content" class="lode-container">
        <div class="lode-header">
            <h1>LODĚ</h1>
            <p>Multiplayer - najdi a potop všechny soupeřovy lodě!</p>
        </div>

        <!-- Lobby -->
        <div class="lode-lobby" id="lobby">
            <h2>Najdi soupeře</h2>
            <p style="color: var(--lode-muted); margin-bottom: 1.5rem;">
                Klikni na tlačítko a systém tě automaticky spáruje s dalším hráčem.
            </p>
            <button class="lode-btn velke" id="hledatBtn">HRÁT ONLINE</button>
        </div>

        <!-- Čekání -->
        <div id="cekani" style="display: none; text-align: center;">
            <div class="lode-loader"></div>
            <p>Hledám soupeře...</p>
            <button class="lode-btn secondary" id="zrusitBtn">Zrušit</button>
        </div>

        <!-- Hra -->
        <div id="hra" style="display: none;">
            <div class="lode-hraci" id="hraciPanel"></div>
            <div class="lode-status" id="status">Umísti své lodě kliknutím na plochu</div>

            <!-- Placement phase -->
            <div class="lode-placement" id="placementInfo">
                <div class="lode-placement-info">
                    <div class="lode-placement-ship" id="currentShip">Letadlová loď (5 polí)</div>
                    <button class="lode-btn secondary" id="rotateBtn">Otočit (R)</button>
                </div>
            </div>

            <div class="lode-boards">
                <div class="lode-board-wrapper">
                    <div class="lode-board-title">Tvoje pole</div>
                    <div class="lode-board" id="mojeBoard"></div>
                </div>
                <div class="lode-board-wrapper">
                    <div class="lode-board-title">Soupeř</div>
                    <div class="lode-board" id="souperBoard"></div>
                </div>
            </div>

            <div class="lode-legend">
                <div class="lode-legend-item">
                    <div class="lode-legend-color" style="background: var(--lode-ship);"></div>Loď
                </div>
                <div class="lode-legend-item">
                    <div class="lode-legend-color" style="background: var(--lode-hit);"></div>Zásah
                </div>
                <div class="lode-legend-item">
                    <div class="lode-legend-color" style="background: var(--lode-miss);"></div>Mimo
                </div>
            </div>
        </div>

        <div class="lode-actions">
            <button class="lode-btn" id="novaHraBtn" style="display: none;">Nová hra</button>
            <a href="/hry.php" class="lode-btn secondary">Zpět do herní zóny</a>
        </div>
    </main>

    <script>
    (function() {
        'use strict';

        const VELIKOST = 10;
        const CSRF_TOKEN = '<?php echo $csrfToken; ?>';
        const USER_ID = <?php echo $userId; ?>;

        const LODE = [
            { nazev: 'Letadlová loď', delka: 5 },
            { nazev: 'Křižník', delka: 4 },
            { nazev: 'Torpédovka', delka: 3 },
            { nazev: 'Ponorka', delka: 3 },
            { nazev: 'Člun', delka: 2 }
        ];

        // Stav
        let mistnostId = null;
        let faze = 'lobby'; // lobby, cekani, umistovani, cekaniNaSoupere, hra, konec
        let jsemHrac1 = false;
        let jsemNaTahu = false;
        let pollingInterval = null;

        // Placement
        let aktualniLodIndex = 0;
        let orientace = 'horizontal';
        let mojePole = vytvoritPrazdnePole();
        let mojeLode = [];

        // Zobrazení
        let mojeZasahy = [];
        let souperZasahy = [];

        // DOM
        const lobbyEl = document.getElementById('lobby');
        const cekaniEl = document.getElementById('cekani');
        const hraEl = document.getElementById('hra');
        const statusEl = document.getElementById('status');
        const hraciPanelEl = document.getElementById('hraciPanel');
        const placementInfo = document.getElementById('placementInfo');
        const currentShipEl = document.getElementById('currentShip');
        const mojeBoard = document.getElementById('mojeBoard');
        const souperBoard = document.getElementById('souperBoard');
        const hledatBtn = document.getElementById('hledatBtn');
        const zrusitBtn = document.getElementById('zrusitBtn');
        const rotateBtn = document.getElementById('rotateBtn');
        const novaHraBtn = document.getElementById('novaHraBtn');

        function vytvoritPrazdnePole() {
            return Array(VELIKOST).fill(null).map(() => Array(VELIKOST).fill(0));
        }

        // Hledat soupeře
        async function hledatSoupere() {
            lobbyEl.style.display = 'none';
            cekaniEl.style.display = 'block';
            faze = 'cekani';

            try {
                const formData = new FormData();
                formData.append('action', 'quick_match');
                formData.append('hra', 'lode');
                formData.append('csrf_token', CSRF_TOKEN);

                const response = await fetch('/api/hry_api.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.status === 'success') {
                    mistnostId = result.data.mistnost_id;
                    spustitPolling();
                } else {
                    alert('Chyba: ' + result.message);
                    zobrazLobby();
                }
            } catch (error) {
                console.error('Chyba:', error);
                zobrazLobby();
            }
        }

        function zobrazLobby() {
            lobbyEl.style.display = 'block';
            cekaniEl.style.display = 'none';
            hraEl.style.display = 'none';
            faze = 'lobby';
        }

        function spustitPolling() {
            nacistStav();
            pollingInterval = setInterval(nacistStav, 1500);
        }

        function zastavitPolling() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
        }

        async function nacistStav() {
            if (!mistnostId) return;

            try {
                const response = await fetch(`/api/hry_api.php?action=lode_stav&mistnost_id=${mistnostId}`);
                const result = await response.json();

                if (result.status !== 'success') return;

                const data = result.data;
                jsemHrac1 = data.jsem_hrac1;
                mojeZasahy = data.moje_zasahy || [];
                souperZasahy = data.souper_zasahy || [];

                // Čekám na soupeře?
                if (data.hraci.length < 2) {
                    cekaniEl.style.display = 'block';
                    hraEl.style.display = 'none';
                    return;
                }

                // Zobrazit hru
                cekaniEl.style.display = 'none';
                hraEl.style.display = 'block';

                aktualizujHrace(data.hraci, data);

                // Fáze umisťování
                if (!data.moje_lode && faze !== 'umistovani') {
                    faze = 'umistovani';
                    placementInfo.style.display = 'block';
                    statusEl.textContent = 'Umísti své lodě kliknutím na plochu';
                    vykresliMojePole();
                    vykresliSouperPole();
                    return;
                }

                // Čekání na soupeře
                if (data.moje_lode && !data.souper_pripraveny) {
                    faze = 'cekaniNaSoupere';
                    placementInfo.style.display = 'none';
                    statusEl.textContent = 'Čekám až soupeř rozmístí lodě...';
                    vykresliMojePole();
                    vykresliSouperPole();
                    return;
                }

                // Hra probíhá
                if (data.stav === 'hra') {
                    faze = 'hra';
                    placementInfo.style.display = 'none';
                    jsemNaTahu = (jsemHrac1 && data.na_tahu === 1) || (!jsemHrac1 && data.na_tahu === 2);

                    if (jsemNaTahu) {
                        statusEl.textContent = 'Tvůj tah - klikni na soupeřovo pole';
                        souperBoard.classList.add('aktivni');
                    } else {
                        statusEl.textContent = 'Soupeř střílí...';
                        souperBoard.classList.remove('aktivni');
                    }

                    vykresliMojePole();
                    vykresliSouperPole();
                }

                // Konec hry
                if (data.vitez) {
                    faze = 'konec';
                    zastavitPolling();
                    novaHraBtn.style.display = 'inline-block';
                    souperBoard.classList.remove('aktivni');

                    const vyhral = (jsemHrac1 && data.vitez === 1) || (!jsemHrac1 && data.vitez === 2);
                    statusEl.textContent = vyhral ? 'VYHRÁL JSI!' : 'PROHRÁL JSI!';
                    statusEl.className = 'lode-status ' + (vyhral ? 'vyhral' : 'prohral');
                }

            } catch (error) {
                console.error('Polling error:', error);
            }
        }

        function aktualizujHrace(hraci, data) {
            hraciPanelEl.innerHTML = hraci.map((hrac, index) => {
                const jsemJa = hrac.user_id == USER_ID;
                const jeNaTahu = (index === 0 && data.na_tahu === 1) || (index === 1 && data.na_tahu === 2);
                const pripraveny = (index === 0 && jsemHrac1 && data.moje_lode) ||
                                   (index === 1 && !jsemHrac1 && data.moje_lode) ||
                                   (index === 0 && !jsemHrac1 && data.souper_pripraveny) ||
                                   (index === 1 && jsemHrac1 && data.souper_pripraveny);

                return `
                    <div class="lode-hrac ${jeNaTahu && data.stav === 'hra' ? 'aktivni' : ''} ${jsemJa ? 'ja' : ''}">
                        <div>${escapeHtml(hrac.username)}${jsemJa ? ' (ty)' : ''}</div>
                        <div class="lode-hrac-stav ${pripraveny ? 'pripraveny' : ''}">${pripraveny ? 'Připraven' : 'Rozmisťuje...'}</div>
                    </div>
                `;
            }).join('');
        }

        function vykresliMojePole() {
            mojeBoard.innerHTML = '';
            for (let y = 0; y < VELIKOST; y++) {
                for (let x = 0; x < VELIKOST; x++) {
                    const cell = document.createElement('div');
                    cell.className = 'lode-cell';
                    cell.dataset.x = x;
                    cell.dataset.y = y;

                    if (mojePole[y][x] > 0) {
                        cell.classList.add('ship');
                    }

                    // Zobrazit zásahy soupeře
                    const zasahKlic = `${y}_${x}`;
                    if (souperZasahy.includes(zasahKlic)) {
                        cell.classList.add(mojePole[y][x] > 0 ? 'hit' : 'miss');
                        cell.textContent = mojePole[y][x] > 0 ? 'X' : 'o';
                    }

                    if (faze === 'umistovani') {
                        cell.addEventListener('click', () => klikMojePole(x, y));
                        cell.addEventListener('mouseover', () => previewLod(x, y));
                        cell.addEventListener('mouseout', clearPreview);
                    }

                    mojeBoard.appendChild(cell);
                }
            }
        }

        function vykresliSouperPole() {
            souperBoard.innerHTML = '';
            for (let y = 0; y < VELIKOST; y++) {
                for (let x = 0; x < VELIKOST; x++) {
                    const cell = document.createElement('div');
                    cell.className = 'lode-cell';
                    cell.dataset.x = x;
                    cell.dataset.y = y;

                    const zasahKlic = `${y}_${x}`;
                    if (mojeZasahy.includes(zasahKlic)) {
                        // Nemůžeme vědět, jestli je zásah, dokud server nepotvrdí
                        // Ale pro vizuální feedback zobrazíme jako miss (server pošle správnou info)
                        cell.classList.add('miss');
                        cell.textContent = 'o';
                    }

                    if (faze === 'hra' && jsemNaTahu) {
                        cell.addEventListener('click', () => strelit(x, y));
                    }

                    souperBoard.appendChild(cell);
                }
            }
        }

        function previewLod(x, y) {
            if (faze !== 'umistovani') return;
            clearPreview();

            const lod = LODE[aktualniLodIndex];
            const pozice = getPoziceLode(x, y, lod.delka, orientace);
            if (!pozice) return;

            pozice.forEach(([px, py]) => {
                const cell = mojeBoard.querySelector(`[data-x="${px}"][data-y="${py}"]`);
                if (cell && !cell.classList.contains('ship')) {
                    cell.classList.add('preview');
                }
            });
        }

        function clearPreview() {
            mojeBoard.querySelectorAll('.preview').forEach(c => c.classList.remove('preview'));
        }

        function getPoziceLode(x, y, delka, ori) {
            const pozice = [];
            for (let i = 0; i < delka; i++) {
                const px = ori === 'horizontal' ? x + i : x;
                const py = ori === 'vertical' ? y + i : y;
                if (px >= VELIKOST || py >= VELIKOST) return null;
                pozice.push([px, py]);
            }
            return pozice;
        }

        function lzeUmistitLod(pozice) {
            for (const [px, py] of pozice) {
                if (mojePole[py][px] !== 0) return false;
                for (let dy = -1; dy <= 1; dy++) {
                    for (let dx = -1; dx <= 1; dx++) {
                        const nx = px + dx, ny = py + dy;
                        if (nx >= 0 && nx < VELIKOST && ny >= 0 && ny < VELIKOST) {
                            if (mojePole[ny][nx] !== 0) return false;
                        }
                    }
                }
            }
            return true;
        }

        function klikMojePole(x, y) {
            if (faze !== 'umistovani') return;

            const lod = LODE[aktualniLodIndex];
            const pozice = getPoziceLode(x, y, lod.delka, orientace);

            if (!pozice || !lzeUmistitLod(pozice)) {
                statusEl.textContent = 'Sem loď umístit nelze!';
                return;
            }

            const lodId = aktualniLodIndex + 1;
            pozice.forEach(([px, py]) => mojePole[py][px] = lodId);
            mojeLode.push({ nazev: lod.nazev, pozice: pozice.map(p => [p[1], p[0]]) }); // [row, col]

            aktualniLodIndex++;

            if (aktualniLodIndex >= LODE.length) {
                // Odeslat rozmístění na server
                odeslatRozmisteni();
            } else {
                currentShipEl.textContent = `${LODE[aktualniLodIndex].nazev} (${LODE[aktualniLodIndex].delka} polí)`;
                vykresliMojePole();
            }
        }

        async function odeslatRozmisteni() {
            statusEl.textContent = 'Odesílám rozmístění...';
            placementInfo.style.display = 'none';

            try {
                const formData = new FormData();
                formData.append('action', 'lode_rozmisteni');
                formData.append('mistnost_id', mistnostId);
                formData.append('lode', JSON.stringify(mojeLode));
                formData.append('csrf_token', CSRF_TOKEN);

                const response = await fetch('/api/hry_api.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.status === 'success') {
                    faze = 'cekaniNaSoupere';
                    statusEl.textContent = 'Čekám až soupeř rozmístí lodě...';
                    vykresliMojePole();
                } else {
                    alert('Chyba: ' + result.message);
                }
            } catch (error) {
                console.error('Chyba:', error);
            }
        }

        async function strelit(x, y) {
            if (faze !== 'hra' || !jsemNaTahu) return;

            const zasahKlic = `${y}_${x}`;
            if (mojeZasahy.includes(zasahKlic)) return;

            jsemNaTahu = false;
            statusEl.textContent = 'Střílím...';
            souperBoard.classList.remove('aktivni');

            try {
                const formData = new FormData();
                formData.append('action', 'lode_strela');
                formData.append('mistnost_id', mistnostId);
                formData.append('radek', y);
                formData.append('sloupec', x);
                formData.append('csrf_token', CSRF_TOKEN);

                const response = await fetch('/api/hry_api.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.status === 'success') {
                    const cell = souperBoard.querySelector(`[data-x="${x}"][data-y="${y}"]`);
                    if (result.data.zasah) {
                        cell.classList.add('hit');
                        cell.textContent = 'X';
                        if (result.data.potopena) {
                            statusEl.textContent = `Potopil jsi ${result.data.potopena}!`;
                        } else {
                            statusEl.textContent = 'Zásah! Střílej znovu.';
                        }
                        jsemNaTahu = true;
                        souperBoard.classList.add('aktivni');
                    } else {
                        cell.classList.add('miss');
                        cell.textContent = 'o';
                        statusEl.textContent = 'Mimo. Soupeř střílí...';
                    }

                    mojeZasahy.push(zasahKlic);

                    if (result.data.vitez) {
                        faze = 'konec';
                        zastavitPolling();
                        novaHraBtn.style.display = 'inline-block';
                        const vyhral = (jsemHrac1 && result.data.vitez === 1) || (!jsemHrac1 && result.data.vitez === 2);
                        statusEl.textContent = vyhral ? 'VYHRÁL JSI!' : 'PROHRÁL JSI!';
                        statusEl.className = 'lode-status ' + (vyhral ? 'vyhral' : 'prohral');
                    }
                } else {
                    alert('Chyba: ' + result.message);
                    jsemNaTahu = true;
                    souperBoard.classList.add('aktivni');
                }
            } catch (error) {
                console.error('Střela error:', error);
                jsemNaTahu = true;
            }
        }

        async function novaHra() {
            if (mistnostId) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'opustit');
                    formData.append('mistnost_id', mistnostId);
                    formData.append('csrf_token', CSRF_TOKEN);
                    await fetch('/api/hry_api.php', { method: 'POST', body: formData });
                } catch (e) {}
            }

            // Reset
            mistnostId = null;
            faze = 'lobby';
            aktualniLodIndex = 0;
            mojePole = vytvoritPrazdnePole();
            mojeLode = [];
            mojeZasahy = [];
            souperZasahy = [];
            currentShipEl.textContent = `${LODE[0].nazev} (${LODE[0].delka} polí)`;
            statusEl.className = 'lode-status';
            novaHraBtn.style.display = 'none';
            hledatSoupere();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Event listenery
        hledatBtn.addEventListener('click', hledatSoupere);
        zrusitBtn.addEventListener('click', () => {
            zastavitPolling();
            if (mistnostId) {
                const formData = new FormData();
                formData.append('action', 'opustit');
                formData.append('mistnost_id', mistnostId);
                formData.append('csrf_token', CSRF_TOKEN);
                fetch('/api/hry_api.php', { method: 'POST', body: formData });
            }
            mistnostId = null;
            zobrazLobby();
        });
        rotateBtn.addEventListener('click', () => {
            orientace = orientace === 'horizontal' ? 'vertical' : 'horizontal';
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'r' || e.key === 'R') {
                orientace = orientace === 'horizontal' ? 'vertical' : 'horizontal';
            }
        });
        novaHraBtn.addEventListener('click', novaHra);

        // Heartbeat
        setInterval(async () => {
            try { await fetch('/api/hry_api.php?action=heartbeat'); } catch (e) {}
        }, 30000);

        // Cleanup
        window.addEventListener('beforeunload', () => {
            if (mistnostId) {
                navigator.sendBeacon('/api/hry_api.php?action=opustit&mistnost_id=' + mistnostId);
            }
        });

    })();
    </script>
</body>
</html>
