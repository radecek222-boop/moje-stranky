<?php
/**
 * Detailni test push notifikaci
 * Zobrazi presne chybove hlasky pro kazdy endpoint
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/WebPush.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator.");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Detailni Test Push</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #0f0; }
        .error { color: #f66; }
        .success { color: #6f6; }
        .warning { color: #ff0; }
        .info { color: #6cf; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background: #333; }
        .endpoint { max-width: 300px; overflow: hidden; text-overflow: ellipsis; }
    </style>
</head>
<body>
<h1>Detailni Test Push Notifikaci</h1>
<p>Cas: " . date('Y-m-d H:i:s') . "</p>";

try {
    $pdo = getDbConnection();

    // Nacist VAPID klice
    $vapidPublic = $_ENV['VAPID_PUBLIC_KEY'] ?? getenv('VAPID_PUBLIC_KEY') ?? '';
    $vapidPrivate = $_ENV['VAPID_PRIVATE_KEY'] ?? getenv('VAPID_PRIVATE_KEY') ?? '';
    $vapidSubject = $_ENV['VAPID_SUBJECT'] ?? getenv('VAPID_SUBJECT') ?? 'mailto:info@wgs-service.cz';

    if (empty($vapidPublic) || empty($vapidPrivate)) {
        die("<p class='error'>VAPID klice nejsou nastaveny!</p>");
    }

    // Inicializovat WebPush primo
    require_once __DIR__ . '/vendor/autoload.php';

    $auth = [
        'VAPID' => [
            'subject' => $vapidSubject,
            'publicKey' => $vapidPublic,
            'privateKey' => $vapidPrivate,
        ],
    ];

    // Client options - bez SSL verifikace (kvuli Apple)
    $clientOptions = [
        'verify' => false,
        'curl' => [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ],
    ];

    $webPush = new \Minishlink\WebPush\WebPush($auth, [], 30, $clientOptions);
    $webPush->setReuseVAPIDHeaders(true);

    echo "<p class='success'>WebPush inicializovan</p>";

    // Limit na 10 subscriptions pro test
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

    // Nacist subscriptions - rozdelene podle typu
    $stmt = $pdo->prepare("
        SELECT id, endpoint, p256dh, auth, platforma, user_id,
               CASE
                   WHEN endpoint LIKE '%fcm.googleapis.com%' THEN 'FCM'
                   WHEN endpoint LIKE '%mozilla%' THEN 'Mozilla'
                   WHEN endpoint LIKE '%push.apple%' OR endpoint LIKE '%web.push.apple%' THEN 'APNS'
                   WHEN endpoint LIKE '%windows%' THEN 'WNS'
                   ELSE 'Unknown'
               END as push_service
        FROM wgs_push_subscriptions
        WHERE aktivni = 1
        ORDER BY
            CASE
                WHEN endpoint LIKE '%fcm.googleapis.com%' THEN 1
                WHEN endpoint LIKE '%mozilla%' THEN 2
                WHEN endpoint LIKE '%push.apple%' OR endpoint LIKE '%web.push.apple%' THEN 3
                ELSE 4
            END,
            datum_vytvoreni DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p class='info'>Testuji {$limit} subscriptions...</p>";

    // Statistiky podle typu
    $statsByType = [];
    foreach ($subscriptions as $sub) {
        $type = $sub['push_service'];
        if (!isset($statsByType[$type])) {
            $statsByType[$type] = ['total' => 0, 'success' => 0, 'errors' => []];
        }
        $statsByType[$type]['total']++;
    }

    // Pripravit payload
    $payload = json_encode([
        'title' => 'WGS Test',
        'body' => 'Detailni test - ' . date('H:i:s'),
        'icon' => '/icon192.png',
        'tag' => 'wgs-test-detail-' . time(),
        'data' => ['test' => true]
    ], JSON_UNESCAPED_UNICODE);

    // Odeslat a sbrat vysledky
    echo "<h2>Vysledky</h2>";
    echo "<table>
        <tr>
            <th>ID</th>
            <th>Push Service</th>
            <th>Endpoint</th>
            <th>Status</th>
            <th>Detail</th>
        </tr>";

    $results = [];

    foreach ($subscriptions as $sub) {
        try {
            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $sub['endpoint'],
                'publicKey' => $sub['p256dh'],
                'authToken' => $sub['auth'],
            ]);

            $webPush->queueNotification($subscription, $payload);
        } catch (Exception $e) {
            $results[] = [
                'id' => $sub['id'],
                'service' => $sub['push_service'],
                'endpoint' => $sub['endpoint'],
                'success' => false,
                'reason' => 'Queue error: ' . $e->getMessage()
            ];
        }
    }

    // Flush a ziskat vysledky
    $i = 0;
    foreach ($webPush->flush() as $report) {
        $sub = $subscriptions[$i] ?? null;
        $i++;

        if (!$sub) continue;

        $endpoint = $report->getRequest()->getUri()->__toString();
        $isSuccess = $report->isSuccess();
        $reason = $report->getReason() ?? '';
        $isExpired = $report->isSubscriptionExpired();

        // HTTP response info
        $response = $report->getResponse();
        $statusCode = $response ? $response->getStatusCode() : 'N/A';
        $responseBody = $response ? (string)$response->getBody() : '';

        $results[] = [
            'id' => $sub['id'],
            'service' => $sub['push_service'],
            'endpoint' => $sub['endpoint'],
            'success' => $isSuccess,
            'expired' => $isExpired,
            'reason' => $reason,
            'status_code' => $statusCode,
            'response_body' => substr($responseBody, 0, 200)
        ];

        // Update stats
        if ($isSuccess) {
            $statsByType[$sub['push_service']]['success']++;
        } else {
            $statsByType[$sub['push_service']]['errors'][] = $reason;
        }
    }

    // Zobrazit vysledky
    foreach ($results as $r) {
        $statusClass = $r['success'] ? 'success' : 'error';
        $statusText = $r['success'] ? 'OK' : 'CHYBA';
        $shortEndpoint = substr($r['endpoint'], 0, 50) . '...';

        $detail = '';
        if (!$r['success']) {
            $detail = htmlspecialchars($r['reason'] ?? 'Unknown');
            if (isset($r['status_code']) && $r['status_code'] !== 'N/A') {
                $detail .= " (HTTP {$r['status_code']})";
            }
            if (isset($r['expired']) && $r['expired']) {
                $detail .= " [EXPIRED]";
            }
        }

        echo "<tr>
            <td>{$r['id']}</td>
            <td>{$r['service']}</td>
            <td class='endpoint' title='" . htmlspecialchars($r['endpoint']) . "'>{$shortEndpoint}</td>
            <td class='{$statusClass}'>{$statusText}</td>
            <td class='{$statusClass}'>{$detail}</td>
        </tr>";
    }

    echo "</table>";

    // Souhrn podle typu
    echo "<h2>Souhrn podle Push Service</h2>";
    echo "<table>
        <tr><th>Service</th><th>Celkem</th><th>Uspech</th><th>Chyby</th><th>Nejcastejsi chyba</th></tr>";

    foreach ($statsByType as $type => $stats) {
        $errorCount = $stats['total'] - $stats['success'];
        $mostCommonError = '';
        if (!empty($stats['errors'])) {
            $errorCounts = array_count_values($stats['errors']);
            arsort($errorCounts);
            $mostCommonError = htmlspecialchars(key($errorCounts));
        }

        $successClass = $stats['success'] > 0 ? 'success' : '';
        $errorClass = $errorCount > 0 ? 'error' : '';

        echo "<tr>
            <td>{$type}</td>
            <td>{$stats['total']}</td>
            <td class='{$successClass}'>{$stats['success']}</td>
            <td class='{$errorClass}'>{$errorCount}</td>
            <td class='error'>{$mostCommonError}</td>
        </tr>";
    }
    echo "</table>";

    // Doporuceni
    echo "<h2>Analyza</h2>";

    // Zkontrolovat APNS problemy
    $apnsErrors = $statsByType['APNS']['errors'] ?? [];
    if (!empty($apnsErrors)) {
        $uniqueErrors = array_unique($apnsErrors);
        echo "<div class='warning'><strong>Apple Push (APNS) problemy:</strong><br>";
        foreach ($uniqueErrors as $err) {
            echo "- " . htmlspecialchars($err) . "<br>";
        }
        echo "</div>";

        // Specificke doporuceni podle chyby
        $errStr = implode(' ', $apnsErrors);
        if (strpos($errStr, 'certificate') !== false || strpos($errStr, 'SSL') !== false) {
            echo "<p class='info'>Problem s SSL certifikatem. Hosting neumi overit Apple certifikaty.</p>";
        }
        if (strpos($errStr, '410') !== false || strpos($errStr, 'Gone') !== false) {
            echo "<p class='info'>Subscription je expirována (HTTP 410). Safari vytváří nové subscription při každém spuštění.</p>";
        }
        if (strpos($errStr, '403') !== false || strpos($errStr, 'Forbidden') !== false) {
            echo "<p class='info'>Pristup odepren (HTTP 403). Mozna problem s VAPID klicem nebo endpoint.</p>";
        }
    }

    echo "<p><a href='?limit=50' style='color: #6cf;'>Testovat 50 subscriptions</a> | ";
    echo "<a href='/diagnostika_push_notifikace.php' style='color: #6cf;'>Zpet na diagnostiku</a></p>";

} catch (Exception $e) {
    echo "<p class='error'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";
?>
