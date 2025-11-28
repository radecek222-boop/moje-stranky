<?php
/**
 * Test volání skutečného API endpointu
 */
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Direct API Call Test</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 5px; max-width: 800px; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .success { color: green; }
        .error { color: red; }
        button { padding: 10px 20px; background: #333; color: white; border: none; cursor: pointer; margin: 5px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Direct API Call Test</h1>
    
    <button onclick="testAPI()">Test track_heatmap.php API</button>
    <button onclick="clearOpcache()">Clear PHP OPcache</button>
    
    <h2>Response:</h2>
    <pre id="response">Klikni na tlačítko...</pre>
</div>

<script>
const csrfToken = '<?php echo $csrfToken; ?>';

async function testAPI() {
    const responseEl = document.getElementById('response');
    responseEl.textContent = 'Sending request to /api/track_heatmap.php...\n\n';
    
    const data = {
        page_url: 'https://www.wgs-service.cz/test-api-call.php',
        device_type: 'desktop',
        clicks: [
            { x_percent: 50, y_percent: 30, viewport_width: 1920, viewport_height: 1080 }
        ],
        scroll_depths: [0, 10, 20],
        csrf_token: csrfToken
    };
    
    try {
        const response = await fetch('/api/track_heatmap.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(data)
        });
        
        responseEl.textContent += 'HTTP Status: ' + response.status + ' ' + response.statusText + '\n\n';
        
        const text = await response.text();
        responseEl.textContent += 'Response body length: ' + text.length + ' bytes\n\n';
        
        if (text.length === 0) {
            responseEl.textContent += 'EMPTY RESPONSE (500 error bez output)\n';
            responseEl.textContent += 'Pravděpodobně PHP fatal error nebo opcache issue.\n';
            responseEl.className = 'error';
        } else {
            try {
                const json = JSON.parse(text);
                responseEl.textContent += '✓ Valid JSON response:\n\n';
                responseEl.textContent += JSON.stringify(json, null, 2);
                responseEl.className = json.status === 'success' ? 'success' : 'error';
            } catch (e) {
                responseEl.textContent += 'NOT JSON:\n\n' + text;
                responseEl.className = 'error';
            }
        }
    } catch (error) {
        responseEl.textContent += 'Network error: ' + error.message;
        responseEl.className = 'error';
    }
}

async function clearOpcache() {
    const responseEl = document.getElementById('response');
    responseEl.textContent = 'Clearing OPcache...\n\n';
    
    try {
        const response = await fetch('/clear_opcache.php');
        const text = await response.text();
        responseEl.textContent += text;
    } catch (error) {
        responseEl.textContent += 'Error: ' + error.message;
    }
}
</script>
</body>
</html>
