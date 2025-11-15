<?php
/**
 * JavaScript Error Logging API
 * Přijímá JS chyby z frontendu a loguje je na server
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

try {
    // BEZPEČNOST: DoS ochrana - rate limiting pro error logging
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitKey = "log_js_error_{$ip}";
    $maxAttempts = 20; // Max 20 errors za hodinu
    $timeWindow = 3600; // 1 hodina

    $rateLimit = checkRateLimit($rateLimitKey, $maxAttempts, $timeWindow);
    if (!$rateLimit['allowed']) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Příliš mnoho error reportů. Zkuste to za ' . ceil($rateLimit['retry_after'] / 60) . ' minut.',
            'retry_after' => $rateLimit['retry_after']
        ]);
        exit;
    }

    recordLoginAttempt($rateLimitKey);

    // Načtení JSON dat
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);

    if (!$data) {
        throw new Exception('Invalid JSON data');
    }

    // Podpora pro batched errors (nový formát) i single error (starý formát)
    $errors = [];
    if (isset($data['errors']) && is_array($data['errors'])) {
        // Nový formát: { errors: [...], count: N }
        $errors = $data['errors'];
    } else {
        // Starý formát: single error object
        $errors = [$data];
    }

    $logMessage = '';

    // Zpracovat každý error
    foreach ($errors as $error) {
        $logMessage .= "\n" . str_repeat('=', 80) . "\n";
        $logMessage .= "JAVASCRIPT ERROR\n";
        $logMessage .= str_repeat('=', 80) . "\n";
        $logMessage .= "Čas: " . date('Y-m-d H:i:s') . "\n";
        $logMessage .= "Typ: " . ($error['type'] ?? 'Unknown') . "\n";
        $logMessage .= "Zpráva: " . ($error['message'] ?? 'No message') . "\n";

        if (!empty($error['file'])) {
            $logMessage .= "Soubor: " . $error['file'] . "\n";
        }

        if (!empty($error['line'])) {
            $logMessage .= "Řádek: " . $error['line'];
            if (!empty($error['column'])) {
                $logMessage .= ":" . $error['column'];
            }
            $logMessage .= "\n";
        }

        if (!empty($error['stack'])) {
            $logMessage .= "\nStack Trace:\n";
            $logMessage .= str_repeat('-', 80) . "\n";
            $logMessage .= $error['stack'] . "\n";
        }

        $logMessage .= "\nClient Info:\n";
        $logMessage .= str_repeat('-', 80) . "\n";
        $logMessage .= "URL: " . ($error['url'] ?? 'N/A') . "\n";
        $logMessage .= "User Agent: " . ($error['userAgent'] ?? 'N/A') . "\n";
        $logMessage .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . "\n";

        if (isset($_SESSION['user_id'])) {
            $logMessage .= "User ID: " . $_SESSION['user_id'] . "\n";
            $logMessage .= "User: " . ($_SESSION['full_name'] ?? 'Unknown') . "\n";
        }

        $logMessage .= str_repeat('=', 80) . "\n\n";
    }

    // Logování do souboru
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            error_log('Failed to create log directory: ' . $logDir);
        }
    }

    $logFile = $logDir . '/js_errors.log';

    // BEZPEČNOST: DoS ochrana - max velikost log souboru (10MB)
    $maxLogSize = 10 * 1024 * 1024; // 10MB
    if (file_exists($logFile) && filesize($logFile) > $maxLogSize) {
        // Rotace logu - přejmenovat starý, začít nový
        $archiveFile = $logDir . '/js_errors_' . date('Y-m-d_H-i-s') . '.log';
        @rename($logFile, $archiveFile);
    }

    if (file_put_contents($logFile, $logMessage, FILE_APPEND) === false) {
    error_log('Failed to write file');
}

    $errorCount = count($errors);
    echo json_encode([
        'success' => true,
        'message' => $errorCount > 1
            ? "Logged {$errorCount} errors successfully"
            : 'Error logged successfully',
        'count' => $errorCount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
