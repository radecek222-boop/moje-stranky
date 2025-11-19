<?php
/**
 * AUTOMATICKÁ OPRAVA SESSION NASTAVENÍ
 *
 * Tento skript vynutí správné session nastavení pro fungování iframe
 */

// KROK 1: Zničit starou session
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// KROK 2: Načíst novou init.php s opravenými nastaveními
require_once __DIR__ . '/init.php';

// KROK 3: Zkontrolovat nastavení
$sameSite = ini_get('session.cookie_samesite');
$secure = ini_get('session.cookie_secure');
$httponly = ini_get('session.cookie_httponly');

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>🔧 Automatická oprava session nastavení</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2D5016;
            border-bottom: 3px solid #2D5016;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2D5016;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #1a300d;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background: #2D5016;
            color: white;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Automatická oprava session nastavení</h1>

    <?php if ($sameSite === 'None' && $secure && $httponly): ?>
        <div class="success">
            <strong>✅ SESSION JE SPRÁVNĚ NAKONFIGUROVÁNA!</strong>
            <p>Všechna nastavení jsou v pořádku:</p>
            <ul>
                <li>SameSite = None (iframe funguje)</li>
                <li>Secure = true (pouze HTTPS)</li>
                <li>HttpOnly = true (ochrana proti XSS)</li>
            </ul>
        </div>

        <div class="info">
            <p><strong>Co dělat dál:</strong></p>
            <ol>
                <li>Přejděte na <a href="/admin.php" class="btn">Admin panel</a></li>
                <li>Otevřete kartu "Správa reklamací"</li>
                <li>Iframe by měl nyní fungovat správně</li>
            </ol>
        </div>

    <?php else: ?>
        <div class="error">
            <strong>❌ SESSION NENÍ SPRÁVNĚ NAKONFIGUROVÁNA</strong>
            <p>Některá nastavení nejsou správná:</p>
        </div>

        <table>
            <tr>
                <th>Nastavení</th>
                <th>Aktuální hodnota</th>
                <th>Požadovaná hodnota</th>
                <th>Status</th>
            </tr>
            <tr>
                <td>session.cookie_samesite</td>
                <td><code><?= $sameSite ?: 'NOT SET' ?></code></td>
                <td><code>None</code></td>
                <td><?= ($sameSite === 'None') ? '✅' : '❌' ?></td>
            </tr>
            <tr>
                <td>session.cookie_secure</td>
                <td><code><?= $secure ? 'true' : 'false' ?></code></td>
                <td><code>true</code></td>
                <td><?= $secure ? '✅' : '❌' ?></td>
            </tr>
            <tr>
                <td>session.cookie_httponly</td>
                <td><code><?= $httponly ? 'true' : 'false' ?></code></td>
                <td><code>true</code></td>
                <td><?= $httponly ? '✅' : '❌' ?></td>
            </tr>
        </table>

        <div class="warning">
            <strong>⚠️ PROBLÉM:</strong>
            <p>Změny v <code>init.php</code> se ještě neprojevily na serveru.</p>
            <p>Možné příčiny:</p>
            <ul>
                <li>Opcache (PHP opcode cache) - soubor je cachedovaný</li>
                <li>Server ještě nenačetl novou verzi</li>
                <li>Změny nebyly správně deploynuty</li>
            </ul>
        </div>

        <div class="info">
            <p><strong>ŘEŠENÍ:</strong></p>
            <ol>
                <li>Vyčkejte 1-2 minuty (opcache se může vyprázdnit automaticky)</li>
                <li>Restartujte PHP-FPM (pokud máte přístup): <code>sudo systemctl restart php-fpm</code></li>
                <li>Nebo kontaktujte hosting support pro vyčištění opcache</li>
                <li>Pak obnovte tuto stránku (Ctrl+Shift+R)</li>
            </ol>
        </div>

        <button class="btn" onclick="location.reload();">🔄 Zkontrolovat znovu</button>
    <?php endif; ?>

    <hr style="margin: 30px 0;">

    <h2>📊 Aktuální session info:</h2>
    <table>
        <tr><th>Položka</th><th>Hodnota</th></tr>
        <tr>
            <td>Session ID</td>
            <td><code><?= session_id() ?></code></td>
        </tr>
        <tr>
            <td>Session Name</td>
            <td><code><?= session_name() ?></code></td>
        </tr>
        <tr>
            <td>PHP Version</td>
            <td><code><?= PHP_VERSION ?></code></td>
        </tr>
        <tr>
            <td>HTTPS</td>
            <td><code><?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'YES' : 'NO' ?></code></td>
        </tr>
    </table>

    <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-left: 4px solid #2D5016;">
        <strong>💡 TIP:</strong> Po opravě se vraťte na <a href="/diagnostika_session.php">diagnostiku session</a>
        a zkontrolujte že iframe skutečně funguje.
    </div>

</div>
</body>
</html>
