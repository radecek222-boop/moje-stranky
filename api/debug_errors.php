<?php
/**
 * Diagnostika: Zobrazení error logů
 *
 * Pouze pro admina - zobrazí poslední PHP a JS chyby z dnešního dne.
 * URL: /api/debug_errors.php
 */

require_once __DIR__ . '/../init.php';

// Bezpečnost - pouze admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    http_response_code(403);
    die(json_encode(['error' => 'Přístup odepřen - pouze pro admina']));
}

header('Content-Type: application/json; charset=utf-8');

$dnesniDatum = date('Y-m-d');
$result = [
    'datum' => $dnesniDatum,
    'cas_kontroly' => date('H:i:s'),
    'php_errors' => [],
    'js_errors' => [],
    'souhrn' => []
];

// 1. PHP Error Log
$phpLogPath = __DIR__ . '/../logs/php_errors.log';
if (file_exists($phpLogPath)) {
    $logContent = file_get_contents($phpLogPath);
    $lines = explode("\n", $logContent);

    // Filtrovat pouze dnešní záznamy
    $dnesniChyby = [];
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;

        // Hledat datum v různých formátech
        if (strpos($line, $dnesniDatum) !== false ||
            strpos($line, date('d-M-Y')) !== false ||
            preg_match('/\[' . date('d') . '-[A-Za-z]+-' . date('Y') . '/', $line)) {
            $dnesniChyby[] = $line;
        }
    }

    // Vzít posledních 50
    $result['php_errors'] = array_slice($dnesniChyby, -50);
    $result['php_log_path'] = $phpLogPath;
    $result['php_log_size'] = filesize($phpLogPath);
} else {
    $result['php_errors'] = ['Log soubor neexistuje: ' . $phpLogPath];
}

// 2. JavaScript Error Log (z databáze nebo souboru)
try {
    $pdo = getDbConnection();

    // Zkusit načíst z tabulky wgs_js_errors pokud existuje
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_js_errors'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("
            SELECT error_message, error_url, error_line, user_agent, created_at
            FROM wgs_js_errors
            WHERE DATE(created_at) = :datum
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute([':datum' => $dnesniDatum]);
        $result['js_errors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $result['js_errors'] = ['Tabulka wgs_js_errors neexistuje'];
    }
} catch (Exception $e) {
    $result['js_errors'] = ['Chyba při načítání JS chyb: ' . $e->getMessage()];
}

// 3. Zkusit načíst JS error log ze souboru
$jsLogPath = __DIR__ . '/../logs/js_errors.log';
if (file_exists($jsLogPath)) {
    $jsLogContent = file_get_contents($jsLogPath);
    $jsLines = explode("\n", $jsLogContent);

    $dnesniJsChyby = [];
    foreach ($jsLines as $line) {
        if (empty(trim($line))) continue;
        if (strpos($line, $dnesniDatum) !== false) {
            $dnesniJsChyby[] = $line;
        }
    }

    if (!empty($dnesniJsChyby)) {
        $result['js_errors_file'] = array_slice($dnesniJsChyby, -30);
    }
}

// 4. Souhrn
$result['souhrn'] = [
    'php_chyb_dnes' => count($result['php_errors']),
    'js_chyb_dnes' => is_array($result['js_errors']) ? count($result['js_errors']) : 0,
    'stav' => (count($result['php_errors']) == 0 || (count($result['php_errors']) == 1 && strpos($result['php_errors'][0], 'neexistuje') !== false))
        ? 'OK - Žádné chyby'
        : 'VAROVÁNÍ - Nalezeny chyby'
];

// 5. Systémové info
$result['system'] = [
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'error_reporting' => error_reporting(),
    'display_errors' => ini_get('display_errors'),
    'log_errors' => ini_get('log_errors'),
    'error_log_path' => ini_get('error_log')
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
