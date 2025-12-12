<?php
/**
 * Dáma - klasická desková hra
 * Podporuje hru proti AI i multiplayer
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
        VALUES (:user_id, :username, 'Dáma', NOW())
        ON DUPLICATE KEY UPDATE aktualni_hra = 'Dáma', posledni_aktivita = NOW()
    ");
    $stmt->execute(['user_id' => $userId, 'username' => $userName]);
} catch (PDOException $e) {
    error_log("Dama online error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dáma | Herní zóna</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --dama-bg: #0a0a0a;
            --dama-card: #1a1a1a;
            --dama-border: #333;
            --dama-text: #fff;
            --dama-muted: #888;
            --dama-accent: #0099ff;
            --dama-light-square: #e8d4b8;
            --dama-dark-square: #8b4513;
            --dama-white-piece: #f0f0f0;
            --dama-black-piece: #1a1a1a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--dama-bg);
            color: var(--dama-text);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .dama-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 1rem;
        }

        /* Horní panel */
        .horni-panel {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            margin-bottom: 1rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .zpet-btn {
            background: rgba(0,0,0,0.85);
            color: #fff;
            border: 1px solid #333;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .zpet-btn:hover {
            background: var(--dama-accent);
            border-color: var(--dama-accent);
        }

        .info-panel {
            background: var(--dama-card);
            padding: 1rem 1.5rem;
            border-radius: 10px;
            display: flex;
            gap: 2rem;
            border: 1px solid var(--dama-border);
        }

        .info-radek {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .info-label {
            color: var(--dama-muted);
        }

        .info-hodnota {
            font-weight: bold;
        }

        .info-hodnota.na-tahu {
            color: var(--dama-accent);
        }

        /* Volba režimu */
        .rezim-panel {
            background: var(--dama-card);
            border: 1px solid var(--dama-border);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 1rem;
        }

        .rezim-panel h2 {
            margin-bottom: 1.5rem;
            color: var(--dama-accent);
        }

        .rezim-btns {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .rezim-btn {
            background: var(--dama-bg);
            color: #fff;
            border: 2px solid var(--dama-border);
            padding: 1rem 2rem;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s;
        }

        .rezim-btn:hover {
            border-color: var(--dama-accent);
            background: rgba(0, 153, 255, 0.1);
        }

        .rezim-btn.selected {
            border-color: var(--dama-accent);
            background: var(--dama-accent);
            color: #000;
        }

        /* Herní plocha */
        .hra-wrapper {
            display: none;
        }

        .hra-wrapper.active {
            display: block;
        }

        .board-container {
            display: flex;
            justify-content: center;
            margin: 1rem 0;
        }

        .board {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            border: 4px solid var(--dama-border);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }

        .square {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            position: relative;
        }

        .square.light {
            background: var(--dama-light-square);
        }

        .square.dark {
            background: var(--dama-dark-square);
        }

        .square.selected {
            box-shadow: inset 0 0 0 3px var(--dama-accent);
        }

        .square.mozny-tah::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            background: rgba(0, 153, 255, 0.5);
            border-radius: 50%;
        }

        .square.mozny-skok::after {
            content: '';
            position: absolute;
            width: 30px;
            height: 30px;
            background: rgba(255, 100, 100, 0.5);
            border-radius: 50%;
        }

        /* Kameny */
        .piece {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            cursor: pointer;
            transition: transform 0.2s;
            position: relative;
            box-shadow: 0 3px 8px rgba(0,0,0,0.4);
        }

        .piece:hover {
            transform: scale(1.1);
        }

        .piece.white {
            background: radial-gradient(circle at 30% 30%, #fff, #ccc);
            border: 2px solid #999;
        }

        .piece.black {
            background: radial-gradient(circle at 30% 30%, #444, #111);
            border: 2px solid #000;
        }

        .piece.king::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            background: gold;
            border-radius: 50%;
            box-shadow: 0 0 10px gold;
        }

        .piece.white.king::after {
            background: #ffd700;
        }

        .piece.black.king::after {
            background: #ffd700;
        }

        /* Skóre panely */
        .score-panels {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            gap: 1rem;
        }

        .score-panel {
            flex: 1;
            background: var(--dama-card);
            border: 1px solid var(--dama-border);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
        }

        .score-panel.active {
            border-color: var(--dama-accent);
            box-shadow: 0 0 15px rgba(0, 153, 255, 0.3);
        }

        .score-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .score-pieces {
            display: flex;
            gap: 0.25rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .captured-piece {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            opacity: 0.6;
        }

        .captured-piece.white {
            background: #ccc;
            border: 1px solid #999;
        }

        .captured-piece.black {
            background: #333;
            border: 1px solid #000;
        }

        /* Akční tlačítka */
        .akce-panel {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-top: 1rem;
        }

        .akce-btn {
            background: var(--dama-card);
            color: #fff;
            border: 1px solid var(--dama-border);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .akce-btn:hover {
            background: var(--dama-accent);
            border-color: var(--dama-accent);
            color: #000;
        }

        /* Výsledek */
        .vysledek-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .vysledek-overlay.show {
            display: flex;
        }

        .vysledek-modal {
            background: var(--dama-card);
            border: 2px solid var(--dama-accent);
            border-radius: 15px;
            padding: 3rem;
            text-align: center;
            max-width: 400px;
        }

        .vysledek-modal h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .vysledek-modal .vitez {
            color: var(--dama-accent);
        }

        .vysledek-modal .prohra {
            color: #ff6666;
        }

        .vysledek-btn {
            background: var(--dama-accent);
            color: #000;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 1.5rem;
        }

        /* AI obtížnost */
        .ai-panel {
            display: none;
            margin-top: 1rem;
        }

        .ai-panel.show {
            display: block;
        }

        .ai-panel h3 {
            margin-bottom: 1rem;
            color: var(--dama-muted);
        }

        .ai-btns {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }

        .ai-btn {
            background: var(--dama-bg);
            color: #fff;
            border: 1px solid var(--dama-border);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .ai-btn:hover, .ai-btn.selected {
            background: var(--dama-accent);
            color: #000;
            border-color: var(--dama-accent);
        }

        /* Responsive */
        @media (max-width: 600px) {
            .square {
                width: 42px;
                height: 42px;
            }

            .piece {
                width: 34px;
                height: 34px;
            }

            .piece.king::after {
                width: 14px;
                height: 14px;
            }

            .info-panel {
                flex-direction: column;
                gap: 0.5rem;
            }

            .horni-panel {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/hamburger-menu.php'; ?>

    <main class="dama-container">
        <div class="horni-panel">
            <a href="/hry.php" class="zpet-btn">Zpět do lobby</a>

            <div class="info-panel">
                <div class="info-radek">
                    <span class="info-label">Na tahu:</span>
                    <span class="info-hodnota na-tahu" id="naTahu">Bílý</span>
                </div>
                <div class="info-radek">
                    <span class="info-label">Režim:</span>
                    <span class="info-hodnota" id="rezimInfo">--</span>
                </div>
            </div>
        </div>

        <!-- Volba režimu -->
        <div class="rezim-panel" id="rezimPanel">
            <h2>Vyber režim hry</h2>
            <div class="rezim-btns">
                <button class="rezim-btn" data-rezim="ai">Proti počítači</button>
                <button class="rezim-btn" data-rezim="local">Lokální hra (2 hráči)</button>
            </div>

            <div class="ai-panel" id="aiPanel">
                <h3>Obtížnost AI:</h3>
                <div class="ai-btns">
                    <button class="ai-btn" data-level="1">Lehká</button>
                    <button class="ai-btn selected" data-level="2">Střední</button>
                    <button class="ai-btn" data-level="3">Těžká</button>
                </div>
            </div>
        </div>

        <!-- Herní plocha -->
        <div class="hra-wrapper" id="hraWrapper">
            <div class="score-panels">
                <div class="score-panel" id="panelBily">
                    <div class="score-name">Bílý</div>
                    <div class="score-pieces" id="sebraneBlack"></div>
                </div>
                <div class="score-panel" id="panelCerny">
                    <div class="score-name" id="cernyName">Černý</div>
                    <div class="score-pieces" id="sebraneWhite"></div>
                </div>
            </div>

            <div class="board-container">
                <div class="board" id="board"></div>
            </div>

            <div class="akce-panel">
                <button class="akce-btn" id="btnNovaHra">Nová hra</button>
                <button class="akce-btn" id="btnZmenaRezimu">Změnit režim</button>
            </div>
        </div>
    </main>

    <!-- Výsledek -->
    <div class="vysledek-overlay" id="vysledekOverlay">
        <div class="vysledek-modal">
            <h2 id="vysledekText">Konec hry</h2>
            <button class="vysledek-btn" id="vysledekBtn">Hrát znovu</button>
        </div>
    </div>

    <script>
    (function() {
        'use strict';

        // Stav hry
        let board = [];
        let selectedPiece = null;
        let currentPlayer = 'white';
        let gameMode = null; // 'ai' nebo 'local'
        let aiLevel = 2;
        let mustJump = false;
        let capturedWhite = [];
        let capturedBlack = [];

        // DOM elementy
        const boardEl = document.getElementById('board');
        const rezimPanel = document.getElementById('rezimPanel');
        const hraWrapper = document.getElementById('hraWrapper');
        const aiPanel = document.getElementById('aiPanel');
        const naTahuEl = document.getElementById('naTahu');
        const rezimInfoEl = document.getElementById('rezimInfo');
        const vysledekOverlay = document.getElementById('vysledekOverlay');
        const vysledekText = document.getElementById('vysledekText');
        const panelBily = document.getElementById('panelBily');
        const panelCerny = document.getElementById('panelCerny');

        // Inicializace desky
        function initBoard() {
            board = [];
            for (let row = 0; row < 8; row++) {
                board[row] = [];
                for (let col = 0; col < 8; col++) {
                    if ((row + col) % 2 === 1) {
                        if (row < 3) {
                            board[row][col] = { color: 'black', king: false };
                        } else if (row > 4) {
                            board[row][col] = { color: 'white', king: false };
                        } else {
                            board[row][col] = null;
                        }
                    } else {
                        board[row][col] = null;
                    }
                }
            }
            selectedPiece = null;
            currentPlayer = 'white';
            mustJump = false;
            capturedWhite = [];
            capturedBlack = [];
            renderBoard();
            updateUI();
        }

        // Renderování desky
        function renderBoard() {
            boardEl.innerHTML = '';

            const validMoves = selectedPiece ? getValidMoves(selectedPiece.row, selectedPiece.col) : [];

            for (let row = 0; row < 8; row++) {
                for (let col = 0; col < 8; col++) {
                    const square = document.createElement('div');
                    square.className = 'square ' + ((row + col) % 2 === 0 ? 'light' : 'dark');
                    square.dataset.row = row;
                    square.dataset.col = col;

                    // Zvýraznění vybraného pole
                    if (selectedPiece && selectedPiece.row === row && selectedPiece.col === col) {
                        square.classList.add('selected');
                    }

                    // Zvýraznění možných tahů
                    const move = validMoves.find(m => m.row === row && m.col === col);
                    if (move) {
                        square.classList.add(move.jump ? 'mozny-skok' : 'mozny-tah');
                    }

                    // Přidání kamene
                    const piece = board[row][col];
                    if (piece) {
                        const pieceEl = document.createElement('div');
                        pieceEl.className = `piece ${piece.color}${piece.king ? ' king' : ''}`;
                        square.appendChild(pieceEl);
                    }

                    square.addEventListener('click', () => handleSquareClick(row, col));
                    boardEl.appendChild(square);
                }
            }

            // Aktualizovat sebrané kameny
            updateCaptured();
        }

        // Aktualizovat sebrané kameny
        function updateCaptured() {
            document.getElementById('sebraneWhite').innerHTML = capturedWhite.map(() =>
                '<div class="captured-piece white"></div>'
            ).join('');

            document.getElementById('sebraneBlack').innerHTML = capturedBlack.map(() =>
                '<div class="captured-piece black"></div>'
            ).join('');
        }

        // Klik na pole
        function handleSquareClick(row, col) {
            const piece = board[row][col];

            // Pokud je vybraný kámen a kliknutí na možný tah
            if (selectedPiece) {
                const validMoves = getValidMoves(selectedPiece.row, selectedPiece.col);
                const move = validMoves.find(m => m.row === row && m.col === col);

                if (move) {
                    makeMove(selectedPiece.row, selectedPiece.col, row, col, move);
                    return;
                }
            }

            // Výběr kamene
            if (piece && piece.color === currentPlayer) {
                // Kontrola povinného skoku
                if (mustJump) {
                    const moves = getValidMoves(row, col);
                    if (!moves.some(m => m.jump)) {
                        return; // Musí skákat jiným kamenem
                    }
                }

                selectedPiece = { row, col };
                renderBoard();
            } else {
                selectedPiece = null;
                renderBoard();
            }
        }

        // Získání platných tahů
        function getValidMoves(row, col) {
            const piece = board[row][col];
            if (!piece) return [];

            const moves = [];
            const directions = piece.king
                ? [[-1, -1], [-1, 1], [1, -1], [1, 1]]
                : (piece.color === 'white' ? [[-1, -1], [-1, 1]] : [[1, -1], [1, 1]]);

            // Kontrola skoků
            let hasJumps = false;
            for (const [dr, dc] of directions) {
                const jumpRow = row + dr * 2;
                const jumpCol = col + dc * 2;
                const midRow = row + dr;
                const midCol = col + dc;

                if (jumpRow >= 0 && jumpRow < 8 && jumpCol >= 0 && jumpCol < 8) {
                    const midPiece = board[midRow][midCol];
                    if (midPiece && midPiece.color !== piece.color && !board[jumpRow][jumpCol]) {
                        moves.push({ row: jumpRow, col: jumpCol, jump: true, captured: { row: midRow, col: midCol } });
                        hasJumps = true;
                    }
                }
            }

            // Pokud existuje skok, musí se skákat (nelze tahat)
            if (hasJumps || mustJump) {
                return moves.filter(m => m.jump);
            }

            // Běžné tahy
            for (const [dr, dc] of directions) {
                const newRow = row + dr;
                const newCol = col + dc;

                if (newRow >= 0 && newRow < 8 && newCol >= 0 && newCol < 8 && !board[newRow][newCol]) {
                    moves.push({ row: newRow, col: newCol, jump: false });
                }
            }

            return moves;
        }

        // Kontrola jestli hráč musí skákat
        function playerHasJumps(color) {
            for (let row = 0; row < 8; row++) {
                for (let col = 0; col < 8; col++) {
                    const piece = board[row][col];
                    if (piece && piece.color === color) {
                        const moves = getValidMoves(row, col);
                        if (moves.some(m => m.jump)) {
                            return true;
                        }
                    }
                }
            }
            return false;
        }

        // Provedení tahu
        function makeMove(fromRow, fromCol, toRow, toCol, move) {
            const piece = board[fromRow][fromCol];

            // Přesun kamene
            board[toRow][toCol] = piece;
            board[fromRow][fromCol] = null;

            // Sebrání přeskočeného kamene
            if (move.jump && move.captured) {
                const captured = board[move.captured.row][move.captured.col];
                if (captured.color === 'white') {
                    capturedWhite.push(captured);
                } else {
                    capturedBlack.push(captured);
                }
                board[move.captured.row][move.captured.col] = null;

                // Zvuk sebrání
                if (window.HryZvuky) window.HryZvuky.prehrat('sebrani');
            } else {
                // Zvuk pohybu
                if (window.HryZvuky) window.HryZvuky.prehrat('pohyb');
            }

            // Povýšení na dámu
            if ((piece.color === 'white' && toRow === 0) || (piece.color === 'black' && toRow === 7)) {
                piece.king = true;
                // Zvuk povýšení
                if (window.HryZvuky) window.HryZvuky.prehrat('levelUp');
            }

            selectedPiece = null;

            // Kontrola dalšího skoku stejným kamenem
            if (move.jump) {
                const furtherJumps = getValidMoves(toRow, toCol).filter(m => m.jump);
                if (furtherJumps.length > 0) {
                    mustJump = true;
                    selectedPiece = { row: toRow, col: toCol };
                    renderBoard();
                    updateUI();
                    return;
                }
            }

            mustJump = false;

            // Změna hráče
            currentPlayer = currentPlayer === 'white' ? 'black' : 'white';

            // Kontrola povinného skoku pro nového hráče
            mustJump = playerHasJumps(currentPlayer);

            renderBoard();
            updateUI();

            // Kontrola konce hry
            if (checkGameOver()) {
                return;
            }

            // AI tah
            if (gameMode === 'ai' && currentPlayer === 'black') {
                setTimeout(makeAIMove, 500);
            }
        }

        // AI tah
        function makeAIMove() {
            const moves = getAllMoves('black');

            if (moves.length === 0) {
                endGame('white');
                return;
            }

            // Filtrace skoků (povinné)
            const jumps = moves.filter(m => m.jump);
            const availableMoves = jumps.length > 0 ? jumps : moves;

            let selectedMove;

            if (aiLevel === 1) {
                // Lehká - náhodný tah
                selectedMove = availableMoves[Math.floor(Math.random() * availableMoves.length)];
            } else if (aiLevel === 2) {
                // Střední - preferuje skoky a povýšení
                selectedMove = availableMoves.reduce((best, move) => {
                    let score = 0;
                    if (move.jump) score += 10;
                    if (move.toRow === 7) score += 5; // Povýšení
                    if (!best || score > best.score) {
                        return { ...move, score };
                    }
                    return best;
                }, null);
            } else {
                // Těžká - minimax (zjednodušený)
                selectedMove = getBestMove(availableMoves);
            }

            if (selectedMove) {
                selectedPiece = { row: selectedMove.fromRow, col: selectedMove.fromCol };
                const moveObj = {
                    row: selectedMove.toRow,
                    col: selectedMove.toCol,
                    jump: selectedMove.jump,
                    captured: selectedMove.captured
                };
                makeMove(selectedMove.fromRow, selectedMove.fromCol, selectedMove.toRow, selectedMove.toCol, moveObj);
            }
        }

        // Získat všechny tahy pro barvu
        function getAllMoves(color) {
            const moves = [];
            for (let row = 0; row < 8; row++) {
                for (let col = 0; col < 8; col++) {
                    const piece = board[row][col];
                    if (piece && piece.color === color) {
                        const pieceMoves = getValidMoves(row, col);
                        for (const move of pieceMoves) {
                            moves.push({
                                fromRow: row,
                                fromCol: col,
                                toRow: move.row,
                                toCol: move.col,
                                jump: move.jump,
                                captured: move.captured
                            });
                        }
                    }
                }
            }
            return moves;
        }

        // Nejlepší tah pro AI (těžká obtížnost)
        function getBestMove(moves) {
            let best = null;
            let bestScore = -Infinity;

            for (const move of moves) {
                let score = 0;

                // Skok = bonus
                if (move.jump) score += 15;

                // Povýšení
                if (move.toRow === 7) score += 10;

                // Preferovat střed desky
                const centerDist = Math.abs(3.5 - move.toCol);
                score -= centerDist;

                // Bezpečnost - kontrola jestli může být sebrán
                // (zjednodušená implementace)

                if (score > bestScore) {
                    bestScore = score;
                    best = move;
                }
            }

            return best;
        }

        // Kontrola konce hry
        function checkGameOver() {
            const whitePieces = countPieces('white');
            const blackPieces = countPieces('black');

            if (whitePieces === 0) {
                endGame('black');
                return true;
            }

            if (blackPieces === 0) {
                endGame('white');
                return true;
            }

            // Kontrola jestli má hráč nějaký tah
            const moves = getAllMoves(currentPlayer);
            if (moves.length === 0) {
                endGame(currentPlayer === 'white' ? 'black' : 'white');
                return true;
            }

            return false;
        }

        // Počet kamenů
        function countPieces(color) {
            let count = 0;
            for (let row = 0; row < 8; row++) {
                for (let col = 0; col < 8; col++) {
                    if (board[row][col] && board[row][col].color === color) {
                        count++;
                    }
                }
            }
            return count;
        }

        // Konec hry
        function endGame(winner) {
            const isWin = (gameMode === 'ai' && winner === 'white') ||
                          (gameMode === 'local');

            if (gameMode === 'ai') {
                if (winner === 'white') {
                    vysledekText.innerHTML = '<span class="vitez">Vyhrál jsi!</span>';
                    if (window.HryZvuky) window.HryZvuky.prehrat('vyhra');
                } else {
                    vysledekText.innerHTML = '<span class="prohra">Prohrál jsi</span>';
                    if (window.HryZvuky) window.HryZvuky.prehrat('prohra');
                }
            } else {
                vysledekText.innerHTML = `<span class="vitez">Vyhrál ${winner === 'white' ? 'Bílý' : 'Černý'}!</span>`;
                if (window.HryZvuky) window.HryZvuky.prehrat('vyhra');
            }

            vysledekOverlay.classList.add('show');
        }

        // Aktualizace UI
        function updateUI() {
            naTahuEl.textContent = currentPlayer === 'white' ? 'Bílý' : 'Černý';

            panelBily.classList.toggle('active', currentPlayer === 'white');
            panelCerny.classList.toggle('active', currentPlayer === 'black');
        }

        // Event listenery
        document.querySelectorAll('.rezim-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                gameMode = btn.dataset.rezim;

                document.querySelectorAll('.rezim-btn').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');

                if (gameMode === 'ai') {
                    aiPanel.classList.add('show');
                } else {
                    aiPanel.classList.remove('show');
                    startGame();
                }
            });
        });

        document.querySelectorAll('.ai-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.ai-btn').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                aiLevel = parseInt(btn.dataset.level);
                startGame();
            });
        });

        document.getElementById('btnNovaHra').addEventListener('click', () => {
            initBoard();
        });

        document.getElementById('btnZmenaRezimu').addEventListener('click', () => {
            hraWrapper.classList.remove('active');
            rezimPanel.style.display = 'block';
            gameMode = null;
            document.querySelectorAll('.rezim-btn').forEach(b => b.classList.remove('selected'));
            aiPanel.classList.remove('show');
        });

        document.getElementById('vysledekBtn').addEventListener('click', () => {
            vysledekOverlay.classList.remove('show');
            initBoard();
        });

        function startGame() {
            rezimPanel.style.display = 'none';
            hraWrapper.classList.add('active');

            if (gameMode === 'ai') {
                rezimInfoEl.textContent = `AI (${aiLevel === 1 ? 'Lehká' : aiLevel === 2 ? 'Střední' : 'Těžká'})`;
                document.getElementById('cernyName').textContent = 'Počítač';
            } else {
                rezimInfoEl.textContent = 'Lokální';
                document.getElementById('cernyName').textContent = 'Černý';
            }

            initBoard();
        }

        // Heartbeat
        setInterval(async () => {
            try {
                await fetch('/api/hry_api.php?action=heartbeat');
            } catch (e) {}
        }, 30000);

    })();
    </script>

<?php include __DIR__ . '/../includes/hry-sidebar.php'; ?>
</body>
</html>
