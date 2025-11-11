<?php
/**
 * JavaScript Error Logging API
 * Přijímá JS chyby z frontendu a loguje je na server
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

try {
    // Načtení JSON dat
    $jsonData = file_get_contents('php://input');
    $error = json_decode($jsonData, true);

    if (!$error) {
        throw new Exception('Invalid JSON data');
    }

    // Formátování chybové zprávy
    $logMessage = "\n" . str_repeat('=', 80) . "\n";
    $logMessage .= "🔴 JAVASCRIPT ERROR\n";
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

    // Logování do souboru
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/js_errors.log';
    @file_put_contents($logFile, $logMessage, FILE_APPEND);

    echo json_encode([
        'success' => true,
        'message' => 'Error logged successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
