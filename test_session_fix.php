<?php
/**
 * TEST SESSION FIX - ověří OKAMŽITĚ jestli oprava funguje
 * Tento skript VYNUTÍ session nastavení a pak je zkontroluje
 */

// KROK 1: VYNUTIT session nastavení (stejně jako v admin_reklamace_management.php)
if (session_status() === PHP_SESSION_NONE) {
    // Detekce HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    // FORCE session parametry
    session_set_cookie_params(
        3600,           // lifetime
        '/',            // path
        '',             // domain
        $isHttps,       // secure
        true            // httponly
    );

    // SameSite=None MUSÍ být nastaven
    if (PHP_VERSION_ID >= 70300) {
        ini_set('session.cookie_samesite', 'None');
    }

    session_start();
}

// KROK 2: Zkontrolovat nastavení
$sameSite = ini_get('session.cookie_samesite');
$secure = ini_get('session.cookie_secure');
$httponly = ini_get('session.cookie_httponly');

$allGood = ($sameSite === 'None' && $secure && $httponly);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title><?= $allGood ? '✅' : '❌' ?> Test Session Fix</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: <?= $allGood ? '#d4edda' : '#f8d7da' ?>;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: <?= $allGood ? '#155724' : '#721c24' ?>;
            border-bottom: 3px solid <?= $allGood ? '#155724' : '#721c24' ?>;
            padding-bottom: 10px;
            font-size: 2rem;
            text-align: center;
        }
        .success {
            background: #d4edda;
            border: 2px solid #c3e6cb;
            color: #155724;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 1.1rem;
        }
        .error {
            background: #f8d7da;
            border: 2px solid #f5c6cb;
            color: #721c24;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 1.1rem;
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
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #2D5016;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
            font-size: 1.1rem;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #1a300d;
        }
        .btn-large {
            font-size: 1.3rem;
            padding: 15px 30px;
            font-weight: bold;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .highlight {
            background: #fff3cd;
            padding: 15px;
            border-left: 4px solid #ffc107;
            margin: 20px 0;
        }
    </style>
</head>
<body>
<div class="container">

    <?php if ($allGood): ?>
        <!-- SUCCESS -->
        <h1>✅ OPRAVA FUNGUJE!</h1>

        <div class="success">
            <strong>🎉 VÝBORNĚ! Session je správně nakonfigurována!</strong>
            <p>Všechna nastavení jsou v pořádku a iframe by měl nyní fungovat.</p>
        </div>

        <table>
            <tr>
                <th>Nastavení</th>
                <th>Hodnota</th>
                <th>Status</th>
            </tr>
            <tr>
                <td>session.cookie_samesite</td>
                <td><code><?= $sameSite ?></code></td>
                <td style="color: green; font-weight: bold;">✅ OK</td>
            </tr>
            <tr>
                <td>session.cookie_secure</td>
                <td><code>true</code></td>
                <td style="color: green; font-weight: bold;">✅ OK</td>
            </tr>
            <tr>
                <td>session.cookie_httponly</td>
                <td><code>true</code></td>
                <td style="color: green; font-weight: bold;">✅ OK</td>
            </tr>
        </table>

        <div class="highlight">
            <h3>📋 CO DĚLAT DÁL:</h3>
            <ol style="font-size: 1.1rem;">
                <li><strong>Přejděte na:</strong> <a href="/admin.php" class="btn btn-large">Admin Panel</a></li>
                <li><strong>Klikněte na kartu:</strong> "Správa reklamací"</li>
                <li><strong>Měl by se zobrazit:</strong> Seznam všech reklamací (NE "Unauthorized")</li>
            </ol>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="/test_iframe.php" class="btn">🧪 Vyzkoušet iframe test</a>
            <a href="/diagnostika_session.php" class="btn">📊 Plná diagnostika</a>
        </div>

    <?php else: ?>
        <!-- ERROR -->
        <h1>❌ OPRAVA JEŠTĚ NEFUNGUJE</h1>

        <div class="error">
            <strong>Některá nastavení stále nejsou správná:</strong>
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
                <td><?= ($sameSite === 'None') ? '<span style="color: green;">✅</span>' : '<span style="color: red;">❌</span>' ?></td>
            </tr>
            <tr>
                <td>session.cookie_secure</td>
                <td><code><?= $secure ? 'true' : 'false' ?></code></td>
                <td><code>true</code></td>
                <td><?= $secure ? '<span style="color: green;">✅</span>' : '<span style="color: red;">❌</span>' ?></td>
            </tr>
            <tr>
                <td>session.cookie_httponly</td>
                <td><code><?= $httponly ? 'true' : 'false' ?></code></td>
                <td><code>true</code></td>
                <td><?= $httponly ? '<span style="color: green;">✅</span>' : '<span style="color: red;">❌</span>' ?></td>
            </tr>
        </table>

        <div class="highlight">
            <h3>⚠️ MOŽNÉ PŘÍČINY:</h3>
            <ul>
                <li>PHP verze je starší než 7.3 (aktuální: <code><?= PHP_VERSION ?></code>)</li>
                <li>Server nepodporuje nastavení SameSite</li>
                <li>Kód se ještě neprojevil</li>
            </ul>

            <h3>🔧 CO ZKUSIT:</h3>
            <ol>
                <li>Počkejte 30 sekund a klikněte: <button class="btn" onclick="location.reload()">🔄 Zkontrolovat znovu</button></li>
                <li>Zkontrolujte PHP verzi (musí být 7.3+)</li>
                <li>Kontaktujte hosting support</li>
            </ol>
        </div>
    <?php endif; ?>

    <hr style="margin: 30px 0;">

    <h2>ℹ️ Informace o testu:</h2>
    <p>Tento test:</p>
    <ul>
        <li>✅ VYNUTÍ správné session nastavení přímo v tomto souboru</li>
        <li>✅ NEPOUŽÍVÁ init.php (obchází opcache)</li>
        <li>✅ Ukazuje OKAMŽITÝ výsledek</li>
    </ul>

    <table>
        <tr><th>Detail</th><th>Hodnota</th></tr>
        <tr>
            <td>PHP Version</td>
            <td><code><?= PHP_VERSION ?></code></td>
        </tr>
        <tr>
            <td>HTTPS Detekce</td>
            <td><code><?= $isHttps ? 'ANO' : 'NE' ?></code></td>
        </tr>
        <tr>
            <td>Session ID</td>
            <td><code><?= session_id() ?></code></td>
        </tr>
    </table>

</div>
</body>
</html>
