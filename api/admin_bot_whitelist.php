<?php
/**
 * Admin Bot Whitelist API
 *
 * Endpoint pro správu whitelistu legitimních botů (pouze admin)
 *
 * Metody: GET (načtení), POST (přidání/úprava/smazání)
 * Autentizace: Pouze admin
 * CSRF: Vyžadováno pro POST
 * Rate limiting: 50 požadavků/hodinu
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #3 - Bot Detection Engine
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json; charset=utf-8');

// CORS headers
header('Access-Control-Allow-Origin: https://www.wgs-service.cz');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// OPTIONS request pro CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Pouze GET a POST metody
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    sendJsonError('Pouze GET a POST metody jsou povoleny', 405);
}

// Autentizace - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    sendJsonError('Přístup odepřen - pouze admin', 403);
}

// Extrakce dat ze session před uvolněním zámku
$adminId = $_SESSION['user_id'] ?? $_SESSION['email'] ?? 'admin';
$adminEmail = $_SESSION['email'] ?? 'admin';

// PERFORMANCE: Uvolnění session zámku pro paralelní požadavky
session_write_close();

try {
    $pdo = getDbConnection();

    // Rate limiting - 50 požadavků za hodinu per admin
    $rateLimiter = new RateLimiter($pdo);

    $rateLimitResult = $rateLimiter->checkLimit($adminId, 'bot_whitelist_api', [
        'max_attempts' => 50,
        'window_minutes' => 60,
        'block_minutes' => 60
    ]);

    if (!$rateLimitResult['allowed']) {
        sendJsonError($rateLimitResult['message'], 429);
    }

    // ========================================
    // GET - NAČTENÍ WHITELIST ZÁZNAMŮ
    // ========================================
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'list';

        switch ($action) {
            // ------------------------------------------------------------
            // ACTION: list
            // Vrací všechny whitelist záznamy
            // ------------------------------------------------------------
            case 'list':
                $stmt = $pdo->query("
                    SELECT
                        whitelist_id,
                        bot_name,
                        bot_type,
                        ua_pattern,
                        ip_ranges,
                        is_active,
                        added_by,
                        notes,
                        created_at,
                        updated_at
                    FROM wgs_analytics_bot_whitelist
                    ORDER BY bot_name ASC
                ");

                $whitelist = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Dekódovat JSON IP ranges
                foreach ($whitelist as &$item) {
                    $item['ip_ranges'] = $item['ip_ranges'] ? json_decode($item['ip_ranges'], true) : null;
                }

                sendJsonSuccess('Whitelist načten', [
                    'celkem' => count($whitelist),
                    'whitelist' => $whitelist
                ]);
                break;

            // ------------------------------------------------------------
            // ACTION: detail
            // Vrací detail jednoho whitelist záznamu
            // ------------------------------------------------------------
            case 'detail':
                $whitelistId = (int)($_GET['whitelist_id'] ?? 0);

                if ($whitelistId <= 0) {
                    sendJsonError('Chybí parametr whitelist_id', 400);
                }

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM wgs_analytics_bot_whitelist
                    WHERE whitelist_id = :id
                ");

                $stmt->execute(['id' => $whitelistId]);
                $detail = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$detail) {
                    sendJsonError('Whitelist záznam nenalezen', 404);
                }

                // Dekódovat JSON IP ranges
                $detail['ip_ranges'] = $detail['ip_ranges'] ? json_decode($detail['ip_ranges'], true) : null;

                sendJsonSuccess('Detail načten', ['whitelist_item' => $detail]);
                break;

            default:
                sendJsonError('Neplatná akce (povoleno: list, detail)', 400);
        }

    // ========================================
    // POST - PŘIDÁNÍ/ÚPRAVA/SMAZÁNÍ ZÁZNAMU
    // ========================================
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRF validace
        if (!validateCSRFToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            sendJsonError('Neplatný CSRF token', 403);
        }

        $action = $_POST['action'] ?? '';

        switch ($action) {
            // ------------------------------------------------------------
            // ACTION: pridat
            // Přidá nový whitelist záznam
            // ------------------------------------------------------------
            case 'pridat':
                // Validace povinných polí
                $requiredFields = ['bot_name', 'bot_type'];
                foreach ($requiredFields as $field) {
                    if (empty($_POST[$field])) {
                        sendJsonError("Chybí povinné pole: {$field}", 400);
                    }
                }

                $botName = trim($_POST['bot_name']);
                $botType = $_POST['bot_type'];
                $uaPattern = trim($_POST['ua_pattern'] ?? '');
                $ipRanges = $_POST['ip_ranges'] ?? null; // JSON string nebo pole
                $isActive = isset($_POST['is_active']) ? ($_POST['is_active'] === 'true' || $_POST['is_active'] === '1') : true;
                $notes = trim($_POST['notes'] ?? '');
                $addedBy = $adminEmail;

                // Validace bot_type
                $povolenoTypes = ['search_engine', 'social_media', 'monitoring', 'other'];
                if (!in_array($botType, $povolenoTypes)) {
                    sendJsonError('Neplatný bot_type (povoleno: search_engine, social_media, monitoring, other)', 400);
                }

                // Validace a konverze IP ranges
                if (!empty($ipRanges)) {
                    if (is_string($ipRanges)) {
                        // Pokusit se dekódovat JSON
                        $ipRangesArray = json_decode($ipRanges, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            sendJsonError('Neplatný formát ip_ranges (očekáváno JSON pole)', 400);
                        }
                        $ipRanges = json_encode($ipRangesArray);
                    } elseif (is_array($ipRanges)) {
                        $ipRanges = json_encode($ipRanges);
                    } else {
                        $ipRanges = null;
                    }
                } else {
                    $ipRanges = null;
                }

                // INSERT do databáze
                $stmt = $pdo->prepare("
                    INSERT INTO wgs_analytics_bot_whitelist (
                        bot_name,
                        bot_type,
                        ua_pattern,
                        ip_ranges,
                        is_active,
                        added_by,
                        notes
                    ) VALUES (
                        :bot_name,
                        :bot_type,
                        :ua_pattern,
                        :ip_ranges,
                        :is_active,
                        :added_by,
                        :notes
                    )
                ");

                $stmt->execute([
                    'bot_name' => $botName,
                    'bot_type' => $botType,
                    'ua_pattern' => $uaPattern,
                    'ip_ranges' => $ipRanges,
                    'is_active' => $isActive ? 1 : 0,
                    'added_by' => $addedBy,
                    'notes' => $notes
                ]);

                $newId = $pdo->lastInsertId();

                sendJsonSuccess('Whitelist záznam přidán', [
                    'whitelist_id' => $newId,
                    'bot_name' => $botName
                ]);
                break;

            // ------------------------------------------------------------
            // ACTION: upravit
            // Upraví existující whitelist záznam
            // ------------------------------------------------------------
            case 'upravit':
                $whitelistId = (int)($_POST['whitelist_id'] ?? 0);

                if ($whitelistId <= 0) {
                    sendJsonError('Chybí parametr whitelist_id', 400);
                }

                // Kontrola, zda záznam existuje
                $stmt = $pdo->prepare("SELECT * FROM wgs_analytics_bot_whitelist WHERE whitelist_id = :id");
                $stmt->execute(['id' => $whitelistId]);

                if (!$stmt->fetch()) {
                    sendJsonError('Whitelist záznam nenalezen', 404);
                }

                // Příprava UPDATE
                $updateFields = [];
                $updateParams = ['id' => $whitelistId];

                if (isset($_POST['bot_name'])) {
                    $updateFields[] = 'bot_name = :bot_name';
                    $updateParams['bot_name'] = trim($_POST['bot_name']);
                }

                if (isset($_POST['bot_type'])) {
                    $povolenoTypes = ['search_engine', 'social_media', 'monitoring', 'other'];
                    if (!in_array($_POST['bot_type'], $povolenoTypes)) {
                        sendJsonError('Neplatný bot_type', 400);
                    }
                    $updateFields[] = 'bot_type = :bot_type';
                    $updateParams['bot_type'] = $_POST['bot_type'];
                }

                if (isset($_POST['ua_pattern'])) {
                    $updateFields[] = 'ua_pattern = :ua_pattern';
                    $updateParams['ua_pattern'] = trim($_POST['ua_pattern']);
                }

                if (isset($_POST['ip_ranges'])) {
                    $ipRanges = $_POST['ip_ranges'];

                    if (!empty($ipRanges)) {
                        if (is_string($ipRanges)) {
                            $ipRangesArray = json_decode($ipRanges, true);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                sendJsonError('Neplatný formát ip_ranges', 400);
                            }
                            $ipRanges = json_encode($ipRangesArray);
                        } elseif (is_array($ipRanges)) {
                            $ipRanges = json_encode($ipRanges);
                        }
                    } else {
                        $ipRanges = null;
                    }

                    $updateFields[] = 'ip_ranges = :ip_ranges';
                    $updateParams['ip_ranges'] = $ipRanges;
                }

                if (isset($_POST['is_active'])) {
                    $updateFields[] = 'is_active = :is_active';
                    $updateParams['is_active'] = ($_POST['is_active'] === 'true' || $_POST['is_active'] === '1') ? 1 : 0;
                }

                if (isset($_POST['notes'])) {
                    $updateFields[] = 'notes = :notes';
                    $updateParams['notes'] = trim($_POST['notes']);
                }

                if (empty($updateFields)) {
                    sendJsonError('Žádná pole k aktualizaci', 400);
                }

                // UPDATE
                $sql = "UPDATE wgs_analytics_bot_whitelist SET " . implode(', ', $updateFields) . " WHERE whitelist_id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($updateParams);

                sendJsonSuccess('Whitelist záznam aktualizován', ['whitelist_id' => $whitelistId]);
                break;

            // ------------------------------------------------------------
            // ACTION: smazat
            // Smaže whitelist záznam
            // ------------------------------------------------------------
            case 'smazat':
                $whitelistId = (int)($_POST['whitelist_id'] ?? 0);

                if ($whitelistId <= 0) {
                    sendJsonError('Chybí parametr whitelist_id', 400);
                }

                // DELETE
                $stmt = $pdo->prepare("DELETE FROM wgs_analytics_bot_whitelist WHERE whitelist_id = :id");
                $stmt->execute(['id' => $whitelistId]);

                if ($stmt->rowCount() === 0) {
                    sendJsonError('Whitelist záznam nenalezen', 404);
                }

                sendJsonSuccess('Whitelist záznam smazán', ['whitelist_id' => $whitelistId]);
                break;

            // ------------------------------------------------------------
            // ACTION: aktivovat/deaktivovat
            // Rychlé toggle is_active
            // ------------------------------------------------------------
            case 'toggle_aktivace':
                $whitelistId = (int)($_POST['whitelist_id'] ?? 0);

                if ($whitelistId <= 0) {
                    sendJsonError('Chybí parametr whitelist_id', 400);
                }

                // Toggle is_active
                $stmt = $pdo->prepare("
                    UPDATE wgs_analytics_bot_whitelist
                    SET is_active = NOT is_active
                    WHERE whitelist_id = :id
                ");

                $stmt->execute(['id' => $whitelistId]);

                if ($stmt->rowCount() === 0) {
                    sendJsonError('Whitelist záznam nenalezen', 404);
                }

                // Načíst nový stav
                $stmt = $pdo->prepare("SELECT is_active FROM wgs_analytics_bot_whitelist WHERE whitelist_id = :id");
                $stmt->execute(['id' => $whitelistId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                sendJsonSuccess('Aktivace změněna', [
                    'whitelist_id' => $whitelistId,
                    'is_active' => (bool)$result['is_active']
                ]);
                break;

            default:
                sendJsonError('Neplatná akce (povoleno: pridat, upravit, smazat, toggle_aktivace)', 400);
        }
    }

} catch (PDOException $e) {
    // Logování chyby bez expozice detailů
    error_log('Admin Bot Whitelist API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    sendJsonError('Chyba při zpracování požadavku', 500);

} catch (Exception $e) {
    // Obecná chyba
    error_log('Admin Bot Whitelist API Unexpected Error: ' . $e->getMessage());
    sendJsonError('Neočekávaná chyba serveru', 500);
}
?>
