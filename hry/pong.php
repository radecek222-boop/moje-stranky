<?php
/**
 * Hra Pong
 * Klasicka arkadova hra
 */
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

// Kontrola prihlaseni
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'Hráč';

// Aktualizovat online status
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        INSERT INTO wgs_hry_online (user_id, username, posledni_aktivita, aktualni_hra)
        VALUES (:user_id, :username, NOW(), 'Pong')
        ON DUPLICATE KEY UPDATE
            username = VALUES(username),
            posledni_aktivita = NOW(),
            aktualni_hra = 'Pong'
    ");
    $stmt->execute(['user_id' => $userId, 'username' => $username]);
} catch (PDOException $e) {
    error_log("Pong error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pong | Herni zona</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --pong-bg: #0a0a0a;
            --pong-card: #1a1a1a;
            --pong-border: #333;
            --pong-text: #fff;
            --pong-muted: #888;
            --pong-accent: #0099ff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--pong-bg);
            color: var(--pong-text);
            min-height: 100vh;
            margin: 0;
        }

        .pong-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 1rem;
            text-align: center;
        }

        .pong-header {
            margin-bottom: 1rem;
        }

        .pong-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--pong-accent);
        }

        .pong-header p {
            color: var(--pong-muted);
            font-size: 0.9rem;
        }

        .pong-score {
            display: flex;
            justify-content: center;
            gap: 3rem;
            margin-bottom: 1rem;
            font-size: 2rem;
            font-weight: 700;
        }

        .pong-score-player {
            text-align: center;
        }

        .pong-score-label {
            font-size: 0.8rem;
            color: var(--pong-muted);
            font-weight: 400;
        }

        .pong-canvas-wrapper {
            display: inline-block;
            border: 2px solid var(--pong-border);
            border-radius: 8px;
            overflow: hidden;
            background: #000;
        }

        #pongCanvas {
            display: block;
        }

        .pong-controls {
            margin-top: 1.5rem;
            color: var(--pong-muted);
            font-size: 0.85rem;
        }

        .pong-controls kbd {
            background: var(--pong-card);
            border: 1px solid var(--pong-border);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
        }

        .pong-actions {
            margin-top: 1.5rem;
        }

        .pong-btn {
            background: var(--pong-accent);
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

        .pong-btn:hover {
            background: #33bbff;
        }

        .pong-btn.secondary {
            background: var(--pong-border);
            color: var(--pong-text);
        }

        .pong-btn.secondary:hover {
            background: #444;
        }

        .pong-difficulty {
            margin-top: 1rem;
        }

        .pong-difficulty label {
            color: var(--pong-muted);
            margin-right: 0.5rem;
        }

        .pong-difficulty select {
            background: var(--pong-card);
            color: var(--pong-text);
            border: 1px solid var(--pong-border);
            padding: 0.5rem;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
        }

        .pong-message {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2rem;
            font-weight: 700;
            color: var(--pong-accent);
            text-shadow: 0 0 10px rgba(0, 153, 255, 0.5);
        }

        .pong-game-wrapper {
            position: relative;
            display: inline-block;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/hamburger-menu.php'; ?>

    <main id="main-content" class="pong-container">
        <div class="pong-header">
            <h1>PONG</h1>
            <p>Klasicka arkadova hra - poraz pocitac!</p>
        </div>

        <div class="pong-score">
            <div class="pong-score-player">
                <div class="pong-score-label">TY</div>
                <div id="scorePlayer">0</div>
            </div>
            <div class="pong-score-player">
                <div class="pong-score-label">POCITAC</div>
                <div id="scoreComputer">0</div>
            </div>
        </div>

        <div class="pong-game-wrapper">
            <div class="pong-canvas-wrapper">
                <canvas id="pongCanvas" width="800" height="500"></canvas>
            </div>
            <div class="pong-message" id="gameMessage">Stiskni MEZERNIK pro start</div>
        </div>

        <div class="pong-controls">
            Ovladani: <kbd>W</kbd> / <kbd>S</kbd> nebo <kbd>sipky</kbd> nahoru/dolu | <kbd>MEZERNIK</kbd> start/pauza
        </div>

        <div class="pong-difficulty">
            <label for="difficulty">Obtiznost:</label>
            <select id="difficulty">
                <option value="easy">Lehka</option>
                <option value="medium" selected>Stredni</option>
                <option value="hard">Tezka</option>
            </select>
        </div>

        <div class="pong-actions">
            <button class="pong-btn" id="resetBtn">Reset skore</button>
            <a href="/hry.php" class="pong-btn secondary">Zpet do herni zony</a>
        </div>
    </main>

    <script>
    (function() {
        'use strict';

        const canvas = document.getElementById('pongCanvas');
        const ctx = canvas.getContext('2d');
        const gameMessage = document.getElementById('gameMessage');
        const scorePlayerEl = document.getElementById('scorePlayer');
        const scoreComputerEl = document.getElementById('scoreComputer');
        const difficultyEl = document.getElementById('difficulty');
        const resetBtn = document.getElementById('resetBtn');

        // Herní konstanty
        const PADDLE_WIDTH = 12;
        const PADDLE_HEIGHT = 100;
        const BALL_SIZE = 12;
        const WINNING_SCORE = 11;

        // Obtiznost
        const DIFFICULTY = {
            easy: { speed: 4, aiSpeed: 3, aiReaction: 0.02 },
            medium: { speed: 6, aiSpeed: 5, aiReaction: 0.05 },
            hard: { speed: 8, aiSpeed: 7, aiReaction: 0.1 }
        };

        // Herní stav
        let gameState = 'waiting'; // 'waiting', 'playing', 'paused', 'ended'
        let playerScore = 0;
        let computerScore = 0;

        // Hráčova palka (vlevo)
        const player = {
            x: 20,
            y: canvas.height / 2 - PADDLE_HEIGHT / 2,
            width: PADDLE_WIDTH,
            height: PADDLE_HEIGHT,
            speed: 8,
            dy: 0
        };

        // Počítačova palka (vpravo)
        const computer = {
            x: canvas.width - 20 - PADDLE_WIDTH,
            y: canvas.height / 2 - PADDLE_HEIGHT / 2,
            width: PADDLE_WIDTH,
            height: PADDLE_HEIGHT,
            speed: 5
        };

        // Míček
        const ball = {
            x: canvas.width / 2,
            y: canvas.height / 2,
            size: BALL_SIZE,
            dx: 5,
            dy: 3,
            speed: 5
        };

        // Klávesy
        const keys = {
            w: false,
            s: false,
            ArrowUp: false,
            ArrowDown: false
        };

        // Event listenery pro klávesy
        document.addEventListener('keydown', (e) => {
            if (e.key in keys) {
                keys[e.key] = true;
                e.preventDefault();
            }

            if (e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                toggleGame();
            }
        });

        document.addEventListener('keyup', (e) => {
            if (e.key in keys) {
                keys[e.key] = false;
            }
        });

        // Přepnout stav hry
        function toggleGame() {
            if (gameState === 'waiting' || gameState === 'paused') {
                gameState = 'playing';
                gameMessage.style.display = 'none';
            } else if (gameState === 'playing') {
                gameState = 'paused';
                gameMessage.textContent = 'PAUZA';
                gameMessage.style.display = 'block';
            } else if (gameState === 'ended') {
                resetGame();
            }
        }

        // Reset hry
        function resetGame() {
            playerScore = 0;
            computerScore = 0;
            updateScore();
            resetBall();
            gameState = 'waiting';
            gameMessage.textContent = 'Stiskni MEZERNIK pro start';
            gameMessage.style.display = 'block';
        }

        // Reset míčku
        function resetBall() {
            ball.x = canvas.width / 2;
            ball.y = canvas.height / 2;

            const diff = DIFFICULTY[difficultyEl.value];
            ball.speed = diff.speed;
            ball.dx = (Math.random() > 0.5 ? 1 : -1) * ball.speed;
            ball.dy = (Math.random() - 0.5) * ball.speed;
        }

        // Aktualizace skóre
        function updateScore() {
            scorePlayerEl.textContent = playerScore;
            scoreComputerEl.textContent = computerScore;
        }

        // Pohyb hráče
        function movePlayer() {
            if (keys.w || keys.ArrowUp) {
                player.y -= player.speed;
            }
            if (keys.s || keys.ArrowDown) {
                player.y += player.speed;
            }

            // Omezení na hrací plochu
            player.y = Math.max(0, Math.min(canvas.height - player.height, player.y));
        }

        // Pohyb počítače (AI)
        function moveComputer() {
            const diff = DIFFICULTY[difficultyEl.value];
            computer.speed = diff.aiSpeed;

            // Jednoduchá AI - sleduje míček
            const targetY = ball.y - computer.height / 2;
            const currentY = computer.y;
            const distance = targetY - currentY;

            // Přidáme trochu "reakce" - počítač nereaguje okamžitě
            if (Math.abs(distance) > 10) {
                computer.y += distance * diff.aiReaction + Math.sign(distance) * computer.speed * 0.3;
            }

            // Omezení na hrací plochu
            computer.y = Math.max(0, Math.min(canvas.height - computer.height, computer.y));
        }

        // Pohyb míčku
        function moveBall() {
            ball.x += ball.dx;
            ball.y += ball.dy;

            // Odraz od horní a dolní stěny
            if (ball.y - ball.size / 2 <= 0 || ball.y + ball.size / 2 >= canvas.height) {
                ball.dy = -ball.dy;
                ball.y = ball.y - ball.size / 2 <= 0 ? ball.size / 2 : canvas.height - ball.size / 2;
            }

            // Kolize s hráčovou palkou
            if (ball.x - ball.size / 2 <= player.x + player.width &&
                ball.x + ball.size / 2 >= player.x &&
                ball.y >= player.y &&
                ball.y <= player.y + player.height) {

                ball.dx = Math.abs(ball.dx) * 1.05; // Zrychlení
                ball.x = player.x + player.width + ball.size / 2;

                // Úhel odrazu závisí na místě dopadu
                const hitPos = (ball.y - player.y) / player.height;
                ball.dy = (hitPos - 0.5) * ball.speed * 2;
            }

            // Kolize s počítačovou palkou
            if (ball.x + ball.size / 2 >= computer.x &&
                ball.x - ball.size / 2 <= computer.x + computer.width &&
                ball.y >= computer.y &&
                ball.y <= computer.y + computer.height) {

                ball.dx = -Math.abs(ball.dx) * 1.05;
                ball.x = computer.x - ball.size / 2;

                const hitPos = (ball.y - computer.y) / computer.height;
                ball.dy = (hitPos - 0.5) * ball.speed * 2;
            }

            // Gól pro počítač
            if (ball.x < 0) {
                computerScore++;
                updateScore();
                checkWin();
                if (gameState === 'playing') resetBall();
            }

            // Gól pro hráče
            if (ball.x > canvas.width) {
                playerScore++;
                updateScore();
                checkWin();
                if (gameState === 'playing') resetBall();
            }
        }

        // Kontrola výhry
        function checkWin() {
            if (playerScore >= WINNING_SCORE) {
                gameState = 'ended';
                gameMessage.textContent = 'VYHRAL JSI!';
                gameMessage.style.display = 'block';
            } else if (computerScore >= WINNING_SCORE) {
                gameState = 'ended';
                gameMessage.textContent = 'PROHRAL JSI!';
                gameMessage.style.display = 'block';
            }
        }

        // Vykreslení
        function draw() {
            // Pozadí
            ctx.fillStyle = '#000';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // Středová čára
            ctx.setLineDash([10, 10]);
            ctx.strokeStyle = '#333';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(canvas.width / 2, 0);
            ctx.lineTo(canvas.width / 2, canvas.height);
            ctx.stroke();
            ctx.setLineDash([]);

            // Hráčova palka
            ctx.fillStyle = '#0099ff';
            ctx.fillRect(player.x, player.y, player.width, player.height);

            // Počítačova palka
            ctx.fillStyle = '#fff';
            ctx.fillRect(computer.x, computer.y, computer.width, computer.height);

            // Míček
            ctx.fillStyle = '#fff';
            ctx.beginPath();
            ctx.arc(ball.x, ball.y, ball.size / 2, 0, Math.PI * 2);
            ctx.fill();
        }

        // Herní smyčka
        function gameLoop() {
            if (gameState === 'playing') {
                movePlayer();
                moveComputer();
                moveBall();
            }

            draw();
            requestAnimationFrame(gameLoop);
        }

        // Reset tlačítko
        resetBtn.addEventListener('click', resetGame);

        // Změna obtížnosti
        difficultyEl.addEventListener('change', () => {
            if (gameState === 'waiting') {
                resetBall();
            }
        });

        // Heartbeat
        setInterval(async () => {
            try {
                await fetch('/api/hry_api.php?action=heartbeat');
            } catch (e) {}
        }, 30000);

        // Start
        gameLoop();
    })();
    </script>
</body>
</html>
