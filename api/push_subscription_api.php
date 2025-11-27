<?php
/**
 * Push Subscription API
 *
 * Endpointy pro spravu Web Push subscriptions:
 * - POST subscribe: Registrace nove subscription
 * - POST unsubscribe: Zruseni subscription
 * - GET vapid-key: Ziskat VAPID public key pro frontend
 * - GET status: Zkontrolovat stav subscription
 * - POST test: Odeslat testovaci notifikaci
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/WebPush.php';

header('Content-Type: application/json; charset=utf-8');

// Ziskat akci
$akce = $_GET['action'] ?? $_POST['action'] ?? '';

// VAPID key nepotrebuje CSRF (GET request pro frontend)
if ($akce === 'vapid-key' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $vapidKey = WGSWebPush::getVapidPublicKey();

    if (empty($vapidKey)) {
        sendJsonError('VAPID klice nejsou nakonfigurovany', 500);
    }

    sendJsonSuccess('VAPID public key', ['vapidPublicKey' => $vapidKey]);
    exit;
}

// Ostatni akce vyzaduji POST a CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonError('Metoda neni povolena', 405);
}

// CSRF validace
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    sendJsonError('Neplatny CSRF token', 403);
}

try {
    $pdo = getDbConnection();
    $webPush = new WGSWebPush($pdo);

    // Ziskat user ID z session (pokud je prihlasen)
    $userId = $_SESSION['user_id'] ?? null;

    switch ($akce) {

        case 'subscribe':
            // Registrace nove subscription
            $subscriptionJson = $_POST['subscription'] ?? '';

            if (empty($subscriptionJson)) {
                sendJsonError('Chybi subscription data');
            }

            $subscription = json_decode($subscriptionJson, true);

            if (!$subscription || empty($subscription['endpoint'])) {
                sendJsonError('Neplatna subscription data');
            }

            // Pridat uzivatelska data
            $subscription['user_id'] = $userId;
            $subscription['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $subscription['platforma'] = $_POST['platforma'] ?? null;

            $vysledek = $webPush->ulozitSubscription($subscription);

            if ($vysledek['uspech']) {
                sendJsonSuccess($vysledek['zprava'], ['id' => $vysledek['id'] ?? null]);
            } else {
                sendJsonError($vysledek['zprava']);
            }
            break;

        case 'unsubscribe':
            // Zruseni subscription
            $endpoint = $_POST['endpoint'] ?? '';

            if (empty($endpoint)) {
                sendJsonError('Chybi endpoint');
            }

            $vysledek = $webPush->odstranSubscription($endpoint);

            if ($vysledek['uspech']) {
                sendJsonSuccess($vysledek['zprava']);
            } else {
                sendJsonError($vysledek['zprava']);
            }
            break;

        case 'status':
            // Zkontrolovat stav (pokud uzivatel ma aktivni subscription)
            if (!$userId) {
                sendJsonSuccess('Uzivatel neni prihlasen', ['prihlasen' => false, 'subscriptions' => 0]);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT COUNT(*) as pocet FROM wgs_push_subscriptions
                WHERE user_id = :user_id AND aktivni = 1
            ");
            $stmt->execute(['user_id' => $userId]);
            $pocet = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];

            sendJsonSuccess('Stav subscriptions', [
                'prihlasen' => true,
                'subscriptions' => (int)$pocet
            ]);
            break;

        case 'test':
            // Odeslat testovaci notifikaci (pouze admin)
            if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
                sendJsonError('Pristup odepren - pouze admin', 403);
            }

            if (!$webPush->jeInicializovano()) {
                sendJsonError('WebPush neni inicializovan: ' . $webPush->getChyba());
            }

            $endpoint = $_POST['endpoint'] ?? '';
            $payload = [
                'title' => 'WGS Test',
                'body' => 'Testovaci push notifikace - ' . date('H:i:s'),
                'icon' => '/icon192.png',
                'tag' => 'wgs-test-' . time(),
                'data' => ['test' => true, 'timestamp' => time()]
            ];

            if (!empty($endpoint)) {
                // Odeslat na konkretni endpoint
                $stmt = $pdo->prepare("
                    SELECT endpoint, p256dh, auth FROM wgs_push_subscriptions WHERE endpoint = :endpoint
                ");
                $stmt->execute(['endpoint' => $endpoint]);
                $sub = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$sub) {
                    sendJsonError('Subscription nenalezena');
                }

                $vysledek = $webPush->odeslatNotifikaci($sub, $payload);

            } else {
                // Odeslat vsem aktivnim
                $vysledek = $webPush->odeslatVsem($payload);
            }

            if ($vysledek['uspech']) {
                sendJsonSuccess('Testovaci notifikace odeslana', $vysledek);
            } else {
                sendJsonError($vysledek['zprava'], 500);
            }
            break;

        case 'stats':
            // Statistiky (pouze admin)
            if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
                sendJsonError('Pristup odepren - pouze admin', 403);
            }

            $statistiky = $webPush->getStatistiky();

            if (empty($statistiky)) {
                sendJsonSuccess('Statistiky', [
                    'celkem' => 0,
                    'aktivni' => 0,
                    'ios' => 0,
                    'android' => 0,
                    'desktop' => 0
                ]);
            } else {
                sendJsonSuccess('Statistiky', $statistiky);
            }
            break;

        default:
            sendJsonError('Neznama akce: ' . $akce);
    }

} catch (PDOException $e) {
    error_log('[PushAPI] DB chyba: ' . $e->getMessage());
    sendJsonError('Chyba databaze');
} catch (Exception $e) {
    error_log('[PushAPI] Chyba: ' . $e->getMessage());
    sendJsonError('Interni chyba serveru');
}
