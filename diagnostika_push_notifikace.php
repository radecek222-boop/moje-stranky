<?php
/**
 * Diagnostika Push Notifikaci
 *
 * Tento skript zkontroluje:
 * 1. VAPID klice
 * 2. Stav subscriptions v databazi
 * 3. Logy push notifikaci
 * 4. Testovaci odeslani
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit diagnostiku.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Diagnostika Push Notifikaci</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h1 { color: #222; border-bottom: 3px solid #222; padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f5f5f5; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #222; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; }
        .btn:hover { background: #444; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        pre { background: #222; color: #0f0; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .endpoint { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>
</head>
<body>
<div class='container'>
<h1>Diagnostika Push Notifikaci</h1>
<p>Spusteno: " . date('Y-m-d H:i:s') . "</p>";

try {
    $pdo = getDbConnection();

    // =====================================================
    // 1. KONTROLA VAPID KLICU
    // =====================================================
    echo "<h2>1. VAPID Klice</h2>";

    $vapidPublic = $_ENV['VAPID_PUBLIC_KEY'] ?? getenv('VAPID_PUBLIC_KEY') ?? '';
    $vapidPrivate = $_ENV['VAPID_PRIVATE_KEY'] ?? getenv('VAPID_PRIVATE_KEY') ?? '';
    $vapidSubject = $_ENV['VAPID_SUBJECT'] ?? getenv('VAPID_SUBJECT') ?? '';

    if (empty($vapidPublic)) {
        echo "<div class='error'><strong>VAPID_PUBLIC_KEY</strong> NENI NASTAVEN!</div>";
        echo "<div class='info'>Spustte: <code>php setup_web_push.php</code> nebo nastavte rucne v .env</div>";
    } else {
        $keyLength = strlen($vapidPublic);
        echo "<div class='success'><strong>VAPID_PUBLIC_KEY</strong> je nastaven ({$keyLength} znaku)</div>";
        echo "<div class='info'>Zacatek: <code>" . substr($vapidPublic, 0, 20) . "...</code></div>";
    }

    if (empty($vapidPrivate)) {
        echo "<div class='error'><strong>VAPID_PRIVATE_KEY</strong> NENI NASTAVEN!</div>";
    } else {
        echo "<div class='success'><strong>VAPID_PRIVATE_KEY</strong> je nastaven (" . strlen($vapidPrivate) . " znaku)</div>";
    }

    if (empty($vapidSubject)) {
        echo "<div class='warning'><strong>VAPID_SUBJECT</strong> neni nastaven (pouzije se default)</div>";
    } else {
        echo "<div class='success'><strong>VAPID_SUBJECT</strong> = <code>{$vapidSubject}</code></div>";
    }

    // =====================================================
    // 2. KONTROLA MINISHLINK KNIHOVNY
    // =====================================================
    echo "<h2>2. WebPush Knihovna</h2>";

    $autoloadSoubor = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoloadSoubor)) {
        echo "<div class='error'><strong>vendor/autoload.php</strong> NEEXISTUJE!</div>";
        echo "<div class='info'>Spustte: <code>composer install</code> nebo <code>composer update</code></div>";
    } else {
        require_once $autoloadSoubor;

        if (!class_exists('Minishlink\WebPush\WebPush')) {
            echo "<div class='error'>Trida <strong>Minishlink\\WebPush\\WebPush</strong> neni dostupna!</div>";
            echo "<div class='info'>Spustte: <code>composer require minishlink/web-push</code></div>";
        } else {
            echo "<div class='success'>Knihovna <strong>minishlink/web-push</strong> je nainstalovana</div>";
        }
    }

    // =====================================================
    // 3. KONTROLA DATABAZOVYCH TABULEK
    // =====================================================
    echo "<h2>3. Databazove Tabulky</h2>";

    // Kontrola wgs_push_subscriptions
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_push_subscriptions'");
    if ($stmt->rowCount() === 0) {
        echo "<div class='error'>Tabulka <strong>wgs_push_subscriptions</strong> NEEXISTUJE!</div>";
        echo "<div class='info'>Spustte migracni skript: <code>pridej_push_subscriptions_tabulku.php</code></div>";
    } else {
        echo "<div class='success'>Tabulka <strong>wgs_push_subscriptions</strong> existuje</div>";

        // Struktura tabulky
        $stmt = $pdo->query("DESCRIBE wgs_push_subscriptions");
        $sloupce = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<div class='info'>Sloupce: <code>" . implode(', ', $sloupce) . "</code></div>";
    }

    // Kontrola wgs_push_log
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_push_log'");
    if ($stmt->rowCount() === 0) {
        echo "<div class='warning'>Tabulka <strong>wgs_push_log</strong> neexistuje (volitelna)</div>";
    } else {
        echo "<div class='success'>Tabulka <strong>wgs_push_log</strong> existuje</div>";
    }

    // Kontrola COLLATION tabulek
    echo "<h3>Collation Tabulek</h3>";
    $stmt = $pdo->query("
        SELECT TABLE_NAME, TABLE_COLLATION
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('wgs_push_subscriptions', 'wgs_users', 'wgs_reklamace', 'wgs_push_log')
    ");
    $collations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $collationMap = [];
    foreach ($collations as $row) {
        $collationMap[$row['TABLE_NAME']] = $row['TABLE_COLLATION'];
    }

    echo "<table><tr><th>Tabulka</th><th>Collation</th><th>Status</th></tr>";
    $expectedCollation = 'utf8mb4_czech_ci';
    $mismatch = false;

    foreach ($collationMap as $table => $collation) {
        $status = ($collation === $expectedCollation)
            ? '<span style=\"color:green;\">OK</span>'
            : '<span style=\"color:orange;\">Rozdilna!</span>';
        if ($collation !== $expectedCollation) $mismatch = true;
        echo "<tr><td>{$table}</td><td><code>{$collation}</code></td><td>{$status}</td></tr>";
    }
    echo "</table>";

    if ($mismatch) {
        echo "<div class='warning'>
            <strong>COLLATION MISMATCH!</strong> Tabulky maji ruzne collation, coz muze zpusobit chyby pri JOIN operacich.<br>
            Doporuceni: Spustte migracni skript <code>sjednotit_collation.php</code> pro sjednoceni na <code>{$expectedCollation}</code>
        </div>";
    }

    // =====================================================
    // 4. STATISTIKY SUBSCRIPTIONS
    // =====================================================
    echo "<h2>4. Statistiky Subscriptions</h2>";

    $stmt = $pdo->query("SELECT
        COUNT(*) as celkem,
        SUM(CASE WHEN aktivni = 1 THEN 1 ELSE 0 END) as aktivni,
        SUM(CASE WHEN aktivni = 0 THEN 1 ELSE 0 END) as neaktivni,
        SUM(CASE WHEN platforma = 'ios' AND aktivni = 1 THEN 1 ELSE 0 END) as ios,
        SUM(CASE WHEN platforma = 'android' AND aktivni = 1 THEN 1 ELSE 0 END) as android,
        SUM(CASE WHEN platforma = 'desktop' AND aktivni = 1 THEN 1 ELSE 0 END) as desktop,
        SUM(CASE WHEN user_id IS NULL AND aktivni = 1 THEN 1 ELSE 0 END) as bez_user_id
    FROM wgs_push_subscriptions");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<table>
        <tr><th>Metrika</th><th>Hodnota</th></tr>
        <tr><td>Celkem subscriptions</td><td><strong>{$stats['celkem']}</strong></td></tr>
        <tr><td>Aktivni</td><td><strong style='color: green;'>{$stats['aktivni']}</strong></td></tr>
        <tr><td>Neaktivni (deaktivovane)</td><td>{$stats['neaktivni']}</td></tr>
        <tr><td>iOS</td><td>{$stats['ios']}</td></tr>
        <tr><td>Android</td><td>{$stats['android']}</td></tr>
        <tr><td>Desktop</td><td>{$stats['desktop']}</td></tr>
        <tr><td>Bez user_id (problematicke!)</td><td>" . ($stats['bez_user_id'] > 0 ? "<strong style='color: red;'>{$stats['bez_user_id']}</strong>" : "0") . "</td></tr>
    </table>";

    if ($stats['celkem'] == 0) {
        echo "<div class='error'>ZADNE SUBSCRIPTIONS! Zadne zarizeni nema zaregistrovane push notifikace.</div>";
        echo "<div class='info'>
            <strong>Co zkontrolovat:</strong><br>
            1. Otevrte DevTools v prohlizeci (F12)<br>
            2. Prejdete na Console a hledejte '[Notifikace]' zpravy<br>
            3. Zkontrolujte Application > Service Workers<br>
            4. Zkontrolujte Application > Push > Subscription
        </div>";
    }

    if ($stats['bez_user_id'] > 0) {
        echo "<div class='warning'>
            <strong>VAROVANI:</strong> {$stats['bez_user_id']} subscription(s) bez user_id!<br>
            Tyto subscriptions nebudou dostavat notifikace pro techniky/adminy, protoze je nelze prirazit k roli.
        </div>";
    }

    // =====================================================
    // 5. DETAIL VSECH AKTIVNICH SUBSCRIPTIONS
    // =====================================================
    echo "<h2>5. Detail Aktivnich Subscriptions</h2>";

    // POZOR: Pouzivame COLLATE pro reseni problemu s ruznymi collation mezi tabulkami
    $stmt = $pdo->query("
        SELECT
            ps.id,
            ps.user_id,
            ps.email,
            ps.endpoint,
            ps.platforma,
            ps.aktivni,
            ps.pocet_chyb,
            ps.datum_vytvoreni,
            ps.posledni_uspesne_odeslani,
            u.name as uzivatel_jmeno,
            u.role as uzivatel_role
        FROM wgs_push_subscriptions ps
        LEFT JOIN wgs_users u ON CAST(ps.user_id AS CHAR) = CAST(u.user_id AS CHAR) COLLATE utf8mb4_unicode_ci
        WHERE ps.aktivni = 1
        ORDER BY ps.datum_vytvoreni DESC
        LIMIT 20
    ");
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($subscriptions)) {
        echo "<div class='warning'>Zadne aktivni subscriptions</div>";
    } else {
        echo "<table>
            <tr>
                <th>ID</th>
                <th>User ID</th>
                <th>Uzivatel</th>
                <th>Role</th>
                <th>Platforma</th>
                <th>Endpoint</th>
                <th>Chyby</th>
                <th>Vytvoreno</th>
                <th>Posledni uspech</th>
            </tr>";

        foreach ($subscriptions as $sub) {
            $role = $sub['uzivatel_role'] ?? '<span style="color:red;">N/A</span>';
            $jmeno = $sub['uzivatel_jmeno'] ?? $sub['email'] ?? '<span style="color:red;">Neznamy</span>';
            $endpoint = $sub['endpoint'];
            $shortEndpoint = strlen($endpoint) > 50 ? substr($endpoint, 0, 50) . '...' : $endpoint;

            // Detekce push service z endpoint
            $pushService = 'Neznamy';
            if (strpos($endpoint, 'fcm.googleapis.com') !== false) {
                $pushService = 'FCM (Chrome/Android)';
            } elseif (strpos($endpoint, 'mozilla.com') !== false) {
                $pushService = 'Mozilla (Firefox)';
            } elseif (strpos($endpoint, 'windows.com') !== false) {
                $pushService = 'WNS (Windows)';
            } elseif (strpos($endpoint, 'apple.com') !== false || strpos($endpoint, 'push.apple') !== false) {
                $pushService = 'APNS (Apple)';
            }

            $chybyClass = $sub['pocet_chyb'] > 0 ? 'style="color: orange; font-weight: bold;"' : '';

            echo "<tr>
                <td>{$sub['id']}</td>
                <td>" . ($sub['user_id'] ?? '<span style="color:red;">NULL</span>') . "</td>
                <td>{$jmeno}</td>
                <td>{$role}</td>
                <td>{$sub['platforma']}<br><small>{$pushService}</small></td>
                <td class='endpoint' title='{$endpoint}'>{$shortEndpoint}</td>
                <td {$chybyClass}>{$sub['pocet_chyb']}</td>
                <td>" . ($sub['datum_vytvoreni'] ?? '-') . "</td>
                <td>" . ($sub['posledni_uspesne_odeslani'] ?? '<span style=\"color:gray;\">Nikdy</span>') . "</td>
            </tr>";
        }
        echo "</table>";
    }

    // =====================================================
    // 6. KONTROLA TECHNICI/ADMINI
    // =====================================================
    echo "<h2>6. Technici a Admini - Push Status</h2>";

    // POZOR: CAST kvuli collation mismatch
    $stmt = $pdo->query("
        SELECT
            u.user_id,
            u.name,
            u.email,
            u.role,
            COUNT(ps.id) as pocet_subscriptions
        FROM wgs_users u
        LEFT JOIN wgs_push_subscriptions ps ON CAST(u.user_id AS CHAR) = CAST(ps.user_id AS CHAR) COLLATE utf8mb4_unicode_ci AND ps.aktivni = 1
        WHERE u.role IN ('admin', 'technik') AND u.is_active = 1
        GROUP BY u.user_id
        ORDER BY u.role, u.name
    ");
    $techniciAdmini = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($techniciAdmini)) {
        echo "<div class='warning'>Zadni aktivni technici nebo admini!</div>";
    } else {
        echo "<table>
            <tr>
                <th>User ID</th>
                <th>Jmeno</th>
                <th>Email</th>
                <th>Role</th>
                <th>Aktivni Subscriptions</th>
            </tr>";

        $bezSubscription = 0;
        foreach ($techniciAdmini as $user) {
            $status = $user['pocet_subscriptions'] > 0
                ? '<span style="color: green;">Ma ' . $user['pocet_subscriptions'] . ' subscription(s)</span>'
                : '<span style="color: red; font-weight: bold;">ZADNA SUBSCRIPTION!</span>';

            if ($user['pocet_subscriptions'] == 0) {
                $bezSubscription++;
            }

            echo "<tr>
                <td>{$user['user_id']}</td>
                <td>{$user['name']}</td>
                <td>{$user['email']}</td>
                <td>{$user['role']}</td>
                <td>{$status}</td>
            </tr>";
        }
        echo "</table>";

        if ($bezSubscription > 0) {
            echo "<div class='error'>
                <strong>PROBLEM:</strong> {$bezSubscription} technik(u)/admin(u) NEMA zadnou push subscription!<br>
                Tito uzivatele nedostanou push notifikace o novych zakazkach.
            </div>";
        }
    }

    // =====================================================
    // 7. POSLEDNI PUSH LOGY
    // =====================================================
    echo "<h2>7. Posledni Push Logy</h2>";

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_push_log'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("
            SELECT * FROM wgs_push_log
            ORDER BY datum_odeslani DESC
            LIMIT 10
        ");
        $logy = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($logy)) {
            echo "<div class='warning'>Zadne zaznamy v logu - zatim nebyly odeslany zadne push notifikace</div>";
        } else {
            echo "<table>
                <tr>
                    <th>ID</th>
                    <th>Typ</th>
                    <th>Reklamace</th>
                    <th>Titulek</th>
                    <th>Zprava</th>
                    <th>Stav</th>
                    <th>Datum</th>
                </tr>";

            foreach ($logy as $log) {
                $stavClass = $log['stav'] === 'odeslano' ? 'color: green;' : 'color: red;';
                echo "<tr>
                    <td>{$log['id']}</td>
                    <td>{$log['typ_notifikace']}</td>
                    <td>" . ($log['reklamace_id'] ?? '-') . "</td>
                    <td>" . htmlspecialchars($log['titulek'] ?? '') . "</td>
                    <td>" . htmlspecialchars(substr($log['zprava'] ?? '', 0, 50)) . "...</td>
                    <td style='{$stavClass}'>{$log['stav']}</td>
                    <td>{$log['datum_odeslani']}</td>
                </tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<div class='info'>Tabulka wgs_push_log neexistuje - logy nejsou k dispozici</div>";
    }

    // =====================================================
    // 8. TEST ODESLANI
    // =====================================================
    echo "<h2>8. Test Odeslani Push Notifikace</h2>";

    if (isset($_GET['test']) && $_GET['test'] === '1') {
        require_once __DIR__ . '/includes/WebPush.php';

        $webPush = new WGSWebPush($pdo);

        if (!$webPush->jeInicializovano()) {
            echo "<div class='error'>WebPush se nepodarilo inicializovat: {$webPush->getChyba()}</div>";
        } else {
            echo "<div class='success'>WebPush inicializovan uspesne</div>";

            // Odeslat testovaci notifikaci vsem aktivnim
            $payload = [
                'title' => 'WGS Test',
                'body' => 'Testovaci push notifikace - ' . date('H:i:s'),
                'icon' => '/icon192.png',
                'tag' => 'wgs-test-' . time(),
                'typ' => 'test',
                'data' => ['test' => true, 'timestamp' => time()]
            ];

            if (isset($_GET['endpoint'])) {
                // Odeslat na konkretni endpoint
                $endpoint = $_GET['endpoint'];
                $stmt = $pdo->prepare("SELECT endpoint, p256dh, auth FROM wgs_push_subscriptions WHERE id = :id");
                $stmt->execute(['id' => $endpoint]);
                $sub = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($sub) {
                    $vysledek = $webPush->odeslatNotifikaci($sub, $payload);
                    echo "<div class='info'>Vysledek pro subscription #{$endpoint}: <pre>" . json_encode($vysledek, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre></div>";
                } else {
                    echo "<div class='error'>Subscription #{$endpoint} nenalezena</div>";
                }
            } else {
                // Odeslat vsem aktivnim
                $vysledek = $webPush->odeslatVsem($payload);
                echo "<div class='info'>Vysledek broadcast: <pre>" . json_encode($vysledek, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre></div>";
            }
        }
    } else {
        echo "<a href='?test=1' class='btn'>Odeslat Testovaci Notifikaci Vsem</a>";

        // Tlacitka pro jednotlive subscriptions
        if (!empty($subscriptions)) {
            echo "<br><br><strong>Nebo otestovat konkretni subscription:</strong><br>";
            foreach ($subscriptions as $sub) {
                $label = ($sub['uzivatel_jmeno'] ?? $sub['email'] ?? 'ID ' . $sub['id']) . ' (' . $sub['platforma'] . ')';
                echo "<a href='?test=1&endpoint={$sub['id']}' class='btn' style='margin: 5px;'>{$label}</a> ";
            }
        }
    }

    // =====================================================
    // 9. DOPORUCENI
    // =====================================================
    echo "<h2>9. Doporuceni</h2>";

    $problemy = [];

    if (empty($vapidPublic) || empty($vapidPrivate)) {
        $problemy[] = "VAPID klice nejsou nastaveny - spustte <code>setup_web_push.php</code>";
    }

    if ($stats['celkem'] == 0) {
        $problemy[] = "Zadne zaregistrovane subscriptions - uzivatele musi povolit notifikace";
    }

    if ($stats['bez_user_id'] > 0) {
        $problemy[] = "Nektere subscriptions nemaji user_id - nebudou prirazeny k roli";
    }

    if ($bezSubscription > 0) {
        $problemy[] = "Nekteri technici/admini nemaji zaregistrovanou subscription";
    }

    if (empty($problemy)) {
        echo "<div class='success'><strong>Vse vypada v poradku!</strong> Pokud stale nefunguji notifikace, zkontrolujte:
            <ul>
                <li>Browser DevTools > Console pro chyby</li>
                <li>Browser DevTools > Application > Service Workers</li>
                <li>Browser nastaveni > Notifikace pro wgs-service.cz</li>
                <li>Operacni system nastaveni notifikaci</li>
            </ul>
        </div>";
    } else {
        echo "<div class='error'><strong>Nalezene problemy:</strong><ul>";
        foreach ($problemy as $problem) {
            echo "<li>{$problem}</li>";
        }
        echo "</ul></div>";
    }

    echo "<h3>Uzitecne odkazy</h3>
    <ul>
        <li><a href='/admin.php'>Admin Panel</a></li>
        <li><a href='/api/push_subscription_api.php?action=vapid-key' target='_blank'>Test VAPID Key API</a></li>
        <li><a href='/api/push_subscription_api.php?action=stats' target='_blank'>Push Stats API (vyzaduje prihlaseni)</a></li>
    </ul>";

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</div>
</body>
</html>";
?>
