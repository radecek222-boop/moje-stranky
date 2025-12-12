<?php
/**
 * Hra Piskvorky
 * Strategicka hra pro 2 hrace (proti pocitaci)
 */
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

// Kontrola prihlaseni
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? $_SESSION['email'] ?? 'Hrac';

// Aktualizovat online status
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        INSERT INTO wgs_hry_online (user_id, username, posledni_aktivita, aktualni_hra)
        VALUES (:user_id, :username, NOW(), 'Piskvorky')
        ON DUPLICATE KEY UPDATE
            username = VALUES(username),
            posledni_aktivita = NOW(),
            aktualni_hra = 'Piskvorky'
    ");
    $stmt->execute(['user_id' => $userId, 'username' => $username]);
} catch (PDOException $e) {
    error_log("Piskvorky error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Piskvorky | Herni zona</title>
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

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--pisk-bg);
            color: var(--pisk-text);
            min-height: 100vh;
            margin: 0;
        }

        .pisk-container {
            max-width: 800px;
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

        .pisk-score {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 1.5rem;
        }

        .pisk-score-item {
            text-align: center;
        }

        .pisk-score-label {
            font-size: 0.8rem;
            color: var(--pisk-muted);
        }

        .pisk-score-value {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .pisk-score-value.x {
            color: var(--pisk-x);
        }

        .pisk-score-value.o {
            color: var(--pisk-o);
        }

        .pisk-board-wrapper {
            display: inline-block;
            background: var(--pisk-card);
            border: 2px solid var(--pisk-border);
            border-radius: 12px;
            padding: 10px;
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

        .pisk-cell:hover:not(.obsazeno) {
            background: rgba(0, 153, 255, 0.2);
        }

        .pisk-cell.obsazeno {
            cursor: default;
        }

        .pisk-cell.x {
            color: var(--pisk-x);
        }

        .pisk-cell.o {
            color: var(--pisk-o);
        }

        .pisk-cell.vitezna {
            background: rgba(0, 153, 255, 0.3);
            animation: pulse 0.5s ease-in-out infinite alternate;
        }

        @keyframes pulse {
            from { background: rgba(0, 153, 255, 0.2); }
            to { background: rgba(0, 153, 255, 0.4); }
        }

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

        .pisk-btn:hover {
            background: #33bbff;
        }

        .pisk-btn.secondary {
            background: var(--pisk-border);
            color: var(--pisk-text);
        }

        .pisk-btn.secondary:hover {
            background: #444;
        }

        .pisk-settings {
            margin-top: 1rem;
        }

        .pisk-settings label {
            color: var(--pisk-muted);
            margin-right: 0.5rem;
        }

        .pisk-settings select {
            background: var(--pisk-card);
            color: var(--pisk-text);
            border: 1px solid var(--pisk-border);
            padding: 0.5rem;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
        }

        @media (max-width: 550px) {
            .pisk-board {
                grid-template-columns: repeat(15, 20px);
                grid-template-rows: repeat(15, 20px);
            }

            .pisk-cell {
                width: 20px;
                height: 20px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/hamburger-menu.php'; ?>

    <main id="main-content" class="pisk-container">
        <div class="pisk-header">
            <h1>PISKVORKY</h1>
            <p>Spojte 5 symbolu v rade a vyhrajte!</p>
        </div>

        <div class="pisk-score">
            <div class="pisk-score-item">
                <div class="pisk-score-label">TY (X)</div>
                <div class="pisk-score-value x" id="scoreX">0</div>
            </div>
            <div class="pisk-score-item">
                <div class="pisk-score-label">POCITAC (O)</div>
                <div class="pisk-score-value o" id="scoreO">0</div>
            </div>
        </div>

        <div class="pisk-status" id="status">Tvuj tah - klikni na pole</div>

        <div class="pisk-board-wrapper">
            <div class="pisk-board" id="board"></div>
        </div>

        <div class="pisk-settings">
            <label for="difficulty">Obtiznost:</label>
            <select id="difficulty">
                <option value="easy">Lehka</option>
                <option value="medium" selected>Stredni</option>
                <option value="hard">Tezka</option>
            </select>
        </div>

        <div class="pisk-actions">
            <button class="pisk-btn" id="novaHraBtn">Nova hra</button>
            <a href="/hry.php" class="pisk-btn secondary">Zpet do herni zony</a>
        </div>
    </main>

    <script>
    (function() {
        'use strict';

        const VELIKOST = 15;
        const VYHERNI_POCET = 5;

        // Stav hry
        let board = [];
        let gameOver = false;
        let scoreX = 0;
        let scoreO = 0;

        // DOM
        const boardEl = document.getElementById('board');
        const statusEl = document.getElementById('status');
        const scoreXEl = document.getElementById('scoreX');
        const scoreOEl = document.getElementById('scoreO');
        const novaHraBtn = document.getElementById('novaHraBtn');
        const difficultyEl = document.getElementById('difficulty');

        // Inicializace
        function init() {
            board = Array(VELIKOST).fill(null).map(() => Array(VELIKOST).fill(null));
            gameOver = false;
            vykresliBoard();
            statusEl.textContent = 'Tvuj tah - klikni na pole';
            statusEl.className = 'pisk-status';
        }

        // Vykreslit board
        function vykresliBoard() {
            boardEl.innerHTML = '';
            for (let y = 0; y < VELIKOST; y++) {
                for (let x = 0; x < VELIKOST; x++) {
                    const cell = document.createElement('div');
                    cell.className = 'pisk-cell';
                    cell.dataset.x = x;
                    cell.dataset.y = y;

                    if (board[y][x]) {
                        cell.textContent = board[y][x];
                        cell.classList.add(board[y][x].toLowerCase(), 'obsazeno');
                    }

                    cell.addEventListener('click', () => klikNaPole(x, y));
                    boardEl.appendChild(cell);
                }
            }
        }

        // Klik na pole
        function klikNaPole(x, y) {
            if (gameOver || board[y][x]) return;

            // Hracuv tah
            board[y][x] = 'X';
            vykresliBoard();

            // Kontrola vyhry
            const vyhra = zkontrolujVyhru(x, y, 'X');
            if (vyhra) {
                gameOver = true;
                scoreX++;
                scoreXEl.textContent = scoreX;
                statusEl.textContent = 'Vyhral jsi!';
                statusEl.className = 'pisk-status vyhral';
                zvyrazniVyhru(vyhra);
                return;
            }

            // Kontrola remízy
            if (jePlnyBoard()) {
                gameOver = true;
                statusEl.textContent = 'Remiza!';
                return;
            }

            // Tah pocitace
            statusEl.textContent = 'Pocitac premysli...';
            setTimeout(tahPocitace, 300);
        }

        // Tah pocitace
        function tahPocitace() {
            if (gameOver) return;

            const [x, y] = najdiNejlepsiTah();
            board[y][x] = 'O';
            vykresliBoard();

            // Kontrola vyhry
            const vyhra = zkontrolujVyhru(x, y, 'O');
            if (vyhra) {
                gameOver = true;
                scoreO++;
                scoreOEl.textContent = scoreO;
                statusEl.textContent = 'Pocitac vyhral!';
                statusEl.className = 'pisk-status prohral';
                zvyrazniVyhru(vyhra);
                return;
            }

            // Kontrola remízy
            if (jePlnyBoard()) {
                gameOver = true;
                statusEl.textContent = 'Remiza!';
                return;
            }

            statusEl.textContent = 'Tvuj tah';
            statusEl.className = 'pisk-status';
        }

        // Najdi nejlepsi tah pro AI
        function najdiNejlepsiTah() {
            const difficulty = difficultyEl.value;
            let candidates = [];

            // Najdi vsechna volna pole v okolí existujicích kamenu
            for (let y = 0; y < VELIKOST; y++) {
                for (let x = 0; x < VELIKOST; x++) {
                    if (board[y][x]) continue;

                    // Kontrola zda je v okoli nejaky kamen
                    let vOkoli = false;
                    for (let dy = -2; dy <= 2; dy++) {
                        for (let dx = -2; dx <= 2; dx++) {
                            const ny = y + dy;
                            const nx = x + dx;
                            if (ny >= 0 && ny < VELIKOST && nx >= 0 && nx < VELIKOST) {
                                if (board[ny][nx]) {
                                    vOkoli = true;
                                    break;
                                }
                            }
                        }
                        if (vOkoli) break;
                    }

                    if (vOkoli || jePrvniTah()) {
                        const score = ohodnotPozici(x, y);
                        candidates.push({ x, y, score });
                    }
                }
            }

            if (candidates.length === 0) {
                // Prvni tah - hraj do stredu nebo blizko
                return [Math.floor(VELIKOST / 2), Math.floor(VELIKOST / 2)];
            }

            // Seradit podle skore
            candidates.sort((a, b) => b.score - a.score);

            // Podle obtiznosti
            if (difficulty === 'easy') {
                // Nahodny vybor z top 5
                const top = candidates.slice(0, Math.min(5, candidates.length));
                const choice = top[Math.floor(Math.random() * top.length)];
                return [choice.x, choice.y];
            } else if (difficulty === 'medium') {
                // Obcas udelat chybu
                if (Math.random() < 0.2 && candidates.length > 1) {
                    const choice = candidates[Math.floor(Math.random() * Math.min(3, candidates.length))];
                    return [choice.x, choice.y];
                }
            }

            // Hard - nejlepsi tah
            return [candidates[0].x, candidates[0].y];
        }

        // Je prvni tah?
        function jePrvniTah() {
            for (let y = 0; y < VELIKOST; y++) {
                for (let x = 0; x < VELIKOST; x++) {
                    if (board[y][x]) return false;
                }
            }
            return true;
        }

        // Ohodnotit pozici pro AI
        function ohodnotPozici(x, y) {
            let score = 0;

            // Ohodnotit pro utok (O)
            score += ohodnotSmer(x, y, 'O') * 1.1;

            // Ohodnotit pro obranu (X)
            score += ohodnotSmer(x, y, 'X');

            // Preferovat pozice blize stredu
            const stred = VELIKOST / 2;
            const vzdalenost = Math.sqrt(Math.pow(x - stred, 2) + Math.pow(y - stred, 2));
            score += (VELIKOST - vzdalenost) * 0.1;

            return score;
        }

        // Ohodnotit smery z pozice
        function ohodnotSmer(x, y, hrac) {
            const smery = [
                [[1, 0], [-1, 0]],   // horizontalni
                [[0, 1], [0, -1]],   // vertikalni
                [[1, 1], [-1, -1]], // diagonala
                [[1, -1], [-1, 1]]  // antidiagonala
            ];

            let maxScore = 0;

            for (const smer of smery) {
                let pocet = 1; // pocitame i testovanou pozici
                let otevreno = 0;
                let blokovano = 0;

                for (const [dx, dy] of smer) {
                    let nx = x + dx;
                    let ny = y + dy;
                    let smerPocet = 0;

                    while (nx >= 0 && nx < VELIKOST && ny >= 0 && ny < VELIKOST) {
                        if (board[ny][nx] === hrac) {
                            smerPocet++;
                            nx += dx;
                            ny += dy;
                        } else if (board[ny][nx] === null) {
                            otevreno++;
                            break;
                        } else {
                            blokovano++;
                            break;
                        }
                    }

                    pocet += smerPocet;
                }

                // Skore podle poctu v rade
                let smerScore = 0;
                if (pocet >= 5) {
                    smerScore = 100000; // Vyhra
                } else if (pocet === 4) {
                    if (blokovano === 0) smerScore = 10000; // Otevrena 4
                    else smerScore = 1000; // Polootevřená 4
                } else if (pocet === 3) {
                    if (blokovano === 0) smerScore = 500; // Otevrena 3
                    else smerScore = 100; // Polootevřená 3
                } else if (pocet === 2) {
                    if (blokovano === 0) smerScore = 50;
                    else smerScore = 10;
                }

                maxScore = Math.max(maxScore, smerScore);
            }

            return maxScore;
        }

        // Zkontrolovat vyhru
        function zkontrolujVyhru(x, y, hrac) {
            const smery = [
                [1, 0],   // horizontalni
                [0, 1],   // vertikalni
                [1, 1],   // diagonala
                [1, -1]   // antidiagonala
            ];

            for (const [dx, dy] of smery) {
                const pozice = [[x, y]];

                // Jit jednim smerem
                let nx = x + dx;
                let ny = y + dy;
                while (nx >= 0 && nx < VELIKOST && ny >= 0 && ny < VELIKOST && board[ny][nx] === hrac) {
                    pozice.push([nx, ny]);
                    nx += dx;
                    ny += dy;
                }

                // Jit opacnym smerem
                nx = x - dx;
                ny = y - dy;
                while (nx >= 0 && nx < VELIKOST && ny >= 0 && ny < VELIKOST && board[ny][nx] === hrac) {
                    pozice.push([nx, ny]);
                    nx -= dx;
                    ny -= dy;
                }

                if (pozice.length >= VYHERNI_POCET) {
                    return pozice;
                }
            }

            return null;
        }

        // Zvyraznit viteznou radu
        function zvyrazniVyhru(pozice) {
            pozice.forEach(([x, y]) => {
                const cell = boardEl.querySelector(`[data-x="${x}"][data-y="${y}"]`);
                if (cell) cell.classList.add('vitezna');
            });
        }

        // Je plny board?
        function jePlnyBoard() {
            for (let y = 0; y < VELIKOST; y++) {
                for (let x = 0; x < VELIKOST; x++) {
                    if (!board[y][x]) return false;
                }
            }
            return true;
        }

        // Nova hra
        novaHraBtn.addEventListener('click', init);

        // Heartbeat
        setInterval(async () => {
            try {
                await fetch('/api/hry_api.php?action=heartbeat');
            } catch (e) {}
        }, 30000);

        // Start
        init();
    })();
    </script>
</body>
</html>
