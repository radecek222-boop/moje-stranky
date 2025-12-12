<?php
/**
 * Tetris - klasická puzzle hra
 */

require_once __DIR__ . '/../init.php';

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Hráč';

// Aktualizovat online status
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        INSERT INTO wgs_hry_online (user_id, username, aktualni_hra, posledni_aktivita)
        VALUES (:user_id, :username, 'Tetris', NOW())
        ON DUPLICATE KEY UPDATE aktualni_hra = 'Tetris', posledni_aktivita = NOW()
    ");
    $stmt->execute(['user_id' => $userId, 'username' => $userName]);
} catch (PDOException $e) {
    error_log("Tetris online error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tetris | Herní zóna</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --tet-bg: #0a0a0a;
            --tet-card: #1a1a1a;
            --tet-border: #333;
            --tet-text: #fff;
            --tet-muted: #888;
            --tet-accent: #0099ff;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--tet-bg);
            color: var(--tet-text);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .tet-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 1rem;
        }

        .horni-panel {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            margin-bottom: 0.5rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .zpet-btn {
            background: rgba(0,0,0,0.85);
            color: #fff;
            border: 1px solid #333;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .zpet-btn:hover {
            background: var(--tet-accent);
            border-color: var(--tet-accent);
        }

        .info-panel {
            background: var(--tet-card);
            border: 1px solid var(--tet-border);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            display: flex;
            gap: 1.5rem;
            font-size: 0.9rem;
        }

        .info-panel span { color: var(--tet-muted); }
        .info-panel strong { color: var(--tet-accent); margin-left: 0.25rem; }

        .hra-wrapper {
            display: flex;
            justify-content: center;
            gap: 1rem;
            align-items: flex-start;
        }

        #tetrisCanvas {
            background: #111;
            border: 2px solid var(--tet-border);
            border-radius: 4px;
            display: block;
        }

        .bokovka {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .dalsi-box {
            background: var(--tet-card);
            border: 1px solid var(--tet-border);
            border-radius: 8px;
            padding: 0.75rem;
            text-align: center;
        }

        .dalsi-box h3 {
            font-size: 0.75rem;
            color: var(--tet-muted);
            margin-bottom: 0.5rem;
        }

        #dalsiCanvas {
            background: #111;
            border-radius: 4px;
        }

        .ovladani {
            display: grid;
            grid-template-columns: repeat(3, 50px);
            grid-template-rows: repeat(3, 50px);
            gap: 4px;
            margin-top: 1rem;
        }

        .ovladani button {
            background: var(--tet-card);
            border: 1px solid var(--tet-border);
            color: var(--tet-text);
            font-size: 1.2rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .ovladani button:hover, .ovladani button:active {
            background: var(--tet-accent);
            border-color: var(--tet-accent);
        }

        .ovladani .otocit { grid-column: 2; grid-row: 1; }
        .ovladani .doleva { grid-column: 1; grid-row: 2; }
        .ovladani .dolu { grid-column: 2; grid-row: 2; }
        .ovladani .doprava { grid-column: 3; grid-row: 2; }
        .ovladani .pad { grid-column: 2; grid-row: 3; font-size: 0.8rem; }

        .game-over {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 100;
        }

        .game-over.active { display: flex; }

        .game-over-box {
            background: var(--tet-card);
            border: 2px solid var(--tet-accent);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
        }

        .game-over-box h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--tet-accent);
        }

        .game-over-box p { font-size: 1.2rem; margin-bottom: 0.5rem; }

        .restart-btn {
            background: var(--tet-accent);
            color: #000;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 1rem;
        }

        .restart-btn:hover { background: #fff; }

        .instrukce {
            color: var(--tet-muted);
            font-size: 0.75rem;
            text-align: center;
            margin-top: 0.5rem;
        }

        @media (max-width: 500px) {
            .hra-wrapper { flex-direction: column; align-items: center; }
            .bokovka { flex-direction: row; align-items: center; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/hamburger-menu.php'; ?>

    <div class="tet-container">
        <div class="horni-panel">
            <a href="/hry.php" class="zpet-btn">Zpet</a>
            <div class="info-panel">
                <div>Skore: <strong id="skore">0</strong></div>
                <div>Radky: <strong id="radky">0</strong></div>
                <div>Level: <strong id="level">1</strong></div>
            </div>
        </div>

        <div class="hra-wrapper">
            <canvas id="tetrisCanvas" width="200" height="400"></canvas>

            <div class="bokovka">
                <div class="dalsi-box">
                    <h3>DALSI</h3>
                    <canvas id="dalsiCanvas" width="80" height="80"></canvas>
                </div>

                <div class="ovladani">
                    <button class="otocit" onclick="otocit()">R</button>
                    <button class="doleva" onclick="pohybDoleva()"><</button>
                    <button class="dolu" onclick="pohybDolu()">v</button>
                    <button class="doprava" onclick="pohybDoprava()">></button>
                    <button class="pad" onclick="hardDrop()">PAD</button>
                </div>
            </div>
        </div>

        <p class="instrukce">Sipky: pohyb | Nahoru/R: otocit | Mezernik: pad</p>
    </div>

    <div class="game-over" id="gameOver">
        <div class="game-over-box">
            <h2>KONEC HRY</h2>
            <p>Skore: <strong id="finalSkore">0</strong></p>
            <p>Radky: <strong id="finalRadky">0</strong></p>
            <button class="restart-btn" onclick="restartHru()">HRAT ZNOVU</button>
        </div>
    </div>

    <script>
    const canvas = document.getElementById('tetrisCanvas');
    const ctx = canvas.getContext('2d');
    const dalsiCanvas = document.getElementById('dalsiCanvas');
    const dalsiCtx = dalsiCanvas.getContext('2d');

    const COLS = 10;
    const ROWS = 20;
    const BLOCK = 20;

    const BARVY = [
        null,
        '#00f0f0', // I - cyan
        '#f0f000', // O - zluta
        '#a000f0', // T - fialova
        '#00f000', // S - zelena
        '#f00000', // Z - cervena
        '#0000f0', // J - modra
        '#f0a000'  // L - oranzova
    ];

    const TVARY = [
        [],
        [[1,1,1,1]],                         // I
        [[2,2],[2,2]],                       // O
        [[0,3,0],[3,3,3]],                   // T
        [[0,4,4],[4,4,0]],                   // S
        [[5,5,0],[0,5,5]],                   // Z
        [[6,0,0],[6,6,6]],                   // J
        [[0,0,7],[7,7,7]]                    // L
    ];

    let plocha = [];
    let aktualni = null;
    let dalsi = null;
    let skore = 0;
    let radky = 0;
    let level = 1;
    let hraId = null;
    let rychlost = 1000;

    function vytvorPlochu() {
        plocha = [];
        for (let r = 0; r < ROWS; r++) {
            plocha.push(new Array(COLS).fill(0));
        }
    }

    function novyKus() {
        if (dalsi === null) {
            dalsi = vytvorKus();
        }
        aktualni = dalsi;
        dalsi = vytvorKus();
        vykresliDalsi();

        // Konec hry?
        if (kolize(aktualni)) {
            konecHry();
        }
    }

    function vytvorKus() {
        const typ = Math.floor(Math.random() * 7) + 1;
        return {
            typ: typ,
            tvar: TVARY[typ].map(row => [...row]),
            x: Math.floor(COLS / 2) - Math.ceil(TVARY[typ][0].length / 2),
            y: 0
        };
    }

    function kolize(kus, dx = 0, dy = 0, novyTvar = null) {
        const tvar = novyTvar || kus.tvar;
        for (let r = 0; r < tvar.length; r++) {
            for (let c = 0; c < tvar[r].length; c++) {
                if (tvar[r][c]) {
                    const nx = kus.x + c + dx;
                    const ny = kus.y + r + dy;
                    if (nx < 0 || nx >= COLS || ny >= ROWS) return true;
                    if (ny >= 0 && plocha[ny][nx]) return true;
                }
            }
        }
        return false;
    }

    function zamkniKus() {
        for (let r = 0; r < aktualni.tvar.length; r++) {
            for (let c = 0; c < aktualni.tvar[r].length; c++) {
                if (aktualni.tvar[r][c]) {
                    const ny = aktualni.y + r;
                    if (ny >= 0) {
                        plocha[ny][aktualni.x + c] = aktualni.typ;
                    }
                }
            }
        }
        vymazRadky();
        novyKus();
    }

    function vymazRadky() {
        let vymazano = 0;
        for (let r = ROWS - 1; r >= 0; r--) {
            if (plocha[r].every(c => c !== 0)) {
                plocha.splice(r, 1);
                plocha.unshift(new Array(COLS).fill(0));
                vymazano++;
                r++;
            }
        }

        if (vymazano > 0) {
            radky += vymazano;
            skore += [0, 100, 300, 500, 800][vymazano] * level;
            document.getElementById('skore').textContent = skore;
            document.getElementById('radky').textContent = radky;

            // Level up
            const novyLevel = Math.floor(radky / 10) + 1;
            if (novyLevel > level) {
                level = novyLevel;
                document.getElementById('level').textContent = level;
                rychlost = Math.max(100, 1000 - (level - 1) * 100);
                clearInterval(hraId);
                hraId = setInterval(herniSmycka, rychlost);
            }
        }
    }

    function vykresli() {
        ctx.fillStyle = '#111';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        // Mrizka
        ctx.strokeStyle = '#1a1a1a';
        for (let r = 0; r <= ROWS; r++) {
            ctx.beginPath();
            ctx.moveTo(0, r * BLOCK);
            ctx.lineTo(canvas.width, r * BLOCK);
            ctx.stroke();
        }
        for (let c = 0; c <= COLS; c++) {
            ctx.beginPath();
            ctx.moveTo(c * BLOCK, 0);
            ctx.lineTo(c * BLOCK, canvas.height);
            ctx.stroke();
        }

        // Plocha
        for (let r = 0; r < ROWS; r++) {
            for (let c = 0; c < COLS; c++) {
                if (plocha[r][c]) {
                    ctx.fillStyle = BARVY[plocha[r][c]];
                    ctx.fillRect(c * BLOCK + 1, r * BLOCK + 1, BLOCK - 2, BLOCK - 2);
                }
            }
        }

        // Aktualni kus
        if (aktualni) {
            ctx.fillStyle = BARVY[aktualni.typ];
            for (let r = 0; r < aktualni.tvar.length; r++) {
                for (let c = 0; c < aktualni.tvar[r].length; c++) {
                    if (aktualni.tvar[r][c]) {
                        ctx.fillRect(
                            (aktualni.x + c) * BLOCK + 1,
                            (aktualni.y + r) * BLOCK + 1,
                            BLOCK - 2, BLOCK - 2
                        );
                    }
                }
            }
        }
    }

    function vykresliDalsi() {
        dalsiCtx.fillStyle = '#111';
        dalsiCtx.fillRect(0, 0, dalsiCanvas.width, dalsiCanvas.height);

        if (dalsi) {
            dalsiCtx.fillStyle = BARVY[dalsi.typ];
            const offsetX = (dalsiCanvas.width - dalsi.tvar[0].length * BLOCK) / 2;
            const offsetY = (dalsiCanvas.height - dalsi.tvar.length * BLOCK) / 2;

            for (let r = 0; r < dalsi.tvar.length; r++) {
                for (let c = 0; c < dalsi.tvar[r].length; c++) {
                    if (dalsi.tvar[r][c]) {
                        dalsiCtx.fillRect(
                            offsetX + c * BLOCK + 1,
                            offsetY + r * BLOCK + 1,
                            BLOCK - 2, BLOCK - 2
                        );
                    }
                }
            }
        }
    }

    function herniSmycka() {
        if (!kolize(aktualni, 0, 1)) {
            aktualni.y++;
        } else {
            zamkniKus();
        }
        vykresli();
    }

    function pohybDoleva() {
        if (!kolize(aktualni, -1, 0)) {
            aktualni.x--;
            vykresli();
        }
    }

    function pohybDoprava() {
        if (!kolize(aktualni, 1, 0)) {
            aktualni.x++;
            vykresli();
        }
    }

    function pohybDolu() {
        if (!kolize(aktualni, 0, 1)) {
            aktualni.y++;
            vykresli();
        }
    }

    function otocit() {
        const novyTvar = aktualni.tvar[0].map((_, i) =>
            aktualni.tvar.map(row => row[i]).reverse()
        );
        if (!kolize(aktualni, 0, 0, novyTvar)) {
            aktualni.tvar = novyTvar;
            vykresli();
        }
    }

    function hardDrop() {
        while (!kolize(aktualni, 0, 1)) {
            aktualni.y++;
            skore += 2;
        }
        document.getElementById('skore').textContent = skore;
        zamkniKus();
        vykresli();
    }

    function konecHry() {
        clearInterval(hraId);
        hraId = null;
        document.getElementById('finalSkore').textContent = skore;
        document.getElementById('finalRadky').textContent = radky;
        document.getElementById('gameOver').classList.add('active');
    }

    function restartHru() {
        document.getElementById('gameOver').classList.remove('active');
        vytvorPlochu();
        skore = 0;
        radky = 0;
        level = 1;
        rychlost = 1000;
        dalsi = null;
        document.getElementById('skore').textContent = '0';
        document.getElementById('radky').textContent = '0';
        document.getElementById('level').textContent = '1';
        novyKus();
        vykresli();
        hraId = setInterval(herniSmycka, rychlost);
    }

    document.addEventListener('keydown', (e) => {
        if (!hraId) return;
        switch(e.key) {
            case 'ArrowLeft': case 'a': case 'A': pohybDoleva(); e.preventDefault(); break;
            case 'ArrowRight': case 'd': case 'D': pohybDoprava(); e.preventDefault(); break;
            case 'ArrowDown': case 's': case 'S': pohybDolu(); e.preventDefault(); break;
            case 'ArrowUp': case 'w': case 'W': case 'r': case 'R': otocit(); e.preventDefault(); break;
            case ' ': hardDrop(); e.preventDefault(); break;
        }
    });

    // Start
    vytvorPlochu();
    novyKus();
    vykresli();
    hraId = setInterval(herniSmycka, rychlost);

    // Heartbeat
    setInterval(async () => {
        try { await fetch('/api/hry_api.php?action=heartbeat'); } catch (e) {}
    }, 30000);
    </script>
</body>
</html>
