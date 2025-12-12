<?php
/**
 * Breakout - klasická arkádová hra
 * Odrážení míčku a rozbíjení cihel
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
        VALUES (:user_id, :username, 'Breakout', NOW())
        ON DUPLICATE KEY UPDATE aktualni_hra = 'Breakout', posledni_aktivita = NOW()
    ");
    $stmt->execute(['user_id' => $userId, 'username' => $userName]);
} catch (PDOException $e) {
    error_log("Breakout online error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Breakout | Herní zóna</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --break-bg: #0a0a0a;
            --break-card: #1a1a1a;
            --break-border: #333;
            --break-text: #fff;
            --break-muted: #888;
            --break-accent: #0099ff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--break-bg);
            color: var(--break-text);
            min-height: 100vh;
            overflow: hidden;
        }

        .break-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 1rem;
        }

        /* Horní panel */
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
            background: var(--break-accent);
            border-color: var(--break-accent);
        }

        .info-panel {
            background: var(--break-card);
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            display: flex;
            gap: 2rem;
            border: 1px solid var(--break-border);
        }

        .info-radek {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .info-label {
            color: var(--break-muted);
            font-size: 0.85rem;
        }

        .info-hodnota {
            font-weight: bold;
            font-size: 1.1rem;
        }

        .info-hodnota.skore {
            color: var(--break-accent);
        }

        .info-hodnota.zivoty {
            color: #ff6666;
        }

        /* Herní canvas */
        .game-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        #gameCanvas {
            background: #111;
            border: 3px solid var(--break-border);
            border-radius: 10px;
            display: block;
            max-width: 100%;
        }

        /* Overlay */
        .overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .overlay.show {
            display: flex;
        }

        .overlay-modal {
            background: var(--break-card);
            border: 2px solid var(--break-accent);
            border-radius: 15px;
            padding: 3rem;
            text-align: center;
            max-width: 400px;
        }

        .overlay-modal h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .overlay-modal .win {
            color: var(--break-accent);
        }

        .overlay-modal .lose {
            color: #ff6666;
        }

        .overlay-modal p {
            color: var(--break-muted);
            margin-bottom: 1.5rem;
        }

        .overlay-btn {
            background: var(--break-accent);
            color: #000;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin: 0.5rem;
        }

        .overlay-btn.secondary {
            background: var(--break-border);
            color: #fff;
        }

        /* Start obrazovka */
        .start-info {
            margin-bottom: 1.5rem;
        }

        .start-info h3 {
            color: var(--break-accent);
            margin-bottom: 0.5rem;
        }

        .start-info ul {
            list-style: none;
            color: var(--break-muted);
            font-size: 0.9rem;
        }

        .start-info li {
            margin: 0.25rem 0;
        }

        /* Level info */
        .level-info {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 3rem;
            font-weight: 700;
            color: var(--break-accent);
            text-shadow: 0 0 20px var(--break-accent);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }

        .level-info.show {
            opacity: 1;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .info-panel {
                flex-direction: column;
                gap: 0.5rem;
                padding: 0.5rem 1rem;
            }

            .horni-panel {
                flex-direction: column;
            }

            #gameCanvas {
                max-height: 60vh;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/hamburger-menu.php'; ?>

    <main class="break-container">
        <div class="horni-panel">
            <a href="/hry.php" class="zpet-btn">Zpět do lobby</a>

            <div class="info-panel">
                <div class="info-radek">
                    <span class="info-label">Skóre:</span>
                    <span class="info-hodnota skore" id="skore">0</span>
                </div>
                <div class="info-radek">
                    <span class="info-label">Level:</span>
                    <span class="info-hodnota" id="level">1</span>
                </div>
                <div class="info-radek">
                    <span class="info-label">Životy:</span>
                    <span class="info-hodnota zivoty" id="zivoty">3</span>
                </div>
            </div>
        </div>

        <div class="game-wrapper">
            <canvas id="gameCanvas" width="800" height="500"></canvas>
        </div>
    </main>

    <!-- Start overlay -->
    <div class="overlay show" id="startOverlay">
        <div class="overlay-modal">
            <h2>Breakout</h2>
            <div class="start-info">
                <h3>Ovládání:</h3>
                <ul>
                    <li>Myš / Touch - pohyb pálky</li>
                    <li>Klávesy A/D nebo šipky - pohyb pálky</li>
                    <li>Mezerník - pauza</li>
                </ul>
            </div>
            <button class="overlay-btn" id="startBtn">Začít hru</button>
        </div>
    </div>

    <!-- Game Over overlay -->
    <div class="overlay" id="gameOverOverlay">
        <div class="overlay-modal">
            <h2 id="gameOverTitle">Game Over</h2>
            <p id="gameOverText">Skóre: 0</p>
            <button class="overlay-btn" id="restartBtn">Hrát znovu</button>
            <button class="overlay-btn secondary" id="menuBtn">Zpět do menu</button>
        </div>
    </div>

    <script>
    (function() {
        'use strict';

        const canvas = document.getElementById('gameCanvas');
        const ctx = canvas.getContext('2d');

        // Přizpůsobení velikosti
        function resizeCanvas() {
            const maxWidth = Math.min(800, window.innerWidth - 40);
            const ratio = canvas.height / canvas.width;
            canvas.style.width = maxWidth + 'px';
            canvas.style.height = (maxWidth * ratio) + 'px';
        }
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        // Herní stav
        let gameRunning = false;
        let paused = false;
        let score = 0;
        let lives = 3;
        let level = 1;

        // Pálka
        const paddle = {
            width: 100,
            height: 12,
            x: 0,
            speed: 8
        };
        paddle.x = (canvas.width - paddle.width) / 2;
        const paddleY = canvas.height - 30;

        // Míček
        const ball = {
            radius: 8,
            x: canvas.width / 2,
            y: paddleY - 10,
            dx: 4,
            dy: -4,
            speed: 5
        };

        // Cihly
        const brickRowCount = 5;
        const brickColumnCount = 10;
        const brickWidth = 70;
        const brickHeight = 20;
        const brickPadding = 5;
        const brickOffsetTop = 50;
        const brickOffsetLeft = (canvas.width - (brickColumnCount * (brickWidth + brickPadding) - brickPadding)) / 2;

        let bricks = [];

        // Barvy cihel podle řádku
        const brickColors = [
            '#ff4444', // Červená
            '#ff8844', // Oranžová
            '#ffcc00', // Žlutá
            '#44ff44', // Zelená
            '#4488ff'  // Modrá
        ];

        // Inicializace cihel
        function initBricks() {
            bricks = [];
            for (let c = 0; c < brickColumnCount; c++) {
                bricks[c] = [];
                for (let r = 0; r < brickRowCount; r++) {
                    // Více životů pro vyšší levely
                    const hits = level > 2 ? (r < 2 ? 2 : 1) : 1;
                    bricks[c][r] = {
                        x: brickOffsetLeft + c * (brickWidth + brickPadding),
                        y: brickOffsetTop + r * (brickHeight + brickPadding),
                        status: hits,
                        maxHits: hits,
                        color: brickColors[r % brickColors.length]
                    };
                }
            }
        }

        // Reset míčku
        function resetBall() {
            ball.x = paddle.x + paddle.width / 2;
            ball.y = paddleY - ball.radius - 5;
            const angle = (Math.random() * 60 + 60) * Math.PI / 180; // 60-120 stupňů
            const speed = ball.speed + (level - 1) * 0.5;
            ball.dx = Math.cos(angle) * speed * (Math.random() > 0.5 ? 1 : -1);
            ball.dy = -Math.abs(Math.sin(angle) * speed);
        }

        // Kontrola kolize
        function collisionDetection() {
            for (let c = 0; c < brickColumnCount; c++) {
                for (let r = 0; r < brickRowCount; r++) {
                    const brick = bricks[c][r];
                    if (brick.status > 0) {
                        if (ball.x > brick.x &&
                            ball.x < brick.x + brickWidth &&
                            ball.y > brick.y &&
                            ball.y < brick.y + brickHeight) {

                            ball.dy = -ball.dy;
                            brick.status--;

                            if (brick.status === 0) {
                                score += 10 * level;
                            } else {
                                score += 5;
                            }

                            updateUI();

                            // Kontrola výhry
                            if (checkWin()) {
                                nextLevel();
                            }

                            return;
                        }
                    }
                }
            }
        }

        // Kontrola výhry
        function checkWin() {
            for (let c = 0; c < brickColumnCount; c++) {
                for (let r = 0; r < brickRowCount; r++) {
                    if (bricks[c][r].status > 0) {
                        return false;
                    }
                }
            }
            return true;
        }

        // Další level
        function nextLevel() {
            level++;
            ball.speed += 0.5;
            paddle.width = Math.max(60, paddle.width - 5);
            initBricks();
            resetBall();
            updateUI();

            // Zobrazit level info
            showLevelInfo();
        }

        // Zobrazit info o levelu
        function showLevelInfo() {
            paused = true;
            setTimeout(() => {
                paused = false;
            }, 1500);
        }

        // Kreslení pálky
        function drawPaddle() {
            ctx.beginPath();
            ctx.roundRect(paddle.x, paddleY, paddle.width, paddle.height, 5);
            ctx.fillStyle = '#fff';
            ctx.fill();
            ctx.closePath();
        }

        // Kreslení míčku
        function drawBall() {
            ctx.beginPath();
            ctx.arc(ball.x, ball.y, ball.radius, 0, Math.PI * 2);
            ctx.fillStyle = '#0099ff';
            ctx.fill();

            // Záře
            ctx.shadowColor = '#0099ff';
            ctx.shadowBlur = 15;
            ctx.fill();
            ctx.shadowBlur = 0;
            ctx.closePath();
        }

        // Kreslení cihel
        function drawBricks() {
            for (let c = 0; c < brickColumnCount; c++) {
                for (let r = 0; r < brickRowCount; r++) {
                    const brick = bricks[c][r];
                    if (brick.status > 0) {
                        ctx.beginPath();
                        ctx.roundRect(brick.x, brick.y, brickWidth, brickHeight, 3);

                        // Tmavší barva pro poškozené cihly
                        if (brick.status < brick.maxHits) {
                            ctx.fillStyle = adjustColor(brick.color, -30);
                        } else {
                            ctx.fillStyle = brick.color;
                        }

                        ctx.fill();

                        // Okraj
                        ctx.strokeStyle = 'rgba(255,255,255,0.3)';
                        ctx.stroke();
                        ctx.closePath();
                    }
                }
            }
        }

        // Úprava barvy
        function adjustColor(color, amount) {
            const num = parseInt(color.slice(1), 16);
            const r = Math.max(0, Math.min(255, (num >> 16) + amount));
            const g = Math.max(0, Math.min(255, ((num >> 8) & 0x00FF) + amount));
            const b = Math.max(0, Math.min(255, (num & 0x0000FF) + amount));
            return `rgb(${r},${g},${b})`;
        }

        // Hlavní herní smyčka
        function draw() {
            if (!gameRunning) return;

            ctx.clearRect(0, 0, canvas.width, canvas.height);

            drawBricks();
            drawBall();
            drawPaddle();

            if (paused) {
                // Zobrazit level
                ctx.font = 'bold 48px Poppins';
                ctx.fillStyle = '#0099ff';
                ctx.textAlign = 'center';
                ctx.fillText(`Level ${level}`, canvas.width / 2, canvas.height / 2);
                requestAnimationFrame(draw);
                return;
            }

            collisionDetection();

            // Pohyb míčku
            ball.x += ball.dx;
            ball.y += ball.dy;

            // Odrazy od stěn
            if (ball.x + ball.radius > canvas.width || ball.x - ball.radius < 0) {
                ball.dx = -ball.dx;
            }

            // Odraz od stropu
            if (ball.y - ball.radius < 0) {
                ball.dy = -ball.dy;
            }

            // Odraz od pálky
            if (ball.y + ball.radius > paddleY &&
                ball.y + ball.radius < paddleY + paddle.height &&
                ball.x > paddle.x &&
                ball.x < paddle.x + paddle.width) {

                // Změna úhlu podle místa dopadu
                const hitPoint = (ball.x - paddle.x) / paddle.width;
                const angle = (hitPoint - 0.5) * Math.PI * 0.7;
                const speed = Math.sqrt(ball.dx * ball.dx + ball.dy * ball.dy);

                ball.dx = Math.sin(angle) * speed;
                ball.dy = -Math.abs(Math.cos(angle) * speed);

                ball.y = paddleY - ball.radius - 1;
            }

            // Ztráta míčku
            if (ball.y - ball.radius > canvas.height) {
                lives--;
                updateUI();

                if (lives <= 0) {
                    gameOver(false);
                } else {
                    resetBall();
                }
            }

            requestAnimationFrame(draw);
        }

        // Aktualizace UI
        function updateUI() {
            document.getElementById('skore').textContent = score;
            document.getElementById('level').textContent = level;
            document.getElementById('zivoty').textContent = lives;
        }

        // Konec hry
        function gameOver(win) {
            gameRunning = false;

            const title = document.getElementById('gameOverTitle');
            const text = document.getElementById('gameOverText');

            if (win) {
                title.innerHTML = '<span class="win">Gratuluji!</span>';
                text.textContent = `Dokončil jsi všechny levely! Skóre: ${score}`;
            } else {
                title.innerHTML = '<span class="lose">Game Over</span>';
                text.textContent = `Skóre: ${score} | Level: ${level}`;
            }

            document.getElementById('gameOverOverlay').classList.add('show');
        }

        // Start hry
        function startGame() {
            score = 0;
            lives = 3;
            level = 1;
            ball.speed = 5;
            paddle.width = 100;
            paddle.x = (canvas.width - paddle.width) / 2;

            initBricks();
            resetBall();
            updateUI();

            gameRunning = true;
            paused = false;

            document.getElementById('startOverlay').classList.remove('show');
            document.getElementById('gameOverOverlay').classList.remove('show');

            draw();
        }

        // Ovládání myší
        canvas.addEventListener('mousemove', (e) => {
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width / rect.width;
            const relativeX = (e.clientX - rect.left) * scaleX;

            paddle.x = relativeX - paddle.width / 2;

            // Omezení na canvas
            if (paddle.x < 0) paddle.x = 0;
            if (paddle.x + paddle.width > canvas.width) paddle.x = canvas.width - paddle.width;
        });

        // Ovládání touch
        canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width / rect.width;
            const touch = e.touches[0];
            const relativeX = (touch.clientX - rect.left) * scaleX;

            paddle.x = relativeX - paddle.width / 2;

            if (paddle.x < 0) paddle.x = 0;
            if (paddle.x + paddle.width > canvas.width) paddle.x = canvas.width - paddle.width;
        }, { passive: false });

        // Ovládání klávesnicí
        const keys = {};

        document.addEventListener('keydown', (e) => {
            keys[e.key] = true;

            if (e.key === ' ' && gameRunning) {
                paused = !paused;
                if (!paused) draw();
            }
        });

        document.addEventListener('keyup', (e) => {
            keys[e.key] = false;
        });

        // Pohyb klávesnicí
        function updatePaddleKeyboard() {
            if (!gameRunning || paused) return;

            if (keys['ArrowLeft'] || keys['a'] || keys['A']) {
                paddle.x -= paddle.speed;
                if (paddle.x < 0) paddle.x = 0;
            }

            if (keys['ArrowRight'] || keys['d'] || keys['D']) {
                paddle.x += paddle.speed;
                if (paddle.x + paddle.width > canvas.width) paddle.x = canvas.width - paddle.width;
            }

            requestAnimationFrame(updatePaddleKeyboard);
        }
        updatePaddleKeyboard();

        // Event listenery
        document.getElementById('startBtn').addEventListener('click', startGame);
        document.getElementById('restartBtn').addEventListener('click', startGame);
        document.getElementById('menuBtn').addEventListener('click', () => {
            document.getElementById('gameOverOverlay').classList.remove('show');
            document.getElementById('startOverlay').classList.add('show');
        });

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
