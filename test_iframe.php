<?php
/**
 * RYCHLÝ TEST IFRAME - ověří jestli oprava funguje
 */
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>⚡ Rychlý test iframe</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            padding: 2rem;
            background: #000;
            color: #0f0;
        }
        .box {
            background: #1a1a1a;
            border: 2px solid #0f0;
            padding: 1.5rem;
            margin: 1rem 0;
            border-radius: 8px;
        }
        h1 {
            color: #0f0;
            text-align: center;
        }
        iframe {
            border: 3px solid #0ff;
            width: 100%;
            height: 600px;
            margin: 1rem 0;
        }
        .btn {
            background: #0f0;
            color: #000;
            padding: 1rem 2rem;
            border: none;
            border-radius: 4px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            margin: 1rem 0.5rem 1rem 0;
        }
        .btn:hover {
            background: #0ff;
        }
        #status {
            padding: 1rem;
            border: 2px solid #ff0;
            background: #1a1a1a;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <h1>⚡ RYCHLÝ TEST IFRAME</h1>

    <div class="box">
        <p><strong>TENTO TEST OVĚŘÍ JESTLI OPRAVA FUNGUJE</strong></p>
        <p>Pokud uvidíš SEZNAM REKLAMACÍ níže, oprava funguje! ✅</p>
        <p>Pokud uvidíš "Unauthorized", ještě to nefunguje. ❌</p>
    </div>

    <button class="btn" onclick="reloadIframe()">🔄 RELOAD IFRAME</button>
    <button class="btn" onclick="openInNewTab()">🔗 OTEVŘÍT V NOVÉ ZÁLOŽCE</button>

    <div id="status">
        <strong>Status:</strong> <span id="statusText">Načítám iframe...</span>
    </div>

    <iframe id="testIframe" src="/includes/admin_reklamace_management.php?embed=1&filter=all"></iframe>

    <script>
    function reloadIframe() {
        const iframe = document.getElementById('testIframe');
        const timestamp = new Date().getTime();
        iframe.src = `/includes/admin_reklamace_management.php?embed=1&filter=all&_t=${timestamp}`;
        document.getElementById('statusText').innerHTML = '🔄 Reload iframe...';
    }

    function openInNewTab() {
        window.open('/includes/admin_reklamace_management.php?embed=1&filter=all', '_blank');
    }

    // Monitor iframe load
    document.getElementById('testIframe').addEventListener('load', function() {
        try {
            const iframeDoc = this.contentDocument || this.contentWindow.document;
            const body = iframeDoc.body;

            if (body && body.textContent.includes('Unauthorized')) {
                document.getElementById('statusText').innerHTML = '❌ <span style="color: #f00; font-weight: bold;">IFRAME VRACÍ "Unauthorized" - OPRAVA JEŠTĚ NEFUNGUJE</span>';
            } else if (body && body.textContent.trim().length < 50) {
                document.getElementById('statusText').innerHTML = `⚠️ <span style="color: #ff0;">Iframe obsahuje krátký text: "${body.textContent.substring(0, 100)}"</span>`;
            } else {
                document.getElementById('statusText').innerHTML = '✅ <span style="color: #0f0; font-weight: bold;">IFRAME FUNGUJE! Vidíš seznam reklamací? Pokud ano, oprava funguje!</span>';
            }
        } catch (e) {
            document.getElementById('statusText').innerHTML = `⚠️ Nelze přečíst obsah iframe: ${e.message}`;
        }
    });
    </script>

    <div class="box" style="margin-top: 2rem;">
        <h2 style="color: #ff0;">📋 CO DĚLAT DÁL:</h2>
        <ol>
            <li>Pokud vidíš <span style="color: #0f0;">✅ SEZNAM REKLAMACÍ</span> v iframe výše → Jdi na <a href="/admin.php" style="color: #0ff;">/admin.php</a> a zkus "Správa reklamací"</li>
            <li>Pokud vidíš <span style="color: #f00;">❌ "Unauthorized"</span> → Počkej 30 sekund a klikni na "🔄 RELOAD IFRAME"</li>
            <li>Pokud stále nefunguje po 2-3 reload → Kontaktuj hosting support pro vyčištění opcache</li>
        </ol>
    </div>

</body>
</html>
