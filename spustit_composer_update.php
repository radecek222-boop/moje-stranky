<?php
/**
 * Composer Update - PHP interface
 *
 * Tento skript spusti composer update na serveru.
 * Pristup pouze pro admina.
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit tento skript.");
}

$vystup = '';
$uspech = false;
$chyba = '';

// Zjistit cestu k composeru
function najdiComposer() {
    $moznosti = [
        'composer',
        'composer.phar',
        '/usr/local/bin/composer',
        '/usr/bin/composer',
        __DIR__ . '/composer.phar',
        '~/composer.phar'
    ];

    foreach ($moznosti as $cesta) {
        $test = shell_exec("which $cesta 2>/dev/null");
        if ($test) {
            return trim($test);
        }

        // Zkusit primo
        if (file_exists($cesta)) {
            return $cesta;
        }
    }

    return null;
}

// Spustit composer update
if (isset($_GET['execute']) && $_GET['execute'] === '1') {
    $composerPath = najdiComposer();

    if (!$composerPath) {
        // Pokusit se stahnout composer.phar
        $chyba = "Composer nenalezen. Stahuji composer.phar...";

        $pharUrl = 'https://getcomposer.org/download/latest-stable/composer.phar';
        $pharPath = __DIR__ . '/composer.phar';

        $pharContent = @file_get_contents($pharUrl);
        if ($pharContent && file_put_contents($pharPath, $pharContent)) {
            chmod($pharPath, 0755);
            $composerPath = 'php ' . $pharPath;
            $chyba = '';
        } else {
            $chyba = "Nelze stahnout composer.phar. Nahrajte jej rucne do " . __DIR__;
        }
    }

    if ($composerPath && !$chyba) {
        // Zmenit adresar na root projektu
        $projektDir = __DIR__;

        // Sestavit prikaz
        if (strpos($composerPath, '.phar') !== false && strpos($composerPath, 'php ') !== 0) {
            $prikaz = "cd $projektDir && php $composerPath update 2>&1";
        } else {
            $prikaz = "cd $projektDir && $composerPath update 2>&1";
        }

        // Spustit
        $vystup = shell_exec($prikaz);

        if ($vystup) {
            // Kontrola zda instalace probehla uspesne
            if (strpos($vystup, 'Nothing to install') !== false ||
                strpos($vystup, 'Generating autoload') !== false ||
                strpos($vystup, 'Installing') !== false ||
                strpos($vystup, 'Updating') !== false) {
                $uspech = true;
            }
        } else {
            $chyba = "Prikaz nevratil zadny vystup. Zkontrolujte prava.";
        }
    }
}

// Kontrola stavu
$composerExistuje = file_exists(__DIR__ . '/composer.json');
$vendorExistuje = is_dir(__DIR__ . '/vendor');
$webPushNainstalovano = file_exists(__DIR__ . '/vendor/minishlink/web-push/src/WebPush.php');
$autoloadExistuje = file_exists(__DIR__ . '/vendor/autoload.php');

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Composer Update - WGS</title>
    <style>
        * {
            box-sizing: border-box;
        }
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
        h2 {
            color: #444;
            margin-top: 30px;
        }
        .success {
            background: #e8e8e8;
            border: 1px solid #ccc;
            color: #222;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .success::before {
            content: "OK: ";
            font-weight: bold;
        }
        .error {
            background: #f5f5f5;
            border: 2px solid #333;
            color: #222;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .error::before {
            content: "CHYBA: ";
            font-weight: bold;
        }
        .info {
            background: #f9f9f9;
            border: 1px solid #ddd;
            color: #444;
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
            transition: background 0.3s;
        }
        .btn:hover {
            background: #555;
        }
        .btn-secondary {
            background: #777;
        }
        .btn-secondary:hover {
            background: #999;
        }
        .status-box {
            background: #fafafa;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .status-box h3 {
            margin-top: 0;
            color: #333;
        }
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .status-item:last-child {
            border-bottom: none;
        }
        .status-ok {
            color: #333;
            font-weight: bold;
        }
        .status-ok::before {
            content: "[OK] ";
        }
        .status-fail {
            color: #666;
            font-weight: bold;
        }
        .status-fail::before {
            content: "[X] ";
        }
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
        .next-steps {
            background: #fafafa;
            border-left: 4px solid #333;
            padding: 20px;
            margin: 20px 0;
        }
        .next-steps h3 {
            margin-top: 0;
        }
        .next-steps ol {
            margin-bottom: 0;
            padding-left: 20px;
        }
        .next-steps li {
            margin: 10px 0;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Composer Update</h1>

    <?php if ($chyba): ?>
        <div class="error"><?php echo htmlspecialchars($chyba); ?></div>
    <?php endif; ?>

    <?php if ($uspech): ?>
        <div class="success">
            <strong>Composer update uspesne dokoncen!</strong>
        </div>
    <?php endif; ?>

    <?php if ($vystup): ?>
        <h2>Vystup prikazu</h2>
        <pre><?php echo htmlspecialchars($vystup); ?></pre>
    <?php endif; ?>

    <div class="status-box">
        <h3>Aktualni stav:</h3>

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
            <span>minishlink/web-push</span>
            <span class="<?php echo $webPushNainstalovano ? 'status-ok' : 'status-fail'; ?>">
                <?php echo $webPushNainstalovano ? 'Nainstalovano' : 'Neni nainstalovano'; ?>
            </span>
        </div>
    </div>

    <?php if (!$webPushNainstalovano): ?>
        <div class="warning">
            <strong>Knihovna web-push neni nainstalovana.</strong><br>
            Kliknete na tlacitko nize pro spusteni composer update.
        </div>

        <a href="?execute=1" class="btn">Spustit Composer Update</a>
    <?php else: ?>
        <div class="success">
            <strong>Web Push knihovna je nainstalovana a pripravena k pouziti!</strong>
        </div>

        <div class="next-steps">
            <h3>Dalsi kroky:</h3>
            <ol>
                <li><a href="setup_web_push.php">Zkontrolovat VAPID klice</a></li>
                <li><a href="pridej_push_subscriptions_tabulku.php">Zkontrolovat DB tabulky</a></li>
                <li>Otestovat push notifikace v aplikaci</li>
            </ol>
        </div>

        <a href="?execute=1" class="btn btn-secondary">Znovu spustit Composer Update</a>
    <?php endif; ?>

    <h2>Informace</h2>
    <div class="info">
        <strong>Co tento skript dela:</strong><br>
        1. Najde nebo stahne composer.phar<br>
        2. Spusti <code>composer update</code> v adresari projektu<br>
        3. Nainstaluje vsechny zavislosti z composer.json<br>
        4. Vygeneruje autoload soubory
    </div>

    <a href="/admin.php" class="btn btn-secondary">Zpet do Admin</a>
</div>
</body>
</html>
