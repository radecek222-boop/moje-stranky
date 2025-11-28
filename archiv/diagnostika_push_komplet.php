<?php
/**
 * Kompletni diagnostika Web Push notifikaci
 * Tento skript analyzuje vsechny aspekty push notifikaci a navrhne reseni
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Diagnostika Web Push</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #222; border-bottom: 2px solid #333; padding-bottom: 15px; margin-top: 0; }
        h2 { color: #333; margin-top: 30px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        .ok { background: #e8e8e8; border-left: 4px solid #333; padding: 12px; margin: 10px 0; }
        .error { background: #f5f5f5; border-left: 4px solid #999; padding: 12px; margin: 10px 0; color: #333; }
        .warning { background: #fafafa; border-left: 4px solid #666; padding: 12px; margin: 10px 0; }
        .info { background: #f9f9f9; border: 1px solid #ddd; padding: 12px; margin: 10px 0; }
        pre { background: #222; color: #fff; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; white-space: pre-wrap; }
        code { background: #eee; padding: 2px 6px; border-radius: 3px; }
        .btn { display: inline-block; padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 5px; margin: 5px 5px 5px 0; }
        .btn:hover { background: #555; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        .status-ok { color: #333; font-weight: bold; }
        .status-error { color: #666; font-weight: bold; }
    </style>
</head>
<body>
<div class='container'>
<h1>Diagnostika Web Push Notifikaci</h1>
<p>Cas: " . date('Y-m-d H:i:s') . "</p>";

$problemy = [];
$reseni = [];

// ============================================
// 1. KONTROLA PHP PROSTREDI
// ============================================
echo "<h2>1. PHP Prostredi</h2>";

echo "<table>";
echo "<tr><th>Parametr</th><th>Hodnota</th><th>Status</th></tr>";

// PHP verze
$phpVerze = phpversion();
$phpOk = version_compare($phpVerze, '8.0', '>=');
echo "<tr><td>PHP verze</td><td>$phpVerze</td><td>" . ($phpOk ? "<span class='status-ok'>OK</span>" : "<span class='status-error'>Zastarale</span>") . "</td></tr>";

// cURL
$curlOk = function_exists('curl_init');
echo "<tr><td>cURL extension</td><td>" . ($curlOk ? 'Nainstalovano' : 'CHYBI') . "</td><td>" . ($curlOk ? "<span class='status-ok'>OK</span>" : "<span class='status-error'>CHYBA</span>") . "</td></tr>";
if (!$curlOk) $problemy[] = "cURL extension neni nainstalovano";

// cURL verze a SSL
if ($curlOk) {
    $curlInfo = curl_version();
    echo "<tr><td>cURL verze</td><td>" . $curlInfo['version'] . "</td><td><span class='status-ok'>OK</span></td></tr>";
    echo "<tr><td>SSL verze</td><td>" . $curlInfo['ssl_version'] . "</td><td><span class='status-ok'>OK</span></td></tr>";
    echo "<tr><td>CA Path</td><td>" . ($curlInfo['capath'] ?: 'Nenastaveno') . "</td><td>-</td></tr>";
    echo "<tr><td>CA Info</td><td>" . ($curlInfo['cainfo'] ?: 'Nenastaveno') . "</td><td>-</td></tr>";
}

// GMP nebo BCMath (potrebne pro VAPID)
$gmpOk = extension_loaded('gmp');
$bcmathOk = extension_loaded('bcmath');
echo "<tr><td>GMP extension</td><td>" . ($gmpOk ? 'Ano' : 'Ne') . "</td><td>" . ($gmpOk ? "<span class='status-ok'>OK</span>" : "-") . "</td></tr>";
echo "<tr><td>BCMath extension</td><td>" . ($bcmathOk ? 'Ano' : 'Ne') . "</td><td>" . ($bcmathOk ? "<span class='status-ok'>OK</span>" : "-") . "</td></tr>";
if (!$gmpOk && !$bcmathOk) $problemy[] = "Chybi GMP nebo BCMath extension pro VAPID";

// OpenSSL
$opensslOk = extension_loaded('openssl');
echo "<tr><td>OpenSSL extension</td><td>" . ($opensslOk ? 'Ano' : 'Ne') . "</td><td>" . ($opensslOk ? "<span class='status-ok'>OK</span>" : "<span class='status-error'>CHYBA</span>") . "</td></tr>";
if (!$opensslOk) $problemy[] = "OpenSSL extension neni nainstalovano";

echo "</table>";

// ============================================
// 2. KONTROLA CEST A SYMLINKU
// ============================================
echo "<h2>2. Cesty a Symlinky</h2>";

echo "<table>";
echo "<tr><th>Cesta</th><th>Hodnota</th></tr>";
echo "<tr><td>__DIR__</td><td>" . __DIR__ . "</td></tr>";
echo "<tr><td>dirname(__DIR__)</td><td>" . dirname(__DIR__) . "</td></tr>";
echo "<tr><td>realpath(__DIR__)</td><td>" . realpath(__DIR__) . "</td></tr>";
echo "<tr><td>\$_SERVER['DOCUMENT_ROOT']</td><td>" . ($_SERVER['DOCUMENT_ROOT'] ?? 'neurceno') . "</td></tr>";
echo "<tr><td>getcwd()</td><td>" . getcwd() . "</td></tr>";
echo "</table>";

echo "<div class='warning'><strong>Poznamka:</strong> Pokud cesty obsahuji zdvojene cesty (napr. /home/www/.../wgs-service.cz/www/wgs-service.cz/www), je to kvuli chroot prostredi hostingu.</div>";

// ============================================
// 3. KONTROLA VAPID KLICU
// ============================================
echo "<h2>3. VAPID Klice</h2>";

$vapidPublic = $_ENV['VAPID_PUBLIC_KEY'] ?? getenv('VAPID_PUBLIC_KEY') ?? '';
$vapidPrivate = $_ENV['VAPID_PRIVATE_KEY'] ?? getenv('VAPID_PRIVATE_KEY') ?? '';
$vapidSubject = $_ENV['VAPID_SUBJECT'] ?? getenv('VAPID_SUBJECT') ?? '';

echo "<table>";
echo "<tr><th>Klic</th><th>Status</th><th>Delka</th></tr>";

$publicOk = !empty($vapidPublic) && strlen($vapidPublic) > 80;
echo "<tr><td>VAPID_PUBLIC_KEY</td><td>" . ($publicOk ? "<span class='status-ok'>Nastaven</span>" : "<span class='status-error'>CHYBI</span>") . "</td><td>" . strlen($vapidPublic) . " znaku</td></tr>";

$privateOk = !empty($vapidPrivate) && strlen($vapidPrivate) > 40;
echo "<tr><td>VAPID_PRIVATE_KEY</td><td>" . ($privateOk ? "<span class='status-ok'>Nastaven</span>" : "<span class='status-error'>CHYBI</span>") . "</td><td>" . strlen($vapidPrivate) . " znaku</td></tr>";

echo "<tr><td>VAPID_SUBJECT</td><td>" . (!empty($vapidSubject) ? $vapidSubject : 'Nenastaveno (pouzije se default)') . "</td><td>-</td></tr>";
echo "</table>";

if (!$publicOk || !$privateOk) {
    $problemy[] = "VAPID klice nejsou spravne nakonfigurovany";
    $reseni[] = "Spustte: php setup_web_push.php";
}

// ============================================
// 4. KONTROLA KNIHOVNY WEBPUSH
// ============================================
echo "<h2>4. WebPush Knihovna</h2>";

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    echo "<div class='ok'>Composer autoload nalezen: " . realpath($autoloadPath) . "</div>";

    if (class_exists('Minishlink\WebPush\WebPush')) {
        echo "<div class='ok'>Trida Minishlink\WebPush\WebPush je dostupna</div>";
    } else {
        echo "<div class='error'>Trida WebPush NENI dostupna</div>";
        $problemy[] = "WebPush knihovna neni nainstalovana";
        $reseni[] = "Spustte: composer require minishlink/web-push";
    }
} else {
    echo "<div class='error'>Composer autoload NENALEZEN: $autoloadPath</div>";
    $problemy[] = "Composer autoload nenalezen";
    $reseni[] = "Spustte: composer install";
}

// ============================================
// 5. KONTROLA CA CERTIFIKATU
// ============================================
echo "<h2>5. CA Certifikaty (SSL)</h2>";

$cacertPath = __DIR__ . '/cacert.pem';

echo "<table>";
echo "<tr><th>Vlastnost</th><th>Hodnota</th></tr>";
echo "<tr><td>Ocekavana cesta</td><td><code>$cacertPath</code></td></tr>";
echo "<tr><td>Existuje (file_exists)</td><td>" . (file_exists($cacertPath) ? 'ANO' : 'NE') . "</td></tr>";
echo "<tr><td>Citelny (is_readable)</td><td>" . (is_readable($cacertPath) ? 'ANO' : 'NE') . "</td></tr>";

if (file_exists($cacertPath)) {
    echo "<tr><td>Velikost</td><td>" . number_format(filesize($cacertPath)) . " bytu</td></tr>";
    echo "<tr><td>Posledni zmena</td><td>" . date('Y-m-d H:i:s', filemtime($cacertPath)) . "</td></tr>";

    // Kontrola obsahu
    $obsah = file_get_contents($cacertPath, false, null, 0, 500);
    $zacinaSpravne = strpos($obsah, '-----BEGIN') !== false || strpos($obsah, 'Bundle of CA') !== false;
    echo "<tr><td>Format souboru</td><td>" . ($zacinaSpravne ? 'OK (PEM)' : 'NEZNAMY') . "</td></tr>";

    // Kontrola Apple certifikatu
    $plnyObsah = file_get_contents($cacertPath);
    $maApple = strpos($plnyObsah, 'Apple') !== false;
    echo "<tr><td>Apple CA certifikaty</td><td>" . ($maApple ? 'Pridany' : 'CHYBI') . "</td></tr>";

    if (!$maApple) {
        $problemy[] = "cacert.pem neobsahuje Apple CA certifikaty";
        $reseni[] = "Pridejte Apple certifikaty do cacert.pem";
    }
}
echo "</table>";

// ============================================
// 6. TEST SSL PRIPOJENI
// ============================================
echo "<h2>6. Test SSL Pripojeni</h2>";

if ($curlOk) {
    // Test 1: Bez vlastniho CA
    echo "<h3>6a) Test bez vlastniho CA (system default)</h3>";
    $ch = curl_init('https://web.push.apple.com');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno === 0) {
        echo "<div class='ok'>System CA: OK (HTTP $httpCode)</div>";
    } else {
        echo "<div class='error'>System CA: CHYBA $errno - $error</div>";
        $problemy[] = "System CA bundle nezna Apple Push certifikaty (errno $errno)";
    }

    // Test 2: S vlastnim CA (pokud existuje)
    if (file_exists($cacertPath) && is_readable($cacertPath)) {
        echo "<h3>6b) Test s vlastnim cacert.pem</h3>";
        $ch = curl_init('https://web.push.apple.com');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_CAINFO, $cacertPath);
        $result = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno === 0) {
            echo "<div class='ok'>Vlastni CA: OK (HTTP $httpCode)</div>";
        } else {
            echo "<div class='error'>Vlastni CA: CHYBA $errno - $error</div>";
            if ($errno === 77) {
                $problemy[] = "cURL nemuze cist cacert.pem (error 77 - hosting omezeni?)";
                $reseni[] = "Kontaktujte hosting ohledne SSL/CA konfigurace";
            }
        }
    }

    // Test 3: Bez SSL verifikace
    echo "<h3>6c) Test bez SSL verifikace (pouze diagnosticky)</h3>";
    $ch = curl_init('https://web.push.apple.com');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno === 0) {
        echo "<div class='ok'>Bez SSL verifikace: OK (HTTP $httpCode) - Pripojeni k Apple funguje</div>";
    } else {
        echo "<div class='error'>Bez SSL verifikace: CHYBA $errno - $error</div>";
        $problemy[] = "Nelze se pripojit k Apple Push serveru ani bez SSL verifikace";
    }
}

// ============================================
// 7. KONTROLA DATABAZE
// ============================================
echo "<h2>7. Databaze Push Subscriptions</h2>";

try {
    $pdo = getDbConnection();

    // Kontrola tabulky
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_push_subscriptions'");
    if ($stmt->rowCount() > 0) {
        echo "<div class='ok'>Tabulka wgs_push_subscriptions existuje</div>";

        // Statistiky
        $stmt = $pdo->query("SELECT
            COUNT(*) as celkem,
            SUM(aktivni = 1) as aktivni,
            SUM(platforma = 'ios') as ios,
            SUM(platforma = 'android') as android,
            SUM(platforma = 'desktop') as desktop
        FROM wgs_push_subscriptions");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        echo "<table>";
        echo "<tr><th>Metrika</th><th>Hodnota</th></tr>";
        echo "<tr><td>Celkem subscriptions</td><td>" . ($stats['celkem'] ?? 0) . "</td></tr>";
        echo "<tr><td>Aktivnich</td><td>" . ($stats['aktivni'] ?? 0) . "</td></tr>";
        echo "<tr><td>iOS</td><td>" . ($stats['ios'] ?? 0) . "</td></tr>";
        echo "<tr><td>Android</td><td>" . ($stats['android'] ?? 0) . "</td></tr>";
        echo "<tr><td>Desktop</td><td>" . ($stats['desktop'] ?? 0) . "</td></tr>";
        echo "</table>";

        if (($stats['aktivni'] ?? 0) == 0) {
            $problemy[] = "Zadne aktivni push subscriptions";
            $reseni[] = "Navstivte web jako uzivatel a povolte notifikace";
        }
    } else {
        echo "<div class='error'>Tabulka wgs_push_subscriptions NEEXISTUJE</div>";
        $problemy[] = "Chybi databazova tabulka pro push subscriptions";
    }
} catch (Exception $e) {
    echo "<div class='error'>Chyba databaze: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// ============================================
// 8. SHRNUTI A RESENI
// ============================================
echo "<h2>8. Shrnuti</h2>";

if (empty($problemy)) {
    echo "<div class='ok'><strong>Vsechny kontroly prosly!</strong> Push notifikace by mely fungovat.</div>";
} else {
    echo "<div class='error'><strong>Nalezeno " . count($problemy) . " problemu:</strong></div>";
    echo "<ol>";
    foreach ($problemy as $problem) {
        echo "<li>$problem</li>";
    }
    echo "</ol>";
}

if (!empty($reseni)) {
    echo "<h3>Doporucena reseni:</h3>";
    echo "<ol>";
    foreach ($reseni as $r) {
        echo "<li>$r</li>";
    }
    echo "</ol>";
}

// Hlavni problem - SSL
if (in_array("cURL nemuze cist cacert.pem (error 77 - hosting omezeni?)", $problemy) ||
    in_array("System CA bundle nezna Apple Push certifikaty (errno 60)", $problemy)) {

    echo "<div class='warning'>";
    echo "<h3>Hlavni problem: SSL certifikaty</h3>";
    echo "<p>Hosting neumi overit SSL certifikat Apple Push serveru. Mozna reseni:</p>";
    echo "<ol>";
    echo "<li><strong>Kontaktovat hosting</strong> - pozadat o aktualizaci system CA bundle</li>";
    echo "<li><strong>Pouzit jiny pristup</strong> - napriklad externi push sluzbu (OneSignal, Firebase, PushAlert)</li>";
    echo "<li><strong>Docasne vypnout SSL verifikaci</strong> (NEDOPORUCENO pro produkci)</li>";
    echo "</ol>";
    echo "</div>";
}

// ============================================
// 9. AKCE
// ============================================
echo "<h2>Akce</h2>";
echo "<a href='/test_push_notifikace.php?send=1' class='btn'>Test Push Notifikace</a>";
echo "<a href='/reaktivuj_subscriptions.php?execute=1' class='btn'>Reaktivovat Subscriptions</a>";
echo "<a href='/admin.php' class='btn'>Zpet do Admin</a>";

// ============================================
// 10. POSLEDNI LOGY
// ============================================
echo "<h2>Posledni WebPush logy</h2>";
$logFile = __DIR__ . '/logs/php_errors.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    $webPushLines = array_filter($lines, function($line) {
        return stripos($line, 'webpush') !== false || stripos($line, 'push') !== false;
    });
    $webPushLines = array_slice($webPushLines, -10);

    if (!empty($webPushLines)) {
        echo "<pre>" . htmlspecialchars(implode("\n", $webPushLines)) . "</pre>";
    } else {
        echo "<div class='info'>Zadne WebPush zaznamy v logu</div>";
    }
} else {
    echo "<div class='info'>Log soubor nenalezen</div>";
}

echo "</div></body></html>";
?>
