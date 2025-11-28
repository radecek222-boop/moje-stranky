<?php
/**
 * Diagnostika Push Notifikaci
 *
 * Kontroluje stav subscriptions, WebPush inicializaci a testuje odeslani.
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/WebPush.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika Push Notifikaci</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1200px;
               margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .success { background: #d4edda; color: #155724; padding: 12px;
                   border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 12px;
                 border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 12px;
                   border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px;
                border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #333;
               color: white; text-decoration: none; border-radius: 5px;
               margin: 10px 5px 10px 0; border: none; cursor: pointer; }
        .btn:hover { background: #555; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left;
                 font-size: 13px; }
        th { background: #f0f0f0; }
        tr:nth-child(even) { background: #f9f9f9; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px;
              overflow-x: auto; border: 1px solid #ddd; font-size: 12px; }
        code { background: #eee; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Diagnostika Push Notifikaci</h1>";

    // ========================================
    // 1. KONTROLA VAPID KLICU
    // ========================================
    echo "<h2>1. VAPID Klice</h2>";

    $vapidPublic = $_ENV['VAPID_PUBLIC_KEY'] ?? getenv('VAPID_PUBLIC_KEY') ?? '';
    $vapidPrivate = $_ENV['VAPID_PRIVATE_KEY'] ?? getenv('VAPID_PRIVATE_KEY') ?? '';

    if (empty($vapidPublic) || empty($vapidPrivate)) {
        echo "<div class='error'>VAPID klice nejsou nastaveny v .env!</div>";
        echo "<pre>Pridejte do .env:\nVAPID_PUBLIC_KEY=...\nVAPID_PRIVATE_KEY=...</pre>";
    } else {
        echo "<div class='success'>VAPID klice jsou nastaveny</div>";
        echo "<pre>Public key: " . htmlspecialchars(substr($vapidPublic, 0, 30)) . "...</pre>";
    }

    // ========================================
    // 2. KONTROLA WEBPUSH KNIHOVNY
    // ========================================
    echo "<h2>2. WebPush Knihovna</h2>";

    if (class_exists('Minishlink\WebPush\WebPush')) {
        echo "<div class='success'>Knihovna minishlink/web-push je nainstalovana</div>";
    } else {
        echo "<div class='error'>Knihovna minishlink/web-push NENI nainstalovana!</div>";
        echo "<pre>Spustte: composer require minishlink/web-push</pre>";
    }

    // ========================================
    // 3. KONTROLA TABULKY wgs_push_subscriptions
    // ========================================
    echo "<h2>3. Tabulka wgs_push_subscriptions</h2>";

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_push_subscriptions'");
    if ($stmt->rowCount() === 0) {
        echo "<div class='error'>Tabulka wgs_push_subscriptions NEEXISTUJE!</div>";
    } else {
        echo "<div class='success'>Tabulka existuje</div>";

        // Struktura
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_push_subscriptions");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<h3>Struktura tabulky:</h3>";
        echo "<table><tr><th>Sloupec</th><th>Typ</th><th>Null</th></tr>";
        foreach ($columns as $col) {
            $highlight = ($col['Field'] === 'user_id') ? 'style="background:#fff3cd;"' : '';
            echo "<tr {$highlight}>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Zkontrolovat user_id typ
        $userIdCol = array_filter($columns, fn($c) => $c['Field'] === 'user_id');
        $userIdCol = reset($userIdCol);
        if ($userIdCol && stripos($userIdCol['Type'], 'varchar') !== false) {
            echo "<div class='success'>user_id je VARCHAR - OK</div>";
        } else {
            echo "<div class='error'>user_id NENI VARCHAR! Typ: " . htmlspecialchars($userIdCol['Type'] ?? 'neznamy') . "</div>";
        }
    }

    // ========================================
    // 4. VSECHNY SUBSCRIPTIONS
    // ========================================
    echo "<h2>4. Aktivni Subscriptions</h2>";

    // COLLATE pro reseni rozdilnych kolaci mezi tabulkami
    $stmt = $pdo->query("
        SELECT ps.*, u.name as user_name, u.role as user_role
        FROM wgs_push_subscriptions ps
        LEFT JOIN wgs_users u ON ps.user_id COLLATE utf8mb4_unicode_ci = u.user_id
        ORDER BY ps.datum_vytvoreni DESC
    ");
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($subscriptions)) {
        echo "<div class='warning'>Zadne subscriptions v databazi!</div>";
        echo "<div class='info'>Pro ziskani subscription:</div>";
        echo "<ol>";
        echo "<li>Prihlaste se do aplikace</li>";
        echo "<li>Povolte notifikace (dialog nebo v nastaveni)</li>";
        echo "<li>Subscription se automaticky ulozi</li>";
        echo "</ol>";
    } else {
        echo "<div class='info'>Nalezeno " . count($subscriptions) . " subscriptions</div>";

        echo "<table>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th>user_id</th>";
        echo "<th>Uzivatel</th>";
        echo "<th>Role</th>";
        echo "<th>Platforma</th>";
        echo "<th>Aktivni</th>";
        echo "<th>Chyby</th>";
        echo "<th>Posledni odeslani</th>";
        echo "<th>Endpoint (zkraceny)</th>";
        echo "</tr>";

        foreach ($subscriptions as $sub) {
            $aktivniClass = $sub['aktivni'] ? 'style="color:green;"' : 'style="color:red;"';
            $userIdDisplay = $sub['user_id'] ?: '<span style="color:#999;">NULL</span>';

            echo "<tr>";
            echo "<td>{$sub['id']}</td>";
            echo "<td>{$userIdDisplay}</td>";
            echo "<td>" . htmlspecialchars($sub['user_name'] ?? $sub['email'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($sub['user_role'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($sub['platforma'] ?? '-') . "</td>";
            echo "<td {$aktivniClass}>" . ($sub['aktivni'] ? 'ANO' : 'NE') . "</td>";
            echo "<td>" . ($sub['pocet_chyb'] ?? 0) . "</td>";
            echo "<td>" . ($sub['posledni_uspesne_odeslani'] ?? '-') . "</td>";
            echo "<td><code>" . htmlspecialchars(substr($sub['endpoint'], 0, 60)) . "...</code></td>";
            echo "</tr>";
        }
        echo "</table>";

        // Statistiky
        $aktivni = array_filter($subscriptions, fn($s) => $s['aktivni']);
        $sUserId = array_filter($subscriptions, fn($s) => !empty($s['user_id']));
        $bezUserId = array_filter($subscriptions, fn($s) => empty($s['user_id']));

        echo "<div class='info'>";
        echo "Aktivni: " . count($aktivni) . " | ";
        echo "S user_id: " . count($sUserId) . " | ";
        echo "Bez user_id: " . count($bezUserId);
        echo "</div>";

        if (count($bezUserId) > 0) {
            echo "<div class='warning'>";
            echo "<strong>POZOR:</strong> " . count($bezUserId) . " subscriptions nema user_id! ";
            echo "Uzivatele se musi znovu prihlasit a povolit notifikace.";
            echo "</div>";
        }
    }

    // ========================================
    // 5. WEBPUSH INICIALIZACE
    // ========================================
    echo "<h2>5. Test WebPush Inicializace</h2>";

    $webPush = new WGSWebPush($pdo);

    if ($webPush->jeInicializovano()) {
        echo "<div class='success'>WebPush je inicializovan a pripraven k odeslani</div>";
    } else {
        echo "<div class='error'>WebPush NENI inicializovan!</div>";
        echo "<pre>Chyba: " . htmlspecialchars($webPush->getChyba()) . "</pre>";
    }

    // ========================================
    // 6. TEST ODESLANI
    // ========================================
    echo "<h2>6. Test Odeslani</h2>";

    if (isset($_GET['test']) && $_GET['test'] === '1') {
        if (!$webPush->jeInicializovano()) {
            echo "<div class='error'>Nelze testovat - WebPush neni inicializovan</div>";
        } else {
            $payload = [
                'title' => 'WGS Test',
                'body' => 'Testovaci push notifikace - ' . date('H:i:s'),
                'icon' => '/icon192.png',
                'tag' => 'wgs-test-' . time(),
                'data' => ['test' => true, 'timestamp' => time()]
            ];

            echo "<div class='info'>Odesilam testovaci notifikaci vsem aktivnim subscriptions...</div>";
            echo "<pre>Payload: " . htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";

            $vysledek = $webPush->odeslatVsem($payload);

            if ($vysledek['uspech']) {
                echo "<div class='success'>";
                echo "<strong>Vysledek:</strong><br>";
                echo "Odeslano: " . ($vysledek['odeslano'] ?? 0) . "<br>";
                echo "Chyby: " . ($vysledek['chyby'] ?? 0) . "<br>";
                echo "Neplatne: " . ($vysledek['neplatne'] ?? 0);
                echo "</div>";
            } else {
                echo "<div class='error'>";
                echo "<strong>Chyba:</strong> " . htmlspecialchars($vysledek['zprava']);
                echo "</div>";
            }
        }
    } else {
        $aktivniCount = count(array_filter($subscriptions ?? [], fn($s) => $s['aktivni']));
        if ($aktivniCount > 0) {
            echo "<a href='?test=1' class='btn'>Odeslat testovaci notifikaci ({$aktivniCount} zarizeni)</a>";
        } else {
            echo "<div class='warning'>Zadne aktivni subscriptions - nelze testovat</div>";
        }
    }

    // ========================================
    // 7. KONTROLA LOGU
    // ========================================
    echo "<h2>7. Posledni Push Logy</h2>";

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_push_log'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("
            SELECT * FROM wgs_push_log
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $logy = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($logy)) {
            echo "<div class='info'>Zadne push logy</div>";
        } else {
            echo "<table>";
            echo "<tr><th>Cas</th><th>Typ</th><th>Titulek</th><th>Stav</th></tr>";
            foreach ($logy as $log) {
                $stavClass = $log['stav'] === 'odeslano' ? 'color:green;' : 'color:red;';
                echo "<tr>";
                echo "<td>" . htmlspecialchars($log['created_at']) . "</td>";
                echo "<td>" . htmlspecialchars($log['typ_notifikace'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($log['titulek'] ?? '-') . "</td>";
                echo "<td style='{$stavClass}'>" . htmlspecialchars($log['stav']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<div class='info'>Tabulka wgs_push_log neexistuje</div>";
    }

    // ========================================
    // NAVOD
    // ========================================
    echo "<h2>8. Reseni problemu</h2>";
    echo "<div class='info'>";
    echo "<strong>Pokud notifikace nechodí:</strong><br><br>";
    echo "1. <strong>Zkontrolujte subscriptions:</strong> Kazdy uzivatel/zarizeni musi mit aktivni subscription s vyplnenym user_id<br><br>";
    echo "2. <strong>Re-registrace subscriptions:</strong> Po zmene user_id typu je potreba se znovu prihlasit a povolit notifikace<br><br>";
    echo "3. <strong>Kontrola v prohlizeci:</strong> Otevrete DevTools > Application > Service Workers a zkontrolujte ze SW je aktivni<br><br>";
    echo "4. <strong>Kontrola opravneni:</strong> Zkontrolujte ze notifikace jsou povoleny v nastaveni prohlizece/systemu<br><br>";
    echo "5. <strong>Test:</strong> Kliknete na tlacitko 'Odeslat testovaci notifikaci' vyse";
    echo "</div>";

    echo "<br><a href='admin.php' class='btn' style='background:#666;'>Zpet do Admin</a>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
