<?php
/**
 * Test skutečného POST volání track_heatmap.php API
 * Simuluje přesně to co dělá JavaScript heatmap-tracker.js
 */

session_start();

// Vygenerovat platný CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Test Track Heatmap POST</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 5px; max-width: 1200px; margin: 0 auto; }
        pre { background: #f0f0f0; padding: 15px; border-radius: 3px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        h2 { margin-top: 20px; border-bottom: 2px solid #333; padding-bottom: 5px; }
        button { padding: 10px 20px; background: #333; color: white; border: none; cursor: pointer; font-size: 14px; }
    </style>
</head>
<body>
<div class='container'>
    <h1>Test Track Heatmap API - POST Request</h1>
    <p>Tento test zavolá skutečné API pomocí POST requestu (stejně jako JavaScript)</p>

    <button onclick='testAPI()'>Zavolat API</button>
    <button onclick='testDebugAPI()'>Zavolat DEBUG API</button>
    <button onclick='testWithoutCSRF()'>Test BEZ CSRF tokenu</button>

    <h2>CSRF Token v session:</h2>
    <pre>" . htmlspecialchars($csrfToken) . "</pre>

    <div id='results'></div>
</div>

<script>
const csrfToken = '" . $csrfToken . "';

async function testAPI() {
    const resultsDiv = document.getElementById('results');
    resultsDiv.innerHTML = '<h2>Odesílám request...</h2>';

    const testData = {
        page_url: 'https://www.wgs-service.cz/test.php',
        device_type: 'desktop',
        clicks: [
            { x_percent: 50, y_percent: 30, viewport_width: 1920, viewport_height: 1080 }
        ],
        scroll_depths: [0, 10, 20],
        csrf_token: csrfToken
    };

    resultsDiv.innerHTML += '<h2>Request Data:</h2><pre>' + JSON.stringify(testData, null, 2) + '</pre>';

    try {
        const response = await fetch('/api/track_heatmap.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(testData)
        });

        resultsDiv.innerHTML += '<h2>HTTP Status:</h2>';
        resultsDiv.innerHTML += '<pre class=\"' + (response.ok ? 'success' : 'error') + '\">' +
                                response.status + ' ' + response.statusText + '</pre>';

        // Headers
        resultsDiv.innerHTML += '<h2>Response Headers:</h2><pre>';
        response.headers.forEach((value, key) => {
            resultsDiv.innerHTML += key + ': ' + value + '\\n';
        });
        resultsDiv.innerHTML += '</pre>';

        // Body - zkusit jako text
        const text = await response.text();

        resultsDiv.innerHTML += '<h2>Response Body (length: ' + text.length + ' bytes):</h2>';

        if (text.length === 0) {
            resultsDiv.innerHTML += '<pre class=\"error\">⚠ PRÁZDNÝ RESPONSE BODY! (toto je problém)</pre>';
        } else {
            resultsDiv.innerHTML += '<pre>' + htmlEscape(text.substring(0, 500)) + '</pre>';

            // Zkusit parsovat jako JSON
            try {
                const json = JSON.parse(text);
                resultsDiv.innerHTML += '<h2>Parsed JSON:</h2>';
                resultsDiv.innerHTML += '<pre class=\"success\">' + JSON.stringify(json, null, 2) + '</pre>';
            } catch (e) {
                resultsDiv.innerHTML += '<h2>JSON Parse Error:</h2>';
                resultsDiv.innerHTML += '<pre class=\"error\">' + e.message + '</pre>';
            }
        }

    } catch (error) {
        resultsDiv.innerHTML += '<h2>Network Error:</h2>';
        resultsDiv.innerHTML += '<pre class=\"error\">' + error.toString() + '</pre>';
    }
}

async function testDebugAPI() {
    const resultsDiv = document.getElementById('results');
    resultsDiv.innerHTML = '<h2>Volám DEBUG API...</h2>';

    const testData = {
        page_url: 'https://www.wgs-service.cz/test.php',
        device_type: 'desktop',
        clicks: [
            { x_percent: 50, y_percent: 30, viewport_width: 1920, viewport_height: 1080 }
        ],
        scroll_depths: [0, 10, 20],
        csrf_token: csrfToken
    };

    resultsDiv.innerHTML += '<h2>Request Data:</h2><pre>' + JSON.stringify(testData, null, 2) + '</pre>';

    try {
        const response = await fetch('/api/track_heatmap_debug.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(testData)
        });

        resultsDiv.innerHTML += '<h2>HTTP Status:</h2>';
        resultsDiv.innerHTML += '<pre class=\"' + (response.ok ? 'success' : 'error') + '\">' +
                                response.status + ' ' + response.statusText + '</pre>';

        const text = await response.text();
        resultsDiv.innerHTML += '<h2>Debug Output:</h2>';
        resultsDiv.innerHTML += '<pre>' + htmlEscape(text) + '</pre>';

    } catch (error) {
        resultsDiv.innerHTML += '<pre class=\"error\">' + error.toString() + '</pre>';
    }
}

async function testWithoutCSRF() {
    const resultsDiv = document.getElementById('results');
    resultsDiv.innerHTML = '<h2>Test BEZ CSRF tokenu (mělo by vrátit 403)...</h2>';

    const testData = {
        page_url: 'https://www.wgs-service.cz/test.php',
        device_type: 'desktop',
        clicks: [],
        scroll_depths: [0],
        csrf_token: 'invalid_token_123'
    };

    try {
        const response = await fetch('/api/track_heatmap.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(testData)
        });

        resultsDiv.innerHTML += '<h2>HTTP Status:</h2>';
        resultsDiv.innerHTML += '<pre class=\"' + (response.status === 403 ? 'success' : 'error') + '\">' +
                                response.status + ' ' + response.statusText + '</pre>';

        const text = await response.text();
        resultsDiv.innerHTML += '<h2>Response:</h2><pre>' + htmlEscape(text) + '</pre>';

    } catch (error) {
        resultsDiv.innerHTML += '<pre class=\"error\">' + error.toString() + '</pre>';
    }
}

function htmlEscape(str) {
    return str.replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/\"/g, '&quot;')
              .replace(/'/g, '&#39;');
}
</script>
</body>
</html>";
?>
