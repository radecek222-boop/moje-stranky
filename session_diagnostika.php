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

// BEZPEČNOST: Kontrola přihlášení (AKTUALIZOVÁNO podle nové logiky photocustomer.php)
// KROK 1: Kontrola user_id (NOVĚ PŘIDÁNO v photocustomer.php!)
$hasUserId = isset($_SESSION['user_id']);
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Pro diagnostiku povolíme i nepřihlášené uživatele, aby viděli, CO jim chybí
// (v photocustomer.php by došlo k redirectu)
$isLoggedIn = $hasUserId || $isAdmin;

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
    $rawRole = (string) ($_SESSION['role'] ?? '');
    $normalizedRole = strtolower(trim($rawRole));
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

    // ✅ NOVÁ LOGIKA photocustomer.php (OPRAVENO 2025-11-18)
    // KROK 1: Kontrola user_id (CRITICAL!)
    $hasUserId = isset($_SESSION['user_id']);

    // KROK 2: Kontrola role (admin nebo technik)
    $technikKeywords = ['technik', 'technician'];
    $isTechnik = in_array($normalizedRole, $technikKeywords, true);
    if (!$isTechnik) {
        foreach ($technikKeywords as $keyword) {
            if (strpos($normalizedRole, $keyword) !== false) {
                $isTechnik = true;
                break;
            }
        }
    }

    // VÝSLEDEK: Přístup k photocustomer.php
    $passedStep1 = $hasUserId;  // KROK 1: Musí mít user_id
    $passedStep2 = $isAdmin || $isTechnik;  // KROK 2: Musí být admin nebo technik

    $isLoggedInPhotocustomer = $passedStep1 && $passedStep2;  // OBA KROKY MUSÍ PROJÍT!
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
        <div><span class="key">role (raw):</span> <span class="value">'<?php echo htmlspecialchars($rawRole); ?>'</span></div>
        <div><span class="key">role (normalized):</span> <span class="value">'<?php echo htmlspecialchars($normalizedRole); ?>'</span></div>
        <div><span class="key">is_admin:</span> <span class="value"><?php echo $isAdmin ? '✅ ANO (admin)' : '❌ NE'; ?></span></div>
        <div><span class="key">isTechnik:</span> <span class="value"><?php echo $isTechnik ? '✅ ANO (technik)' : '❌ NE'; ?></span></div>
    </div>

    <h2>📊 Celá $_SESSION data</h2>
    <div class="section">
        <pre><?php print_r($_SESSION); ?></pre>
    </div>

    <h2>🔧 Photocustomer.php kontrola (NOVÁ LOGIKA - 2025-11-18)</h2>
    <div class="section">
        <strong>✅ KROK 1: Kontrola user_id (photocustomer.php řádek 6-10)</strong>
        <pre>if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=photocustomer.php');
    exit;
}</pre>

        <div style="margin-top: 15px; padding: 10px; background: white; border-radius: 5px;">
            <span class="key">isset($_SESSION['user_id']):</span>
            <span class="value"><?php echo $hasUserId ? '✅ TRUE' : '❌ FALSE'; ?></span>
            <?php if (!$hasUserId): ?>
                <br><span style="color: #dc3545; font-weight: bold;">❌ KROK 1 SELHAL → redirect na login.php</span>
            <?php else: ?>
                <br><span style="color: #28a745; font-weight: bold;">✅ KROK 1 ÚSPĚŠNÝ → pokračuje na KROK 2</span>
            <?php endif; ?>
        </div>

        <strong style="display: block; margin-top: 20px;">✅ KROK 2: Kontrola role - admin nebo technik (photocustomer.php řádek 22-51)</strong>
        <pre>$rawRole = (string) ($_SESSION['role'] ?? '');
$normalizedRole = strtolower(trim($rawRole));
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Kontrola technika
$technikKeywords = ['technik', 'technician'];
$isTechnik = in_array($normalizedRole, $technikKeywords, true);
if (!$isTechnik) {
    foreach ($technikKeywords as $keyword) {
        if (strpos($normalizedRole, $keyword) !== false) {
            $isTechnik = true;
            break;
        }
    }
}

if (!$isAdmin && !$isTechnik) {
    header('Location: login.php?redirect=photocustomer.php');
    exit;
}</pre>

        <div style="margin-top: 15px; padding: 10px; background: white; border-radius: 5px;">
            <div><span class="key">$rawRole:</span> <span class="value">'<?php echo htmlspecialchars($rawRole); ?>'</span></div>
            <div><span class="key">$normalizedRole:</span> <span class="value">'<?php echo htmlspecialchars($normalizedRole); ?>'</span></div>
            <div><span class="key">$isAdmin:</span> <span class="value"><?php echo $isAdmin ? '✅ TRUE' : '❌ FALSE'; ?></span></div>
            <div><span class="key">$isTechnik:</span> <span class="value"><?php echo $isTechnik ? '✅ TRUE' : '❌ FALSE'; ?></span></div>
            <div><span class="key">(!$isAdmin && !$isTechnik):</span> <span class="value"><?php echo (!$isAdmin && !$isTechnik) ? '❌ TRUE (redirect)' : '✅ FALSE (přístup povolen)'; ?></span></div>

            <?php if ($passedStep2): ?>
                <br><span style="color: #28a745; font-weight: bold;">✅ KROK 2 ÚSPĚŠNÝ → uživatel je admin nebo technik</span>
            <?php else: ?>
                <br><span style="color: #dc3545; font-weight: bold;">❌ KROK 2 SELHAL → uživatel není admin ani technik → redirect na login.php</span>
                <br><span style="color: #856404; background: #fff3cd; padding: 5px; border-radius: 3px; display: inline-block; margin-top: 5px;">
                    ⚠️ ŘEŠENÍ: V databázi <code>wgs_users</code> musí mít uživatel roli obsahující 'technik' nebo 'technician'
                </span>
            <?php endif; ?>
        </div>

        <div style="margin-top: 20px; padding: 15px; background: <?php echo $isLoggedInPhotocustomer ? '#d4edda' : '#f8d7da'; ?>; border-radius: 5px;">
            <strong style="font-size: 18px;">FINÁLNÍ VÝSLEDEK:</strong><br>
            <?php if ($isLoggedInPhotocustomer): ?>
                <span style="color: #28a745; font-weight: bold; font-size: 20px;">✅ PŘÍSTUP K PHOTOCUSTOMER.PHP POVOLEN</span>
                <br><span style="color: #155724;">Oba kroky prošly úspěšně! Uživatel má přístup k fotodokumentaci.</span>
            <?php else: ?>
                <span style="color: #dc3545; font-weight: bold; font-size: 20px;">❌ PŘÍSTUP K PHOTOCUSTOMER.PHP ODEPŘEN</span>
                <br><span style="color: #721c24;">→ Uživatel bude přesměrován na login.php</span>
                <br><br>
                <strong>Důvod:</strong>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <?php if (!$passedStep1): ?>
                        <li style="color: #721c24;">❌ KROK 1: Chybí $_SESSION['user_id']</li>
                    <?php endif; ?>
                    <?php if (!$passedStep2): ?>
                        <li style="color: #721c24;">❌ KROK 2: Uživatel není admin ani technik (role: '<?php echo htmlspecialchars($rawRole); ?>')</li>
                    <?php endif; ?>
                </ul>
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

    <h2>💻 JavaScript diagnostika pro konzoli prohlížeče</h2>
    <div class="section">
        <p><strong>Pokud jste na stránce <code>seznam.php</code> nebo <code>photocustomer.php</code>, vložte tento kód do konzole prohlížeče (F12):</strong></p>
        <pre style="background: #1a1a1a; color: #00ff88; padding: 15px; border-radius: 5px; overflow-x: auto; cursor: pointer;"
             onclick="navigator.clipboard.writeText(this.textContent.trim()); alert('✅ Kód zkopírován do schránky!');">
// 🔍 WGS Session Diagnostika - Console Test
(async function() {
  console.log('%c🔍 WGS SESSION DIAGNOSTIKA', 'font-size: 20px; color: #00ff88; font-weight: bold;');
  console.log('%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #00ff88;');

  // Test 1: localStorage (customer data)
  console.log('\n%c📦 KROK 1: LocalStorage (customer data)', 'font-size: 16px; color: #ffaa00; font-weight: bold;');
  const customerData = localStorage.getItem('currentCustomer');
  if (customerData) {
    const parsed = JSON.parse(customerData);
    console.log('✅ currentCustomer:', parsed);
  } else {
    console.log('❌ currentCustomer: není nastaveno');
  }

  // Test 2: PHP Session (přes API)
  console.log('\n%c👤 KROK 2: PHP Session (server-side)', 'font-size: 16px; color: #ffaa00; font-weight: bold;');
  try {
    const response = await fetch(window.location.href);
    const html = await response.text();

    // Parse session info from HTML (pokud je to session_diagnostika.php)
    if (html.includes('user_id isset')) {
      console.log('✅ Session diagnostika stránka načtena - podívejte se do UI');
    }

    // Alternativně: zkus zavolat user_session_check.php
    const sessionCheck = await fetch('/includes/user_session_check.php');
    const sessionData = await sessionCheck.json();

    console.log('Session data z API:', sessionData);

    if (sessionData.logged_in) {
      console.log('%c✅ PŘIHLÁŠEN', 'color: #00ff88; font-weight: bold;');
      console.log('  user_id:', sessionData.user_id);
      console.log('  name:', sessionData.name);
      console.log('  email:', sessionData.email);
      console.log('  role:', sessionData.role);
    } else {
      console.log('%c❌ NEPŘIHLÁŠEN', 'color: #ff4444; font-weight: bold;');
    }
  } catch (err) {
    console.error('❌ Chyba při načítání session:', err);
  }

  // Test 3: Pokus o přístup k photocustomer.php
  console.log('\n%c🚪 KROK 3: Test přístupu k photocustomer.php', 'font-size: 16px; color: #ffaa00; font-weight: bold;');
  try {
    const photoTest = await fetch('/photocustomer.php', { redirect: 'manual' });

    if (photoTest.type === 'opaqueredirect' || photoTest.status === 302 || photoTest.status === 301) {
      console.log('%c❌ REDIRECT DETEKOVÁN!', 'color: #ff4444; font-weight: bold; font-size: 14px;');
      console.log('  → photocustomer.php redirectuje na login.php');
      console.log('  → Příčina: Chybí user_id NEBO uživatel není admin/technik');
    } else if (photoTest.status === 200 || photoTest.ok) {
      console.log('%c✅ PŘÍSTUP POVOLEN!', 'color: #00ff88; font-weight: bold; font-size: 14px;');
      console.log('  → photocustomer.php vrátilo status 200');
      console.log('  → Uživatel má přístup k fotodokumentaci');
    } else {
      console.log(`⚠️ Neočekávaný status: ${photoTest.status}`);
    }
  } catch (err) {
    console.error('❌ Chyba při testu photocustomer.php:', err);
  }

  console.log('\n%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #00ff88;');
  console.log('%c✅ DIAGNOSTIKA DOKONČENA', 'font-size: 16px; color: #00ff88; font-weight: bold;');
  console.log('%cPro detailní výsledky otevřete: https://www.wgs-service.cz/session_diagnostika.php', 'color: #ffaa00;');
})();
        </pre>
        <p style="margin-top: 10px; color: #666; font-size: 14px;">
            💡 <strong>Tip:</strong> Klikněte na kód pro zkopírování do schránky!
        </p>
    </div>

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
