<?php
/**
 * Hra Piškvorky - Multiplayer
 * Strategická hra pro 2 hráče online
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
    <link rel="stylesheet" href="/assets/css/wgs-base.min.css">
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
            <p>Multiplayer - spoj 5 symbolů v řadě a vyhraj!</p>
        </div>

        <!-- Lobby - hledání soupeře -->
        <div class="pisk-lobby" id="lobby">
            <h2>Najdi soupeře</h2>
            <p style="color: var(--pisk-muted); margin-bottom: 1.5rem;">
                Klikni na tlačítko a systém tě automaticky spáruje s dalším hráčem.
            </p>
            <button class="pisk-btn velke" id="hledatBtn">HRÁT ONLINE</button>
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
        const USER_ID = <?php echo $userId; ?>;

        // Stav
        let mistnostId = null;
        let mujSymbol = null; // 1 = X, 2 = O
        let jsemNaTahu = false;
        let hraSkoncila = false;
        let pollingInterval = null;

        // DOM elementy
        const lobbyEl = document.getElementById('lobby');
        const cekaniEl = document.getElementById('cekani');
        const hraEl = document.getElementById('hra');
        const boardEl = document.getElementById('board');
        const statusEl = document.getElementById('status');
        const hraciPanelEl = document.getElementById('hraciPanel');
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

        // Aktualizovat board podle stavu ze serveru
        function aktualizujBoard(plocha) {
            const cells = boardEl.querySelectorAll('.pisk-cell');
            cells.forEach(cell => {
                const x = parseInt(cell.dataset.x);
                const y = parseInt(cell.dataset.y);
                const hodnota = plocha[y][x];

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

        // Aktualizovat panel hráčů
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

        // Hledat soupeře
        async function hledatSoupere() {
            lobbyEl.style.display = 'none';
            cekaniEl.style.display = 'block';

            try {
                const formData = new FormData();
                formData.append('action', 'quick_match');
                formData.append('hra', 'piskvorky');
                formData.append('csrf_token', CSRF_TOKEN);

                const response = await fetch('/api/hry_api.php', {
                    method: 'POST',
                    body: formData
                });

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
                alert('Chyba při hledání soupeře');
                zobrazLobby();
            }
        }

        // Zrušit hledání
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
                        body: formData
                    });
                } catch (e) {}
            }

            mistnostId = null;
            zobrazLobby();
        }

        // Zobrazit lobby
        function zobrazLobby() {
            lobbyEl.style.display = 'block';
            cekaniEl.style.display = 'none';
            hraEl.style.display = 'none';
            novaHraBtn.style.display = 'none';
        }

        // Spustit polling pro stav hry
        function spustitPolling() {
            nacistStav();
            pollingInterval = setInterval(nacistStav, 1000);
        }

        // Zastavit polling
        function zastavitPolling() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
        }

        // Načíst stav hry ze serveru
        async function nacistStav() {
            if (!mistnostId) return;

            try {
                const response = await fetch(`/api/hry_api.php?action=piskvorky_stav&mistnost_id=${mistnostId}`);
                const result = await response.json();

                if (result.status !== 'success') {
                    console.error('Chyba stavu:', result.message);
                    return;
                }

                const data = result.data;

                // Aktualizovat můj symbol
                mujSymbol = data.muj_symbol;
                jsemNaTahu = data.jsem_na_tahu;

                // Čekám na soupeře?
                if (data.hraci.length < 2) {
                    cekaniEl.style.display = 'block';
                    hraEl.style.display = 'none';
                    return;
                }

                // Zobrazit hru
                cekaniEl.style.display = 'none';
                hraEl.style.display = 'block';

                // Aktualizovat UI
                aktualizujHrace(data.hraci, data.na_tahu);
                aktualizujBoard(data.plocha);

                // Status
                if (data.vitez) {
                    hraSkoncila = true;
                    zastavitPolling();
                    novaHraBtn.style.display = 'inline-block';

                    if (data.vitez === 'remiza') {
                        statusEl.textContent = 'Remíza!';
                        statusEl.className = 'pisk-status';
                    } else if (data.vitez === mujSymbol) {
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

        // Klik na pole
        async function klikNaPole(x, y) {
            if (!jsemNaTahu || hraSkoncila || !mistnostId) return;

            // Zkontrolovat, zda je pole prázdné
            const cell = boardEl.querySelector(`[data-x="${x}"][data-y="${y}"]`);
            if (cell.classList.contains('obsazeno')) return;

            // Dočasně zablokovat další tahy
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
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    // Okamžitě aktualizovat UI
                    aktualizujBoard(result.data.plocha);
                    aktualizujHrace(result.data.hraci, result.data.na_tahu);

                    if (result.data.vitez) {
                        hraSkoncila = true;
                        zastavitPolling();
                        novaHraBtn.style.display = 'inline-block';

                        if (result.data.vitez === mujSymbol) {
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

        // Nová hra
        async function novaHra() {
            hraSkoncila = false;
            mujSymbol = null;
            jsemNaTahu = false;

            // Opustit starou místnost
            if (mistnostId) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'opustit');
                    formData.append('mistnost_id', mistnostId);
                    formData.append('csrf_token', CSRF_TOKEN);
                    await fetch('/api/hry_api.php', { method: 'POST', body: formData });
                } catch (e) {}
            }

            mistnostId = null;
            novaHraBtn.style.display = 'none';
            hledatSoupere();
        }

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Event listenery
        hledatBtn.addEventListener('click', hledatSoupere);
        zrusitBtn.addEventListener('click', zrusitHledani);
        novaHraBtn.addEventListener('click', novaHra);

        // Inicializace
        vytvorBoard();

        // Heartbeat
        setInterval(async () => {
            try {
                await fetch('/api/hry_api.php?action=heartbeat');
            } catch (e) {}
        }, 30000);

        // Cleanup při opuštění stránky
        window.addEventListener('beforeunload', () => {
            if (mistnostId) {
                navigator.sendBeacon('/api/hry_api.php?action=opustit&mistnost_id=' + mistnostId);
            }
        });

    })();
    </script>
</body>
</html>
