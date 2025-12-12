<?php
/**
 * Hra Piškvorky - Solo proti PC nebo Multiplayer
 * Strategická hra - spoj 5 symbolů v řadě
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
        VALUES (:user_id, :username, NOW(), 'Piškvorky')
        ON DUPLICATE KEY UPDATE
            username = VALUES(username),
            posledni_aktivita = NOW(),
            aktualni_hra = 'Piškvorky'
    ");
    $stmt->execute(['user_id' => $userId, 'username' => $username]);
} catch (PDOException $e) {
    error_log("Piškvorky error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Piškvorky | Herní zóna</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --pisk-bg: #0a0a0a;
            --pisk-card: #1a1a1a;
            --pisk-border: #333;
            --pisk-text: #fff;
            --pisk-muted: #888;
            --pisk-accent: #0099ff;
            --pisk-x: #0099ff;
            --pisk-o: #fff;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--pisk-bg);
            color: var(--pisk-text);
            min-height: 100vh;
            margin: 0;
        }

        .pisk-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 1rem;
            text-align: center;
        }

        .pisk-header {
            margin-bottom: 1.5rem;
        }

        .pisk-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--pisk-accent);
        }

        .pisk-header p {
            color: var(--pisk-muted);
            font-size: 0.9rem;
        }

        /* Lobby */
        .pisk-lobby {
            background: var(--pisk-card);
            border: 1px solid var(--pisk-border);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .pisk-lobby h2 {
            margin-bottom: 1.5rem;
            color: var(--pisk-accent);
        }

        .pisk-lobby-btns {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .pisk-cekani {
            padding: 2rem;
            text-align: center;
        }

        .pisk-loader {
            width: 50px;
            height: 50px;
            border: 4px solid var(--pisk-border);
            border-top-color: var(--pisk-accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Hráči */
        .pisk-hraci {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .pisk-hrac {
            background: var(--pisk-card);
            border: 2px solid var(--pisk-border);
            border-radius: 10px;
            padding: 1rem 2rem;
            min-width: 150px;
        }

        .pisk-hrac.aktivni {
            border-color: var(--pisk-accent);
            box-shadow: 0 0 15px rgba(0, 153, 255, 0.3);
        }

        .pisk-hrac.ja {
            background: rgba(0, 153, 255, 0.1);
        }

        .pisk-hrac-symbol {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .pisk-hrac-symbol.x { color: var(--pisk-x); }
        .pisk-hrac-symbol.o { color: var(--pisk-o); }

        .pisk-hrac-jmeno {
            font-weight: 600;
        }

        /* Status */
        .pisk-status {
            background: var(--pisk-card);
            border: 1px solid var(--pisk-border);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }

        .pisk-status.vyhral {
            border-color: var(--pisk-accent);
            color: var(--pisk-accent);
        }

        .pisk-status.prohral {
            border-color: #ff4444;
            color: #ff4444;
        }

        /* Board */
        .pisk-board-wrapper {
            display: inline-block;
            background: var(--pisk-card);
            border: 2px solid var(--pisk-border);
            border-radius: 12px;
            padding: 10px;
            overflow-x: auto;
        }

        .pisk-board {
            display: grid;
            grid-template-columns: repeat(15, 32px);
            grid-template-rows: repeat(15, 32px);
            gap: 1px;
            background: var(--pisk-border);
        }

        .pisk-cell {
            width: 32px;
            height: 32px;
            background: var(--pisk-card);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
            font-weight: 700;
            transition: background 0.2s;
        }

        .pisk-cell:hover:not(.obsazeno):not(.disabled) {
            background: rgba(0, 153, 255, 0.2);
        }

        .pisk-cell.obsazeno, .pisk-cell.disabled {
            cursor: default;
        }

        .pisk-cell.x { color: var(--pisk-x); }
        .pisk-cell.o { color: var(--pisk-o); }

        .pisk-cell.vitezna {
            background: rgba(0, 153, 255, 0.3);
            animation: pulse 0.5s ease-in-out infinite alternate;
        }

        @keyframes pulse {
            from { background: rgba(0, 153, 255, 0.2); }
            to { background: rgba(0, 153, 255, 0.4); }
        }

        /* Tlačítka */
        .pisk-actions {
            margin-top: 1.5rem;
        }

        .pisk-btn {
            background: var(--pisk-accent);
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

        .pisk-btn:hover { background: #33bbff; }
        .pisk-btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .pisk-btn.secondary {
            background: var(--pisk-border);
            color: var(--pisk-text);
        }

        .pisk-btn.secondary:hover { background: #444; }

        .pisk-btn.velke {
            padding: 1rem 3rem;
            font-size: 1.2rem;
        }

        @media (max-width: 550px) {
            .pisk-board {
                grid-template-columns: repeat(15, 22px);
                grid-template-rows: repeat(15, 22px);
            }
            .pisk-cell {
                width: 22px;
                height: 22px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/hamburger-menu.php'; ?>

    <main id="main-content" class="pisk-container">
        <div class="pisk-header">
            <h1>PIŠKVORKY</h1>
            <p>Spoj 5 symbolů v řadě a vyhraj!</p>
        </div>

        <!-- Lobby - výběr režimu -->
        <div class="pisk-lobby" id="lobby">
            <h2>Vyber režim hry</h2>
            <p style="color: var(--pisk-muted); margin-bottom: 1.5rem;">
                Hraj proti počítači nebo najdi soupeře online.
            </p>
            <div class="pisk-lobby-btns">
                <button class="pisk-btn velke" id="soloBtn">HRÁT PROTI POČÍTAČI</button>
                <button class="pisk-btn velke secondary" id="hledatBtn">HRÁT ONLINE</button>
            </div>
        </div>

        <!-- Čekání na soupeře -->
        <div class="pisk-cekani" id="cekani" style="display: none;">
            <div class="pisk-loader"></div>
            <p>Hledám soupeře...</p>
            <button class="pisk-btn secondary" id="zrusitBtn">Zrušit</button>
        </div>

        <!-- Herní obrazovka -->
        <div id="hra" style="display: none;">
            <div class="pisk-hraci" id="hraciPanel"></div>
            <div class="pisk-status" id="status">Čekám na soupeře...</div>
            <div class="pisk-board-wrapper">
                <div class="pisk-board" id="board"></div>
            </div>
        </div>

        <div class="pisk-actions">
            <button class="pisk-btn" id="novaHraBtn" style="display: none;">Nová hra</button>
            <a href="/hry.php" class="pisk-btn secondary">Zpět do herní zóny</a>
        </div>
    </main>

    <script>
    (function() {
        'use strict';

        const VELIKOST = 15;
        const CSRF_TOKEN = '<?php echo $csrfToken; ?>';
        const USER_ID = '<?php echo $userId; ?>';
        const USERNAME = '<?php echo addslashes($username); ?>';

        // Stav
        let rezim = null; // 'solo' nebo 'multiplayer'
        let mistnostId = null;
        let mujSymbol = null; // 1 = X, 2 = O
        let jsemNaTahu = false;
        let hraSkoncila = false;
        let pollingInterval = null;

        // Solo stav
        let plocha = [];

        // DOM elementy
        const lobbyEl = document.getElementById('lobby');
        const cekaniEl = document.getElementById('cekani');
        const hraEl = document.getElementById('hra');
        const boardEl = document.getElementById('board');
        const statusEl = document.getElementById('status');
        const hraciPanelEl = document.getElementById('hraciPanel');
        const soloBtn = document.getElementById('soloBtn');
        const hledatBtn = document.getElementById('hledatBtn');
        const zrusitBtn = document.getElementById('zrusitBtn');
        const novaHraBtn = document.getElementById('novaHraBtn');

        // Inicializace boardu
        function vytvorBoard() {
            boardEl.innerHTML = '';
            for (let y = 0; y < VELIKOST; y++) {
                for (let x = 0; x < VELIKOST; x++) {
                    const cell = document.createElement('div');
                    cell.className = 'pisk-cell';
                    cell.dataset.x = x;
                    cell.dataset.y = y;
                    cell.addEventListener('click', () => klikNaPole(x, y));
                    boardEl.appendChild(cell);
                }
            }
        }

        // Aktualizovat board podle stavu
        function aktualizujBoard(plochaData) {
            const cells = boardEl.querySelectorAll('.pisk-cell');
            cells.forEach(cell => {
                const x = parseInt(cell.dataset.x);
                const y = parseInt(cell.dataset.y);
                const hodnota = plochaData[y][x];

                cell.textContent = '';
                cell.className = 'pisk-cell';

                if (hodnota === 1) {
                    cell.textContent = 'X';
                    cell.classList.add('x', 'obsazeno');
                } else if (hodnota === 2) {
                    cell.textContent = 'O';
                    cell.classList.add('o', 'obsazeno');
                }

                if (!jsemNaTahu || hraSkoncila) {
                    cell.classList.add('disabled');
                }
            });
        }

        // Aktualizovat panel hráčů (solo)
        function aktualizujHraceSolo(naTahu) {
            hraciPanelEl.innerHTML = `
                <div class="pisk-hrac ${naTahu === 1 ? 'aktivni' : ''} ja">
                    <div class="pisk-hrac-symbol x">X</div>
                    <div class="pisk-hrac-jmeno">${escapeHtml(USERNAME)} (ty)</div>
                </div>
                <div class="pisk-hrac ${naTahu === 2 ? 'aktivni' : ''}">
                    <div class="pisk-hrac-symbol o">O</div>
                    <div class="pisk-hrac-jmeno">Počítač</div>
                </div>
            `;
        }

        // Aktualizovat panel hráčů (multiplayer)
        function aktualizujHrace(hraci, naTahu) {
            hraciPanelEl.innerHTML = hraci.map((hrac, index) => {
                const symbol = index === 0 ? 'X' : 'O';
                const symbolCislo = index === 0 ? 1 : 2;
                const jeAktivni = symbolCislo === naTahu;
                const jsemJa = hrac.user_id == USER_ID;

                return `
                    <div class="pisk-hrac ${jeAktivni ? 'aktivni' : ''} ${jsemJa ? 'ja' : ''}">
                        <div class="pisk-hrac-symbol ${symbol.toLowerCase()}">${symbol}</div>
                        <div class="pisk-hrac-jmeno">${escapeHtml(hrac.username)}${jsemJa ? ' (ty)' : ''}</div>
                    </div>
                `;
            }).join('');
        }

        // ==================== SOLO REŽIM ====================

        function spustitSolo() {
            rezim = 'solo';
            mujSymbol = 1; // Hráč je vždy X
            jsemNaTahu = true;
            hraSkoncila = false;

            // Inicializovat prázdnou plochu
            plocha = [];
            for (let y = 0; y < VELIKOST; y++) {
                plocha[y] = [];
                for (let x = 0; x < VELIKOST; x++) {
                    plocha[y][x] = 0;
                }
            }

            lobbyEl.style.display = 'none';
            cekaniEl.style.display = 'none';
            hraEl.style.display = 'block';

            aktualizujHraceSolo(1);
            aktualizujBoard(plocha);
            statusEl.textContent = 'Tvůj tah - klikni na pole';
            statusEl.className = 'pisk-status';
        }

        // Klik na pole (solo)
        function klikSolo(x, y) {
            if (!jsemNaTahu || hraSkoncila) return;
            if (plocha[y][x] !== 0) return;

            // Můj tah
            plocha[y][x] = 1;
            jsemNaTahu = false;

            aktualizujBoard(plocha);
            aktualizujHraceSolo(2);
            statusEl.textContent = 'Počítač přemýšlí...';

            // Zkontrolovat výhru
            if (zkontrolujVyhru(1)) {
                hraSkoncila = true;
                statusEl.textContent = 'Vyhrál jsi!';
                statusEl.className = 'pisk-status vyhral';
                novaHraBtn.style.display = 'inline-block';
                return;
            }

            // Zkontrolovat remízu
            if (jeRemiza()) {
                hraSkoncila = true;
                statusEl.textContent = 'Remíza!';
                statusEl.className = 'pisk-status';
                novaHraBtn.style.display = 'inline-block';
                return;
            }

            // Tah počítače
            setTimeout(tahPocitace, 500);
        }

        // AI tah
        function tahPocitace() {
            if (hraSkoncila) return;

            const tah = najdiNejlepsiTah();
            if (tah) {
                plocha[tah.y][tah.x] = 2;
            }

            aktualizujBoard(plocha);

            // Zkontrolovat výhru počítače
            if (zkontrolujVyhru(2)) {
                hraSkoncila = true;
                statusEl.textContent = 'Prohrál jsi!';
                statusEl.className = 'pisk-status prohral';
                novaHraBtn.style.display = 'inline-block';
                return;
            }

            // Zkontrolovat remízu
            if (jeRemiza()) {
                hraSkoncila = true;
                statusEl.textContent = 'Remíza!';
                statusEl.className = 'pisk-status';
                novaHraBtn.style.display = 'inline-block';
                return;
            }

            jsemNaTahu = true;
            aktualizujHraceSolo(1);
            aktualizujBoard(plocha);
            statusEl.textContent = 'Tvůj tah - klikni na pole';
        }

        // AI - najít nejlepší tah
        function najdiNejlepsiTah() {
            // 1. Můžu vyhrát? (4 v řadě + prázdné pole)
            const vyhra = najdiTahProRaduN(2, 4);
            if (vyhra) return vyhra;

            // 2. Musím blokovat? (hráč má 4 v řadě)
            const blokuj4 = najdiTahProRaduN(1, 4);
            if (blokuj4) return blokuj4;

            // 3. Můžu udělat 4 v řadě?
            const moje4 = najdiTahProRaduN(2, 3);
            if (moje4) return moje4;

            // 4. Blokovat 3 v řadě hráče
            const blokuj3 = najdiTahProRaduN(1, 3);
            if (blokuj3) return blokuj3;

            // 5. Udělat 3 v řadě
            const moje3 = najdiTahProRaduN(2, 2);
            if (moje3) return moje3;

            // 6. Hrát blízko existujících kamenů
            const blizko = najdiTahBlizkoKamenu();
            if (blizko) return blizko;

            // 7. Hrát do středu
            const stred = Math.floor(VELIKOST / 2);
            if (plocha[stred][stred] === 0) {
                return { x: stred, y: stred };
            }

            // 8. Náhodný tah
            return nahodnyTah();
        }

        // Najít tah, který vytvoří/blokuje N v řadě
        function najdiTahProRaduN(hrac, n) {
            const smery = [[0, 1], [1, 0], [1, 1], [1, -1]];

            for (let y = 0; y < VELIKOST; y++) {
                for (let x = 0; x < VELIKOST; x++) {
                    if (plocha[y][x] !== 0) continue;

                    for (const [dy, dx] of smery) {
                        // Počítat v obou směrech
                        let pocet = 0;
                        let otevrenych = 0;

                        // Směr +
                        for (let i = 1; i <= 4; i++) {
                            const ny = y + dy * i;
                            const nx = x + dx * i;
                            if (ny < 0 || ny >= VELIKOST || nx < 0 || nx >= VELIKOST) break;
                            if (plocha[ny][nx] === hrac) pocet++;
                            else if (plocha[ny][nx] === 0) { otevrenych++; break; }
                            else break;
                        }

                        // Směr -
                        for (let i = 1; i <= 4; i++) {
                            const ny = y - dy * i;
                            const nx = x - dx * i;
                            if (ny < 0 || ny >= VELIKOST || nx < 0 || nx >= VELIKOST) break;
                            if (plocha[ny][nx] === hrac) pocet++;
                            else if (plocha[ny][nx] === 0) { otevrenych++; break; }
                            else break;
                        }

                        if (pocet >= n) {
                            return { x, y };
                        }
                    }
                }
            }

            return null;
        }

        // Najít tah blízko existujících kamenů
        function najdiTahBlizkoKamenu() {
            const kandidati = [];

            for (let y = 0; y < VELIKOST; y++) {
                for (let x = 0; x < VELIKOST; x++) {
                    if (plocha[y][x] !== 0) continue;

                    // Zkontrolovat sousedy
                    let maSouseda = false;
                    for (let dy = -2; dy <= 2; dy++) {
                        for (let dx = -2; dx <= 2; dx++) {
                            if (dy === 0 && dx === 0) continue;
                            const ny = y + dy;
                            const nx = x + dx;
                            if (ny >= 0 && ny < VELIKOST && nx >= 0 && nx < VELIKOST) {
                                if (plocha[ny][nx] !== 0) {
                                    maSouseda = true;
                                    break;
                                }
                            }
                        }
                        if (maSouseda) break;
                    }

                    if (maSouseda) {
                        kandidati.push({ x, y });
                    }
                }
            }

            if (kandidati.length > 0) {
                return kandidati[Math.floor(Math.random() * kandidati.length)];
            }

            return null;
        }

        // Náhodný tah
        function nahodnyTah() {
            const volne = [];
            for (let y = 0; y < VELIKOST; y++) {
                for (let x = 0; x < VELIKOST; x++) {
                    if (plocha[y][x] === 0) {
                        volne.push({ x, y });
                    }
                }
            }

            if (volne.length > 0) {
                return volne[Math.floor(Math.random() * volne.length)];
            }

            return null;
        }

        // Zkontrolovat výhru
        function zkontrolujVyhru(hrac) {
            const smery = [[0, 1], [1, 0], [1, 1], [1, -1]];

            for (let y = 0; y < VELIKOST; y++) {
                for (let x = 0; x < VELIKOST; x++) {
                    if (plocha[y][x] !== hrac) continue;

                    for (const [dy, dx] of smery) {
                        let pocet = 1;
                        for (let i = 1; i < 5; i++) {
                            const ny = y + dy * i;
                            const nx = x + dx * i;
                            if (ny >= 0 && ny < VELIKOST && nx >= 0 && nx < VELIKOST && plocha[ny][nx] === hrac) {
                                pocet++;
                            } else {
                                break;
                            }
                        }
                        if (pocet >= 5) return true;
                    }
                }
            }

            return false;
        }

        // Zkontrolovat remízu
        function jeRemiza() {
            for (let y = 0; y < VELIKOST; y++) {
                for (let x = 0; x < VELIKOST; x++) {
                    if (plocha[y][x] === 0) return false;
                }
            }
            return true;
        }

        // ==================== MULTIPLAYER REŽIM ====================

        async function hledatSoupere() {
            rezim = 'multiplayer';
            lobbyEl.style.display = 'none';
            cekaniEl.style.display = 'block';

            try {
                const formData = new FormData();
                formData.append('action', 'quick_match');
                formData.append('hra', 'piskvorky');
                formData.append('csrf_token', CSRF_TOKEN);

                const response = await fetch('/api/hry_api.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                });

                const result = await response.json();

                if (result.status === 'success') {
                    mistnostId = result.mistnost_id;
                    spustitPolling();
                } else {
                    alert('Chyba: ' + result.message);
                    zobrazLobby();
                }
            } catch (error) {
                console.error('Chyba:', error);
                alert('Chyba při hledání soupeře');
                zobrazLobby();
            }
        }

        async function zrusitHledani() {
            zastavitPolling();

            if (mistnostId) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'opustit');
                    formData.append('mistnost_id', mistnostId);
                    formData.append('csrf_token', CSRF_TOKEN);

                    await fetch('/api/hry_api.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'include'
                    });
                } catch (e) {}
            }

            mistnostId = null;
            zobrazLobby();
        }

        function zobrazLobby() {
            rezim = null;
            lobbyEl.style.display = 'block';
            cekaniEl.style.display = 'none';
            hraEl.style.display = 'none';
            novaHraBtn.style.display = 'none';
        }

        function spustitPolling() {
            nacistStav();
            pollingInterval = setInterval(nacistStav, 1000);
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
                const response = await fetch(`/api/hry_api.php?action=piskvorky_stav&mistnost_id=${mistnostId}`, {
                    credentials: 'include'
                });
                const result = await response.json();

                if (result.status !== 'success') {
                    console.error('Chyba stavu:', result.message);
                    return;
                }

                // API vraci data primo v result objektu
                mujSymbol = result.muj_symbol;
                jsemNaTahu = result.jsem_na_tahu;

                if (result.hraci.length < 2) {
                    cekaniEl.style.display = 'block';
                    hraEl.style.display = 'none';
                    return;
                }

                cekaniEl.style.display = 'none';
                hraEl.style.display = 'block';

                aktualizujHrace(result.hraci, result.na_tahu);
                aktualizujBoard(result.plocha);

                if (result.vitez) {
                    hraSkoncila = true;
                    zastavitPolling();
                    novaHraBtn.style.display = 'inline-block';

                    if (result.vitez === 'remiza') {
                        statusEl.textContent = 'Remíza!';
                        statusEl.className = 'pisk-status';
                    } else if (result.vitez === mujSymbol) {
                        statusEl.textContent = 'Vyhrál jsi!';
                        statusEl.className = 'pisk-status vyhral';
                    } else {
                        statusEl.textContent = 'Prohrál jsi!';
                        statusEl.className = 'pisk-status prohral';
                    }
                } else if (jsemNaTahu) {
                    statusEl.textContent = 'Tvůj tah - klikni na pole';
                    statusEl.className = 'pisk-status';
                } else {
                    statusEl.textContent = 'Soupeř přemýšlí...';
                    statusEl.className = 'pisk-status';
                }

            } catch (error) {
                console.error('Polling error:', error);
            }
        }

        async function klikMultiplayer(x, y) {
            if (!jsemNaTahu || hraSkoncila || !mistnostId) return;

            const cell = boardEl.querySelector(`[data-x="${x}"][data-y="${y}"]`);
            if (cell.classList.contains('obsazeno')) return;

            jsemNaTahu = false;
            statusEl.textContent = 'Odesílám tah...';

            try {
                const formData = new FormData();
                formData.append('action', 'piskvorky_tah');
                formData.append('mistnost_id', mistnostId);
                formData.append('radek', y);
                formData.append('sloupec', x);
                formData.append('csrf_token', CSRF_TOKEN);

                const response = await fetch('/api/hry_api.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                });

                const result = await response.json();

                if (result.status === 'success') {
                    aktualizujBoard(result.plocha);
                    aktualizujHrace(result.hraci, result.na_tahu);

                    if (result.vitez) {
                        hraSkoncila = true;
                        zastavitPolling();
                        novaHraBtn.style.display = 'inline-block';

                        if (result.vitez === mujSymbol) {
                            statusEl.textContent = 'Vyhrál jsi!';
                            statusEl.className = 'pisk-status vyhral';
                        }
                    } else {
                        statusEl.textContent = 'Soupeř přemýšlí...';
                    }
                } else {
                    alert('Chyba: ' + result.message);
                    jsemNaTahu = true;
                }
            } catch (error) {
                console.error('Tah error:', error);
                alert('Chyba při odesílání tahu');
                jsemNaTahu = true;
            }
        }

        // ==================== SPOLEČNÉ ====================

        function klikNaPole(x, y) {
            if (rezim === 'solo') {
                klikSolo(x, y);
            } else if (rezim === 'multiplayer') {
                klikMultiplayer(x, y);
            }
        }

        async function novaHra() {
            hraSkoncila = false;
            mujSymbol = null;
            jsemNaTahu = false;
            zastavitPolling();

            if (mistnostId) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'opustit');
                    formData.append('mistnost_id', mistnostId);
                    formData.append('csrf_token', CSRF_TOKEN);
                    await fetch('/api/hry_api.php', { method: 'POST', body: formData, credentials: 'include' });
                } catch (e) {}
            }

            mistnostId = null;
            novaHraBtn.style.display = 'none';

            if (rezim === 'solo') {
                spustitSolo();
            } else {
                zobrazLobby();
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Event listenery
        soloBtn.addEventListener('click', spustitSolo);
        hledatBtn.addEventListener('click', hledatSoupere);
        zrusitBtn.addEventListener('click', zrusitHledani);
        novaHraBtn.addEventListener('click', novaHra);

        // Inicializace
        vytvorBoard();

        // Heartbeat
        setInterval(async () => {
            try {
                await fetch('/api/hry_api.php?action=heartbeat', { credentials: 'include' });
            } catch (e) {}
        }, 30000);

        // Cleanup při opuštění stránky
        window.addEventListener('beforeunload', () => {
            if (mistnostId) {
                const data = new FormData();
                data.append('action', 'opustit');
                data.append('mistnost_id', mistnostId);
                data.append('csrf_token', CSRF_TOKEN);
                navigator.sendBeacon('/api/hry_api.php', data);
            }
        });

    })();
    </script>

<?php include __DIR__ . '/../includes/hry-sidebar.php'; ?>
</body>
</html>
