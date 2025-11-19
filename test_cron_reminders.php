<?php
/**
 * Testovací rozhraní pro CRON job odesílání připomínek
 *
 * Umožňuje manuálně spustit CRON job a zobrazit výsledky.
 * URL: https://www.wgs-service.cz/test_cron_reminders.php
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Pouze admin může spustit
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může testovat CRON job.");
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test CRON - Automatické připomínky</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2D5016 0%, #1a300d 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1em;
        }

        .content {
            padding: 40px;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 5px;
        }

        .info-box h3 {
            color: #1976D2;
            margin-bottom: 10px;
        }

        .info-box ul {
            margin-left: 20px;
            line-height: 1.8;
        }

        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #2D5016 0%, #1a300d 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            border: none;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin: 10px 5px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(45, 80, 22, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #757575 0%, #424242 100%);
        }

        .output {
            background: #1e1e1e;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            max-height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading.active {
            display: block;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #2D5016;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            opacity: 0.9;
            font-size: 0.9em;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #2D5016;
            text-decoration: none;
            font-weight: bold;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📅 Test CRON: Automatické připomínky</h1>
            <p>Testování automatického odesílání připomínek zákazníkům</p>
        </div>

        <div class="content">
            <div class="info-box">
                <h3>ℹ️ Co tento test dělá?</h3>
                <ul>
                    <li>Najde všechny návštěvy naplánované na <strong>ZÍTRA</strong></li>
                    <li>Pro každou návštěvu připraví připomínací email</li>
                    <li>Přidá emaily do fronty (skutečně se odešlou podle SMTP nastavení)</li>
                    <li>Zobrazí detailní log operace</li>
                </ul>
            </div>

            <div class="info-box" style="background: #fff3cd; border-left-color: #ffc107;">
                <h3>⚠️ POZOR</h3>
                <p>Tento test <strong>SKUTEČNĚ PŘIDÁ EMAILY DO FRONTY</strong>. Pokud máte nakonfigurovaný SMTP server, emaily se skutečně odešlou zákazníkům!</p>
            </div>

            <div id="stats" class="stats" style="display: none;">
                <div class="stat-card">
                    <div class="stat-value" id="stat-found">-</div>
                    <div class="stat-label">Nalezeno návštěv</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="stat-sent">-</div>
                    <div class="stat-label">Přidáno do fronty</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="stat-errors">-</div>
                    <div class="stat-label">Chyby</div>
                </div>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <button class="btn" onclick="spustitTest()">🚀 Spustit Test</button>
                <button class="btn btn-secondary" onclick="zobrazitLog()">📋 Zobrazit Log</button>
                <a href="admin.php" class="btn btn-secondary" style="text-decoration: none;">← Zpět do Admin Panelu</a>
            </div>

            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>Zpracovávám... Prosím čekejte...</p>
            </div>

            <div id="output" class="output" style="display: none;"></div>

            <a href="NAVOD_WEBCRON.md" class="back-link" target="_blank">📖 Návod na nastavení WEBCRON (doporučeno)</a>
            <a href="CRON_NAVOD.md" class="back-link" target="_blank">📖 Návod na nastavení CLI CRON</a>
        </div>
    </div>

    <script>
        async function spustitTest() {
            const output = document.getElementById('output');
            const loading = document.getElementById('loading');
            const stats = document.getElementById('stats');

            // Zobrazit loading
            loading.classList.add('active');
            output.style.display = 'none';
            stats.style.display = 'none';

            try {
                // Spustit CRON skript přes nový webcron endpoint
                const response = await fetch('/cron/send-reminders.php?key=wgs2025reminder', {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const text = await response.text();
                const data = JSON.parse(text);

                // Skrýt loading, zobrazit output
                loading.classList.remove('active');
                output.style.display = 'block';
                output.textContent = JSON.stringify(data, null, 2);

                // Zobrazit statistiky z JSON odpovědi
                document.getElementById('stat-found').textContent = data.found || 0;
                document.getElementById('stat-sent').textContent = data.sent || 0;
                document.getElementById('stat-errors').textContent = data.errors || 0;

                stats.style.display = 'grid';

            } catch (error) {
                loading.classList.remove('active');
                output.style.display = 'block';
                output.innerHTML = `<span style="color: #ff5555;">CHYBA: ${error.message}</span>`;
            }
        }

        async function zobrazitLog() {
            const output = document.getElementById('output');
            const loading = document.getElementById('loading');

            loading.classList.add('active');
            output.style.display = 'none';

            try {
                const response = await fetch('logs/cron_reminders.log');
                const text = await response.text();

                loading.classList.remove('active');
                output.style.display = 'block';

                if (text.trim() === '') {
                    output.textContent = '[LOG JE PRÁZDNÝ - CRON ještě nebyl spuštěn]';
                } else {
                    // Zobrazit posledních 50 řádků
                    const lines = text.split('\n').slice(-50);
                    output.textContent = lines.join('\n');
                }

            } catch (error) {
                loading.classList.remove('active');
                output.style.display = 'block';
                output.innerHTML = `<span style="color: #ff5555;">CHYBA: Nelze načíst log - ${error.message}</span>`;
            }
        }
    </script>
</body>
</html>
