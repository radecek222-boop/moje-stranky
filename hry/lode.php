<?php
/**
 * Hra Lodě (Battleship)
 * Námořní bitva proti počítači
 */
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? $_SESSION['email'] ?? 'Hráč';

// Aktualizovat online status - hraje Lodě
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
    error_log("Lode error: " . $e->getMessage());
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

        * {
            box-sizing: border-box;
        }

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

        .lode-header p {
            color: var(--lode-muted);
        }

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

        .lode-boards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            justify-items: center;
        }

        .lode-board-wrapper {
            text-align: center;
        }

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

        .lode-board.souper .lode-cell:not(.hit):not(.miss) {
            cursor: crosshair;
        }

        .lode-board.souper .lode-cell:not(.hit):not(.miss):hover {
            background: rgba(0, 153, 255, 0.3);
        }

        .lode-cell.ship {
            background: var(--lode-ship);
        }

        .lode-cell.hit {
            background: var(--lode-hit);
            color: #fff;
        }

        .lode-cell.miss {
            background: var(--lode-miss);
        }

        .lode-cell.sunk {
            background: #991111;
        }

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

        .lode-actions {
            text-align: center;
            margin-top: 2rem;
        }

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

        .lode-btn:hover {
            background: #33bbff;
        }

        .lode-btn.secondary {
            background: var(--lode-border);
            color: var(--lode-text);
        }

        .lode-btn.secondary:hover {
            background: #444;
        }

        .lode-ships-remaining {
            display: flex;
            gap: 2rem;
            justify-content: center;
            margin-top: 1rem;
        }

        .lode-ships-col {
            text-align: center;
        }

        .lode-ships-col h4 {
            font-size: 0.9rem;
            color: var(--lode-muted);
            margin-bottom: 0.5rem;
        }

        .lode-ships-list {
            font-size: 0.85rem;
        }

        .lode-ships-list span {
            display: block;
            margin: 0.25rem 0;
        }

        .lode-ships-list span.sunk {
            text-decoration: line-through;
            color: var(--lode-hit);
        }

        /* Placement phase */
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

        .lode-rotate-btn {
            background: var(--lode-border);
            color: var(--lode-text);
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 0.5rem;
            font-family: 'Poppins', sans-serif;
        }

        @media (max-width: 800px) {
            .lode-boards {
                grid-template-columns: 1fr;
            }

            .lode-board {
                grid-template-columns: repeat(10, 28px);
                grid-template-rows: repeat(10, 28px);
            }

            .lode-cell {
                width: 28px;
                height: 28px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/hamburger-menu.php'; ?>

    <main id="main-content" class="lode-container">
        <div class="lode-header">
            <h1>LODE</h1>
            <p>Najdi a potop vsechny souper ovy lode!</p>
        </div>

        <div class="lode-status" id="status">Umisti sve lode kliknutim na svou hraci plochu</div>

        <!-- Placement phase info -->
        <div class="lode-placement" id="placementInfo">
            <div class="lode-placement-info">
                <div class="lode-placement-ship" id="currentShip">Letadlova lod (5 poli)</div>
                <button class="lode-rotate-btn" id="rotateBtn">Otocit (R)</button>
            </div>
        </div>

        <div class="lode-boards">
            <div class="lode-board-wrapper">
                <div class="lode-board-title">Tvoje pole</div>
                <div class="lode-board moje" id="mojeBoard"></div>
            </div>
            <div class="lode-board-wrapper">
                <div class="lode-board-title">Souper</div>
                <div class="lode-board souper" id="souperBoard"></div>
            </div>
        </div>

        <div class="lode-ships-remaining">
            <div class="lode-ships-col">
                <h4>Tvoje lode</h4>
                <div class="lode-ships-list" id="mojeLode"></div>
            </div>
            <div class="lode-ships-col">
                <h4>Souper ovy lode</h4>
                <div class="lode-ships-list" id="souperLode"></div>
            </div>
        </div>

        <div class="lode-legend">
            <div class="lode-legend-item">
                <div class="lode-legend-color" style="background: var(--lode-ship);"></div>
                Lod
            </div>
            <div class="lode-legend-item">
                <div class="lode-legend-color" style="background: var(--lode-hit);"></div>
                Zasah
            </div>
            <div class="lode-legend-item">
                <div class="lode-legend-color" style="background: var(--lode-miss);"></div>
                Mimo
            </div>
        </div>

        <div class="lode-actions">
            <button class="lode-btn" id="novaHraBtn" style="display:none;">Nova hra</button>
            <a href="/hry.php" class="lode-btn secondary">Zpet do herni zony</a>
        </div>
    </main>

    <script>
    (function() {
        'use strict';

        // Velikost hraci plochy
        const VELIKOST = 10;

        // Lode - nazev a delka
        const LODE = [
            { nazev: 'Letadlova lod', delka: 5 },
            { nazev: 'Kriznik', delka: 4 },
            { nazev: 'Torpedovka', delka: 3 },
            { nazev: 'Ponorka', delka: 3 },
            { nazev: 'Clun', delka: 2 }
        ];

        // Stav hry
        let faze = 'umistovani'; // 'umistovani', 'hra', 'konec'
        let aktualniLodIndex = 0;
        let orientace = 'horizontal'; // 'horizontal' nebo 'vertical'

        // Hraci plochy
        let mojePole = vytvoritPrazdnePole();
        let souperPole = vytvoritPrazdnePole();
        let mojeStrely = vytvoritPrazdnePole();
        let souperStrely = vytvoritPrazdnePole();

        // Lode hracu
        let mojeLode = [];
        let souperLode = [];

        // DOM elementy
        const mojeBoard = document.getElementById('mojeBoard');
        const souperBoard = document.getElementById('souperBoard');
        const statusEl = document.getElementById('status');
        const placementInfo = document.getElementById('placementInfo');
        const currentShipEl = document.getElementById('currentShip');
        const rotateBtn = document.getElementById('rotateBtn');
        const novaHraBtn = document.getElementById('novaHraBtn');
        const mojeLodeEl = document.getElementById('mojeLode');
        const souperLodeEl = document.getElementById('souperLode');

        // Inicializace
        function init() {
            vykresliPlochu(mojeBoard, mojePole, true);
            vykresliPlochu(souperBoard, souperPole, false);
            aktualizovatSeznamLodi();
            umistiSouperovyLode();
        }

        // Vytvorit prazdne pole
        function vytvoritPrazdnePole() {
            return Array(VELIKOST).fill(null).map(() => Array(VELIKOST).fill(0));
        }

        // Vykreslit hraci plochu
        function vykresliPlochu(boardEl, pole, jeMoje) {
            boardEl.innerHTML = '';
            for (let y = 0; y < VELIKOST; y++) {
                for (let x = 0; x < VELIKOST; x++) {
                    const cell = document.createElement('div');
                    cell.className = 'lode-cell';
                    cell.dataset.x = x;
                    cell.dataset.y = y;

                    if (jeMoje) {
                        if (pole[y][x] > 0) {
                            cell.classList.add('ship');
                        }
                        if (souperStrely[y][x] === 1) {
                            cell.classList.add(pole[y][x] > 0 ? 'hit' : 'miss');
                            cell.textContent = pole[y][x] > 0 ? 'X' : 'o';
                        }
                        cell.addEventListener('click', () => klikMojePole(x, y));
                        cell.addEventListener('mouseover', () => previewLod(x, y, boardEl));
                        cell.addEventListener('mouseout', () => clearPreview(boardEl));
                    } else {
                        if (mojeStrely[y][x] === 1) {
                            cell.classList.add(souperPole[y][x] > 0 ? 'hit' : 'miss');
                            cell.textContent = souperPole[y][x] > 0 ? 'X' : 'o';
                        }
                        cell.addEventListener('click', () => strelitNaSoupere(x, y));
                    }

                    boardEl.appendChild(cell);
                }
            }
        }

        // Preview lode pred umistenim
        function previewLod(x, y, boardEl) {
            if (faze !== 'umistovani') return;
            clearPreview(boardEl);

            const lod = LODE[aktualniLodIndex];
            const pozice = getPoziceLode(x, y, lod.delka, orientace);

            if (!pozice) return;

            pozice.forEach(([px, py]) => {
                const cell = boardEl.querySelector(`[data-x="${px}"][data-y="${py}"]`);
                if (cell && !cell.classList.contains('ship')) {
                    cell.style.background = 'rgba(0, 153, 255, 0.4)';
                }
            });
        }

        function clearPreview(boardEl) {
            boardEl.querySelectorAll('.lode-cell').forEach(cell => {
                if (!cell.classList.contains('ship') && !cell.classList.contains('hit') && !cell.classList.contains('miss')) {
                    cell.style.background = '';
                }
            });
        }

        // Ziskat pozice lode
        function getPoziceLode(x, y, delka, orientace) {
            const pozice = [];
            for (let i = 0; i < delka; i++) {
                const px = orientace === 'horizontal' ? x + i : x;
                const py = orientace === 'vertical' ? y + i : y;

                if (px >= VELIKOST || py >= VELIKOST) return null;
                pozice.push([px, py]);
            }
            return pozice;
        }

        // Zkontrolovat zda lze umistit lod
        function lzUmistitLod(pole, pozice) {
            for (const [px, py] of pozice) {
                // Kontrola samotneho pole
                if (pole[py][px] !== 0) return false;

                // Kontrola okolnich poli (lode se nesmi dotykat)
                for (let dy = -1; dy <= 1; dy++) {
                    for (let dx = -1; dx <= 1; dx++) {
                        const nx = px + dx;
                        const ny = py + dy;
                        if (nx >= 0 && nx < VELIKOST && ny >= 0 && ny < VELIKOST) {
                            if (pole[ny][nx] !== 0) return false;
                        }
                    }
                }
            }
            return true;
        }

        // Umistit lod
        function umistitLod(pole, pozice, lodId) {
            pozice.forEach(([px, py]) => {
                pole[py][px] = lodId;
            });
        }

        // Klik na moje pole (umistovani lodi)
        function klikMojePole(x, y) {
            if (faze !== 'umistovani') return;

            const lod = LODE[aktualniLodIndex];
            const pozice = getPoziceLode(x, y, lod.delka, orientace);

            if (!pozice || !lzUmistitLod(mojePole, pozice)) {
                statusEl.textContent = 'Sem lod umistit nelze!';
                return;
            }

            const lodId = aktualniLodIndex + 1;
            umistitLod(mojePole, pozice, lodId);
            mojeLode.push({ id: lodId, nazev: lod.nazev, pozice: pozice, zasahy: 0 });

            vykresliPlochu(mojeBoard, mojePole, true);
            aktualniLodIndex++;

            if (aktualniLodIndex >= LODE.length) {
                // Vsechny lode umisteny - zaciname hru
                faze = 'hra';
                placementInfo.style.display = 'none';
                statusEl.textContent = 'Klikni na souper ovo pole a strilej!';
                souperBoard.classList.add('aktivni');
            } else {
                currentShipEl.textContent = `${LODE[aktualniLodIndex].nazev} (${LODE[aktualniLodIndex].delka} poli)`;
            }

            aktualizovatSeznamLodi();
        }

        // Umistit souper ovy lode nahodne
        function umistiSouperovyLode() {
            LODE.forEach((lod, index) => {
                let umisteno = false;
                let pokusy = 0;

                while (!umisteno && pokusy < 1000) {
                    const x = Math.floor(Math.random() * VELIKOST);
                    const y = Math.floor(Math.random() * VELIKOST);
                    const ori = Math.random() < 0.5 ? 'horizontal' : 'vertical';
                    const pozice = getPoziceLode(x, y, lod.delka, ori);

                    if (pozice && lzUmistitLod(souperPole, pozice)) {
                        const lodId = index + 1;
                        umistitLod(souperPole, pozice, lodId);
                        souperLode.push({ id: lodId, nazev: lod.nazev, pozice: pozice, zasahy: 0 });
                        umisteno = true;
                    }
                    pokusy++;
                }
            });
        }

        // Strelit na soupere
        function strelitNaSoupere(x, y) {
            if (faze !== 'hra') return;
            if (mojeStrely[y][x] !== 0) return; // Uz jsme sem strileli

            mojeStrely[y][x] = 1;

            if (souperPole[y][x] > 0) {
                // Zasah!
                const lodId = souperPole[y][x];
                const lod = souperLode.find(l => l.id === lodId);
                lod.zasahy++;

                if (lod.zasahy >= lod.pozice.length) {
                    statusEl.textContent = `Potopil jsi ${lod.nazev}!`;
                } else {
                    statusEl.textContent = 'Zasah!';
                }

                // Kontrola vyhry
                if (souperLode.every(l => l.zasahy >= l.pozice.length)) {
                    faze = 'konec';
                    statusEl.textContent = 'VYHRAL JSI!';
                    statusEl.classList.add('vyhral');
                    novaHraBtn.style.display = 'inline-block';
                    aktualizovatSeznamLodi();
                    vykresliPlochu(souperBoard, souperPole, false);
                    return;
                }
            } else {
                statusEl.textContent = 'Mimo...';
            }

            vykresliPlochu(souperBoard, souperPole, false);
            aktualizovatSeznamLodi();

            // Souper stri li
            setTimeout(souperStrelba, 500);
        }

        // Souper ova strelba (jednoducha AI)
        function souperStrelba() {
            if (faze !== 'hra') return;

            let x, y;
            let pokusy = 0;

            // Najdi pole kde jeste nebylo strileno
            do {
                x = Math.floor(Math.random() * VELIKOST);
                y = Math.floor(Math.random() * VELIKOST);
                pokusy++;
            } while (souperStrely[y][x] !== 0 && pokusy < 1000);

            souperStrely[y][x] = 1;

            if (mojePole[y][x] > 0) {
                const lodId = mojePole[y][x];
                const lod = mojeLode.find(l => l.id === lodId);
                lod.zasahy++;

                if (lod.zasahy >= lod.pozice.length) {
                    statusEl.textContent = `Souper potopil tvou ${lod.nazev}! Tvuj tah.`;
                } else {
                    statusEl.textContent = 'Souper trefil! Tvuj tah.';
                }

                // Kontrola prohry
                if (mojeLode.every(l => l.zasahy >= l.pozice.length)) {
                    faze = 'konec';
                    statusEl.textContent = 'PROHRAL JSI!';
                    statusEl.classList.add('prohral');
                    novaHraBtn.style.display = 'inline-block';
                }
            } else {
                statusEl.textContent = 'Souper minul. Tvuj tah.';
            }

            vykresliPlochu(mojeBoard, mojePole, true);
            aktualizovatSeznamLodi();
        }

        // Aktualizovat seznam lodi
        function aktualizovatSeznamLodi() {
            mojeLodeEl.innerHTML = mojeLode.map(l => {
                const potopena = l.zasahy >= l.pozice.length;
                return `<span class="${potopena ? 'sunk' : ''}">${l.nazev}</span>`;
            }).join('') || '<span style="color: var(--lode-muted);">-</span>';

            souperLodeEl.innerHTML = souperLode.map(l => {
                const potopena = l.zasahy >= l.pozice.length;
                return `<span class="${potopena ? 'sunk' : ''}">${l.nazev}</span>`;
            }).join('') || '<span style="color: var(--lode-muted);">-</span>';
        }

        // Otocit lod
        rotateBtn.addEventListener('click', () => {
            orientace = orientace === 'horizontal' ? 'vertical' : 'horizontal';
        });

        // Klavesova zkratka pro otoceni
        document.addEventListener('keydown', (e) => {
            if (e.key === 'r' || e.key === 'R') {
                orientace = orientace === 'horizontal' ? 'vertical' : 'horizontal';
            }
        });

        // Nova hra
        novaHraBtn.addEventListener('click', () => {
            location.reload();
        });

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
