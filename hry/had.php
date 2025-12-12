<?php
/**
 * Had (Snake) - klasická arkádová hra
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
        VALUES (:user_id, :username, 'Had', NOW())
        ON DUPLICATE KEY UPDATE aktualni_hra = 'Had', posledni_aktivita = NOW()
    ");
    $stmt->execute(['user_id' => $userId, 'username' => $userName]);
} catch (PDOException $e) {
    error_log("Had online error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Had | Herní zóna</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --had-bg: #0a0a0a;
            --had-card: #1a1a1a;
            --had-border: #333;
            --had-text: #fff;
            --had-muted: #888;
            --had-accent: #0099ff;
            --had-snake: #39ff14;
            --had-food: #ff3939;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--had-bg);
            color: var(--had-text);
            min-height: 100vh;
            overflow: hidden;
        }

        .had-container {
            max-width: 700px;
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
            background: var(--had-accent);
            border-color: var(--had-accent);
        }

        .info-panel {
            background: var(--had-card);
            border: 1px solid var(--had-border);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            display: flex;
            gap: 1.5rem;
            font-size: 0.9rem;
        }

        .info-panel span { color: var(--had-muted); }
        .info-panel strong { color: var(--had-accent); margin-left: 0.25rem; }

        .hra-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        #hadCanvas {
            background: #111;
            border: 2px solid var(--had-border);
            border-radius: 4px;
            display: block;
        }

        .ovladani {
            display: grid;
            grid-template-columns: repeat(3, 60px);
            grid-template-rows: repeat(2, 60px);
            gap: 5px;
            margin-top: 1rem;
        }

        .ovladani button {
            background: var(--had-card);
            border: 1px solid var(--had-border);
            color: var(--had-text);
            font-size: 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .ovladani button:hover, .ovladani button:active {
            background: var(--had-accent);
            border-color: var(--had-accent);
        }

        .ovladani .nahoru { grid-column: 2; }
        .ovladani .doleva { grid-column: 1; grid-row: 2; }
        .ovladani .dolu { grid-column: 2; grid-row: 2; }
        .ovladani .doprava { grid-column: 3; grid-row: 2; }

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
            background: var(--had-card);
            border: 2px solid var(--had-accent);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
        }

        .game-over-box h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--had-accent);
        }

        .game-over-box p {
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
        }

        .restart-btn {
            background: var(--had-accent);
            color: #000;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .restart-btn:hover {
            background: #fff;
        }

        .instrukce {
            color: var(--had-muted);
            font-size: 0.8rem;
            text-align: center;
            margin-top: 0.5rem;
        }

        @media (max-width: 500px) {
            #hadCanvas { max-width: 100%; height: auto; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/hamburger-menu.php'; ?>

    <div class="had-container">
        <div class="horni-panel">
            <a href="/hry.php" class="zpet-btn">Zpet</a>
            <div class="info-panel">
                <div>Skore: <strong id="skore">0</strong></div>
                <div>Nejlepsi: <strong id="nejlepsi">0</strong></div>
            </div>
        </div>

        <div class="hra-wrapper">
            <canvas id="hadCanvas" width="400" height="400"></canvas>
            <p class="instrukce">Sipky nebo WASD | Mobil: tlacitka dole</p>
            <div class="ovladani">
                <button class="nahoru" onclick="zmenSmer('up')">^</button>
                <button class="doleva" onclick="zmenSmer('left')"><</button>
                <button class="dolu" onclick="zmenSmer('down')">v</button>
                <button class="doprava" onclick="zmenSmer('right')">></button>
            </div>
        </div>
    </div>

    <div class="game-over" id="gameOver">
        <div class="game-over-box">
            <h2>KONEC HRY</h2>
            <p>Skore: <strong id="finalSkore">0</strong></p>
            <button class="restart-btn" onclick="restartHru()">HRAT ZNOVU</button>
        </div>
    </div>

    <script>
    const canvas = document.getElementById('hadCanvas');
    const ctx = canvas.getContext('2d');

    const velikostPole = 20;
    const pocetPoli = canvas.width / velikostPole;

    let had = [{x: 10, y: 10}];
    let smer = {x: 1, y: 0};
    let novySmer = {x: 1, y: 0};
    let jidlo = {x: 15, y: 10};
    let skore = 0;
    let nejlepsiSkore = parseInt(localStorage.getItem('hadNejlepsi') || '0');
    let hraId = null;
    let rychlost = 150;

    document.getElementById('nejlepsi').textContent = nejlepsiSkore;

    function generujJidlo() {
        do {
            jidlo.x = Math.floor(Math.random() * pocetPoli);
            jidlo.y = Math.floor(Math.random() * pocetPoli);
        } while (had.some(s => s.x === jidlo.x && s.y === jidlo.y));
    }

    function vykresli() {
        // Pozadi
        ctx.fillStyle = '#111';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        // Mrizka
        ctx.strokeStyle = '#1a1a1a';
        ctx.lineWidth = 1;
        for (let i = 0; i <= pocetPoli; i++) {
            ctx.beginPath();
            ctx.moveTo(i * velikostPole, 0);
            ctx.lineTo(i * velikostPole, canvas.height);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(0, i * velikostPole);
            ctx.lineTo(canvas.width, i * velikostPole);
            ctx.stroke();
        }

        // Jidlo
        ctx.fillStyle = '#ff3939';
        ctx.beginPath();
        ctx.arc(
            jidlo.x * velikostPole + velikostPole/2,
            jidlo.y * velikostPole + velikostPole/2,
            velikostPole/2 - 2,
            0, Math.PI * 2
        );
        ctx.fill();

        // Had
        had.forEach((segment, i) => {
            ctx.fillStyle = i === 0 ? '#39ff14' : '#2acc10';
            ctx.fillRect(
                segment.x * velikostPole + 1,
                segment.y * velikostPole + 1,
                velikostPole - 2,
                velikostPole - 2
            );
        });
    }

    function pohyb() {
        smer = {...novySmer};

        const hlava = {
            x: had[0].x + smer.x,
            y: had[0].y + smer.y
        };

        // Kolize se stenou
        if (hlava.x < 0 || hlava.x >= pocetPoli || hlava.y < 0 || hlava.y >= pocetPoli) {
            konecHry();
            return;
        }

        // Kolize se sebou
        if (had.some(s => s.x === hlava.x && s.y === hlava.y)) {
            konecHry();
            return;
        }

        had.unshift(hlava);

        // Snedl jidlo?
        if (hlava.x === jidlo.x && hlava.y === jidlo.y) {
            skore += 10;
            document.getElementById('skore').textContent = skore;
            generujJidlo();
            // Zrychlit
            if (rychlost > 50) {
                rychlost -= 2;
                clearInterval(hraId);
                hraId = setInterval(herniSmycka, rychlost);
            }
        } else {
            had.pop();
        }
    }

    function herniSmycka() {
        pohyb();
        vykresli();
    }

    function konecHry() {
        clearInterval(hraId);
        hraId = null;

        if (skore > nejlepsiSkore) {
            nejlepsiSkore = skore;
            localStorage.setItem('hadNejlepsi', nejlepsiSkore);
            document.getElementById('nejlepsi').textContent = nejlepsiSkore;
        }

        document.getElementById('finalSkore').textContent = skore;
        document.getElementById('gameOver').classList.add('active');
    }

    function restartHru() {
        document.getElementById('gameOver').classList.remove('active');
        had = [{x: 10, y: 10}];
        smer = {x: 1, y: 0};
        novySmer = {x: 1, y: 0};
        skore = 0;
        rychlost = 150;
        document.getElementById('skore').textContent = '0';
        generujJidlo();
        hraId = setInterval(herniSmycka, rychlost);
    }

    function zmenSmer(novy) {
        const smery = {
            'up': {x: 0, y: -1},
            'down': {x: 0, y: 1},
            'left': {x: -1, y: 0},
            'right': {x: 1, y: 0}
        };

        const s = smery[novy];
        // Nelze jit opacnym smerem
        if (s.x !== -smer.x || s.y !== -smer.y) {
            novySmer = s;
        }
    }

    document.addEventListener('keydown', (e) => {
        switch(e.key) {
            case 'ArrowUp': case 'w': case 'W': zmenSmer('up'); e.preventDefault(); break;
            case 'ArrowDown': case 's': case 'S': zmenSmer('down'); e.preventDefault(); break;
            case 'ArrowLeft': case 'a': case 'A': zmenSmer('left'); e.preventDefault(); break;
            case 'ArrowRight': case 'd': case 'D': zmenSmer('right'); e.preventDefault(); break;
        }
    });

    // Start hry
    vykresli();
    hraId = setInterval(herniSmycka, rychlost);

    // Heartbeat
    setInterval(async () => {
        try { await fetch('/api/hry_api.php?action=heartbeat'); } catch (e) {}
    }, 30000);
    </script>
</body>
</html>
