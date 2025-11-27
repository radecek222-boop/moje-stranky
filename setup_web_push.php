<?php
/**
 * Setup Web Push - Automatická konfigurace VAPID klíčů
 *
 * Tento skript:
 * 1. Zkontroluje závislosti (composer, knihovna web-push)
 * 2. Vygeneruje VAPID klíče
 * 3. Automaticky je přidá do .env souboru
 *
 * POUŽITÍ: Otevřete v prohlížeči a klikněte na tlačítko
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit setup.");
}

$envSoubor = __DIR__ . '/.env';
$zpravy = [];
$vapidVePrivate = '';
$vapidVePublic = '';
$uspech = false;

// Kontrola a generování klíčů
if (isset($_GET['generovat']) && $_GET['generovat'] === '1') {

    // Kontrola knihovny web-push
    if (!class_exists('Minishlink\WebPush\VAPID')) {
        // Zkusit autoload
        $autoloadSoubor = __DIR__ . '/vendor/autoload.php';
        if (file_exists($autoloadSoubor)) {
            require_once $autoloadSoubor;
        }
    }

    if (class_exists('Minishlink\WebPush\VAPID')) {
        // Generovat VAPID klíče pomocí knihovny
        try {
            $klice = \Minishlink\WebPush\VAPID::createVapidKeys();
            $vapidVePublic = $klice['publicKey'];
            $vapidVePrivate = $klice['privateKey'];
            $zpravy[] = ['typ' => 'success', 'text' => 'VAPID klice uspesne vygenerovany pomoci knihovny web-push'];
        } catch (Exception $e) {
            $zpravy[] = ['typ' => 'error', 'text' => 'Chyba pri generovani klicu: ' . $e->getMessage()];
        }
    } else {
        // Fallback - generovat pomocí openssl
        if (extension_loaded('openssl')) {
            try {
                // Generovat EC klíč
                $config = [
                    'curve_name' => 'prime256v1',
                    'private_key_type' => OPENSSL_KEYTYPE_EC,
                ];

                $klicRes = openssl_pkey_new($config);
                if ($klicRes === false) {
                    throw new Exception('Nelze vygenerovat EC klic');
                }

                $detaily = openssl_pkey_get_details($klicRes);

                // Získat raw klíče
                $privateKeyRaw = $detaily['ec']['d'];
                $publicKeyX = $detaily['ec']['x'];
                $publicKeyY = $detaily['ec']['y'];

                // Public key = 0x04 + X + Y (uncompressed point format)
                $publicKeyRaw = chr(4) . $publicKeyX . $publicKeyY;

                // Base64url encoding
                $vapidVePrivate = rtrim(strtr(base64_encode($privateKeyRaw), '+/', '-_'), '=');
                $vapidVePublic = rtrim(strtr(base64_encode($publicKeyRaw), '+/', '-_'), '=');

                $zpravy[] = ['typ' => 'success', 'text' => 'VAPID klice uspesne vygenerovany pomoci OpenSSL'];
            } catch (Exception $e) {
                $zpravy[] = ['typ' => 'error', 'text' => 'Chyba OpenSSL: ' . $e->getMessage()];
            }
        } else {
            $zpravy[] = ['typ' => 'error', 'text' => 'Neni dostupna knihovna web-push ani OpenSSL extension'];
        }
    }

    // Uložit do .env pokud máme klíče
    if (!empty($vapidVePrivate) && !empty($vapidVePublic)) {
        if (file_exists($envSoubor)) {
            $envObsah = file_get_contents($envSoubor);

            // Kontrola zda už VAPID klíče existují
            if (strpos($envObsah, 'VAPID_PUBLIC_KEY=') !== false) {
                // Aktualizovat existující
                $envObsah = preg_replace('/VAPID_PUBLIC_KEY=.*/', 'VAPID_PUBLIC_KEY=' . $vapidVePublic, $envObsah);
                $envObsah = preg_replace('/VAPID_PRIVATE_KEY=.*/', 'VAPID_PRIVATE_KEY=' . $vapidVePrivate, $envObsah);
                $zpravy[] = ['typ' => 'info', 'text' => 'Existujici VAPID klice byly aktualizovany'];
            } else {
                // Přidat nové
                $pridatText = "\n# ========================================\n";
                $pridatText .= "# WEB PUSH NOTIFICATIONS (VAPID)\n";
                $pridatText .= "# ========================================\n";
                $pridatText .= "VAPID_PUBLIC_KEY=" . $vapidVePublic . "\n";
                $pridatText .= "VAPID_PRIVATE_KEY=" . $vapidVePrivate . "\n";
                $pridatText .= "VAPID_SUBJECT=mailto:info@wgs-service.cz\n";

                $envObsah .= $pridatText;
                $zpravy[] = ['typ' => 'info', 'text' => 'VAPID klice pridany na konec .env souboru'];
            }

            // Zapsat zpět
            if (file_put_contents($envSoubor, $envObsah) !== false) {
                $zpravy[] = ['typ' => 'success', 'text' => '.env soubor uspesne ulozen'];
                $uspech = true;
            } else {
                $zpravy[] = ['typ' => 'error', 'text' => 'Nelze zapsat do .env souboru - zkontrolujte opravneni'];
            }
        } else {
            $zpravy[] = ['typ' => 'error', 'text' => '.env soubor neexistuje'];
        }
    }
}

// Zkontrolovat aktuální stav
$maKlice = false;
$aktualniPublic = '';
$aktualniPrivate = '';

if (file_exists($envSoubor)) {
    $envObsah = file_get_contents($envSoubor);
    if (preg_match('/VAPID_PUBLIC_KEY=(.+)/', $envObsah, $matches)) {
        $aktualniPublic = trim($matches[1]);
    }
    if (preg_match('/VAPID_PRIVATE_KEY=(.+)/', $envObsah, $matches)) {
        $aktualniPrivate = trim($matches[1]);
    }
    $maKlice = !empty($aktualniPublic) && !empty($aktualniPrivate);
}

// Kontrola knihovny
$maKnihovnu = false;
$autoloadSoubor = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadSoubor)) {
    require_once $autoloadSoubor;
    $maKnihovnu = class_exists('Minishlink\WebPush\WebPush');
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Web Push - WGS</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
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
        .warning {
            background: #fafafa;
            border: 1px dashed #666;
            color: #333;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .warning::before {
            content: "POZOR: ";
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
            font-size: 0.9rem;
        }
        code {
            background: #eee;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Consolas', monospace;
        }
        .key-display {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 8px;
            word-break: break-all;
            font-family: 'Consolas', monospace;
            font-size: 0.85rem;
            margin: 10px 0;
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
    <h1>Setup Web Push Notifikaci</h1>

    <?php foreach ($zpravy as $zprava): ?>
        <div class="<?php echo htmlspecialchars($zprava['typ']); ?>">
            <?php echo htmlspecialchars($zprava['text']); ?>
        </div>
    <?php endforeach; ?>

    <?php if ($uspech && !empty($vapidVePublic)): ?>
        <div class="success">
            <strong>VAPID klice byly uspesne vygenerovany a ulozeny!</strong>
        </div>

        <h3>Vygenerovane klice:</h3>
        <p><strong>Public Key (pro frontend):</strong></p>
        <div class="key-display"><?php echo htmlspecialchars($vapidVePublic); ?></div>

        <p><strong>Private Key (pouze backend):</strong></p>
        <div class="key-display"><?php echo htmlspecialchars($vapidVePrivate); ?></div>

        <div class="next-steps">
            <h3>Dalsi kroky:</h3>
            <ol>
                <li>Spustte <a href="pridej_push_subscriptions_tabulku.php">migracni skript pro databazi</a></li>
                <li>Spustte <code>composer update</code> na serveru (pokud jeste neni)</li>
                <li>Otestujte notifikace v PWA aplikaci</li>
            </ol>
        </div>
    <?php endif; ?>

    <div class="status-box">
        <h3>Aktualni stav:</h3>

        <div class="status-item">
            <span>Knihovna web-push</span>
            <span class="<?php echo $maKnihovnu ? 'status-ok' : 'status-fail'; ?>">
                <?php echo $maKnihovnu ? 'Nainstalovana' : 'Chybi - spustte composer update'; ?>
            </span>
        </div>

        <div class="status-item">
            <span>VAPID klice v .env</span>
            <span class="<?php echo $maKlice ? 'status-ok' : 'status-fail'; ?>">
                <?php echo $maKlice ? 'Nakonfigurovany' : 'Chybi'; ?>
            </span>
        </div>

        <?php if ($maKlice): ?>
        <div class="status-item">
            <span>Public Key</span>
            <span style="font-size: 0.8rem; max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                <?php echo htmlspecialchars(substr($aktualniPublic, 0, 30) . '...'); ?>
            </span>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!$maKnihovnu): ?>
        <div class="warning">
            Knihovna <code>minishlink/web-push</code> neni nainstalovana. Spustte na serveru:
        </div>
        <pre>cd /cesta/k/projektu
composer update</pre>
        <p>Skript bude fungovat i bez knihovny (pouzije OpenSSL), ale pro odesilani push zprav je knihovna potreba.</p>
    <?php endif; ?>

    <?php if (!$maKlice || !$uspech): ?>
        <a href="?generovat=1" class="btn">Generovat VAPID Klice</a>
    <?php else: ?>
        <a href="?generovat=1" class="btn btn-secondary">Pregenerovat Klice</a>
    <?php endif; ?>

    <a href="pridej_push_subscriptions_tabulku.php" class="btn btn-secondary">Vytvorit DB Tabulku</a>
    <a href="/admin.php" class="btn btn-secondary">Zpet do Admin</a>
</div>
</body>
</html>
