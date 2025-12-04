<?php
/**
 * Analytics API - Správa blokovaných IP adres
 *
 * Akce:
 * - action=add_blocked_ip = přidat IP do blacklistu (POST)
 * - action=remove_blocked_ip = odebrat IP z blacklistu (POST)
 * - action=add_my_ip = přidat vlastní IP do blacklistu (POST)
 *
 * POZNÁMKA: Analytics data se načítají přímo v analytics.php (server-side),
 * toto API slouží pouze pro správu blokovaných IP adres.
 */

// Error handling - zachytit všechny chyby
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    require_once __DIR__ . '/../init.php';
    require_once __DIR__ . '/../includes/csrf_helper.php';

    header('Content-Type: application/json; charset=utf-8');

    // BEZPEČNOST: Kontrola admin přihlášení
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Přístup odepřen. Pouze pro administrátory.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = $_GET['action'] ?? '';

    if ($action === '') {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Chybí parametr action. Dostupné akce: add_blocked_ip, remove_blocked_ip, add_my_ip'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    zpracujIpAkci($action);

} catch (Throwable $e) {
    // Zachytit jakoukoliv chybu a vrátit JSON
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'message' => 'Interní chyba serveru',
        'debug' => [
            'error' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
    error_log("Analytics API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    exit;
}

/**
 * Zpracuje akce pro správu blokovaných IP adres
 */
function zpracujIpAkci(string $action): void
{
    $pdo = getDbConnection();

    // Zajistit existenci tabulky
    zajistiTabulkuIgnoredIps($pdo);

    switch ($action) {
        case 'add_blocked_ip':
            // POST - přidat IP
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Pouze POST metoda'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            // CSRF validace
            if (!validateCSRFToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Neplatný CSRF token'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $ipAddress = trim($input['ip_address'] ?? '');
            $reason = trim($input['reason'] ?? 'Manuálně přidáno');

            // Validace IP adresy
            if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Neplatná IP adresa'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Kontrola duplicity
            $checkStmt = $pdo->prepare("SELECT id FROM wgs_analytics_ignored_ips WHERE ip_address = :ip");
            $checkStmt->execute([':ip' => $ipAddress]);
            if ($checkStmt->fetch()) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'IP adresa již existuje v blacklistu'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Přidat IP
            $stmt = $pdo->prepare("
                INSERT INTO wgs_analytics_ignored_ips (ip_address, reason, created_at)
                VALUES (:ip, :reason, NOW())
            ");
            $stmt->execute([':ip' => $ipAddress, ':reason' => $reason]);

            // Anonymizovat IP pro vyhledávání v pageviews (GDPR - ukládáme anonymizované)
            $anonymizovanaIp = $ipAddress;
            if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $parts = explode('.', $ipAddress);
                $parts[3] = '0';
                $anonymizovanaIp = implode('.', $parts);
            }

            // Smazat existující záznamy z pageviews pro tuto IP (hledat i anonymizovanou verzi)
            $smazanoZaznamu = 0;
            try {
                $deleteStmt = $pdo->prepare("DELETE FROM wgs_pageviews WHERE ip_address = :ip OR ip_address = :anon_ip");
                $deleteStmt->execute([':ip' => $ipAddress, ':anon_ip' => $anonymizovanaIp]);
                $smazanoZaznamu = $deleteStmt->rowCount();
            } catch (PDOException $e) {
                // Sloupec ip_address nemusí existovat - ignorovat
            }

            error_log("Analytics: IP $ipAddress přidána do blacklistu. Důvod: $reason. Smazáno záznamů: $smazanoZaznamu");

            echo json_encode([
                'status' => 'success',
                'message' => "IP adresa $ipAddress byla přidána do blacklistu" . ($smazanoZaznamu > 0 ? " (smazáno $smazanoZaznamu záznamů)" : "")
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'remove_blocked_ip':
            // POST - odebrat IP
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Pouze POST metoda'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            // CSRF validace
            if (!validateCSRFToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Neplatný CSRF token'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $ipId = (int)($input['id'] ?? 0);

            if ($ipId <= 0) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Neplatné ID'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Získat IP pro log
            $getStmt = $pdo->prepare("SELECT ip_address FROM wgs_analytics_ignored_ips WHERE id = :id");
            $getStmt->execute([':id' => $ipId]);
            $row = $getStmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'IP adresa nenalezena'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Smazat
            $stmt = $pdo->prepare("DELETE FROM wgs_analytics_ignored_ips WHERE id = :id");
            $stmt->execute([':id' => $ipId]);

            error_log("Analytics: IP {$row['ip_address']} odebrána z blacklistu");

            echo json_encode([
                'status' => 'success',
                'message' => "IP adresa {$row['ip_address']} byla odebrána z blacklistu"
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'add_my_ip':
            // POST - přidat vlastní IP
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Pouze POST metoda'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            // CSRF validace
            if (!validateCSRFToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Neplatný CSRF token'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Získat IP uživatele
            $myIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

            // Pokud je více IP (proxy), vzít první
            if (strpos($myIp, ',') !== false) {
                $myIp = trim(explode(',', $myIp)[0]);
            }

            if (!filter_var($myIp, FILTER_VALIDATE_IP)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Nelze určit vaši IP adresu'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Kontrola duplicity
            $checkStmt = $pdo->prepare("SELECT id FROM wgs_analytics_ignored_ips WHERE ip_address = :ip");
            $checkStmt->execute([':ip' => $myIp]);
            if ($checkStmt->fetch()) {
                echo json_encode([
                    'status' => 'success',
                    'message' => "Vaše IP adresa $myIp již je v blacklistu"
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Přidat IP
            $stmt = $pdo->prepare("
                INSERT INTO wgs_analytics_ignored_ips (ip_address, reason, created_at)
                VALUES (:ip, :reason, NOW())
            ");
            $stmt->execute([':ip' => $myIp, ':reason' => 'Vlastní IP administrátora']);

            // Anonymizovat IP pro vyhledávání v pageviews (GDPR - ukládáme anonymizované)
            $anonymizovanaIp = $myIp;
            if (filter_var($myIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $parts = explode('.', $myIp);
                $parts[3] = '0';
                $anonymizovanaIp = implode('.', $parts);
            }

            // Smazat existující záznamy z pageviews pro tuto IP (hledat i anonymizovanou verzi)
            $smazanoZaznamu = 0;
            try {
                $deleteStmt = $pdo->prepare("DELETE FROM wgs_pageviews WHERE ip_address = :ip OR ip_address = :anon_ip");
                $deleteStmt->execute([':ip' => $myIp, ':anon_ip' => $anonymizovanaIp]);
                $smazanoZaznamu = $deleteStmt->rowCount();
            } catch (PDOException $e) {
                // Sloupec ip_address nemusí existovat - ignorovat
            }

            error_log("Analytics: Vlastní IP $myIp přidána do blacklistu. Smazáno záznamů: $smazanoZaznamu");

            echo json_encode([
                'status' => 'success',
                'message' => "Vaše IP adresa $myIp byla přidána do blacklistu" . ($smazanoZaznamu > 0 ? " (smazáno $smazanoZaznamu záznamů)" : ""),
                'ip' => $myIp
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Neznámá akce. Dostupné akce: add_blocked_ip, remove_blocked_ip, add_my_ip'
            ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Zajistí existenci tabulky pro ignorované IP adresy
 */
function zajistiTabulkuIgnoredIps(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wgs_analytics_ignored_ips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_ip (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}
