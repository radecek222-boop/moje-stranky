<?php
/**
 * Test skript pro track_heatmap.php API
 * Zobrazí přesný response a případné chyby
 */

// Získat CSRF token z session
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Test Heatmap API</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 3px; overflow-x: auto; }
        button { padding: 10px 20px; background: #333; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
<div class='container'>
    <h1>Test Heatmap API</h1>

    <h2>CSRF Token:</h2>
    <pre>$csrfToken</pre>

    <button onclick='testAPI()'>Zavolat track_heatmap.php</button>

    <h2>Request:</h2>
    <pre id='request'>-</pre>

    <h2>Response Status:</h2>
    <pre id='status'>-</pre>

    <h2>Response Headers:</h2>
    <pre id='headers'>-</pre>

    <h2>Response Body:</h2>
    <pre id='response'>-</pre>

    <h2>Console Log:</h2>
    <pre id='console'>-</pre>
</div>

<script>
async function testAPI() {
    const consoleEl = document.getElementById('console');
    const requestEl = document.getElementById('request');
    const statusEl = document.getElementById('status');
    const headersEl = document.getElementById('headers');
    const responseEl = document.getElementById('response');

    consoleEl.textContent = 'Odesílám request...\\n';

    const data = {
        page_url: 'https://www.wgs-service.cz/cenik.php',
        device_type: 'desktop',
        clicks: [
            { x_percent: 50, y_percent: 30, viewport_width: 1920, viewport_height: 1080 }
        ],
        scroll_depths: [0, 10, 20],
        csrf_token: '$csrfToken'
    };

    requestEl.textContent = JSON.stringify(data, null, 2);

    try {
        const response = await fetch('/api/track_heatmap.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '$csrfToken'
            },
            body: JSON.stringify(data)
        });

        consoleEl.textContent += 'Response přijat\\n';

        statusEl.textContent = response.status + ' ' + response.statusText;
        statusEl.className = response.ok ? 'success' : 'error';

        // Headers
        let headersText = '';
        response.headers.forEach((value, key) => {
            headersText += key + ': ' + value + '\\n';
        });
        headersEl.textContent = headersText;

        // Body - zkusíme získat jako text (možná to není JSON)
        const text = await response.text();
        responseEl.textContent = text;

        consoleEl.textContent += 'Raw response text length: ' + text.length + '\\n';

        // Zkusíme parsovat jako JSON
        try {
            const json = JSON.parse(text);
            responseEl.textContent = JSON.stringify(json, null, 2);
            consoleEl.textContent += 'Parsed as JSON successfully\\n';
        } catch (e) {
            consoleEl.textContent += 'NOT JSON! Error: ' + e.message + '\\n';
            consoleEl.textContent += 'Možná HTML error page?\\n';
        }

    } catch (error) {
        consoleEl.textContent += 'Fetch error: ' + error.message + '\\n';
        responseEl.textContent = error.toString();
        responseEl.className = 'error';
    }
}
</script>
</body>
</html>";
?>
