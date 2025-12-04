<?php
/**
 * Composer Update - PHP interface
 *
 * Tento skript kontroluje stav composer zavislosti
 * a nabizi instrukce pro instalaci.
 * Pristup pouze pro admina.
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit tento skript.");
}

// Kontrola dostupnosti shell funkci
$shellFunkcePovoleny = function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', ini_get('disable_functions'))));
$execPovoleno = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))));
$procOpenPovoleno = function_exists('proc_open') && !in_array('proc_open', array_map('trim', explode(',', ini_get('disable_functions'))));

$shellDostupny = $shellFunkcePovoleny || $execPovoleno || $procOpenPovoleno;

$vystup = '';
$uspech = false;
$chyba = '';

// Pokud shell je dostupny a byl pozadavek na spusteni
if ($shellDostupny && isset($_GET['execute']) && $_GET['execute'] === '1') {
    $projektDir = __DIR__;
    $composerPath = null;

    // Hledat composer
    $moznosti = [
        $projektDir . '/composer.phar',
        '/usr/local/bin/composer',
        '/usr/bin/composer'
    ];

    foreach ($moznosti as $cesta) {
        if (file_exists($cesta)) {
            $composerPath = $cesta;
            break;
        }
    }

    // Stahnout composer.phar pokud neexistuje
    if (!$composerPath && !file_exists($projektDir . '/composer.phar')) {
        $pharUrl = 'https://getcomposer.org/download/latest-stable/composer.phar';
        $pharContent = @file_get_contents($pharUrl);
        if ($pharContent && file_put_contents($projektDir . '/composer.phar', $pharContent)) {
            chmod($projektDir . '/composer.phar', 0755);
            $composerPath = $projektDir . '/composer.phar';
        }
    }

    if ($composerPath) {
        // Sestavit prikaz
        if (strpos($composerPath, '.phar') !== false) {
            $prikaz = "cd $projektDir && php $composerPath update 2>&1";
        } else {
            $prikaz = "cd $projektDir && $composerPath update 2>&1";
        }

        // Spustit
        if ($shellFunkcePovoleny) {
            $vystup = shell_exec($prikaz);
        } elseif ($execPovoleno) {
            exec($prikaz, $vystupPole, $navratovyKod);
            $vystup = implode("\n", $vystupPole);
        }

        if ($vystup) {
            if (strpos($vystup, 'Nothing to install') !== false ||
                strpos($vystup, 'Generating autoload') !== false ||
                strpos($vystup, 'Installing') !== false ||
                strpos($vystup, 'Updating') !== false) {
                $uspech = true;
            }
        } else {
            $chyba = "Prikaz nevratil zadny vystup.";
        }
    } else {
        $chyba = "Composer nenalezen a nelze stahnout.";
    }
}

// Kontrola stavu
$composerExistuje = file_exists(__DIR__ . '/composer.json');
$vendorExistuje = is_dir(__DIR__ . '/vendor');
$webPushNainstalovano = file_exists(__DIR__ . '/vendor/minishlink/web-push/src/WebPush.php');
$autoloadExistuje = file_exists(__DIR__ . '/vendor/autoload.php');
$composerPharExistuje = file_exists(__DIR__ . '/composer.phar');

// VAPID klice
$vapidPublic = $_ENV['VAPID_PUBLIC_KEY'] ?? getenv('VAPID_PUBLIC_KEY') ?? '';
$vapidPrivate = $_ENV['VAPID_PRIVATE_KEY'] ?? getenv('VAPID_PRIVATE_KEY') ?? '';
$vapidNastaveny = !empty($vapidPublic) && !empty($vapidPrivate);

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Composer & Web Push Setup - WGS</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        h1 {
            color: #222;
            border-bottom: 3px solid #333;
            padding-bottom: 15px;
            margin-top: 0;
        }
        h2 { color: #444; margin-top: 30px; }
        .success {
            background: #e8e8e8;
            border: 1px solid #ccc;
            color: #222;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .error {
            background: #f5f5f5;
            border: 2px solid #333;
            color: #222;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .warning {
            background: #fafafa;
            border-left: 4px solid #666;
            color: #333;
            padding: 15px;
            border-radius: 0 8px 8px 0;
            margin: 15px 0;
        }
        .info {
            background: #f9f9f9;
            border: 1px solid #ddd;
            color: #444;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 15px 10px 15px 0;
            font-size: 1rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
        }
        .btn:hover { background: #555; }
        .btn-secondary { background: #777; }
        .btn-secondary:hover { background: #999; }
        .btn-disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .status-box {
            background: #fafafa;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .status-box h3 { margin-top: 0; color: #333; }
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .status-item:last-child { border-bottom: none; }
        .status-ok { color: #333; font-weight: bold; }
        .status-ok::before { content: "[OK] "; }
        .status-fail { color: #666; font-weight: bold; }
        .status-fail::before { content: "[X] "; }
        pre {
            background: #1a1a1a;
            color: #eee;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 0.85rem;
            line-height: 1.5;
            max-height: 400px;
            overflow-y: auto;
        }
        code {
            background: #eee;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Monaco', 'Menlo', monospace;
        }
        .instructions {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 25px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .instructions h3 { margin-top: 0; }
        .instructions ol { padding-left: 20px; }
        .instructions li { margin: 12px 0; line-height: 1.6; }
        .step-number {
            display: inline-block;
            width: 28px;
            height: 28px;
            background: #333;
            color: white;
            text-align: center;
            line-height: 28px;
            border-radius: 50%;
            margin-right: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Composer & Web Push Setup</h1>

    <?php if ($chyba): ?>
        <div class="error"><strong>CHYBA:</strong> <?php echo htmlspecialchars($chyba); ?></div>
    <?php endif; ?>

    <?php if ($uspech): ?>
        <div class="success"><strong>OK:</strong> Composer update uspesne dokoncen!</div>
    <?php endif; ?>

    <?php if ($vystup): ?>
        <h2>Vystup</h2>
        <pre><?php echo htmlspecialchars($vystup); ?></pre>
    <?php endif; ?>

    <!-- STAV SYSTEMU -->
    <div class="status-box">
        <h3>Stav systemu:</h3>

        <div class="status-item">
            <span>Shell funkce (shell_exec)</span>
            <span class="<?php echo $shellDostupny ? 'status-ok' : 'status-fail'; ?>">
                <?php echo $shellDostupny ? 'Povoleno' : 'Zakazano hostingem'; ?>
            </span>
        </div>

        <div class="status-item">
            <span>composer.json</span>
            <span class="<?php echo $composerExistuje ? 'status-ok' : 'status-fail'; ?>">
                <?php echo $composerExistuje ? 'Existuje' : 'Chybi'; ?>
            </span>
        </div>

        <div class="status-item">
            <span>Slozka vendor/</span>
            <span class="<?php echo $vendorExistuje ? 'status-ok' : 'status-fail'; ?>">
                <?php echo $vendorExistuje ? 'Existuje' : 'Chybi'; ?>
            </span>
        </div>

        <div class="status-item">
            <span>vendor/autoload.php</span>
            <span class="<?php echo $autoloadExistuje ? 'status-ok' : 'status-fail'; ?>">
                <?php echo $autoloadExistuje ? 'Existuje' : 'Chybi'; ?>
            </span>
        </div>

        <div class="status-item">
            <span>minishlink/web-push knihovna</span>
            <span class="<?php echo $webPushNainstalovano ? 'status-ok' : 'status-fail'; ?>">
                <?php echo $webPushNainstalovano ? 'Nainstalovano' : 'Neni nainstalovano'; ?>
            </span>
        </div>

        <div class="status-item">
            <span>VAPID klice (.env)</span>
            <span class="<?php echo $vapidNastaveny ? 'status-ok' : 'status-fail'; ?>">
                <?php echo $vapidNastaveny ? 'Nastaveny' : 'Chybi'; ?>
            </span>
        </div>
    </div>

    <?php if (!$shellDostupny): ?>
    <!-- INSTRUKCE PRO HOSTING BEZ SHELL -->
    <div class="warning">
        <strong>Shell funkce jsou na tomto hostingu zakazany.</strong><br>
        Composer nelze spustit primo z PHP. Pouzijte jednu z alternativ nize.
    </div>

    <div class="instructions">
        <h3>Varianta A: SSH pristup (doporuceno)</h3>
        <p>Pokud mate SSH pristup k serveru:</p>
        <ol>
            <li>Pripojte se pres SSH: <code>ssh uzivatel@server</code></li>
            <li>Prejdete do adresare projektu: <code>cd /cesta/k/wgs-service.cz/www</code></li>
            <li>Spustte: <code>composer update</code></li>
        </ol>
    </div>

    <div class="instructions">
        <h3>Varianta B: FTP upload vendor slozky</h3>
        <p>Pokud nemate SSH:</p>
        <ol>
            <li>Na lokalnim PC nainstalujte <a href="https://getcomposer.org" target="_blank">Composer</a></li>
            <li>Stahnte <code>composer.json</code> a <code>composer.lock</code> z projektu</li>
            <li>Spustte lokalne: <code>composer install --no-dev</code></li>
            <li>Nahrajte celou slozku <code>vendor/</code> na server pres FTP</li>
        </ol>
    </div>

    <div class="instructions">
        <h3>Varianta C: Pozadat hosting o povoleni</h3>
        <p>Kontaktujte podporu hostingu a pozadejte o:</p>
        <ul>
            <li>Povoleni funkce <code>shell_exec()</code> nebo <code>exec()</code></li>
            <li>Nebo SSH pristup k serveru</li>
        </ul>
    </div>

    <?php else: ?>

    <!-- SHELL JE DOSTUPNY -->
    <?php if (!$webPushNainstalovano): ?>
        <div class="warning">
            <strong>Knihovna web-push neni nainstalovana.</strong><br>
            Kliknete na tlacitko pro spusteni composer update.
        </div>
        <a href="?execute=1" class="btn">Spustit Composer Update</a>
    <?php else: ?>
        <div class="success">
            <strong>Vsechny zavislosti jsou nainstalovany!</strong>
        </div>
        <a href="?execute=1" class="btn btn-secondary">Znovu spustit Composer Update</a>
    <?php endif; ?>

    <?php endif; ?>

    <!-- VAPID KLICE -->
    <?php if (!$vapidNastaveny): ?>
    <h2>VAPID klice</h2>
    <div class="warning">
        <strong>VAPID klice nejsou nastaveny v .env souboru.</strong>
    </div>
    <div class="instructions">
        <h3>Jak nastavit VAPID klice:</h3>
        <ol>
            <li>Spustte <a href="setup_web_push.php">setup_web_push.php</a> pro vygenerovani klicu</li>
            <li>Pridejte vygenerovane klice do <code>.env</code> souboru:
<pre>VAPID_PUBLIC_KEY=vygenerovany_public_klic
VAPID_PRIVATE_KEY=vygenerovany_private_klic
VAPID_SUBJECT=mailto:vas@email.cz</pre>
            </li>
        </ol>
    </div>
    <?php else: ?>
    <div class="success">
        <strong>VAPID klice jsou nastaveny.</strong>
    </div>
    <?php endif; ?>

    <h2>Dalsi kroky</h2>
    <div class="info">
        <ol>
            <li><a href="setup_web_push.php">setup_web_push.php</a> - Generovani/kontrola VAPID klicu</li>
            <li><a href="pridej_push_subscriptions_tabulku.php">pridej_push_subscriptions_tabulku.php</a> - Kontrola DB tabulek</li>
            <li>Po nastaveni otestujte push notifikace v aplikaci</li>
        </ol>
    </div>

    <a href="/admin.php" class="btn btn-secondary">Zpet do Admin</a>
</div>
</body>
</html>
