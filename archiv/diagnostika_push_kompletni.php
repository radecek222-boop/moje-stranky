<?php
/**
 * Kompletni diagnostika Web Push notifikaci
 * Spustit na produkci: https://www.wgs-service.cz/diagnostika_push_kompletni.php
 */

require_once __DIR__ . '/init.php';

// Admin check
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze admin muze spustit diagnostiku.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika Web Push</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1000px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #222; border-bottom: 3px solid #222; padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; }
        .ok { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .chyba { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .varovani { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #e2e3e5; border: 1px solid #d6d8db; color: #383d41; padding: 12px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f5f5f5; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #222; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; }
        .btn:hover { background: #444; }
    </style>
</head>
<body>
<div class='container'>
<h1>Diagnostika Web Push Notifikaci</h1>
<p>Datum: " . date('Y-m-d H:i:s') . "</p>";

$vsechnoOk = true;
$chyby = [];

// ============================================
// 1. COMPOSER KNIHOVNA
// ============================================
echo "<h2>1. Composer knihovna (minishlink/web-push)</h2>";

$vendorAutoload = __DIR__ . '/vendor/autoload.php';
$minishlinkExists = file_exists(__DIR__ . '/vendor/minishlink');

if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
    echo "<div class='ok'>vendor/autoload.php: EXISTUJE</div>";

    if (class_exists('Minishlink\WebPush\WebPush')) {
        echo "<div class='ok'>Trida Minishlink\WebPush\WebPush: NALEZENA</div>";
    } else {
        echo "<div class='chyba'>Trida Minishlink\WebPush\WebPush: NENALEZENA - spustte <code>composer install</code></div>";
        $vsechnoOk = false;
        $chyby[] = 'Composer knihovna neni nainstalovana';
    }
} else {
    echo "<div class='chyba'>vendor/autoload.php: NEEXISTUJE - spustte <code>composer install</code></div>";
    $vsechnoOk = false;
    $chyby[] = 'Vendor slozka neexistuje';
}

if ($minishlinkExists) {
    echo "<div class='ok'>vendor/minishlink/: EXISTUJE</div>";
} else {
    echo "<div class='chyba'>vendor/minishlink/: NEEXISTUJE</div>";
}

// ============================================
// 2. VAPID KLICE
// ============================================
echo "<h2>2. VAPID klice (.env)</h2>";

$vapidPublic = $_ENV['VAPID_PUBLIC_KEY'] ?? getenv('VAPID_PUBLIC_KEY') ?? '';
$vapidPrivate = $_ENV['VAPID_PRIVATE_KEY'] ?? getenv('VAPID_PRIVATE_KEY') ?? '';
$vapidSubject = $_ENV['VAPID_SUBJECT'] ?? getenv('VAPID_SUBJECT') ?? '';

if (!empty($vapidPublic)) {
    echo "<div class='ok'>VAPID_PUBLIC_KEY: NASTAVEN (" . strlen($vapidPublic) . " znaku, zacina: " . substr($vapidPublic, 0, 20) . "...)</div>";
} else {
    echo "<div class='chyba'>VAPID_PUBLIC_KEY: NENI NASTAVEN - spustte setup_web_push.php</div>";
    $vsechnoOk = false;
    $chyby[] = 'VAPID_PUBLIC_KEY neni nastaven';
}

if (!empty($vapidPrivate)) {
    echo "<div class='ok'>VAPID_PRIVATE_KEY: NASTAVEN (" . strlen($vapidPrivate) . " znaku)</div>";
} else {
    echo "<div class='chyba'>VAPID_PRIVATE_KEY: NENI NASTAVEN - spustte setup_web_push.php</div>";
    $vsechnoOk = false;
    $chyby[] = 'VAPID_PRIVATE_KEY neni nastaven';
}

if (!empty($vapidSubject)) {
    echo "<div class='ok'>VAPID_SUBJECT: " . htmlspecialchars($vapidSubject) . "</div>";
} else {
    echo "<div class='varovani'>VAPID_SUBJECT: neni nastaven (pouzije se default)</div>";
}

// ============================================
// 3. DATABAZOVA TABULKA
// ============================================
echo "<h2>3. Databazova tabulka wgs_push_subscriptions</h2>";

try {
    $pdo = getDbConnection();

    // Kontrola existence tabulky
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_push_subscriptions'");
    if ($stmt->rowCount() > 0) {
        echo "<div class='ok'>Tabulka wgs_push_subscriptions: EXISTUJE</div>";

        // Pocet zaznamu
        $stmt = $pdo->query("SELECT COUNT(*) as celkem, SUM(CASE WHEN aktivni = 1 THEN 1 ELSE 0 END) as aktivni FROM wgs_push_subscriptions");
        $pocty = $stmt->fetch(PDO::FETCH_ASSOC);

        echo "<div class='info'>Celkem subscriptions: " . $pocty['celkem'] . " (aktivnich: " . $pocty['aktivni'] . ")</div>";

        if ($pocty['aktivni'] == 0) {
            echo "<div class='varovani'>POZOR: Zadne aktivni subscriptions! Uzivatel musi povolit notifikace v prohlizeci.</div>";
            $chyby[] = 'Zadne aktivni push subscriptions';
        }

        // Detail subscriptions
        $stmt = $pdo->query("SELECT id, user_id, platforma, aktivni, pocet_chyb,
            LEFT(endpoint, 60) as endpoint_zkraceny,
            datum_vytvoreni, posledni_uspesne_odeslani
            FROM wgs_push_subscriptions ORDER BY datum_vytvoreni DESC LIMIT 10");
        $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($subs)) {
            echo "<table>
                <tr><th>ID</th><th>User ID</th><th>Platforma</th><th>Aktivni</th><th>Chyby</th><th>Endpoint</th><th>Vytvoreno</th><th>Posledni odeslani</th></tr>";
            foreach ($subs as $s) {
                $aktivniClass = $s['aktivni'] ? 'ok' : 'chyba';
                echo "<tr>
                    <td>" . $s['id'] . "</td>
                    <td>" . ($s['user_id'] ?? '-') . "</td>
                    <td>" . ($s['platforma'] ?? '-') . "</td>
                    <td class='{$aktivniClass}'>" . ($s['aktivni'] ? 'ANO' : 'NE') . "</td>
                    <td>" . $s['pocet_chyb'] . "</td>
                    <td><code>" . htmlspecialchars($s['endpoint_zkraceny']) . "...</code></td>
                    <td>" . $s['datum_vytvoreni'] . "</td>
                    <td>" . ($s['posledni_uspesne_odeslani'] ?? '-') . "</td>
                </tr>";
            }
            echo "</table>";
        }

        // Statistiky podle platformy
        $stmt = $pdo->query("
            SELECT platforma, COUNT(*) as pocet, SUM(CASE WHEN aktivni = 1 THEN 1 ELSE 0 END) as aktivni
            FROM wgs_push_subscriptions GROUP BY platforma
        ");
        $statPlatforma = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($statPlatforma)) {
            echo "<h3>Subscriptions podle platformy:</h3><table><tr><th>Platforma</th><th>Celkem</th><th>Aktivni</th></tr>";
            foreach ($statPlatforma as $sp) {
                echo "<tr><td>" . ($sp['platforma'] ?? 'neznama') . "</td><td>" . $sp['pocet'] . "</td><td>" . $sp['aktivni'] . "</td></tr>";
            }
            echo "</table>";
        }

    } else {
        echo "<div class='chyba'>Tabulka wgs_push_subscriptions: NEEXISTUJE - spustte pridej_push_subscriptions_tabulku.php</div>";
        $vsechnoOk = false;
        $chyby[] = 'Tabulka wgs_push_subscriptions neexistuje';
    }

    // Push log
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_push_log'");
    if ($stmt->rowCount() > 0) {
        echo "<div class='ok'>Tabulka wgs_push_log: EXISTUJE</div>";

        // Posledni logy
        $stmt = $pdo->query("SELECT * FROM wgs_push_log ORDER BY datum_odeslani DESC LIMIT 5");
        $logy = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($logy)) {
            echo "<h3>Posledni push logy:</h3><table><tr><th>ID</th><th>Typ</th><th>Titulek</th><th>Stav</th><th>Datum</th><th>Chyba</th></tr>";
            foreach ($logy as $l) {
                $stavClass = ($l['stav'] ?? '') === 'odeslano' ? 'ok' : 'chyba';
                echo "<tr>
                    <td>" . $l['id'] . "</td>
                    <td>" . ($l['typ_notifikace'] ?? '-') . "</td>
                    <td>" . htmlspecialchars($l['titulek'] ?? '-') . "</td>
                    <td class='{$stavClass}'>" . ($l['stav'] ?? '-') . "</td>
                    <td>" . ($l['datum_odeslani'] ?? '-') . "</td>
                    <td>" . htmlspecialchars($l['chybova_zprava'] ?? '-') . "</td>
                </tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='info'>Zatim zadne push logy - notifikace zatim nebyly odeslany.</div>";
        }
    }

} catch (PDOException $e) {
    echo "<div class='chyba'>Chyba databaze: " . htmlspecialchars($e->getMessage()) . "</div>";
    $vsechnoOk = false;
}

// ============================================
// 3b. KONTROLA USER_ID MAPPING
// ============================================
echo "<h2>3b. Kontrola user_id mapping</h2>";

try {
    // Subscriptions s jejich user_id
    $stmt = $pdo->query("SELECT id, user_id, platforma FROM wgs_push_subscriptions WHERE aktivni = 1 LIMIT 10");
    $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>Push subscriptions:</h3><table><tr><th>Sub ID</th><th>user_id</th><th>Platforma</th><th>Nalezen v wgs_users?</th><th>Role</th></tr>";

    foreach ($subs as $s) {
        $uid = $s['user_id'];
        $nalezen = 'NE';
        $role = '-';

        if ($uid !== null) {
            // Zkusit najit v wgs_users
            $stmtU = $pdo->prepare("SELECT user_id, role FROM wgs_users WHERE user_id = :uid LIMIT 1");
            $stmtU->execute([':uid' => $uid]);
            $user = $stmtU->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $nalezen = 'ANO';
                $role = $user['role'] ?? '-';
            }
        }

        $class = $nalezen === 'ANO' ? 'ok' : 'chyba';
        echo "<tr>
            <td>" . $s['id'] . "</td>
            <td>" . htmlspecialchars($uid ?? 'NULL') . "</td>
            <td>" . $s['platforma'] . "</td>
            <td class='{$class}'>{$nalezen}</td>
            <td>{$role}</td>
        </tr>";
    }
    echo "</table>";

    // Ukazat strukturu wgs_users
    echo "<h3>Uzivatele v wgs_users (prvnich 10):</h3>";
    $stmt = $pdo->query("SELECT user_id, email, role FROM wgs_users LIMIT 10");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table><tr><th>user_id</th><th>email</th><th>role</th></tr>";
    foreach ($users as $u) {
        echo "<tr>
            <td>" . htmlspecialchars($u['user_id'] ?? '-') . "</td>
            <td>" . htmlspecialchars($u['email'] ?? '-') . "</td>
            <td>" . htmlspecialchars($u['role'] ?? '-') . "</td>
        </tr>";
    }
    echo "</table>";

} catch (PDOException $e) {
    echo "<div class='chyba'>Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// ============================================
// 4. WEBPUSH INICIALIZACE
// ============================================
echo "<h2>4. WebPush inicializace</h2>";

require_once __DIR__ . '/includes/WebPush.php';

try {
    $webPush = new WGSWebPush($pdo);

    if ($webPush->jeInicializovano()) {
        echo "<div class='ok'>WGSWebPush: INICIALIZOVANO USPESNE</div>";
    } else {
        echo "<div class='chyba'>WGSWebPush: CHYBA INICIALIZACE - " . htmlspecialchars($webPush->getChyba()) . "</div>";
        $vsechnoOk = false;
        $chyby[] = 'WebPush inicializace selhala: ' . $webPush->getChyba();
    }
} catch (Exception $e) {
    echo "<div class='chyba'>WGSWebPush: VYJIMKA - " . htmlspecialchars($e->getMessage()) . "</div>";
    $vsechnoOk = false;
}

// ============================================
// 5. SERVICE WORKER
// ============================================
echo "<h2>5. Service Worker a manifest</h2>";

if (file_exists(__DIR__ . '/sw.js')) {
    $swContent = file_get_contents(__DIR__ . '/sw.js');
    preg_match("/SW_VERSION = '([^']+)'/", $swContent, $matches);
    $swVersion = $matches[1] ?? 'neznama';
    echo "<div class='ok'>sw.js: EXISTUJE (verze: " . $swVersion . ")</div>";

    // Kontrola push event handleru
    if (strpos($swContent, "addEventListener('push'") !== false) {
        echo "<div class='ok'>sw.js: Push event handler NALEZEN</div>";
    } else {
        echo "<div class='chyba'>sw.js: Push event handler CHYBI!</div>";
        $vsechnoOk = false;
    }
} else {
    echo "<div class='chyba'>sw.js: NEEXISTUJE</div>";
    $vsechnoOk = false;
}

if (file_exists(__DIR__ . '/manifest.json')) {
    echo "<div class='ok'>manifest.json: EXISTUJE</div>";
} else {
    echo "<div class='varovani'>manifest.json: NEEXISTUJE (dulezite pro PWA)</div>";
}

// ============================================
// 6. PHP LOGY
// ============================================
echo "<h2>6. PHP logy (posledni push chyby)</h2>";

$logFile = __DIR__ . '/logs/php_errors.log';
if (file_exists($logFile)) {
    // Precist poslednich 50 radku a hledat push/webpush
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    $pushLines = array_filter($lines, function($line) {
        return stripos($line, 'push') !== false || stripos($line, 'webpush') !== false || stripos($line, 'Notes]') !== false;
    });

    $pushLines = array_slice($pushLines, -20);

    if (!empty($pushLines)) {
        echo "<pre>" . htmlspecialchars(implode("\n", $pushLines)) . "</pre>";
    } else {
        echo "<div class='info'>Zadne push-related logy nalezeny</div>";
    }
} else {
    echo "<div class='info'>Log soubor neexistuje: logs/php_errors.log</div>";
}

// ============================================
// 7. TEST ODESLANI (volitelne)
// ============================================
echo "<h2>7. Test odeslani push notifikace</h2>";

if (isset($_GET['test']) && $_GET['test'] === '1' && $webPush->jeInicializovano()) {
    echo "<div class='info'>Posilam testovaci notifikaci vsem aktivnim subscriptions...</div>";

    $payload = [
        'title' => 'WGS Test',
        'body' => 'Testovaci push notifikace - ' . date('H:i:s'),
        'icon' => '/icon192.png',
        'tag' => 'wgs-test-' . time(),
        'data' => ['test' => true]
    ];

    $vysledek = $webPush->odeslatVsem($payload);

    if ($vysledek['uspech']) {
        echo "<div class='ok'>Test uspesny: " . htmlspecialchars(json_encode($vysledek, JSON_UNESCAPED_UNICODE)) . "</div>";
    } else {
        echo "<div class='chyba'>Test selhal: " . htmlspecialchars(json_encode($vysledek, JSON_UNESCAPED_UNICODE)) . "</div>";
    }
} else {
    if ($webPush->jeInicializovano()) {
        echo "<a href='?test=1' class='btn'>Odeslat testovaci push vsem</a>";
    } else {
        echo "<div class='varovani'>Test neni mozny - WebPush neni inicializovan</div>";
    }
}

// ============================================
// SOUHRN
// ============================================
echo "<h2>SOUHRN</h2>";

if ($vsechnoOk && empty($chyby)) {
    echo "<div class='ok'><strong>VSECHNO V PORADKU!</strong> Vsechny komponenty jsou spravne nakonfigurovane.</div>";
    echo "<p>Pokud push notifikace stale nefunguji:</p>
    <ol>
        <li>Zkontrolujte ze uzivatel povolil notifikace v prohlizeci</li>
        <li>Zkontrolujte ze je subscription ulozena v databazi (viz tabulka vyse)</li>
        <li>Zkontrolujte konzoli prohlizece pro JS chyby</li>
        <li>Zkuste odeslat testovaci notifikaci (tlacitko vyse)</li>
        <li>Zkontrolujte network tab v dev tools pri registraci subscription</li>
    </ol>";
} else {
    echo "<div class='chyba'><strong>NALEZENY PROBLEMY:</strong></div>";
    echo "<ul>";
    foreach ($chyby as $ch) {
        echo "<li>" . htmlspecialchars($ch) . "</li>";
    }
    echo "</ul>";

    echo "<h3>Jak opravit:</h3>";
    echo "<ol>";
    if (in_array('Vendor slozka neexistuje', $chyby) || in_array('Composer knihovna neni nainstalovana', $chyby)) {
        echo "<li><strong>Nainstalujte Composer zavislosti:</strong><br><code>cd /cesta/k/projektu && composer install</code></li>";
    }
    if (in_array('VAPID_PUBLIC_KEY neni nastaven', $chyby) || in_array('VAPID_PRIVATE_KEY neni nastaven', $chyby)) {
        echo "<li><strong>Vygenerujte VAPID klice:</strong><br>Otevrete: <a href='setup_web_push.php'>setup_web_push.php</a></li>";
    }
    if (in_array('Tabulka wgs_push_subscriptions neexistuje', $chyby)) {
        echo "<li><strong>Vytvorte databazove tabulky:</strong><br>Otevrete: <a href='pridej_push_subscriptions_tabulku.php'>pridej_push_subscriptions_tabulku.php</a></li>";
    }
    echo "</ol>";
}

echo "<hr><p><a href='admin.php' class='btn'>Zpet do Admin panelu</a></p>";
echo "</div></body></html>";
?>
