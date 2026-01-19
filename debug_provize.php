<?php
/**
 * Debug stránka pro testování Tech Provize API
 * Otevři tuto stránku na mobilu: https://www.wgs-service.cz/debug_provize.php
 */

require_once __DIR__ . '/init.php';

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    die('<h1>Nejsi přihlášen</h1><p>Přihlas se nejdřív jako technik.</p>');
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'N/A';

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Debug Provize</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: #000;
            color: #0f0;
            line-height: 1.6;
        }
        h1 { color: #39ff14; }
        .section {
            background: #111;
            padding: 15px;
            margin: 15px 0;
            border: 1px solid #333;
            border-radius: 5px;
        }
        .label {
            color: #999;
            font-weight: bold;
        }
        .value {
            color: #39ff14;
            margin-left: 10px;
        }
        .error {
            color: #ff4444;
        }
        .success {
            color: #39ff14;
        }
        pre {
            background: #222;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
        button {
            background: #39ff14;
            color: #000;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
            cursor: pointer;
        }
    </style>
</head>
<body>
<h1>DEBUG: Tech Provize API</h1>

<div class='section'>
    <h2>1. Session Info</h2>
    <div><span class='label'>User ID:</span><span class='value'>{$userId}</span></div>
    <div><span class='label'>Role:</span><span class='value'>{$userRole}</span></div>
    <div><span class='label'>Je technik?:</span><span class='value'>" . ($userRole === 'technik' ? '✅ ANO' : '❌ NE') . "</span></div>
</div>

<div class='section'>
    <h2>2. API Test</h2>
    <button onclick='testAPI()'>Zavolat API /api/tech_provize_api.php</button>
    <div id='apiResult' style='margin-top: 15px;'></div>
</div>

<div class='section'>
    <h2>3. Alpine.js Test</h2>
    <div x-data=\"{
        mesic: '...',
        reklamace: '...',
        poz: '...',
        status: 'Čekám...',
        async nactiProvize() {
            this.status = 'Načítám...';
            try {
                const response = await fetch('/api/tech_provize_api.php');
                const result = await response.json();

                if (result.status === 'success') {
                    this.mesic = result.mesic || '---';
                    this.reklamace = result.provize_reklamace || '0.00';
                    this.poz = result.provize_poz || '0.00';
                    this.status = '✅ Načteno';
                } else {
                    this.status = '❌ Chyba: ' + result.message;
                }
            } catch (e) {
                this.status = '❌ Exception: ' + e.message;
            }
        }
    }\" x-init=\"nactiProvize()\">
        <div><span class='label'>Status:</span><span class='value' x-text='status'></span></div>
        <div><span class='label'>Měsíc:</span><span class='value' x-text='mesic'></span></div>
        <div><span class='label'>REKLAMACE provize:</span><span class='value' x-text='reklamace'></span> €</div>
        <div><span class='label'>POZ provize:</span><span class='value' x-text='poz'></span> €</div>
        <button @click='nactiProvize()' style='margin-top: 10px;'>Načíst znovu</button>
    </div>
</div>

<script src='https://unpkg.com/@alpinejs/csp@3.14.3/dist/cdn.min.js' defer></script>

<script>
async function testAPI() {
    const resultDiv = document.getElementById('apiResult');
    resultDiv.innerHTML = '<p class=\"value\">⏳ Načítám...</p>';

    try {
        const response = await fetch('/api/tech_provize_api.php');

        const statusClass = response.ok ? 'success' : 'error';

        const result = await response.json();

        let html = '<h3>Response:</h3>';
        html += '<div><span class=\"label\">HTTP Status:</span><span class=\"' + statusClass + '\">' + response.status + '</span></div>';
        html += '<div><span class=\"label\">Status:</span><span class=\"' + (result.status === 'success' ? 'success' : 'error') + '\">' + result.status + '</span></div>';

        if (result.status === 'success') {
            html += '<div><span class=\"label\">Měsíc:</span><span class=\"value\">' + result.mesic + '</span></div>';
            html += '<div><span class=\"label\">Rok:</span><span class=\"value\">' + result.rok + '</span></div>';
            html += '<div><span class=\"label\">Počet zakázek:</span><span class=\"value\">' + result.pocet_zakazek + '</span></div>';
            html += '<div><span class=\"label\">Celková částka:</span><span class=\"value\">' + result.celkem_castka + ' €</span></div>';
            html += '<div><span class=\"label\">Provize REKLAMACE:</span><span class=\"value\">' + result.provize_reklamace + ' €</span></div>';
            html += '<div><span class=\"label\">Provize POZ:</span><span class=\"value\">' + result.provize_poz + ' €</span></div>';
            html += '<div><span class=\"label\">Provize celkem:</span><span class=\"value\">' + result.provize_celkem + ' €</span></div>';
        } else {
            html += '<div><span class=\"label\">Message:</span><span class=\"error\">' + result.message + '</span></div>';
        }

        html += '<h3>Raw JSON:</h3><pre>' + JSON.stringify(result, null, 2) + '</pre>';

        resultDiv.innerHTML = html;
    } catch (e) {
        resultDiv.innerHTML = '<h3 class=\"error\">Error:</h3><p class=\"error\">' + e.message + '</p>';
    }
}
</script>

</body>
</html>";
?>
