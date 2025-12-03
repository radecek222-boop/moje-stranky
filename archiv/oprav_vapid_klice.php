<?php
/**
 * Oprava VAPID klicu - konverze z PEM/ASN.1 na raw base64url format
 *
 * Problem: setup_web_push.php vygeneroval klice v SPKI formatu (MFkw...),
 * ale minishlink/web-push ocekava raw EC klice (65 bajtu).
 */

require_once __DIR__ . '/init.php';

// Admin check
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze admin.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Oprava VAPID klicu</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #222; border-bottom: 3px solid #222; padding-bottom: 10px; }
        .ok { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .chyba { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .info { background: #e2e3e5; border: 1px solid #d6d8db; color: #383d41; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .varovani { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; }
        code { background: #f4f4f4; padding: 3px 8px; border-radius: 3px; font-family: 'Consolas', monospace; font-size: 13px; }
        pre { background: #2d2d2d; color: #f8f8f2; padding: 20px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        .btn { display: inline-block; padding: 12px 24px; background: #222; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #444; }
        .btn-danger { background: #721c24; }
        .btn-danger:hover { background: #a02530; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #f5f5f5; }
        .key-box { background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-radius: 5px; margin: 10px 0; word-break: break-all; font-family: monospace; font-size: 11px; }
    </style>
</head>
<body>
<div class='container'>
<h1>Oprava VAPID klicu</h1>";

// Nacteni aktualniho klice
$currentPublic = $_ENV['VAPID_PUBLIC_KEY'] ?? getenv('VAPID_PUBLIC_KEY') ?? '';
$currentPrivate = $_ENV['VAPID_PRIVATE_KEY'] ?? getenv('VAPID_PRIVATE_KEY') ?? '';

echo "<h2>1. Aktualni stav</h2>";

echo "<table>
<tr><th>Klic</th><th>Delka</th><th>Format</th><th>Zacatek</th></tr>
<tr>
    <td>VAPID_PUBLIC_KEY</td>
    <td>" . strlen($currentPublic) . " znaku</td>
    <td>" . (strpos($currentPublic, 'MFkw') === 0 ? '<span style=\"color:red\">SPATNY (SPKI/ASN.1)</span>' : '<span style=\"color:green\">Spravny (raw)</span>') . "</td>
    <td><code>" . substr($currentPublic, 0, 30) . "...</code></td>
</tr>
<tr>
    <td>VAPID_PRIVATE_KEY</td>
    <td>" . strlen($currentPrivate) . " znaku</td>
    <td>" . (strlen($currentPrivate) > 50 ? '<span style=\"color:red\">SPATNY (SPKI/ASN.1)</span>' : '<span style=\"color:green\">Spravny (raw)</span>') . "</td>
    <td><code>" . substr($currentPrivate, 0, 30) . "...</code></td>
</tr>
</table>";

// Detekce problemu
$jeSpkiFormat = strpos($currentPublic, 'MFkw') === 0;

if ($jeSpkiFormat) {
    echo "<div class='chyba'>
        <strong>PROBLEM:</strong> Vase VAPID klice jsou ve formatu SPKI/ASN.1.<br>
        Knihovna minishlink/web-push vyzaduje raw base64url format.<br><br>
        <strong>Reseni:</strong> Vygenerovat nove klice ve spravnem formatu.
    </div>";
}

// ============================================
// GENEROVANI NOVYCH KLICU
// ============================================

echo "<h2>2. Generovani novych VAPID klicu</h2>";

// Kontrola OpenSSL
if (!extension_loaded('openssl')) {
    echo "<div class='chyba'>OpenSSL extension neni nactena!</div>";
    exit;
}

// Funkce pro generovani VAPID klicu ve spravnem formatu
function generateVapidKeys() {
    // Vytvorit EC key pair
    $config = [
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ];

    $key = openssl_pkey_new($config);
    if (!$key) {
        throw new Exception('Nelze vygenerovat EC klice: ' . openssl_error_string());
    }

    // Ziskat detaily
    $details = openssl_pkey_get_details($key);

    // Ziskat private key (d parameter) - 32 bajtu
    $privateKeyRaw = $details['ec']['d'];

    // Ziskat public key (x a y koordinaty) - 32 + 32 = 64 bajtu + 0x04 prefix = 65 bajtu
    $x = $details['ec']['x'];
    $y = $details['ec']['y'];

    // Public key format: 0x04 || x || y (uncompressed point)
    $publicKeyRaw = chr(0x04) . $x . $y;

    // Konverze na base64url
    $publicKeyBase64url = rtrim(strtr(base64_encode($publicKeyRaw), '+/', '-_'), '=');
    $privateKeyBase64url = rtrim(strtr(base64_encode($privateKeyRaw), '+/', '-_'), '=');

    return [
        'publicKey' => $publicKeyBase64url,
        'privateKey' => $privateKeyBase64url,
        'publicKeyLength' => strlen($publicKeyRaw),
        'privateKeyLength' => strlen($privateKeyRaw)
    ];
}

// Generovat nove klice
try {
    $newKeys = generateVapidKeys();

    echo "<div class='ok'>
        <strong>Nove klice vygenerovany uspesne!</strong><br>
        Public key: " . $newKeys['publicKeyLength'] . " bajtu (spravne: 65)<br>
        Private key: " . $newKeys['privateKeyLength'] . " bajtu (spravne: 32)
    </div>";

    echo "<h3>Novy VAPID_PUBLIC_KEY:</h3>";
    echo "<div class='key-box'>" . htmlspecialchars($newKeys['publicKey']) . "</div>";

    echo "<h3>Novy VAPID_PRIVATE_KEY:</h3>";
    echo "<div class='key-box'>" . htmlspecialchars($newKeys['privateKey']) . "</div>";

    // ============================================
    // AUTOMATICKA AKTUALIZACE .env
    // ============================================

    echo "<h2>3. Aktualizace .env souboru</h2>";

    $envFile = __DIR__ . '/.env';

    if (isset($_GET['aktualizovat']) && $_GET['aktualizovat'] === '1') {
        // Nacist .env
        if (!file_exists($envFile)) {
            echo "<div class='chyba'>.env soubor neexistuje!</div>";
        } else {
            $envContent = file_get_contents($envFile);

            // Ziskat klice z POST (nebo pouzit nove)
            $newPublic = $_POST['new_public'] ?? $newKeys['publicKey'];
            $newPrivate = $_POST['new_private'] ?? $newKeys['privateKey'];

            // Nahradit klice
            $envContent = preg_replace(
                '/^VAPID_PUBLIC_KEY=.*/m',
                'VAPID_PUBLIC_KEY=' . $newPublic,
                $envContent
            );
            $envContent = preg_replace(
                '/^VAPID_PRIVATE_KEY=.*/m',
                'VAPID_PRIVATE_KEY=' . $newPrivate,
                $envContent
            );

            // Ulozit
            if (file_put_contents($envFile, $envContent)) {
                echo "<div class='ok'>
                    <strong>.env soubor aktualizovan!</strong><br><br>
                    DULEZITE: Po zmene VAPID klicu je nutne:<br>
                    1. <strong>Smazat vsechny existujici subscriptions</strong> (jsou svazane se starym klicem)<br>
                    2. Uzivatele musi znovu povolit notifikace
                </div>";

                echo "<div class='varovani'>
                    <strong>UPOZORNENI:</strong> Muzete smazat stare subscriptions pomoci tlacitka nize.
                </div>";

                echo "<a href='?smazat_subscriptions=1' class='btn btn-danger' onclick=\"return confirm('Opravdu smazat vsechny push subscriptions?')\">Smazat vsechny subscriptions</a>";

            } else {
                echo "<div class='chyba'>Nelze zapsat do .env souboru! Zkontrolujte opravneni.</div>";
            }
        }
    } elseif (isset($_GET['smazat_subscriptions']) && $_GET['smazat_subscriptions'] === '1') {
        // Smazat subscriptions
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->exec("DELETE FROM wgs_push_subscriptions");
            echo "<div class='ok'>Vsechny subscriptions smazany. Uzivatele musi znovu povolit notifikace.</div>";
        } catch (PDOException $e) {
            echo "<div class='chyba'>Chyba pri mazani: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        // Zobrazit formular
        echo "<form method='POST' action='?aktualizovat=1'>
            <input type='hidden' name='new_public' value='" . htmlspecialchars($newKeys['publicKey']) . "'>
            <input type='hidden' name='new_private' value='" . htmlspecialchars($newKeys['privateKey']) . "'>
            <button type='submit' class='btn'>Aktualizovat .env s novymi klici</button>
        </form>";

        echo "<div class='info'>
            <strong>Manualni postup:</strong><br>
            Pokud chcete klice nastavit rucne, pridejte do <code>.env</code>:<br><br>
            <pre>VAPID_PUBLIC_KEY=" . htmlspecialchars($newKeys['publicKey']) . "
VAPID_PRIVATE_KEY=" . htmlspecialchars($newKeys['privateKey']) . "</pre>
        </div>";
    }

} catch (Exception $e) {
    echo "<div class='chyba'>Chyba pri generovani klicu: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// ============================================
// TESTOVANI
// ============================================

echo "<h2>4. Overeni po aktualizaci</h2>";
echo "<p>Po aktualizaci .env souboru spustte znovu diagnostiku:</p>";
echo "<a href='diagnostika_push_kompletni.php' class='btn'>Spustit diagnostiku</a>";

echo "<hr><p><a href='admin.php' class='btn'>Zpet do Admin panelu</a></p>";
echo "</div></body></html>";
?>
