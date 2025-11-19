<?php
/**
 * DIAGNOSTIKA SESSION & IFRAME
 * Tento skript ukáže PŘESNĚ co se děje se session, cookies a iframe
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    // Pokud nejsme přihlášeni, ZOBRAZÍME PROČ
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <title>❌ NEPŘIHLÁŠEN - Diagnostika</title>
        <style>
            body { font-family: 'Courier New', monospace; padding: 2rem; background: #000; color: #0f0; }
            .box { background: #1a1a1a; border: 2px solid #0f0; padding: 1.5rem; margin: 1rem 0; border-radius: 8px; }
            h1 { color: #f00; }
            h2 { color: #ff0; border-bottom: 2px solid #ff0; padding-bottom: 0.5rem; }
            .error { color: #f00; font-weight: bold; }
            .warning { color: #ff0; }
            .success { color: #0f0; }
            table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
            th, td { border: 1px solid #0f0; padding: 0.5rem; text-align: left; }
            th { background: #0a3d0a; }
        </style>
    </head>
    <body>
        <h1>❌ NEJSTE PŘIHLÁŠEN JAKO ADMIN</h1>

        <div class="box error">
            <h2>PROČ VIDÍTE TUTO STRÁNKU:</h2>
            <p>Session neobsahuje <code>$_SESSION['is_admin'] = true</code></p>
        </div>

        <div class="box">
            <h2>📊 SESSION DATA:</h2>
            <table>
                <tr><th>Klíč</th><th>Hodnota</th></tr>
                <?php if (empty($_SESSION)): ?>
                    <tr><td colspan="2" class="error">❌ SESSION JE PRÁZDNÁ!</td></tr>
                <?php else: ?>
                    <?php foreach ($_SESSION as $key => $value): ?>
                        <tr>
                            <td><?= htmlspecialchars($key) ?></td>
                            <td><?= htmlspecialchars(is_array($value) ? json_encode($value) : $value) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>

        <div class="box">
            <h2>🍪 COOKIES:</h2>
            <table>
                <tr><th>Cookie Name</th><th>Value</th></tr>
                <?php if (empty($_COOKIE)): ?>
                    <tr><td colspan="2" class="error">❌ ŽÁDNÉ COOKIES!</td></tr>
                <?php else: ?>
                    <?php foreach ($_COOKIE as $name => $value): ?>
                        <tr>
                            <td><?= htmlspecialchars($name) ?></td>
                            <td><?= htmlspecialchars(substr($value, 0, 50)) ?><?= strlen($value) > 50 ? '...' : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
            <p class="warning">Session cookie name: <strong><?= session_name() ?></strong></p>
            <p class="warning">Session ID: <strong><?= session_id() ?></strong></p>
        </div>

        <div class="box">
            <h2>💡 JAK OPRAVIT:</h2>
            <ol>
                <li>Přihlaste se na <a href="/admin.php" style="color: #0ff;">/admin.php</a></li>
                <li>Pak se vraťte na tuto stránku</li>
            </ol>
        </div>

    </body>
    </html>
    <?php
    exit;
}

// Pokud JSME přihlášeni, zobrazíme kompletní diagnostiku
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>✅ DIAGNOSTIKA SESSION & IFRAME</title>
    <style>
        body { font-family: 'Courier New', monospace; padding: 2rem; background: #000; color: #0f0; }
        .box { background: #1a1a1a; border: 2px solid #0f0; padding: 1.5rem; margin: 1rem 0; border-radius: 8px; }
        h1 { color: #0f0; text-align: center; border-bottom: 3px solid #0f0; padding-bottom: 1rem; }
        h2 { color: #ff0; border-bottom: 2px solid #ff0; padding-bottom: 0.5rem; }
        .error { color: #f00; font-weight: bold; }
        .warning { color: #ff0; }
        .success { color: #0f0; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { border: 1px solid #0f0; padding: 0.5rem; text-align: left; }
        th { background: #0a3d0a; }
        iframe { border: 3px solid #0ff; width: 100%; height: 400px; margin: 1rem 0; }
        .test-btn { background: #0f0; color: #000; padding: 1rem 2rem; border: none; border-radius: 4px;
                    font-size: 1.2rem; font-weight: bold; cursor: pointer; margin: 1rem; }
        .test-btn:hover { background: #0ff; }
        code { background: #333; padding: 0.2rem 0.5rem; border-radius: 3px; color: #0ff; }
    </style>
</head>
<body>
    <h1>✅ DIAGNOSTIKA SESSION & IFRAME</h1>

    <div class="box success">
        <h2>✅ JSTE PŘIHLÁŠEN JAKO ADMIN</h2>
        <p>Session obsahuje správné autorizační údaje.</p>
    </div>

    <!-- SESSION INFO -->
    <div class="box">
        <h2>📊 SESSION DATA:</h2>
        <table>
            <tr><th>Klíč</th><th>Hodnota</th></tr>
            <?php foreach ($_SESSION as $key => $value): ?>
                <tr>
                    <td><?= htmlspecialchars($key) ?></td>
                    <td><?= htmlspecialchars(is_array($value) ? json_encode($value) : $value) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- COOKIES INFO -->
    <div class="box">
        <h2>🍪 COOKIES:</h2>
        <table>
            <tr><th>Cookie Name</th><th>Value (first 50 chars)</th></tr>
            <?php foreach ($_COOKIE as $name => $value): ?>
                <tr>
                    <td><?= htmlspecialchars($name) ?></td>
                    <td><?= htmlspecialchars(substr($value, 0, 50)) ?><?= strlen($value) > 50 ? '...' : '' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <p><strong>Session cookie name:</strong> <code><?= session_name() ?></code></p>
        <p><strong>Session ID:</strong> <code><?= session_id() ?></code></p>
    </div>

    <!-- PHP SESSION CONFIG -->
    <div class="box">
        <h2>⚙️ PHP SESSION KONFIGURACE:</h2>
        <table>
            <tr>
                <th>Nastavení</th>
                <th>Hodnota</th>
                <th>Status</th>
            </tr>
            <tr>
                <td>session.cookie_samesite</td>
                <td><code><?= ini_get('session.cookie_samesite') ?: 'NOT SET' ?></code></td>
                <td class="<?= (ini_get('session.cookie_samesite') === 'None') ? 'success' : 'error' ?>">
                    <?= (ini_get('session.cookie_samesite') === 'None') ? '✅ OK pro iframe' : '❌ MĚLO BY BÝT "None"' ?>
                </td>
            </tr>
            <tr>
                <td>session.cookie_secure</td>
                <td><code><?= ini_get('session.cookie_secure') ? 'true' : 'false' ?></code></td>
                <td class="<?= ini_get('session.cookie_secure') ? 'success' : 'warning' ?>">
                    <?= ini_get('session.cookie_secure') ? '✅ Pouze HTTPS' : '⚠️ Mělo by být true' ?>
                </td>
            </tr>
            <tr>
                <td>session.cookie_httponly</td>
                <td><code><?= ini_get('session.cookie_httponly') ? 'true' : 'false' ?></code></td>
                <td class="<?= ini_get('session.cookie_httponly') ? 'success' : 'error' ?>">
                    <?= ini_get('session.cookie_httponly') ? '✅ Ochrana proti XSS' : '❌ Mělo by být true' ?>
                </td>
            </tr>
            <tr>
                <td>session.gc_maxlifetime</td>
                <td><code><?= ini_get('session.gc_maxlifetime') ?> sekund (<?= round(ini_get('session.gc_maxlifetime') / 60) ?> minut)</code></td>
                <td class="success">✅ Platnost session</td>
            </tr>
            <tr>
                <td>PHP Version</td>
                <td><code><?= PHP_VERSION ?></code></td>
                <td class="success">✅</td>
            </tr>
        </table>
    </div>

    <!-- SERVER INFO -->
    <div class="box">
        <h2>🌐 SERVER INFO:</h2>
        <table>
            <tr><th>Proměnná</th><th>Hodnota</th></tr>
            <tr>
                <td>HTTPS</td>
                <td><code><?= isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'NOT SET' ?></code></td>
            </tr>
            <tr>
                <td>SERVER_PORT</td>
                <td><code><?= $_SERVER['SERVER_PORT'] ?? 'NOT SET' ?></code></td>
            </tr>
            <tr>
                <td>HTTP_HOST</td>
                <td><code><?= $_SERVER['HTTP_HOST'] ?? 'NOT SET' ?></code></td>
            </tr>
            <tr>
                <td>REQUEST_URI</td>
                <td><code><?= $_SERVER['REQUEST_URI'] ?? 'NOT SET' ?></code></td>
            </tr>
            <tr>
                <td>HTTP_REFERER</td>
                <td><code><?= $_SERVER['HTTP_REFERER'] ?? 'NOT SET' ?></code></td>
            </tr>
        </table>
    </div>

    <!-- IFRAME TEST -->
    <div class="box">
        <h2>🖼️ TEST IFRAME:</h2>
        <p>Tento iframe načítá <code>/includes/admin_reklamace_management.php</code> - stejný soubor jako v admin panelu:</p>

        <button class="test-btn" onclick="reloadIframe()">🔄 RELOAD IFRAME</button>
        <button class="test-btn" onclick="openInNewTab()">🔗 OTEVŘÍT V NOVÉ ZÁLOŽCE</button>

        <iframe id="testIframe" src="/includes/admin_reklamace_management.php?embed=1&filter=all"></iframe>

        <div id="iframeStatus" style="padding: 1rem; margin-top: 1rem; border: 2px solid #ff0; background: #1a1a1a;">
            <strong>Status:</strong> <span id="statusText">Načítám iframe...</span>
        </div>
    </div>

    <!-- JAVASCRIPT DIAGNOSTIKA -->
    <div class="box">
        <h2>🔍 JAVASCRIPT COOKIE DIAGNOSTIKA:</h2>
        <div id="jsCookies" style="background: #222; padding: 1rem; border-radius: 4px;"></div>
    </div>

    <script>
    // JavaScript diagnostika
    function displayCookies() {
        const cookies = document.cookie.split(';').map(c => c.trim());
        const container = document.getElementById('jsCookies');

        if (cookies.length === 0 || (cookies.length === 1 && cookies[0] === '')) {
            container.innerHTML = '<p class="error">❌ JavaScript NEVIDÍ ŽÁDNÉ COOKIES!</p><p class="warning">⚠️ To je NORMÁLNÍ pro HttpOnly cookies (které by měly být skryté před JS).</p>';
        } else {
            let html = '<table style="width: 100%;"><tr><th>Cookie</th><th>Value</th></tr>';
            cookies.forEach(cookie => {
                const [name, ...valueParts] = cookie.split('=');
                const value = valueParts.join('=');
                html += `<tr><td>${name}</td><td>${value.substring(0, 50)}${value.length > 50 ? '...' : ''}</td></tr>`;
            });
            html += '</table>';
            container.innerHTML = html;
        }
    }

    // Reload iframe
    function reloadIframe() {
        const iframe = document.getElementById('testIframe');
        const timestamp = new Date().getTime();
        iframe.src = `/includes/admin_reklamace_management.php?embed=1&filter=all&_t=${timestamp}`;
        document.getElementById('statusText').textContent = 'Reload iframe...';
    }

    // Open in new tab
    function openInNewTab() {
        window.open('/includes/admin_reklamace_management.php?embed=1&filter=all', '_blank');
    }

    // Monitor iframe load
    document.getElementById('testIframe').addEventListener('load', function() {
        document.getElementById('statusText').textContent = '✅ Iframe načten (zkontrolujte obsah výše)';

        // Try to read iframe content (will fail due to same-origin if there's an issue)
        try {
            const iframeDoc = this.contentDocument || this.contentWindow.document;
            const body = iframeDoc.body;
            if (body && body.textContent.includes('Unauthorized')) {
                document.getElementById('statusText').innerHTML = '❌ <span class="error">IFRAME VRACÍ "Unauthorized"</span> - Session cookies se NEPOSÍLAJÍ!';
            } else if (body && body.textContent.trim().length < 50) {
                document.getElementById('statusText').innerHTML = `⚠️ <span class="warning">Iframe obsahuje krátký text: "${body.textContent.substring(0, 100)}"</span>`;
            } else {
                document.getElementById('statusText').innerHTML = '✅ <span class="success">Iframe načten úspěšně a obsahuje obsah</span>';
            }
        } catch (e) {
            document.getElementById('statusText').innerHTML = `⚠️ Nelze přečíst obsah iframe (možná CORS): ${e.message}`;
        }
    });

    // Display cookies on load
    displayCookies();
    </script>

    <div class="box">
        <h2>📋 ZÁVĚR & DOPORUČENÍ:</h2>
        <ol>
            <li class="<?= (ini_get('session.cookie_samesite') === 'None') ? 'success' : 'error' ?>">
                <?= (ini_get('session.cookie_samesite') === 'None')
                    ? '✅ SameSite=None je nastaveno správně'
                    : '❌ SameSite NENÍ "None" - iframe nebude fungovat!' ?>
            </li>
            <li>Zkontrolujte iframe výše - pokud vidíte "Unauthorized", session cookies se NEPOSÍLAJÍ</li>
            <li>Pokud iframe funguje zde, ale ne v admin.php, problém je v admin.php JavaScriptu</li>
            <li>Použijte tlačítko "OTEVŘÍT V NOVÉ ZÁLOŽCE" pro test mimo iframe</li>
        </ol>
    </div>

</body>
</html>
