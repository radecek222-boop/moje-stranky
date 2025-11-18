<?php
/**
 * DEBUG SESSION - Diagnostický nástroj pro kontrolu session
 *
 * Tento skript zobrazí aktuální stav session a pomůže identifikovat
 * problémy s přihlášením technika na photocustomer.php
 *
 * BEZPEČNOST: Pouze pro přihlášené uživatele (admin nebo technik)
 */

require_once "init.php";

// BEZPEČNOST: Kontrola přihlášení (stejná logika jako photocustomer.php)
$isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
if (!$isLoggedIn) {
    http_response_code(403);
    die('<html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: sans-serif; text-align: center; padding: 50px;"><h1>🔒 Přístup odepřen</h1><p>Tento diagnostický nástroj je dostupný pouze pro přihlášené uživatele.</p><a href="login.php" style="display: inline-block; padding: 10px 20px; background: #2D5016; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px;">Přihlásit se</a></body></html>');
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Debug Session | WGS Service</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
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
        .section {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #2D5016;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        .error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        .info {
            background: #d1ecf1;
            border-left-color: #17a2b8;
            color: #0c5460;
        }
        .key {
            font-weight: bold;
            color: #2D5016;
            display: inline-block;
            min-width: 200px;
        }
        .value {
            color: #333;
        }
        pre {
            background: #1a1a1a;
            color: #00ff88;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2D5016;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
        }
        .btn:hover {
            background: #1a300d;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Debug Session - Diagnostika přihlášení</h1>

    <div class="section info">
        <strong>ℹ️ O tomto nástroji:</strong><br>
        Tento skript zobrazuje aktuální stav PHP session a pomáhá identifikovat problémy
        s přístupem technika na stránku <code>photocustomer.php</code>.
    </div>

    <?php
    // Kontrola session ID
    $sessionId = session_id();

    // Kontrola správnosti session nastavení
    $cookieLifetime = ini_get('session.cookie_lifetime');
    $gcMaxlifetime = ini_get('session.gc_maxlifetime');
    $cookieSecure = ini_get('session.cookie_secure');
    $cookieHttponly = ini_get('session.cookie_httponly');
    $cookieSamesite = ini_get('session.cookie_samesite');

    $sessionNastaveniOk = true;
    $sessionProblemy = [];

    if ($cookieLifetime != 3600 && $cookieLifetime != 0) {
        $sessionNastaveniOk = false;
        $sessionProblemy[] = "Cookie Lifetime je {$cookieLifetime} místo 3600 nebo 0";
    }
    if ($gcMaxlifetime != 3600) {
        $sessionNastaveniOk = false;
        $sessionProblemy[] = "GC Maxlifetime je {$gcMaxlifetime} místo 3600";
    }
    if (!$cookieHttponly) {
        $sessionNastaveniOk = false;
        $sessionProblemy[] = "Cookie HTTPOnly není nastaveno (bezpečnostní riziko)";
    }
    if (empty($cookieSamesite) || $cookieSamesite !== 'Lax') {
        $sessionNastaveniOk = false;
        $sessionProblemy[] = "Cookie SameSite není 'Lax' (session se může ztrácet)";
    }
    ?>

    <h2>📋 Session informace</h2>

    <?php if (!$sessionNastaveniOk): ?>
    <div class="section error">
        <strong>⚠️ Session nastavení má PROBLÉMY:</strong>
        <ul>
            <?php foreach ($sessionProblemy as $problem): ?>
                <li><?php echo htmlspecialchars($problem); ?></li>
            <?php endforeach; ?>
        </ul>
        <p style="margin-top: 10px; padding: 10px; background: white; border-radius: 5px;">
            <strong>🔧 ŘEŠENÍ:</strong><br>
            1. Opraveno v <code>init.php</code> (použití <code>session_set_cookie_params()</code>)<br>
            2. <strong style="color: #dc3545;">→ ODHLASTE SE A ZNOVU SE PŘIHLASTE!</strong> (session se musí restartovat)<br>
            3. Obnovte tuto stránku a zkontrolujte, zda se vše opravilo
        </p>
    </div>
    <?php else: ?>
    <div class="section success">
        <strong>✅ Session nastavení je SPRÁVNÉ!</strong><br>
        Všechny parametry jsou nastaveny korektně.
    </div>
    <?php endif; ?>

    <div class="section">
        <div><span class="key">Session ID:</span> <span class="value"><code><?php echo htmlspecialchars($sessionId); ?></code></span></div>
        <div><span class="key">Session Status:</span> <span class="value"><?php echo session_status() === PHP_SESSION_ACTIVE ? '✅ AKTIVNÍ' : '❌ NEAKTIVNÍ'; ?></span></div>
        <div><span class="key">Cookie Lifetime:</span> <span class="value"><?php echo $cookieLifetime; ?> sekund <?php echo ($cookieLifetime == 3600 || $cookieLifetime == 0) ? '✅' : '❌'; ?></span></div>
        <div><span class="key">GC Maxlifetime:</span> <span class="value"><?php echo $gcMaxlifetime; ?> sekund <?php echo $gcMaxlifetime == 3600 ? '✅' : '❌'; ?></span></div>
        <div><span class="key">Cookie Secure:</span> <span class="value"><?php echo $cookieSecure ? '✅ ANO (HTTPS)' : '⚠️ NE'; ?></span></div>
        <div><span class="key">Cookie HTTPOnly:</span> <span class="value"><?php echo $cookieHttponly ? '✅ ANO' : '❌ NE'; ?></span></div>
        <div><span class="key">Cookie SameSite:</span> <span class="value"><?php echo $cookieSamesite ?: '❌ NENÍ NASTAVENO'; ?> <?php echo ($cookieSamesite === 'Lax') ? '✅' : '❌'; ?></span></div>
    </div>

    <h2>👤 Přihlášení</h2>
    <?php
    $userId = $_SESSION['user_id'] ?? null;
    $userName = $_SESSION['user_name'] ?? null;
    $userEmail = $_SESSION['user_email'] ?? null;
    $role = $_SESSION['role'] ?? null;
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

    // Kontrola podle logiky photocustomer.php
    $isLoggedInPhotocustomer = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
    ?>

    <?php if ($isLoggedInPhotocustomer): ?>
        <div class="section success">
            <strong>✅ ÚSPĚCH: Uživatel je přihlášen</strong><br>
            Podle logiky v <code>photocustomer.php</code> by měl mít přístup.
        </div>
    <?php else: ?>
        <div class="section error">
            <strong>❌ PROBLÉM: Uživatel NENÍ přihlášen</strong><br>
            Podle logiky v <code>photocustomer.php</code> bude přesměrován na login.
        </div>
    <?php endif; ?>

    <div class="section">
        <div><span class="key">user_id isset:</span> <span class="value"><?php echo isset($_SESSION['user_id']) ? '✅ ANO' : '❌ NE'; ?></span></div>
        <div><span class="key">user_id hodnota:</span> <span class="value"><?php echo $userId !== null ? htmlspecialchars($userId) : '⚠️ NENÍ NASTAVENO'; ?></span></div>
        <div><span class="key">user_name:</span> <span class="value"><?php echo $userName !== null ? htmlspecialchars($userName) : '⚠️ NENÍ NASTAVENO'; ?></span></div>
        <div><span class="key">user_email:</span> <span class="value"><?php echo $userEmail !== null ? htmlspecialchars($userEmail) : '⚠️ NENÍ NASTAVENO'; ?></span></div>
        <div><span class="key">role:</span> <span class="value"><?php echo $role !== null ? htmlspecialchars($role) : '⚠️ NENÍ NASTAVENO'; ?></span></div>
        <div><span class="key">is_admin:</span> <span class="value"><?php echo $isAdmin ? '✅ ANO (admin)' : '❌ NE (technik/prodejce)'; ?></span></div>
    </div>

    <h2>📊 Celá $_SESSION data</h2>
    <div class="section">
        <pre><?php print_r($_SESSION); ?></pre>
    </div>

    <h2>🔧 Photocustomer.php kontrola</h2>
    <div class="section">
        <strong>Logika v photocustomer.php (řádek 16):</strong>
        <pre>$isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);</pre>

        <div style="margin-top: 15px;">
            <span class="key">isset($_SESSION['user_id']):</span>
            <span class="value"><?php echo isset($_SESSION['user_id']) ? '✅ TRUE' : '❌ FALSE'; ?></span>
        </div>
        <div>
            <span class="key">isset($_SESSION['is_admin']):</span>
            <span class="value"><?php echo isset($_SESSION['is_admin']) ? '✅ TRUE' : '❌ FALSE'; ?></span>
        </div>
        <div>
            <span class="key">$_SESSION['is_admin'] === true:</span>
            <span class="value"><?php echo (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) ? '✅ TRUE' : '❌ FALSE'; ?></span>
        </div>
        <div style="margin-top: 10px; padding: 10px; background: white; border-radius: 5px;">
            <strong>VÝSLEDEK:</strong>
            <?php if ($isLoggedInPhotocustomer): ?>
                <span style="color: #28a745; font-weight: bold;">✅ PŘÍSTUP POVOLEN</span>
            <?php else: ?>
                <span style="color: #dc3545; font-weight: bold;">❌ PŘÍSTUP ODEPŘEN → redirect na login.php</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$isLoggedInPhotocustomer): ?>
    <h2>⚠️ Doporučení</h2>
    <div class="section warning">
        <strong>Problém identifikován:</strong>
        <ul>
            <li>Session neobsahuje <code>$_SESSION['user_id']</code></li>
            <li>A zároveň neobsahuje <code>$_SESSION['is_admin'] = true</code></li>
        </ul>

        <strong>Možné příčiny:</strong>
        <ol>
            <li><strong>Session vypršela</strong> - Technik je přihlášen déle než 1 hodinu (<?php echo ini_get('session.gc_maxlifetime'); ?> sekund)</li>
            <li><strong>Chyba při přihlášení</strong> - Login controller nenastavil správně <code>$_SESSION['user_id']</code></li>
            <li><strong>Session se resetovala</strong> - Někde v kódu se volá <code>session_destroy()</code> nebo <code>session_regenerate_id()</code> bez zachování dat</li>
            <li><strong>Cookie problém</strong> - Session cookie se neuloží kvůli HTTPS/SameSite nastavení</li>
        </ol>

        <strong>Řešení:</strong>
        <ol>
            <li>Zkuste se <strong>odhlásit a znovu přihlásit</strong></li>
            <li>Zkontrolujte logy v <code>/logs/php_errors.log</code></li>
            <li>Ověřte, že v databázi <code>wgs_users</code> má technik správnou roli</li>
        </ol>
    </div>
    <?php endif; ?>

    <h2>🔗 Akce</h2>
    <div class="section">
        <a href="photocustomer.php" class="btn">Zkusit otevřít photocustomer.php</a>
        <a href="seznam.php" class="btn">Otevřít seznam.php</a>
        <a href="login.php" class="btn">Přihlásit se</a>
        <a href="logout.php" class="btn" style="background: #dc3545;">Odhlásit se</a>
        <a href="javascript:location.reload()" class="btn" style="background: #6c757d;">Obnovit tuto stránku</a>
    </div>

    <div class="section info" style="margin-top: 30px;">
        <strong>📝 Poznámka:</strong><br>
        Po provedení změn (přihlášení, odhlášení) klikněte na "Obnovit tuto stránku" pro aktualizaci údajů.
    </div>
</div>
</body>
</html>
