<?php
/**
 * ARCHIV: Debug verze video_api.php z 29.11.2025
 *
 * Tato verze obsahuje detailní loggování pro debugování 500 chyb.
 * Použití: Přejmenovat na video_api.php a zkopírovat do /api/
 *
 * Nalezená chyba: uploaded_by sloupec je INT, ale admin má ID 'ADMIN001' (string)
 * Řešení: is_numeric() kontrola před INSERT
 */

// DEBUG: Zachytit všechny chyby
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Vlastní error handler pro zachycení všech chyb
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("video_api.php ERROR [$errno]: $errstr in $errfile:$errline");
    return false;
});

// Zachytit fatální chyby
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("video_api.php FATAL: " . $error['message'] . " in " . $error['file'] . ":" . $error['line']);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Interní chyba serveru: ' . $error['message'],
                'debug' => [
                    'file' => basename($error['file']),
                    'line' => $error['line']
                ]
            ]);
        }
    }
});

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// Debug log - request info
error_log("video_api.php: Request received - Method: " . $_SERVER['REQUEST_METHOD'] . ", Action: " . ($_POST['action'] ?? $_GET['action'] ?? 'none'));

try {
    // Kontrola přihlášení
    $isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);

    if (!$isLoggedIn) {
        http_response_code(401);
        die(json_encode([
            'status' => 'error',
            'message' => 'Neautorizovaný přístup'
        ]));
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $pdo = getDbConnection();

    switch ($action) {

        case 'upload_video':
            error_log("video_api.php: [1] Začátek upload_video");

            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                error_log("video_api.php: CSRF validace selhala");
                http_response_code(403);
                die(json_encode(['status' => 'error', 'message' => 'Neplatný CSRF token']));
            }
            error_log("video_api.php: [2] CSRF OK");

            session_write_close();

            $claimId = $_POST['claim_id'] ?? null;
            $rawUserId = $_SESSION['user_id'] ?? null;
            $userId = (is_numeric($rawUserId)) ? (int)$rawUserId : null;
            error_log("video_api.php: [3] claimId=$claimId, rawUserId=$rawUserId, userId=" . ($userId ?? 'NULL'));

            // ... zbytek kódu s error_log() pro každý krok [4] až [21] ...

            break;

        // Další case bloky...
    }

} catch (PDOException $e) {
    error_log("Database error in video_api.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Chyba při zpracování požadavku']);
} catch (Exception $e) {
    error_log("Error in video_api.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Chyba serveru']);
}
?>
