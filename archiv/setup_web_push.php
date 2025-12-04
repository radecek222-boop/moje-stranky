<?php
/**
 * Setup Web Push - Generování VAPID klíčů
 *
 * Tento skript vygeneruje VAPID klíče pro Web Push notifikace
 * a přidá je do .env souboru.
 *
 * SPOUŠTĚNÍ:
 * 1. Ujistěte se, že jste admin
 * 2. Otevřete: https://www.wgs-service.cz/setup_web_push.php
 * 3. Klikněte na "Generovat VAPID klíče"
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit tento setup.");
}

// Kontrola zda uz jsou klice v .env
function zkontrolovatVapidKlice() {
    $envSoubor = __DIR__ . '/.env';

    if (!file_exists($envSoubor)) {
        return ['existuje' => false, 'public' => null, 'private' => null];
    }

    $obsah = file_get_contents($envSoubor);

    preg_match('/VAPID_PUBLIC_KEY=(.+)/', $obsah, $publicMatch);
    preg_match('/VAPID_PRIVATE_KEY=(.+)/', $obsah, $privateMatch);

    $hasPublic = !empty($publicMatch[1]);
    $hasPrivate = !empty($privateMatch[1]);

    return [
        'existuje' => $hasPublic && $hasPrivate,
        'public' => $hasPublic ? substr($publicMatch[1], 0, 30) . '...' : null,
        'private' => $hasPrivate ? substr($privateMatch[1], 0, 30) . '...' : null
    ];
}

// Vygenerovat VAPID klice pomoci OpenSSL
function vygenerovratVapidKlice() {
    // VAPID klice jsou EC keypair (Elliptic Curve P-256)

    // Vygenerovat private key
    $privateKey = openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);

    if (!$privateKey) {
        throw new Exception('Chyba generování private key: ' . openssl_error_string());
    }

    // Export private key jako PEM
    openssl_pkey_export($privateKey, $pemPrivate);

    // Ziskat public key
    $details = openssl_pkey_get_details($privateKey);
    $pemPublic = $details['key'];

    // Konverze PEM na base64url format (VAPID standard)
    $privateBase64 = rtrim(strtr(base64_encode(base64_decode(preg_replace(['/\r/', '/\n/', '/-----.*?-----/'], '', $pemPrivate))), '+/', '-_'), '=');
    $publicBase64 = rtrim(strtr(base64_encode(base64_decode(preg_replace(['/\r/', '/\n/', '/-----.*?-----/'], '', $pemPublic))), '+/', '-_'), '=');

    return [
        'public' => $publicBase64,
        'private' => $privateBase64
    ];
}

// Ulozit VAPID klice do .env
function ulozitVapidDoEnv($publicKey, $privateKey) {
    $envSoubor = __DIR__ . '/.env';

    if (!file_exists($envSoubor)) {
        throw new Exception('.env soubor nenalezen');
    }

    $obsah = file_get_contents($envSoubor);

    // Odstranit existujici VAPID klice
    $obsah = preg_replace('/VAPID_PUBLIC_KEY=.*\n?/', '', $obsah);
    $obsah = preg_replace('/VAPID_PRIVATE_KEY=.*\n?/', '', $obsah);
    $obsah = preg_replace('/VAPID_SUBJECT=.*\n?/', '', $obsah);

    // Pridat nove klice na konec souboru
    $obsah = trim($obsah) . "\n\n# Web Push VAPID Keys (vygenerovano " . date('Y-m-d H:i:s') . ")\n";
    $obsah .= "VAPID_PUBLIC_KEY={$publicKey}\n";
    $obsah .= "VAPID_PRIVATE_KEY={$privateKey}\n";
    $obsah .= "VAPID_SUBJECT=mailto:reklamace@wgs-service.cz\n";

    file_put_contents($envSoubor, $obsah);

    return true;
}

$akce = $_GET['akce'] ?? '';
$stavKlicu = zkontrolovatVapidKlice();

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Web Push - WGS</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
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
            color: #000;
            border-bottom: 3px solid #000;
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
            background: #000;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn:hover {
            background: #333;
        }
        .btn-secondary {
            background: #666;
        }
        code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        pre {
            background: #f8f8f8;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border: 1px solid #ddd;
        }
        .key-display {
            background: #f8f8f8;
            padding: 10px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            word-break: break-all;
            margin: 10px 0;
        }
    </style>
</head>
<body>
<div class="container">

<?php
if ($akce === 'generovat') {
    echo "<h1>Generování VAPID klíčů</h1>";

    try {
        $klice = vygenerovratVapidKlice();
        ulozitVapidDoEnv($klice['public'], $klice['private']);

        echo "<div class='success'>";
        echo "<strong>✓ VAPID KLÍČE ÚSPĚŠNĚ VYGENEROVÁNY</strong><br><br>";
        echo "Klíče byly uloženy do .env souboru.";
        echo "</div>";

        echo "<h2>Vygenerované klíče</h2>";
        echo "<p><strong>Public Key:</strong></p>";
        echo "<div class='key-display'>{$klice['public']}</div>";

        echo "<p><strong>Private Key:</strong></p>";
        echo "<div class='key-display'>{$klice['private']}</div>";

        echo "<div class='info'>";
        echo "<strong>Co dál?</strong><br>";
        echo "1. VAPID klíče jsou teď nakonfigurovány<br>";
        echo "2. Spusťte <code>composer install</code> pro instalaci web-push knihovny<br>";
        echo "3. Push notifikace budou fungovat po nahrání na server";
        echo "</div>";

        echo "<a href='admin.php' class='btn'>← Zpět do Admin Panelu</a>";

    } catch (Exception $e) {
        echo "<div class='error'>";
        echo "<strong>CHYBA:</strong><br>";
        echo htmlspecialchars($e->getMessage());
        echo "</div>";
        echo "<a href='setup_web_push.php' class='btn btn-secondary'>Zkusit znovu</a>";
    }

} else {
    // Zobrazit status a moznost generovat
    echo "<h1>Setup Web Push Notifikací</h1>";

    echo "<div class='info'>";
    echo "<strong>O Web Push notifikacích:</strong><br>";
    echo "Web Push umožňuje posílat notifikace do prohlížeče i když uživatel nemá web otevřený.<br>";
    echo "Funguje na iOS 16.4+ (PWA), Android a desktopu.";
    echo "</div>";

    echo "<h2>1. Aktuální stav VAPID klíčů</h2>";

    if ($stavKlicu['existuje']) {
        echo "<div class='success'>";
        echo "<strong>✓ VAPID klíče jsou nakonfigurovány</strong><br><br>";
        echo "Public Key: <code>{$stavKlicu['public']}</code><br>";
        echo "Private Key: <code>{$stavKlicu['private']}</code>";
        echo "</div>";

        echo "<div class='warning'>";
        echo "<strong>⚠️ UPOZORNĚNÍ:</strong><br>";
        echo "Pokud vygenerujete nové klíče, všichni uživatelé se budou muset znovu přihlásit k odběru notifikací.";
        echo "</div>";

        echo "<a href='?akce=generovat' class='btn btn-secondary'>Vygenerovat nové klíče</a>";

    } else {
        echo "<div class='warning'>";
        echo "<strong>⚠️ VAPID klíče nejsou nakonfigurovány</strong><br><br>";
        echo "Push notifikace nebudou fungovat, dokud nevygenerujete VAPID klíče.";
        echo "</div>";

        echo "<a href='?akce=generovat' class='btn'>Vygenerovat VAPID klíče</a>";
    }

    echo "<h2>2. Composer knihovna</h2>";

    $vendorExists = file_exists(__DIR__ . '/vendor/autoload.php');

    if ($vendorExists) {
        echo "<div class='success'>";
        echo "<strong>✓ Composer vendor složka existuje</strong><br>";
        echo "Knihovna minishlink/web-push je pravděpodobně nainstalovaná.";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "<strong>⚠️ Composer vendor složka nenalezena</strong><br><br>";
        echo "Musíte spustit: <code>composer install</code>";
        echo "</div>";

        echo "<h3>Instalace composer balíčků</h3>";
        echo "<pre>cd " . __DIR__ . "\ncomposer install</pre>";
    }

    echo "<h2>3. Databázová tabulka</h2>";

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_push_subscriptions'");
        $tabulkaExistuje = $stmt->rowCount() > 0;

        if ($tabulkaExistuje) {
            echo "<div class='success'>";
            echo "<strong>✓ Tabulka wgs_push_subscriptions existuje</strong>";
            echo "</div>";

            // Pocet subscriptions
            $stmt = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_push_subscriptions");
            $pocet = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];

            echo "<p>Počet registrovaných zařízení: <strong>{$pocet}</strong></p>";

        } else {
            echo "<div class='warning'>";
            echo "<strong>⚠️ Tabulka wgs_push_subscriptions neexistuje</strong><br><br>";
            echo "Spusťte migraci: <code>migrations/create_push_subscriptions_table.sql</code>";
            echo "</div>";
        }

    } catch (Exception $e) {
        echo "<div class='error'>";
        echo "Chyba připojení k databázi: " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }

    echo "<hr style='margin: 30px 0;'>";
    echo "<a href='admin.php' class='btn btn-secondary'>← Zpět do Admin Panelu</a>";
}
?>

</div>
</body>
</html>
