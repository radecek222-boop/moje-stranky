<?php
/**
 * Analytics Replay Viewer - Admin UI pro přehrání session replay
 *
 * Admin stránka pro vizualizaci nahraných uživatelských interakcí.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #7 - Session Replay Engine
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

// Získat CSRF token pro API calls
$csrfToken = $_SESSION['csrf_token'] ?? '';

// Získat session_id a page_index z URL parametrů
$sessionId = $_GET['session'] ?? '';
$pageIndex = isset($_GET['page']) ? (int)$_GET['page'] : 0;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Replay Viewer - WGS Analytics</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        h1 {
            color: #333333;
            margin-bottom: 20px;
        }

        .controls {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .control-group {
            display: inline-block;
            margin-right: 20px;
            margin-bottom: 10px;
        }

        .control-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }

        .control-group input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            min-width: 300px;
        }

        .btn {
            padding: 10px 20px;
            background: #333333;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            margin-right: 10px;
        }

        .btn:hover {
            background: #1a300d;
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        #replay-container {
            position: relative;
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            border: 1px solid #ccc;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        #replay-canvas {
            display: block;
            width: 100%;
            height: auto;
            background: #fafafa;
        }

        .player-controls {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .playback-buttons {
            margin-bottom: 15px;
        }

        .timeline-container {
            margin-bottom: 15px;
        }

        #replay-timeline {
            width: 100%;
            height: 8px;
            cursor: pointer;
        }

        .time-display {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }

        .speed-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .speed-control select {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }

        .page-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #333;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ANALYTICS TABS NAVIGACE -->
        <?php require_once __DIR__ . '/includes/analytics_tabs.php'; ?>

        <h1>Session Replay Viewer</h1>

        <div class="controls">
            <div class="control-group">
                <label>Session ID:</label>
                <input type="text" id="session-id-input" value="<?php echo htmlspecialchars($sessionId); ?>" placeholder="Zadejte session_id">
            </div>

            <div class="control-group">
                <label>Page Index:</label>
                <input type="number" id="page-index-input" value="<?php echo $pageIndex; ?>" min="0" placeholder="0">
            </div>

            <div class="control-group" style="display: block; margin-top: 15px;">
                <button id="load-replay-btn" class="btn">Načíst Replay</button>
                <a href="admin.php" class="btn" style="background: #6c757d; text-decoration: none;">← Zpět</a>
            </div>
        </div>

        <div id="page-info" class="page-info" style="display: none;">
            <!-- Info o stránce bude načteno dynamicky -->
        </div>

        <div class="player-controls">
            <div class="playback-buttons">
                <button id="play-pause-btn" class="btn" disabled>▶ Play</button>
            </div>

            <div class="timeline-container">
                <input type="range" id="replay-timeline" min="0" max="100" value="0" step="0.1">
                <div class="time-display">
                    <span id="current-time">0:00</span>
                    <span id="total-time">0:00</span>
                </div>
            </div>

            <div class="speed-control">
                <label>Rychlost:</label>
                <select id="speed-select">
                    <option value="0.5">0.5x</option>
                    <option value="1.0" selected>1.0x</option>
                    <option value="2.0">2.0x</option>
                    <option value="4.0">4.0x</option>
                </select>
            </div>
        </div>

        <div id="replay-container">
            <canvas id="replay-canvas" width="1920" height="1080"></canvas>
            <div id="loading-message" class="loading">Zadejte session_id a klikněte na "Načíst Replay"</div>
        </div>
    </div>

    <script src="/assets/js/replay-player.js"></script>
    <script>
        const sessionIdFromUrl = '<?php echo htmlspecialchars($sessionId); ?>';
        const pageIndexFromUrl = <?php echo $pageIndex; ?>;

        // Event listener pro tlačítko "Načíst Replay"
        document.getElementById('load-replay-btn').addEventListener('click', () => {
            const sessionId = document.getElementById('session-id-input').value.trim();
            const pageIndex = parseInt(document.getElementById('page-index-input').value) || 0;

            if (!sessionId) {
                alert('Zadejte session_id');
                return;
            }

            // Aktualizovat URL (bez reload)
            const newUrl = `${window.location.pathname}?session=${encodeURIComponent(sessionId)}&page=${pageIndex}`;
            window.history.pushState({}, '', newUrl);

            // Načíst replay
            loadReplay(sessionId, pageIndex);
        });

        // Funkce pro načtení replay
        function loadReplay(sessionId, pageIndex) {
            // Skrýt page info
            document.getElementById('page-info').style.display = 'none';

            // Zobrazit loading
            document.getElementById('loading-message').style.display = 'block';
            document.getElementById('loading-message').textContent = 'Načítám replay data...';
            document.getElementById('loading-message').style.color = '#666';

            // Disable play button
            document.getElementById('play-pause-btn').disabled = true;

            // Reset timeline
            document.getElementById('replay-timeline').value = 0;
            document.getElementById('current-time').textContent = '0:00';
            document.getElementById('total-time').textContent = '0:00';

            // Inicializovat ReplayPlayer
            const success = ReplayPlayer.init(sessionId, pageIndex);

            if (success) {
                // Enable play button po načtení
                setTimeout(() => {
                    document.getElementById('play-pause-btn').disabled = false;
                    document.getElementById('page-info').style.display = 'block';
                }, 1000);
            }
        }

        // Auto-load pokud je session_id v URL
        if (sessionIdFromUrl) {
            loadReplay(sessionIdFromUrl, pageIndexFromUrl);
        }
    </script>
</body>
</html>
