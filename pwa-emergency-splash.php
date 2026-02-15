<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>PWA Emergency Debug</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            min-height: 100vh;
            padding: 20px;
            overflow-y: auto;
        }
        .debug {
            background: rgba(0,0,0,0.7);
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #39ff14;
        }
        h1 { margin-bottom: 20px; font-size: 1.5rem; }
        h2 { margin: 10px 0; font-size: 1.2rem; color: #39ff14; }
        pre {
            background: rgba(255,255,255,0.1);
            padding: 10px;
            border-radius: 3px;
            font-size: 0.8rem;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #39ff14;
            color: #000;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>🚨 PWA EMERGENCY DEBUG</h1>

    <div class="debug">
        <h2>1. PHP Works</h2>
        <pre>✅ PHP funguje! Tento soubor se načetl.</pre>
        <pre>PHP Version: <?php echo PHP_VERSION; ?></pre>
        <pre>Current Time: <?php echo date('Y-m-d H:i:s'); ?></pre>
    </div>

    <div class="debug">
        <h2>2. Init.php Test</h2>
        <pre><?php
        try {
            ob_start();
            require_once __DIR__ . '/init.php';
            $initOutput = ob_get_clean();
            echo "✅ init.php načten úspěšně!\n";
            if (!empty($initOutput)) {
                echo "⚠️ init.php měl output (může způsobit problémy):\n";
                echo htmlspecialchars($initOutput);
            }
        } catch (Exception $e) {
            echo "❌ init.php SELHAL!\n";
            echo "Error: " . htmlspecialchars($e->getMessage()) . "\n";
            echo "File: " . htmlspecialchars($e->getFile()) . ":" . $e->getLine();
        }
        ?></pre>
    </div>

    <div class="debug">
        <h2>3. Session Info</h2>
        <pre><?php
        echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? '✅ Active' : '❌ Not Active') . "\n";
        echo "Session ID: " . (session_id() ?: 'None') . "\n";
        echo "Logged In: " . (isset($_SESSION['user_id']) ? '✅ Yes (User: ' . $_SESSION['user_id'] . ')' : '❌ No');
        ?></pre>
    </div>

    <div class="debug">
        <h2>4. PWA Detection</h2>
        <pre><?php
        $isPWA = false;
        if (isset($_SERVER['HTTP_X_PWA_MODE'])) $isPWA = true;
        if (strpos($_SERVER['REQUEST_URI'] ?? '', 'source=pwa') !== false) $isPWA = true;
        if (strpos($_SERVER['HTTP_REFERER'] ?? '', 'source=pwa') !== false) $isPWA = true;

        echo "Is PWA: " . ($isPWA ? '✅ YES' : '❌ NO') . "\n";
        echo "Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
        echo "Referer: " . ($_SERVER['HTTP_REFERER'] ?? 'N/A') . "\n";
        echo "User Agent: " . substr($_SERVER['HTTP_USER_AGENT'] ?? 'N/A', 0, 80) . "...";
        ?></pre>
    </div>

    <div class="debug">
        <h2>5. Critical Files</h2>
        <pre><?php
        $files = [
            'manifest.json',
            'sw.js',
            'sw.php',
            'login.php'
        ];
        foreach ($files as $file) {
            $exists = file_exists(__DIR__ . '/' . $file);
            echo ($exists ? '✅' : '❌') . " $file " . ($exists ? '(' . filesize(__DIR__ . '/' . $file) . ' bytes)' : '(MISSING)') . "\n";
        }
        ?></pre>
    </div>

    <div class="debug">
        <h2>6. Redirect Test</h2>
        <pre><?php
        echo "URL této stránky: " . $_SERVER['REQUEST_URI'] . "\n";
        echo "Byla přesměrována? " . (isset($_SERVER['REDIRECT_STATUS']) ? '⚠️ YES' : '✅ NO');
        ?></pre>
    </div>

    <div class="debug">
        <h2>JavaScript Test</h2>
        <div id="jsTest">❌ JavaScript NEBĚŽÍ!</div>
    </div>

    <a href="login.php?pwa=1" class="btn">➡️ Pokračovat na Login</a>
    <a href="pwa-diagnostika.html" class="btn">🔍 Plná diagnostika</a>

    <script>
        document.getElementById('jsTest').innerHTML = '✅ JavaScript funguje!';

        // Detekce PWA módu z JS
        const isPWA = window.matchMedia('(display-mode: standalone)').matches ||
                      window.navigator.standalone === true;

        const div = document.createElement('div');
        div.className = 'debug';
        div.innerHTML = `
            <h2>7. JavaScript PWA Detection</h2>
            <pre>PWA Mode: ${isPWA ? '✅ YES (standalone)' : '❌ NO (browser)'}\nDisplay Mode: ${window.matchMedia('(display-mode: standalone)').matches ? 'standalone' : 'browser'}\nNavigator Standalone: ${window.navigator.standalone || 'undefined'}</pre>
        `;
        document.body.insertBefore(div, document.querySelector('.btn'));

        console.log('Emergency splash loaded');
    </script>
</body>
</html>
